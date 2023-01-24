<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoHtml;
use Vrok\SymfonyAddons\Validator\Constraints\NoHtmlValidator;

class NoHtmlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoHtmlValidator
    {
        return new NoHtmlValidator();
    }

    public function getValid(): array
    {
        return [
            ['teststring'],
            ['0'],
            [' test '],
            ['11 < 12'],
            // ['is 11<12 ?'], fails but should be valid
            ['12 > 11'],
            ['11 < 12 and 13 > 11'],
            ['What is?>'],
             // [' close <3 heart, and 13 > 12'], fails but should be valid
        ];
    }

    public function getInvalid(): array
    {
        return [
            ['This is <b>bold</b>'],
            ['1119<br>0231'],
            ['1684<br />5312'],
            ['1996 <? echo 1; ?> test'],
            ['1684 <?php echo 2;?> test'],
            ['this is not valid <a script tag>abc</a>'],
        ];
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
        $this->expectException('Symfony\Component\Validator\Exception\UnexpectedValueException');
        $constraint = new NoHtml();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValid
     */
    public function testValid($value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }
}
