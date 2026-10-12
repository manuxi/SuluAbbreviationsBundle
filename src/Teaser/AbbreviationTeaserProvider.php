<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Teaser;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentMetadataInspector\ContentMetadataInspectorInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Teaser\ContentTeaserProvider;
use Sulu\Bundle\PageBundle\Teaser\Configuration\TeaserConfiguration;
use Sulu\Component\Content\Metadata\Factory\StructureMetadataFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends ContentTeaserProvider<AbbreviationDimensionContent, Abbreviation>
 */
class AbbreviationTeaserProvider extends ContentTeaserProvider
{
    public function __construct(
        ContentManagerInterface $contentManager,
        EntityManagerInterface $entityManager,
        ContentMetadataInspectorInterface $contentMetadataInspector,
        StructureMetadataFactoryInterface $metadataFactory,
        private readonly TranslatorInterface $translator,
        bool $showDrafts,
    ) {
        parent::__construct($contentManager, $entityManager, $contentMetadataInspector, $metadataFactory, Abbreviation::class, $showDrafts);
    }

    public function getConfiguration(): TeaserConfiguration
    {
        return new TeaserConfiguration(
            $this->translator->trans('sulu_abbreviations.abbreviations', [], 'admin'),
            $this->getResourceKey(),
            'table',
            ['name'],
            $this->translator->trans('sulu_abbreviations.select_abbreviationlist', [], 'admin')
        );
    }

    /**
     * @param AbbreviationDimensionContent $dimensionContent
     * @param array<string, mixed>         $data
     */
    protected function getDescription(DimensionContentInterface $dimensionContent, array $data): ?string
    {
        if ($excerptDescription = $dimensionContent->getExcerptDescription()) {
            return $excerptDescription;
        }

        $explanation = $data['explanation'] ?? null;
        $description = \is_string($explanation) ? trim(strip_tags($explanation)) : '';

        return '' !== $description ? $description : parent::getDescription($dimensionContent, $data);
    }
}
