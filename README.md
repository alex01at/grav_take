# Take

Grav-Plugin für Eintragungslisten zu Feiern (Potluck-Prinzip).

- **Administration**: Das Plugin bringt einen eigenen Seitentyp **"Feier"**
  mit. Unter "Seiten" eine neue Seite anlegen und als Typ "Feier" wählen –
  dort gibt es normale Grav-Formularfelder für Datum und eine Liste von
  Einträgen (Name, E-Mail, Mitgebrachtes), die direkt im Seitenformular
  bearbeitet und gelöscht werden können. Kein eigenes Admin-Panel nötig,
  das läuft komplett über die normale Seitenverwaltung (funktioniert daher
  unabhängig von Admin 1 / Admin 2 / Admin Next).
- **Frontend**: über den Shortcode `[take]` (alle Feier-Seiten) oder
  `[take route="/pfad/zur/feier"]` (eine bestimmte Feier) wird je Feier
  eine Liste der bisherigen Einträge sowie ein Anmeldeformular angezeigt.
  Keine Registrierung nötig. Name und Mitgebrachtes werden öffentlich
  gelistet, die E-Mail-Adresse bleibt im Frontend unsichtbar (nur in der
  Seiten-Bearbeitung in der Administration sichtbar). Über
  "+ weitere Sache" lassen sich mehrere Dinge mitbringen.
- Eine Feier-Seite direkt aufgerufen (statt über Shortcode eingebunden)
  zeigt Formular + Liste ebenfalls automatisch an (`templates/feier.html.twig`).
  Das Template geht von einem Theme mit `partials/base.html.twig` und
  Block `content` aus (Standard bei Grav-Themes wie Quark); bei einem
  anderen Theme ggf. anpassen – betrifft nur den direkten Seitenaufruf,
  nicht die Einbindung per Shortcode auf anderen Seiten.

## Installation

Abhängigkeit: `shortcode-core` (über den Grav-Admin per GPM installierbar,
falls noch nicht vorhanden).

```bash
cd your-grav-site/user/plugins
git clone https://github.com/alex01at/grav_take.git take
```

Die Einträge landen in der Frontmatter der jeweiligen Feier-Seite
(`entries`-Feld), es werden keine zusätzlichen Datenbanken/Dateien
benötigt.

## Lizenz

MIT
