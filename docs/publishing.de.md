# Veröffentlichen (Entwurf und Live)

Jede Abkürzung besitzt je Sprache eine Version **Entwurf** und eine Version **Live**. Änderungen werden im Entwurf gespeichert, die Website zeigt nur die Live-Version.

## Toolbar

Die Toolbar des Formulars bietet:

- **Speichern** / **Speichern und veröffentlichen** / **Veröffentlichen**: speichert den Entwurf und veröffentlicht ihn optional (der Entwurf wird nach Live kopiert).
- Menü **Bearbeiten**:
  - **Entwurf verwerfen** (`remove-draft`): ersetzt den Entwurf durch die Live-Version.
  - **Veröffentlichung beenden** (`unpublish`): entfernt die Live-Version der aktuellen Sprache. Der Entwurf bleibt erhalten.
  - **In Sprache kopieren**: siehe [Übersetzungen](translations.de.md).
- Menü **Löschen**: die Abkürzung oder nur die aktuelle Übersetzung löschen.

Veröffentlichen, Veröffentlichung beenden und Entwurf verwerfen erfordern die Berechtigung `live` des Sicherheitskontexts `sulu.abbreviations.abbreviations`. Speichern erfordert `edit`, Löschen `delete`.

## Workflow-Status

Der Entwurf kennt seinen Status im Content-Workflow: `unpublished` (nie veröffentlicht), `published` (Live und Entwurf sind gleich), `draft` (veröffentlicht, aber mit unveröffentlichten Änderungen). Der Status wird in der Liste angezeigt.

## Automation

Mit installiertem `sulu/automation-bundle` steht im Bearbeiten-Formular der Tab **Automation** zur Verfügung. Aufgaben zum Veröffentlichen oder Beenden der Veröffentlichung einer Sprache zu einem bestimmten Zeitpunkt stellt das Content-Bundle bereit. Versionen, Referenzen und der HTTP-Cache werden auch bei automatisierten Übergängen aktualisiert.

## Suchindizes

- `abbreviations`: Entwurf (im Admin durchsuchbar)
- `abbreviations_published`: Live (für die Suche auf der Website)

Die Indizes werden beim Speichern, Veröffentlichen und Beenden der Veröffentlichung aktualisiert. Neu aufbauen mit `bin/console sulu:search:reindex`.

## Aktivitäten

Die Ereignisse `created`, `modified`, `removed`, `restored`, `published`, `unpublished`, `draft_removed`, `copied_language`, `translation_removed` und `version_restored` werden im Aktivitätenprotokoll (Tab "Einblicke") protokolliert.
