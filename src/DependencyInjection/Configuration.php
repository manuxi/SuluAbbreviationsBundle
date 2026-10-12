<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sulu_abbreviations');
        $root        = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('versioning')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_versions')
                            ->min(0)
                            ->defaultValue(20)
                            ->info('Number of versions kept per abbreviation and locale. 0 disables versioning.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('twig')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_abbr')
                            ->defaultFalse()
                            ->info('Registers the "abbr" twig filter which wraps known abbreviations in abbr tags.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
