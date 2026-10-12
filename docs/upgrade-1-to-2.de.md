# Upgrade von 1.x auf 2.0

Version 2.0 speichert den Inhalt mit `sulu/content-bundle` (Entwurf/Live je Sprache, UUIDs als IDs). Die Daten der Version 1.x müssen migriert werden. **Vorher ein Datenbank-Backup erstellen.**

## 1. Code aktualisieren

```console
composer require manuxi/sulu-abbreviations-bundle:^2.0
```

- `Sulu\Bundle\ContentBundle\SuluContentBundle` in `config/bundles.php` **vor** `SuluAbbreviationsBundle` registrieren.
- `manuxi/sulu-shared-tools-bundle` und `phpcr/phpcr-migrations-bundle` werden von diesem Bundle nicht mehr benötigt (im Projekt behalten, falls sie anderweitig genutzt werden).
- Die Konfiguration `sulu_abbreviations.objects` entfernen, falls vorhanden.
- Den Routen-Import in der `routes_admin.yaml` beibehalten (`@SuluAbbreviationsBundle/Resources/config/routes_admin.yaml`).
- Den Website-Suchindex `abbreviations_published` ergänzen (siehe README).

## 2. Neue Tabellen anlegen

Eine Migration mit `bin/console doctrine:migrations:diff` erzeugen oder [templates/doctrine-migration.php.dist](templates/doctrine-migration.php.dist) kopieren (MySQL / MariaDB). Angelegt werden `abbr_abbreviations`, `abbr_abbreviation_dimension_contents`, die zwei Verknüpfungstabellen für Excerpt-Tags / -Kategorien und `abbr_abbreviation_versions`. Die Tabellen der Version 1.x (`app_abbreviation*`) bleiben unverändert.

## 3. Daten migrieren

```console
bin/console sulu:abbreviations:migrate-to-content --dry-run
bin/console sulu:abbreviations:migrate-to-content
```

- Jede Abkürzung erhält eine neue UUID. Die alte ID wird in `abbr_abbreviations.legacy_id` gespeichert.
- Je Sprache wird ein Entwurf (und, falls veröffentlicht, eine Live-Version) aus Name, Erklärung, Beschreibung, Bild, Link, Routen-Pfad, Autor, SEO und Excerpt (Bild, Icon, Tags, Kategorien) erzeugt.
- Die Routen veröffentlichter Übersetzungen werden der neuen ID zugeordnet (fehlende Routen werden angelegt), die Routen unveröffentlichter Übersetzungen werden entfernt.
- Nicht migriert: `showAuthor`, `showDate`, Zeitstempel und Benutzer der alten Datensätze. Aktivitätenprotokoll und Papierkorb behalten die alten Integer-IDs.
- Der Befehl ist idempotent: bereits migrierte Abkürzungen werden übersprungen.

## 4. Referenzen in Seiten und Snippets migrieren

```console
bin/console sulu:abbreviations:migrate-page-references --dry-run
bin/console sulu:abbreviations:migrate-page-references
```

Die Integer-IDs in `abbreviation_selection`- / `single_abbreviation_selection`-Eigenschaften (auch in Blöcken) werden durch die UUIDs ersetzt, `<sulu-link provider="abbreviation" href="5">` und Link-Eigenschaften werden zu `provider="abbreviations"` mit der UUID. Beide PHPCR-Workspaces (Entwurf und Live) werden verarbeitet. Weitere Stellen, an denen IDs gespeichert sind (z. B. eigene Smart-Content-Filter oder fest eingetragene IDs in Templates), müssen manuell angepasst werden.

## 5. Indizes und Referenzen neu aufbauen

```console
bin/console sulu:search:reindex
bin/console sulu:reference:refresh abbreviations
```

## 6. Templates und Code anpassen

- Das Website-Template erhält den normalisierten Inhalt in der Variable `content` (vorher: die Entität `abbreviation`). Auswahl-Felder und Twig-Funktionen liefern Arrays statt Entitäten: `abbreviation.name` funktioniert weiterhin, Methoden wie `abbreviation.getName()` nicht.
- Alias des Link-Providers: `abbreviations` (vorher `abbreviation`).
- Die Toolbar-Aktion `copy` wurde durch "In Sprache kopieren" ersetzt.
- Rollen-Berechtigungen funktionieren weiter: der Sicherheitskontext `sulu.abbreviations.abbreviations` ist unverändert.
- Entitäten, Models und Repositories der Version 1.x wurden entfernt. Stattdessen `ContentManagerInterface` (Service `sulu_content.content_manager`) oder die Twig-Funktionen verwenden.

## 7. Aufräumen

Nach der Kontrolle des Ergebnisses die Tabellen `app_abbreviation`, `app_abbreviation_translation`, `app_abbreviation_seo*`, `app_abbreviation_excerpt*` und optional die Spalte `abbr_abbreviations.legacy_id` entfernen (behalten, falls die Seiten-Migration erneut ausgeführt werden soll).
