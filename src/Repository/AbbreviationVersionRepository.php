<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationVersion;

class AbbreviationVersionRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function add(AbbreviationVersion $version): void
    {
        $this->entityManager->persist($version);
    }

    /**
     * @return AbbreviationVersion[] ordered by version, newest first
     */
    public function findByAbbreviation(Abbreviation $abbreviation, string $locale, ?int $limit = null, int $offset = 0): array
    {
        $query = $this->createQueryBuilder($abbreviation, $locale)
            ->orderBy('version.version', 'DESC')
            ->setFirstResult($offset);

        if (null !== $limit) {
            $query->setMaxResults($limit);
        }

        /** @var AbbreviationVersion[] $versions */
        $versions = $query->getQuery()->getResult();

        return $versions;
    }

    public function countByAbbreviation(Abbreviation $abbreviation, string $locale): int
    {
        return (int) $this->createQueryBuilder($abbreviation, $locale)
            ->select('COUNT(version.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByVersion(Abbreviation $abbreviation, string $locale, int $version): ?AbbreviationVersion
    {
        $result = $this->createQueryBuilder($abbreviation, $locale)
            ->andWhere('version.version = :versionNumber')
            ->setParameter('versionNumber', $version)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof AbbreviationVersion ? $result : null;
    }

    public function findLatestVersionNumber(Abbreviation $abbreviation, string $locale): ?int
    {
        $latest = $this->findByAbbreviation($abbreviation, $locale, 1)[0] ?? null;

        return $latest?->getVersion();
    }

    /**
     * Removes all versions except the newest ones and returns the number of removed versions.
     */
    public function removeOldVersions(Abbreviation $abbreviation, string $locale, int $keep): int
    {
        $removed = 0;
        foreach ($this->findByAbbreviation($abbreviation, $locale, null, max(0, $keep)) as $version) {
            $this->entityManager->remove($version);
            ++$removed;
        }

        return $removed;
    }

    private function createQueryBuilder(Abbreviation $abbreviation, string $locale): \Doctrine\ORM\QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('version')
            ->from(AbbreviationVersion::class, 'version')
            ->where('version.abbreviation = :abbreviation')
            ->andWhere('version.locale = :locale')
            ->setParameter('abbreviation', $abbreviation)
            ->setParameter('locale', $locale);
    }
}
