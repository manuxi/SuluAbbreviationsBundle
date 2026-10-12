# Teaser

Abkürzungen können im Teaser-Content-Type von Sulu ausgewählt werden (Provider `abbreviations`). Der Teaser-Provider `Manuxi\SuluAbbreviationsBundle\Teaser\AbbreviationTeaserProvider` ist mit dem Tag `sulu.teaser.provider` (Alias `abbreviations`) registriert.

```xml
<property name="teasers" type="teaser_selection">
    <meta>
        <title lang="de">Teaser</title>
    </meta>
</property>
```

Es werden nur Abkürzungen geliefert, die in der aktuellen Sprache veröffentlicht sind. Der Teaser enthält

- **Titel**: den Excerpt-Titel, sonst den Namen der Abkürzung
- **Beschreibung**: die Excerpt-Beschreibung, sonst die Erklärung (reiner Text)
- **URL**: die URL der Abkürzung
- **Medium**: das Excerpt-Bild, sonst das Bild der Abkürzung
- **Mehr-Text**: den "Mehr"-Text des Excerpts

Um den Inhalt des Teasers zu ändern, den Provider erweitern und `getTitle()`, `getDescription()`, `getMoreText()` oder `getMediaId()` überschreiben.
