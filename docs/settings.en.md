# Settings

The settings (**Abbreviations > Settings**, security context `sulu.abbreviations.settings`) are a singleton entity (table `app_abbreviation_settings`):

- Display header, default hero and breadcrumbs on the detail page
- The overview page of the abbreviations (used as intermediate page for breadcrumbs)

Use them in templates:

```twig
{% set settings = load_abbreviations_settings() %}
{% if settings.toggleBreadcrumbs %}
    ...
{% endif %}
```

REST: `GET|PUT /admin/api/abbreviations-settings` (route names `sulu_abbreviations.get_abbreviations_settings` and `sulu_abbreviations.put_abbreviations_settings`, resource key `abbreviations_settings`). Changes are written to the activity log.
