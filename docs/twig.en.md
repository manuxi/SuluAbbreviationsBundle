# Twig

## Functions

- `sulu_resolve_abbreviation(id, locale = null)`: returns the normalized live content of the abbreviation (see below) or `null` if it is not published in the locale.
- `sulu_get_abbreviations(limit = 100, locale = null)`: returns the published abbreviations of the locale, ordered by name.
- `load_abbreviations_settings()`: returns the [settings](settings.en.md).

Without a locale the locale of the current request is used. The ids are UUIDs.

```twig
{% set abbreviation = sulu_resolve_abbreviation(id) %}
{% if abbreviation %}
    <abbr title="{{ abbreviation.explanation }}">{{ abbreviation.name }}</abbr>
{% endif %}

{% for abbreviation in sulu_get_abbreviations(20) %}
    {{ abbreviation.name }}
{% endfor %}
```

The content is an array with, among others, `id`, `name`, `explanation`, `description`, `image`, `link`, `url`, `locale`, `excerptTitle`, `seoTitle`. Images are referenced by id: `sulu_resolve_media(abbreviation.image.id, app.request.locale)`.

## Filter `abbr` (optional)

The filter wraps every known abbreviation of a text in `<abbr title="explanation">`. Text inside `a`, `abbr`, `code`, `pre`, `script` and `style` is not changed. It is disabled by default:

```yaml
sulu_abbreviations:
    twig:
        auto_abbr: true
```

```twig
{{ content.article|abbr }}
```

## Default template

The default view `@SuluAbbreviations/abbreviation.html.twig` extends `base.html.twig` and renders the block `content`. Override it in your project with `templates/bundles/SuluAbbreviationsBundle/abbreviation.html.twig`; the variable `content` contains the normalized live content.
