<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOf;
use Vrok\SymfonyAddons\Validator\Constraints\AtLeastOneOfValidator;

class AtLeastOneOfValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): AtLeastOneOfValidator
    {
        return new AtLeastOneOfValidator();
    }

    public static function getValid(): array
    {
        return [
            [null],
            [''],
            ['12'],
        ];
    }

    public static function getInvalid(): array
    {
        return [
            [' '],
            ['1'],
        ];
    }

    public static function getValidSequentially(): array
    {
        return [
            [null],
            [''],
            ['123'],
        ];
    }

    public static function getInvalidSequentially(): array
    {
        return [
            [' ', 'minMessage'],
            ['1', 'minMessage'],
            ['1234', 'maxMessage'],
        ];
    }

    public static function getValidAll(): array
    {
        return [
            [null],
            [[]],
            [['123']],
        ];
    }

    public static function getInvalidAll(): array
    {
        return [
            [[' '], 'minMessage'],
            [['1'], 'minMessage'],
            [['1234'], 'maxMessage'],
        ];
    }

    /**
     * @dataProvider getValid
     */
    public function testValid($value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(min: 2, minMessage: 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getValidSequentially
     */
    public function testValidSequentially($value): void
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
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getValidAll
     */
    public function testValidAll($value): void
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
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value): void
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(min: 2, minMessage: 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation('minMessage', 'minMessage', [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }

    /**
     * @dataProvider getInvalidSequentially
     */
    public function testInvalidSequentially($value, $message): void
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

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }

    /**
     * @dataProvider getInvalidAll
     */
    public function testInvalidAll($value, $message): void
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

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
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

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation('customMessage', 'customMessage', [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }
}
