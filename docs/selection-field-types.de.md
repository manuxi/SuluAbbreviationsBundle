# Auswahl-Feldtypen

In Seiten-, Snippet- und anderen Templates stehen zwei Feldtypen zur Verfügung:

```xml
<property name="abbreviations" type="abbreviation_selection">
    <meta>
        <title lang="de">Abkürzungen</title>
    </meta>
</property>

<property name="abbreviation" type="single_abbreviation_selection">
    <meta>
        <title lang="de">Abkürzung</title>
    </meta>
</property>
```

- `abbreviation_selection`: Mehrfachauswahl (Listen-Overlay), die Reihenfolge der Auswahl bleibt erhalten.
- `single_abbreviation_selection`: Einzelauswahl (Listen-Overlay oder Auto-Complete).

Gespeichert werden die UUIDs der Abkürzungen. Im Template steht der **normalisierte Live-Inhalt** der aktuellen Sprache zur Verfügung. Abkürzungen, die in der aktuellen Sprache nicht veröffentlicht sind, werden übersprungen (Mehrfachauswahl) bzw. sind `null` (Einzelauswahl).

```twig
{% for abbreviation in content.abbreviations %}
    {{ abbreviation.name }}: {{ abbreviation.explanation }}
{% endfor %}

{% if content.abbreviation %}
    {{ content.abbreviation.name }}
{% endif %}
```

Die Content-Types implementieren `ReferenceContentTypeInterface`, die Seiten erscheinen im Referenzen-Tab der Abkürzungen.
