<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\View\ViewHandlerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\Settings\ModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationsSettings;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Component\Rest\AbstractRestController;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[Route('/admin/api')]
class SettingsController extends AbstractRestController implements SecuredControllerInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        ViewHandlerInterface $viewHandler,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        ?TokenStorageInterface $tokenStorage = null,
    ) {
        parent::__construct($viewHandler, $tokenStorage);
    }

    #[Route('/abbreviations-settings', name: 'sulu_abbreviations.get_abbreviations_settings', methods: ['GET'])]
    public function getAction(): Response
    {
        $entity = $this->entityManager->getRepository(AbbreviationsSettings::class)->findOneBy([]);

        return $this->handleView($this->view($this->getDataForEntity($entity ?: new AbbreviationsSettings())));
    }

    #[Route('/abbreviations-settings', name: 'sulu_abbreviations.put_abbreviations_settings', methods: ['PUT'])]
    public function putAction(Request $request): Response
    {
        $entity = $this->entityManager->getRepository(AbbreviationsSettings::class)->findOneBy([]);
        if (!$entity) {
            $entity = new AbbreviationsSettings();
            $this->entityManager->persist($entity);
        }

        $data = $request->toArray();
        $this->mapDataToEntity($data, $entity);

        $this->domainEventCollector->collect(new ModifiedEvent($entity, $data));
        $this->entityManager->flush();

        return $this->handleView($this->view($this->getDataForEntity($entity)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDataForEntity(AbbreviationsSettings $entity): array
    {
        return [
            'toggleHeader'      => $entity->getToggleHeader(),
            'toggleHero'        => $entity->getToggleHero(),
            'toggleBreadcrumbs' => $entity->getToggleBreadcrumbs(),
            'pageAbbreviations' => $entity->getPageAbbreviations(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function mapDataToEntity(array $data, AbbreviationsSettings $entity): void
    {
        $entity->setToggleHeader(isset($data['toggleHeader']) ? (bool) $data['toggleHeader'] : null);
        $entity->setToggleHero(isset($data['toggleHero']) ? (bool) $data['toggleHero'] : null);
        $entity->setToggleBreadcrumbs(isset($data['toggleBreadcrumbs']) ? (bool) $data['toggleBreadcrumbs'] : null);
        $entity->setPageAbbreviations(isset($data['pageAbbreviations']) ? (string) $data['pageAbbreviations'] : null);
    }

    public function getSecurityContext(): string
    {
        return AbbreviationsSettings::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        $locale = $request->query->get('locale');

        return \is_string($locale) ? $locale : null;
    }
}
