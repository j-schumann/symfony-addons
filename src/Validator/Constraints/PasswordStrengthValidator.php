<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Vrok\SymfonyAddons\Helper\PasswordStrength as StrengthCalculator;

/**
 * Checks the strength of a given password.
 */
class PasswordStrengthValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $calculator = new StrengthCalculator();
        $strength = $calculator->getStrength($value);

        if ($strength < $constraint->getMinStrength()) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();

            return;
        }
    }
}
