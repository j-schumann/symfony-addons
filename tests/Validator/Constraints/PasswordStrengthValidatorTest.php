<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrength;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrengthValidator;

class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
    }

    public static function getValid(): \Iterator
    {
        yield ['password of multiple words'];
        yield ['l0ngPa$$w0rd'];
    }

    public static function getInvalid(): \Iterator
    {
        yield ['1234567890'];
        yield ['aaaaaaaaaaa'];
    }

    public function testNullIsValid(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $constraint = new PasswordStrength();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testConstraintWithNamedArgument(): void
    {
        $constraint = new PasswordStrength(message: 'myMessage');

        $this->validator->validate('fail', $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setCode(PasswordStrength::PASSWORD_TOO_WEAK_ERROR);

        $violation->assertRaised();
    }

    public function testConstraintWithMinStrengthOption(): void
    {
        $constraint = new PasswordStrength(minStrength: 12);

        // "works" is exactly strength 12
        $this->validator->validate('works', $constraint);

        $this->assertNoViolation();
    }

    // @todo remove with SymfonyAddons 3.0
    public function testConstraintWithOptions(): void
    {
        $constraint = new PasswordStrength(['message' => 'myMessage']);

        $this->validator->validate('fail', $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setCode(PasswordStrength::PASSWORD_TOO_WEAK_ERROR);

        $violation->assertRaised();
        $this->expectUserDeprecationMessage(
            'Since symfony/validator 7.3: Passing an array of options to configure the "Vrok\SymfonyAddons\Validator\Constraints\PasswordStrength" constraint is deprecated, use named arguments instead.'
        );
    }

    public function testEmptyStringIsInvalid(): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate('', $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(PasswordStrength::PASSWORD_TOO_WEAK_ERROR)
            ->assertRaised();
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new PasswordStrength();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->setCode(PasswordStrength::PASSWORD_TOO_WEAK_ERROR)
            ->assertRaised();
    }
}
