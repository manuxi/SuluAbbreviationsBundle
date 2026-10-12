<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationController;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCopiedLanguageEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCreatedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationDraftRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Route\AbbreviationRouteRemover;
use Manuxi\SuluAbbreviationsBundle\Tests\Unit\Support\ArrayViewHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AbbreviationControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FieldDescriptorFactoryInterface> */
    private ObjectProphecy $fieldDescriptorFactory;

    /** @var ObjectProphecy<DoctrineListBuilderFactoryInterface> */
    private ObjectProphecy $listBuilderFactory;

    /** @var ObjectProphecy<RestHelperInterface> */
    private ObjectProphecy $restHelper;

    /** @var ObjectProphecy<ContentManagerInterface> */
    private ObjectProphecy $contentManager;

    /** @var ObjectProphecy<ContentIndexerInterface> */
    private ObjectProphecy $contentIndexer;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<ObjectRepository<Abbreviation>> */
    private ObjectProphecy $repository;

    /** @var ObjectProphecy<DomainEventCollectorInterface> */
    private ObjectProphecy $eventCollector;

    /** @var ObjectProphecy<TrashManagerInterface> */
    private ObjectProphecy $trashManager;

    /** @var ObjectProphecy<SecurityCheckerInterface> */
    private ObjectProphecy $securityChecker;

    /** @var ObjectProphecy<AbbreviationRouteRemover> */
    private ObjectProphecy $routeRemover;

    private ArrayViewHandler $viewHandler;

    private AbbreviationController $controller;

    protected function setUp(): void
    {
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory     = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->restHelper             = $this->prophesize(RestHelperInterface::class);
        $this->contentManager         = $this->prophesize(ContentManagerInterface::class);
        $this->contentIndexer         = $this->prophesize(ContentIndexerInterface::class);
        $this->entityManager          = $this->prophesize(EntityManagerInterface::class);
        $this->repository             = $this->prophesize(ObjectRepository::class);
        $this->eventCollector         = $this->prophesize(DomainEventCollectorInterface::class);
        $this->trashManager           = $this->prophesize(TrashManagerInterface::class);
        $this->securityChecker        = $this->prophesize(SecurityCheckerInterface::class);
        $this->routeRemover           = $this->prophesize(AbbreviationRouteRemover::class);
        $this->viewHandler            = new ArrayViewHandler();

        $this->entityManager->getRepository(Abbreviation::class)->willReturn($this->repository->reveal());
        $this->entityManager->flush()->will(static function (): void {
        });
        $this->entityManager->persist(Argument::any())->will(static function (): void {
        });
        $this->entityManager->remove(Argument::any())->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent(Argument::any())->will(static function (): void {
        });
        $this->contentIndexer->deindex(Argument::cetera())->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::any())->will(static function (): void {
        });
        $this->trashManager->store(Argument::cetera())->willReturn($this->prophesize(TrashItemInterface::class)->reveal());
        $this->securityChecker->checkPermission(Argument::any(), PermissionTypes::EDIT)->will(static function (): void {
        });
        $this->routeRemover->remove(Argument::cetera())->will(static function (): void {
        });

        $this->controller = new AbbreviationController(
            $this->viewHandler,
            null,
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
            $this->restHelper->reveal(),
            $this->contentManager->reveal(),
            $this->contentIndexer->reveal(),
            $this->entityManager->reveal(),
            $this->eventCollector->reveal(),
            $this->trashManager->reveal(),
            $this->securityChecker->reveal(),
            $this->routeRemover->reveal()
        );
    }

    private function createContent(Abbreviation $abbreviation, string $name = 'HTML', string $stage = 'draft'): AbbreviationDimensionContent
    {
        $content = new AbbreviationDimensionContent($abbreviation);
        $content->setTemplateData(['name' => $name]);
        $content->setLocale('en');
        $content->setStage($stage);

        return $content;
    }

    private function expectLivePermissionCheck(string $locale = 'en'): void
    {
        $this->securityChecker
            ->checkPermission(Argument::that(static fn (SecurityCondition $condition): bool => Abbreviation::SECURITY_CONTEXT === $condition->getSecurityContext() && $locale === $condition->getLocale()), PermissionTypes::LIVE)
            ->shouldBeCalled();
    }

    public function testCget(): void
    {
        $locale = $this->prophesize(DoctrineFieldDescriptorInterface::class)->reveal();
        $ghost  = $this->prophesize(DoctrineFieldDescriptorInterface::class)->reveal();
        $this->fieldDescriptorFactory->getFieldDescriptors(Abbreviation::LIST_KEY)->willReturn(['locale' => $locale, 'ghostLocale' => $ghost]);

        $listBuilder = $this->prophesize(DoctrineListBuilder::class);
        $listBuilder->addSelectField($locale)->shouldBeCalled();
        $listBuilder->addSelectField($ghost)->shouldBeCalled();
        $listBuilder->setParameter('locale', 'de')->shouldBeCalled();
        $listBuilder->execute()->willReturn([['id' => 'a', 'name' => 'HTML']]);
        $listBuilder->getCurrentPage()->willReturn(1);
        $listBuilder->getLimit()->willReturn(10);
        $listBuilder->count()->willReturn(1);
        $this->listBuilderFactory->create(Abbreviation::class)->willReturn($listBuilder->reveal());
        $this->restHelper->initializeListBuilder($listBuilder->reveal(), Argument::type('array'))->shouldBeCalled();

        $response = $this->controller->cgetAction(new Request(['locale' => 'de']));

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->viewHandler->lastView?->getData();
        $this->assertInstanceOf(PaginatedRepresentation::class, $data);
        $this->assertSame(1, $data->getTotal());
        $this->assertSame(Abbreviation::RESOURCE_KEY, $data->getRel());
    }

    public function testGetResolvesAndNormalizesContent(): void
    {
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation);
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en'])->willReturn($content);
        $this->contentManager->normalize($content)->willReturn(['name' => 'HTML']);

        $response = $this->controller->getAction(new Request(['locale' => 'en']), $abbreviation->getId());

        $this->assertSame('{"name":"HTML"}', $response->getContent());
    }

    public function testGetThrowsNotFound(): void
    {
        $this->repository->find('missing')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->getAction(new Request(['locale' => 'en']), 'missing');
    }

    public function testPostCreatesDraft(): void
    {
        $content = $this->createContent(new Abbreviation());
        $this->contentManager->persist(Argument::type(Abbreviation::class), ['name' => 'HTML'], ['locale' => 'en'])->willReturn($content);
        $this->contentManager->normalize($content)->willReturn(['name' => 'HTML']);

        $response = $this->controller->postAction(new Request(['locale' => 'en'], ['name' => 'HTML']));

        $this->assertSame(201, $response->getStatusCode());
        $this->entityManager->persist(Argument::type(Abbreviation::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->entityManager->flush()->shouldHaveBeenCalled()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::type(AbbreviationCreatedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($content)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentManager->applyTransition(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    public function testPostWithPublishActionPublishesImmediately(): void
    {
        $this->expectLivePermissionCheck();
        $draft = $this->createContent(new Abbreviation());
        $live  = $this->createContent(new Abbreviation(), 'HTML', 'live');
        $this->contentManager->persist(Argument::type(Abbreviation::class), Argument::type('array'), ['locale' => 'en'])->willReturn($draft);
        $this->contentManager
            ->applyTransition(Argument::type(Abbreviation::class), ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)
            ->willReturn($live);
        $this->contentManager->normalize($live)->willReturn(['name' => 'HTML']);
        $this->contentIndexer->index(Argument::type(Abbreviation::class), ['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE])->willReturn($live);

        $response = $this->controller->postAction(new Request(['locale' => 'en', 'action' => 'publish'], ['name' => 'HTML']));

        $this->assertSame(201, $response->getStatusCode());
        $this->eventCollector->collect(Argument::type(AbbreviationPublishedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->index(Argument::type(Abbreviation::class), ['locale' => 'en', 'stage' => DimensionContentInterface::STAGE_LIVE])->shouldHaveBeenCalledOnce();
    }

    public function testPostWithPublishActionRequiresLivePermission(): void
    {
        $this->securityChecker->checkPermission(Argument::type(SecurityCondition::class), PermissionTypes::LIVE)->willThrow(new AccessDeniedException());

        $this->expectException(AccessDeniedException::class);

        try {
            $this->controller->postAction(new Request(['locale' => 'en', 'action' => 'publish'], ['name' => 'HTML']));
        } finally {
            $this->entityManager->persist(Argument::any())->shouldNotHaveBeenCalled();
        }
    }

    public function testPutUpdatesDraft(): void
    {
        $abbreviation = new Abbreviation();
        $content      = $this->createContent($abbreviation);
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->persist($abbreviation, ['name' => 'HTML'], ['locale' => 'en'])->willReturn($content);
        $this->contentManager->normalize($content)->willReturn(['name' => 'HTML']);

        $response = $this->controller->putAction(new Request(['locale' => 'en'], ['name' => 'HTML']), $abbreviation->getId());

        $this->assertSame(200, $response->getStatusCode());
        $this->eventCollector->collect(Argument::type(AbbreviationModifiedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentManager->applyTransition(Argument::cetera())->shouldNotHaveBeenCalled();
        $this->contentIndexer->indexDimensionContent($content)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testPutCreatesDraftOfPublishedContent(): void
    {
        $abbreviation = new Abbreviation();
        $published    = $this->createContent($abbreviation);
        $published->setWorkflowPlace(WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
        $draft = $this->createContent($abbreviation);
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->persist($abbreviation, [], ['locale' => 'en'])->willReturn($published);
        $this->contentManager
            ->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_CREATE_DRAFT)
            ->willReturn($draft)
            ->shouldBeCalledOnce();
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->controller->putAction(new Request(['locale' => 'en']), $abbreviation->getId());
    }

    public function testPutWithPublishAction(): void
    {
        $this->expectLivePermissionCheck();
        $abbreviation = new Abbreviation();
        $draft        = $this->createContent($abbreviation);
        $live         = $this->createContent($abbreviation, 'HTML', 'live');
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->persist($abbreviation, [], ['locale' => 'en'])->willReturn($draft);
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)->willReturn($live);
        $this->contentManager->normalize($live)->willReturn([]);
        $this->contentIndexer->index($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($live);

        $this->controller->putAction(new Request(['locale' => 'en', 'action' => 'publish']), $abbreviation->getId());

        $this->eventCollector->collect(Argument::type(AbbreviationPublishedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testTriggerPublish(): void
    {
        $this->expectLivePermissionCheck();
        $abbreviation = new Abbreviation();
        $live         = $this->createContent($abbreviation, 'HTML', 'live');
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH)->willReturn($live);
        $this->contentManager->normalize($live)->willReturn(['name' => 'HTML']);
        $this->contentIndexer->index($abbreviation, ['locale' => 'en', 'stage' => 'live'])->willReturn($live);

        $response = $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'publish']), $abbreviation->getId());

        $this->assertSame('{"name":"HTML"}', $response->getContent());
        $this->eventCollector->collect(Argument::type(AbbreviationPublishedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($live)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testTriggerUnpublishDeindexesLiveContent(): void
    {
        $this->expectLivePermissionCheck();
        $abbreviation = new Abbreviation();
        $draft        = $this->createContent($abbreviation);
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH)->willReturn($draft);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'unpublish']), $abbreviation->getId());

        $this->contentIndexer->deindex(Abbreviation::RESOURCE_KEY, $abbreviation->getId(), ['locale' => 'en', 'stage' => 'live'])->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($draft)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::type(AbbreviationUnpublishedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testTriggerRemoveDraft(): void
    {
        $this->expectLivePermissionCheck();
        $abbreviation = new Abbreviation();
        $draft        = $this->createContent($abbreviation);
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->applyTransition($abbreviation, ['locale' => 'en'], WorkflowInterface::WORKFLOW_TRANSITION_REMOVE_DRAFT)->willReturn($draft);
        $this->contentManager->normalize($draft)->willReturn([]);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'remove-draft']), $abbreviation->getId());

        $this->eventCollector->collect(Argument::type(AbbreviationDraftRemovedEvent::class))->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($draft)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testTriggerCopyLocaleCopiesDraftToEveryTargetLocale(): void
    {
        $abbreviation = new Abbreviation();
        $source       = $this->createContent($abbreviation);
        $copyDe       = $this->createContent($abbreviation, 'HTML');
        $copyFr       = $this->createContent($abbreviation, 'HTML (fr)');
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);
        $this->contentManager->copy($abbreviation, ['stage' => 'draft', 'locale' => 'en'], $abbreviation, ['stage' => 'draft', 'locale' => 'de'])->willReturn($copyDe);
        $this->contentManager->copy($abbreviation, ['stage' => 'draft', 'locale' => 'en'], $abbreviation, ['stage' => 'draft', 'locale' => 'fr'])->willReturn($copyFr);
        $this->contentManager->resolve($abbreviation, ['locale' => 'en'])->willReturn($source);
        $this->contentManager->normalize($source)->willReturn(['name' => 'HTML']);

        $response = $this->controller->postTriggerAction(
            new Request(['locale' => 'en', 'action' => 'copy-locale', 'src' => 'en', 'dest' => 'de,fr']),
            $abbreviation->getId()
        );

        $this->assertSame('{"name":"HTML"}', $response->getContent());
        $this->securityChecker->checkPermission(Argument::that(static fn (SecurityCondition $c): bool => 'de' === $c->getLocale()), PermissionTypes::EDIT)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->securityChecker->checkPermission(Argument::that(static fn (SecurityCondition $c): bool => 'fr' === $c->getLocale()), PermissionTypes::EDIT)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::type(AbbreviationCopiedLanguageEvent::class))->shouldHaveBeenCalledTimes(2)->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($copyDe)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->contentIndexer->indexDimensionContent($copyFr)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
    }

    public function testTriggerCopyLocaleRequiresDestination(): void
    {
        $abbreviation = new Abbreviation();
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);

        $this->expectException(\Sulu\Component\Rest\Exception\MissingParameterException::class);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'copy-locale']), $abbreviation->getId());
    }

    public function testTriggerRejectsUnknownAction(): void
    {
        $abbreviation = new Abbreviation();
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);

        $this->expectException(BadRequestHttpException::class);

        $this->controller->postTriggerAction(new Request(['locale' => 'en', 'action' => 'explode']), $abbreviation->getId());
    }

    public function testDeleteStoresTrashItemAndRemovesEverything(): void
    {
        $abbreviation = new Abbreviation();
        $abbreviation->addDimensionContent($this->createContent($abbreviation, 'HTML'));
        $this->repository->find($abbreviation->getId())->willReturn($abbreviation);

        $response = $this->controller->deleteAction(new Request(['locale' => 'en']), $abbreviation->getId());

        $this->assertSame(204, $response->getStatusCode());
        $this->trashManager->store(Abbreviation::RESOURCE_KEY, $abbreviation)->shouldHaveBeenCalledOnce();
        $this->routeRemover->remove($abbreviation)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->entityManager->remove($abbreviation)->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->entityManager->flush()->shouldHaveBeenCalled()->will(static function (): void {
        });
        $this->contentIndexer->deindex(Abbreviation::RESOURCE_KEY, $abbreviation->getId())->shouldHaveBeenCalledOnce()->will(static function (): void {
        });
        $this->eventCollector->collect(Argument::that(
            static fn ($event): bool => $event instanceof AbbreviationRemovedEvent && 'HTML' === $event->getResourceTitle()
        ))->shouldHaveBeenCalledOnce();
    }

    public function testDeleteThrowsNotFound(): void
    {
        $this->repository->find('missing')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->deleteAction(new Request(), 'missing');
    }

    public function testSecurityContextAndLocale(): void
    {
        $this->assertSame(Abbreviation::SECURITY_CONTEXT, $this->controller->getSecurityContext());
        $this->assertSame('de', $this->controller->getLocale(new Request(['locale' => 'de'])));
        $this->assertNull($this->controller->getLocale(new Request()));
        $this->assertNull($this->controller->getLocale(new Request(['locale' => ''])));
    }
}
