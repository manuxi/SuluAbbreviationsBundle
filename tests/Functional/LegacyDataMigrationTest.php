<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Manuxi\SuluAbbreviationsBundle\Command\MigrateToContentCommand;
use Manuxi\SuluAbbreviationsBundle\Entity\Abbreviation;
use Manuxi\SuluAbbreviationsBundle\Entity\AbbreviationDimensionContent;
use Manuxi\SuluAbbreviationsBundle\Migration\LegacyDataMigrator;
use Manuxi\SuluAbbreviationsBundle\Resolver\AbbreviationResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\ContentBundle\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Bundle\RouteBundle\Entity\Route;
use Symfony\Component\Console\Tester\CommandTester;

class LegacyDataMigrationTest extends AbbreviationKernelTestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideNamingStrategies(): iterable
    {
        yield 'underscore columns' => [true];
        yield 'camel case columns' => [false];
    }

    #[DataProvider('provideNamingStrategies')]
    public function testMigratesPublishedAndUnpublishedTranslations(bool $underscore): void
    {
        $this->createLegacySchema($underscore);
        $this->insertLegacyData($underscore);

        $result = $this->getMigrator()->migrate();

        $this->assertTrue($result->legacyTablesFound);
        $this->assertSame(2, $result->migrated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(3, $result->translations);
        $this->assertSame(2, $result->published);
        $this->assertSame([1, 2], array_keys($result->idMap));

        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $html = $entityManager->getRepository(Abbreviation::class)->findOneBy(['legacyId' => 1]);
        $this->assertInstanceOf(Abbreviation::class, $html);
        $this->assertSame($result->idMap[1], $html->getId());

        $contentManager = $this->getService('sulu_content.content_manager');
        \assert($contentManager instanceof ContentManagerInterface);

        $draft = $contentManager->resolve($html, ['locale' => 'de']);
        $this->assertInstanceOf(AbbreviationDimensionContent::class, $draft);
        $this->assertSame('HTML', $draft->getName());
        $this->assertSame('HyperText Markup Language', $draft->getTemplateData()['explanation']);
        $this->assertSame('<p>Markup</p>', $draft->getTemplateData()['description']);
        $this->assertSame(['id' => 5], $draft->getTemplateData()['image']);
        $this->assertSame(['provider' => 'pages', 'href' => 'abc'], $draft->getTemplateData()['link']);
        $this->assertSame('/abkuerzungen/html', $draft->getTemplateData()['url']);
        $this->assertSame('published', $draft->getWorkflowPlace());
        $this->assertSame('2026-01-02', $draft->getWorkflowPublished()?->format('Y-m-d'));
        $this->assertSame('2026-01-01', $draft->getAuthored()?->format('Y-m-d'));
        $this->assertSame('SEO title', $draft->getSeoTitle());
        $this->assertSame('https://example.org/html', $draft->getSeoCanonicalUrl());
        $this->assertTrue($draft->getSeoNoIndex());
        $this->assertFalse($draft->getSeoNoFollow());
        $this->assertSame('Excerpt title', $draft->getExcerptTitle());
        $this->assertSame(['id' => 11], $draft->getExcerptImage());
        $this->assertSame(['id' => 12], $draft->getExcerptIcon());

        $resolver = $this->getService(AbbreviationResolver::class);
        \assert($resolver instanceof AbbreviationResolver);
        $live = $resolver->resolve($html->getId(), 'de');
        $this->assertNotNull($live);
        $this->assertSame('HTML', $live['name']);
        $this->assertNull($resolver->resolve($html->getId(), 'en'));

        $css = $entityManager->getRepository(Abbreviation::class)->findOneBy(['legacyId' => 2]);
        $this->assertInstanceOf(Abbreviation::class, $css);
        $cssDraft = $contentManager->resolve($css, ['locale' => 'de']);
        $this->assertSame('unpublished', $cssDraft->getWorkflowPlace());
        $this->assertNull($resolver->resolve($css->getId(), 'de'));
    }

    #[DataProvider('provideNamingStrategies')]
    public function testRoutesAreAssignedToTheNewIds(bool $underscore): void
    {
        $this->createLegacySchema($underscore);
        $this->insertLegacyData($underscore);

        $result = $this->getMigrator()->migrate();

        $this->assertSame(1, $result->routesUpdated);
        $this->assertSame(1, $result->routesRemoved);
        $this->assertSame(1, $result->routesCreated);

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $routes = $entityManager->getRepository(Route::class)->findAll();

        $byPath = [];
        foreach ($routes as $route) {
            $byPath[$route->getPath()] = $route->getEntityId();
        }

        $this->assertSame($result->idMap[1], $byPath['/abkuerzungen/html']);
        $this->assertSame($result->idMap[2], $byPath['/abbreviations/css-sheets']);
        $this->assertArrayNotHasKey('/abkuerzungen/css', $byPath);
        $this->assertCount(2, $routes);
    }

    public function testMigrationIsIdempotent(): void
    {
        $this->createLegacySchema(true);
        $this->insertLegacyData(true);

        $first  = $this->getMigrator()->migrate();
        $second = $this->getMigrator()->migrate();

        $this->assertSame(2, $first->migrated);
        $this->assertSame(0, $second->migrated);
        $this->assertSame(2, $second->skipped);
        $this->assertCount(2, $this->getEntityManager()->getRepository(Abbreviation::class)->findAll());
    }

    public function testDryRunDoesNotChangeAnything(): void
    {
        $this->createLegacySchema(true);
        $this->insertLegacyData(true);

        $result = $this->getMigrator()->migrate(true);

        $this->assertSame(2, $result->migrated);
        $this->assertSame(1, $result->routesUpdated);

        $entityManager = $this->getEntityManager();
        $entityManager->clear();
        $this->assertCount(0, $entityManager->getRepository(Abbreviation::class)->findAll());
        $this->assertSame(
            ['1', '2'],
            array_map(static fn (Route $route): string => $route->getEntityId(), $entityManager->getRepository(Route::class)->findAll()),
        );

        $this->assertSame(2, $this->getMigrator()->migrate()->migrated);
    }

    public function testNothingHappensWithoutLegacyTables(): void
    {
        $result = $this->getMigrator()->migrate();

        $this->assertFalse($result->legacyTablesFound);
        $this->assertSame(0, $result->migrated);
    }

    public function testCommandReportsTheResult(): void
    {
        $this->createLegacySchema(true);
        $this->insertLegacyData(true);

        $command = $this->getService(MigrateToContentCommand::class);
        \assert($command instanceof MigrateToContentCommand);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['--dry-run' => true]));
        $this->assertStringContainsString('Dry run', $tester->getDisplay());
        $this->assertMatchesRegularExpression('/Migrated abbreviations\s+2/', $tester->getDisplay());

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('Migration finished', $tester->getDisplay());

        $this->assertSame(0, $tester->execute([]));
        $this->assertMatchesRegularExpression('/Already migrated \(skipped\)\s+2/', $tester->getDisplay());
    }

    public function testCommandWarnsWithoutLegacyTables(): void
    {
        $command = $this->getService(MigrateToContentCommand::class);
        \assert($command instanceof MigrateToContentCommand);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute([]));
        $this->assertStringContainsString('legacy tables', $tester->getDisplay());
    }

    private function getMigrator(): LegacyDataMigrator
    {
        $migrator = $this->getService(LegacyDataMigrator::class);
        \assert($migrator instanceof LegacyDataMigrator);

        return $migrator;
    }

    private function getConnection(): Connection
    {
        return $this->getEntityManager()->getConnection();
    }

    private function column(string $name, bool $underscore): string
    {
        return $underscore ? mb_strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) : $name;
    }

    private function createLegacySchema(bool $underscore): void
    {
        $c = fn (string $name): string => $this->column($name, $underscore);

        $statements = [
            'CREATE TABLE app_abbreviation (id INTEGER PRIMARY KEY)',
            \sprintf(
                'CREATE TABLE app_abbreviation_translation (id INTEGER PRIMARY KEY, %s INTEGER, locale VARCHAR(5), name VARCHAR(255), explanation TEXT, description TEXT, %s INTEGER, link CLOB, published BOOLEAN, %s DATETIME, %s INTEGER, %s VARCHAR(255), %s BOOLEAN, %s BOOLEAN, %s INTEGER, authored DATETIME)',
                $c('abbreviationId'),
                $c('imageId'),
                $c('publishedAt'),
                $c('publishedState'),
                $c('routePath'),
                $c('showAuthor'),
                $c('showDate'),
                $c('authorId')
            ),
            \sprintf('CREATE TABLE app_abbreviation_seo (id INTEGER PRIMARY KEY, %s INTEGER, %s BOOLEAN, %s BOOLEAN, %s BOOLEAN)', $c('abbreviationId'), $c('hideInSitemap'), $c('noFollow'), $c('noIndex')),
            \sprintf('CREATE TABLE app_abbreviation_seo_translation (id INTEGER PRIMARY KEY, %s INTEGER, locale VARCHAR(5), title VARCHAR(255), %s TEXT, keywords TEXT, description TEXT)', $c('abbreviationSeoId'), $c('canonicalUrl')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt (id INTEGER PRIMARY KEY, %s INTEGER)', $c('abbreviationId')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt_translation (id INTEGER PRIMARY KEY, %s INTEGER, locale VARCHAR(5), title VARCHAR(255), more VARCHAR(255), description TEXT)', $c('abbreviationExcerptId')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt_tags (%s INTEGER, %s INTEGER)', $c('excerptId'), $c('tagId')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt_categories (%s INTEGER, %s INTEGER)', $c('excerptId'), $c('categoryId')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt_icons (%s INTEGER, %s INTEGER)', $c('excerptId'), $c('iconId')),
            \sprintf('CREATE TABLE app_abbreviation_excerpt_images (%s INTEGER, %s INTEGER)', $c('excerptId'), $c('imageId')),
        ];

        foreach ($statements as $statement) {
            $this->getConnection()->executeStatement($statement);
        }
    }

    private function insertLegacyData(bool $underscore): void
    {
        $c          = fn (string $name): string => $this->column($name, $underscore);
        $connection = $this->getConnection();

        $connection->insert('app_abbreviation', ['id' => 1]);
        $connection->insert('app_abbreviation', ['id' => 2]);

        $connection->insert('app_abbreviation_translation', [
            'id'                  => 1,
            $c('abbreviationId')  => 1,
            'locale'              => 'de',
            'name'                => 'HTML',
            'explanation'         => 'HyperText Markup Language',
            'description'         => '<p>Markup</p>',
            $c('imageId')         => 5,
            'link'                => '{"provider":"pages","href":"abc"}',
            'published'           => 1,
            $c('publishedAt')     => '2026-01-02 10:00:00',
            $c('publishedState')  => 1,
            $c('routePath')       => '/abkuerzungen/html',
            $c('showAuthor')      => 1,
            $c('showDate')        => 0,
            'authored'            => '2026-01-01 08:00:00',
        ]);
        $connection->insert('app_abbreviation_translation', [
            'id'                 => 2,
            $c('abbreviationId') => 2,
            'locale'             => 'de',
            'name'               => 'CSS',
            'explanation'        => 'Cascading Style Sheets',
            'published'          => 0,
            $c('routePath')      => '/abkuerzungen/css',
        ]);
        $connection->insert('app_abbreviation_translation', [
            'id'                 => 3,
            $c('abbreviationId') => 2,
            'locale'             => 'en',
            'name'               => 'CSS',
            'explanation'        => 'Cascading Style Sheets',
            'published'          => 1,
            $c('publishedAt')    => '2026-02-01 10:00:00',
            $c('routePath')      => '/abbreviations/css-sheets',
        ]);

        $connection->insert('app_abbreviation_seo', [
            'id'                 => 1,
            $c('abbreviationId') => 1,
            $c('hideInSitemap')  => 0,
            $c('noFollow')       => 0,
            $c('noIndex')        => 1,
        ]);
        $connection->insert('app_abbreviation_seo_translation', [
            'id'                    => 1,
            $c('abbreviationSeoId') => 1,
            'locale'                => 'de',
            'title'                 => 'SEO title',
            $c('canonicalUrl')      => 'https://example.org/html',
            'keywords'              => 'html',
            'description'           => 'SEO description',
        ]);

        $connection->insert('app_abbreviation_excerpt', ['id' => 1, $c('abbreviationId') => 1]);
        $connection->insert('app_abbreviation_excerpt_translation', [
            'id'                        => 1,
            $c('abbreviationExcerptId') => 1,
            'locale'                    => 'de',
            'title'                     => 'Excerpt title',
            'more'                      => 'More',
            'description'               => 'Excerpt description',
        ]);
        $connection->insert('app_abbreviation_excerpt_images', [$c('excerptId') => 1, $c('imageId') => 11]);
        $connection->insert('app_abbreviation_excerpt_icons', [$c('excerptId') => 1, $c('iconId') => 12]);

        $now = (new \DateTime())->format('Y-m-d H:i:s');
        foreach ([
            ['/abkuerzungen/html', 'de', '1'],
            ['/abkuerzungen/css', 'de', '2'],
        ] as [$path, $locale, $entityId]) {
            $connection->insert('ro_routes', [
                'path'         => $path,
                'locale'       => $locale,
                'entity_class' => Abbreviation::class,
                'entity_id'    => $entityId,
                'history'      => 0,
                'created'      => $now,
                'changed'      => $now,
            ]);
        }
    }
}
