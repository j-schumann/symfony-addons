<?php

namespace Vrok\SymfonyAddons\Tests\Util;

use PHPUnit\Framework\TestCase;
use Vrok\SymfonyAddons\Util\HtmlUtil;

final class HtmlUtilTest extends TestCase
{
    public function testStripHtml(): void
    {
        self::assertSame('', HtmlUtil::stripHtml(''));
        self::assertSame('', HtmlUtil::stripHtml(' <p> </p> '));
        self::assertSame('', HtmlUtil::stripHtml('<p>&nbsp;</p>'));
        self::assertSame('test', HtmlUtil::stripHtml("\ttest\r\n"));
        self::assertSame('a"bc123', HtmlUtil::stripHtml('a&quot;bc<br>123'));
        self::assertSame("It's", HtmlUtil::stripHtml('<p>It&apos;s</p>'));
        self::assertSame("a\u{a0}bc123", HtmlUtil::stripHtml('<span>a&nbsp;bc<br>123</span>'));
        self::assertSame('I <3 PHP', HtmlUtil::stripHtml('<p>I <3 PHP</p>'));
        self::assertSame('11<12', HtmlUtil::stripHtml('11<12'));
        self::assertSame('äü', HtmlUtil::stripHtml('ä&uuml;'));
        self::assertSame('Title text', HtmlUtil::stripHtml('<title>Title</title> text'));
        self::assertSame('ab', HtmlUtil::stripHtml('a</body></html><p>b</p>'));
        self::assertSame('<3 PHP', HtmlUtil::stripHtml('<3 PHP'));
        self::assertSame('a < b > c', HtmlUtil::stripHtml('a < b > c'));
        self::assertSame("a\tb", HtmlUtil::stripHtml('a&Tab;b'));
        self::assertSame('ab', HtmlUtil::stripHtml('a<!-- <p>c</p> -->b'));
        self::assertSame('a', HtmlUtil::stripHtml('a<!-- unclosed'));
        self::assertSame('x', HtmlUtil::stripHtml('<!DOCTYPE html><html><body>x</body></html>'));
        self::assertSame("x\0yy", HtmlUtil::stripHtml("<p>x</p>\0<p>yy"));
    }

    public function testStripHtmlWithInvisibleCharacters(): void
    {
        self::assertSame('', HtmlUtil::stripHtml("<p><span>\u{feff}</span><br></p>"));
        self::assertSame('', HtmlUtil::stripHtml("<p>\u{200b}\u{2060}&shy;</p>"));
    }

    public function testStripHtmlWithDeeplyNestedTags(): void
    {
        $html = 'ok '.str_repeat('<b>', 100_000).'text'.str_repeat('</i>', 100_000);

        self::assertSame('ok text', HtmlUtil::stripHtml($html));
    }

    public function testStripHtmlWithInvalidUtf8(): void
    {
        self::assertSame("<p>Gr\xFC</p>", HtmlUtil::stripHtml("<p>Gr\xFC</p>"));
    }
}
