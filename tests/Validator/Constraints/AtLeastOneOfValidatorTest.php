<?php

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf as BaseAtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOf;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOfValidator;

final class AtLeastOneOfValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): AtLeastOneOfValidator
    {
        return new AtLeastOneOfValidator();
    }

    /**
     * @return \Iterator<array{?string}>
     */
    public static function getValid(): \Iterator
    {
        yield [null];
        yield [''];
        yield ['12'];
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getInvalid(): \Iterator
    {
        yield [' '];
        yield ['1'];
    }

    /**
     * @return \Iterator<array{?string}>
     */
    public static function getValidSequentially(): \Iterator
    {
        yield [null];
        yield [''];
        yield ['123'];
    }

    /**
     * @return \Iterator<array{string, string}>
     */
    public static function getInvalidSequentially(): \Iterator
    {
        yield [' ', 'minMessage'];
        yield ['1', 'minMessage'];
        yield ['1234', 'maxMessage'];
    }

    /**
     * @return \Iterator<array{?list<string>}>
     */
    public static function getValidAll(): \Iterator
    {
        yield [null];
        yield [[]];
        yield [['123']];
    }

    /**
     * @return \Iterator<array{list<string>, string}>
     */
    public static function getInvalidAll(): \Iterator
    {
        yield [[' '], 'minMessage'];
        yield [['1'], 'minMessage'];
        yield [['1234'], 'maxMessage'];
    }

    #[DataProvider('getValid')]
    public function testValid(?string $value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(min: 2, minMessage: 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        self::assertCount(0, $violations);
    }

    #[DataProvider('getValidSequentially')]
    public function testValidSequentially(?string $value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Sequentially([
                new Length(min: 2, minMessage: 'minMessage'),
                new Length(max: 3, maxMessage: 'maxMessage'),
            ]),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        self::assertCount(0, $violations);
    }

    #[DataProvider('getValidAll')]
    public function testValidAll(?array $value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new All([
                new Length(min: 2, minMessage: 'minMessage'),
                new Length(max: 3, maxMessage: 'maxMessage'),
            ]),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        self::assertCount(0, $violations);
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(min: 2, minMessage: 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        self::assertCount(1, $violations);
        self::assertEquals(
            $this->violation('minMessage', $value, $constraint),
            $violations->get(0)
        );
    }

    #[DataProvider('getInvalidSequentially')]
    public function testInvalidSequentially(string $value, string $message): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Sequentially([
                new Length(min: 2, minMessage: 'minMessage'),
                new Length(max: 3, maxMessage: 'maxMessage'),
            ]),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        self::assertCount(1, $violations);
        self::assertEquals($this->violation($message, $value, $constraint), $violations->get(0));
    }

    /**
     * @param string[] $value
     */
    #[DataProvider('getInvalidAll')]
    public function testInvalidAll(array $value, string $message): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new All([
                new Length(min: 2, minMessage: 'minMessage'),
                new Length(max: 3, maxMessage: 'maxMessage'),
            ]),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        self::assertCount(1, $violations);
        self::assertEquals($this->violation($message, $value, $constraint), $violations->get(0));
    }

    public function testCustomMessage(): void
    {
        $constraint = new AtLeastOneOf(
            constraints: [
                new Blank(),
                new Length(min: 2, minMessage: 'minMessage'),
            ],
            message: 'customMessage'
        );

        $value = '1';
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        self::assertCount(1, $violations);
        self::assertEquals(
            $this->violation('customMessage', $value, $constraint),
            $violations->get(0)
        );
    }

    public function testValidateFailsWithOtherConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('value', new NotBlank());
    }

    public function testValidateSkipsConstraintsOfOtherGroups(): void
    {
        $constraint = new AtLeastOneOf(constraints: [
            new Blank(groups: ['create']),
            new Length(min: 5, minMessage: 'minMessage'),
        ]);

        $violations = Validation::createValidator()->validate('abc', $constraint);

        self::assertCount(1, $violations);
        self::assertSame('minMessage', $violations->get(0)->getMessage());
    }

    public function testValidateWithNestedAtLeastOneOf(): void
    {
        $constraint = new AtLeastOneOf(constraints: [
            new AtLeastOneOf(constraints: [new Length(min: 10), new Blank()]),
            new Length(min: 5, minMessage: 'minMessage'),
        ]);

        $violations = Validation::createValidator()->validate('abc', $constraint);

        self::assertCount(1, $violations);
        self::assertSame('minMessage', $violations->get(0)->getMessage());
    }

    public function testConstraintFromYamlMapping(): void
    {
        $metadata = Validation::createValidatorBuilder()
            ->addYamlMapping(__DIR__.'/../../Fixtures/Validator/constraints.yaml')
            ->getValidator()
            ->getMetadataFor(TestEntity::class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);
        $constraints = array_values(array_filter(
            $metadata->getPropertyMetadata('varcharColumn')[0]->getConstraints(),
            static fn (Constraint $c): bool => $c instanceof AtLeastOneOf,
        ));

        self::assertCount(1, $constraints);
        self::assertSame('custom', $constraints[0]->message);
    }

    public function testConstraintFromYamlMappingWithoutMessage(): void
    {
        $metadata = Validation::createValidatorBuilder()
            ->addYamlMapping(__DIR__.'/../../Fixtures/Validator/constraints.yaml')
            ->getValidator()
            ->getMetadataFor(TestEntity::class);
        self::assertInstanceOf(ClassMetadata::class, $metadata);
        $constraints = array_values(array_filter(
            $metadata->getPropertyMetadata('jsonColumn')[0]->getConstraints(),
            static fn (Constraint $c): bool => $c instanceof AtLeastOneOf,
        ));

        self::assertCount(1, $constraints);
        self::assertSame('', $constraints[0]->message);
        self::assertTrue($constraints[0]->includeInternalMessages);
    }

    private function violation(
        string $message,
        mixed $value,
        AtLeastOneOf $constraint,
    ): ConstraintViolation {
        return new ConstraintViolation(
            $message,
            $message,
            [],
            $value,
            '',
            $value,
            null,
            BaseAtLeastOneOf::AT_LEAST_ONE_OF_ERROR,
            $constraint
        );
    }
}
