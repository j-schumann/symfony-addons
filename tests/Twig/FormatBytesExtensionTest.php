<?php

namespace Vrok\SymfonyAddons\Tests\Twig;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Vrok\SymfonyAddons\Twig\FormatBytesExtension;

final class FormatBytesExtensionTest extends KernelTestCase
{
    public function testFormatBytes(): void
    {
        $ext = new FormatBytesExtension();

        $result = $ext->formatBytes(10);
        self::assertSame('10 B', $result);

        $result = $ext->formatBytes(1024);
        self::assertSame('1 KiB', $result);

        $result = $ext->formatBytes(10000);
        self::assertSame('9.77 KiB', $result);

        $result = $ext->formatBytes(10000, 1);
        self::assertSame('9.8 KiB', $result);

        $result = $ext->formatBytes(10000, 0);
        self::assertSame('10 KiB', $result);

        $result = $ext->formatBytes(100000);
        self::assertSame('97.66 KiB', $result);

        $result = $ext->formatBytes(1000000);
        self::assertSame('976.56 KiB', $result);

        $result = $ext->formatBytes(2000000);
        self::assertSame('1.91 MiB', $result);

        $result = $ext->formatBytes(10000000);
        self::assertSame('9.54 MiB', $result);

        $result = $ext->formatBytes(1000000000);
        self::assertSame('953.67 MiB', $result);

        $result = $ext->formatBytes(10000000000);
        self::assertSame('9.31 GiB', $result);

        $result = $ext->formatBytes(2000000000000);
        self::assertSame('1.82 TiB', $result);
    }

    public function testFilterIsRegistered(): void
    {
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');

        self::assertSame(
            '9.77 KiB',
            $twig->createTemplate('{{ 10000|formatBytes }}')->render()
        );
    }
}
