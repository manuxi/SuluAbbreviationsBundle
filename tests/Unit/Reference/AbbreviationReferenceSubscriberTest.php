<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Reference;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceSubscriber;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceUpdater;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Marking;
use Symfony\Component\Workflow\Transition;

class AbbreviationReferenceSubscriberTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AbbreviationReferenceUpdater> */
    private ObjectProphecy $updater;

    private AbbreviationReferenceSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->updater = $this->prophesize(AbbreviationReferenceUpdater::class);
        $this->updater->update(Argument::cetera())->will(static function (): void {
        });
        $this->updater->remove(Argument::cetera())->will(static function (): void {
        });

        $this->subscriber = new AbbreviationReferenceSubscriber($this->updater->reveal());
    }

    private function event(object $subject): CompletedEvent
    {
        return new CompletedEvent($subject, new Marking(), new Transition('t', 'a', 'b'));
    }

    private function draft(?string $locale = 'en'): AbbreviationDimensionContent
    {
        $draft = new AbbreviationDimensionContent(new Abbreviation());
        $draft->setLocale($locale);

        return $draft;
    }

    public function testPublishUpdatesTheWebsiteReferences(): void
    {
        $draft = $this->draft();

        $this->subscriber->onPublish($this->event($draft));

        $this->updater->update($draft, 'website')->shouldHaveBeenCalledOnce();
    }

    public function testRemoveDraftUpdatesTheAdminReferences(): void
    {
        $draft = $this->draft();

        $this->subscriber->onRemoveDraft($this->event($draft));

        $this->updater->update($draft, 'admin')->shouldHaveBeenCalledOnce();
    }

    public function testUnpublishRemovesTheWebsiteReferencesOfTheLocale(): void
    {
        $draft = $this->draft('de');

        $this->subscriber->onUnpublish($this->event($draft));

        $this->updater->remove($draft->getResource(), 'de', 'website')->shouldHaveBeenCalledOnce();
    }

    public function testUnpublishIgnoresUnlocalizedContent(): void
    {
        $this->subscriber->onUnpublish($this->event($this->draft(null)));

        $this->updater->remove(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testOtherSubjectsAreIgnored(): void
    {
        $event = $this->event(new \stdClass());

        $this->subscriber->onPublish($event);
        $this->subscriber->onUnpublish($event);
        $this->subscriber->onRemoveDraft($event);

        $this->updater->update(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->updater->remove(Argument::cetera())->shouldNotHaveBeenCalled();
    }
}
