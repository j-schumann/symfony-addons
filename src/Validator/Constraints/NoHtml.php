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
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(
            groups: $groups,
            payload: $payload,
        );

        $this->message = $message ?? $this->message;
    }
}
