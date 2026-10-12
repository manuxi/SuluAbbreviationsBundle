# Versions

Whenever a locale is published a snapshot of the draft is stored (table `abbr_abbreviation_versions`). The versions are listed in **Insights > Versions** (newest first) with the user who published and the date.

## Restore

The restore button of a version writes the stored content into the **draft** of the locale (never directly into live). The draft can be reviewed and published afterwards. The event `version_restored` is written to the activity log. The `edit` permission for the locale is required.

- `GET /admin/api/abbreviations/{id}/versions?locale=en`
- `POST /admin/api/abbreviations/{id}/versions/{version}?action=restore&locale=en`

The version number is a unix timestamp.

## Configuration

```yaml
# config/packages/sulu_abbreviations.yaml
sulu_abbreviations:
    versioning:
        max_versions: 20  # versions per abbreviation and locale, 0 disables the feature
```

The oldest versions are removed automatically. With `0` no version is recorded and the versions tab is hidden. Versions are removed together with the abbreviation.
