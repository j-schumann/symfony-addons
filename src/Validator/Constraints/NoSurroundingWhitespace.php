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
        ?string $message = null,
        ?callable $normalizer = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(
            // Matches string without leading/trailing whitespace and newline characters
            // separators, @see https://stackoverflow.com/a/38935454/1341762.
            // We cannot use \R in the lookbehind: lookbehind assertion is not fixed length at offset 14
            pattern: '/^(?!(\R|\s)).*(?<!(\n|\r|\f|\x0b|\x85|\x{2028}|\x{2029}|\s))$/Dsu',
            message: $message ?? 'This value contains leading/trailing whitespace.',
            match: true,
            normalizer: $normalizer,
            groups: $groups,
            payload: $payload,
        );
    }

    #[\Override]
    public function getTargets(): string
    {
        return self::PROPERTY_CONSTRAINT;
    }

    #[\Override]
    public function validatedBy(): string
    {
        return RegexValidator::class;
    }
}
