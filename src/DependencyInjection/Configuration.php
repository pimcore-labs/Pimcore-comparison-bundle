<?php

declare(strict_types=1);

namespace Pimcore\Bundle\ComparisonBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Bundle configuration (base-idea §6.5): normalization toggles, the default filter mode, export
 * formats, and per-class excluded fields.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pimcore_comparison');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('normalization')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('trim')->defaultTrue()
                            ->info('Trim-insensitive string comparison.')
                        ->end()
                        ->floatNode('numeric_epsilon')->defaultValue(0.0)
                            ->info('Absolute tolerance for numeric equality.')
                        ->end()
                        ->booleanNode('empty_string_equals_null')->defaultTrue()
                            ->info('Treat "" and null as equal.')
                        ->end()
                    ->end()
                ->end()
                ->enumNode('default_filter')
                    ->values(['all', 'differences', 'equal'])
                    ->defaultValue('differences')
                    ->info('Which rows the diff table shows by default.')
                ->end()
                ->arrayNode('export')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('formats')
                            ->scalarPrototype()->end()
                            ->defaultValue(['xlsx', 'json'])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('excluded_fields')
                    ->info('Per-class field names to exclude from comparison, keyed by class name.')
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->scalarPrototype()->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
