# Changelog

All notable changes to this project are documented in this file.

## [Unreleased] - 1.9.0

### Changed
- Require PHP `^8.2`, Sulu `^2.6` and Symfony `^6.4 | ^7.0`.
- Test suite migrated to PHPUnit 10.5 (`phpunit.xml.dist` uses the PHPUnit 10 schema with `<source>`); Prophecy is used via `phpspec/prophecy-phpunit`.
- Entity unit tests no longer boot the Sulu test kernel (`TestCase` instead of `SuluTestCase`).
- `.php-cs-fixer.php` enforces `declare(strict_types=1)` explicitly (newer `@Symfony:risky` rule sets would remove it); code base reformatted.

### Added
- GitHub Actions workflow `ci.yml`: PHP 8.2/8.3/8.4 matrix with `composer validate`, php-cs-fixer (dry run), PHPStan and PHPUnit.
- PHPStan (level 6) with `phpstan.neon.dist` and `phpstan-baseline.neon`.
- Composer scripts `phpstan`, `cs` and `cs-fix`.

### Fixed
- `AbbreviationRepository` and `AbbreviationsRouteDefaultsProvider` were registered twice (the route defaults provider was added twice to the route defaults chain); each is now one service, `sulu_abbreviations.repository.abbreviation` and `AbbreviationsRouteDefaultsProvider` are aliases.
- Selection content types: the backend registered `abbreviations_selection` twice while the admin field types are `abbreviation_selection` / `single_abbreviation_selection`. The content type aliases now match the field types. `abbreviation_selection` returns only abbreviations published in the current locale, keeps the selection order and sets the locale; `single_abbreviation_selection` sets the locale and returns `null` for unpublished entries.
- Wrong translation key `sulu_abbreviations.abbreviations_selection_label` in the selection field type options.
- Settings view could not load or save: the admin resource was registered as `abbreviations-settings` while the views use `AbbreviationsSettings::RESOURCE_KEY` (`abbreviations_settings`). The resource is now registered as `abbreviations_settings` (detail route `sulu_abbreviations.get_abbreviations-settings`, URL `/admin/api/abbreviations-settings` unchanged).

### Removed
- `Content\AbbreviationsSelectionContentType` (duplicate of `Content\Type\AbbreviationsSelection`). Templates must use the property type `abbreviation_selection`.
- Unused constant `AbbreviationsAdmin::SECURITY_CONTEXT` (`sulu.modules.abbreviation`); use `Abbreviation::SECURITY_CONTEXT`.
- Workflows `php.yml` and `symfony.yml` (broken `.env.test` copy step and `composer.lock` cache key).
- Dev dependencies `symfony/phpunit-bridge` and `phpspec/prophecy` (replaced by `phpspec/prophecy-phpunit`).
