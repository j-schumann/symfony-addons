<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrength;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrengthValidator;

class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
    }

    public static function getValid(): array
    {
        return [
            ['password of multiple words'],
            ['l0ngPa$$w0rd'],
        ];
    }

    public static function getInvalid(): array
    {
        return [
            ['1234567890'],
            ['aaaaaaaaaaa'],
        ];
    }

    public function testNullIsValid(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException('Symfony\Component\Validator\Exception\UnexpectedValueException');
        $constraint = new PasswordStrength();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testEmptyStringIsInvalid(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('', $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }

    /**
     * @dataProvider getValid
     */
    public function testValid($value): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->assertRaised();
    }
}
