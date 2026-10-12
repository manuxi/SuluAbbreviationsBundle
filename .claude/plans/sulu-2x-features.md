# Plan: Sulu 2.6 line - features, bug fixes, tests (branch `2.x`)

This document is the complete brief for a fresh session. Read it fully before touching code.
The Sulu 3 migration is a separate plan: `.claude/plans/sulu-3x-migration.md`.

## 0. Context

- Package: `manuxi/sulu-abbreviations-bundle`, namespace `Manuxi\SuluAbbreviationsBundle`.
- Current state: tag `1.8.5` on `main` (Sulu `^2.5`, PHP `^8.1`), classic model with own translation
  entities (`Abbreviation`, `AbbreviationTranslation`, `AbbreviationSeo*`, `AbbreviationExcerpt*`) and
  `manuxi/sulu-shared-tools-bundle ^0.4` traits.
- Target: Sulu **2.6** (latest 2.6.x), PHP **^8.2**, Symfony **6.4 | 7.x**.
- Reference implementations (read them, do not guess APIs):
  - `vendor/sulu/sulu` (2.6) - especially `PageBundle/Admin/PageAdmin.php` (copy/delete locale, versions tab),
    `SnippetBundle` (references, cache invalidation), `ReferenceBundle`, `HttpCacheBundle/Cache/CacheManagerInterface.php`.
  - `vendor/sulu/content-bundle` (0.8.x) - `Tests/Application/ExampleTestBundle/*` is the canonical example
    of a custom content-rich entity (entity, dimension content, controller, admin, link/teaser provider,
    XML mapping, templates, services.yaml).

### Rules (non-negotiable)

- Services in `services.yaml` (no XML/PHP service config). Doctrine mapping in **XML** under
  `src/Resources/config/doctrine/`. No mapping attributes on entities.
- Controllers: Symfony `#[Route]` attributes (`type: attribute`), no FOSRest `type: rest` / `@RouteResource`.
- Constructor injection only, `readonly` promoted properties, native types everywhere, `declare(strict_types=1)`.
- Translations: `src/Resources/translations/admin.{de,en}.yaml` (+ `messages.{de,en}.yaml` for website).
- Code comments in English only. SCSS comments only `/* ... */`. Use "-" not long dashes.
- Every new or changed Service, Controller, Helper, Provider, Subscriber gets a PHPUnit test in `tests/Unit/`.
  Run the full suite (`composer test`) and php-cs-fixer before every commit.
- Docs: every feature doc as `docs/<name>.en.md` + `docs/<name>.de.md`; README as `README.md` + `README.de.md`.
- `CHANGELOG.md` updated with concrete entries for every structural change.
- Never add AI co-author trailers to commits. Never commit personal or company data (names, e-mails,
  company-specific config values) into code, docs or config examples.

## 1. Branch and version strategy

1. Create branch `2.x` from `main` (`git checkout -b 2.x origin/main`) and push it. All work of this plan
   lands on `2.x` (via short-lived feature branches / PRs into `2.x`).
2. Releases on `2.x`:
   - **1.9.0** - Milestone A (bug fixes, tests, CI). Backwards compatible, no schema change.
   - **2.0.0** - Milestone B (switch to SuluContentBundle, new features). Schema change + data migration.
   - Bundle major 2 = Sulu 2.6, bundle major 3 = Sulu 3 (later, separate plan).
3. `main` is not touched by this plan. It becomes the Sulu 3 line later.

## 2. Milestone A - bug fixes and test foundation (release 1.9.0)

Goal: a stable, tested 1.x for users who do not want the 2.0 data migration. Keep changes minimal.

### A1 Test and CI foundation

- `require-dev`: `phpunit/phpunit ^10.5`, `phpspec/prophecy-phpunit ^2.4` (or PHPUnit mocks only), drop
  `phpunit ^8`. Migrate `phpunit.xml.dist` to the PHPUnit 10 schema (`<source>` instead of `<filter>`).
- `.github/workflows/`: replace both workflows with one `ci.yml`: matrix PHP 8.2/8.3/8.4, `composer validate`,
  `composer install`, `php-cs-fixer --dry-run`, `vendor/bin/phpunit`. Remove the `.env.test` copy step and
  the broken composer.lock cache key.
- Add `phpstan/phpstan` (level 6 to start) with a baseline if needed, run in CI.

### A2 Bug fixes (each with a regression test)

| # | Bug | File | Fix |
|---|-----|------|-----|
| 1 | Preview crashes: undefined `$this->documentInspector`, nonexistent `setStructureType()`, empty `setValues()` | `src/Preview/AbbreviationsObjectProvider.php` | Return `Abbreviation::SECURITY_CONTEXT` from `getSecurityContext()`; drop `setStructureType`; implement `setValues()` by mapping form data onto the entity (reuse the model's mapping, extracted into a mapper service); serialize via `serialize()` is fine. |
| 2 | Settings resource key mismatch (`abbreviations_settings` vs registered `abbreviations-settings`) | `src/Entity/AbbreviationsSettings.php`, `SuluAbbreviationsExtension::prepend()`, `SettingsController` | Use one key everywhere: `abbreviations_settings`; route name `sulu_abbreviations.get_abbreviations_settings`. Verify the admin settings view loads and saves. |
| 3 | Two content types share alias `abbreviations_selection`; field type option is `abbreviation_selection` | `services.xml`, `Content/AbbreviationsSelectionContentType.php`, `Content/Type/AbbreviationsSelection.php`, extension prepend | Keep one class (`Content/Type/AbbreviationsSelection`), delete the duplicate. Align alias and `field_type_options` to `abbreviation_selection` / `single_abbreviation_selection`. Keep a BC alias only if documented in README. Only return published entities in `getContentData()`, preserve order, set locale. |
| 4 | `copy` action: `Abbreviation::copy()` returns `null`, `save(null)` TypeError | `Entity/Abbreviation.php`, `Entity/Models/AbbreviationModel.php` | Implement `copy()` (clone translations, seo, excerpt; new route path with `-copy` suffix) or remove the `copy` action. Prefer implementing. |
| 5 | Settings tab contains page-only fields (`shadowOn`, webspace fields) that are never mapped; `previewCondition 'nodeType == 1'` never true | `forms/abbreviation_settings.xml`, `Admin/AbbreviationsAdmin.php` | Remove the `<schema>` block and the webspace fields (and `WebspaceSelector` if unused); use `previewCondition 'id != null'` for SEO/excerpt/settings tabs. |
| 6 | Delete toolbar action shown without DELETE permission (form toolbar array is overwritten) | `Admin/AbbreviationsAdmin.php` | Build edit-form toolbar from permissions; publish toggler only with `PermissionTypes::LIVE`. |
| 7 | Duplicate service definitions (repository registered twice, route defaults provider twice) | `services.xml` | Keep one definition each, alias the other id. |
| 8 | Unused constant `AbbreviationsAdmin::SECURITY_CONTEXT = 'sulu.modules.abbreviation'` | `Admin/AbbreviationsAdmin.php` | Remove. |
| 9 | Missing translation `sulu_abbreviation.publish` / unpublish in task handlers | `Automation/*TaskHandler.php`, translations | Use `sulu_abbreviations.publish` / `.unpublish` (exist) and add missing keys de/en. |
| 10 | Twig `sulu_get_abbreviations()` calls `findByFilters([], 0, ...)` (page 0) and ignores published state | `Twig/AbbreviationsTwigExtension.php` | Page 1, published only, locale from request when not given. |
| 11 | Sitemap `getLocaleByHost()` may access undefined index | `Sitemap/AbbreviationsSitemapProvider.php` | Fallback to default locale of the portal; return typed values; implement `countForSitemap()` correctly. |
| 12 | Translation files live in `Resources/config/translations` and are added via `framework.translator.paths` in `prepend()` (also forces `default_locale: en` on the host app) | extension | Move to `src/Resources/translations/` (auto-discovered), remove the `framework` override. |

Release 1.9.0 after A1+A2: update `CHANGELOG.md`, README badge "Sulu 2.6".

## 3. Milestone B - modern architecture on SuluContentBundle (release 2.0.0)

### Why SuluContentBundle 0.8

`sulu/content-bundle` 0.8.x (supports `sulu/sulu ^2.5.6 || ^2.6`) is the Sulu 2 predecessor of Sulu 3's
`Sulu\Content` package. Using it now gives, out of the box:

- real **draft/live separation** (`stage` dimension, Symfony workflow: publish, unpublish, remove draft),
- **copy locale** (`ContentManager::copy()`), ghost locales, available locales,
- SEO, excerpt (categories, tags, icon, image), template, author, route traits,
- `ContentViewBuilderFactory` (standard Sulu tabs + toolbar incl. save-with-publishing dropdown),
- `ContentObjectProvider` (preview), `ContentTeaserProvider` (teaser), `ContentLinkProvider`,
  `ContentSitemapProvider`, `ContentDataProvider` (smart content), `ContentRouteDefaultsProvider`,
  massive-search indexing, automation publish/unpublish task handlers.

The same concepts exist 1:1 in Sulu 3 (`Sulu\Content\...`), so the later Sulu 3 migration becomes mostly
a namespace/API port instead of a second rewrite. Only versioning, references, delete-locale and cache
invalidation must be built on top (section B4-B7).

### B1 Dependencies

- `require`: `php ^8.2`, `sulu/sulu ^2.6`, `sulu/content-bundle ^0.8.3`, `symfony/* ^6.4 | ^7.0`,
  `doctrine/doctrine-bundle ^2.13`, `massive/search-bundle ^2.9`.
- Remove: `manuxi/sulu-shared-tools-bundle`, `phpcr/phpcr-migrations-bundle` (unused).
- `suggest`: `sulu/automation-bundle` (load automation services only if `interface_exists(AutomationTaskHandlerInterface::class)`).
- README install section: register `Sulu\Bundle\ContentBundle\SuluContentBundle` before this bundle.

### B2 Domain model (XML mapping)

```
src/Entity/Abbreviation.php                    implements ContentRichEntityInterface, uses ContentRichEntityTrait
src/Entity/AbbreviationDimensionContent.php    implements DimensionContentInterface, TemplateInterface, RoutableInterface,
                                               SeoInterface, ExcerptInterface, AuthorInterface, WorkflowInterface
                                               (traits from Sulu\Bundle\ContentBundle\Content\Domain\Model)
src/Entity/AbbreviationsSettings.php           unchanged fields, mapping moved to XML
src/Entity/AbbreviationVersion.php             new (see B5)
src/Resources/config/doctrine/*.orm.xml
```

- `Abbreviation`: `uuid` (string, `Uuid::v7()`), `dimensionContents` (OneToMany, cascade all, orphanRemoval).
  Keep an integer-free API: the resource id is the uuid. Constants `RESOURCE_KEY = 'abbreviations'`,
  `TEMPLATE_TYPE = 'abbreviation'`, `SECURITY_CONTEXT = 'sulu.abbreviations.abbreviations'` (unchanged so
  existing role permissions keep working).
- `AbbreviationDimensionContent`: template data holds `name`, `explanation`, `description`, `image`, `link`
  (structure-driven, so the glossary use case can add fields without code). Template type `abbreviation`,
  default template `abbreviation_default` shipped in `src/Resources/config/templates/abbreviations/default.xml`
  (`name` with `sulu.rlp.part`, `url` type `route` with `sulu.rlp`). Register the template directory in
  `prepend()` via `sulu_core.content.structure.paths`.
- `getTitle()` on dimension content returns `name` (needed by teaser, search, activity, trash).
- Route: `sulu_route.mappings` for `Abbreviation::class` with `resource_key` and a schema
  `/{translator.trans("sulu_abbreviations.url_prefix")}/{implode("-", object)}`; generated in admin via
  `sulu.rlp.part`.
- Table names: `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, `abbr_abbreviation_versions`,
  keep `app_abbreviation_settings` (rename optional, then migrate).

### B3 Admin, controller, standard providers

- `AbbreviationsAdmin`: list view (table, locales, `addToolbarActions` add/delete/export by permission) +
  `ContentViewBuilderFactory::createViews(Abbreviation::class, EDIT_TABS_VIEW, ADD_TABS_VIEW, SECURITY_CONTEXT, $toolbarActions)`.
  Build `$toolbarActions` from `getDefaultToolbarActions()` and extend:
  - `edit` dropdown: `sulu_admin.copy_locale` (visible if >1 locale and `!!id`), `sulu_admin.delete_draft`,
    `sulu_admin.set_unpublished` (LIVE permission).
  - `delete` as `DropdownToolbarAction('sulu_admin.delete', 'su-trash-alt', [delete, delete with 'delete_locale' => true])`
    (pattern from `PageAdmin.php:122-142`).
  - Activity + Versions tab (B5), References tab (B6).
- `Controller/Admin/AbbreviationController` (`#[Route('/admin/api')]`, names `sulu_abbreviations.*`):
  `GET /abbreviations` (list), `GET/PUT/DELETE /abbreviations/{id}`, `POST /abbreviations` (create),
  `POST /abbreviations/{id}?action=publish|unpublish|remove-draft|copy-locale`, versions endpoints (B5).
  Use `ContentManagerInterface` (`resolve`, `persist`, `normalize`, `applyTransition`, `copy`, `index`, `deindex`).
  Follow `ExampleController.php` from content-bundle 0.8 closely. Wrap writes in one flush; collect
  domain events (`Domain/Event/*`) for activity; store to trash on delete.
- `routes_admin.yaml`: `type: attribute` resources only.
- Services from content-bundle, configured for the abbreviation resource:
  - Preview: tag `ContentObjectProvider` service with `sulu_preview.object_provider provider-key="abbreviations"`.
  - Teaser: `Teaser/AbbreviationTeaserProvider extends ContentTeaserProvider` (see `ExampleTeaserProvider`),
    tag `sulu.teaser.provider alias="abbreviations"`. **This is the new teaser feature.**
  - Link: `Link/AbbreviationLinkProvider extends ContentLinkProvider`, tag `sulu.link.provider alias="abbreviations"`.
  - Sitemap: `ContentSitemapProvider` configured with `Abbreviation::class`, alias `abbreviations`.
  - Smart content: `ContentDataProvider` + repository for `Abbreviation`, alias `abbreviations`.
  - Route defaults: `ContentRouteDefaultsProvider` handles `Abbreviation::class`; website controller
    renders via template (`sulu_core.content.structure.default_type`) - delete the old
    `Controller/Website/*` once the template-based rendering works.
  - Search: content-bundle search metadata provider; indexes `abbreviations` (draft) and
    `abbreviations_published` (live) kept for BC.
  - Automation: content-bundle `ContentPublishTaskHandler` / `ContentUnpublishTaskHandler` (remove own handlers).
  - Selection field types: `abbreviation_selection`, `single_abbreviation_selection` via
    `sulu_admin.field_type_options` (list overlay on `abbreviations` list key) + content types that load
    **live** dimension contents for the current locale and keep the selection order.
- Trash: `Trash/AbbreviationTrashItemHandler` stores the normalized draft+live dimension contents of all
  locales and restores them via `DoctrineRestoreHelper::persistAndFlushWithId` (keep uuid) + recreate routes.

### B4 Delete single translation

- Admin: delete dropdown with `delete_locale` (B3). The admin then sends `DELETE /abbreviations/{id}?locale=xx&deleteLocale=true`.
- Controller: if `deleteLocale` → `AbbreviationTranslationRemover` service:
  remove draft + live dimension contents of that locale, `removeAvailableLocale()` on the unlocalized
  dimension contents, remove routes of that locale, deindex, collect `AbbreviationTranslationRemovedEvent`
  (activity), invalidate cache (B7). If it was the last locale, behave like a full delete.
- Guard: EDIT permission for that locale (`SecurityCondition` with locale).
- Reference for behaviour: Sulu 3 `packages/snippet/src/Application/MessageHandler/RemoveSnippetTranslationMessageHandler.php`.

### B5 Versioning

SuluContentBundle 0.8 has no versions, so build a small, isolated module that maps cleanly onto Sulu 3's
native versions later:

- Entity `AbbreviationVersion`: `id`, `abbreviation` (ManyToOne, cascade remove), `locale`, `version` (int,
  unix timestamp like Sulu 3), `data` (JSON: `ContentManager::normalize()` of the draft dimension content),
  `created` (DateTimeImmutable), `creator` (User, nullable).
- `Versioning/VersionRecorder`: on the publish transition (listen to the bundle's
  `AbbreviationPublishedEvent` or the Symfony workflow event `workflow.content_workflow.completed.publish` filtered on
  `Abbreviation`) store a version for that locale. Configurable retention `sulu_abbreviations.versioning.max_versions` (default 20, `0` = disabled).
- Endpoints: `GET /abbreviations/{id}/versions?locale=xx` (list, newest first) and
  `POST /abbreviations/{id}/versions/{version}?action=restore&locale=xx` → `ContentManager::persist()` the
  stored data into the **draft** (never directly live), collect `AbbreviationVersionRestoredEvent`.
- Admin: `sulu_admin.resources.abbreviation_versions` with routes `list` + `detail` (the restore route),
  list `src/Resources/config/lists/abbreviation_versions.xml`, tab "Activity / Versions" exactly like
  `PageAdmin.php:320-362` incl. `ListItemAction('restore_version', ['success_view' => EDIT_FORM_VIEW])`
  (the item action is provided by SuluPageBundle in 2.6).

### B6 References (SuluReferenceBundle)

Two directions:

1. **Where is an abbreviation used?** The selection content types implement
   `Sulu\Bundle\ReferenceBundle\Infrastructure\Sulu\ContentType\ReferenceContentTypeInterface::getReferences()`
   and call `$referenceCollector->addReference('abbreviations', $uuid, $propertyPrefix . $property->getName())`
   so pages/snippets referencing abbreviations show up. Text-editor links (`sulu-link provider="abbreviations"`)
   are collected by Sulu core automatically once the link provider alias matches the resource key.
   Admin: add `ReferenceViewBuilderFactory::createReferenceListViewBuilder(...)` tab when
   `hasReferenceListPermission()` (pattern from `SnippetAdmin`).
2. **What does an abbreviation use?** `Reference/AbbreviationReferenceRefresher implements ReferenceRefresherInterface`
   (tag `sulu_reference.refresher`) plus a collector service called after persist/publish that adds
   references to `media` (image, SEO/excerpt images), `contacts` (author), `categories`, `tags`,
   `pages`/`snippets` from template data. Use `ReferenceCollector` like `SnippetReferenceRefresher`.
   Command `bin/console sulu:reference:refresh abbreviations` must work.

### B7 HTTP cache invalidation

- `EventSubscriber/AbbreviationCacheInvalidationSubscriber` (`kernel.event_subscriber`), constructor
  `?CacheManagerInterface $cacheManager` (`sulu_http_cache.cache_manager`, nullable when the cache bundle is
  disabled). On published, unpublished, removed, translation removed, restored:
  `invalidateReference('abbreviations', $uuid)`, plus excerpt tags (`invalidateReference('tag', ...)`) and
  categories (`invalidateReference('category', ...)`) of the live content (pattern from Sulu 3
  `SnippetCacheInvalidationSubscriber`).
- Tagging on the website: content types, smart content provider, twig functions and the detail rendering
  must add `abbreviations`-references to the reference store so tagged pages get purged:
  register `Sulu\Bundle\WebsiteBundle\ReferenceStore\ReferenceStore` with tag `sulu_website.reference_store alias="abbreviations"`, inject it into the `ContentDataProvider` (see example `services.yaml`) and call
  `$referenceStore->add($uuid)` wherever an abbreviation is resolved.
- Test with `SymfonyHttpCache` in a functional test: render page with selection → publish change → response
  is no longer served from cache.

### B8 Data migration 1.x → 2.0

- Command `sulu:abbreviations:migrate-to-content` (idempotent, `--dry-run`):
  for each old `app_abbreviation` + `app_abbreviation_translation` row create `Abbreviation` (new uuid,
  keep mapping old id → uuid in a temporary column/table), draft dimension content per locale
  (template `default`, data from name/explanation/description/image/link, author, authored, seo/excerpt from
  `app_abbreviation_seo*` / `app_abbreviation_excerpt*`), live dimension content when `published = 1`,
  migrate routes (`ro_routes.entity_class/entity_id`), activities/trash keep old ids (document it).
  Rewrite references in page content: `abbreviation_selection` ids → uuids (PHPCR) - provide
  `sulu:abbreviations:migrate-page-references` or document manual step.
- Ship a Doctrine migration class template in `docs/` (projects own their migrations).
- `UPGRADE.md` with step-by-step instructions.

### B9 Remaining items

- `AbbreviationsSettings`: keep singleton controller, port to `#[Route]`, XML mapping, tests.
- Twig: `sulu_resolve_abbreviation(uuid, locale)`, `sulu_get_abbreviations(limit, locale)` return resolved
  live content arrays; new filter `abbr` that wraps known abbreviations in `<abbr title="...">` (optional,
  behind config flag `sulu_abbreviations.twig.auto_abbr`, default false).
- Remove: `Common/DoctrineListRepresentationFactory` (replace by a lean `ListBuilder/` version like the
  EventBundle), `Entity/Models/*`, `Entity/Interfaces/*`, old repositories for seo/excerpt/translation,
  `Admin/Helper/WebspaceSelector`.

## 4. Tests (minimum list)

`tests/Unit/` mirrors `src/`:
- Admin: `AbbreviationsAdminTest` (views, toolbar actions per permission incl. copy/delete locale,
  versions + references tab), `SettingsAdminTest`.
- Entity: `AbbreviationTest`, `AbbreviationDimensionContentTest`, `AbbreviationVersionTest`, `AbbreviationsSettingsTest`.
- Controller: `AbbreviationControllerTest` (each action incl. delete locale, versions, restore; permission checks).
- Services: `AbbreviationTranslationRemoverTest`, `VersionRecorderTest` (retention), `AbbreviationReferenceRefresherTest`,
  reference collection in content types, `AbbreviationCacheInvalidationSubscriberTest` (null cache manager,
  publish/unpublish/remove), `AbbreviationTeaserProviderTest`, `AbbreviationLinkProviderTest`,
  `AbbreviationTrashItemHandlerTest` (store/restore round trip), `AbbreviationsSitemapProviderTest`,
  Twig extensions, `SuluAbbreviationsExtensionTest` (prepend config, automation conditional loading),
  migration command (`--dry-run` output, idempotency) with an in-memory SQLite kernel.
- `tests/Functional/` (optional but recommended): kernel test with `SuluTestKernel` + content-bundle:
  create → publish → website render → unpublish → 404.

## 5. Docs and release

- `README.md` / `README.de.md`: features, install (bundles, routes, search indexes, migrations), upgrade link.
- `docs/` (en+de each): `translations` (copy/delete locale), `publishing`, `versions`, `references`,
  `teaser`, `http-cache`, `smart-content`, `selection-field-types`, `twig`, `settings`, `upgrade-1-to-2`.
- `CHANGELOG.md` with sections per release (1.9.0, 2.0.0).
- Tag `1.9.0` after Milestone A, `2.0.0` after Milestone B (only after tests green in CI).

## 6. Suggested commit sequence

1. CI + PHPUnit 10 + phpstan (A1)
2. One commit per bug fix with its test (A2) → tag 1.9.0
3. Dependencies + XML mapping + new entities + migration command (B1, B2, B8)
4. Admin/controller/content-bundle providers (B3) incl. teaser
5. Delete locale (B4)
6. Versioning (B5)
7. References (B6)
8. Cache invalidation (B7)
9. Cleanup + settings + twig (B9)
10. Docs/README/CHANGELOG/UPGRADE → tag 2.0.0
