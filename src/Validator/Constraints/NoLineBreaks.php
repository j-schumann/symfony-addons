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
    public function __construct(
        string|array|null $options = null,
        ?string $message = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        // @todo remove $options with SymfonyAddons 3.0
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        } elseif (null !== $options) {
            trigger_deprecation('vrok/symfony-addons', '2.16', 'Using options is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct(
            // matches any linebreak characters, including Unicode line/paragraph
            // separators, @see https://stackoverflow.com/a/18992691/1341762
            pattern: '/\R/u',

            message: $message ?? $options['message'] ?? 'This value contains line breaks.',
            match: false,
            normalizer: $normalizer ?? $options['normalizer'] ?? null,
            groups: $groups ?? $options['groups'] ?? null,
            payload: $payload ?? $options['payload'] ?? null,
        );
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
