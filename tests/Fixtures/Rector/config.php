<?php

use Rector\Config\RectorConfig;
use Vrok\SymfonyAddons\Rector\NamedArgumentsFromArrayRector;

return RectorConfig::configure()
    ->withConfiguredRule(NamedArgumentsFromArrayRector::class, [
        'targets' => [
            'foo',
            ['MyClass', 'staticMethod'],
            ['MyClass', 'instanceMethod'],
        ],
    ]);
