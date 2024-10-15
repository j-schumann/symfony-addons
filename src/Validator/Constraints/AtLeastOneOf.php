<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraints\AtLeastOneOf as BaseAtLeastOneOf;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AtLeastOneOf extends BaseAtLeastOneOf
{
    public function __construct(
        mixed $constraints = null,
        ?array $groups = null,
        mixed $payload = null,
        string $message = '',
        ?string $messageCollection = null,
    ) {
        parent::__construct(
            $constraints,
            $groups,
            $payload,
            // previously (SF <= 6.4) this value could be NULL -> automatically
            // convert to empty string now
            $message ?? '',
            $messageCollection
        );
    }
}
