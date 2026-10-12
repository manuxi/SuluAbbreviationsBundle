# Publishing (draft and live)

Every abbreviation has a **draft** and a **live** version per locale. Changes are saved into the draft, the website only shows the live version.

## Toolbar

The form toolbar offers:

- **Save** / **Save and publish** / **Publish**: saves the draft, optionally publishes it (copies the draft to live).
- **Edit** menu:
  - **Remove draft** (`remove-draft`): replaces the draft with the live version.
  - **Unpublish** (`unpublish`): removes the live version of the current locale. The draft is kept.
  - **Copy to locale**: see [translations](translations.en.md).
- **Delete** menu: delete the abbreviation or only the current translation.

Publishing, unpublishing and removing a draft require the `live` permission of the security context `sulu.abbreviations.abbreviations`. Saving requires `edit`, deleting `delete`.

## Workflow places

The draft knows its place in the content workflow: `unpublished` (never published), `published` (live and draft are equal), `draft` (published with unpublished changes). The place is shown in the list.

## Automation

With `sulu/automation-bundle` installed an **Automation** tab is available in the edit form. Tasks to publish or unpublish a locale at a given date are provided by the content bundle. Versions, references and the HTTP cache are updated for automated transitions as well.

## Search indexes

- `abbreviations`: draft content (searchable in the admin)
- `abbreviations_published`: live content (use it for the website search)

The indexes are updated on save, publish and unpublish. Rebuild them with `bin/console sulu:search:reindex`.

## Activity log

The events `created`, `modified`, `removed`, `restored`, `published`, `unpublished`, `draft_removed`, `copied_language`, `translation_removed` and `version_restored` are written to the activity log (insights tab).
