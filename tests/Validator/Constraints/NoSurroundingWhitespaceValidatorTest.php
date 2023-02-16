<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoSurroundingWhitespace;

class NoSurroundingWhitespaceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    public function getValid(): array
    {
        return [
            ["test\tstring"], // tab inside
            ['.0 d'], // space inside
            ['*tes t'],
            ['_as d@'],

            // make sure linebreaks within the string are allowed:
            ["valid\nmultiline"],
            ["valid\n space multiline"],
            ["valid\r\nwindows multiline"],
            ["new\x0bline"], // vertical tab
            ["new\xc2\x85line"], // NEL, Next Line
            ["test\vspace"], // vertical space
            ["new\xe2\x80\xa8line"], // Unicode LS
            ["new\xe2\x80\xa9line"], // Unicode PS
        ];
    }

    public function getInvalid(): array
    {
        return [
            // whitespace character at the beginning or end:
            [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ['asd  ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["\tasd", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["asd\t", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // THSP leading
            ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // THSP trailing
            [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // NQSP
            [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // MQSP
            ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // ENSP
            ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // EMSP
            ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // 3/MSP

            // leading/trailing newline characters:
            ["a\nb\n", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // trailing newline
            ["\na\nb", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // leading newline
            ["new\x0b", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical tab
            ["\x0btest", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical tab
            ["new\xc2\x85", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // NEL, Next Line
            ["\xc2\x85test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // NEL, Next Line
            ["test\v", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical space
            ["\vtest", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical space
            ["new\xe2\x80\xa8", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode LS
            ["\xe2\x80\xa8test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode LS
            ["new\xe2\x80\xa9", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode PS
            ["\xe2\x80\xa9test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode PS
        ];
    }

    public function testNullIsValid(): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException('Symfony\Component\Validator\Exception\UnexpectedValueException');
        $constraint = new NoSurroundingWhitespace();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValid
     */
    public function testValid($value): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value, $code): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode($code)
            ->assertRaised();
    }
}
