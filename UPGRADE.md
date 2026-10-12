# Upgrade guide

## From 1.x to 2.0

Version 2.0 stores the abbreviations with `sulu/content-bundle` (draft and live per locale, UUIDs as ids). The data of version 1.x has to be migrated.

1. Create a database backup.
2. `composer require manuxi/sulu-abbreviations-bundle:^2.0`, register `Sulu\Bundle\ContentBundle\SuluContentBundle` before the abbreviations bundle in `config/bundles.php`.
3. Create the new tables (`doctrine:migrations:diff` or [docs/templates/doctrine-migration.php.dist](docs/templates/doctrine-migration.php.dist)).
4. `bin/console sulu:abbreviations:migrate-to-content --dry-run`, then without `--dry-run`.
5. `bin/console sulu:abbreviations:migrate-page-references --dry-run`, then without `--dry-run`.
6. `bin/console sulu:search:reindex` and `bin/console sulu:reference:refresh abbreviations`.
7. Adjust templates (variable `content`, arrays instead of entities, link provider alias `abbreviations`).
8. Drop the legacy tables `app_abbreviation*` after checking the result.

The complete guide with all details: [docs/upgrade-1-to-2.en.md](docs/upgrade-1-to-2.en.md) / [docs/upgrade-1-to-2.de.md](docs/upgrade-1-to-2.de.md).

## From 1.8 to 1.9

- Requires PHP 8.2 and Sulu 2.6.
- Templates must use the property type `abbreviation_selection` (the former backend alias `abbreviations_selection` was removed).
- Translations moved to `src/Resources/translations/`. The bundle no longer sets `framework.default_locale` of your application.
- Publishing and unpublishing require the `live` permission.
