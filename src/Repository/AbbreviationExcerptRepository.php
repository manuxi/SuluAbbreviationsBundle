<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationExcerpt;

/**
 * @method AbbreviationExcerpt|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationExcerpt|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationExcerpt[]    findAll()
 * @method AbbreviationExcerpt[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Event>
 */
class AbbreviationExcerptRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationExcerpt::class);
    }

    public function create(string $locale): AbbreviationExcerpt
    {
        $abbreviationExcerpt = new AbbreviationExcerpt();
        $abbreviationExcerpt->setLocale($locale);

        return $abbreviationExcerpt;
    }

    public function remove(int $id): void
    {
        /** @var object $abbreviationExcerpt */
        $abbreviationExcerpt = $this->getEntityManager()->getReference(
            $this->getClassName(),
            $id
        );

        $this->getEntityManager()->remove($abbreviationExcerpt);
        $this->getEntityManager()->flush();
    }

    public function save(AbbreviationExcerpt $abbreviationExcerpt): AbbreviationExcerpt
    {
        $this->getEntityManager()->persist($abbreviationExcerpt);
        $this->getEntityManager()->flush();
        return $abbreviationExcerpt;
    }

    public function findById(int $id, string $locale): ?AbbreviationExcerpt
    {
        $abbreviationExcerpt = $this->find($id);
        if (!$abbreviationExcerpt) {
            return null;
        }

        $abbreviationExcerpt->setLocale($locale);

        return $abbreviationExcerpt;
    }

}
