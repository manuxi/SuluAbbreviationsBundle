<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Entity\Models;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCreatedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Models\AbbreviationModel;
use Manuxi\SuluAbbreviationsBundle\Mapper\AbbreviationDataMapper;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Traits\AbbreviationFactoryTrait;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;
use Sulu\Bundle\RouteBundle\Model\RouteInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AbbreviationModelTest extends TestCase
{
    use AbbreviationFactoryTrait;
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<AbbreviationRepository>
     */
    private ObjectProphecy $repository;

    /**
     * @var ObjectProphecy<RouteManagerInterface>
     */
    private ObjectProphecy $routeManager;

    /**
     * @var ObjectProphecy<EntityManagerInterface>
     */
    private ObjectProphecy $entityManager;

    /**
     * @var ObjectProphecy<DomainEventCollectorInterface>
     */
    private ObjectProphecy $domainEventCollector;

    private AbbreviationModel $model;

    protected function setUp(): void
    {
        $this->repository           = $this->prophesize(AbbreviationRepository::class);
        $this->routeManager         = $this->prophesize(RouteManagerInterface::class);
        $this->entityManager        = $this->prophesize(EntityManagerInterface::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $dispatcher = $this->prophesize(EventDispatcherInterface::class);
        $dispatcher->dispatch(Argument::any())->willReturnArgument(0);

        $this->repository->getClassName()->willReturn(Abbreviation::class);

        $this->model = new AbbreviationModel(
            $this->repository->reveal(),
            $this->prophesize(AbbreviationDataMapper::class)->reveal(),
            $this->routeManager->reveal(),
            $this->prophesize(RouteRepositoryInterface::class)->reveal(),
            $this->entityManager->reveal(),
            $this->domainEventCollector->reveal(),
            $dispatcher->reveal()
        );
    }

    public function testCopyCreatesRoutesForAllLocales(): void
    {
        $original = $this->createAbbreviation(5, 'de', true, 'API');
        $original->setRoutePath('/abkuerzungen/api');
        $original->setLocale('en');
        $original->setName('API');
        $original->setRoutePath('/abbreviations/api');

        $this->repository->find(5)->willReturn($original);
        $this->repository->save(Argument::type(Abbreviation::class))->will(static function (array $args): Abbreviation {
            (new \ReflectionProperty(Abbreviation::class, 'id'))->setValue($args[0], 6);

            return $args[0];
        });

        $this->routeManager->createOrUpdateByAttributes(Abbreviation::class, '6', 'de', '/abkuerzungen/api-copy')
            ->shouldBeCalledOnce()
            ->willReturn($this->createRoute('/abkuerzungen/api-copy'));
        $this->routeManager->createOrUpdateByAttributes(Abbreviation::class, '6', 'en', '/abbreviations/api-copy')
            ->shouldBeCalledOnce()
            ->willReturn($this->createRoute('/abbreviations/api-copy-1'));

        $this->domainEventCollector->collect(Argument::type(AbbreviationCreatedEvent::class))->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $copy = $this->model->copy(5, new Request(['locale' => 'de']));

        $this->assertSame(6, $copy->getId());
        $this->assertSame('de', $copy->getLocale());
        $this->assertFalse($copy->isPublished());

        $copy->setLocale('en');
        $this->assertSame('/abbreviations/api-copy-1', $copy->getRoutePath());
    }

    public function testCopyOfUnknownAbbreviationThrows(): void
    {
        $this->repository->find(5)->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $this->model->copy(5, new Request());
    }

    private function createRoute(string $path): RouteInterface
    {
        $route = $this->prophesize(RouteInterface::class);
        $route->getPath()->willReturn($path);

        return $route->reveal();
    }
}
