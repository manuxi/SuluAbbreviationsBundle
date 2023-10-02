<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Common\Collections\Criteria;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\SmartContent\Orm\DataProviderRepositoryInterface;
use Sulu\Component\SmartContent\Orm\DataProviderRepositoryTrait;

/**
 * @method Abbreviation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Abbreviation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Abbreviation[]    findAll()
 * @method Abbreviation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @extends ServiceEntityRepository<Abbreviation>
 */
class AbbreviationRepository extends ServiceEntityRepository implements DataProviderRepositoryInterface
{
    use DataProviderRepositoryTrait {
        findByFilters as protected parentFindByFilters;
    }

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abbreviation::class);
    }

    public function create(string $locale): Abbreviation
    {
        $entity = new Abbreviation();
        $entity->setLocale($locale);

        return $entity;
    }

    public function remove(int $id): void
    {
        /** @var object $entity */
        $entity = $this->getEntityManager()->getReference(
            $this->getClassName(),
            $id
        );

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    public function save(Abbreviation $entity): Abbreviation
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }

    public function findById(int $id, string $locale): ?Abbreviation
    {
        $entity = $this->find($id);

        if (!$entity) {
            return null;
        }

        $entity->setLocale($locale);

        return $entity;
    }

    public function findAllForSitemap(int $page, int $limit): array
    {
        $offset = ($page * $limit) - $limit;
        $criteria = [
            'published' => true,
        ];
        return $this->findBy($criteria, [], $limit, $offset);
    }

    public function countForSitemap()
    {
        $query = $this->createQueryBuilder('e')
            ->select('count(e)');
        return $query->getQuery()->getSingleScalarResult();
    }

    public static function createEnabledCriteria(): Criteria
    {
        return Criteria::create()
            ->andWhere(Criteria::expr()->eq('published', true))
            ;
    }

    /**
     * Returns filtered entities.
     * When pagination is active the result count is pageSize + 1 to determine has next page.
     *
     * @param array $filters array of filters: tags, tagOperator
     * @param int $page
     * @param int $pageSize
     * @param int $limit
     * @param string $locale
     * @param mixed[] $options
     * @param UserInterface|null $user
     * @param null $entityClass
     * @param null $entityAlias
     * @param null $permission
     * @return object[]
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    public function findByFilters(
        $filters,
        $page,
        $pageSize,
        $limit,
        $locale,
        $options = [],
        ?UserInterface $user = null,
        $entityClass = null,
        $entityAlias = null,
        $permission = null
    ) {
        $entities = $this->getPublishedAbbreviations($filters, $locale, $page, $pageSize, $limit, $options);
        return \array_map(
            function (Abbreviation $entity) use ($locale) {
                return $entity->setLocale($locale);
            },
            $entities
        );
    }

    protected function appendJoins(QueryBuilder $queryBuilder, $alias, $locale): void
    {

    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $alias
     * @param string $locale
     * @param mixed[] $options
     *
     * @return string[]
     */
    protected function append(QueryBuilder $queryBuilder, string $alias, string $locale, $options = []): array
    {
        //$queryBuilder->andWhere($alias . '.translation.published = true');

        return [];
    }

    public function appendCategoriesRelation(QueryBuilder $queryBuilder, $alias)
    {
        return $alias . '.category';
        //$queryBuilder->addSelect($alias.'.category');
    }

    protected function appendSortByJoins(QueryBuilder $queryBuilder, string $alias, string $locale): void
    {
        $queryBuilder->innerJoin($alias . '.translations', 'translation', Join::WITH, 'translation.locale = :locale');
        $queryBuilder->setParameter('locale', $locale);
    }

    public function hasNextPage(array $filters, ?int $page, ?int $pageSize, ?int $limit, string $locale, array $options = []): bool
    {
        $pageCurrent = (key_exists('page', $options)) ? (int)$options['page'] : 0;
        $totalArticles = $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->leftJoin('n.translations', 'translation')
            ->where('translation.published = 1')
            ->andWhere('translation.locale = :locale')->setParameter('locale', $locale)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int)($limit * $pageCurrent) + $limit < (int)$totalArticles) return true; else return false;

    }

    public function getPublishedAbbreviations(array $filters, string $locale, ?int $page, $pageSize, ?int $limit, array $options): array
    {
        $pageCurrent = (key_exists('page', $options)) ? (int)$options['page'] : 0;

        $query = $this->createQueryBuilder('n')
            ->leftJoin('n.translations', 'translation')
            ->where('translation.published = 1')
            ->andWhere('translation.locale = :locale')->setParameter('locale', $locale)
            ->orderBy('translation.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($pageCurrent * $limit);

        if (!empty($filters['categories'])) {
            $i = 0;
            if ($filters['categoryOperator'] === "and") {
                $andWhere = "";
                foreach ($filters['categories'] as $category) {
                    if ($i === 0) {
                        $andWhere .= "n.category = :category" . $i;
                    } else {
                        $andWhere .= " AND n.category = :category" . $i;
                    }
                    $query->setParameter("category" . $i, $category);
                    $i++;
                }
                $query->andWhere($andWhere);
            } else if ($filters['categoryOperator'] === "or") {
                $orWhere = "";
                foreach ($filters['categories'] as $category) {
                    if ($i === 0) {
                        $orWhere .= "n.category = :category" . $i;
                    } else {
                        $orWhere .= " OR n.category = :category" . $i;
                    }
                    $query->setParameter("category" . $i, $category);
                    $i++;
                }
                $query->andWhere($orWhere);
            }
        }
        if (isset($filters['sortBy'])) $query->orderBy($filters['sortBy'], $filters['sortMethod']);
        $news = $query->getQuery()->getResult();
        if (!$news) {
            return [];
        }
        return $news;
    }

}
