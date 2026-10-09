<?php

namespace Vrok\SymfonyAddons\PHPUnit;

// API Platform 5 moved the ApiTestCase to its own package (api-platform/test,
// replaced by api-platform/core) and deprecated the old class. Extend whichever
// is available, without autoloading the deprecated one on 5.x.
// @todo remove when support for API Platform 4 is dropped
if (class_exists(\ApiPlatform\Test\ApiTestCase::class)) {
    /**
     * @internal
     */
    abstract class BaseApiTestCase extends \ApiPlatform\Test\ApiTestCase
    {
    }
} else {
    /**
     * @internal
     */
    abstract class BaseApiTestCase extends \ApiPlatform\Symfony\Bundle\Test\ApiTestCase
    {
    }
}
