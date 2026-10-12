<?php

declare(strict_types=1);

namespace Manuxi\SuluAbbreviationsBundle\Tests\Functional;

use Manuxi\SuluAbbreviationsBundle\Admin\AbbreviationsAdmin;
use Manuxi\SuluAbbreviationsBundle\Admin\SettingsAdmin;
use Manuxi\SuluAbbreviationsBundle\Command\MigratePageReferencesCommand;
use Manuxi\SuluAbbreviationsBundle\Command\MigrateToContentCommand;
use Manuxi\SuluAbbreviationsBundle\Content\AbbreviationsDataProvider;
use Manuxi\SuluAbbreviationsBundle\Content\Type\AbbreviationsSelection;
use Manuxi\SuluAbbreviationsBundle\Content\Type\SingleAbbreviationSelection;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationController;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\AbbreviationVersionController;
use Manuxi\SuluAbbreviationsBundle\Controller\Admin\SettingsController;
use Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber;
use Manuxi\SuluAbbreviationsBundle\Link\AbbreviationLinkProvider;
use Manuxi\SuluAbbreviationsBundle\Reference\AbbreviationReferenceRefresher;
use Manuxi\SuluAbbreviationsBundle\Teaser\AbbreviationTeaserProvider;
use Manuxi\SuluAbbreviationsBundle\Trash\AbbreviationTrashItemHandler;
use Manuxi\SuluAbbreviationsBundle\Twig\AbbreviationsSettingsTwigExtension;
use Manuxi\SuluAbbreviationsBundle\Twig\AbbreviationsTwigExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\AdminBundle\Admin\AdminPool;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Preview\ContentObjectProvider;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Search\ContentReindexProvider;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Search\ContentSearchMetadataProvider;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\Sitemap\ContentSitemapProvider;
use Sulu\Bundle\ContentBundle\Content\Infrastructure\Sulu\SmartContent\Repository\ContentDataProviderRepository;
use Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStore;

class ServiceWiringTest extends AbbreviationKernelTestCase
{
    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function provideServices(): iterable
    {
        yield 'abbreviation controller' => [AbbreviationController::class, AbbreviationController::class];
        yield 'version controller' => [AbbreviationVersionController::class, AbbreviationVersionController::class];
        yield 'settings controller' => [SettingsController::class, SettingsController::class];
        yield 'selection content type' => [AbbreviationsSelection::class, AbbreviationsSelection::class];
        yield 'single selection content type' => [SingleAbbreviationSelection::class, SingleAbbreviationSelection::class];
        yield 'link provider' => [AbbreviationLinkProvider::class, AbbreviationLinkProvider::class];
        yield 'teaser provider' => [AbbreviationTeaserProvider::class, AbbreviationTeaserProvider::class];
        yield 'sitemap provider' => ['sulu_abbreviations.sitemap_provider', ContentSitemapProvider::class];
        yield 'data provider' => ['sulu_abbreviations.data_provider', AbbreviationsDataProvider::class];
        yield 'data provider repository' => ['sulu_abbreviations.data_provider_repository', ContentDataProviderRepository::class];
        yield 'reindex provider' => ['sulu_abbreviations.reindex_provider', ContentReindexProvider::class];
        yield 'search metadata provider' => ['sulu_abbreviations.search_metadata_provider', ContentSearchMetadataProvider::class];
        yield 'preview object provider' => ['sulu_abbreviations.preview_object_provider', ContentObjectProvider::class];
        yield 'reference store' => ['sulu_abbreviations.reference_store', ReferenceStore::class];
        yield 'reference refresher' => [AbbreviationReferenceRefresher::class, AbbreviationReferenceRefresher::class];
        yield 'cache subscriber' => [AbbreviationCacheInvalidationSubscriber::class, AbbreviationCacheInvalidationSubscriber::class];
        yield 'trash handler' => [AbbreviationTrashItemHandler::class, AbbreviationTrashItemHandler::class];
        yield 'twig extension' => [AbbreviationsTwigExtension::class, AbbreviationsTwigExtension::class];
        yield 'settings twig extension' => [AbbreviationsSettingsTwigExtension::class, AbbreviationsSettingsTwigExtension::class];
        yield 'migration command' => [MigrateToContentCommand::class, MigrateToContentCommand::class];
        yield 'page reference command' => [MigratePageReferencesCommand::class, MigratePageReferencesCommand::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideServices')]
    public function testServiceCanBeCreated(string $id, string $class): void
    {
        $this->assertInstanceOf($class, $this->getService($id));
    }

    public function testAdminsAreRegisteredInTheAdminPool(): void
    {
        $pool = $this->getService('sulu_admin.admin_pool');
        \assert($pool instanceof AdminPool);

        $classes = array_map(static fn (object $admin): string => $admin::class, $pool->getAdmins());

        $this->assertContains(AbbreviationsAdmin::class, $classes);
        $this->assertContains(SettingsAdmin::class, $classes);
    }

    public function testAdminViewsCanBeBuilt(): void
    {
        $pool = $this->getService('sulu_admin.admin_pool');
        \assert($pool instanceof AdminPool);

        $collection = new ViewCollection();
        foreach ($pool->getAdmins() as $admin) {
            if ($admin instanceof AbbreviationsAdmin) {
                $admin->configureViews($collection);
            }
        }

        $this->assertTrue($collection->has(AbbreviationsAdmin::LIST_VIEW));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_FORM_VIEW . '.content'));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_FORM_VIEW . '.seo'));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_FORM_VIEW . '.excerpt'));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_FORM_VIEW . '.settings'));
        $this->assertTrue($collection->has(AbbreviationsAdmin::EDIT_INSIGHTS_VIEW . '.versions'));
    }
}
