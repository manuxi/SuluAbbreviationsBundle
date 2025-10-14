<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Trash;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRestoredEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationSavedEvent;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\TrashBundle\Application\DoctrineRestoreHelper\DoctrineRestoreHelperInterface;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfiguration;
use Sulu\Bundle\TrashBundle\Application\RestoreConfigurationProvider\RestoreConfigurationProviderInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\RestoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Application\TrashItemHandler\StoreTrashItemHandlerInterface;
use Sulu\Bundle\TrashBundle\Domain\Model\TrashItemInterface;
use Sulu\Bundle\TrashBundle\Domain\Repository\TrashItemRepositoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AbbreviationsTrashItemHandler implements StoreTrashItemHandlerInterface, RestoreTrashItemHandlerInterface, RestoreConfigurationProviderInterface
{
    public function __construct(
        private readonly TrashItemRepositoryInterface   $trashItemRepository,
        private readonly EntityManagerInterface         $entityManager,
        private readonly DoctrineRestoreHelperInterface $doctrineRestoreHelper,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public static function getResourceKey(): string
    {
        return Abbreviation::RESOURCE_KEY;
    }

    public function store(object $entity, array $options = []): TrashItemInterface
    {
        /* @var Abbreviation $entity */

        $image = $entity->getImage();

        $data = [
            'name' => $entity->getName(),
            'explanation' => $entity->getExplanation(),
            'description' => $entity->getDescription(),
            'slug' => $entity->getRoutePath(),
            'published' => $entity->isPublished(),
            'publishedAt' => $entity->getPublishedAt(),
            'ext' => $entity->getExt(),
            'locale' => $entity->getLocale(),
            'imageId' => $image?->getId(),
            'link' => $entity->getLink(),
            'showAuthor' => $entity->getShowAuthor(),
            'showDate' => $entity->getShowDate(),
            'authored' => $entity->getAuthored(),
            'author' => $entity->getAuthor(),
        ];

        $restoreType = isset($options['locale']) ? 'translation' : null;

        $this->dispatcher->dispatch(new AbbreviationRemovedEvent($entity));

        return $this->trashItemRepository->create(
            Abbreviation::RESOURCE_KEY,
            (string) $entity->getId(),
            $entity->getName(),
            $data,
            $restoreType,
            $options,
            Abbreviation::SECURITY_CONTEXT,
            null,
            null
        );
    }

    public function restore(TrashItemInterface $trashItem, array $restoreFormData = []): object
    {
        $data = $trashItem->getRestoreData();

        $abbreviationId = (int) $trashItem->getResourceId();
        $abbreviation = new Abbreviation();
        $abbreviation->setLocale($data['locale']);
        $abbreviation->setName($data['name']);
        $abbreviation->setExplanation($data['explanation']);
        $abbreviation->setDescription($data['description']);
        $abbreviation->setRoutePath($data['slug']);
        $abbreviation->setExt($data['ext']);
        $abbreviation->setPublished($data['published']);
        $abbreviation->setPublishedAt($data['publishedAt'] ? new \DateTime($data['publishedAt']['date']) : null);
        $abbreviation->setShowAuthor($data['showAuthor']);
        $abbreviation->setShowDate($data['showDate']);

        $abbreviation->setAuthored($data['authored'] ? new \DateTime($data['authored']['date']) : new \DateTime());

        if ($data['author']) {
            $abbreviation->setAuthor($this->entityManager->find(ContactInterface::class, $data['author']));
        }

        if ($data['link']) {
            $abbreviation->setLink($data['link']);
        }

        if ($data['imageId']) {
            $abbreviation->setImage($this->entityManager->find(MediaInterface::class, $data['imageId']));
        }

        $this->domainEventCollector->collect(
            new AbbreviationRestoredEvent($abbreviation, $data)
        );

        $this->doctrineRestoreHelper->persistAndFlushWithId($abbreviation, $abbreviationId);
        $this->createRoute($this->entityManager, $abbreviationId, $data['locale'], $abbreviation->getRoutePath(), Abbreviation::class);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new AbbreviationSavedEvent($abbreviation));

        return $abbreviation;
    }

    private function createRoute(EntityManagerInterface $manager, int $id, string $locale, string $slug, string $class)
    {
        $route = new Route();
        $route->setPath($slug);
        $route->setLocale($locale);
        $route->setEntityClass($class);
        $route->setEntityId($id);
        $route->setHistory(0);
        $route->setCreated(new \DateTime());
        $route->setChanged(new \DateTime());
        $manager->persist($route);
    }

    public function getConfiguration(): RestoreConfiguration
    {
        return new RestoreConfiguration(
            null,
            AbbreviationsAdmin::EDIT_FORM_VIEW,
            ['id' => 'id']
        );
    }
}
