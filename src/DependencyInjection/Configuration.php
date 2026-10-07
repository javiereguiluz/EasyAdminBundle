<?php

namespace EasyCorp\Bundle\EasyAdminBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('easy_admin');

        $treeBuilder->getRootNode()
            // EasyAdmin ignored the "easy_admin" config before having this tree, so old
            // applications can still define options that no longer exist
            ->ignoreExtraKeys()
            ->children()
                ->arrayNode('mcp')
                    ->info('Options of the MCP server (experimental)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('limits')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('max_page_size')
                                    ->info('The maximum number of records returned by each call that lists records')
                                    ->defaultValue(50)
                                    ->min(1)
                                ->end()
                                ->integerNode('max_response_bytes')
                                    ->info('Calls whose response is larger than this fail with an error asking to narrow the results (they are never truncated silently)')
                                    ->defaultValue(256 * 1024)
                                    ->min(1024)
                                ->end()
                                ->integerNode('max_string_length')
                                    ->info('Longer string values are truncated and marked as such')
                                    ->defaultValue(2000)
                                    ->min(1)
                                ->end()
                                ->integerNode('max_to_many_items')
                                    ->info('The maximum number of related records included for each to-many association (their total count is always included)')
                                    ->defaultValue(10)
                                    ->min(0)
                                ->end()
                                ->integerNode('calls_per_minute')
                                    ->info('The maximum number of tool calls per minute for each user, for all their MCP clients (requires symfony/rate-limiter; 0 disables the limit)')
                                    ->defaultValue(120)
                                    ->min(0)
                                ->end()
                                ->integerNode('time_limit')
                                    ->info('The maximum execution time of each tool call, in seconds (0 disables the limit)')
                                    ->defaultValue(30)
                                    ->min(0)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
