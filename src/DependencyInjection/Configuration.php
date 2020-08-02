<?php

declare(strict_types=1);

namespace Vrok\MonitoringBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('vrok_monitoring');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('monitor_address')
                    ->isRequired()
                ->end()
                ->scalarNode('app_name')
                    ->defaultValue('Symfony App')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
