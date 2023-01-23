<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Checks that the value contains no line breaks (\n, \r, etc.).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoLineBreaks extends Regex
{
    public function __construct($options = null)
    {
        if (!isset($options['match'])) {
            $options['match'] = false;
        }
        if (!isset($options['message'])) {
            $options['message'] = 'This value contains line breaks.';
        }

        // matches any linebreak characters, including Unicode line/paragraph
        // separators, @see https://stackoverflow.com/a/18992691/1341762
        $options['pattern'] = '/\R/u';
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
