<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\SettingsController;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Support\ArrayViewHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Symfony\Component\HttpFoundation\Request;

class SettingsControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<ObjectRepository<AbbreviationsSettings>> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $eventCollector;

    private SettingsController $controller;

    protected function setUp(): void
    {
        $this->entityManager  = $this->prophesize(EntityManagerInterface::class);
        $this->repository     = $this->prophesize(ObjectRepository::class);
        $this->eventCollector = $this->prophesize(DomainEventCollectorInterface::class);

        $this->entityManager->getRepository(AbbreviationsSettings::class)->willReturn($this->repository->reveal());
        $this->entityManager->persist(Argument::any())->will(static function (): void {
        });
        $this->entityManager->flush()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::any())->will(static function (): void {
        });

        $this->controller = new SettingsController(
            $this->entityManager->reveal(),
            new ArrayViewHandler(),
            $this->eventCollector->reveal()
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

    public function testGetReturnsStoredSettings(): void
    {
        $settings = new AbbreviationsSettings();
        $settings->setToggleHeader(true);
        $settings->setPageAbbreviations('page');
        $this->repository->findOneBy([])->willReturn($settings);

        $data = json_decode((string) $this->controller->getAction()->getContent(), true);

        $this->assertTrue($data['toggleHeader']);
        $this->assertSame('page', $data['pageAbbreviations']);
    }

    public function testPutCreatesSettingsWhenMissing(): void
    {
        $this->repository->findOneBy([])->willReturn(null);

        $request  = new Request([], [], [], [], [], [], (string) json_encode(['toggleHeader' => true, 'toggleHero' => false, 'pageAbbreviations' => 'page']));
        $response = $this->controller->putAction($request);

        $this->entityManager->persist(Argument::type(AbbreviationsSettings::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->entityManager->flush()->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::type(ModifiedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });

        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['toggleHeader']);
        $this->assertFalse($data['toggleHero']);
        $this->assertNull($data['toggleBreadcrumbs']);
        $this->assertSame('page', $data['pageAbbreviations']);
    }

    public function testPutUpdatesExistingSettings(): void
    {
        $settings = new AbbreviationsSettings();
        $settings->setToggleBreadcrumbs(true);
        $this->repository->findOneBy([])->willReturn($settings);

        $request = new Request([], [], [], [], [], [], (string) json_encode(['toggleBreadcrumbs' => false]));
        $this->controller->putAction($request);

        $this->assertFalse($settings->getToggleBreadcrumbs());
        $this->entityManager->persist(Argument::any())->shouldNotHaveBeenCalled()->will(static function (): void {
        });
    }

    public function testSecurityContextAndLocale(): void
    {
        $this->assertSame(AbbreviationsSettings::SECURITY_CONTEXT, $this->controller->getSecurityContext());
        $this->assertSame('en', $this->controller->getLocale(new Request(['locale' => 'en'])));
        $this->assertNull($this->controller->getLocale(new Request()));
    }
}
