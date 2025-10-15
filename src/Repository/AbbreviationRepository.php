<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
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

    public function findAllForSitemap(string $locale, ?int $limit = null, ?int $offset = null): array
    {
        $queryBuilder = $this->createQueryBuilder('abbreviation')
            ->leftJoin('abbreviation.translations', 'translation')
            ->where('translation.published = :published')
            ->setParameter('published', true)
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $locale)
            ->orderBy('translation.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->prepareFilter($queryBuilder, []);

        $abbreviations = $queryBuilder->getQuery()->getResult();
        if (!$abbreviations) {
            return [];
        }

        return $abbreviations;
    }

    public function countForSitemap(string $locale)
    {
        $query = $this->createQueryBuilder('abbreviation')
            ->select('count(abbreviation)')
            ->leftJoin('abbreviation.translations', 'translation')
            ->where('translation.published = :published')
            ->setParameter('published', true)
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $locale);

        return $query->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns filtered entities.
     * When pagination is active the result count is pageSize + 1 to determine has next page.
     *
     * @param array   $filters  array of filters: tags, tagOperator
     * @param int     $page
     * @param int     $pageSize
     * @param int     $limit
     * @param string  $locale
     * @param mixed[] $options
     *
     * @return object[]
     *
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
     * @param mixed[] $options
     *
     * @return string[]
     */
    protected function append(QueryBuilder $queryBuilder, string $alias, string $locale, $options = []): array
    {
        // $queryBuilder->andWhere($alias . '.translation.published = true');
        $queryBuilder->innerJoin($alias.'.translations', 'translation', Join::WITH, 'translation.locale = :locale');
        $queryBuilder->setParameter('locale', $locale);
        $queryBuilder->andWhere('translation.published = :published');
        $queryBuilder->setParameter('published', true);

        return [];
    }

    public function appendCategoriesRelation(QueryBuilder $queryBuilder, $alias)
    {
        return $alias.'.category';
        // $queryBuilder->addSelect($alias.'.category');
    }

    protected function appendSortByJoins(QueryBuilder $queryBuilder, string $alias, string $locale): void
    {
        $queryBuilder->innerJoin($alias.'.translations', 'translation', Join::WITH, 'translation.locale = :locale');
        $queryBuilder->setParameter('locale', $locale);
    }

    public function hasNextPage(array $filters, ?int $page, ?int $pageSize, ?int $limit, string $locale, array $options = []): bool
    {
        $pageCurrent = (key_exists('page', $options)) ? (int) $options['page'] : 0;
        $totalArticles = $this->createQueryBuilder('n')
            ->select('count(n.id)')
            ->leftJoin('n.translations', 'translation')
            ->where('translation.published = :published')
            ->setParameter('published', true)
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getSingleScalarResult();

        if ((int) ($limit * $pageCurrent) + $limit < (int) $totalArticles) {
            return true;
        } else {
            return false;
        }
    }

    public function getPublishedAbbreviations(array $filters, string $locale, ?int $page, $pageSize, $limit = null, array $options): array
    {
        $pageCurrent = (key_exists('page', $options)) ? (int) $options['page'] : 0;

        $queryBuilder = $this->createQueryBuilder('abbreviation')
            ->leftJoin('abbreviation.translations', 'translation')
            ->where('translation.published = :published')
            ->setParameter('published', true)
            ->andWhere('translation.locale = :locale')
            ->setParameter('locale', $locale)
            ->orderBy('translation.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($pageCurrent * $limit);

        $this->prepareFilter($queryBuilder, $filters);

        $abbreviation = $queryBuilder->getQuery()->getResult();
        if (!$abbreviation) {
            return [];
        }

        return $abbreviation;
    }

    private function prepareFilter(QueryBuilder $queryBuilder, array $filters): void
    {
        if (isset($filters['sortBy'])) {
            $queryBuilder->orderBy($filters['sortBy'], $filters['sortMethod']);
        }

        if (!empty($filters['tags']) || !empty($filters['categories'])) {
            $queryBuilder->leftJoin('abbreviation.abbreviationExcerpt', 'excerpt')
                ->leftJoin('excerpt.translations', 'excerpt_translation');
        }

        $this->prepareTagsFilter($queryBuilder, $filters);
        $this->prepareCategoriesFilter($queryBuilder, $filters);
    }

    private function prepareTagsFilter(QueryBuilder $queryBuilder, array $filters): void
    {
        if (empty($filters['tags'])) {
            return;
        }

        $operator = $filters['tagOperator'] ?? 'or';

        if ('and' === $operator) {
            // AND: Entity must have ALL tags (multiple JOINs necessary)
            foreach ($filters['tags'] as $i => $tag) {
                $alias = 'tag'.$i;
                $queryBuilder
                    ->innerJoin('excerpt_translation.tags', $alias)
                    ->andWhere($queryBuilder->expr()->eq($alias.'.id', ':tag'.$i))
                    ->setParameter('tag'.$i, $tag);
            }
        } else {
            // OR: Entity must at least have one of the tags
            $queryBuilder
                ->leftJoin('excerpt_translation.tags', 'tags')
                ->andWhere($queryBuilder->expr()->in('tags.id', ':tags'))
                ->setParameter('tags', $filters['tags']);
        }
    }

    private function prepareCategoriesFilter(QueryBuilder $queryBuilder, array $filters): void
    {
        if (empty($filters['categories'])) {
            return;
        }

        $queryBuilder->leftJoin('excerpt_translation.categories', 'categories');

        $operator = $filters['categoryOperator'] ?? 'or';

        if ('and' === $operator) {
            // AND: Entity must have ALL categories (multiple JOINs necessary)
            foreach ($filters['categories'] as $i => $category) {
                $alias = 'category'.$i;
                $queryBuilder
                    ->leftJoin('excerpt_translation.categories', $alias)
                    ->andWhere($alias.'.id = :category'.$i)
                    ->setParameter('category'.$i, $category);
            }
        } else {
            // OR: Entity must at least have one of the categories
            $queryBuilder
                ->andWhere('categories.id IN (:categories)')
                ->setParameter('categories', $filters['categories']);
        }
    }
}
