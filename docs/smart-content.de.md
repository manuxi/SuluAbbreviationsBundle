# Smart Content

Mit dem Smart-Content-Provider `abbreviations` lassen sich Listen von Abkürzungen anzeigen:

```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="de">Abkürzungen</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```

Filter: Excerpt-Tags und -Kategorien, Limit, Paginierung, Darstellung und Sortierung nach Veröffentlichungsdatum. Es wird nur der Live-Inhalt der aktuellen Sprache geliefert.

```twig
{% for abbreviation in abbreviations %}
    <h2>{{ abbreviation.name }}</h2>
    <p>{{ abbreviation.explanation }}</p>
{% endfor %}
```

Die Einträge sind der normalisierte Inhalt: `id`, `name`, `explanation`, `description`, `image` (`{id: ...}`), `link`, `url`, `excerptTitle`, `seoTitle` usw. Die IDs sind UUIDs.
