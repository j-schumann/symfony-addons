<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\PHPUnit;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Psr\Container\ContainerInterface;
use RuntimeException;

trait AuthenticatedClientTrait
{
    /**
     * Creates a client to access the api that uses a JWT to authenticate as
     * the user specified by one ore more identifying properties, e.g. email.
     */
    public static function createAuthenticatedClient(array $findUserBy): Client
    {
        // boots the kernel, initializes static::$container
        $client = static::createClient();

        $token = static::getJWT(static::getContainer(), $findUserBy);
        $client->setDefaultOptions([
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $token),
            ],
        ]);

        return $client;
    }

    /**
     * Generates a JWT for the user given by its identifying property, e.g. email.
     */
    protected static function getJWT(ContainerInterface $container, array $findUserBy): string
    {
        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy($findUserBy);
        if (!$user) {
            throw new RuntimeException('User specified for JWT authentication was not found, please check your test database/fixtures!');
        }

        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        return $jwtManager->create($user);
    }
}
