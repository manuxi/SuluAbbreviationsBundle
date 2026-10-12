<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Sulu\Bundle\TrashBundle\Application\DoctrineRestoreHelper\DoctrineRestoreHelperInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;

class AbbreviationTrashItemHandler implements StoreTrashItemHandlerInterface, RestoreTrashItemHandlerInterface, RestoreConfigurationProviderInterface
{
    public function __construct(
        private readonly TrashItemRepositoryInterface $trashItemRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DoctrineRestoreHelperInterface $doctrineRestoreHelper,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly ContentIndexerInterface $contentIndexer,
    ) {
    }

    public static function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function store(object $resource, array $options = []): TrashItemInterface
    {
        \assert($resource instanceof Abbreviation);

        $dimensionContents = [];
        $title             = null;

        foreach ($resource->getDimensionContents() as $dimensionContent) {
            $dimensionContents[] = $this->serializeDimensionContent($dimensionContent);

            if (null === $title && null !== $dimensionContent->getLocale() && DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()) {
                $title = $dimensionContent->getTitle();
            }
        }

        $data = [
            'legacyId'          => $resource->getLegacyId(),
            'dimensionContents' => $dimensionContents,
        ];

        return $this->trashItemRepository->create(
            Abbreviation::RESOURCE_KEY,
            $resource->getId(),
            $title,
            $data,
            null,
            $options,
            Abbreviation::SECURITY_CONTEXT,
            null,
            null
        );
    }

    /**
     * @param array<string, mixed> $restoreFormData
     */
    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        /** @var array{legacyId?: int|null, dimensionContents?: array<int, array<string, mixed>>} $data */
        $data = $trashItem->getRestoreData();

        $abbreviation = new Abbreviation((string) $trashItem->getResourceId());
        $abbreviation->setLegacyId($data['legacyId'] ?? null);

        $title = null;
        foreach ($data['dimensionContents'] ?? [] as $dimensionData) {
            $dimensionContent = $this->restoreDimensionContent($abbreviation, $dimensionData);
            $abbreviation->addDimensionContent($dimensionContent);

            if (null === $title && null !== $dimensionContent->getLocale()) {
                $title = $dimensionContent->getTitle();
            }
        }

        $this->doctrineRestoreHelper->persistAndFlushWithId($abbreviation, $abbreviation->getId());

        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            $this->restoreRoute($dimensionContent);
            $this->reindex($abbreviation, $dimensionContent);
        }

        $this->domainEventCollector->collect(new AbbreviationRestoredEvent($abbreviation->getId(), $title));
        $this->entityManager->flush();

        return $abbreviation;
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            AbbreviationsAdmin::EDIT_FORM_VIEW,
            ['id' => 'id']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDimensionContent(AbbreviationDimensionContent $dimensionContent): array
    {
        return [
            'stage'              => $dimensionContent->getStage(),
            'locale'             => $dimensionContent->getLocale(),
            'ghostLocale'        => $dimensionContent->getGhostLocale(),
            'availableLocales'   => $dimensionContent->getAvailableLocales(),
            'templateKey'        => $dimensionContent->getTemplateKey(),
            'templateData'       => $dimensionContent->getTemplateData(),
            'seoTitle'           => $dimensionContent->getSeoTitle(),
            'seoDescription'     => $dimensionContent->getSeoDescription(),
            'seoKeywords'        => $dimensionContent->getSeoKeywords(),
            'seoCanonicalUrl'    => $dimensionContent->getSeoCanonicalUrl(),
            'seoNoIndex'         => $dimensionContent->getSeoNoIndex(),
            'seoNoFollow'        => $dimensionContent->getSeoNoFollow(),
            'seoHideInSitemap'   => $dimensionContent->getSeoHideInSitemap(),
            'excerptTitle'       => $dimensionContent->getExcerptTitle(),
            'excerptMore'        => $dimensionContent->getExcerptMore(),
            'excerptDescription' => $dimensionContent->getExcerptDescription(),
            'excerptImage'       => $dimensionContent->getExcerptImage(),
            'excerptIcon'        => $dimensionContent->getExcerptIcon(),
            'excerptTagIds'      => array_map(static fn (TagInterface $tag): int => $tag->getId(), $dimensionContent->getExcerptTags()),
            'excerptCategoryIds' => array_values($dimensionContent->getExcerptCategoryIds()),
            'authorId'           => $dimensionContent->getAuthor()?->getId(),
            'authored'           => $dimensionContent->getAuthored()?->format(\DATE_ATOM),
            'workflowPlace'      => $dimensionContent->getWorkflowPlace(),
            'workflowPublished'  => $dimensionContent->getWorkflowPublished()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function restoreDimensionContent(Abbreviation $abbreviation, array $data): AbbreviationDimensionContent
    {
        /** @var AbbreviationDimensionContent $dimensionContent */
        $dimensionContent = $abbreviation->createDimensionContent();
        $dimensionContent->setStage((string) $data['stage']);
        $dimensionContent->setLocale($data['locale'] ?? null);
        $dimensionContent->setGhostLocale($data['ghostLocale'] ?? null);

        foreach ($data['availableLocales'] ?? [] as $availableLocale) {
            $dimensionContent->addAvailableLocale((string) $availableLocale);
        }

        $dimensionContent->setTemplateKey($data['templateKey'] ?? null);
        $dimensionContent->setTemplateData($data['templateData'] ?? []);
        $dimensionContent->setSeoTitle($data['seoTitle'] ?? null);
        $dimensionContent->setSeoDescription($data['seoDescription'] ?? null);
        $dimensionContent->setSeoKeywords($data['seoKeywords'] ?? null);
        $dimensionContent->setSeoCanonicalUrl($data['seoCanonicalUrl'] ?? null);
        $dimensionContent->setSeoNoIndex((bool) ($data['seoNoIndex'] ?? false));
        $dimensionContent->setSeoNoFollow((bool) ($data['seoNoFollow'] ?? false));
        $dimensionContent->setSeoHideInSitemap((bool) ($data['seoHideInSitemap'] ?? false));
        $dimensionContent->setExcerptTitle($data['excerptTitle'] ?? null);
        $dimensionContent->setExcerptMore($data['excerptMore'] ?? null);
        $dimensionContent->setExcerptDescription($data['excerptDescription'] ?? null);
        $dimensionContent->setExcerptImage($data['excerptImage'] ?? null);
        $dimensionContent->setExcerptIcon($data['excerptIcon'] ?? null);
        $dimensionContent->setExcerptTags($this->findAll(TagInterface::class, $data['excerptTagIds'] ?? []));
        $dimensionContent->setExcerptCategories($this->findAll(CategoryInterface::class, $data['excerptCategoryIds'] ?? []));

        if (isset($data['authorId'])) {
            $author = $this->entityManager->find(ContactInterface::class, $data['authorId']);
            $dimensionContent->setAuthor($author instanceof ContactInterface ? $author : null);
        }

        $dimensionContent->setAuthored(isset($data['authored']) ? new \DateTimeImmutable((string) $data['authored']) : null);
        $dimensionContent->setWorkflowPlace($data['workflowPlace'] ?? null);
        $dimensionContent->setWorkflowPublished(isset($data['workflowPublished']) ? new \DateTimeImmutable((string) $data['workflowPublished']) : null);

        return $dimensionContent;
    }

    /**
     * @param class-string      $class
     * @param array<int, mixed> $ids
     *
     * @return object[]
     */
    private function findAll(string $class, array $ids): array
    {
        $objects = [];
        foreach ($ids as $id) {
            $object = $this->entityManager->find($class, $id);
            if (null !== $object) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    private function reindex(Abbreviation $abbreviation, AbbreviationDimensionContent $dimensionContent): void
    {
        $locale = $dimensionContent->getLocale();
        if (null === $locale) {
            return;
        }

        try {
            $this->contentIndexer->index($abbreviation, ['locale' => $locale, 'stage' => $dimensionContent->getStage()]);
        } catch (ContentNotFoundException) {
            return;
        }
    }

    private function restoreRoute(AbbreviationDimensionContent $dimensionContent): void
    {
        $locale = $dimensionContent->getLocale();
        $url    = $dimensionContent->getTemplateData()['url'] ?? null;

        if (DimensionContentInterface::STAGE_LIVE !== $dimensionContent->getStage() || null === $locale || !\is_string($url) || '' === $url) {
            return;
        }

        $route = new Route();
        $route->setPath($url);
        $route->setLocale($locale);
        $route->setEntityClass(Abbreviation::class);
        $route->setEntityId($dimensionContent->getResourceId());
        $route->setHistory(false);
        $route->setCreated(new \DateTime());
        $route->setChanged(new \DateTime());
        $this->entityManager->persist($route);
    }
}
