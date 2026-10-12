# References

The bundle uses the reference module of Sulu (`sulu/sulu` 2.6). The tab **Insights > References** of an abbreviation lists the pages, snippets and other content which reference it.

## Where is an abbreviation used?

- The content types `abbreviation_selection` and `single_abbreviation_selection` report their selection (also inside blocks).
- Links created with the text editor (`provider="abbreviations"`) are collected by Sulu itself.

## What does an abbreviation use?

For the draft (context `admin`) and the live version (context `website`) the references to media (template properties, excerpt image and icon), the author contact, excerpt categories and excerpt tags are stored, so the media, contact, category and tag lists show where they are used. The references are updated on save, publish, unpublish, remove draft, restore of a version, deletion and automated transitions.

## Rebuild

```console
bin/console sulu:reference:refresh abbreviations
```

Run the command in the `admin` context for the draft references and in the `website` context for the live references (like for pages and snippets).
