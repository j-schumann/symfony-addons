<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class PasswordStrength extends Constraint
{
    public $message = 'This password is not secure enough.';

    protected float $minStrength = 25;

    public function __construct($options = null, array $groups = null, $payload = null)
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
