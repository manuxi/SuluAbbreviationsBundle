<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\EventSubscriber;

use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbstractAbbreviationEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Purges the cached responses which contain an abbreviation when it is published, unpublished, removed or restored.
 *
 * Invalidations caused by workflow transitions are executed after the next flush, so that the cache is not
 * refilled with the old content.
 */
class AbbreviationCacheInvalidationSubscriber
{
    /**
     * Reference id which is added to every response containing a list of abbreviations (twig function, smart content).
     */
    public const LIST_REFERENCE = 'list';

    /**
     * @var array<string, array{tags: string[], categories: string[]}>
     */
    private array $pending = [];

    public function __construct(
        private readonly ?CacheManagerInterface $cacheManager = null,
    ) {
    }

    public function onPublish(CompletedEvent $event): void
    {
        $this->queue($event);
    }

    public function onUnpublish(CompletedEvent $event): void
    {
        $this->queue($event);
    }

    public function postFlush(): void
    {
        $pending       = $this->pending;
        $this->pending = [];

        foreach ($pending as $id => $references) {
            $this->invalidate($id, $references['tags'], $references['categories']);
        }
    }

    public function onDomainEvent(AbstractAbbreviationEvent $event): void
    {
        $this->invalidate($event->getAbbreviationId());
    }

    private function queue(CompletedEvent $event): void
    {
        if (null === $this->cacheManager) {
            return;
        }

        $dimensionContent = $event->getSubject();
        if (!$dimensionContent instanceof AbbreviationDimensionContent) {
            return;
        }

        $id = $dimensionContent->getResource()->getId();

        $this->pending[$id] = [
            'tags'       => array_map(static fn (TagInterface $tag): string => (string) $tag->getId(), $dimensionContent->getExcerptTags()),
            'categories' => array_map('strval', $dimensionContent->getExcerptCategoryIds()),
        ];
    }

    /**
     * @param string[] $tagIds
     * @param string[] $categoryIds
     */
    private function invalidate(string $id, array $tagIds = [], array $categoryIds = []): void
    {
        if (null === $this->cacheManager) {
            return;
        }

        $this->cacheManager->invalidateReference(Abbreviation::RESOURCE_KEY, $id);
        $this->cacheManager->invalidateReference(Abbreviation::RESOURCE_KEY, self::LIST_REFERENCE);

        foreach ($tagIds as $tagId) {
            $this->cacheManager->invalidateReference('tag', $tagId);
        }

        foreach ($categoryIds as $categoryId) {
            $this->cacheManager->invalidateReference('category', $categoryId);
        }
    }
}
