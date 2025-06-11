<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\NoSurroundingWhitespace;

class NoSurroundingWhitespaceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    public static function getValid(): \Iterator
    {
        yield ["test\tstring"]; // tab inside
        yield ['.0 d']; // space inside
        yield ['*tes t'];
        yield ['_as d@'];

        // make sure linebreaks within the string are allowed:
        yield ["valid\nmultiline"];
        yield ["valid\n space multiline"];
        yield ["valid\r\nwindows multiline"];
        yield ["new\x0bline"]; // vertical tab
        yield ["new\xc2\x85line"]; // NEL, Next Line
        yield ["test\vspace"]; // vertical space
        yield ["new\xe2\x80\xa8line"]; // Unicode LS
        yield ["new\xe2\x80\xa9line"]; // Unicode PS
    }

    public static function getInvalid(): \Iterator
    {
        // whitespace character at the beginning or end:
        yield [' asd', Regex::REGEX_FAILED_ERROR];
        yield ['asd  ', Regex::REGEX_FAILED_ERROR];
        yield ["\tasd", Regex::REGEX_FAILED_ERROR];
        yield ["asd\t", Regex::REGEX_FAILED_ERROR];
        yield [' asd', Regex::REGEX_FAILED_ERROR]; // THSP leading
        yield ['asd ', Regex::REGEX_FAILED_ERROR]; // THSP trailing
        yield [' asd', Regex::REGEX_FAILED_ERROR]; // NQSP
        yield [' asd', Regex::REGEX_FAILED_ERROR]; // MQSP
        yield ['asd ', Regex::REGEX_FAILED_ERROR]; // ENSP
        yield ['asd ', Regex::REGEX_FAILED_ERROR]; // EMSP
        yield ['asd ', Regex::REGEX_FAILED_ERROR]; // 3/MSP

        // leading/trailing newline characters:
        yield ["a\nb\n", Regex::REGEX_FAILED_ERROR]; // trailing newline
        yield ["\na\nb", Regex::REGEX_FAILED_ERROR]; // leading newline
        yield ["new\x0b", Regex::REGEX_FAILED_ERROR]; // vertical tab
        yield ["\x0btest", Regex::REGEX_FAILED_ERROR]; // vertical tab
        yield ["new\xc2\x85", Regex::REGEX_FAILED_ERROR]; // NEL, Next Line
        yield ["\xc2\x85test", Regex::REGEX_FAILED_ERROR]; // NEL, Next Line
        yield ["test\v", Regex::REGEX_FAILED_ERROR]; // vertical space
        yield ["\vtest", Regex::REGEX_FAILED_ERROR]; // vertical space
        yield ["new\xe2\x80\xa8", Regex::REGEX_FAILED_ERROR]; // Unicode LS
        yield ["\xe2\x80\xa8test", Regex::REGEX_FAILED_ERROR]; // Unicode LS
        yield ["new\xe2\x80\xa9", Regex::REGEX_FAILED_ERROR]; // Unicode PS
        yield ["\xe2\x80\xa9test", Regex::REGEX_FAILED_ERROR]; // Unicode PS
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
        $this->expectException(UnexpectedValueException::class);
        $constraint = new NoSurroundingWhitespace();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testConstraintWithNamedArgument(): void
    {
        $constraint = new NoSurroundingWhitespace(message: 'myMessage');

        $this->validator->validate(' fail ', $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '" fail "')
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR);

        $violation->assertRaised();
    }

    // @todo remove with SymfonyAddons 3.0
    public function testConstraintWithOptions(): void
    {
        $constraint = new NoSurroundingWhitespace(['message' => 'myMessage']);

        $this->validator->validate(' fail ', $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '" fail "')
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR);

        $violation->assertRaised();
        $this->expectUserDeprecationMessage(
            'Since symfony/validator 7.3: Passing an array of options to configure the "Vrok\SymfonyAddons\Validator\Constraints\NoSurroundingWhitespace" constraint is deprecated, use named arguments instead.'
        );
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value, string $code): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $violation = $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setCode($code);

        // symfony/validator 6.3 added a new parameter ...
        $version = \Composer\InstalledVersions::getVersion('symfony/validator');
        if (\Composer\Semver\Comparator::greaterThanOrEqualTo($version, '6.3')) {
            $violation->setParameter('{{ pattern }}', $constraint->pattern);
        }

        $violation->assertRaised();
    }
}
