<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Sulu\Bundle\CategoryBundle\Entity\CategoryInterface;
use Sulu\Bundle\ContactBundle\Entity\ContactInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\DimensionContentInterface;
use Sulu\Bundle\ContentBundle\Content\Domain\Model\WorkflowInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Sulu\Bundle\RouteBundle\Entity\RouteRepositoryInterface;
use Sulu\Bundle\TagBundle\Tag\TagInterface;

/**
 * Migrates the data of the 1.x version (translation, SEO and excerpt tables) into the content bundle structure.
 *
 * The legacy tables are only read, they are not changed or removed. The migration is idempotent: abbreviations
 * which were already migrated (the legacy id is stored in the column "legacy_id") are skipped.
 */
class LegacyDataMigrator
{
    public const TABLE_ABBREVIATION       = 'app_abbreviation';
    public const TABLE_TRANSLATION        = 'app_abbreviation_translation';
    public const TABLE_SEO                = 'app_abbreviation_seo';
    public const TABLE_SEO_TRANS          = 'app_abbreviation_seo_translation';
    public const TABLE_EXCERPT            = 'app_abbreviation_excerpt';
    public const TABLE_EXCERPT_TRANS      = 'app_abbreviation_excerpt_translation';
    public const TABLE_EXCERPT_TAGS       = 'app_abbreviation_excerpt_tags';
    public const TABLE_EXCERPT_CATEGORIES = 'app_abbreviation_excerpt_categories';
    public const TABLE_EXCERPT_ICONS      = 'app_abbreviation_excerpt_icons';
    public const TABLE_EXCERPT_IMAGES     = 'app_abbreviation_excerpt_images';

    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly RouteRepositoryInterface $routeRepository,
    ) {
    }

    public function migrate(bool $dryRun = false): MigrationResult
    {
        $result = new MigrationResult();

        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([self::TABLE_ABBREVIATION, self::TABLE_TRANSLATION])) {
            $result->legacyTablesFound = false;

            return $result;
        }

        $migratedLegacyIds   = $this->loadMigratedLegacyIds();
        $translations        = $this->groupBy($this->fetch(self::TABLE_TRANSLATION), 'abbreviationid');
        $seo                 = $this->indexBy($this->fetchOptional($schemaManager->tablesExist([self::TABLE_SEO]), self::TABLE_SEO), 'abbreviationid');
        $seoTranslations     = $this->groupBy($this->fetchOptional($schemaManager->tablesExist([self::TABLE_SEO_TRANS]), self::TABLE_SEO_TRANS), 'abbreviationseoid');
        $excerpt             = $this->indexBy($this->fetchOptional($schemaManager->tablesExist([self::TABLE_EXCERPT]), self::TABLE_EXCERPT), 'abbreviationid');
        $excerptTranslations = $this->groupBy($this->fetchOptional($schemaManager->tablesExist([self::TABLE_EXCERPT_TRANS]), self::TABLE_EXCERPT_TRANS), 'abbreviationexcerptid');
        $relations           = [
            'tags'       => $this->loadRelation($schemaManager->tablesExist([self::TABLE_EXCERPT_TAGS]), self::TABLE_EXCERPT_TAGS, 'tagid'),
            'categories' => $this->loadRelation($schemaManager->tablesExist([self::TABLE_EXCERPT_CATEGORIES]), self::TABLE_EXCERPT_CATEGORIES, 'categoryid'),
            'icons'      => $this->loadRelation($schemaManager->tablesExist([self::TABLE_EXCERPT_ICONS]), self::TABLE_EXCERPT_ICONS, 'iconid'),
            'images'     => $this->loadRelation($schemaManager->tablesExist([self::TABLE_EXCERPT_IMAGES]), self::TABLE_EXCERPT_IMAGES, 'imageid'),
        ];

        $this->connection->beginTransaction();

        try {
            foreach ($this->fetch(self::TABLE_ABBREVIATION) as $legacy) {
                $legacyId = (int) $legacy['id'];

                if (isset($migratedLegacyIds[$legacyId])) {
                    ++$result->skipped;

                    continue;
                }

                $abbreviation = $this->migrateAbbreviation(
                    $legacyId,
                    $translations[$legacyId] ?? [],
                    $seo[$legacyId]          ?? null,
                    $seoTranslations,
                    $excerpt[$legacyId] ?? null,
                    $excerptTranslations,
                    $relations,
                    $result
                );

                $this->entityManager->persist($abbreviation);
                $this->entityManager->flush();

                $result->idMap[$legacyId] = $abbreviation->getId();
                ++$result->migrated;
            }

            if ($dryRun) {
                $this->connection->rollBack();
                $this->entityManager->clear();
            } else {
                $this->connection->commit();
            }
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $this->entityManager->clear();

            throw $exception;
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>>                    $translations
     * @param array<string, mixed>|null                           $seo
     * @param array<int|string, array<int, array<string, mixed>>> $seoTranslations
     * @param array<string, mixed>|null                           $excerpt
     * @param array<int|string, array<int, array<string, mixed>>> $excerptTranslations
     * @param array<string, array<int|string, int[]>>             $relations
     */
    private function migrateAbbreviation(
        int $legacyId,
        array $translations,
        ?array $seo,
        array $seoTranslations,
        ?array $excerpt,
        array $excerptTranslations,
        array $relations,
        MigrationResult $result,
    ): Abbreviation {
        $abbreviation = new Abbreviation();
        $abbreviation->setLegacyId($legacyId);

        $seoRow          = $seo ?? [];
        $seoByLocale     = $this->indexBy($seoTranslations[$seo['id'] ?? 0] ?? [], 'locale');
        $excerptByLocale = $this->indexBy($excerptTranslations[$excerpt['id'] ?? 0] ?? [], 'locale');

        $locales   = [];
        $published = [];
        foreach ($translations as $translation) {
            $locale      = (string) $translation['locale'];
            $isPublished = (bool) ($translation['published'] ?? false);

            $draft = $this->createDimensionContent($abbreviation, DimensionContentInterface::STAGE_DRAFT, $locale);
            $this->fill($draft, $translation, $seoRow, $seoByLocale[$locale] ?? null, $excerptByLocale[$locale] ?? null, $relations);
            $draft->setWorkflowPlace($isPublished ? WorkflowInterface::WORKFLOW_PLACE_PUBLISHED : WorkflowInterface::WORKFLOW_PLACE_UNPUBLISHED);
            $draft->setWorkflowPublished($isPublished ? $this->toDate($translation['publishedat'] ?? null) ?? new \DateTimeImmutable() : null);
            $abbreviation->addDimensionContent($draft);

            if ($isPublished) {
                $live = $this->createDimensionContent($abbreviation, DimensionContentInterface::STAGE_LIVE, $locale);
                $this->fill($live, $translation, $seoRow, $seoByLocale[$locale] ?? null, $excerptByLocale[$locale] ?? null, $relations);
                $live->setWorkflowPublished($draft->getWorkflowPublished());
                $abbreviation->addDimensionContent($live);

                $published[] = $locale;
                ++$result->published;
            }

            $locales[] = $locale;
            ++$result->translations;
        }

        $this->addUnlocalizedDimensionContent($abbreviation, DimensionContentInterface::STAGE_DRAFT, $locales);
        if ([] !== $published) {
            $this->addUnlocalizedDimensionContent($abbreviation, DimensionContentInterface::STAGE_LIVE, $published);
        }

        $this->migrateRoutes($legacyId, $abbreviation, $translations, $result);

        return $abbreviation;
    }

    private function createDimensionContent(Abbreviation $abbreviation, string $stage, ?string $locale): AbbreviationDimensionContent
    {
        /** @var AbbreviationDimensionContent $dimensionContent */
        $dimensionContent = $abbreviation->createDimensionContent();
        $dimensionContent->setStage($stage);
        $dimensionContent->setLocale($locale);

        return $dimensionContent;
    }

    /**
     * @param string[] $locales
     */
    private function addUnlocalizedDimensionContent(Abbreviation $abbreviation, string $stage, array $locales): void
    {
        $dimensionContent = $this->createDimensionContent($abbreviation, $stage, null);
        $dimensionContent->setGhostLocale($locales[0] ?? null);
        $dimensionContent->setTemplateKey(Abbreviation::DEFAULT_TEMPLATE);

        foreach ($locales as $locale) {
            $dimensionContent->addAvailableLocale($locale);
        }

        $abbreviation->addDimensionContent($dimensionContent);
    }

    /**
     * @param array<string, mixed>                    $translation
     * @param array<string, mixed>                    $seo
     * @param array<string, mixed>|null               $seoTranslation
     * @param array<string, mixed>|null               $excerptTranslation
     * @param array<string, array<int|string, int[]>> $relations
     */
    private function fill(
        AbbreviationDimensionContent $dimensionContent,
        array $translation,
        array $seo,
        ?array $seoTranslation,
        ?array $excerptTranslation,
        array $relations,
    ): void {
        $data = [
            'name'        => $translation['name']        ?? null,
            'explanation' => $translation['explanation'] ?? null,
            'description' => $translation['description'] ?? null,
            'url'         => $translation['routepath']   ?? null,
        ];

        if (null !== ($translation['imageid'] ?? null)) {
            $data['image'] = ['id' => (int) $translation['imageid']];
        }

        $link = $this->decodeJson($translation['link'] ?? null);
        if (null !== $link) {
            $data['link'] = $link;
        }

        $dimensionContent->setTemplateKey(Abbreviation::DEFAULT_TEMPLATE);
        $dimensionContent->setTemplateData(array_filter($data, static fn (mixed $value): bool => null !== $value));

        $dimensionContent->setAuthored($this->toDate($translation['authored'] ?? null));
        if (null !== ($translation['authorid'] ?? null)) {
            $author = $this->entityManager->getReference(ContactInterface::class, (int) $translation['authorid']);
            $dimensionContent->setAuthor($author instanceof ContactInterface ? $author : null);
        }

        $dimensionContent->setSeoNoIndex((bool) ($seo['noindex'] ?? false));
        $dimensionContent->setSeoNoFollow((bool) ($seo['nofollow'] ?? false));
        $dimensionContent->setSeoHideInSitemap((bool) ($seo['hideinsitemap'] ?? false));

        if (null !== $seoTranslation) {
            $dimensionContent->setSeoTitle($seoTranslation['title'] ?? null);
            $dimensionContent->setSeoDescription($seoTranslation['description'] ?? null);
            $dimensionContent->setSeoKeywords($seoTranslation['keywords'] ?? null);
            $dimensionContent->setSeoCanonicalUrl($seoTranslation['canonicalurl'] ?? null);
        }

        if (null !== $excerptTranslation) {
            $excerptId = $excerptTranslation['id'];
            $dimensionContent->setExcerptTitle($excerptTranslation['title'] ?? null);
            $dimensionContent->setExcerptMore($excerptTranslation['more'] ?? null);
            $dimensionContent->setExcerptDescription($excerptTranslation['description'] ?? null);

            $image = ($relations['images'][$excerptId] ?? [])[0] ?? null;
            $dimensionContent->setExcerptImage(null === $image ? null : ['id' => $image]);
            $icon = ($relations['icons'][$excerptId] ?? [])[0] ?? null;
            $dimensionContent->setExcerptIcon(null === $icon ? null : ['id' => $icon]);

            $dimensionContent->setExcerptTags($this->findAll(TagInterface::class, $relations['tags'][$excerptId] ?? []));
            $dimensionContent->setExcerptCategories($this->findAll(CategoryInterface::class, $relations['categories'][$excerptId] ?? []));
        }
    }

    /**
     * Routes of published translations are assigned to the new uuid, the routes of unpublished translations
     * are removed (the content bundle only keeps routes of live content). Missing routes of published translations
     * are created.
     *
     * @param array<int, array<string, mixed>> $translations
     */
    private function migrateRoutes(int $legacyId, Abbreviation $abbreviation, array $translations, MigrationResult $result): void
    {
        $publishedLocales = [];
        $paths            = [];
        foreach ($translations as $translation) {
            $locale = (string) $translation['locale'];
            if ((bool) ($translation['published'] ?? false)) {
                $publishedLocales[$locale] = true;
                $paths[$locale]            = (string) ($translation['routepath'] ?? '');
            }
        }

        $assigned = [];
        foreach ($this->routeRepository->findAllByEntity(Abbreviation::class, (string) $legacyId) as $route) {
            $locale = $route->getLocale();

            if (!isset($publishedLocales[$locale])) {
                $this->entityManager->remove($route);
                ++$result->routesRemoved;

                continue;
            }

            $route->setEntityId($abbreviation->getId());
            ++$result->routesUpdated;

            if (!$route->isHistory() && $route->getPath() === ($paths[$locale] ?? null)) {
                $assigned[$locale] = true;
            }
        }

        foreach ($paths as $locale => $path) {
            if (isset($assigned[$locale]) || '' === $path) {
                continue;
            }

            $route = new Route();
            $route->setPath($path);
            $route->setLocale($locale);
            $route->setEntityClass(Abbreviation::class);
            $route->setEntityId($abbreviation->getId());
            $route->setHistory(false);
            $route->setCreated(new \DateTime());
            $route->setChanged(new \DateTime());
            $this->entityManager->persist($route);
            ++$result->routesCreated;
        }
    }

    /**
     * @return array<int, true>
     */
    private function loadMigratedLegacyIds(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['abbr_abbreviations'])) {
            throw new \RuntimeException('The table "abbr_abbreviations" does not exist, update the database schema first.');
        }

        $ids = [];
        foreach ($this->connection->fetchFirstColumn('SELECT legacy_id FROM abbr_abbreviations WHERE legacy_id IS NOT NULL') as $id) {
            $ids[(int) $id] = true;
        }

        return $ids;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $table): array
    {
        $rows = [];
        foreach ($this->connection->fetchAllAssociative('SELECT * FROM ' . $this->connection->quoteIdentifier($table)) as $row) {
            $normalized = [];
            foreach ($row as $column => $value) {
                $normalized[mb_strtolower(str_replace('_', '', (string) $column))] = $value;
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchOptional(bool $exists, string $table): array
    {
        return $exists ? $this->fetch($table) : [];
    }

    /**
     * @return array<int|string, int[]>
     */
    private function loadRelation(bool $exists, string $table, string $targetColumn): array
    {
        $relation = [];
        foreach ($this->fetchOptional($exists, $table) as $row) {
            $relation[$row['excerptid']][] = (int) $row[$targetColumn];
        }

        return $relation;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    private function groupBy(array $rows, string $column): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row[$column]][] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function indexBy(array $rows, string $column): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row[$column]] = $row;
        }

        return $indexed;
    }

    /**
     * @param class-string $class
     * @param int[]        $ids
     *
     * @return object[]
     */
    private function findAll(string $class, array $ids): array
    {
        $objects = [];
        foreach ($ids as $id) {
            $object = $this->entityManager->find($class, $id);
            if (null !== $object) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): ?array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return \is_array($decoded) ? $decoded : null;
    }

    private function toDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value) || '' === $value) {
            return null;
        }

        return new \DateTimeImmutable($value);
    }
}
