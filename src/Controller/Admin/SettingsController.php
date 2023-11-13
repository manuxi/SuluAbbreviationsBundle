<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use HandcraftedInTheAlps\RestRoutingBundle\Controller\Annotations\RouteResource;
use HandcraftedInTheAlps\RestRoutingBundle\Routing\ClassResourceInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;


/**
 * @RouteResource("abbreviations-settings")
 */
class SettingsController extends AbstractRestController implements ClassResourceInterface, SecuredControllerInterface
{
    private EntityManagerInterface $entityManager;
    private DomainEventCollectorInterface $domainEventCollector;

    public function __construct(
        EntityManagerInterface $entityManager,
        ViewHandlerInterface $viewHandler,
        DomainEventCollectorInterface $domainEventCollector,
        ?TokenStorageInterface $tokenStorage = null
    ) {
        $this->entityManager = $entityManager;
        $this->domainEventCollector = $domainEventCollector;

        parent::__construct($viewHandler, $tokenStorage);
    }

    public function getAction(): Response
    {
        $entity = $this->entityManager->getRepository(AbbreviationsSettings::class)->findOneBy([]);

        return $this->handleView($this->view($this->getDataForEntity($entity ?: new AbbreviationsSettings())));
    }

    public function putAction(Request $request): Response
    {
        $entity = $this->entityManager->getRepository(AbbreviationsSettings::class)->findOneBy([]);
        if (!$entity) {
            $entity = new AbbreviationsSettings();
            $this->entityManager->persist($entity);
        }

        $this->domainEventCollector->collect(
            new ModifiedEvent($entity, $request->request->all())
        );

        $data = $request->toArray();
        $this->mapDataToEntity($data, $entity);
        $this->entityManager->flush();

        return $this->handleView($this->view($this->getDataForEntity($entity)));
    }

    protected function getDataForEntity(AbbreviationsSettings $entity): array
    {
        return [
            'toggleHeader' => $entity->getToggleHeader(),
            'toggleHero' => $entity->getToggleHero(),
            'toggleBreadcrumbs' => $entity->getToggleBreadcrumbs(),
            'pageAbbreviations' => $entity->getPageAbbreviations(),
        ];
    }

    protected function mapDataToEntity(array $data, AbbreviationsSettings $entity): void
    {
        $entity->setToggleHeader($data['toggleHeader']);
        $entity->setToggleHero($data['toggleHero']);
        $entity->setToggleBreadcrumbs($data['toggleBreadcrumbs']);
        $entity->setPageAbbreviations($data['pageAbbreviations']);
    }

    public function getSecurityContext(): string
    {
        return AbbreviationsSettings::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return $request->query->get('locale');
    }
}