<?php

namespace Vrok\SymfonyAddons\Tests\Rector;

use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class NamedArgumentsFromArrayRectorTest extends AbstractRectorTestCase
{
    public static function provideFixtures(): \Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__.'/../Fixtures/Rector');
    }

    #[DataProvider('provideFixtures')]
    public function testRefactor(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__.'/../Fixtures/Rector/config.php';
    }
}
