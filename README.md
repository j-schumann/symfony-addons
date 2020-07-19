# vrok/symfony-addons

This is a library with additional classes for usage in combination with the
Symfony framework.

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

## PHPUnit helpers
### Using the NoTLS mail transport

Add the factory in your services_test.yaml
```yaml
    Vrok\SymfonyAddons\PHPUnit\Mailer\NoTlsTransportFactory:
        tags: ['mailer.transport_factory']
```

Use the `notls` schema in your env.test[.local]
```yaml
MAILER_DSN=notls://{user}:{passwd}@{host}:25
``` 

### Using the RefreshDatabaseTrait

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

### Using the AuthenticatedClientTrait

For use with an APIPlatform project with `lexik/jwt-authentication-bundle`.

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