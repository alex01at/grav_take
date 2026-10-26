# Take

Grav-2-Plugin für Eintragungslisten zu Feiern (Potluck-Prinzip). Architektur
orientiert sich am bewährten Muster des `feuerwerk`-Plugins.

- **Administration**: Feiern (Veranstaltungsname + Datum) werden in der
  Plugin-Konfiguration (Admin → Plugins → Take) als Liste gepflegt – kein
  eigenes Formular nötig. Eingegangene Eintragungen (Name, Mitgebrachtes,
  E-Mail) erscheinen unter dem Menüpunkt **Feiern** in admin2, dort pro
  Feier bearbeitbar (Name/Mitgebrachtes) und löschbar.
- **Frontend**: `[take]` in den Inhalt einer beliebigen Seite schreiben –
  dort erscheinen alle angelegten Feiern jeweils mit bisheriger Liste und
  eigenem Anmeldeformular (Name, E-Mail, "Was bringst du mit?" + "weitere
  Sache"-Button). Keine Registrierung nötig. Name und Mitgebrachtes werden
  öffentlich gelistet, die E-Mail-Adresse bleibt im Frontend unsichtbar
  (nur in admin2 sichtbar).

## Installation

```bash
cd your-grav-site/user/plugins
git clone https://github.com/alex01at/grav_take.git take
```

Voraussetzung: admin2 (`api`-Plugin) für die eigene Verwaltungsseite der
Eintragungen – ohne admin2 funktioniert das Frontend-Formular trotzdem,
nur die Eintragungs-Übersicht im Admin fehlt dann.

Die Eintragungen landen als JSON-Dateien unter `user/data/take/<Feier>/`,
es wird keine Datenbank benötigt.

## Lizenz

MIT
