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
use Vrok\SymfonyAddons\Validator\Constraints\NoHtml;
use Vrok\SymfonyAddons\Validator\Constraints\NoHtmlValidator;

final class NoHtmlValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoHtmlValidator
    {
        return new NoHtmlValidator();
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getValid(): \Iterator
    {
        yield ['teststring'];
        yield ['0'];
        yield [' test '];
        yield ['11 < 12'];
        // @todo 'is 11<12 ?' is rejected but should be valid
        yield ['12 > 11'];
        yield ['11 < 12 and 13 > 11'];
        yield ['What is?>'];
        // @todo ' close <3 heart, and 13 > 12' is rejected but should be valid
    }

    /**
     * @return \Iterator<array{string}>
     */
    public static function getInvalid(): \Iterator
    {
        yield ['This is <b>bold</b>'];
        yield ['1119<br>0231'];
        yield ['1684<br />5312'];
        yield ['1996 <? echo 1; ?> test'];
        yield ['1684 <?php echo 2;?> test'];
        yield ['this is not valid <a script tag>abc</a>'];
    }

    public function testNullIsValid(): void
    {
        $constraint = new NoHtml();

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $constraint = new NoHtml();

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $constraint = new NoHtml();
        $this->validator->validate(new \stdClass(), $constraint);
    }

    public function testConstraintWithNamedArgument(): void
    {
        $constraint = new NoHtml(message: 'myMessage');

        $this->validator->validate('this<b>fails', $constraint);

        $violation = $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"this<b>fails"')
            ->setCode(NoHtml::CONTAINS_HTML_ERROR);

        $violation->assertRaised();
    }

    #[DataProvider('getValid')]
    public function testValid(string $value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->assertNoViolation();
    }

    #[DataProvider('getInvalid')]
    public function testInvalid(string $value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ value }}', "\"$value\"")
            ->setCode(NoHtml::CONTAINS_HTML_ERROR)
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
            static fn (Constraint $c): bool => $c instanceof NoHtml,
        ));

        self::assertCount(1, $constraints);
        self::assertSame('no html', $constraints[0]->message);
    }
}
