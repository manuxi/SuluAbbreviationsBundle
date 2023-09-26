<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeoTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbbreviationSeoTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationSeoTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationSeoTranslation[]    findAll()
 * @method AbbreviationSeoTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<AbbreviationTranslation>
 */
class AbbreviationSeoTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationSeoTranslation::class);
    }
}
