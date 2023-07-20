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

Adding this bundle to the `bundles.php` registers the new extension:
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

* _symfony/browser-kit_ is required for tests of the MultipartDecoder
* _symfony/mailer_ is required for tests of the AutoSenderSubscriber
* _symfony/doctrine-messenger_ is required for tests of the ResetLoggerSubscriber
* _symfony/monolog-bundle_ is required for tests of the MonologAssertsTrait and ResetLoggerSubscriber
* _symfony/twig-bundle_ is required for tests of the FormatBytesExtension
* _symfony/workflow_ is required for tests of the WorkflowHelper and PropertyMarkingStore
* _api-platform/core_ and _vrok/doctrine-addons_ are required for testing the ApiPlatform filters

### Open ToDos
* tests for `ApiPlatformTestCase::testOperation`, `AuthenticatedClientTrait`, 
  `RefreshDatabaseTrait`
* tests for QueryBuilderHelper
* compare code to ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper