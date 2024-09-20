<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Models;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCopiedLanguageEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCreatedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AbbreviationModelInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ArrayPropertyTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class AbbreviationModel implements AbbreviationModelInterface
{
    use ArrayPropertyTrait;

    public function __construct(
        private AbbreviationRepository $abbreviationRepository,
        private MediaRepositoryInterface $mediaRepository,
        private ContactRepository $contactRepository,
        private RouteManagerInterface $routeManager,
        private RouteRepositoryInterface $routeRepository,
        private EntityManagerInterface $entityManager,
        private DomainEventCollectorInterface $domainEventCollector
    ) {}

    /**
     * @param int $id
     * @param Request|null $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    public function get(int $id, Request $request = null): Abbreviation
    {
        if(null === $request) {
            return $this->findById($id);
        }
        return $this->findByIdAndLocale($id, $request);
    }

    public function delete(Abbreviation $entity): void
    {
        $this->domainEventCollector->collect(
            new AbbreviationRemovedEvent($entity->getId(), $entity->getTitle() ?? '')
        );
        $this->removeRoutesForEntity($entity);
        $this->abbreviationRepository->remove($entity->getId());
    }

    /**
     * @param Request $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    public function create(Request $request): Abbreviation
    {
        $entity = $this->abbreviationRepository->create((string) $this->getLocaleFromRequest($request));
        $entity = $this->mapDataToEntity($entity, $request->request->all());

        $this->domainEventCollector->collect(
            new AbbreviationCreatedEvent($entity, $request->request->all())
        );

        //need the id for updateRoutesForEntity(), so we have to persist and flush here
        $entity = $this->abbreviationRepository->save($entity);

        $this->updateRoutesForEntity($entity);

        //explicit flush to save routes persisted by updateRoutesForEntity()
        $this->entityManager->flush();

        return $entity;
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    public function update(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $entity = $this->mapDataToEntity($entity, $request->request->all());
        $entity = $this->mapSettingsToEntity($entity, $request->request->all());
        $this->updateRoutesForEntity($entity);

        $this->domainEventCollector->collect(
            new AbbreviationModifiedEvent($entity, $request->request->all())
        );

        return $this->abbreviationRepository->save($entity);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    public function publish(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $entity->setPublished(true);

        $this->domainEventCollector->collect(
            new AbbreviationPublishedEvent($entity, $request->request->all())
        );

        return $this->abbreviationRepository->save($entity);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    public function unpublish(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $entity->setPublished(false);

        $this->domainEventCollector->collect(
            new AbbreviationUnpublishedEvent($entity, $request->request->all())
        );

        return $this->abbreviationRepository->save($entity);
    }

    public function copy(int $id, Request $request): Abbreviation
    {
        $entity = $this->findById($id);
        $copy = $entity->copy();

        return $this->abbreviationRepository->save($copy);
    }

    public function copyLanguage(int $id, Request $request, string $srcLocale, array $destLocales): Abbreviation
    {
        $entity = $this->findById($id);
        $entity->setLocale($srcLocale);

        foreach($destLocales as $destLocale) {
            $entity = $entity->copyToLocale($destLocale);
        }

        //@todo: test with more than one different locale
        $entity->setLocale($this->getLocaleFromRequest($request));

        $this->domainEventCollector->collect(
            new AbbreviationCopiedLanguageEvent($entity, $request->request->all())
        );

        return $this->abbreviationRepository->save($entity);
    }

    /**
     * @param int $id
     * @param Request $request
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    private function findByIdAndLocale(int $id, Request $request): Abbreviation
    {
        $entity = $this->abbreviationRepository->findById($id, (string) $this->getLocaleFromRequest($request));
        if (!$entity) {
            throw new EntityNotFoundException($this->abbreviationRepository->getClassName(), $id);
        }
        return $entity;
    }

    /**
     * @param int $id
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    private function findById(int $id): Abbreviation
    {
        $entity = $this->abbreviationRepository->find($id);
        if (!$entity) {
            throw new EntityNotFoundException($this->abbreviationRepository->getClassName(), $id);
        }
        return $entity;
    }

    private function getLocaleFromRequest(Request $request)
    {
        return $request->query->get('locale');
    }

    /**
     * @param Abbreviation $entity
     * @param array $data
     * @return Abbreviation
     * @throws \Exception
     */
    private function mapDataToEntity(Abbreviation $entity, array $data): Abbreviation
    {
        $name = $this->getProperty($data, 'name');
        if ($name) {
            $entity->setName($name);
        }

        $published = $this->getProperty($data, 'published');
        if ($published) {
            $entity->setPublished($published);
        }

        $routePath = $this->getProperty($data, 'routePath');
        if ($routePath) {
            $entity->setRoutePath($routePath);
        }

        $showAuthor = $this->getProperty($data, 'showAuthor');
        $entity->setShowAuthor($showAuthor ? true : false);

        $showDate = $this->getProperty($data, 'showDate');
        $entity->setShowDate($showDate ? true : false);

        $link = $this->getProperty($data, 'link');
        $entity->setLink($link ?: null);

        $explanation = $this->getProperty($data, 'explanation');
        $entity->setExplanation($explanation ?: null);

        $description = $this->getProperty($data, 'description');
        $entity->setDescription($description ?: null);

        $imageId = $this->getPropertyMulti($data, ['image', 'id']);
        if ($imageId) {
            $image = $this->mediaRepository->findMediaById((int) $imageId);
            if (!$image) {
                throw new EntityNotFoundException($this->mediaRepository->getClassName(), $imageId);
            }
            $entity->setImage($image);
        } else {
            $entity->setImage(null);
        }

        return $entity;
    }

    /**
     * @param Abbreviation $entity
     * @param array $data
     * @return Abbreviation
     * @throws EntityNotFoundException
     */
    private function mapSettingsToEntity(Abbreviation $entity, array $data): Abbreviation
    {
        //settings (author, authored) changeable
        $authorId = $this->getProperty($data, 'author');
        if ($authorId) {
            $author = $this->contactRepository->findById($authorId);
            if (!$author) {
                throw new EntityNotFoundException($this->contactRepository->getClassName(), $authorId);
            }
            $entity->setAuthor($author);
        } else {
            $entity->setAuthor(null);
        }

        $authored = $this->getProperty($data, 'authored');
        if ($authored) {
            $entity->setAuthored(new DateTime($authored));
        } else {
            $entity->setAuthored(null);
        }
        return $entity;
    }

    private function updateRoutesForEntity(Abbreviation $entity): void
    {
        $this->routeManager->createOrUpdateByAttributes(
            Abbreviation::class,
            (string) $entity->getId(),
            $entity->getLocale(),
            $entity->getRoutePath()
        );
    }

    private function removeRoutesForEntity(Abbreviation $entity): void
    {
        $routes = $this->routeRepository->findAllByEntity(
            Abbreviation::class,
            (string) $entity->getId(),
            $entity->getLocale()
        );

        foreach ($routes as $route) {
            $this->routeRepository->remove($route);
        }
    }
}
