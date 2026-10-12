<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;

class AbbreviationRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function findById(string $id): ?Abbreviation
    {
        $abbreviation = $this->entityManager->getRepository(Abbreviation::class)->find($id);

        return $abbreviation instanceof Abbreviation ? $abbreviation : null;
    }

    /**
     * Returns the ids of all abbreviations which are published in the given locale, ordered by name.
     *
     * @return string[]
     */
    public function findPublishedIds(string $locale, ?int $limit = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('abbreviation.id AS id')
            ->from(Abbreviation::class, 'abbreviation')
            ->innerJoin(AbbreviationDimensionContent::class, 'dimensionContent', 'WITH', 'dimensionContent.abbreviation = abbreviation')
            ->where('dimensionContent.stage = :stage')
            ->andWhere('dimensionContent.locale = :locale')
            ->setParameter('stage', DimensionContentInterface::STAGE_LIVE)
            ->setParameter('locale', $locale)
            ->orderBy('dimensionContent.name', 'ASC');

        if (null !== $limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var array<int, array{id: string}> $rows */
        $rows = $queryBuilder->getQuery()->getArrayResult();

        return array_map(static fn (array $row): string => (string) $row['id'], $rows);
    }
}
