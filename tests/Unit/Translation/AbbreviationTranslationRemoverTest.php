<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Translation;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationTranslationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceUpdater;
use Manuxi\SuluAbbreviationsBundle\Route\AbbreviationRouteRemover;
use Manuxi\SuluAbbreviationsBundle\Translation\AbbreviationTranslationRemover;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;

class AbbreviationTranslationRemoverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<ContentIndexerInterface> */
    private ObjectProphecy $contentIndexer;

    /** @var ObjectProphecy<AbbreviationRouteRemover> */
    private ObjectProphecy $routeRemover;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $eventCollector;

    /** @var ObjectProphecy<AbbreviationReferenceUpdater> */
    private ObjectProphecy $referenceUpdater;

    private AbbreviationTranslationRemover $remover;

    protected function setUp(): void
    {
        $this->entityManager    = $this->prophesize(EntityManagerInterface::class);
        $this->contentIndexer   = $this->prophesize(ContentIndexerInterface::class);
        $this->routeRemover     = $this->prophesize(AbbreviationRouteRemover::class);
        $this->eventCollector   = $this->prophesize(DomainEventCollectorInterface::class);
        $this->referenceUpdater = $this->prophesize(AbbreviationReferenceUpdater::class);
        $this->referenceUpdater->remove(Argument::cetera())->will(static function (): void {
        });

        $this->entityManager->remove(Argument::any())->will(static function (): void {
        });
        $this->contentIndexer->deindex(Argument::cetera())->will(static function (): void {
        });
        $this->routeRemover->remove(Argument::cetera())->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::any())->will(static function (): void {
        });

        $this->remover = new AbbreviationTranslationRemover(
            $this->entityManager->reveal(),
            $this->contentIndexer->reveal(),
            $this->routeRemover->reveal(),
            $this->eventCollector->reveal(),
            $this->referenceUpdater->reveal()
        );
    }

    /**
     * @param string[] $locales
     */
    private function createAbbreviation(array $locales): Abbreviation
    {
        $abbreviation = new Abbreviation();

        foreach ([DimensionContentInterface::STAGE_DRAFT, DimensionContentInterface::STAGE_LIVE] as $stage) {
            $unlocalized = new AbbreviationDimensionContent($abbreviation);
            $unlocalized->setStage($stage);
            $unlocalized->setGhostLocale($locales[0] ?? null);
            $abbreviation->addDimensionContent($unlocalized);

            foreach ($locales as $locale) {
                $unlocalized->addAvailableLocale($locale);

                $localized = new AbbreviationDimensionContent($abbreviation);
                $localized->setStage($stage);
                $localized->setLocale($locale);
                $localized->setTemplateData(['name' => 'Name ' . $locale]);
                $abbreviation->addDimensionContent($localized);
            }
        }

        return $abbreviation;
    }

    public function testRemovesDraftAndLiveContentOfTheLocale(): void
    {
        $abbreviation = $this->createAbbreviation(['en', 'de']);

        $this->assertTrue($this->remover->remove($abbreviation, 'de'));

        $remaining = [];
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            $remaining[] = $dimensionContent->getStage() . ':' . ($dimensionContent->getLocale() ?? '-');
        }
        sort($remaining);
        $this->assertSame(['draft:-', 'draft:en', 'live:-', 'live:en'], $remaining);
        $this->entityManager->remove(Argument::type(AbbreviationDimensionContent::class))->shouldHaveBeenCalledTimes(2);
    }

    public function testRemovesTheLocaleFromTheAvailableLocalesOfUnlocalizedContent(): void
    {
        $abbreviation = $this->createAbbreviation(['en', 'de']);

        $this->remover->remove($abbreviation, 'de');

        $this->assertSame(['en'], $this->remover->getAvailableLocales($abbreviation));
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale()) {
                $this->assertSame(['en'], array_values($dimensionContent->getAvailableLocales() ?? []));
            }
        }
    }

    public function testGhostLocaleIsSwitchedWhenItWasRemoved(): void
    {
        $abbreviation = $this->createAbbreviation(['de', 'en']);

        $this->remover->remove($abbreviation, 'de');

        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if (null === $dimensionContent->getLocale()) {
                $this->assertSame('en', $dimensionContent->getGhostLocale());
            }
        }
    }

    public function testRoutesSearchIndexAndActivityAreCleanedUp(): void
    {
        $abbreviation = $this->createAbbreviation(['en', 'de']);

        $this->remover->remove($abbreviation, 'de');

        $this->routeRemover->remove($abbreviation, 'de')->shouldHaveBeenCalledOnce();
        $this->referenceUpdater->remove($abbreviation, 'de')->shouldHaveBeenCalledOnce();
        $this->contentIndexer->deindex(Abbreviation::RESOURCE_KEY, $abbreviation->getId(), ['locale' => 'de'])->shouldHaveBeenCalledOnce();
        $this->eventCollector->collect(Argument::that(
            static fn ($event): bool => $event instanceof AbbreviationTranslationRemovedEvent
                && 'de'      === $event->getResourceLocale()
                && 'Name de' === $event->getResourceTitle()
        ))->shouldHaveBeenCalledOnce();
    }

    public function testLastLocaleIsNotRemoved(): void
    {
        $abbreviation = $this->createAbbreviation(['en']);

        $this->assertFalse($this->remover->remove($abbreviation, 'en'));

        $this->assertCount(4, $abbreviation->getDimensionContents());
        $this->entityManager->remove(Argument::any())->shouldNotHaveBeenCalled();
        $this->routeRemover->remove(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->eventCollector->collect(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testUnknownLocaleOfAnAbbreviationWithOneLocaleIsTreatedLikeTheLastLocale(): void
    {
        $abbreviation = $this->createAbbreviation([]);

        $this->assertFalse($this->remover->remove($abbreviation, 'en'));
    }

    public function testAvailableLocalesAreMergedAcrossStages(): void
    {
        $abbreviation = $this->createAbbreviation(['en', 'de']);

        $this->assertEqualsCanonicalizing(['en', 'de'], $this->remover->getAvailableLocales($abbreviation));
    }
}
