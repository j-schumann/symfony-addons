<?php

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoLineBreaks;

final class NoLineBreaksValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getValid(): \Iterator
    {
        yield ["test\tstring"]; // tab
        yield ['0 d']; // space
        yield [' test ']; // spaces
        yield ['1119<br>0231'];
        yield ['1684<br />5312'];
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getInvalid(): \Iterator
    {
        yield ["new\nline"];
        yield ["new\rline"];
        yield ["new\r\nline"];
        yield ["new\fline"];
        yield ["\nnewline"];
        yield ["newline\n"];
        yield ["new\x0bline"]; // vertical tab
        yield ["new\xc2\x85line"]; // NEL, Next Line
        yield ["test\vspace"]; // vertical space
        yield ["new\xe2\x80\xa8line"]; // Unicode LS
        yield ["new\xe2\x80\xa9line"]; // Unicode PS
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
        $this->expectException(UnexpectedValueException::class);
        $constraint = new NoLineBreaks();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testConstraintWithNamedArgument(): void
    {
        $constraint = new NoLineBreaks(message: 'myMessage');

        $this->validator->validate("fail\nnow", $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', "\"fail\nnow\"")
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR);

        $violation->assertRaised();
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $violation = $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR);

        $violation->assertRaised();
    }
}
