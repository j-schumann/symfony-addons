<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Checks that the value contains no leading/trailing whitespace.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoSurroundingWhitespace extends Regex
{
    public $match = true;
    public $message = 'This value contains leading/trailing whitespace.';

    public function __construct($options = null)
    {
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
