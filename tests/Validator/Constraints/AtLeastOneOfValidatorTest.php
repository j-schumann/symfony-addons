<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf as AtLeastOneOfAlias;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOf;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOfValidator;

final class AtLeastOneOfValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): AtLeastOneOfValidator
    {
        return new AtLeastOneOfValidator();
    }

    /**
     * @return \Iterator<(array<int, null> | array<int, string>)>
     */
    public static function getValid(): \Iterator
    {
        yield [null];
        yield [''];
        yield ['12'];
    }

    /**
     * @return \Iterator<array<int, string>>
     */
    public static function getInvalid(): \Iterator
    {
        yield [' '];
        yield ['1'];
    }

    /**
     * @return \Iterator<(array<int, null> | array<int, string>)>
     */
    public static function getValidSequentially(): \Iterator
    {
        yield [null];
        yield [''];
        yield ['123'];
    }

    /**
     * @return \Iterator<array<int, string>>
     */
    public static function getInvalidSequentially(): \Iterator
    {
        yield [' ', 'minMessage'];
        yield ['1', 'minMessage'];
        yield ['1234', 'maxMessage'];
    }

    /**
     * @return \Iterator<array<int, (array<mixed> | null)>>
     */
    public static function getValidAll(): \Iterator
    {
        yield [null];
        yield [[]];
        yield [['123']];
    }

    /**
     * @return \Iterator<(array<int, array<int, string>> | array<int, string>)>
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
        self::assertEquals(new ConstraintViolation('minMessage', 'minMessage', [], $value, '', $value, null, AtLeastOneOfAlias::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
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
        self::assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOfAlias::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
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
        self::assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOfAlias::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
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
        self::assertEquals(new ConstraintViolation('customMessage', 'customMessage', [], $value, '', $value, null, AtLeastOneOfAlias::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }
}
