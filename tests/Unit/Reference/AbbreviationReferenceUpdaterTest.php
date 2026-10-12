<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Reference;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceUpdater;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Structure\ContentStructureBridgeFactory;
use Sulu\Bundle\ReferenceBundle\Domain\Repository\ReferenceRepositoryInterface;
use Sulu\Component\Content\ContentTypeManagerInterface;

class AbbreviationReferenceUpdaterTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ReferenceRepositoryInterface> */
    private ObjectProphecy $repository;

    private AbbreviationReferenceUpdater $updater;

    protected function setUp(): void
    {
        $this->repository = $this->prophesize(ReferenceRepositoryInterface::class);
        $this->repository->removeBy(Argument::any())->will(static function (): void {
        });

        $this->updater = new AbbreviationReferenceUpdater(
            $this->repository->reveal(),
            $this->prophesize(ContentStructureBridgeFactory::class)->reveal(),
            $this->prophesize(ContentTypeManagerInterface::class)->reveal()
        );
    }

    public function testContextDependsOnTheStage(): void
    {
        $this->assertSame('admin', AbbreviationReferenceUpdater::getContext('draft'));
        $this->assertSame('website', AbbreviationReferenceUpdater::getContext('live'));
    }

    public function testRemoveDeletesAllReferencesOfTheAbbreviation(): void
    {
        $abbreviation = new Abbreviation();

        $this->updater->remove($abbreviation);

        $this->repository->removeBy([
            'referenceResourceKey' => 'abbreviations',
            'referenceResourceId'  => $abbreviation->getId(),
        ])->shouldHaveBeenCalledOnce();
    }

    public function testRemoveCanBeLimitedToLocaleAndContext(): void
    {
        $abbreviation = new Abbreviation();

        $this->updater->remove($abbreviation, 'de', 'website');

        $this->repository->removeBy([
            'referenceResourceKey' => 'abbreviations',
            'referenceResourceId'  => $abbreviation->getId(),
            'referenceLocale'      => 'de',
            'referenceContext'     => 'website',
        ])->shouldHaveBeenCalledOnce();
    }

    public function testUpdateIgnoresUnlocalizedContent(): void
    {
        $this->updater->update(new AbbreviationDimensionContent(new Abbreviation()));

        $this->repository->removeBy(Argument::any())->shouldNotHaveBeenCalled();
    }
}
