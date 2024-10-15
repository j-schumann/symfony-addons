<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PasswordStrength extends Constraint
{
    public $message = 'This password is not secure enough.';

    protected float $minStrength = 25;

    public function __construct(mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        if (isset($options['minStrength'])) {
            $this->minStrength = (float) $options['minStrength'];
        }
    }

    public function getMinStrength(): float
    {
        return $this->minStrength;
    }
}
