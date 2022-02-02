<?php

declare(strict_types=1);

namespace Vrok\SymfonyAddons\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * (Empty) Configuration class is required for the ConfigurableExtension to work...
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('vrok_symfony_addons');

        return $treeBuilder;
    }
}
