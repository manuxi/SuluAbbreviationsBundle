# SuluAbbreviationsBundle!
![CI](https://github.com/manuxi/SuluAbbreviationsBundle/actions/workflows/ci.yml/badge.svg?branch=2.x)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://github.com/manuxi/SuluAbbreviationsBundle/LICENSE)
![GitHub Tag](https://img.shields.io/github/v/tag/manuxi/SuluAbbreviationsBundle)
![Supports Sulu 2.6](https://img.shields.io/badge/%20Sulu-2.6-0088cc?color=00b2df)
![PHP 8.2+](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4)

[English version](README.md)

Mit diesem Bundle lassen sich Abkürzungen in Sulu-Projekten verwalten.

Das Bundle enthält
- Mehrere Filter für den Abkürzungs-Content-Type
- Link Provider
- Sitemap Provider
- Handler für den Papierkorb
- Handler für die Automation
- Möglichkeit, einen Kontakt als Autor zuzuweisen
- Twig-Extension zum Auflösen einzelner Abkürzungen bzw. für eine Liste von Abkürzungen
- Events für die Aktivitäten-Anzeige
- Suchindizes
    - werden bei jeder Änderung der Entität aktualisiert
    - Unterscheidung zwischen veröffentlicht und Entwurf

und mehr...

Die Abkürzungen sind übersetzbar.

Feature-Wünsche sind jederzeit willkommen.
Das Bundle befindet sich weiterhin in Entwicklung. Nutzung auf eigene Gefahr 🤞🏻

![image](https://github.com/user-attachments/assets/fbd68da1-710d-436c-bee2-9f83a7a8ca32)

## 👩🏻‍🏭 Installation
Paket installieren:
```console
composer require manuxi/sulu-abbreviations-bundle
```
Ohne Symfony Flex muss das Bundle zusätzlich in `config/bundles.php` registriert werden:

```php
return [
    //...
    Manuxi\SuluAbbreviationsBundle\SuluAbbreviationsBundle::class => ['all' => true],
];
```
Folgendes in die `routes_admin.yaml` eintragen:
```yaml
SuluAbbreviationsBundle:
    resource: '@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml'
```
Den Index in der `sulu_search.yaml` ergänzen:

"abbreviations_published" hinzufügen!

"abbreviations_published" ist der Index der veröffentlichten, "abbreviations" der Index der unveröffentlichten Einträge. Beide Indizes sind im Admin durchsuchbar.
```yaml
sulu_search:
    website:
        indexes:
            - abbreviations_published
            - ...
```
Zum Schluss muss das Datenbankschema aktualisiert werden.

Es werden einige Tabellen angelegt (Präfix app_):
abbreviations, abbreviations_translation.

Die nötigen Queries anzeigen mit
```
php bin/console doctrine:schema:update --dump-sql
```
Schema aktualisieren mit
```
php bin/console doctrine:schema:update --force
```

Dabei nur die Schema-Änderungen dieses Bundles ausführen!

## 🎣 Verwendung
Zuerst die Berechtigungen für Abkürzungen vergeben.
Nach einem Neuladen erscheint der Menüpunkt für Abkürzungen in der Navigation.
Danach können Abkürzungen angelegt werden.
Für eine Liste von Abkürzungen den Property-Typ smart_content verwenden, z.B.:
```xml
<property name="abbreviations" type="smart_content">
    <meta>
        <title lang="en">Abbreviations</title>
        <title lang="de">Abkürzungen</title>
    </meta>
    <params>
        <param name="provider" value="abbreviations"/>
        <param name="max_per_page" value="5"/>
        <param name="page_parameter" value="page"/>
    </params>
</property>
```
Zur Auswahl einzelner Abkürzungen in einem Template die Feldtypen `abbreviation_selection` (mehrere) oder `single_abbreviation_selection` (eine) verwenden.
Es werden nur in der aktuellen Sprache veröffentlichte Abkürzungen in der Reihenfolge der Auswahl geliefert:
```xml
<property name="abbreviations" type="abbreviation_selection">
    <meta>
        <title lang="en">Abbreviations</title>
        <title lang="de">Abkürzungen</title>
    </meta>
</property>
```

Beispiel für das zugehörige Twig-Template der Liste:
```html
{% for abbreviation in abbreviations %}
    <div class="col">
        <h2>
            {{ abbreviation.name }}
        </h2>
        <p>
            {{ abbreviation.explanation|raw }}
        </p>
    </div>
{% endfor %}
```

### Twig-Funktionen
- `sulu_resolve_abbreviation(id, locale = null)`: liefert die Abkürzung, wenn sie in der Sprache veröffentlicht ist, sonst `null`.
- `sulu_get_abbreviations(limit = 100, locale = null)`: liefert die veröffentlichten Abkürzungen der Sprache.

Ohne Angabe einer Sprache wird die Sprache des aktuellen Requests verwendet.

## 🧶 Konfiguration
Das Bundle enthält Einstellungen für folgende Punkte:
- Einstellungen für die Detailansicht - Schalter für Header, Standard-Hero-Snippet und Breadcrumbs
- Zwischenseiten für Breadcrumbs: legt die Zwischenseiten für die Breadcrumbs fest

## ⬆️ Upgrade auf 1.9
- Benötigt PHP 8.2 und Sulu 2.6.
- Templates müssen den Property-Typ `abbreviation_selection` verwenden (der bisherige Backend-Alias `abbreviations_selection` wurde entfernt).
- Die Übersetzungen liegen jetzt in `src/Resources/translations/`. Das Bundle setzt `framework.default_locale` der Anwendung nicht mehr.
- Veröffentlichen und Zurückziehen erfordert die Berechtigung `live`.

Alle Änderungen stehen in der [CHANGELOG.md](CHANGELOG.md).

## 👩‍🍳 Mitwirken
Der Einfachheit halber wurde die Erweiterung klein gehalten.
Issues und Pull Requests sind jederzeit willkommen. Über Feedback zur Verbesserung freue ich mich :).
