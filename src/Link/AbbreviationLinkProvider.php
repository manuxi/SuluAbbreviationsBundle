<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Link;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Link\ContentLinkProvider;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfiguration;
use Sulu\Bundle\MarkupBundle\Markup\Link\LinkConfigurationBuilder;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends ContentLinkProvider<AbbreviationDimensionContent, Abbreviation>
 */
class AbbreviationLinkProvider extends ContentLinkProvider
{
    public function __construct(
        ContentManagerInterface $contentManager,
        StructureMetadataFactoryInterface $structureMetadataFactory,
        EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($contentManager, $structureMetadataFactory, $entityManager, Abbreviation::class);
    }

    public function getConfiguration(): LinkConfiguration
    {
        return LinkConfigurationBuilder::create()
            ->setTitle($this->translator->trans('sulu_abbreviations.abbreviation', [], 'admin'))
            ->setResourceKey(Abbreviation::RESOURCE_KEY)
            ->setListAdapter('table')
            ->setDisplayProperties(['name'])
            ->setOverlayTitle($this->translator->trans('sulu_abbreviations.select_abbreviation', [], 'admin'))
            ->setEmptyText($this->translator->trans('sulu_abbreviations.no_abbreviation_selected', [], 'admin'))
            ->setIcon('su-tag-pen')
            ->getLinkConfiguration();
    }
}
