# Teaser

Abbreviations can be selected in the teaser content type of Sulu (provider `abbreviations`). The teaser provider `Manuxi\SuluAbbreviationsBundle\Teaser\AbbreviationTeaserProvider` is registered with the tag `sulu.teaser.provider` (alias `abbreviations`).

```xml
<property name="teasers" type="teaser_selection">
    <meta>
        <title lang="en">Teasers</title>
    </meta>
</property>
```

Only abbreviations published in the current locale are returned. The teaser contains

- **title**: the excerpt title, otherwise the name of the abbreviation
- **description**: the excerpt description, otherwise the explanation (plain text)
- **url**: the url of the abbreviation
- **media**: the excerpt image, otherwise the image of the abbreviation
- **more text**: the excerpt "more" text

To change the content of the teaser extend the provider and override `getTitle()`, `getDescription()`, `getMoreText()` or `getMediaId()`.
