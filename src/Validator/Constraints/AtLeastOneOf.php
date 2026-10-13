<?php

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf as BaseAtLeastOneOf;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AtLeastOneOf extends BaseAtLeastOneOf
{
    /**
     * The message defaults to an empty string instead of the parent's message, so the validator
     * reports the message of the last failed constraint.
     *
     * @param Constraint[]|Constraint|null $constraints
     */
    public function __construct(
        array|Constraint|null $constraints = null,
        ?array $groups = null,
        mixed $payload = null,
        string $message = '',
        ?string $messageCollection = null,
    ) {
        parent::__construct(
            $constraints,
            $groups,
            $payload,
            $message,
            $messageCollection
        );
    }
}
