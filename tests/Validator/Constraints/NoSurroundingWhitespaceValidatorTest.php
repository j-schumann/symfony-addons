<?php

namespace Vrok\SymfonyAddons\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Component\Validator\Validation;
use Vrok\SymfonyAddons\Tests\Fixtures\Entity\TestEntity;
use Vrok\SymfonyAddons\Validator\Constraints\NoSurroundingWhitespace;

final class NoSurroundingWhitespaceValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): RegexValidator
    {
        return new RegexValidator();
    }

    /**
     * @return \Iterator<array{string}>
     */
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

    /**
     * @return \Iterator<array{string}>
     */
    public static function getInvalid(): \Iterator
    {
        // whitespace character at the beginning or end:
        yield [' asd'];
        yield ['asd  '];
        yield ["\tasd"];
        yield ["asd\t"];
        yield [' asd']; // THSP leading
        yield ['asd ']; // THSP trailing
        yield [' asd']; // NQSP
        yield [' asd']; // MQSP
        yield ['asd ']; // ENSP
        yield ['asd ']; // EMSP
        yield ['asd ']; // 3/MSP

        // leading/trailing newline characters:
        yield ["a\nb\n"]; // trailing newline
        yield ["\na\nb"]; // leading newline
        yield ["new\x0b"]; // vertical tab
        yield ["\x0btest"]; // vertical tab
        yield ["new\xc2\x85"]; // NEL, Next Line
        yield ["\xc2\x85test"]; // NEL, Next Line
        yield ["test\v"]; // vertical space
        yield ["\vtest"]; // vertical space
        yield ["new\xe2\x80\xa8"]; // Unicode LS
        yield ["\xe2\x80\xa8test"]; // Unicode LS
        yield ["new\xe2\x80\xa9"]; // Unicode PS
        yield ["\xe2\x80\xa9test"]; // Unicode PS
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

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new NoSurroundingWhitespace();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', '"'.$value.'"')
            ->setParameter('{{ pattern }}', $constraint->pattern)
            ->setCode(Regex::REGEX_FAILED_ERROR)
            ->assertRaised();
    }

    public function testConstraintFromYamlMapping(): void
    {
        $metadata = Validation::createValidatorBuilder()
            ->addYamlMapping(__DIR__.'/../../Fixtures/Validator/constraints.yaml')
            ->getValidator()
            ->getMetadataFor(TestEntity::class);
        \assert($metadata instanceof ClassMetadata);
        $constraints = array_values(array_filter(
            $metadata->getPropertyMetadata('varcharColumn')[0]->getConstraints(),
            static fn (Constraint $c): bool => $c instanceof NoSurroundingWhitespace,
        ));

        self::assertCount(1, $constraints);
        self::assertSame('trimmed', $constraints[0]->message);
    }
}
