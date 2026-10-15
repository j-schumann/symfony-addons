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
    }

    public function testStripHtmlKeepsTextBelowNestingLimit(): void
    {
        $html = str_repeat('<div>', 300).'text';

        self::assertSame('text', HtmlUtil::stripHtml($html));
    }

    public function testStripHtmlKeepsInvalidUtf8(): void
    {
        self::assertSame("<p>Gr\xFC</p>", HtmlUtil::stripHtml("<p>Gr\xFC</p>"));
    }
}
