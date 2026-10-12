# Twig

## Funktionen

- `sulu_resolve_abbreviation(id, locale = null)`: liefert den normalisierten Live-Inhalt der Abkürzung (siehe unten) oder `null`, wenn sie in der Sprache nicht veröffentlicht ist.
- `sulu_get_abbreviations(limit = 100, locale = null)`: liefert die veröffentlichten Abkürzungen der Sprache, nach Name sortiert.
- `load_abbreviations_settings()`: liefert die [Einstellungen](settings.de.md).

Ohne Sprache wird die Sprache des aktuellen Requests verwendet. Die IDs sind UUIDs.

```twig
{% set abbreviation = sulu_resolve_abbreviation(id) %}
{% if abbreviation %}
    <abbr title="{{ abbreviation.explanation }}">{{ abbreviation.name }}</abbr>
{% endif %}

{% for abbreviation in sulu_get_abbreviations(20) %}
    {{ abbreviation.name }}
{% endfor %}
```

Der Inhalt ist ein Array mit u. a. `id`, `name`, `explanation`, `description`, `image`, `link`, `url`, `locale`, `excerptTitle`, `seoTitle`. Bilder werden über die ID referenziert: `sulu_resolve_media(abbreviation.image.id, app.request.locale)`.

## Filter `abbr` (optional)

Der Filter umschließt jede bekannte Abkürzung eines Textes mit `<abbr title="Erklärung">`. Text innerhalb von `a`, `abbr`, `code`, `pre`, `script` und `style` bleibt unverändert. Standardmäßig ist der Filter deaktiviert:

```yaml
sulu_abbreviations:
    twig:
        auto_abbr: true
```

```twig
{{ content.article|abbr }}
```

## Standard-Template

Die Standard-View `@SuluAbbreviations/abbreviation.html.twig` erweitert `base.html.twig` und rendert den Block `content`. Im Projekt lässt sie sich mit `templates/bundles/SuluAbbreviationsBundle/abbreviation.html.twig` überschreiben; die Variable `content` enthält den normalisierten Live-Inhalt.
