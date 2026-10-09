<?php

namespace Vrok\SymfonyAddons\Tests\Fixtures;

use Doctrine\DBAL\Types\JsonbType;

// DBAL 4.5 added the "jsonb" type and deprecated the "jsonb" column option,
// older versions only support the option. Use as
// #[ORM\Column(type: JsonbColumn::TYPE, options: JsonbColumn::OPTIONS)]
// @todo remove when DBAL < 4.5 support is dropped, use Types::JSONB instead
if (class_exists(JsonbType::class)) {
    final class JsonbColumn
    {
        public const string TYPE = 'jsonb';
        public const array OPTIONS = [];
    }
} else {
    final class JsonbColumn
    {
        public const string TYPE = 'json';
        public const array OPTIONS = ['jsonb' => true];
    }
}
