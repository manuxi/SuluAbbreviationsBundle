# Referenzen

Das Bundle nutzt das Referenz-Modul von Sulu (`sulu/sulu` 2.6). Der Tab **Einblicke > Referenzen** einer Abkürzung listet die Seiten, Snippets und weitere Inhalte, die auf sie verweisen.

## Wo wird eine Abkürzung verwendet?

- Die Content-Types `abbreviation_selection` und `single_abbreviation_selection` melden ihre Auswahl (auch innerhalb von Blöcken).
- Mit dem Texteditor erzeugte Links (`provider="abbreviations"`) erfasst Sulu selbst.

## Was verwendet eine Abkürzung?

Für den Entwurf (Kontext `admin`) und die Live-Version (Kontext `website`) werden die Referenzen auf Medien (Template-Eigenschaften, Excerpt-Bild und -Icon), den Autor-Kontakt, Excerpt-Kategorien und Excerpt-Tags gespeichert. In den Listen der Medien, Kontakte, Kategorien und Tags ist damit sichtbar, wo sie verwendet werden. Die Referenzen werden beim Speichern, Veröffentlichen, Beenden der Veröffentlichung, Verwerfen des Entwurfs, Wiederherstellen einer Version, Löschen und bei automatisierten Übergängen aktualisiert.

## Neu aufbauen

```console
bin/console sulu:reference:refresh abbreviations
```

Den Befehl im Kontext `admin` für die Entwurfs-Referenzen und im Kontext `website` für die Live-Referenzen ausführen (wie bei Seiten und Snippets).
