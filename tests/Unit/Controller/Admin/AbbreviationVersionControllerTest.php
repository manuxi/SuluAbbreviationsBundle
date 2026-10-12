<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationVersionController;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationVersionRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceUpdater;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationVersionRepository;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Support\ArrayViewHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Component\Rest\Exception\MissingParameterException;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\ListBuilder\ListRestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AbbreviationVersionControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ListRestHelperInterface> */
    private ObjectProphecy $listRestHelper;

    /** @var ObjectProphecy<AbbreviationVersionRepository> */
    private ObjectProphecy $versionRepository;

    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;

    /** @var ObjectProphecy<ContentIndexerInterface> */
    private ObjectProphecy $contentIndexer;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $eventCollector;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<AbbreviationReferenceUpdater> */
    private ObjectProphecy $referenceUpdater;

    private ArrayViewHandler $viewHandler;

    private AbbreviationVersionController $controller;

    private Abbreviation $abbreviation;

    protected function setUp(): void
    {
        $this->listRestHelper    = $this->prophesize(ListRestHelperInterface::class);
        $this->versionRepository = $this->prophesize(AbbreviationVersionRepository::class);
        $this->contentManager    = $this->prophesize(ContentManagerInterface::class);
        $this->contentIndexer    = $this->prophesize(ContentIndexerInterface::class);
        $this->entityManager     = $this->prophesize(EntityManagerInterface::class);
        $this->eventCollector    = $this->prophesize(DomainEventCollectorInterface::class);
        $this->securityChecker   = $this->prophesize(SecurityCheckerInterface::class);
        $this->referenceUpdater  = $this->prophesize(AbbreviationReferenceUpdater::class);
        $this->referenceUpdater->update(Argument::cetera())->will(static function (): void {
        });
        $this->viewHandler       = new ArrayViewHandler();
        $this->abbreviation      = new Abbreviation();

        $repository = $this->prophesize(ObjectRepository::class);
        $repository->find($this->abbreviation->getId())->willReturn($this->abbreviation);
        $repository->find('missing')->willReturn(null);
        $this->entityManager->getRepository(Abbreviation::class)->willReturn($repository->reveal());
        $this->entityManager->flush()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::any())->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent(Argument::any())->will(static function (): void {
        });
        $this->securityChecker->checkPermission(Argument::cetera())->will(static function (): void {
        });

        $this->controller = new AbbreviationVersionController(
            $this->viewHandler,
            null,
            $this->listRestHelper->reveal(),
            $this->versionRepository->reveal(),
            $this->contentManager->reveal(),
            $this->contentIndexer->reveal(),
            $this->entityManager->reveal(),
            $this->eventCollector->reveal(),
            $this->securityChecker->reveal(),
            $this->referenceUpdater->reveal()
        );
    }

    public function testListReturnsVersionsOfTheLocale(): void
    {
        $this->listRestHelper->getLimit()->willReturn(10);
        $this->listRestHelper->getOffset()->willReturn(0);
        $this->listRestHelper->getPage()->willReturn(1);
        $created = new \DateTimeImmutable('2026-01-01 10:00:00');
        $this->versionRepository->findByAbbreviation($this->abbreviation, 'en', 10, 0)->willReturn([
            new AbbreviationVersion($this->abbreviation, 'en', 1700000000, [], $created),
        ]);
        $this->versionRepository->countByAbbreviation($this->abbreviation, 'en')->willReturn(1);

        $response = $this->controller->cgetAction(new Request(['locale' => 'en']), $this->abbreviation->getId());

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->viewHandler->lastView?->getData();
        $this->assertInstanceOf(ListRepresentation::class, $data);
        $this->assertSame(1, $data->getTotal());
        $this->assertSame(
            [['id' => 1700000000, 'locale' => 'en', 'author' => '', 'authored' => $created]],
            $data->getData()
        );
    }

    public function testListRequiresLocale(): void
    {
        $this->expectException(MissingParameterException::class);

        $this->controller->cgetAction(new Request(), $this->abbreviation->getId());
    }

    public function testListThrowsNotFoundForUnknownAbbreviation(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller->cgetAction(new Request(['locale' => 'en']), 'missing');
    }

    public function testRestoreWritesVersionDataIntoTheDraft(): void
    {
        $version = new AbbreviationVersion($this->abbreviation, 'en', 123, [
            'id'            => $this->abbreviation->getId(),
            'stage'         => 'live',
            'locale'        => 'en',
            'workflowPlace' => 'published',
            'published'     => '2026-01-01',
            'title'         => 'HTML',
            'template'      => 'abbreviation_default',
            'name'          => 'HTML',
            'url'           => '/abbreviations/html',
        ]);
        $this->versionRepository->findOneByVersion($this->abbreviation, 'en', 123)->willReturn($version);

        $published = new AbbreviationDimensionContent($this->abbreviation);
        $published->setWorkflowPlace(WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
        $draft = new AbbreviationDimensionContent($this->abbreviation);
        $draft->setTemplateData(['name' => 'HTML']);

        $this->contentManager->persist(
            $this->abbreviation,
            ['template' => 'abbreviation_default', 'name' => 'HTML', 'url' => '/abbreviations/html'],
            ['locale' => 'en']
        )->willReturn($published);
        $this->contentManager
            ->applyTransition($this->abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_CREATE_DRAFT)
            ->willReturn($draft)
            ->shouldBeCalledOnce();
        $this->contentManager->normalize($draft)->willReturn(['name' => 'HTML']);

        $response = $this->controller->postTriggerAction(new Request(['action' => 'restore', 'locale' => 'en']), $this->abbreviation->getId(), 123);

        $this->assertSame('{"name":"HTML"}', $response->getContent());
        $this->securityChecker
            ->checkPermission(Argument::that(static fn (SecurityCondition $c): bool => 'en' === $c->getLocale()), PermissionTypes::EDIT)
            ->shouldHaveBeenCalled();
        $this->eventCollector->collect(Argument::that(
            static fn ($event): bool => $event instanceof AbbreviationVersionRestoredEvent && ['version' => 123] === $event->getEventPayload()
        ))->shouldHaveBeenCalledOnce();
        $this->contentIndexer->indexDimensionContent($draft)->shouldHaveBeenCalledOnce();
        $this->referenceUpdater->update($draft)->shouldHaveBeenCalledOnce();
    }

    public function testRestoreOfUnpublishedContentKeepsTheDraft(): void
    {
        $version = new AbbreviationVersion($this->abbreviation, 'en', 5, ['name' => 'HTML']);
        $this->versionRepository->findOneByVersion($this->abbreviation, 'en', 5)->willReturn($version);
        $draft = new AbbreviationDimensionContent($this->abbreviation);
        $this->contentManager->persist($this->abbreviation, ['name' => 'HTML'], ['locale' => 'en'])->willReturn($draft);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->controller->postTriggerAction(new Request(['action' => 'restore', 'locale' => 'en']), $this->abbreviation->getId(), 5);

        $this->contentManager->applyTransition(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testRestoreThrowsNotFoundForUnknownVersion(): void
    {
        $this->versionRepository->findOneByVersion($this->abbreviation, 'en', 9)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->postTriggerAction(new Request(['action' => 'restore', 'locale' => 'en']), $this->abbreviation->getId(), 9);
    }

    public function testRestoreRequiresAction(): void
    {
        $this->expectException(MissingParameterException::class);

        $this->controller->postTriggerAction(new Request(['locale' => 'en']), $this->abbreviation->getId(), 1);
    }

    public function testRestoreRejectsUnknownAction(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->controller->postTriggerAction(new Request(['action' => 'purge', 'locale' => 'en']), $this->abbreviation->getId(), 1);
    }

    public function testRestoreRequiresEditPermission(): void
    {
        $this->securityChecker->checkPermission(Argument::cetera())->willThrow(new AccessDeniedException());

        $this->expectException(AccessDeniedException::class);

        try {
            $this->controller->postTriggerAction(new Request(['action' => 'restore', 'locale' => 'en']), $this->abbreviation->getId(), 1);
        } finally {
            $this->contentManager->persist(Argument::cetera())->shouldNotHaveBeenCalled();
        }
    }

    public function testSecurityContextAndLocale(): void
    {
        $this->assertSame(Abbreviation::SECURITY_CONTEXT, $this->controller->getSecurityContext());
        $this->assertSame('de', $this->controller->getLocale(new Request(['locale' => 'de'])));
        $this->assertNull($this->controller->getLocale(new Request()));
    }
}
