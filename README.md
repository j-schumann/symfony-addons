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
<td>postFormAuth</td>
<td>
if given (and 'email' is set) the JWT from Lexik is sent as 'application/x-www-form-urlencoded'
request in a form field.<br />
This is used for download endpoints where the browser should present the user
with the file to download instead of loading it into memory via Javascript.
(As we don't want to supply the token via GET to prevent security issues and
as we cannot set a cookie.)
</td>
<td>

`'postFormAuth' => 'bearer'`

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
Array of message classes, asserts that at least one instance of each given class 
has been dispatched to the message bus. An Element can also be an array of
[FQCN, callable], in that case the callback is called for each matching message
with that message as first parameter and the JSON response as second parameter,
to trigger additional assertions for the message.
</td>
<td>

```php
'dispatchedMessages' => [
  TenantCreatedMessage::class,

  [TenantCreatedMessage::class, function (object $message, array $data): void {
      self::assertSame($data['id'], $message->tenantId);
  }]
],
```

</td>
</tr>

<tr>
<td>dispatchedEvents</td>
<td>
Array of event names (may be class names), asserts that at least one instance of
each given event has been dispatched to Symfony's EventDispatcher.
</td>
<td>

```php
'dispatchedEvents' => [
  'kernel.response',
  ProjectPreCreateEvent::class,
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
are _purge_, _dropSchema_ and _dropDatabase_, for more details see `RefreshDatabaseTrait::$cleanupMethod`.

**Use _purge_** unless a test needs a genuinely fresh schema. The other two exist for that
case, not as a speedup: per booted kernel _purge_ only empties the tables and resets the
identities, where both others drop and recreate the whole schema.

#### Measurements

`bin/benchmark.sh` measures what one `bootKernel()` costs for every combination of
platform and cleanup method, and the _Refresh Benchmark_ workflow runs it in CI. The
numbers below come from that workflow: median milliseconds per `bootKernel()` over 200
boots per cell, on a GitHub-hosted `ubuntu-latest` runner (**4 CPU, 15 GB RAM**), against
this package's own 14 entity test schema. The first boot of each cell is excluded, as it
also creates the database and the schema.

| platform | purge (delete) | purge (truncate) | dropSchema | dropDatabase |
| --- | ---: | ---: | ---: | ---: |
| **on disk** | | | | |
| SQLite | 53.1 | 53.5 | 153.9 | 64.9 |
| MariaDB 12 | 107.4 | **54.8** | 412.2 | 376.5 |
| MySQL 9 | **121.0** | 309.4 | 753.2 | 521.6 |
| PostgreSQL 18 | **31.5** | 39.5 | 162.9 | 186.1 |
| SQL Server 2022 | 43.1 | 37.0 | 312.2 | see below |
| **on tmpfs** | | | | |
| SQLite | 7.5 | 7.3 | 19.7 | 12.0 |
| MariaDB 12 | **12.1** | 13.1 | 37.5 | 25.9 |
| MySQL 9 | **23.4** | 25.1 | 77.3 | 61.9 |
| PostgreSQL 18 | **23.3** | 23.5 | 68.3 | 78.5 |
| SQL Server 2022 | **16.0** | 16.1 | 120.2 | see below |

`dropDatabase` on SQL Server is not in the table: it needs more than 600 seconds for 200
boots, so more than 3 seconds per test, and the benchmark gives up on a cell at that point.
That is the measurement.

Three things to take from this:

* _purge_ is the cheapest method everywhere, usually by a factor of three to ten. The other
  two exist for tests that need a genuinely fresh schema, not as a speedup. Which of the
  two is second is not a given: `dropDatabase` beats `dropSchema` on MySQL and MariaDB and
  loses to it on PostgreSQL and SQLite.
* Putting the database on tmpfs is worth far more than the choice of cleanup method — 4 to
  9 times on disk-bound platforms. See below.
* `DB_PURGE_MODE` is not a one-way street. On MySQL, `delete` is 2.5x faster than
  `truncate`; on MariaDB it is the other way round, because MariaDB's `TRUNCATE` is roughly
  four times cheaper per table than MySQL's while the identity reset that `delete` requires
  costs the same on both.

How much any of this is worth depends on how many of your tests boot the kernel and on how
large your schema is: `dropSchema` and `dropDatabase` scale with the number of tables and
indices, `purge` with the number of tables alone.

#### Running the databases on tmpfs

Test databases are throwaway by definition, so there is no reason to write them to disk.
This is the single largest speedup available and costs nothing but a few lines.

With docker compose:

```yaml
services:
  mysql:
    image: mysql:9
    tmpfs:
      - /var/lib/mysql:rw,size=1g

  mariadb:
    image: mariadb:12
    tmpfs:
      - /var/lib/mysql:rw,size=1g

  postgres:
    image: postgres:18
    tmpfs:
      # note the version in the path, postgres:18 does not use
      # /var/lib/postgresql/data any more. Mounting the wrong path succeeds and
      # silently leaves the database on disk.
      - /var/lib/postgresql/18/docker:rw,size=1g

  mssql:
    image: kcollins/mssql:latest
    tmpfs:
      - /var/opt/mssql/data:rw,size=2g
```

In GitHub Actions, a service container takes no `command`, and `options` are passed to
`docker create`, so `--tmpfs` belongs there:

```yaml
    services:
      mysql:
        image: mysql:9
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: db_test
        options: >-
          --tmpfs /var/lib/mysql:rw,size=1g
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
        ports:
          - 3306:3306
```

For SQLite, point the DSN at a tmpfs path instead, e.g.
`sqlite:////dev/shm/test.db` — four slashes, three would make the path relative.

**Relaxing durability on top of this buys nothing.** Turning off the safety guarantees is
the usual next step, and it was measured: `--innodb-doublewrite=OFF
--innodb-flush-log-at-trx-commit=2 --skip-log-bin` for MySQL/MariaDB, `-c fsync=off
-c synchronous_commit=off -c full_page_writes=off` for PostgreSQL, and
`ALTER DATABASE model SET DELAYED_DURABILITY = FORCED` for SQL Server. On tmpfs every one
of those was flat or slightly slower than leaving the defaults alone, PostgreSQL by about a
quarter — once the data directory is in RAM there is no disk write left for them to skip.
Save the configuration and keep the defaults.

With the cleanup method _purge_, the ENV `DB_PURGE_MODE` selects how the tables are
emptied on MySQL/MariaDB. Allowed values are _delete_ (default) and _truncate_:

* _delete_ empties the tables with `DELETE` and resets the auto-increment counters
  afterwards. On InnoDB, `TRUNCATE` is a DDL operation that drops and recreates the
  tablespace file of each table, for each test, which can dominate the runtime of a
  database-heavy test suite.
* _truncate_ restores the previous behavior. Use it for tests that insert very large
  datasets before the cleanup, as `DELETE` is O(rows) where `TRUNCATE` is O(1).

Measured on a 995 test suite with `paratest -p3` against MySQL 8.4: 38.0 s with _delete_
versus 325.9 s with _truncate_.

On MariaDB the comparison comes out the other way, see the table above: its `TRUNCATE` is
about four times cheaper per table than MySQL's, while the identity reset that _delete_
makes necessary costs the same on both, so _delete_ only pays off once a schema has
considerably more tables than identity columns. If your suite runs on MariaDB, measure
before keeping the default.

The setting has no effect on other platforms: SQLServer cannot `TRUNCATE` tables that
are referenced by a foreign key and always uses `DELETE`, PostgreSQL and SQLite have no
expensive `TRUNCATE` to avoid.

Emptying a table does not reset its identity generator on every platform (only a
`TRUNCATE` on MySQL/MariaDB does), so the purge resets them itself, for every table the
mapping declares with an `IDENTITY` generator. Records created in a test therefore
always receive the same IDs, on every platform.

Both ENV variables reject unknown values with an `InvalidArgumentException` instead of
silently falling back to the default.

Please note that the ENV variables are read from `$_ENV`, so they have to be set with
`<env name="DB_CLEANUP_METHOD" value="purge"/>` in your _phpunit.xml.dist_, a
`<server .../>` element is silently ignored. Also, without `force="true"` an `<env>`
element does not overwrite a variable that is already set in the real environment.

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
one of the specified properties. The properties can also be of relations, e.g.
`child.name`. All specified properties must be string types (varchar, text etc.)
or JSON fields (Postgres only), in that case the JSON is cast to string first.

```php
#[ApiFilter(
    filterClass: SimpleSearchFilter::class,
    properties: [
        'description',
        'name',
        'slug',
        'parent.title',
        'children.content',
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

### ContainsFilter

Postgres-only: Filters entities by their jsonb fields, if they contain the search parameter,
using the `@>` operator. For example for filtering for numbers in an array.

```php
#[ApiFilter(filterClass: ContainsFilter::class, properties: ['numbers'])]
```

Requires CONTAINS as defined Doctrine function, provided by `vrok/doctrine-addons`:
```yaml
doctrine:
  orm:
    dql:
      string_functions:
        CONTAINS: Vrok\DoctrineAddons\ORM\Query\AST\ContainsFunction
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

Adding this bundle to the `bundles.php` registers the `MultipartDecoder`
to allow handling of file uploads with additional data (e.g. in ApiPlatform):

```php
    Vrok\SymfonyAddons\VrokSymfonyAddonsBundle::class => ['all' => true],
```

The decoder is automatically called for `multipart` requests and
simply returns all POST parameters and uploaded files together. To enable
this add the `multipart` format to your `config\api_platform.yaml`:

```yaml
api_platform:
    formats:
        multipart: ['multipart/form-data']
```

## FormDecoder

Adding this bundle to the `bundles.php` registers the `FormDecoder`
to allow handling HTML form data in ApiPlatform:

```php
    Vrok\SymfonyAddons\VrokSymfonyAddonsBundle::class => ['all' => true],
```

The decoder is automatically called for `form` requests and
simply returns all POST parameters. To enable this add the `form` format to your
`config\api_platform.yaml`:

```yaml
api_platform:
    formats:
      form: ['application/x-www-form-urlencoded']
```

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

## Experimental / Additional Features
### NamedArgumentsFromArrayRector
This Rector allows migrating function calls that previously used an array of
options (like `ApiPlatformTestCase#testOperation`) to use named arguments instead.

This can be configured to target static functions, static class methods or instance
methods.  
Example for the `rector.php`:
```php
use Vrok\SymfonyAddons\Rector\NamedArgumentsFromArrayRector;

return RectorConfig::configure()
    ->withConfiguredRule(NamedArgumentsFromArrayRector::class, [
        'targets' => [
            [ApiPlatformTestCase::class, 'testOperation'],
        ],
    ])
;
```

This converts

```php
$this->testOperation([
    'uri' => '/test',
    'requiredKeys' => [
        'success',
        'message',
    ],
    'dispatchedEvents' => ['failedEvent'],
]);
```

to

```php
$this->testOperation(uri: '/test', requiredKeys: [
    'success',
    'message',
], dispatchedEvents: ['failedEvent']);
```

Attention: This Rector is not yet unit-tested, please report any bugs you find!

### WrapNamedMethodArgumentsFixer

This Fixer for php-cs-fixer allows wrapping long lines of function calls with
named arguments to contain one argument per line, respecting multiline argument
values like arrays.  
This can be used to improve readability, e.g. after using the `NamedArgumentsFromArrayRector`
which puts multiple arguments on the same line.

It allows configuring the maximum number of arguments to keep on a single
line, each call with more named arguments will be wrapped.

Register the Fixer in your `.php-cs-fixer.dist.php` and add a rule:
```php
return $config
    ->registerCustomFixers([
        new Vrok\SymfonyAddons\PhpCsFixer\WrapNamedMethodArgumentsFixer(),
    ])
    ->setRules([
        "VrokSymfonyAddons/wrap_named_method_arguments" => [
            'max_arguments' => 2,
        ],

        // your custom formatting rules:
        '@Symfony'               => true,
        [...]
    ])
;
```

This converts
```php
$this->testOperation(uri: '/test', requiredKeys: [
    'success',
    'message',
], dispatchedEvents: ['failedEvent']);
```

to
```php
$this->testOperation(
    uri: '/test',
    requiredKeys: [
        'success',
        'message',
    ],
    dispatchedEvents: ['failedEvent']
);
```

Attention: Formatting (indentation) is only fixed after the arguments were wrapped,
by your specification of `method_argument_space` and `array_indentation` (or rulesets
containing those, like `@Symfony`).  
This fixer is not yet unit-tested, please report any bugs you find!

## Developer Doc
### composer.json require

* _symfony/yaml_ is required for loading the bundle & test config

### composer.json dev

* _doctrine/doctrine-fixtures-bundle_ is required for tests of the ApiPlatformTestCase
* _symfony/browser-kit_ is required for tests of the MultipartDecoder
* _symfony/mailer_ is required for tests of the AutoSenderSubscriber
* _symfony/doctrine-messenger_ is required for tests of the ResetLoggerSubscriber
* _symfony/monolog-bundle_ is required for tests of the MonologAssertsTrait and ResetLoggerSubscriber
* _symfony/string_ is required for API Platform's inflector
* _symfony/twig-bundle_ is required for tests of the FormatBytesExtension
* _symfony/workflow_ is required for tests of the WorkflowHelper and PropertyMarkingStore
* _monolog/monolog_ must be at least v3 for `Monolog\Level`
* _api-platform/core_ and _vrok/doctrine-addons_ are required for testing the ApiPlatform filters

### Open ToDos
* tests for QueryBuilderHelper
* tests for NamedArgumentsFromArrayRector
* tests for WrapNamedMethodArgumentsFixer
* compare code to ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper