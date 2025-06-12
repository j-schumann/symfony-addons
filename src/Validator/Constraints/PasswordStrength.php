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

    protected float $minStrength = 25;

    public function __construct(
        mixed $options = null,
        ?float $minStrength = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        // @todo remove $options with SymfonyAddons 3.0
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        } elseif (null !== $options) {
            trigger_deprecation('vrok/symfony-addons', '2.16', 'Using options is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct(
            groups: $groups ?? $options['groups'] ?? null,
            payload: $payload ?? $options['payload'] ?? null,
        );

        $this->minStrength = $minStrength ?? $options['minStrength'] ?? $this->minStrength;
        $this->message = $message ?? $options['message'] ?? $this->message;
    }

    public function getMinStrength(): float
    {
        return $this->minStrength;
    }
}
