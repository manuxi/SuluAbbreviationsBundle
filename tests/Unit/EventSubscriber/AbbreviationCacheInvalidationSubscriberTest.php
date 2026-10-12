<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\EventSubscriber;

use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\CategoryBundle\Entity\Category;
use Sulu\Bundle\HttpCacheBundle\Cache\CacheManagerInterface;
use Sulu\Bundle\TagBundle\Entity\Tag;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class AbbreviationCacheInvalidationSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<CacheManagerInterface> */
    private ObjectProphecy $cacheManager;

    private AbbreviationCacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cacheManager = $this->prophesize(CacheManagerInterface::class);
        $this->cacheManager->invalidateReference(Argument::cetera())->will(static function (): void {
        });

        $this->subscriber = new AbbreviationCacheInvalidationSubscriber($this->cacheManager->reveal());
    }

    private function event(object $subject, string $transition): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition($transition, 'a', 'b'));
    }

    private function createContent(): AbbreviationDimensionContent
    {
        $tag = new Tag();
        $tag->setName('php');
        $category = new Category();

        $content = new AbbreviationDimensionContent(new Abbreviation());
        $content->setLocale('en');
        $content->setExcerptTags([$this->withId($tag, 4)]);
        $content->setExcerptCategories([$this->withId($category, 9)]);

        return $content;
    }

    private function withId(object $entity, int $id): object
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);

        return $entity;
    }

    public function testPublishInvalidatesAfterTheFlushOnly(): void
    {
        $content = $this->createContent();

        $this->subscriber->onPublish($this->event($content, 'publish'));
        $this->cacheManager->invalidateReference(Argument::cetera())->shouldNotHaveBeenCalled();

        $this->subscriber->postFlush();

        $id = $content->getResource()->getId();
        $this->cacheManager->invalidateReference('abbreviations', $id)->shouldHaveBeenCalledOnce();
        $this->cacheManager->invalidateReference('abbreviations', 'list')->shouldHaveBeenCalledOnce();
        $this->cacheManager->invalidateReference('tag', '4')->shouldHaveBeenCalledOnce();
        $this->cacheManager->invalidateReference('category', '9')->shouldHaveBeenCalledOnce();
    }

    public function testUnpublishInvalidatesAfterTheFlush(): void
    {
        $content = $this->createContent();

        $this->subscriber->onUnpublish($this->event($content, 'unpublish'));
        $this->subscriber->postFlush();

        $this->cacheManager->invalidateReference('abbreviations', $content->getResource()->getId())->shouldHaveBeenCalledOnce();
    }

    public function testQueuedInvalidationsAreExecutedOnce(): void
    {
        $content = $this->createContent();
        $this->subscriber->onPublish($this->event($content, 'publish'));

        $this->subscriber->postFlush();
        $this->subscriber->postFlush();

        $this->cacheManager->invalidateReference('abbreviations', $content->getResource()->getId())->shouldHaveBeenCalledOnce();
    }

    public function testSameAbbreviationIsInvalidatedOncePerFlush(): void
    {
        $content = $this->createContent();
        $this->subscriber->onPublish($this->event($content, 'publish'));
        $this->subscriber->onPublish($this->event($content, 'publish'));

        $this->subscriber->postFlush();

        $this->cacheManager->invalidateReference('abbreviations', $content->getResource()->getId())->shouldHaveBeenCalledOnce();
    }

    public function testDomainEventInvalidatesImmediately(): void
    {
        $this->subscriber->onDomainEvent(new AbbreviationRemovedEvent('abbr-id', 'HTML', 'en'));

        $this->cacheManager->invalidateReference('abbreviations', 'abbr-id')->shouldHaveBeenCalledOnce();
        $this->cacheManager->invalidateReference('abbreviations', 'list')->shouldHaveBeenCalledOnce();
    }

    public function testOtherSubjectsAreIgnored(): void
    {
        $this->subscriber->onPublish($this->event(new \stdClass(), 'publish'));
        $this->subscriber->postFlush();

        $this->cacheManager->invalidateReference(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testWorksWithoutCacheManager(): void
    {
        $subscriber = new AbbreviationCacheInvalidationSubscriber(null);

        $subscriber->onPublish($this->event($this->createContent(), 'publish'));
        $subscriber->onUnpublish($this->event($this->createContent(), 'unpublish'));
        $subscriber->onDomainEvent(new AbbreviationRemovedEvent('abbr-id'));
        $subscriber->postFlush();

        $this->addToAssertionCount(1);
    }
}
