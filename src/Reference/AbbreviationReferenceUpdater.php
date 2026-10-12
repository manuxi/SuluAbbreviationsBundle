<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Reference;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Structure\ContentStructureBridgeFactory;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Structure\StructureMetadataNotFoundException;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollector;
use Sulu\Bundle\ReferenceBundle\Application\Collector\ReferenceCollectorInterface;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\ContentType\ReferenceContentTypeInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Component\Content\ContentTypeManagerInterface;
use Sulu\Component\HttpKernel\SuluKernel;

/**
 * Stores which media, contacts, categories, tags and other resources an abbreviation uses.
 */
class AbbreviationReferenceUpdater
{
    public function __construct(
        private readonly ReferenceRepositoryInterface $referenceRepository,
        private readonly ContentStructureBridgeFactory $structureBridgeFactory,
        private readonly ContentTypeManagerInterface $contentTypeManager,
    ) {
    }

    public static function getContext(string $stage): string
    {
        return DimensionContentInterface::STAGE_LIVE === $stage ? SuluKernel::CONTEXT_WEBSITE : SuluKernel::CONTEXT_ADMIN;
    }

    /**
     * Replaces the references of the given content (draft: admin context, live: website context).
     */
    public function update(AbbreviationDimensionContent $dimensionContent, ?string $context = null): void
    {
        $locale = $dimensionContent->getLocale();
        if (null === $locale) {
            return;
        }

        $context ??= self::getContext($dimensionContent->getStage());
        $id       = $dimensionContent->getResource()->getId();

        $collector = new ReferenceCollector(
            $this->referenceRepository,
            Abbreviation::RESOURCE_KEY,
            $id,
            $locale,
            $dimensionContent->getTitle() ?? '',
            $context,
            ['locale' => $locale]
        );

        $this->collectTemplateReferences($dimensionContent, $id, $locale, $collector);
        $this->collectFixedReferences($dimensionContent, $collector);

        $collector->persistReferences();
    }

    /**
     * Removes the references of an abbreviation, optionally limited to a locale and a context.
     */
    public function remove(Abbreviation $abbreviation, ?string $locale = null, ?string $context = null): void
    {
        $this->referenceRepository->removeBy(array_filter([
            'referenceResourceKey' => Abbreviation::RESOURCE_KEY,
            'referenceResourceId'  => $abbreviation->getId(),
            'referenceLocale'      => $locale,
            'referenceContext'     => $context,
        ]));
    }

    private function collectTemplateReferences(
        AbbreviationDimensionContent $dimensionContent,
        string $id,
        string $locale,
        ReferenceCollectorInterface $collector,
    ): void {
        try {
            $structure = $this->structureBridgeFactory->getBridge($dimensionContent, $id, $locale);
        } catch (StructureMetadataNotFoundException) {
            return;
        }

        foreach ($structure->getProperties(true) as $property) {
            $contentType = $this->contentTypeManager->get($property->getContentTypeName());

            if (!$contentType instanceof ReferenceContentTypeInterface) {
                continue;
            }

            $contentType->getReferences($property, $collector);
        }
    }

    private function collectFixedReferences(AbbreviationDimensionContent $dimensionContent, ReferenceCollectorInterface $collector): void
    {
        $image = $dimensionContent->getExcerptImage();
        if (null !== $image) {
            $collector->addReference(MediaInterface::RESOURCE_KEY, (string) $image['id'], 'excerptImage');
        }

        $icon = $dimensionContent->getExcerptIcon();
        if (null !== $icon) {
            $collector->addReference(MediaInterface::RESOURCE_KEY, (string) $icon['id'], 'excerptIcon');
        }

        foreach ($dimensionContent->getExcerptCategoryIds() as $categoryId) {
            $collector->addReference(CategoryInterface::RESOURCE_KEY, (string) $categoryId, 'excerptCategories');
        }

        foreach ($dimensionContent->getExcerptTags() as $tag) {
            $collector->addReference(TagInterface::RESOURCE_KEY, (string) $tag->getId(), 'excerptTags');
        }

        $author = $dimensionContent->getAuthor();
        if (null !== $author) {
            $collector->addReference(ContactInterface::RESOURCE_KEY, (string) $author->getId(), 'author');
        }
    }
}
