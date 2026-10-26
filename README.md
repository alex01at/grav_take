# Take

Grav-Plugin für Eintragungslisten zu Feiern (Potluck-Prinzip).

- **Administration**: unter "Feiern" im Admin-Menü Veranstaltungen (Name +
  Datum) anlegen, bearbeiten und löschen. Einzelne Einträge (Name +
  Mitgebrachtes) sind dort ebenfalls bearbeitbar/löschbar.
- **Frontend**: über den Shortcode `[take]` (alle Feiern) oder
  `[take slug="<slug>"]` (eine bestimmte Feier) wird je Feier eine Liste
  der bisherigen Einträge sowie ein Anmeldeformular angezeigt. Keine
  Registrierung nötig. Name und Mitgebrachtes werden öffentlich gelistet,
  die E-Mail-Adresse bleibt anonym (nur in der Administration sichtbar).
  Über "+ weitere Sache" lassen sich mehrere Dinge mitbringen.

## Installation

Abhängigkeiten: `admin`, `shortcode-core` (beide über den Grav-Admin per
GPM installierbar, falls noch nicht vorhanden).

```bash
cd your-grav-site/user/plugins
git clone https://github.com/alex01at/grav_take.git take
```

Die Daten werden pro Feier als YAML-Datei unter `user/data/take/events/`
abgelegt.

## Lizenz

MIT
