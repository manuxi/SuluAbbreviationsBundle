# Smart content

Use the smart content provider `abbreviations` to display lists of abbreviations:

```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="en">Abbreviations</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```

Filters: excerpt tags and categories, limit, pagination, present as and sorting by the publish date. Only the live content of the current locale is returned.

```twig
{% for abbreviation in abbreviations %}
    <h2>{{ abbreviation.name }}</h2>
    <p>{{ abbreviation.explanation }}</p>
{% endfor %}
```

The items are the normalized content: `id`, `name`, `explanation`, `description`, `image` (`{id: ...}`), `link`, `url`, `excerptTitle`, `seoTitle` etc. The ids are UUIDs.
