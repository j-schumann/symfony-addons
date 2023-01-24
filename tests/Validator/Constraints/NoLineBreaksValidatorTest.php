<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoLineBreaks;

class NoLineBreaksValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    public function getValid(): array
    {
        return [
            ["test\tstring"], // tab
            ['0 d'], // tab
            [' test '], // spaces
            ['1119<br>0231'],
            ['1684<br />5312'],
        ];
    }

    public function getInvalid(): array
    {
        return [
            ["new\nline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["new\rline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["new\r\nline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["new\fline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["\nnewline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["newline\n", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'],
            ["new\x0bline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical tab
            ["new\xc2\x85line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // NEL, Next Line
            ["test\vspace", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // vertical space
            ["new\xe2\x80\xa8line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode LS
            ["new\xe2\x80\xa9line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'], // Unicode PS
        ];
    }

    public function testNullIsValid(): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException('Symfony\Component\Validator\Exception\UnexpectedValueException');
        $constraint = new NoLineBreaks();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValid
     */
    public function testValid($value): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value, $code): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode($code)
            ->assertRaised();
    }
}
