# HTTP-Cache

Das Bundle versieht die Antworten der Website mit Tags und leert sie, sobald sich eine Abkürzung ändert. Es arbeitet mit dem HTTP-Cache von Sulu (`sulu_http_cache`, Symfony HttpCache oder Varnish); ohne das Cache-Bundle wird nichts geleert und nichts geht kaputt.

## Tagging

Der Reference-Store `abbreviations` (Tag `sulu_website.reference_store`) sammelt beim Rendern einer Seite die IDs aller verwendeten Abkürzungen:

- die Detailseite einer Abkürzung (von Sulu mit der ID der Abkürzung getaggt)
- die Content-Types `abbreviation_selection` / `single_abbreviation_selection`
- die Twig-Funktionen `sulu_resolve_abbreviation()` / `sulu_get_abbreviations()`
- der Smart-Content-Data-Provider `abbreviations`

Antworten, die eine **Liste** von Abkürzungen enthalten (`sulu_get_abbreviations()`, Smart Content), erhalten zusätzlich die Markierung `abbreviations-list`, damit auch eine neu veröffentlichte Abkürzung diese Seiten leert.

## Leeren

`Manuxi\SuluAbbreviationsBundle\EventSubscriber\AbbreviationCacheInvalidationSubscriber` leert die Tags der Abkürzung, die Listen-Markierung, die Excerpt-Tags (`tag`) und die Excerpt-Kategorien (`category`), wenn eine Abkürzung

- veröffentlicht oder die Veröffentlichung beendet wird (Admin und Automation; nach dem Datenbank-Flush, damit der Cache nicht mit alten Daten gefüllt wird)
- gelöscht, aus dem Papierkorb wiederhergestellt oder eine Übersetzung gelöscht wird

Seiten, die Abkürzungen nach Excerpt-Tags oder -Kategorien gefiltert anzeigen, werden über die Tag- und Kategorie-Referenzen geleert.
