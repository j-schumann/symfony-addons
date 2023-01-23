<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Checks that the value contains no leading/trailing whitespace.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoSurroundingWhitespace extends Regex
{
    public function __construct($options = null)
    {
        if (!isset($options['match'])) {
            $options['match'] = true;
        }
        if (!isset($options['message'])) {
            $options['message'] = 'This value contains leading/trailing whitespace.';
        }

        // matches string without leading/trailing whitespace characters
        // separators, @see https://stackoverflow.com/a/38935454/1341762
        $options['pattern'] = '/^(?!\s).*(?<!\s)$/u';
        parent::__construct($options);
    }

    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
