<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Models;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCopiedLanguageEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationCreatedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationModifiedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Domain\Event\AbbreviationUnpublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AbbreviationModelInterface;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationRepository;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationPublishedEvent as SearchPublishedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationRemovedEvent as SearchRemovedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationSavedEvent as SearchSavedEvent;
use Manuxi\SuluAbbreviationsBundle\Search\Event\AbbreviationUnpublishedEvent as SearchUnpublishedEvent;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\ArrayPropertyTrait;
use Sulu\Bundle\ActivityBundle\Application\Collector\DomainEventCollectorInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\RouteBundle\Manager\RouteManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AbbreviationModel implements AbbreviationModelInterface
{
    use ArrayPropertyTrait;

    public function __construct(
        private readonly AbbreviationRepository $abbreviationRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly ContactRepository $contactRepository,
        private readonly RouteManagerInterface $routeManager,
        private readonly RouteRepositoryInterface $routeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainEventCollectorInterface $domainEventCollector,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     */
    public function get(int $id, ?Request $request = null): Abbreviation
    {
        if (null === $request) {
            return $this->findById($id);
        }

        return $this->findByIdAndLocale($id, $request);
    }

    public function delete(Abbreviation $entity): void
    {
        $this->domainEventCollector->collect(new AbbreviationRemovedEvent($entity->getId(), $entity->getTitle() ?? ''));
        $this->dispatcher->dispatch(new SearchRemovedEvent($entity));
        $this->removeRoutesForEntity($entity);
        $this->abbreviationRepository->remove($entity->getId());
    }

    /**
     * @throws EntityNotFoundException
     */
    public function create(Request $request): Abbreviation
    {
        $entity = $this->abbreviationRepository->create((string) $this->getLocaleFromRequest($request));
        $entity = $this->mapDataToEntity($entity, $request->request->all());

        $this->domainEventCollector->collect(
            new AbbreviationCreatedEvent($entity, $request->request->all())
        );

        // need the id for updateRoutesForEntity(), so we have to persist and flush here
        $entity = $this->abbreviationRepository->save($entity);

        $this->updateRoutesForEntity($entity);

        // explicit flush to save routes persisted by updateRoutesForEntity()
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new SearchSavedEvent($entity));

        return $entity;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function update(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $this->dispatcher->dispatch(new SearchUnpublishedEvent($entity));

        $entity = $this->mapDataToEntity($entity, $request->request->all());
        $entity = $this->mapSettingsToEntity($entity, $request->request->all());

        $this->domainEventCollector->collect(
            new AbbreviationModifiedEvent($entity, $request->request->all())
        );

        $entity = $this->abbreviationRepository->save($entity);

        $this->updateRoutesForEntity($entity);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new SearchSavedEvent($entity));

        return $entity;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function publish(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $this->dispatcher->dispatch(new SearchUnpublishedEvent($entity));

        $entity->setPublished(true);
        $entity = $this->abbreviationRepository->save($entity);

        $this->domainEventCollector->collect(
            new AbbreviationPublishedEvent($entity, $request->request->all())
        );

        $this->dispatcher->dispatch(new SearchPublishedEvent($entity));

        return $entity;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function unpublish(int $id, Request $request): Abbreviation
    {
        $entity = $this->findByIdAndLocale($id, $request);
        $this->dispatcher->dispatch(new SearchUnpublishedEvent($entity));

        $entity->setPublished(false);
        $entity = $this->abbreviationRepository->save($entity);

        $this->domainEventCollector->collect(
            new AbbreviationUnpublishedEvent($entity, $request->request->all())
        );

        $this->dispatcher->dispatch(new SearchPublishedEvent($entity));

        return $entity;
    }

    public function copy(int $id, Request $request): Abbreviation
    {
        $locale = $this->getLocaleFromRequest($request);

        $entity = $this->findById($id);
        $entity->setLocale($locale);

        $copy = $this->abbreviationRepository->create($locale);

        $copy = $entity->copy($copy);
        $copy = $this->abbreviationRepository->save($copy);
        $this->dispatcher->dispatch(new SearchSavedEvent($copy));

        return $copy;
    }

    public function copyLanguage(int $id, Request $request, string $srcLocale, array $destLocales): Abbreviation
    {
        $entity = $this->findById($id);
        $entity->setLocale($srcLocale);

        foreach ($destLocales as $destLocale) {
            $entity = $entity->copyToLocale($destLocale);
        }

        // @todo: test with more than one different locale
        $entity->setLocale($this->getLocaleFromRequest($request));

        $this->domainEventCollector->collect(
            new AbbreviationCopiedLanguageEvent($entity, $request->request->all())
        );

        $entity = $this->abbreviationRepository->save($entity);
        $this->dispatcher->dispatch(new SearchSavedEvent($entity));

        return $entity;
    }

    /**
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
     * @throws EntityNotFoundException
     */
    private function mapSettingsToEntity(Abbreviation $entity, array $data): Abbreviation
    {
        // settings (author, authored) changeable
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
            $entity->setAuthored(new \DateTime($authored));
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
