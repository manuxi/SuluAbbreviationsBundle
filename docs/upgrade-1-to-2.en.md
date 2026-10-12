# Upgrade from 1.x to 2.0

Version 2.0 stores the content with `sulu/content-bundle` (draft/live per locale, UUIDs as ids). The data of version 1.x has to be migrated. **Create a database backup first.**

## 1. Update the code

```console
composer require manuxi/sulu-abbreviations-bundle:^2.0
```

- Register `Sulu\Bundle\ContentBundle\SuluContentBundle` in `config/bundles.php` **before** `SuluAbbreviationsBundle`.
- `manuxi/sulu-shared-tools-bundle` and `phpcr/phpcr-migrations-bundle` are no longer required by this bundle (keep them if your project uses them elsewhere).
- Remove the `sulu_abbreviations.objects` configuration if present.
- Keep the route import in `routes_admin.yaml` (`@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml`).
- Add the website search index: `abbreviations_published` (see README).

## 2. Create the new tables

Generate a migration with `bin/console doctrine:migrations:diff` or copy [templates/doctrine-migration.php.dist](templates/doctrine-migration.php.dist) (MySQL / MariaDB). The tables `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, the two join tables for excerpt tags / categories and `abbr_abbreviation_versions` are created. The tables of version 1.x (`app_abbreviation*`) are not changed.

## 3. Migrate the data

```console
bin/console sulu:abbreviations:migrate-to-content --dry-run
bin/console sulu:abbreviations:migrate-to-content
```

- Every abbreviation gets a new UUID. The old id is stored in `abbr_abbreviations.legacy_id`.
- Per locale a draft (and, if it was published, a live version) is created from name, explanation, description, image, link, route path, author, SEO and excerpt (image, icon, tags, categories).
- The routes of published translations are assigned to the new id (missing routes are created), the routes of unpublished translations are removed.
- Not migrated: `showAuthor`, `showDate`, creation / change timestamps and users of the old rows. The activity log and the trash keep the old integer ids.
- The command is idempotent: already migrated abbreviations are skipped.

## 4. Migrate the references in pages and snippets

```console
bin/console sulu:abbreviations:migrate-page-references --dry-run
bin/console sulu:abbreviations:migrate-page-references
```

The integer ids in `abbreviation_selection` / `single_abbreviation_selection` properties (also inside blocks) are replaced by the UUIDs, `<sulu-link provider="abbreviation" href="5">` and link properties become `provider="abbreviations"` with the UUID. Both PHPCR workspaces (draft and live) are processed. Other places which store ids (e.g. custom smart content filters or hard coded ids in templates) have to be adjusted manually.

## 5. Rebuild indexes and references

```console
bin/console sulu:search:reindex
bin/console sulu:reference:refresh abbreviations
```

## 6. Adjust templates and code

- The website template receives the normalized content in the variable `content` (before: the entity `abbreviation`). Selection fields and the Twig functions return arrays instead of entities: `abbreviation.name` still works, methods like `abbreviation.getName()` do not.
- Link provider alias: `abbreviations` (before `abbreviation`).
- The `copy` toolbar action is replaced by "copy to locale".
- Role permissions keep working: the security context `sulu.abbreviations.abbreviations` is unchanged.
- Entities, models and repositories of version 1.x were removed. Use `ContentManagerInterface` (service `sulu_content.content_manager`) or the Twig functions.

## 7. Clean up

After checking the result drop the legacy tables `app_abbreviation`, `app_abbreviation_translation`, `app_abbreviation_seo*`, `app_abbreviation_excerpt*` and the column `abbr_abbreviations.legacy_id` (optional, keep it if you want to run the page migration again).
