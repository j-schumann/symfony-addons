<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoHtml;
use Vrok\SymfonyAddons\Validator\Constraints\NoHtmlValidator;

class NoHtmlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoHtmlValidator
    {
        return new NoHtmlValidator();
    }

    public static function getValid(): \Iterator
    {
        yield ['teststring'];
        yield ['0'];
        yield [' test '];
        yield ['11 < 12'];
        // ['is 11<12 ?'], fails but should be valid
        yield ['12 > 11'];
        yield ['11 < 12 and 13 > 11'];
        yield ['What is?>'];
        // [' close <3 heart, and 13 > 12'], fails but should be valid
    }

    public static function getInvalid(): \Iterator
    {
        yield ['This is <b>bold</b>'];
        yield ['1119<br>0231'];
        yield ['1684<br />5312'];
        yield ['1996 <? echo 1; ?> test'];
        yield ['1684 <?php echo 2;?> test'];
        yield ['this is not valid <a script tag>abc</a>'];
    }

    public function testNullIsValid(): void
    {
        $constraint = new NoHtml();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $constraint = new NoHtml();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $constraint = new NoHtml();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }
}
