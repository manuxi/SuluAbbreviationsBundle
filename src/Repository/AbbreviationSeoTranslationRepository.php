<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeoTranslation;

/**
 * @method AbbreviationSeoTranslation|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationSeoTranslation|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationSeoTranslation[]    findAll()
 * @method AbbreviationSeoTranslation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<AbbreviationSeoTranslation>
 */
class AbbreviationSeoTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationSeoTranslation::class);
    }
}
