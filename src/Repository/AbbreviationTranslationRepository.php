<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbbreviationTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationTranslation[]    findAll()
 * @method AbbreviationTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<AbbreviationTranslation>
 */
class AbbreviationTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationTranslation::class);
    }

    public function findMissingLocaleByIds(array $ids, string $missingLocale, int $countLocales)
    {
        $query = $this->createQueryBuilder('et')
            ->addCriteria($this->createIdsInCriteria($ids))
            ->groupby('et.abbreviation')
            ->having('abbreviationCount < :countLocales')
            ->setParameter('countLocales', $countLocales)
            ->andHaving('et.locale = :locale')
            ->setParameter('locale', $missingLocale)
            ->select('IDENTITY(et.abbreviation) as abbreviation, et.locale, count(et.abbreviation) as abbreviationCount')
            ->getQuery()
        ;
//        dump($query->getSQL());
        return $query->getResult();
    }

    private function createIdsInCriteria(array $ids): Criteria
    {
        return Criteria::create()
            ->andWhere(Criteria::expr()->in('abbreviation', $ids))
            ;
    }

}
