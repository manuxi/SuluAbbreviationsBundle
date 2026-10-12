<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use Manuxi\SuluAbbreviationsBundle\Trash\AbbreviationsTrashItemHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\TrashBundle\Application\DoctrineRestoreHelper\DoctrineRestoreHelperInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AbbreviationsTrashItemHandlerTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<TrashItemRepositoryInterface>
     */
    private ObjectProphecy $trashItemRepository;

    /**
     * @var ObjectProphecy<EntityManagerInterface>
     */
    private ObjectProphecy $entityManager;

    /**
     * @var ObjectProphecy<DoctrineRestoreHelperInterface>
     */
    private ObjectProphecy $doctrineRestoreHelper;

    /**
     * @var ObjectProphecy<DomainEventCollectorInterface>
     */
    private ObjectProphecy $domainEventCollector;

    private AbbreviationsTrashItemHandler $handler;

    protected function setUp(): void
    {
        $this->trashItemRepository   = $this->prophesize(TrashItemRepositoryInterface::class);
        $this->entityManager         = $this->prophesize(EntityManagerInterface::class);
        $this->doctrineRestoreHelper = $this->prophesize(DoctrineRestoreHelperInterface::class);
        $this->domainEventCollector  = $this->prophesize(DomainEventCollectorInterface::class);

        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $dispatcher->dispatch(Argument::any())->willReturnArgument(0);

        $this->handler = new AbbreviationsTrashItemHandler(
            $this->trashItemRepository->reveal(),
            $this->entityManager->reveal(),
            $this->doctrineRestoreHelper->reveal(),
            $this->domainEventCollector->reveal(),
            $dispatcher->reveal()
        );
    }

    public function testResourceKeyAndConfiguration(): void
    {
        $this->assertSame(Abbreviation::RESOURCE_KEY, AbbreviationsTrashItemHandler::getResourceKey());
        $this->assertSame(AbbreviationsAdmin::EDIT_FORM_VIEW, $this->handler->getConfiguration()->getView());
    }

    public function testStore(): void
    {
        $abbreviation = $this->createAbbreviation(12, 'de', true, 'API');
        $abbreviation->setRoutePath('/abkuerzungen/api');

        $trashItem = $this->prophesize(TrashItemInterface::class)->reveal();
        $this->trashItemRepository->create(
            Abbreviation::RESOURCE_KEY,
            '12',
            'API',
            Argument::that(static fn (array $data): bool => 'API' === $data['name'] && '/abkuerzungen/api' === $data['slug'] && 'de' === $data['locale']),
            null,
            [],
            Abbreviation::SECURITY_CONTEXT,
            null,
            null
        )->shouldBeCalledOnce()->willReturn($trashItem);

        $this->assertSame($trashItem, $this->handler->store($abbreviation));
    }

    public function testRestoreKeepsIdAndCreatesRouteWithStringEntityId(): void
    {
        $trashItem = $this->prophesize(TrashItemInterface::class);
        $trashItem->getResourceId()->willReturn('12');
        $trashItem->getRestoreData()->willReturn([
            'name'        => 'API',
            'explanation' => 'Application Programming Interface',
            'description' => null,
            'slug'        => '/abkuerzungen/api',
            'published'   => true,
            'publishedAt' => ['date' => '2026-01-02 03:04:05.000000'],
            'ext'         => ['seo' => [], 'excerpt' => []],
            'locale'      => 'de',
            'imageId'     => null,
            'link'        => null,
            'showAuthor'  => false,
            'showDate'    => true,
            'authored'    => null,
            'author'      => null,
        ]);

        $this->doctrineRestoreHelper->persistAndFlushWithId(Argument::type(Abbreviation::class), 12)->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(AbbreviationRestoredEvent::class))->shouldBeCalledOnce();
        $this->entityManager->persist(Argument::that(
            static fn ($route): bool => $route instanceof Route
                && '12'                === $route->getEntityId()
                && false               === $route->isHistory()
                && '/abkuerzungen/api' === $route->getPath()
                && 'de'                === $route->getLocale()
                && Abbreviation::class === $route->getEntityClass()
        ))->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $restored = $this->handler->restore($trashItem->reveal());

        $this->assertInstanceOf(Abbreviation::class, $restored);
        $this->assertSame('API', $restored->getName());
        $this->assertTrue($restored->isPublished());
        $this->assertTrue($restored->hasExt('seo'));
        $this->assertSame($restored->getSeo(), $restored->getExt()['seo']);
    }
}
