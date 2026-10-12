# HTTP cache

The bundle tags the responses of the website and purges them when an abbreviation changes. It works with the HTTP cache of Sulu (`sulu_http_cache`, Symfony HttpCache or Varnish); without the cache bundle nothing is purged and nothing breaks.

## Tagging

The reference store `abbreviations` (tag `sulu_website.reference_store`) collects the ids of all abbreviations used while a page is rendered:

- the detail page of an abbreviation (tagged by Sulu with the id of the abbreviation)
- the content types `abbreviation_selection` / `single_abbreviation_selection`
- the Twig functions `sulu_resolve_abbreviation()` / `sulu_get_abbreviations()`
- the smart content data provider `abbreviations`

Responses which contain a **list** of abbreviations (`sulu_get_abbreviations()`, smart content) are additionally tagged with the marker `abbreviations-list`, so a newly published abbreviation also purges these pages.

## Purging

`Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber` purges the tags of the abbreviation, the list marker, the excerpt tags (`tag`) and the excerpt categories (`category`) when an abbreviation is

- published or unpublished (admin and automation; executed after the database flush so the cache is not refilled with old data)
- deleted, restored from the trash or when a translation is deleted

Pages which display abbreviations filtered by excerpt tags or categories are purged through the tag and category references.
