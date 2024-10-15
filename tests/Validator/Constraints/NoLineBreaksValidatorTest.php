<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
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
        yield ["new\nline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["new\rline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["new\r\nline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["new\fline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["\nnewline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["newline\n", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3'];
        yield ["new\x0bline", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical tab
        yield ["new\xc2\x85line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // NEL, Next Line
        yield ["test\vspace", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // vertical space
        yield ["new\xe2\x80\xa8line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode LS
        yield ["new\xe2\x80\xa9line", 'de1e3db3-5ed4-4941-aae4-59f3667cc3a3']; // Unicode PS
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
            ->setCode($code);

        // symfony/validator 6.3 added a new parameter ...
        $version = \Composer\InstalledVersions::getVersion('symfony/validator');
        if (\Composer\Semver\Comparator::greaterThanOrEqualTo($version, '6.3')) {
            $violation->setParameter('{{ pattern }}', $constraint->pattern);
        }

        $violation->assertRaised();
    }
}
