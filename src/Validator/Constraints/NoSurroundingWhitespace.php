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
            // Matches string without leading/trailing whitespace and newline characters
            // separators, @see https://stackoverflow.com/a/38935454/1341762.
            // We cannot use \R in the lookbehind: lookbehind assertion is not fixed length at offset 14
            pattern: '/^(?!(\R|\s)).*(?<!(\n|\r|\f|\x0b|\x85|\x{2028}|\x{2029}|\s))$/Dsu',
            message: $message ?? $options['message'] ?? 'This value contains leading/trailing whitespace.',
            match: true,
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
