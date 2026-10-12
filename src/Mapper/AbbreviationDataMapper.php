<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Mapper;

use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluSharedToolsBundle\Entity\Traits\ArrayPropertyTrait;
use Sulu\Bundle\ContactBundle\Entity\ContactRepository;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;

/**
 * Maps admin form data onto an abbreviation in its current locale. Used by the admin model and the preview.
 */
class AbbreviationDataMapper
{
    use ArrayPropertyTrait;

    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly ContactRepository $contactRepository,
    ) {
    }

    /**
     * Maps the fields of the details form.
     *
     * @param array<string, mixed> $data
     *
     * @throws EntityNotFoundException
     */
    public function mapData(Abbreviation $entity, array $data): Abbreviation
    {
        $name = $this->getProperty($data, 'name');
        if ($name) {
            $entity->setName($name);
        }

        $published = $this->getProperty($data, 'published');
        if ($published) {
            $entity->setPublished((bool) $published);
        }

        $routePath = $this->getProperty($data, 'routePath');
        if ($routePath) {
            $entity->setRoutePath($routePath);
        }

        $entity->setShowAuthor((bool) $this->getProperty($data, 'showAuthor'));
        $entity->setShowDate((bool) $this->getProperty($data, 'showDate'));

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
                throw new EntityNotFoundException(MediaRepositoryInterface::class, $imageId);
            }
            $entity->setImage($image);
        } else {
            $entity->setImage(null);
        }

        return $entity;
    }

    /**
     * Maps the fields of the settings form (author, authored).
     *
     * @param array<string, mixed> $data
     *
     * @throws EntityNotFoundException
     */
    public function mapSettings(Abbreviation $entity, array $data): Abbreviation
    {
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
        $entity->setAuthored($authored ? new \DateTime($authored) : null);

        return $entity;
    }
}
