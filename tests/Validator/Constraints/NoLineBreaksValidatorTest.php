<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoLineBreaks;

class NoLineBreaksValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    public static function getValid(): \Iterator
    {
        yield ["test\tstring"]; // tab
        yield ['0 d']; // tab
        yield [' test ']; // spaces
        yield ['1119<br>0231'];
        yield ['1684<br />5312'];
    }

    public static function getInvalid(): \Iterator
    {
        yield ["new\nline", Regex::REGEX_FAILED_ERROR];
        yield ["new\rline", Regex::REGEX_FAILED_ERROR];
        yield ["new\r\nline", Regex::REGEX_FAILED_ERROR];
        yield ["new\fline", Regex::REGEX_FAILED_ERROR];
        yield ["\nnewline", Regex::REGEX_FAILED_ERROR];
        yield ["newline\n", Regex::REGEX_FAILED_ERROR];
        yield ["new\x0bline", Regex::REGEX_FAILED_ERROR]; // vertical tab
        yield ["new\xc2\x85line", Regex::REGEX_FAILED_ERROR]; // NEL, Next Line
        yield ["test\vspace", Regex::REGEX_FAILED_ERROR]; // vertical space
        yield ["new\xe2\x80\xa8line", Regex::REGEX_FAILED_ERROR]; // Unicode LS
        yield ["new\xe2\x80\xa9line", Regex::REGEX_FAILED_ERROR]; // Unicode PS
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

    // @todo remove with SymfonyAddons 3.0
    public function testConstraintWithOptions(): void
    {
        $constraint = new NoLineBreaks(['message' => 'myMessage']);

        $this->validator->validate("fail\nnow", $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', "\"fail\nnow\"")
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR);

        $violation->assertRaised();
        $this->expectUserDeprecationMessage(
            'Since symfony/validator 7.3: Passing an array of options to configure the "Vrok\SymfonyAddons\Validator\Constraints\NoLineBreaks" constraint is deprecated, use named arguments instead.'
        );
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value, string $code): void
    {
        $constraint = new NoLineBreaks();

        $this->validator->validate($value, $constraint);

        $violation = $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode($code);

        $violation->assertRaised();
    }
}
