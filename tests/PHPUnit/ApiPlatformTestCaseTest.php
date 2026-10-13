<?php

namespace Vrok\SymfonyAddons\Tests\PHPUnit;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\ApiPlatformTestCase;

final class ApiPlatformTestCaseTest extends KernelTestCase
{
    /**
     * @return \Iterator<string, array{array<mixed>, array<mixed>}>
     */
    public static function hasKeysSuccessProvider(): \Iterator
    {
        $data = [
            'nested' => [
                'deep'  => [
                    'value' => 3,
                ],
                'empty' => null,
                'value' => 2,
            ],
            'simple' => 1,
        ];
        yield 'simple key' => [$data, ['simple']];
        yield 'multiple keys' => [$data, ['simple', 'nested']];
        yield 'nested key' => [$data, ['nested' => ['deep' => 'value']]];
        yield 'nested key with null value' => [$data, ['nested' => ['empty']]];
    }

    /**
     * @param array<string, array<string, array<string, int>|int|null>|int> $data
     * @param array<int|string, array<int|string, string>|string>           $keys
     */
    #[DataProvider('hasKeysSuccessProvider')]
    public function testAssertDatasetHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    /**
     * @return \Iterator<string, array{array<mixed>, array<mixed>, string}>
     */
    public static function hasKeysThrowsProvider(): \Iterator
    {
        yield 'key not found' => [[], ['fail1'], 'Dataset does not have key [fail1]!'];
        yield 'not all keys found' => [
            ['nested' => ['value']],
            ['simple', 'nested'],
            'Dataset does not have key [simple]!',
        ];
        yield 'parent of the nested key is no array' => [
            ['nested' => 'string'],
            ['nested' => ['deep']],
            'Key [nested] is expected to be an array!',
        ];
        yield 'nested key not found' => [
            ['nested' => ['value']],
            ['nested' => ['deep']],
            'Dataset does not have key [nested][deep]!',
        ];
    }

    /**
     * @param array<string, string[]|string>     $data
     * @param array<int|string, string[]|string> $keys
     */
    #[DataProvider('hasKeysThrowsProvider')]
    public function testAssertDatasetHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains($msg);
        ApiPlatformTestCase::assertDatasetHasKeys($keys, $data);
    }

    /**
     * @return \Iterator<string, array{array<mixed>, array<mixed>}>
     */
    public static function notHasKeysSuccessProvider(): \Iterator
    {
        yield 'key not found' => [['irrelevant' => 1], ['simple']];
        yield 'value instead of key' => [['simple'], ['simple']];
        yield 'values instead of keys' => [['simple'], ['simple', 'nested']];
        yield 'nested key not found' => [
            ['nested' => ['deep' => ['irrelevant' => 1]]],
            ['nested' => ['deep' => 'value']],
        ];
        yield 'parent of the nested key is null' => [
            ['nested' => ['deep' => null]],
            ['nested' => ['deep' => 'value']],
        ];
        yield 'parent of the nested key is empty' => [
            ['nested' => []],
            ['nested' => ['deep' => 'value']],
        ];
        yield 'parent of the nested key not found' => [
            ['irrelevant' => 1],
            ['simple', 'nested' => ['deep' => 'value']],
        ];
    }

    /**
     * @param array<int|string, array<string, array<string, int>|null>|int|string> $data
     * @param array<int|string, array<string, string>|string>                      $keys
     */
    #[DataProvider('notHasKeysSuccessProvider')]
    public function testAssertDatasetNotHasKeysPasses(array $data, array $keys): void
    {
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }

    /**
     * @return \Iterator<string, array{array<mixed>, array<mixed>, string}>
     */
    public static function notHasKeysThrowsProvider(): \Iterator
    {
        yield 'key found' => [['fail1' => true], ['fail1'], 'Dataset should not have key [fail1]!'];
        yield 'one of the keys found' => [
            ['nested' => ['value']],
            ['simple', 'nested'],
            'Dataset should not have key [nested]!',
        ];
        yield 'parent of the nested key is no array' => [
            ['nested' => 'string'],
            ['nested' => ['deep']],
            'Key [nested] is expected to be an array or null!',
        ];
        yield 'nested key found' => [
            ['nested' => ['deep' => 'value']],
            ['nested' => ['deep']],
            'Dataset should not have key [nested][deep]!',
        ];
    }

    /**
     * @param array<string, array<int|string, string>|bool|string> $data
     * @param array<int|string, string[]|string>                   $keys
     */
    #[DataProvider('notHasKeysThrowsProvider')]
    public function testAssertDatasetNotHasKeysThrows(array $data, array $keys, string $msg): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageIsOrContains($msg);
        ApiPlatformTestCase::assertDatasetNotHasKeys($keys, $data);
    }

    public function testPrepareUploadedFile(): void
    {
        $file = ApiPlatformTestCase::prepareUploadedFile(__FILE__, 'test.php', 'text/x-php');

        self::assertNotSame(__FILE__, $file->getPathname());
        self::assertFileEquals(__FILE__, $file->getPathname());
        self::assertSame('test.php', $file->getClientOriginalName());
        self::assertSame('text/x-php', $file->getClientMimeType());

        unlink($file->getPathname());
    }
}
