<?php

declare(strict_types=1);

use Symfony\Component\ErrorHandler\ErrorHandler;

date_default_timezone_set('UTC');

// @todo remove when https://github.com/symfony/symfony/issues/53812 is fixed
set_exception_handler([new ErrorHandler(), 'handleException']);

$loader = require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/AppKernel.php';

return $loader;
