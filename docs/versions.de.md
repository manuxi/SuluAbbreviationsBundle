# Versionen

Bei jeder Veröffentlichung einer Sprache wird eine Momentaufnahme des Entwurfs gespeichert (Tabelle `abbr_abbreviation_versions`). Die Versionen werden unter **Einblicke > Versionen** (neueste zuerst) mit dem veröffentlichenden Benutzer und dem Datum aufgelistet.

## Wiederherstellen

Der Wiederherstellen-Button einer Version schreibt den gespeicherten Inhalt in den **Entwurf** der Sprache (nie direkt nach Live). Der Entwurf kann anschließend geprüft und veröffentlicht werden. Das Ereignis `version_restored` wird im Aktivitätenprotokoll festgehalten. Benötigt wird die Berechtigung `edit` für die Sprache.

- `GET /admin/api/abbreviations/{id}/versions?locale=en`
- `POST /admin/api/abbreviations/{id}/versions/{version}?action=restore&locale=en`

Die Versionsnummer ist ein Unix-Zeitstempel.

## Konfiguration

```yaml
# config/packages/sulu_abbreviations.yaml
sulu_abbreviations:
    versioning:
        max_versions: 20  # Versionen je Abkürzung und Sprache, 0 deaktiviert die Funktion
```

Die ältesten Versionen werden automatisch entfernt. Bei `0` wird keine Version aufgezeichnet und der Tab "Versionen" ausgeblendet. Versionen werden zusammen mit der Abkürzung gelöscht.
