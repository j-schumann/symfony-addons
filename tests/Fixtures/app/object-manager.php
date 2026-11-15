<?php

// Loads the entity manager for phpstan/phpstan-doctrine

require_once __DIR__.'/bootstrap.php';

$kernel = new AppKernel('test', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine')->getManager();
