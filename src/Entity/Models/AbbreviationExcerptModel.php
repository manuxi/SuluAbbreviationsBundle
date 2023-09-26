<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Entity\Models;

use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;
use Manuxi\SuluAbbreviationsBundle\Entity\Interfaces\AbbreviationExcerptModelInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Traits\ArrayPropertyTrait;
use Manuxi\SuluAbbreviationsBundle\Repository\AbbreviationExcerptRepository;
use Sulu\Bundle\CategoryBundle\Category\CategoryManagerInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagManagerInterface;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Request;

class AbbreviationExcerptModel implements AbbreviationExcerptModelInterface
{
    use ArrayPropertyTrait;

    private AbbreviationExcerptRepository $abbreviationExcerptRepository;
    private CategoryManagerInterface $categoryManager;
    private TagManagerInterface $tagManager;
    private MediaRepositoryInterface $mediaRepository;

    public function __construct(
        AbbreviationExcerptRepository $abbreviationExcerptRepository,
        CategoryManagerInterface $categoryManager,
        TagManagerInterface $tagManager,
        MediaRepositoryInterface $mediaRepository
    ) {
        $this->abbreviationExcerptRepository = $abbreviationExcerptRepository;
        $this->categoryManager = $categoryManager;
        $this->tagManager = $tagManager;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function updateAbbreviationExcerpt(AbbreviationExcerpt $abbreviationExcerpt, Request $request): AbbreviationExcerpt
    {
        $abbreviationExcerpt = $this->mapDataToAbbreviationExcerpt($abbreviationExcerpt, $request->request->all()['ext']['excerpt']);
        return $this->abbreviationExcerptRepository->save($abbreviationExcerpt);
    }

    /**
     * @throws EntityNotFoundException
     */
    private function mapDataToAbbreviationExcerpt(AbbreviationExcerpt $abbreviationExcerpt, array $data): AbbreviationExcerpt
    {
        $locale = $this->getProperty($data, 'locale');
        if ($locale) {
            $abbreviationExcerpt->setLocale($locale);
        }

        $title = $this->getProperty($data, 'title');
        if ($title) {
            $abbreviationExcerpt->setTitle($title);
        }

        $more = $this->getProperty($data, 'more');
        if ($more) {
            $abbreviationExcerpt->setMore($more);
        }

        $description = $this->getProperty($data, 'description');
        if ($description) {
            $abbreviationExcerpt->setDescription($description);
        }

        $categoryIds = $this->getProperty($data, 'categories');
        if ($categoryIds && is_array($categoryIds)) {
            $abbreviationExcerpt->removeCategories();
            $categories = $this->categoryManager->findByIds($categoryIds);
            foreach($categories as $category) {
                $abbreviationExcerpt->addCategory($category);
            }
        }

        $tags = $this->getProperty($data, 'tags');
        if ($tags && is_array($tags)) {
            $abbreviationExcerpt->removeTags();
            foreach($tags as $tagName) {
                $abbreviationExcerpt->addTag($this->tagManager->findOrCreateByName($tagName));
            }
        }

        $iconIds = $this->getPropertyMulti($data, ['icon', 'ids']);
        if ($iconIds && is_array($iconIds)) {
            $abbreviationExcerpt->removeIcons();
            foreach($iconIds as $iconId) {
                $icon = $this->mediaRepository->findMediaById((int)$iconId);
                if (!$icon) {
                    throw new EntityNotFoundException($this->mediaRepository->getClassName(), $iconId);
                }
                $abbreviationExcerpt->addIcon($icon);
            }
        }

        $imageIds = $this->getPropertyMulti($data, ['images', 'ids']);
        if ($imageIds && is_array($imageIds)) {
            $abbreviationExcerpt->removeImages();
            foreach($imageIds as $imageId) {
                $image = $this->mediaRepository->findMediaById((int)$imageId);
                if (!$image) {
                    throw new EntityNotFoundException($this->mediaRepository->getClassName(), $imageId);
                }
                $abbreviationExcerpt->addImage($image);
            }
        }

        return $abbreviationExcerpt;
    }
}
