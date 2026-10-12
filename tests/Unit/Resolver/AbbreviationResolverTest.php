<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;

class AbbreviationResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<ObjectRepository<Abbreviation>> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;

    /** @var ObjectProphecy<ReferenceStoreInterface> */
    private ObjectProphecy $referenceStore;

    private AbbreviationResolver $resolver;

    protected function setUp(): void
    {
        $this->entityManager  = $this->prophesize(EntityManagerInterface::class);
        $this->repository     = $this->prophesize(ObjectRepository::class);
        $this->contentManager = $this->prophesize(ContentManagerInterface::class);
        $this->referenceStore = $this->prophesize(ReferenceStoreInterface::class);

        $this->entityManager->getRepository(Abbreviation::class)->willReturn($this->repository->reveal());

        $this->resolver = new AbbreviationResolver(
            $this->entityManager->reveal(),
            $this->contentManager->reveal(),
            $this->referenceStore->reveal()
        );
    }

    public function testResolveReturnsNormalizedLiveContentAndTracksReference(): void
    {
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation, 'en', DimensionContentInterface::STAGE_LIVE);

        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($content);
        $this->contentManager->normalize($content)->willReturn(['name' => 'HTML']);

        $this->assertSame(['name' => 'HTML'], $this->resolver->resolve($abbreviation->getId(), 'en'));
        $this->referenceStore->add($abbreviation->getId())->shouldHaveBeenCalledOnce();
    }

    public function testResolveReturnsNullForEmptyId(): void
    {
        $this->repository->find(Argument::any())->shouldNotBeCalled();

        $this->assertNull($this->resolver->resolve('', 'en'));
    }

    public function testResolveReturnsNullForUnknownAbbreviation(): void
    {
        $this->repository->find('unknown')->willReturn(null);

        $this->assertNull($this->resolver->resolve('unknown', 'en'));
        $this->referenceStore->add(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testResolveReturnsNullWhenNoLiveContentExists(): void
    {
        $abbreviation = new Abbreviation();

        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live'])
            ->willThrow(new ContentNotFoundException($abbreviation, []));

        $this->assertNull($this->resolver->resolve($abbreviation->getId(), 'en'));
        $this->referenceStore->add(Argument::any())->shouldNotHaveBeenCalled();
    }

    public function testResolveReturnsNullForGhostLocale(): void
    {
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation, 'de', DimensionContentInterface::STAGE_LIVE);

        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($content);

        $this->assertNull($this->resolver->resolve($abbreviation->getId(), 'en'));
    }

    public function testResolveReturnsNullForDraftStage(): void
    {
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation, 'en', DimensionContentInterface::STAGE_DRAFT);

        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($content);

        $this->assertNull($this->resolver->resolve($abbreviation->getId(), 'en'));
    }

    public function testResolveWorksWithoutReferenceStore(): void
    {
        $resolver     = new AbbreviationResolver($this->entityManager->reveal(), $this->contentManager->reveal());
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation, 'en', DimensionContentInterface::STAGE_LIVE);

        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($content);
        $this->contentManager->normalize($content)->willReturn(['name' => 'CSS']);

        $this->assertSame(['name' => 'CSS'], $resolver->resolve($abbreviation->getId(), 'en'));
    }

    public function testResolveManyKeepsOrderAndSkipsUnpublishedAndInvalidIds(): void
    {
        $first  = new Abbreviation();
        $second = new Abbreviation();
        $third  = new Abbreviation();

        $firstContent  = $this->createContent($first, 'en', DimensionContentInterface::STAGE_LIVE);
        $secondContent = $this->createContent($second, 'en', DimensionContentInterface::STAGE_LIVE);

        $this->repository->find($first->getId())->willReturn($first);
        $this->repository->find($second->getId())->willReturn($second);
        $this->repository->find($third->getId())->willReturn($third);
        $this->contentManager->resolve($first, ['locale' => 'en', 'stage' => 'live'])->willReturn($firstContent);
        $this->contentManager->resolve($second, ['locale' => 'en', 'stage' => 'live'])->willReturn($secondContent);
        $this->contentManager->resolve($third, ['locale' => 'en', 'stage' => 'live'])
            ->willThrow(new ContentNotFoundException($third, []));
        $this->contentManager->normalize($firstContent)->willReturn(['name' => 'A']);
        $this->contentManager->normalize($secondContent)->willReturn(['name' => 'B']);

        $result = $this->resolver->resolveMany([$second->getId(), 42, $third->getId(), $first->getId()], 'en');

        $this->assertSame([['name' => 'B'], ['name' => 'A']], $result);
    }

    private function createContent(Abbreviation $abbreviation, string $locale, string $stage): AbbreviationDimensionContent
    {
        $content = new AbbreviationDimensionContent($abbreviation);
        $content->setLocale($locale);
        $content->setStage($stage);

        return $content;
    }
}
