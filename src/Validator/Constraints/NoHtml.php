<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoHtml extends Constraint
{
    public const CONTAINS_HTML_ERROR = 'c6f09a0f-eb6d-4509-802c-d35fbe94a053';

    protected const ERROR_NAMES = [
        self::CONTAINS_HTML_ERROR => 'CONTAINS_HTML_ERROR',
    ];

    public string $message = 'The value must not contain HTML Tags.';

    public function __construct(
        mixed $options = null,
        ?string $message = null,
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
            groups: $groups ?? $options['groups'] ?? null,
            payload: $payload ?? $options['payload'] ?? null,
        );

        $this->message = $message ?? $options['message'] ?? $this->message;
    }
}
