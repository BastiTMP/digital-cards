# Digital Cards

WordPress-Plugins für digitale Mitarbeiter-Visitenkarten mit VCard, QR-Code, PWA/Homescreen, WhatsApp-Teilen und markenspezifischer Darstellung.

## Pakete

- `moeller-cards.zip` – Möller Mobility
- `tmp-cards.zip` – This Moment Pictures
- `mdex-cards.zip` – mdexpertise

Die Kontaktdaten, Logos, Porträts und Links werden pro WordPress-Installation gespeichert und bei Updates nicht überschrieben. Bilder werden über die WordPress-Mediathek gewählt und nicht im Repository veröffentlicht. Weitere Mitarbeiterkarten lassen sich im WordPress-Backend anlegen.

## Releases und Updates

Ein Tag im Format `vX.Y.Z` erzeugt automatisch ein GitHub Release mit allen drei installierbaren ZIP-Dateien. Die Plugins prüfen das neueste öffentliche Release höchstens alle sechs Stunden und bieten verfügbare Versionen über die normale WordPress-Pluginverwaltung an.

## Verzeichnisstruktur


    plugins/
      moeller-cards/
      tmp-cards/
      mdex-cards/

## Datenschutz und Indexierung

Die Karten werden mit `noindex, follow` und einem zusätzlichen `X-Robots-Tag` ausgeliefert. Es werden keine Zugangsdaten oder geheimen Schlüssel im Repository gespeichert.
