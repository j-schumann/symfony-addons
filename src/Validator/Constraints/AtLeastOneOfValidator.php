<?php

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Overwrites Symfony's AtLeastOneOf to return the message of the last failed validator instead of
 * the combined message.
 */
class AtLeastOneOfValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AtLeastOneOf) {
            throw new UnexpectedTypeException($constraint, AtLeastOneOf::class);
        }

        $validator = $this->context->getValidator();

        $lastMessage = '';

        /** @var Constraint[] $constraints normalized to an array by the Composite constructor */
        $constraints = $constraint->constraints;
        foreach ($constraints as $item) {
            // @phpstan-ignore argument.type (the Composite constructor sets the groups of all nested constraints)
            if (!\in_array($this->context->getGroup(), $item->groups, true)) {
                continue;
            }

            // a nested constraint re-initializes the shared validator instance with the cloned
            // context, restore ours afterwards
            $context = $this->context;
            $executionContext = clone $this->context;
            $executionContext->setNode(
                $value,
                $this->context->getObject(),
                $this->context->getMetadata(),
                $this->context->getPropertyPath(),
            );
            $violations = $validator->inContext($executionContext)
                ->validate($value, $item, $this->context->getGroup())
                ->getViolations();
            $this->context = $context;

            if (\count($this->context->getViolations()) === \count($violations)) {
                return;
            }

            $lastMessage = '' === $constraint->message
                ? $violations->get(\count($violations) - 1)->getMessage()
                : $constraint->message;
        }

        $this->context->buildViolation($lastMessage)
            ->setCode(AtLeastOneOf::AT_LEAST_ONE_OF_ERROR)
            ->addViolation()
        ;
    }
}
