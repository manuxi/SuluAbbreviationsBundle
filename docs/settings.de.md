# Einstellungen

Die Einstellungen (**Abkürzungen > Einstellungen**, Sicherheitskontext `sulu.abbreviations.settings`) sind eine Singleton-Entität (Tabelle `app_abbreviation_settings`):

- Anzeige von Header, Standard-Hero und Breadcrumbs auf der Detailseite
- Die Übersichtsseite der Abkürzungen (Zwischenseite für Breadcrumbs)

Verwendung in Templates:

```twig
{% set settings = load_abbreviations_settings() %}
{% if settings.toggleBreadcrumbs %}
    ...
{% endif %}
```

REST: `GET|PUT /admin/api/abbreviations-settings` (Routen `sulu_abbreviations.get_abbreviations_settings` und `sulu_abbreviations.put_abbreviations_settings`, Resource-Key `abbreviations_settings`). Änderungen werden im Aktivitätenprotokoll festgehalten.
