# vrok/symfony-addons

This is a library with additional classes for usage in combination with the
Symfony framework.

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