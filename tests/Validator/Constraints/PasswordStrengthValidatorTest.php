<?php

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrength;
use Vrok\SymfonyAddons\Validator\Constraints\PasswordStrengthValidator;

final class PasswordStrengthValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): PasswordStrengthValidator
    {
        return new PasswordStrengthValidator();
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getValid(): \Iterator
    {
        yield ['password of multiple words'];
        yield ['l0ngPa$$w0rd'];
    }

    /**
     * @return \Iterator<array{string}>
     */
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

    public function testValidateFailsWithOtherConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('value', new NotBlank());
    }

    public function testConstraintFromYamlMapping(): void
    {
        $metadata = Validation::createValidatorBuilder()
            ->addYamlMapping(__DIR__.'/../../Fixtures/Validator/constraints.yaml')
            ->getValidator()
            ->getMetadataFor(TestEntity::class);
        \assert($metadata instanceof ClassMetadata);
        $constraints = array_values(array_filter(
            $metadata->getPropertyMetadata('textColumn')[0]->getConstraints(),
            static fn (Constraint $c): bool => $c instanceof PasswordStrength,
        ));

        self::assertCount(1, $constraints);
        self::assertSame('too weak', $constraints[0]->message);
        self::assertSame(30.0, $constraints[0]->minStrength);
    }
}
