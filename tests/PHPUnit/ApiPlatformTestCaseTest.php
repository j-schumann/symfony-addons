<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;

class ApiPlatformTestCaseTest extends KernelTestCase
{
    public static function hasKeysSuccessProvider(): \Iterator
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
        yield [$data, ['simple']];
        yield [$data, ['simple', 'nested']];
        yield [$data, ['nested' => ['deep' => 'value']]];
        yield [$data, ['nested' => ['empty']]];
    }

    #[DataProvider('hasKeysSuccessProvider')]
    public function testAssertDatasetHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    public static function hasKeysThrowsProvider(): \Iterator
    {
        // key not found
        yield [[], ['fail1'], 'Dataset does not have key [fail1]!'];
        // not all keys found
        yield [['nested' => ['value']], ['simple', 'nested'], 'Dataset does not have key [simple]!'];
        // nested key's parent is no array
        yield [['nested' => 'string'], ['nested' => ['deep']], 'Key [nested] is expected to be an array!'];
        // nested key not found
        yield [['nested' => ['value']], ['nested' => ['deep']], 'Dataset does not have key [nested][deep]!'];
    }

    #[DataProvider('hasKeysThrowsProvider')]
    public function testAssertDatasetHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($msg);
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    public static function notHasKeysSuccessProvider(): \Iterator
    {
        yield [['irrelevant' => 1], ['simple']];
        yield [['simple'], ['simple']];
        yield [['simple'], ['simple', 'nested']];
        yield [['nested' => ['deep' => ['irrelevant' => 1]]], ['nested' => ['deep' => 'value']]];
        yield [['nested' => ['deep' => null]], ['nested' => ['deep' => 'value']]];
        yield [['nested' => []], ['nested' => ['deep' => 'value']]];
    }

    #[DataProvider('notHasKeysSuccessProvider')]
    public function testAssertDatasetNotHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }

    public static function notHasKeysThrowsProvider(): \Iterator
    {
        yield [['fail1' => true], ['fail1'], 'Dataset should not have key [fail1]!'];
        yield [['nested' => ['value']], ['simple', 'nested'], 'Dataset should not have key [nested]!'];
        yield [['nested' => 'string'], ['nested' => ['deep']], 'Key [nested] is expected to be an array or null!'];
        // nested key not found
        yield [['nested' => ['deep' => 'value']], ['nested' => ['deep']], 'Dataset should not have key [nested][deep]!'];
    }

    #[DataProvider('notHasKeysThrowsProvider')]
    public function testAssertDatasetNotHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($msg);
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }
}
