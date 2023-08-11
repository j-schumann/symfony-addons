# vrok/symfony-addons

This is a library with additional classes for usage in combination with the
Symfony framework.

[![CI Status](https://github.com/j-schumann/symfony-addons/actions/workflows/ci.yaml/badge.svg)](https://github.com/j-schumann/symfony-addons/actions)
[![Coverage Status](https://coveralls.io/repos/github/j-schumann/symfony-addons/badge.svg?branch=main)](https://coveralls.io/github/j-schumann/symfony-addons?branch=main)

## Mailer helpers
### Automatically set a sender address

We want to replace setting the sender via mailer.yaml as envelope
(@see https://symfonycasts.com/screencast/mailer/event-global-recipients)
as this would still require each mail to have a FROM address set and also
doesn't allow us to set a sender name.

config/services.yaml:
```yaml
    Vrok\SymfonyAddons\EventSubscriber\AutoSenderSubscriber:
        arguments:
            $sender: "%env(MAILER_SENDER)%"
```

.env[.local]:
```yaml
MAILER_SENDER="Change Me <your@email>"
```

## Messenger helpers
### Resetting the logger before/after a message

We want to group all log entries belonging to a single message to be grouped
with a distinct UID and to flush a buffer logger after a message was processed
(successfully or failed), to immediately see the entries in the log:

config/services.yaml:
```yaml
    # add a UID to the context, same UID for each HTTP request or console command
    # and with the event subscriber also for each message 
    Monolog\Processor\UidProcessor:
        tags:
            - { name: monolog.processor, handler: logstash }

    # resets the UID when a message is received, flushed a buffer after a
    # message was handled. Add this multiple times if you want to flush more
    # channels, e.g. messenger
    app.event.reset_app_logger:
        class: Vrok\SymfonyAddons\EventSubscriber\ResetLoggerSubscriber
        tags:
            - { name: monolog.logger, channel: app }
```

## Validators

### AtLeastOneOf
Works like Symfony's own AtLeastOneOf constraint, but instead of returning a message like
`This value should satisfy at least ...` it returns the message of the last failed validation.
Can be used for obviously optional form fields where only simple messages should be
displayed when `AtLeastOne` is used with `Blank` as first constraint.  
See `AtLeastOneOfValidatorTest` for examples. 

### NoHtml
This validator tries to detect if a string contains HTML, to allow only plain text.  
See `NoHtmlValidatorTest` for examples of allowed / forbidden values.

### NoLineBreak
This validator raises a violation if it detects one or more linebreak characters in 
the validated string.  
Detects unicode linebreaks, see `NoLineBreaksValidatorTest` for details.

### NoSurroundingWhitespace
This validator raises a violation if it detects trailing or leading whitespace or
newline characters in the validated string. Linebreaks and spaces are valid within the string.  
Uses a regex looking for `\s` and `\R`, see `NoSurroundingWhitespaceValidatorTest` 
for details on detected characters.

### PasswordStrength
This validator evaluates the strength of a given password string by determining its entropy
instead of requireing something like "must contain at least one uppercase & one digit
& one special char".  
Allows to set a `minStrength` to vary the requirements.
See `Vrok\SymfonyAddons\Helper\PasswordStrength` for details on the calculation.

## PHPUnit helpers

### Using the ApiPlatformTestCase

This class is used to test ApiPlatform endpoints by specifying input data
and verifying the response data. It combines the traits documented below
to refresh the database before each test, optionally create authenticated
requests and check for created logs / sent emails / dispatched messages.
It allows to easily check for expected response content, allowed or forbidden
keys in the data or to verify against a given schema.

Requires "symfony/browser-kit" & "symfony/http-client" to be installed
(and of cause ApiPlatform).

```php
<?php

use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;

class AuthApiTest extends ApiPlatformTestCase
{
    public function testAuthRequiresPassword(): void
    {
        $this->testOperation([
            'uri'            => '/authentication_token',
            'method'         => 'POST',
            'requestOptions' => ['json' => ['username' => 'fakeuser']],
            'responseCode'   => 400,
            'contentType'    => 'application/json',
            'json'           => [
                'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
                'title'  => 'An error occurred',
                'detail' => 'The key "password" must be provided.',
            ],
        ]);
    }
}
```

<table>
<tr>
<th>Option</th>
<th>Usage</th>
<th>Example</th>
</tr>

<tr>
<td>skipRefresh</td>
<td>
 if set & true the database will not be refreshed before the request,
 to allow using two calls to `testOperation` in one testcase, e.g. uploading
 & deleting a file with two requests
</td>
<td>

`'skipRefresh' => true`

</td>
</tr>

<tr>
<td>prepare</td>
<td>Callable, to be executed _after_ the kernel was booted and the DB refreshed, but _before_ the request is made</td>
<td>

```php
'prepare' => static function (ContainerInterface $container, array &$params): void {
      $em = $container->get('doctrine')->getManager();

      $log = new ActionLog();
      $log->action = ActionLog::FAILED_LOGIN;
      $log->ipAddress = '127.0.0.1';
      $em->persist($log);
      $em->flush();

      $params['requestOptions']['query']['id'] = $log->id; 
}
```

</td>
</tr>

<tr>
<td>uri</td>
<td>
 the URI / endpoint to call
</td>
<td>

`'uri' => '/users'`

</td>
</tr>

<tr>
<td>iri</td>
<td>

an array of `[classname, [field => value]]` that is used to fetch a record
from the database, determine its IRI, which is then used as URI for the request

</td>
<td>

`'iri' => [User::class, [email => 'test@test.de']]`

</td>
</tr>

<tr>
<td>email</td>
<td>
if given, tries to find a User with that email and sends
the request authenticated as this user with lexikJWT bundle 
</td>
<td>

`'email' => 'test@test.de'`

</td>
</tr>

<tr>
<td>method</td>
<td>

HTTP method for the request, defaults to GET. If PATCH is used, the content-type
header is automatically set to `application/merge-patch+json` (if not already
specified)

</td>
<td>

`'method' => 'POST'`

</td>
</tr>

<tr>
<td>requestOptions</td>
<td>
options for the HTTP client, e.g. query parameters or basic auth
</td>
<td>

```php
'requestOptions' => [
  'json' => [
    'username' => 'Peter',
    'email'    => 'peter@example.com',
  ],
  
  // or:
  'query' =>  [
    'order' => ['createdAt' => 'asc'],
  ],
  
  // or:
  'headers' => ['content-type' => 'application/json'],
]
```

</td>
</tr>

<tr>
<td>files</td>
<td>

An array of one or more files to upload. The files will be copied to a temp file,
and wrapped in an `UploadedFile`, so the tested application can move/delete it
as it needs to. If this option is used, the content-type header is automatically
set to `multipart/form-data` (if not already specified)

</td>
<td>

```php
'files' => [
  'picture' => [
    'path'         => '/path/to/file.png',
    'originalName' => 'mypicture.png',
    'mimeType'     => 'image/png',
  ]
]
```

</td>
</tr>

<tr>
<td>responseCode</td>
<td>
asserts that the received status code matches
</td>
<td>

`'responseCode' => 201`

</td>
</tr>

<tr>
<td>contentType</td>
<td>
asserts that the received content type header matches
</td>
<td>

`'contentType' => 'application/ld+json; charset=utf-8'`

</td>
</tr>

<tr>
<td>json</td>
<td>
asserts that the returned content is JSON and contains the given array as subset
</td>
<td>

```php
'json' => [
  'username' => 'Peter',
  'email'    => 'peter@example.com',
]
```

</td>
</tr>

<tr>
<td>requiredKeys</td>
<td>
asserts the dataset contains the list of keys. Used for elements where the value 
is not known in advance, e.g. ID, slug, timestamps. Can be nested.
</td>
<td>

```php
'requiredKeys' => ['hydra:member'][0]['id', '@id']
```

</td>
</tr>

<tr>
<td>forbiddenKeys</td>
<td>
like requiredKeys, but the dataset may not contain those
</td>
<td>

```php
'forbiddenKeys' => ['hydra:member'][0]['password', 'salt']
```

</td>
</tr>

<tr>
<td>schemaClass</td>
<td>
Asserts that the received response matches the JSON schema for the given class.
If the `iri` parameter is used or the request method is *not* GET, the item
schema is used. Else the collection schema is used.
</td>
<td>

```php
'schemaClass' => User::class,
```

</td>
</tr>

<tr>
<td>createdLogs</td>
<td>
array of entries, asserts the messages to be present (with the correct log level)
in the monolog handlers after the operation ran
</td>
<td>

```php
'createdLogs'    => [
  ['Failed to validate the provider', Level::Error],
],
```

</td>
</tr>

<tr>
<td>emailCount</td>
<td>
asserts this number of emails to be sent via the mailer after the operation was executed
</td>
<td>

```php
 'emailCount' => 2,
```

</td>
</tr>

<tr>
<td>messageCount</td>
<td>
asserts this number of messages to be dispatched to the message bus
</td>
<td>

```php
 'messageCount' => 2,
```

</td>
</tr>

<tr>
<td>dispatchedMessages</td>
<td>
array of message classes, asserts that at least one instance of each given class 
has been dispatched to the message bus
</td>
<td>

```php
'dispatchedMessages' => [
  TenantCreatedMessage::class,
],
```

</td>
</tr>

</table>


### Using the RefreshDatabaseTrait

(Re-)Creates the DB schema for each test, removes existing data and fills the tables
with predefined fixtures.
Install `doctrine/doctrine-fixtures-bundle` and create fixtures,
the trait uses the _test_ group per default.

Just include the trait in your testcase and call `bootKernel()` or
`createClient()`, e.g. in the setUp method:
```php
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class DatabaseTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

}
```

Optionally define which fixtures to use for this test class:

```php
    protected static $fixtureGroups = ['test', 'other'];
```

Supports setting the cleanup method after tests via `DB_CLEANUP_METHOD`. Allowed values
are _purge_ and _dropSchema_, for more details see `RefreshDatabaseTrait::$cleanupMethod`.

### Using the AuthenticatedClientTrait

For use with an APIPlatform project with `lexik/jwt-authentication-bundle`.
Creates a JWT for the user given by its unique email, username etc. and adds it
to the test client's headers.

Include the trait in your testcase and call `createAuthenticatedClient`:
 ```php
use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;

class ApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;

    public function testAccess(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email']
        ]);

        $iri = $this->findIriBy(User::class, ['id' => 1]);
        $client->request('GET', $iri);
        self::assertResponseIsSuccessful();
    }
}
 ```

### Using the MonologAssertsTrait

For use with an Symfony project using the monolog-bundle.  
Requires `monolog/monolog` of v3.0 or higher.

Include the trait in your testcase and call `prepareLogger` before triggering the
action that should create logs and use `assertLoggerHasMessage` afterwards to check
if a log record was created with the given message & severity:
 ```php
use Monolog\Level;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\MonologAssertsTrait;

class LoggerTest extends KernelTestCase
{
    use MonologAssertsTrait;

    public function testLog(): void
    {      
        self::prepareLogger();

        $logger = static::getContainer()->get(LoggerInterface::class);
        $logger->error('Failed to do something');
        
        self::assertLoggerHasMessage('Failed to do something', Level::Error);
    }
}
 ```

## Workflow helpers

Require `symfony/workflow`.

### PropertyMarkingStore

Can be used instead of the default `MethodMarkingStore`, for entities 
& properties without Setter/Getter.

workflow.yaml:
```yaml
framework:
  workflows:
    application_state:
      type: state_machine
      marking_store:
        # We need to use a service as there is no option to register a new "type"
        service: workflow.application.marking_store
``` 

services.yaml:
```yaml
    # When using the "service" option, all other settings like "property: state"
    # are ignored in the workflow.yaml -> That's why we need a service definition
    # with the correct arguments.
    workflow.application.marking_store:
      class: Vrok\SymfonyAddons\Workflow\PropertyMarkingStore
      arguments: [true, 'state']
``` 

### WorkflowHelper

Allows to get an array of available transitions and their blockers,
can be used to show the user what transitions are possible from the current
state and/or why a transition is currently blocked.

```php
    public function __invoke(
        Entity $data
        WorkflowInterface $entityStateMachine,
    ): array
    {
      $result = $data->toArray();
      
      $result['transitions'] = WorkflowHelper::getTransitionList($data, $entityStateMachine);
      
      return $result;
    }
```

```
'publish' => [
    'blockers' => [
        TransitionBlocker::UNKNOWN => 'Title is empty!',
    ],
],
```

## Cron events
Adding this bundle to the `bundles.php` registers three new CLI commands:
```php
    Vrok\SymfonyAddons\VrokSymfonyAddonsBundle::class => ['all' => true],
```
```shell
bin/console cron:hourly
bin/console cron:daily
bin/console cron:monthly
```

When these are called, they trigger an event (`CronHourlyEvent`, `CronDailyEvent`,
`CronMonthlyEvent`) that can be used by one ore more event listeners/subscribers to do
maintenance, push messages to the messenger etc.
It is your responsibility to execute these commands via crontab correctly!

```php
use Vrok\SymfonyAddons\Event\CronDailyEvent;

class MyEventSubscriber implements EventSubscriberInterface
    public static function getSubscribedEvents(): array
    {
        return [
            CronDailyEvent::class => [
                ['onCronDaily', 100],
            ],
        ];
    }
}
```

## ApiPlatform Filters

### SimpleSearchFilter

Selects entities where the search term is found (case insensitive) in at least
one of the specified properties. All specified properties type must be string.

```php
#[ApiFilter(
    filterClass: SimpleSearchFilter::class,
    properties: [
        'description',
        'name',
        'slug',
    ],
    arguments: ['searchParameterName' => 'pattern']
)]
```

Requires CAST as defined Doctrine function, e.g. by `vrok/doctrine-addons`:
```yaml
doctrine:
  orm:
    dql:
      string_functions:
        CAST: Vrok\DoctrineAddons\ORM\Query\AST\CastFunction
```

### JsonExistsFilter

Postgres-only: Filters entities by their jsonb fields, if they contain the search parameter,
using the `?` operator. For example for filtering Users by their role, to prevent accidental
matching with overlapping role names (e.g. ROLE_ADMIN and ROLE_ADMIN_BLOG) when searching as
text with `WHERE roles LIKE '%ROLE_ADMIN%'`.

```php
#[ApiFilter(filterClass: JsonExistsFilter::class, properties: ['roles'])]
```

Requires JSON_CONTAINS_TEXT as defined Doctrine function, provided by `vrok/doctrine-addons`:
```yaml
doctrine:
  orm:
    dql:
      string_functions:
        JSON_CONTAINS_TEXT: Vrok\DoctrineAddons\ORM\Query\AST\JsonContainsTextFunction
```

## MultipartDecoder

Adding this bundle to the `bundles.php` registers the MultipartDecoder
to allow handling of file uploads with additional data (e.g. in ApiPlatform):

```php
    Vrok\SymfonyAddons\VrokSymfonyAddonsBundle::class => ['all' => true],
```

The decoder is automatically called for `multipart/form-data` requests and
simply returns all POST parameters and uploaded files together.

## Twig Extensions

Adding this bundle to the `bundles.php` together with the `symfony/twig-bundle`
registers the new extension:
```php
    Vrok\SymfonyAddons\VrokSymfonyAddonsBundle::class => ['all' => true],
```

### FormatBytes

Converts bytes to human-readable notation (supports up to TiB).  
This extension is auto-registered.  
In your Twig template:
```
  {{ attachment.filesize|formatBytes }}
```

Outputs: 9.34 MiB

## Developer Doc
### composer.json require

* _symfony/yaml_ is required for loading the bundle & test config

### composer.json dev

* _doctrine/doctrine-fixtures-bundle_ is required for tests of the ApiPlatformTestCase
* _symfony/browser-kit_ is required for tests of the MultipartDecoder
* _symfony/mailer_ is required for tests of the AutoSenderSubscriber
* _symfony/doctrine-messenger_ is required for tests of the ResetLoggerSubscriber
* _symfony/monolog-bundle_ is required for tests of the MonologAssertsTrait and ResetLoggerSubscriber
* _symfony/phpunit-bridge_ must be at least v6.2.3 to prevent"Call to undefined method Doctrine\Common\Annotations\AnnotationRegistry::registerLoader()" 
* _symfony/twig-bundle_ is required for tests of the FormatBytesExtension
* _symfony/workflow_ is required for tests of the WorkflowHelper and PropertyMarkingStore
* _monolog/monolog_ must be at least v3 for `Monolog\Level`
* _api-platform/core_ and _vrok/doctrine-addons_ are required for testing the ApiPlatform filters

### Open ToDos
* tests for `AuthenticatedClientTrait`, 
  `RefreshDatabaseTrait`
* `ApiPlatformTestCase` should no longer use `AuthenticatedClientTrait` but
  use its own getJWT() and make the User class configurable like the fixtures.
* tests for QueryBuilderHelper
* compare code to ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper