<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Checks that the given value contains no HTML tags.
 */
class NoHtmlValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoHtml) {
            throw new UnexpectedTypeException($constraint, NoHtml::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $length = \strlen($value);
        $strippedLength = \strlen(strip_tags($value));

        if ($length !== $strippedLength) {
            $this->context
                ->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(NoHtml::CONTAINS_HTML_ERROR)
                ->addViolation();

            return;
        }
    }
}
