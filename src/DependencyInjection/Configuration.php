<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationTranslationRepository;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('sulu_abbreviations');
        $root = $treeBuilder->getRootNode();

        $root
            ->children()
            ->arrayNode('objects')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('news')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(Abbreviation::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationRepository::class)->end()
                        ->end()
                    ->end()
                    ->arrayNode('abbreviation_translation')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(AbbreviationTranslation::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationTranslationRepository::class)->end()
                        ->end()
                    ->end()

                ->end()
            ->end();

        return $treeBuilder;
    }
}
