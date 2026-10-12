# Selection field types

Two field types are available in page, snippet and other templates:

```xml
<property name="abbreviations" type="abbreviation_selection">
    <meta>
        <title lang="en">Abbreviations</title>
    </meta>
</property>

<property name="abbreviation" type="single_abbreviation_selection">
    <meta>
        <title lang="en">Abbreviation</title>
    </meta>
</property>
```

- `abbreviation_selection`: multiple selection (list overlay), the order of the selection is kept.
- `single_abbreviation_selection`: single selection (list overlay or auto complete).

The stored value are the UUIDs of the abbreviations. In the template the **normalized live content** of the current locale is available. Abbreviations which are not published in the current locale are skipped (multiple selection) or `null` (single selection).

```twig
{% for abbreviation in content.abbreviations %}
    {{ abbreviation.name }}: {{ abbreviation.explanation }}
{% endfor %}

{% if content.abbreviation %}
    {{ content.abbreviation.name }}
{% endif %}
```

The content types implement `ReferenceContentTypeInterface`, the pages show up in the references tab of the abbreviations.
