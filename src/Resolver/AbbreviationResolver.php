<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStoreInterface;

class AbbreviationResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentManagerInterface $contentManager,
        private readonly ?ReferenceStoreInterface $referenceStore = null,
    ) {
    }

    /**
     * Returns the normalized live content of an abbreviation or null if it is not published in the given locale.
     *
     * @return array<string, mixed>|null
     */
    public function resolve(string $id, string $locale): ?array
    {
        if ('' === $id) {
            return null;
        }

        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->find($id);
        if (!$abbreviation instanceof Abbreviation) {
            return null;
        }

        try {
            $dimensionContent = $this->contentManager->resolve($abbreviation, [
                'locale' => $locale,
                'stage'  => DimensionContentInterface::STAGE_LIVE,
            ]);
        } catch (ContentNotFoundException) {
            return null;
        }

        if ($dimensionContent->getLocale() !== $locale || DimensionContentInterface::STAGE_LIVE !== $dimensionContent->getStage()) {
            return null;
        }

        $this->referenceStore?->add($abbreviation->getId());

        return $this->contentManager->normalize($dimensionContent);
    }

    /**
     * Resolves the given ids in their order and skips abbreviations which are not published.
     *
     * @param array<int, mixed> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    public function resolveMany(array $ids, string $locale): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (!\is_string($id)) {
                continue;
            }

            $resolved = $this->resolve($id, $locale);
            if (null !== $resolved) {
                $result[] = $resolved;
            }
        }

        return $result;
    }
}
