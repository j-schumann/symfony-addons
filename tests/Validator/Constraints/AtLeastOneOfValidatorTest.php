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
    protected function createValidator()
    {
        return new AtLeastOneOfValidator();
    }

    public function getValid()
    {
        return [
            [null],
            [''],
            ['12'],
        ];
    }

    public function getInvalid()
    {
        return [
            [' '],
            ['1'],
        ];
    }

    public function getValidSequentially()
    {
        return [
            [null],
            [''],
            ['123'],
        ];
    }

    public function getInvalidSequentially()
    {
        return [
            [' ', 'minMessage'],
            ['1', 'minMessage'],
            ['1234', 'maxMessage'],
        ];
    }

    public function getValidAll()
    {
        return [
            [null],
            [[]],
            [['123']],
        ];
    }

    public function getInvalidAll()
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
    public function testValid($value)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(null, 2, null, null,null,null, 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getValidSequentially
     */
    public function testValidSequentially($value)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Sequentially([
                new Length(null, 2, null, null,null,null, 'minMessage'),
                new Length(null, null, 3, null,null,null, null, 'maxMessage'),
            ])
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getValidAll
     */
    public function testValidAll($value)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new All([
                new Length(null, 2, null, null,null,null, 'minMessage'),
                new Length(null, null, 3, null,null,null, null, 'maxMessage'),
            ])
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);
        $this->assertCount(0, $violations);
    }


    /**
     * @dataProvider getInvalid
     */
    public function testInvalid($value)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Length(null, 2, null, null,null,null, 'minMessage'),
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation('minMessage', 'minMessage', [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }

    /**
     * @dataProvider getInvalidSequentially
     */
    public function testInvalidSequentially($value, $message)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new Sequentially([
                new Length(null, 2, null, null,null,null, 'minMessage'),
                new Length(null, null, 3, null,null,null, null, 'maxMessage'),
            ])
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }


    /**
     * @dataProvider getInvalidAll
     */
    public function testInvalidAll($value, $message)
    {
        $constraint = new AtLeastOneOf([
            new Blank(),
            new All([
                new Length(null, 2, null, null,null,null, 'minMessage'),
                new Length(null, null, 3, null,null,null, null, 'maxMessage'),
            ])
        ]);

        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation($message, $message, [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }

    public function testCustomMessage(): void
    {
        $constraint = new AtLeastOneOf(
            [
                new Blank(),
                new Length(null, 2, null, null,null,null, 'minMessage'),
            ],
            null,
            null,
            'customMessage'
        );

        $value = '1';
        $validator = Validation::createValidator();
        $violations = $validator->validate($value, $constraint);

        $this->assertCount(1, $violations);
        $this->assertEquals(new ConstraintViolation('customMessage', 'customMessage', [], $value, '', $value, null, AtLeastOneOf::AT_LEAST_ONE_OF_ERROR, $constraint), $violations->get(0));
    }
}
