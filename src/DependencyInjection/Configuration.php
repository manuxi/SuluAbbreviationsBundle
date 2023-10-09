<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\DependencyInjection;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerptTranslation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeoTranslation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptRepository;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptTranslationRepository;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationSeoRepository;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationSeoTranslationRepository;
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
                    ->arrayNode('abbreviation')
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
                    ->arrayNode('abbreviation_seo')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(AbbreviationSeo::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationSeoRepository::class)->end()
                        ->end()
                    ->end()
                    ->arrayNode('abbreviation_seo_translation')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(AbbreviationSeoTranslation::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationSeoTranslationRepository::class)->end()
                        ->end()
                    ->end()
                    ->arrayNode('abbreviation_excerpt')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(AbbreviationExcerpt::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationExcerptRepository::class)->end()
                        ->end()
                    ->end()
                    ->arrayNode('abbreviation_excerpt_translation')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue(AbbreviationExcerptTranslation::class)->end()
                            ->scalarNode('repository')->defaultValue(AbbreviationExcerptTranslationRepository::class)->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
