<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PasswordStrength extends Constraint
{
    public const PASSWORD_TOO_WEAK_ERROR = 'b6e99925-dc10-4c40-bc95-a5712bd9a3f0';

    protected const ERROR_NAMES = [
        self::PASSWORD_TOO_WEAK_ERROR => 'PASSWORD_TOO_WEAK_ERROR',
    ];

    public string $message = 'This password is not secure enough.';
    public float $minStrength = 25;

    public function __construct(
        ?float $minStrength = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(
            groups: $groups,
            payload: $payload,
        );

        $this->minStrength = $minStrength ?? $this->minStrength;
        $this->message = $message ?? $this->message;
    }

    public function getMinStrength(): float
    {
        return $this->minStrength;
    }
}
