# Übersetzungen

Der Inhalt einer Abkürzung wird je Sprache gespeichert. Die Sprache wird in der Toolbar des Formulars gewählt.

## In Sprache kopieren

**Bearbeiten > In Sprache kopieren** (nur sichtbar, wenn mehr als eine Sprache konfiguriert und die Abkürzung gespeichert ist) kopiert den Entwurf der aktuellen Sprache in eine oder mehrere andere Sprachen. Vorhandene Entwürfe der Zielsprachen werden überschrieben. Für jede Zielsprache wird die Berechtigung `edit` benötigt.

`POST /admin/api/abbreviations/{id}?action=copy-locale&locale=en&src=en&dest=de,fr`

## Einzelne Übersetzung löschen

**Löschen > Übersetzung löschen** entfernt nur die aktuelle Sprache:

- Entwurf und Live-Inhalt der Sprache
- die Route der Sprache (inklusive Routen-Historie)
- die Suchdokumente und Referenzen der Sprache
- die Sprache wird aus den verfügbaren Sprachen entfernt; war es die Standard-Sprache ("Ghost"-Locale), übernimmt eine andere Sprache

`DELETE /admin/api/abbreviations/{id}?locale=de&deleteLocale=true`

Benötigt wird die Berechtigung `edit` für die Sprache (und `delete`). Ist die Sprache die **letzte** Sprache der Abkürzung, wird die gesamte Abkürzung gelöscht und im Papierkorb abgelegt, genau wie bei **Löschen**. Das Ereignis `translation_removed` wird im Aktivitätenprotokoll festgehalten und der HTTP-Cache geleert.

Die Versionen der Sprache bleiben erhalten: in die gelöschte Sprache wechseln und im Tab "Versionen" eine Version wiederherstellen, um die Übersetzung als Entwurf zurückzuholen. Vollständig gelöschte Abkürzungen lassen sich aus dem Papierkorb wiederherstellen.
