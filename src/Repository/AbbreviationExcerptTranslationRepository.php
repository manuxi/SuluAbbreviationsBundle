<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerptTranslation;

/**
 * @method AbbreviationExcerptTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationExcerptTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationExcerptTranslation[]    findAll()
 * @method AbbreviationExcerptTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<AbbreviationExcerptTranslation>
 */
class AbbreviationExcerptTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationExcerptTranslation::class);
    }
}
