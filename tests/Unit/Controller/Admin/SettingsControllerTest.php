<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\SettingsController;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SettingsControllerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy<EntityManagerInterface>
     */
    private ObjectProphecy $entityManager;

    /**
     * @var ObjectProphecy<EntityRepository<AbbreviationsSettings>>
     */
    private ObjectProphecy $repository;

    /**
     * @var ObjectProphecy<DomainEventCollectorInterface>
     */
    private ObjectProphecy $domainEventCollector;

    private SettingsController $controller;

    protected function setUp(): void
    {
        $this->entityManager        = $this->prophesize(EntityManagerInterface::class);
        $this->repository           = $this->prophesize(EntityRepository::class);
        $this->domainEventCollector = $this->prophesize(DomainEventCollectorInterface::class);
        $this->entityManager->getRepository(AbbreviationsSettings::class)->willReturn($this->repository->reveal());

        $viewHandler = $this->prophesize(ViewHandlerInterface::class);
        $viewHandler->handle(Argument::type(View::class))->will(static function (array $args): JsonResponse {
            return new JsonResponse($args[0]->getData());
        });

        $this->controller = new SettingsController(
            $this->entityManager->reveal(),
            $viewHandler->reveal(),
            $this->domainEventCollector->reveal()
        );
    }

    public function testGetReturnsDefaultsWithoutStoredSettings(): void
    {
        $this->repository->findOneBy([])->willReturn(null);

        $response = $this->controller->getAction();

        $this->assertSame(
            ['toggleHeader' => null, 'toggleHero' => null, 'toggleBreadcrumbs' => null, 'pageAbbreviations' => null],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testPutCreatesSettingsAndCollectsModifiedEvent(): void
    {
        $this->repository->findOneBy([])->willReturn(null);
        $this->entityManager->persist(Argument::type(AbbreviationsSettings::class))->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $payload = ['toggleHeader' => true, 'toggleHero' => false, 'toggleBreadcrumbs' => true, 'pageAbbreviations' => 'page-uuid'];

        $this->domainEventCollector->collect(Argument::that(
            static fn ($event): bool => $event instanceof ModifiedEvent && $event->getEventPayload() === $payload
        ))->shouldBeCalledOnce();

        $response = $this->controller->putAction($this->createJsonRequest($payload));

        $this->assertSame($payload, json_decode((string) $response->getContent(), true));
    }

    public function testPutWithPartialDataDoesNotFail(): void
    {
        $settings = new AbbreviationsSettings();
        $settings->setToggleHero(true);

        $this->repository->findOneBy([])->willReturn($settings);
        $this->entityManager->persist(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();
        $this->domainEventCollector->collect(Argument::type(ModifiedEvent::class))->shouldBeCalledOnce();

        $this->controller->putAction($this->createJsonRequest(['toggleHeader' => true]));

        $this->assertTrue($settings->getToggleHeader());
        $this->assertNull($settings->getToggleHero());
        $this->assertNull($settings->getPageAbbreviations());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createJsonRequest(array $data): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($data));
    }
}
