<?php

namespace Vrok\SymfonyAddons\Tests\Util;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Util\HtmlUtil;

final class HtmlUtilTest extends TestCase
{
    public function testStripHtml(): void
    {
        self::assertSame('', HtmlUtil::stripHtml(''));
        self::assertSame('', HtmlUtil::stripHtml(' <p> </p> '));
        self::assertSame('test', HtmlUtil::stripHtml("\ttest\r\n"));
        self::assertSame('a"bc123', HtmlUtil::stripHtml('a&quot;bc<br>123'));
        self::assertSame("a\u{a0}bc123", HtmlUtil::stripHtml('<span>a&nbsp;bc<br>123</span>'));
    }

    public function testStripHtmlAsConstraintNormalizer(): void
    {
        $validator = Validation::createValidator();
        $normalizer = HtmlUtil::stripHtml(...);

        self::assertCount(0, $validator->validate(
            '<p><strong>abc</strong>&amp;</p>',
            new Length(max: 4, normalizer: $normalizer),
        ));
        self::assertCount(1, $validator->validate(
            '<p><strong>abc</strong>&amp;</p>',
            new Length(max: 3, normalizer: $normalizer),
        ));
        self::assertCount(1, $validator->validate(
            '<p><br></p>',
            new NotBlank(normalizer: $normalizer),
        ));
    }
}
