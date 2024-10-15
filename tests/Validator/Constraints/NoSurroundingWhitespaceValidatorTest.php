<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
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
        yield ["new\xe2\x80\xa8line"];// Unicode LS
        yield ["new\xe2\x80\xa9line"]; // Unicode PS
    }

    public static function getInvalid(): \Iterator
    {
        // whitespace character at the beginning or end:
        yield [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ['asd  ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["\tasd", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["asd\t", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // THSP leading
        yield ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // THSP trailing
        yield [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // NQSP
        yield [' asd', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // MQSP
        yield ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // ENSP
        yield ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // EMSP
        yield ['asd ', 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // 3/MSP

        // leading/trailing newline characters:
        yield ["a\nb\n", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // trailing newline
        yield ["\na\nb", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // leading newline
        yield ["new\x0b", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical tab
        yield ["\x0btest", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical tab
        yield ["new\xc2\x85", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // NEL, Next Line
        yield ["\xc2\x85test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // NEL, Next Line
        yield ["test\v", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical space
        yield ["\vtest", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical space
        yield ["new\xe2\x80\xa8", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode LS
        yield ["\xe2\x80\xa8test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode LS
        yield ["new\xe2\x80\xa9", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode PS
        yield ["\xe2\x80\xa9test", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode PS
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
