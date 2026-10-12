# Plan: Sulu 3 migration (variant A: ContentRichEntity + DimensionContent)

This document is the complete brief for a fresh session. Read it fully before touching code.

## 0. Prerequisites and decisions

- **Start point:** branch `2.x` at release **2.0.0** (see `.claude/plans/sulu-2x-features.md`). At that point
  the bundle already uses `sulu/content-bundle` 0.8 (DimensionContent, draft/live, own versions/references/
  cache invalidation). This plan ports that state to Sulu 3. If 2.0.0 does not exist yet, implement the
  2.x Milestone B concepts directly against the Sulu 3 APIs listed here and migrate data from the 1.x tables
  (section 9, variant "from 1.x").
- **Target:** `sulu/sulu ^3.0` (tested against latest 3.0.x), PHP **^8.2**, Symfony **^6.4 | ^7.0**,
  `doctrine/orm ^2.19 | ^3.0`.
- **Blueprint:** `manuxi/SuluEventBundle` branch `3.x`. Mirror its structure, naming and service layout.
  Read the matching EventBundle file before writing each component.
- **Sulu sources:** `vendor/sulu/sulu/UPGRADE-3.x.md` (mandatory reading: sections "Massive Search Bundle was
  removed", "PHPCR ... removed", "Preview Services changed", "FOSRestRouting Bundle removed", "Upgrade
  resourceLocator and route property type", "Route mapping configuration moved to templates", "Removed
  deprecations for 3.0", "RouteBundle replaced", "Migrate ... DateTimeImmutable", "Introduce Doctrine
  Migrations Bundle", 3.0.5-3.0.9 notes), `vendor/sulu/sulu/packages/{content,route,snippet,article,search}`.
- **Naming decision (made by the maintainer before starting):** keep `SuluAbbreviationsBundle` or rename to
  `SuluGlossaryBundle` (section 1). The rest of the plan uses `{Bundle}` / `{Entity}` / `{key}` placeholders
  from that table.

### Rules (non-negotiable)

- Services in `services.yaml` (split files like EventBundle: `services.yaml`, `services-automation.yaml`, ...).
  Doctrine mapping in **XML** (`src/Resources/config/doctrine/*.orm.xml`). Controllers with `#[Route]`.
- Constructor injection, `readonly` promoted properties, native types, `declare(strict_types=1)`,
  `DateTimeImmutable` for all timestamps.
- Translations in `src/Resources/translations/admin.{de,en}.yaml` and `messages.{de,en}.yaml`.
- Code comments English only. SCSS comments only `/* ... */`. Use "-" not long dashes.
- Generic, reusable admin components (field types, toolbar actions) belong in `manuxi/sulu-admin-extras-bundle`,
  not here. Use its existing components (e.g. `ghost_locale`, `publish_state`) where they fit.
- Every Service/Controller/Helper/Provider/Resolver/Subscriber gets a PHPUnit test in `tests/Unit/`.
  Run `composer test`, php-cs-fixer and phpstan before every commit.
- Feature docs `docs/<name>.en.md` + `docs/<name>.de.md`; `README.md` + `README.de.md`; `CHANGELOG.md` and
  `UPGRADE.md` with concrete entries.
- Never add AI co-author trailers. Never commit personal or company data (names, e-mails, company-specific
  config values such as system collection keys of a customer project).

## 1. Naming

| Item | Keep name | Rename (recommended) |
|------|-----------|----------------------|
| Composer package | `manuxi/sulu-abbreviations-bundle` | `manuxi/sulu-glossary-bundle` |
| Namespace | `Manuxi\SuluAbbreviationsBundle` | `Manuxi\SuluGlossaryBundle` |
| Bundle class / extension alias | `SuluAbbreviationsBundle` / `sulu_abbreviations` | `SuluGlossaryBundle` / `sulu_glossary` |
| `{Entity}` / dimension content | `Abbreviation` / `AbbreviationDimensionContent` | `GlossaryEntry` / `GlossaryEntryDimensionContent` |
| `{key}` resource key / list key | `abbreviations` | `glossary_entries` |
| Template type | `abbreviation` | `glossary_entry` |
| Security context | `sulu.abbreviations.abbreviations` | `sulu.glossary.entries` |
| Tables | `abbr_*` | `glossary_*` |
| Translation prefix | `sulu_abbreviations.` | `sulu_glossary.` |
| Field types | `abbreviation_selection`, `single_abbreviation_selection` | `glossary_entry_selection`, `single_glossary_entry_selection` |

Rename additions (only if renamed):
- New field `type` on the dimension content (unlocalized): `abbreviation | acronym | term` (select, default
  `term`), used by the Twig `<abbr>` helper and as smart content / list filter.
- Repository/package handling: create a new GitHub repository `SuluGlossaryBundle` for the Sulu 3 line
  (start at `1.0.0`), keep this repository for the Sulu 2 line (`2.x`). After the first stable release mark
  `manuxi/sulu-abbreviations-bundle` as abandoned on Packagist with `manuxi/sulu-glossary-bundle` as
  replacement. README of the old package links to the new one.
- Keep name variant: in this repository create branch `3.x` from `2.x` (state 2.0.0), make it the default
  branch, release `3.0.0`.

## 2. Dependencies

- `require`: `php ^8.2`, `sulu/sulu ^3.0`, `doctrine/doctrine-bundle ^2.13`, `symfony/* ^6.4 | ^7.0`.
- Remove: `sulu/content-bundle` (now part of `sulu/sulu` as `Sulu\Content`), `massive/search-bundle`,
  `manuxi/sulu-shared-tools-bundle`, `phpcr/phpcr-migrations-bundle`, `handcraftedinthealps/rest-routing-bundle`.
- `suggest`: `sulu/automation-bundle ^3.0` (load `services-automation.yaml` only if
  `interface_exists(\Sulu\Bundle\AutomationBundle\TaskHandler\AutomationTaskHandlerInterface::class)`),
  `manuxi/sulu-admin-extras-bundle`.
- `require-dev`: `phpunit/phpunit ^10.5 | ^11`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer`,
  `symfony/browser-kit`, `symfony/phpunit-bridge` (no jackalope).

## 3. API mapping (2.x/0.8 → Sulu 3)

| 2.x (content-bundle 0.8 / Sulu 2.6) | Sulu 3 | EventBundle 3.x reference |
|---|---|---|
| `Sulu\Bundle\ContentBundle\Content\Domain\Model\*` | `Sulu\Content\Domain\Model\*` (+ `AuditableInterface`, `TaxonomyInterface`, `LinkInterface`, `version` attribute) | `Entity/Event.php`, `Entity/EventDimensionContent.php` |
| `ContentManagerInterface` | `ContentManagerInterface`, `ContentAggregatorInterface`, `ContentWorkflowInterface` (`sulu_content.content_workflow`), `ContentResolverInterface` | `Controller/Admin/EventController.php`, `TaskHandler/*` |
| `ContentViewBuilderFactory` | `Sulu\Content\Infrastructure\Sulu\Admin\ContentViewBuilderFactory` (incl. versions/insights tab) | `Admin/EventAdmin.php` |
| `ContentObjectProvider` (PreviewObjectProviderInterface) | own provider implementing `PreviewDefaultsProviderInterface` | `Preview/EventObjectProvider.php` |
| `ContentTeaserProvider` | own `TeaserProviderInterface` (`Sulu\Bundle\AdminBundle\Teaser\...`) | `Teaser/EventTeaserProvider.php` |
| `ContentLinkProvider` | own `LinkProviderInterface` (MarkupBundle) | `Link/LinkProvider.php` |
| `ContentSitemapProvider` | own `SitemapProviderInterface` | `Sitemap/EventSitemapProvider.php` |
| `ContentDataProvider` (SmartContent ORM) | `SmartContentProviderInterface` + `SmartContentQueryEnhancer`, resolved through `SmartContentPropertyResolver` | `Content/EventSmartContentProvider.php` |
| `SimpleContentType` selection types (+ `ReferenceContentTypeInterface`) | `PropertyResolverInterface` + `ResourceLoaderInterface` | `Content/PropertyResolver/*`, `Content/ResourceLoader/EventResourceLoader.php` |
| `RouteDefaultsProviderInterface` (RouteBundle), `sulu_route.mappings` | `Sulu\Route\Application\Routing\Matcher\RouteDefaultsProviderInterface`, `Sulu\Route\Domain\Model\Route`; schema via `route_schema` param on the `url` property in the template | `Routing/EventRouteDefaultsProvider.php`, `templates/events/*.xml` |
| Website rendering via `WebsiteController` / content-bundle | `Sulu\Content\UserInterface\Controller\Website\ContentController` (or own controller extending it) | `Controller/Website/EventController.php` |
| Massive search metadata / indexes | SEAL: `ReindexProviderInterface` for admin + website index, subscriber on domain events | `Search/EventAdminSearchProvider.php`, `Search/EventWebsiteSearchProvider.php`, `Search/EventSearchListener.php` |
| Own `AbbreviationVersion` table + endpoints | native versions: dimension content `version` attribute, created on publish by `PublishTransitionSubscriber`; list `{key}_versions`, restore action | `lists/events_versions.xml`, `EventController::getVersionsAction`, `case 'restore'` |
| Own reference collector/refresher (ReferenceBundle 2.6) | `ReferenceDoctrineEventListener` (automatic for dimension contents) + own `ReferenceRefresherInterface` for refresh command | `Reference/EventReferenceRefresher.php` |
| `CacheManager::invalidateReference` + `ReferenceStore` (WebsiteBundle) | `CacheManagerInterface::invalidateTag($uuid)` / `invalidateReference(...)`, `Sulu\Bundle\HttpCacheBundle\ReferenceStore\*`; responses tagged by `DimensionContentTagSubscriber` | Sulu 3 `packages/snippet/src/Infrastructure/Sulu/HttpCache/EventSubscriber/SnippetCacheInvalidationSubscriber.php` |
| Delete translation service | same behaviour, pattern from `RemoveSnippetTranslationMessageHandler` | Sulu 3 `packages/snippet/src/Application/MessageHandler/RemoveSnippetTranslationMessageHandler.php` |
| FOSRest controllers / `type: rest` | `#[Route]`, `routes_admin.yaml` with `type: attribute` | `Resources/config/routes_admin.yaml` |
| Automation handlers (content-bundle) | own `*PublishTaskHandler` / `*UnpublishTaskHandler` using `ContentWorkflowInterface` | `TaskHandler/*`, `services-automation.yaml`, `Admin/EventAutomationAdmin.php` |
| Trash handler | same interfaces (`Sulu\Bundle\TrashBundle\...`), store all dimension contents | `Trash/EventTrashItemHandler.php` |
| `DateTime` timestamps | `DateTimeImmutable` | entities |

## 4. Domain model

- `src/Entity/{Entity}.php`: `ContentRichEntityInterface` + `ContentRichEntityTrait`, `uuid` (Uuid v7 string,
  kept from 2.x), `created`/`changed` immutable.
- `src/Entity/{Entity}DimensionContent.php`: `DimensionContentInterface`, `TemplateInterface`,
  `RoutableInterface`, `SeoInterface`, `ExcerptInterface`, `TaxonomyInterface` (if used), `AuthorInterface`,
  `WorkflowInterface`, `AuditableInterface` with the matching traits; `getResourceKey()` returns `{key}`;
  `getTemplateType()` returns the template type.
- `src/Entity/{Entity}Settings.php` (singleton settings, unchanged fields).
- XML mapping for all entities; `version` column comes from the content trait/metadata loader.
- Drop own `AbbreviationVersion` entity after data migration (section 9).
- Templates: `src/Resources/config/templates/{key}/default.xml` with `name` (`sulu.rlp.part`),
  `explanation`, `description`, `image`, `link`, `url` (type `route`, `mandatory`, `sulu.rlp`,
  param `route_schema`), registered via `prepend()` like EventBundle (`TemplatePathCompilerPass` if needed).

## 5. Features (all from 2.x must stay, now on Sulu 3 APIs)

1. **Admin:** list view + `ContentViewBuilderFactory::createViews()`; toolbar: save-with-publishing dropdown,
   `sulu_admin.copy_locale`, `sulu_admin.delete_draft`, `sulu_admin.set_unpublished`, delete dropdown with
   `delete_locale` (pattern `packages/snippet/src/Infrastructure/Sulu/Admin/SnippetAdmin.php`), settings,
   SEO, excerpt, activity + versions (insights) tab, references tab.
2. **Controller** `Controller/Admin/{Entity}Controller`: list, get (incl. `?version=`), post, put, delete
   (`deleteLocale`), trigger actions `copy_locale`, `publish`, `unpublish`, `remove_draft`, `restore`;
   `GET /{key}/{id}/versions`. Domain events in `Domain/Event/*` (`CreatedEvent`, `ModifiedEvent`,
   `PublishedEvent`, `UnpublishedEvent`, `RemovedEvent`, `RestoredEvent`, `TranslationRemovedEvent`,
   `CopiedLanguageEvent`, `VersionRestoredEvent`) collected via `DomainEventCollectorInterface`.
3. **Draft/live:** `ContentWorkflowInterface::apply()` only; website reads `stage = live` via
   `DimensionContentQueryEnhancer`.
4. **Copy locale / delete locale:** as above; delete locale removes draft+live (+versions) of that locale,
   available locales, routes, search docs, cache.
5. **Versions:** native; list `src/Resources/config/lists/{key}_versions.xml`; restore into draft.
6. **Preview:** `PreviewDefaultsProviderInterface`, provider key `{key}`.
7. **Teaser:** `TeaserProviderInterface`, alias `{key}`, also usable in `teaser_selection`.
8. **Link provider:** alias `{key}` (= resource key, so text-editor links create references).
9. **Selection field types:** `PropertyResolver` (single + multi) + `ResourceLoader` (batch load, live,
   locale, keeps order); `field_type_options` in `src/Resources/config/packages/sulu_admin.yaml` (prepended).
10. **Smart content:** `SmartContentProviderInterface` (filters: categories, tags, type if renamed,
    sorting by name/published date), view link to edit form.
11. **References:** automatic via `ReferenceDoctrineEventListener`; `ReferenceRefresherInterface`
    implementation for `sulu:reference:refresh {key}`; references tab via
    `ReferenceViewBuilderFactoryInterface`.
12. **HTTP cache:** `{Entity}CacheInvalidationSubscriber` on workflow transition + removed + translation
    removed events: `invalidateTag($uuid)`, excerpt tags/categories `invalidateReference`. Verify website
    responses carry the uuid tag (resource loaders / smart content add references).
13. **Search (SEAL):** admin index provider + website index provider + listener on domain events; document
    `cmsig_seal` schema config in README.
14. **Sitemap:** provider alias `{key}`, only live + routable, per webspace/locale.
15. **Routing/website:** `RouteDefaultsProviderInterface` (resource key `{key}`), template-based rendering,
    `Resources/views/{key}/default.html.twig` example, localizations for language switcher.
16. **Trash:** store/restore all dimension contents + routes, keep uuid.
17. **Automation:** publish/unpublish task handlers + automation admin tab (conditional).
18. **Settings singleton + Twig:** `load_{key}_settings()`, `sulu_resolve_{entity}(uuid)`,
    `sulu_get_{key}(limit, locale)`, optional `abbr` filter (wraps known abbreviations in `<abbr title>`,
    config flag, default off).
19. **Optional (EventBundle parity, only if cheap):** MCP tools, fixtures, debug commands.

## 6. Configuration and wiring

- `DependencyInjection/Configuration.php`: `objects` (model/repository overrides), `versioning` (enabled via
  Sulu content config), `twig.auto_abbr`.
- `{Bundle}Extension::prepend()`: `sulu_admin` (lists, forms, resources incl. `{key}_versions`,
  `field_type_options`), `sulu_core.content.structure` paths, `doctrine.orm.mappings` (XML), `cmsig_seal`
  index schema hints only in docs (projects own it), `sulu_search` admin index metadata.
- Routes: `routes_admin.yaml` (attribute), `routes_website.yaml` only if own website endpoints exist.
- JS: only if a bundle-specific admin component is needed (`src/Resources/js/index.js` + `package.json`);
  everything generic goes to SuluAdminExtrasBundle.

## 7. Tests

`tests/Unit/` mirroring `src/` (see EventBundle 3.x `tests/Unit` for structure and mocking style):
Admin (views/toolbars per permission), entities, controller actions (incl. delete locale, versions, restore,
permissions), property resolvers, resource loader, smart content provider, teaser, link, sitemap, preview,
route defaults, search providers + listener, cache invalidation subscriber, trash round trip, reference
refresher, task handlers, twig extensions, extension/prepend config, data migration command.
Optional `tests/Functional/` with a `SuluTestKernel` app: create → publish → render → unpublish → 404 →
restore version.

## 8. Docs

`README.md` + `README.de.md` (features, install incl. bundles, routes, Doctrine migrations, SEAL index config,
permissions), `docs/*.en.md` + `docs/*.de.md` for every feature in section 5, `UPGRADE.md` (2.x → 3.x and
1.x → 3.x), `CHANGELOG.md`.

## 9. Data migration

- **From 2.0 (content-bundle 0.8):** tables are structurally close. Provide a Doctrine migration
  template in `docs/upgrade-2-to-3.*.md` and a command `sulu:{key}:migrate-from-2x`:
  - rename/alter tables to the new names and Sulu 3 columns (`version` default 0, immutable datetimes,
    taxonomy/excerpt columns per Sulu 3 `UPGRADE-3.x.md` "Add new Content storage tables"),
  - copy own `abbr_abbreviation_versions` rows into dimension content rows with `version = <timestamp>`,
    `stage = draft` (denormalize JSON via `ContentManager::persist` on a temporary entity copy, or document
    that old versions are dropped - decide by effort, default: migrate),
  - migrate routes from `ro_routes` (RouteBundle) to the new `Sulu\Route` table (`resource_key`,
    `resource_id`, `locale`, `site`, `slug`),
  - re-index SEAL, refresh references (`sulu:reference:refresh {key}`).
- **From 1.x (classic tables):** same as 2.x plan section B8 but writing Sulu 3 entities directly.
- Page content: selection values stored in page template data must be rewritten if field type names or ids
  changed (rename variant: `abbreviation_selection` → `glossary_entry_selection`); ship a command
  `sulu:{key}:migrate-page-references` operating on the Sulu 3 page/snippet/article dimension content tables.

## 10. Suggested commit sequence

1. Branch/repo setup per section 1, composer deps, CI (PHP 8.2-8.4), phpstan, php-cs-fixer.
2. Entities + XML mapping + templates + extension/prepend.
3. Controller + admin (incl. copy/delete locale, versions, references tab).
4. Website: route defaults, rendering, preview.
5. Selection property resolvers + resource loader, smart content, teaser, link, sitemap.
6. Search (SEAL), cache invalidation, reference refresher.
7. Trash, automation, settings, twig.
8. Data migration commands + UPGRADE docs.
9. README/docs/CHANGELOG, release (`3.0.0` keep name, `1.0.0` new glossary package).
