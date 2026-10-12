<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationVersionRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationVersionRepository;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\ContentBundle\Content\Application\ContentIndexer\ContentIndexerInterface;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Rest\Exception\MissingParameterException;
use Sulu\Component\Rest\ListBuilder\ListRepresentation;
use Sulu\Component\Rest\ListBuilder\ListRestHelperInterface;
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
class AbbreviationVersionController extends AbstractRestController implements SecuredControllerInterface
{
    public const RESOURCE_KEY = 'abbreviation_versions';

    /**
     * Keys of a normalized dimension content which describe the state of the content and are not restored.
     */
    private const STATE_KEYS = [
        'id',
        'stage',
        'locale',
        'ghostLocale',
        'availableLocales',
        'workflowPlace',
        'published',
        'publishedState',
        'title',
    ];

    public function __construct(
        ViewHandlerInterface $viewHandler,
        ?TokenStorageInterface $tokenStorage,
        private readonly ListRestHelperInterface $listRestHelper,
        private readonly AbbreviationVersionRepository $versionRepository,
        private readonly ContentManagerInterface $contentManager,
        private readonly ContentIndexerInterface $contentIndexer,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
        parent::__construct($viewHandler, $tokenStorage);
    }

    #[Route('/abbreviations/{id}/versions', name: 'sulu_abbreviations.get_abbreviation_versions', methods: ['GET'])]
    public function cgetAction(Request $request, string $id): Response
    {
        $locale       = $this->requireLocale($request);
        $abbreviation = $this->findAbbreviation($id);

        $limit    = $this->listRestHelper->getLimit();
        $versions = $this->versionRepository->findByAbbreviation(
            $abbreviation,
            $locale,
            $limit,
            $this->listRestHelper->getOffset()
        );

        $items = array_map(static function (AbbreviationVersion $version): array {
            $contact = $version->getCreator()?->getContact();

            return [
                'id'       => $version->getVersion(),
                'locale'   => $version->getLocale(),
                'author'   => $contact instanceof Contact ? $contact->getFullName() : '',
                'authored' => $version->getCreated(),
            ];
        }, $versions);

        return $this->handleView($this->view(new ListRepresentation(
            $items,
            self::RESOURCE_KEY,
            'sulu_abbreviations.get_abbreviation_versions',
            ['id' => $id, 'locale' => $locale],
            $this->listRestHelper->getPage(),
            $limit,
            $this->versionRepository->countByAbbreviation($abbreviation, $locale)
        )));
    }

    #[Route('/abbreviations/{id}/versions/{version}', name: 'sulu_abbreviations.post_abbreviation_version_trigger', methods: ['POST'], requirements: ['version' => '\d+'])]
    public function postTriggerAction(Request $request, string $id, int $version): Response
    {
        $action = $request->query->get('action');
        if (!\is_string($action) || '' === $action) {
            throw new MissingParameterException(self::class, 'action');
        }

        if ('restore' !== $action) {
            throw new BadRequestHttpException(\sprintf('Unknown action "%s".', $action));
        }

        $locale = $this->requireLocale($request);
        $this->securityChecker->checkPermission(
            new SecurityCondition(Abbreviation::SECURITY_CONTEXT, $locale),
            PermissionTypes::EDIT
        );

        $abbreviation = $this->findAbbreviation($id);
        $stored       = $this->versionRepository->findOneByVersion($abbreviation, $locale, $version);
        if (null === $stored) {
            throw new NotFoundHttpException(\sprintf('Version "%d" of abbreviation "%s" not found.', $version, $id));
        }

        $data = array_diff_key($stored->getData(), array_flip(self::STATE_KEYS));

        /** @var AbbreviationDimensionContent $dimensionContent */
        $dimensionContent = $this->contentManager->persist($abbreviation, $data, ['locale' => $locale]);

        if (WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === $dimensionContent->getWorkflowPlace()) {
            $dimensionContent = $this->contentManager->applyTransition(
                $abbreviation,
                ['locale' => $locale],
                WorkflowInterface::WORKFLOW_TRANSITION_CREATE_DRAFT
            );
        }

        $this->domainEventCollector->collect(new AbbreviationVersionRestoredEvent(
            $abbreviation->getId(),
            $dimensionContent->getTitle(),
            $locale,
            ['version' => $version]
        ));
        $this->entityManager->flush();

        $this->contentIndexer->indexDimensionContent($dimensionContent);

        return $this->handleView($this->view($this->contentManager->normalize($dimensionContent)));
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

    private function requireLocale(Request $request): string
    {
        $locale = $this->getLocale($request);
        if (null === $locale) {
            throw new MissingParameterException(self::class, 'locale');
        }

        return $locale;
    }

    private function findAbbreviation(string $id): Abbreviation
    {
        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->find($id);
        if (!$abbreviation instanceof Abbreviation) {
            throw new NotFoundHttpException(\sprintf('Abbreviation "%s" not found.', $id));
        }

        return $abbreviation;
    }
}
