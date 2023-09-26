<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationSeo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AbbreviationSeo|null find($id, $lockMode = null, $lockVersion = null)
 * @method AbbreviationSeo|null findOneBy(array $criteria, array $orderBy = null)
 * @method AbbreviationSeo[]    findAll()
 * @method AbbreviationSeo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Event>
 */
class AbbreviationSeoRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbbreviationSeo::class);
    }

    public function create(string $locale): AbbreviationSeo
    {
        $abbreviationSeo = new AbbreviationSeo();
        $abbreviationSeo->setLocale($locale);

        return $abbreviationSeo;
    }

    public function remove(int $id): void
    {
        /** @var object $abbreviationSeo */
        $abbreviationSeo = $this->getEntityManager()->getReference(
            $this->getClassName(),
            $id
        );

        $this->getEntityManager()->remove($abbreviationSeo);
        $this->getEntityManager()->flush();
    }

    public function save(AbbreviationSeo $abbreviationSeo): AbbreviationSeo
    {
        $this->getEntityManager()->persist($abbreviationSeo);
        $this->getEntityManager()->flush();
        return $abbreviationSeo;
    }

    public function findById(int $id, string $locale): ?AbbreviationSeo
    {
        $abbreviationSeo = $this->find($id);
        if (!$abbreviationSeo) {
            return null;
        }

        $abbreviationSeo->setLocale($locale);

        return $abbreviationSeo;
    }

}
