# Translations

The content of an abbreviation is stored per locale. The locale is selected in the toolbar of the form.

## Copy to locale

**Edit > Copy to locale** (only visible if more than one locale is configured and the abbreviation was saved) copies the draft of the current locale into one or more other locales. Existing drafts of the target locales are overwritten. The `edit` permission is required for every target locale.

`POST /admin/api/abbreviations/{id}?action=copy-locale&locale=en&src=en&dest=de,fr`

## Delete a single translation

**Delete > Delete translation** removes only the current locale:

- draft and live content of the locale
- the route of the locale (including the route history)
- the search documents and references of the locale
- the locale is removed from the available locales; if it was the default ("ghost") locale another locale takes over

`DELETE /admin/api/abbreviations/{id}?locale=de&deleteLocale=true`

The `edit` permission for the locale is required (and `delete`). If the locale is the **last** locale of the abbreviation the whole abbreviation is deleted and stored in the trash, the same as with **Delete**. The event `translation_removed` is written to the activity log and the HTTP cache is purged.

The versions of the locale are kept: switch to the deleted locale and restore a version in the versions tab to bring the translation back as a draft. Entire abbreviations can be restored from the trash.
