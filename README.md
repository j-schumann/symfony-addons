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
Works as Symfony's own AtLeastOneOf constraint, but instead of returning a message like
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
This validator raises a violation if it detects trailing or leading whitespace in
the validated string.  
Uses a regex looking for `\s`, see `NoSurroundingWhitespaceValidatorTest` for details on
detected characters.

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

## Developer Doc
### composer.json dev
* _symfony/monolog-bundle_ is required for tests of the MonologAssertsTrait
* _symfony/yaml_ is required for loading the test config
* _api-platform/core_ and _vrok/doctrine-addons_ are required for testing the ApiPlatform filters

### Open ToDos
* tests for `ApiPlatformTestCase::testOperation`, `AuthenticatedClientTrait`, 
  `RefreshDatabaseTrait`
* tests for the ApiPlatform filters w/ a real database, for Postgres and MariaDB
* tests for the Bundle, simplify resources/services.xml