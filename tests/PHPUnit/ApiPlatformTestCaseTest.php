<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\AssertionFailedError;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;

/**
 * @group ApiPlatformTestCase
 */
class ApiPlatformTestCaseTest extends KernelTestCase
{
    public static function hasKeysSuccessProvider(): array
    {
        $data = [
            'simple' => 1,
            'nested' => [
                'value' => 2,
                'deep'  => [
                    'value' => 3,
                ],
                'empty' => null,
            ],
        ];

        return [
            [$data, ['simple']],
            [$data, ['simple', 'nested']],
            [$data, ['nested' => ['deep' => 'value']]],
            [$data, ['nested' => ['empty']]],
        ];
    }

    /**
     * @dataProvider hasKeysSuccessProvider
     */
    public function testAssertDatasetHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    public static function hasKeysThrowsProvider(): array
    {
        return [
            // key not found
            [[], ['fail1'], 'Dataset does not have key [fail1]!'],

            // not all keys found
            [['nested' => ['value']], ['simple', 'nested'], 'Dataset does not have key [simple]!'],

            // nested key's parent is no array
            [['nested' => 'string'], ['nested' => ['deep']], 'Key [nested] is expected to be an array!'],

            // nested key not found
            [['nested' => ['value']], ['nested' => ['deep']], 'Dataset does not have key [nested][deep]!'],
        ];
    }

    /**
     * @dataProvider hasKeysThrowsProvider
     */
    public function testAssertDatasetHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($msg);
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    public static function notHasKeysSuccessProvider(): array
    {
        return [
            [['irrelevant' => 1], ['simple']],
            [['simple'], ['simple']],
            [['simple'], ['simple', 'nested']],
            [['nested' => ['deep' => ['irrelevant' => 1]]], ['nested' => ['deep' => 'value']]],
            [['nested' => ['deep' => null]], ['nested' => ['deep' => 'value']]],
            [['nested' => []], ['nested' => ['deep' => 'value']]],
        ];
    }

    /**
     * @dataProvider notHasKeysSuccessProvider
     */
    public function testAssertDatasetNotHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }

    public static function notHasKeysThrowsProvider(): array
    {
        return [
            [['fail1' => true], ['fail1'], 'Dataset should not have key [fail1]!'],

            [['nested' => ['value']], ['simple', 'nested'], 'Dataset should not have key [nested]!'],

            [['nested' => 'string'], ['nested' => ['deep']], 'Key [nested] is expected to be an array or null!'],

            // nested key not found
            [['nested' => ['deep' => 'value']], ['nested' => ['deep']], 'Dataset should not have key [nested][deep]!'],
        ];
    }

    /**
     * @dataProvider notHasKeysThrowsProvider
     */
    public function testAssertDatasetNotHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($msg);
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }
}
