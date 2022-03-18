<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\RegexValidator;

/**
 * Checks that the value contains no line breaks (\n, \r, etc.).
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoLineBreaks extends Regex
{
    public $match = false;
    public $message = 'This value contains line breaks.';

    public function __construct($options = null)
    {
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
