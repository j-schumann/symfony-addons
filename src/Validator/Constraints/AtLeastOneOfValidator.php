<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Overwrites Symfony's AtLeastOneOf to return the message of the last failed validator
 * instead of the combined message.
 */
class AtLeastOneOfValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof AtLeastOneOf) {
            throw new UnexpectedTypeException($constraint, AtLeastOneOf::class);
        }

        $validator = $this->context->getValidator();

        $lastMessage = '';

        foreach ($constraint->constraints as $key => $item) {
            $executionContext = clone $this->context;
            $executionContext->setNode($value, $this->context->getObject(), $this->context->getMetadata(), $this->context->getPropertyPath());
            $violations = $validator->inContext($executionContext)->validate($value, $item, $this->context->getGroup())->getViolations();

            if (\count($this->context->getViolations()) === \count($violations)) {
                return;
            }

            $lastMessage = $constraint->message
                ?: $violations->get(\count($violations) - 1)->getMessage();
        }

        $this->context->buildViolation($lastMessage)
            ->setCode(AtLeastOneOf::AT_LEAST_ONE_OF_ERROR)
            ->addViolation()
        ;
    }
}
