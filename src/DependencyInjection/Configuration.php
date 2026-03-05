<?php

namespace OwlConcept\SettingsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('owl_settings');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('css_class_prefix')
                    ->defaultValue('owl-settings')
                ->end()
                ->scalarNode('cache_pool')
                    ->defaultValue('cache.app')
                ->end()
                ->integerNode('cache_ttl')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
                ->scalarNode('settings_table')
                    ->defaultValue('owl_settings')
                ->end()
                ->scalarNode('preferences_table')
                    ->defaultValue('owl_user_preferences')
                ->end()
                ->scalarNode('user_identifier_method')
                    ->defaultValue('getUserIdentifier')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
