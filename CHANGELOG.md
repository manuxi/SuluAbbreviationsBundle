# Changelog

All notable changes to this project are documented in this file.

## [Unreleased] - 1.9.0

### Changed
- Require PHP `^8.2`, Sulu `^2.6` and Symfony `^6.4 | ^7.0`.
- Test suite migrated to PHPUnit 10.5 (`phpunit.xml.dist` uses the PHPUnit 10 schema with `<source>`); Prophecy is used via `phpspec/prophecy-phpunit`.
- Entity unit tests no longer boot the Sulu test kernel (`TestCase` instead of `SuluTestCase`).
- `.php-cs-fixer.php` enforces `declare(strict_types=1)` explicitly (newer `@Symfony:risky` rule sets would remove it); code base reformatted.

### Added
- Copy toolbar action (`sulu_admin.copy`, requires the `ADD` permission) in the edit form; `POST /admin/api/abbreviations?action=copy&id={id}` is handled by `AbbreviationsController::postAction()`.
- `Mapper\AbbreviationDataMapper` (service `sulu_abbreviations.data_mapper`): maps admin form data onto an abbreviation; used by `AbbreviationModel` and the preview. `AbbreviationModel` receives the mapper instead of the media and contact repositories.
- GitHub Actions workflow `ci.yml`: PHP 8.2/8.3/8.4 matrix with `composer validate`, php-cs-fixer (dry run), PHPStan and PHPUnit.
- PHPStan (level 6) with `phpstan.neon.dist` and `phpstan-baseline.neon`.
- Composer scripts `phpstan`, `cs` and `cs-fix`.

### Fixed
- `AbbreviationRepository` and `AbbreviationsRouteDefaultsProvider` were registered twice (the route defaults provider was added twice to the route defaults chain); each is now one service, `sulu_abbreviations.repository.abbreviation` and `AbbreviationsRouteDefaultsProvider` are aliases.
- Selection content types: the backend registered `abbreviations_selection` twice while the admin field types are `abbreviation_selection` / `single_abbreviation_selection`. The content type aliases now match the field types. `abbreviation_selection` returns only abbreviations published in the current locale, keeps the selection order and sets the locale; `single_abbreviation_selection` sets the locale and returns `null` for unpublished entries.
- Wrong translation key `sulu_abbreviations.abbreviations_selection_label` in the selection field type options.
- Settings view could not load or save: the admin resource was registered as `abbreviations-settings` while the views use `AbbreviationsSettings::RESOURCE_KEY` (`abbreviations_settings`). The resource is now registered as `abbreviations_settings` (detail route `sulu_abbreviations.get_abbreviations-settings`, URL `/admin/api/abbreviations-settings` unchanged).
- The settings payload passed to `Settings\ModifiedEvent` was silently dropped (the constructor accepted only the entity). `Settings\AbstractEvent` now accepts and returns the payload; missing keys in the PUT body no longer raise warnings.
- Translations moved from `src/Resources/config/translations/` to `src/Resources/translations/`, where Symfony discovers them automatically. The extension no longer prepends `framework.translator.paths` and no longer overrides the host application's `framework.default_locale` with `en`.
- Automation task handlers translated the non-existing keys `sulu_abbreviation.publish` / `sulu_abbreviation.unpublish`; they now use `sulu_abbreviations.publish` / `sulu_abbreviations.unpublish`. A unit test checks that every key passed to `trans()` exists in the de/en translation files.
- Twig: `sulu_get_abbreviations()` requested page 0 and defaulted to the locale `en`; it now requests page 1 and uses the locale of the current request when no locale is passed. `sulu_resolve_abbreviation()` also uses the request locale by default and returns `null` for abbreviations that are not published in that locale.
- Sitemap: `AbbreviationsSitemapProvider` could access an undefined array index for unknown hosts, used only one locale per host, ignored URL prefixes (e.g. `/de`) and reported the locale of the entity default instead of the queried locale. It now creates entries for every localization of the host (falling back to the default localization of the portal), builds URLs via `WebspaceManager::findUrlByResourceLocator()`, uses typed return values and computes `getMaxPage()` as an integer. `AbbreviationRepository::countForSitemap()` returns `int`. The service receives `%kernel.environment%` as third argument.
- Settings tab of an abbreviation contained page-only fields (`shadowOn` schema, webspace settings) that were never mapped. The preview of the SEO, excerpt and settings tabs used the page condition `nodeType == 1` and never rendered; it now uses `id != null`.
- The edit form always showed the delete action and the publish toggler because its toolbar was overwritten after the permission checks. Delete now requires the `DELETE` permission, the publish toggler the `LIVE` permission; the add form only offers save.
- Preview crashed: `AbbreviationsObjectProvider::getSecurityContext()` accessed an undefined `documentInspector`, `setContext()` called the non-existing `setStructureType()` and `setValues()` was empty. The provider now returns `Abbreviation::SECURITY_CONTEXT`, maps the form data via `AbbreviationDataMapper` and initializes lazy translation collections before serializing.
- The `copy` action failed with a `TypeError` because `Abbreviation::copy()` returned `null`. `Abbreviation::copy()` now returns a new, not yet persisted abbreviation with copies of all translations, SEO and excerpt data (categories, tags and media are shared). The copy is unpublished in all locales and the route paths get the suffix `-copy`; `AbbreviationModel::copy()` creates the routes of all locales (conflicts are resolved by the route manager) and collects an `AbbreviationCreatedEvent`.
- `POST /admin/api/abbreviations/{id}?action=publish|unpublish` only required the `ADD` permission (derived from the HTTP method). Both actions now require the `LIVE` permission for the requested locale.
- Trash restore created the route with an integer entity id and an integer history flag (`Route::setEntityId()` expects a string, `setHistory()` a bool) and put the JSON-decoded `ext` arrays back into the entity instead of the SEO/excerpt objects.
- `AbbreviationLinkProvider` passes the id and title as strings and the published state as bool to `LinkItem`.
- Wrong `@extends ServiceEntityRepository<...>` generics in the SEO, excerpt and translation repositories (referenced the non-existing classes `Event` / `AbbreviationTranslation`).

### Removed
- `Content\AbbreviationsSelectionContentType` (duplicate of `Content\Type\AbbreviationsSelection`). Templates must use the property type `abbreviation_selection`.
- `Admin\Helper\WebspaceSelector` (service `sulu_abbreviations.webspace_selector`) and the unused webspace/shadow translation keys.
- Unused constant `AbbreviationsAdmin::SECURITY_CONTEXT` (`sulu.modules.abbreviation`); use `Abbreviation::SECURITY_CONTEXT`.
- Workflows `php.yml` and `symfony.yml` (broken `.env.test` copy step and `composer.lock` cache key).
- Dev dependencies `symfony/phpunit-bridge` and `phpspec/prophecy` (replaced by `phpspec/prophecy-phpunit`).
