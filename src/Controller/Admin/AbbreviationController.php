<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
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
use Manuxi\SuluAbbreviationsBundle\Translation\AbbreviationTranslationRemover;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\TrashBundle\Application\TrashManager\TrashManagerInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\Exception\MissingParameterException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Security\Authorization\SecurityCondition;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/admin/api')]
class AbbreviationController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        ViewHandlerInterface $viewHandler,
        ?TokenStorageInterface $tokenStorage,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private readonly RestHelperInterface $restHelper,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentIndexerInterface $contentIndexer,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly TrashManagerInterface $trashManager,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly AbbreviationRouteRemover $routeRemover,
        private readonly AbbreviationTranslationRemover $translationRemover,
    ) {
        parent::__construct($viewHandler, $tokenStorage);
    }

    #[Route('/abbreviations', name: 'sulu_abbreviations.get_abbreviations', methods: ['GET'])]
    public function cgetAction(Request $request): Response
    {
        /** @var array<string, DoctrineFieldDescriptorInterface> $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(Abbreviation::LIST_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(Abbreviation::class);
        $listBuilder->addSelectField($fieldDescriptors['locale']);
        $listBuilder->addSelectField($fieldDescriptors['ghostLocale']);
        $listBuilder->setParameter('locale', $request->query->get('locale'));
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            Abbreviation::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count()
        );

        return $this->handleView($this->view($listRepresentation));
    }

    #[Route('/abbreviations/{id}', name: 'sulu_abbreviations.get_abbreviation', methods: ['GET'])]
    public function getAction(Request $request, string $id): Response
    {
        $abbreviation     = $this->findAbbreviation($id);
        $dimensionContent = $this->contentManager->resolve($abbreviation, $this->getDimensionAttributes($request));

        return $this->handleView($this->view($this->contentManager->normalize($dimensionContent)));
    }

    #[Route('/abbreviations', name: 'sulu_abbreviations.post_abbreviation', methods: ['POST'])]
    public function postAction(Request $request): Response
    {
        $abbreviation        = new Abbreviation();
        $dimensionAttributes = $this->getDimensionAttributes($request);
        $publish             = 'publish' === $request->query->get('action');

        if ($publish) {
            $this->checkLivePermission($dimensionAttributes['locale'] ?? null);
        }

        $dimensionContent = $this->contentManager->persist($abbreviation, $this->getData($request), $dimensionAttributes);

        $this->entityManager->persist($abbreviation);
        $this->entityManager->flush();

        $this->domainEventCollector->collect(new AbbreviationCreatedEvent(
            $abbreviation->getId(),
            $this->getTitle($dimensionContent),
            $dimensionAttributes['locale'] ?? null
        ));

        if ($publish) {
            $dimensionContent = $this->publish($abbreviation, $dimensionAttributes);
        }

        $this->contentIndexer->indexDimensionContent($dimensionContent);
        $this->entityManager->flush();

        return $this->handleView($this->view($this->contentManager->normalize($dimensionContent), 201));
    }

    #[Route('/abbreviations/{id}', name: 'sulu_abbreviations.post_abbreviation_trigger', methods: ['POST'])]
    public function postTriggerAction(Request $request, string $id): Response
    {
        $abbreviation        = $this->findAbbreviation($id);
        $dimensionAttributes = $this->getDimensionAttributes($request);
        $locale              = $dimensionAttributes['locale'] ?? null;
        $action              = $request->query->get('action');

        switch ($action) {
            case 'publish':
                $this->checkLivePermission($locale);
                $dimensionContent = $this->publish($abbreviation, $dimensionAttributes);
                $this->entityManager->flush();
                $this->contentIndexer->indexDimensionContent($dimensionContent);

                break;
            case 'unpublish':
                $this->checkLivePermission($locale);
                $dimensionContent = $this->contentManager->applyTransition(
                    $abbreviation,
                    $dimensionAttributes,
                    WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH
                );
                $this->entityManager->flush();
                $this->contentIndexer->deindex(
                    Abbreviation::RESOURCE_KEY,
                    $abbreviation->getId(),
                    array_merge($dimensionAttributes, ['stage' => DimensionContentInterface::STAGE_LIVE])
                );
                $this->contentIndexer->indexDimensionContent($dimensionContent);
                $this->domainEventCollector->collect(new AbbreviationUnpublishedEvent(
                    $abbreviation->getId(),
                    $this->getTitle($dimensionContent),
                    $locale
                ));
                $this->entityManager->flush();

                break;
            case 'remove-draft':
                $this->checkLivePermission($locale);
                $dimensionContent = $this->contentManager->applyTransition(
                    $abbreviation,
                    $dimensionAttributes,
                    WorkflowInterface::WORKFLOW_TRANSITION_REMOVE_DRAFT
                );
                $this->entityManager->flush();
                $this->contentIndexer->indexDimensionContent($dimensionContent);
                $this->domainEventCollector->collect(new AbbreviationDraftRemovedEvent(
                    $abbreviation->getId(),
                    $this->getTitle($dimensionContent),
                    $locale
                ));
                $this->entityManager->flush();

                break;
            case 'copy-locale':
                $dimensionContent = $this->copyLocale($request, $abbreviation, $dimensionAttributes);

                break;
            default:
                throw new BadRequestHttpException(\sprintf('Unknown action "%s".', \is_scalar($action) ? $action : \gettype($action)));
        }

        return $this->handleView($this->view($this->contentManager->normalize($dimensionContent)));
    }

    #[Route('/abbreviations/{id}', name: 'sulu_abbreviations.put_abbreviation', methods: ['PUT'])]
    public function putAction(Request $request, string $id): Response
    {
        $abbreviation        = $this->findAbbreviation($id);
        $dimensionAttributes = $this->getDimensionAttributes($request);
        $publish             = 'publish' === $request->query->get('action');

        if ($publish) {
            $this->checkLivePermission($dimensionAttributes['locale'] ?? null);
        }

        /** @var AbbreviationDimensionContent $dimensionContent */
        $dimensionContent = $this->contentManager->persist($abbreviation, $this->getData($request), $dimensionAttributes);

        if (WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === $dimensionContent->getWorkflowPlace()) {
            $dimensionContent = $this->contentManager->applyTransition(
                $abbreviation,
                $dimensionAttributes,
                WorkflowInterface::WORKFLOW_TRANSITION_CREATE_DRAFT
            );
        }

        $this->domainEventCollector->collect(new AbbreviationModifiedEvent(
            $abbreviation->getId(),
            $this->getTitle($dimensionContent),
            $dimensionAttributes['locale'] ?? null
        ));
        $this->entityManager->flush();

        if ($publish) {
            $dimensionContent = $this->publish($abbreviation, $dimensionAttributes);
            $this->entityManager->flush();
        }

        $this->contentIndexer->indexDimensionContent($dimensionContent);

        return $this->handleView($this->view($this->contentManager->normalize($dimensionContent)));
    }

    #[Route('/abbreviations/{id}', name: 'sulu_abbreviations.delete_abbreviation', methods: ['DELETE'])]
    public function deleteAction(Request $request, string $id): Response
    {
        $abbreviation = $this->findAbbreviation($id);
        $locale       = $this->getLocale($request);

        if ($request->query->getBoolean('deleteLocale')) {
            if (null === $locale) {
                throw new MissingParameterException(self::class, 'locale');
            }

            $this->securityChecker->checkPermission(
                new SecurityCondition(Abbreviation::SECURITY_CONTEXT, $locale),
                PermissionTypes::EDIT
            );

            if ($this->translationRemover->remove($abbreviation, $locale)) {
                $this->entityManager->flush();

                return new Response('', 204);
            }
        }

        $title = $this->resolveTitle($abbreviation, $locale);

        $this->trashManager->store(Abbreviation::RESOURCE_KEY, $abbreviation);

        $this->domainEventCollector->collect(new AbbreviationRemovedEvent($abbreviation->getId(), $title, $locale));

        $this->routeRemover->remove($abbreviation);
        $this->entityManager->remove($abbreviation);
        $this->entityManager->flush();

        $this->contentIndexer->deindex(Abbreviation::RESOURCE_KEY, $id);

        return new Response('', 204);
    }

    public function getSecurityContext(): string
    {
        return Abbreviation::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) && '' !== $locale ? $locale : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDimensionAttributes(Request $request): array
    {
        $locale = $this->getLocale($request);

        return null === $locale ? [] : ['locale' => $locale];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(Request $request): array
    {
        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $dimensionAttributes
     *
     * @return DimensionContentInterface<Abbreviation>
     */
    private function publish(Abbreviation $abbreviation, array $dimensionAttributes): DimensionContentInterface
    {
        $dimensionContent = $this->contentManager->applyTransition(
            $abbreviation,
            $dimensionAttributes,
            WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH
        );

        $this->contentIndexer->index($abbreviation, array_merge($dimensionAttributes, [
            'stage' => DimensionContentInterface::STAGE_LIVE,
        ]));

        $this->domainEventCollector->collect(new AbbreviationPublishedEvent(
            $abbreviation->getId(),
            $this->getTitle($dimensionContent),
            $dimensionAttributes['locale'] ?? null
        ));

        return $dimensionContent;
    }

    /**
     * @param array<string, mixed> $dimensionAttributes
     *
     * @return DimensionContentInterface<Abbreviation>
     */
    private function copyLocale(Request $request, Abbreviation $abbreviation, array $dimensionAttributes): DimensionContentInterface
    {
        $locale = $dimensionAttributes['locale'] ?? null;
        $source = $request->query->get('src')    ?? $locale;
        $dest   = $request->query->get('dest');

        if (!\is_string($source) || '' === $source) {
            throw new MissingParameterException(self::class, 'src');
        }

        if (!\is_string($dest) || '' === $dest) {
            throw new MissingParameterException(self::class, 'dest');
        }

        $destinations = array_values(array_filter(explode(',', $dest)));
        foreach ($destinations as $destination) {
            $this->securityChecker->checkPermission(
                new SecurityCondition(Abbreviation::SECURITY_CONTEXT, $destination),
                PermissionTypes::EDIT
            );
        }

        foreach ($destinations as $destination) {
            $copied = $this->contentManager->copy(
                $abbreviation,
                ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $source],
                $abbreviation,
                ['stage' => DimensionContentInterface::STAGE_DRAFT, 'locale' => $destination]
            );

            $this->contentIndexer->indexDimensionContent($copied);
            $this->domainEventCollector->collect(new AbbreviationCopiedLanguageEvent(
                $abbreviation->getId(),
                $this->getTitle($copied),
                $destination,
                ['sourceLocale' => $source, 'targetLocale' => $destination]
            ));
        }

        $this->entityManager->flush();

        return $this->contentManager->resolve($abbreviation, ['locale' => \is_string($locale) ? $locale : $source]);
    }

    private function findAbbreviation(string $id): Abbreviation
    {
        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->find($id);
        if (!$abbreviation instanceof Abbreviation) {
            throw new NotFoundHttpException(\sprintf('Abbreviation "%s" not found.', $id));
        }

        return $abbreviation;
    }

    private function checkLivePermission(?string $locale): void
    {
        $this->securityChecker->checkPermission(
            new SecurityCondition(Abbreviation::SECURITY_CONTEXT, $locale),
            PermissionTypes::LIVE
        );
    }

    /**
     * @param DimensionContentInterface<Abbreviation> $dimensionContent
     */
    private function getTitle(DimensionContentInterface $dimensionContent): ?string
    {
        return $dimensionContent instanceof AbbreviationDimensionContent ? $dimensionContent->getTitle() : null;
    }

    private function resolveTitle(Abbreviation $abbreviation, ?string $locale): string
    {
        foreach ($abbreviation->getDimensionContents() as $dimensionContent) {
            if (DimensionContentInterface::STAGE_DRAFT === $dimensionContent->getStage()
                && $dimensionContent->getLocale()      === $locale
                && null !== $dimensionContent->getTitle()) {
                return $dimensionContent->getTitle();
            }
        }

        return '';
    }
}
