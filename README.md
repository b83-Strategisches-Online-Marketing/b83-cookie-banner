# b83 Cookie Banner

Cookie-Banner für WordPress mit Fokus auf DSGVO, österreichischem TKG 2021 und den Leitlinien der europäischen Datenschutzbehörden.

Entwickelt von [b83 – Strategisches Online Marketing](https://b83.at), Wolfgang Grabs-Schrempf, office@b83.at.

- **Version:** 7.1.0
- **Voraussetzungen:** WordPress 6.2+, PHP 8.1+
- **Lizenz:** GPL-2.0-or-later

> Der Banner unterstützt eine rechtskonforme Umsetzung, ersetzt aber keine Rechtsberatung. Die Datenschutzerklärung muss die eingesetzten Dienste ebenfalls nennen.

## Funktionen

- **Zwei Ebenen:** Erste Ebene mit „Alle akzeptieren“, „Alle ablehnen“ und „Einstellungen“ – Zustimmen und Ablehnen gleich gestaltet. Zweite Ebene mit Kategorien und einzelnen Diensten.
- **Informierte Einwilligung:** Je Dienst Anbieter, Zweck, Cookies/Speicherdauer, Drittland und Datenschutzlink.
- **Dienste-Katalog:** Google Tag Manager, Google Analytics, Meta Pixel, YouTube, Vimeo, Google Maps – plus eigene Dienste (z. B. Wetter-Widgets) mit Skript-Code und Domains zum Blockieren.
- **Google Consent Mode v2:** Standardmäßig alles verweigert, Aktualisierung nach Zustimmung.
- **Blockieren eingebetteter Inhalte** (YouTube, Vimeo, Google Maps, eigene Domains) in Beiträgen, oEmbeds, Widgets und allen Elementor-Widgets – mit Platzhalter und Zustimmung je Dienst. YouTube wird über youtube-nocookie.com, Vimeo mit „Do Not Track“ geladen.
- **Widerruf** jederzeit über ein Symbol oder den Shortcode; abgewählte Dienste werden sofort gestoppt, ihre Cookies gelöscht.
- **Einwilligungsprotokoll** in eigener Tabelle: Einwilligungs-ID, Zeitpunkt, Auswahl, Banner-Version, Sprache, Seite – keine IP-Adresse. CSV-Export, Suche nach ID, automatische Löschung nach einstellbarer Frist.
- **Cache-sicher:** Funktioniert mit WP Rocket und anderen Seiten-Caches; Ausnahmen für WP Rocket werden automatisch gesetzt, der Seiten-Cache wird nach dem Speichern geleert.
- **Deutsch und Englisch:** Englische Texte für Seiten in englischer Sprache (WPML, Polylang, TranslatePress).
- **Gestaltung** im Tab „Design“ mit Live-Vorschau: Farben, Rahmen, Abrundungen, Schatten, Breite und Abstände für Box und Buttons, inkl. Hover-Farben und Kontrastwarnung.
- **Neue Abfrage** aller Besucher per Knopfdruck, wenn sich Dienste oder Zwecke ändern.

## Shortcodes

```
[b83_cookie_settings]Cookie-Einstellungen[/b83_cookie_settings]   Einstellungen erneut öffnen
[b83_youtube url="https://youtu.be/…" title="…"]                   YouTube-Video mit Platzhalter
```

## Google Tag Manager

- Wird erst geladen, wenn der Besucher mindestens einem Statistik- oder Marketing-Dienst zustimmt.
- Im Tag Manager bei jedem Tag die Einwilligungsprüfung aktivieren (Google Analytics: `analytics_storage`, Werbung: `ad_storage`).
- Ereignis `b83_consent` mit Datenschicht-Variable `b83_services` (Liste der erlaubten Dienste) steht als Auslöser zur Verfügung.

## Umstieg von Version 6.x

Beim Update werden Einstellungen und Protokoll automatisch übernommen:

- GTM-ID → Dienst „Google Tag Manager“; „Google Analytics“ wird als über GTM laufend angenommen
- Statistik-/Marketing-Code → eigene Dienste (Angaben zu Anbieter und Zweck bitte ergänzen)
- Banner-Version wird erhöht: Alle Besucher werden einmal neu gefragt, weil die bisherigen Einwilligungen nicht alle Pflichtangaben enthielten
- Altes Protokoll wird in die neue Tabelle übernommen

Danach im Backend unter *b83 → Cookie Banner → Dienste* prüfen und speichern.

## Erweitern

| Hook | Typ | Zweck |
|---|---|---|
| `b83_cookie_banner_catalog` | Filter | Dienste-Katalog erweitern oder anpassen |
| `b83_cookie_banner_text` | Filter | Einzelne Oberflächentexte ändern |
| `b83_cookie_banner_locale` | Filter | Sprache bestimmen |
| `b83_cookie_banner_settings_saved` | Action | Nach dem Speichern |
| `b83_cookie_banner_loaded` | Action | Plugin geladen |
| `b83:consent` | JS-Event | Im Browser nach jeder (geladenen) Einwilligung, `detail.services` |

## Aufbau

```
b83-cookie-banner.php        Plugin-Header, Konstanten, Autoloader
uninstall.php                Protokoll bleibt als Nachweis erhalten
src/
  Plugin.php                 verdrahtet alle Komponenten
  Settings.php               Einstellungen und zentrale Validierung
  Services.php               Kategorien und Dienste-Katalog
  ConsentLog.php             Einwilligungsprotokoll (Tabelle wp_b83_consent_log)
  Migration.php              Installation, Übernahme aus 6.x
  Updater.php                Updates über GitHub-Releases
  Rest/ConsentEndpoint.php   Protokoll-Endpunkt (öffentlich, validiert, begrenzt)
  Frontend/                  Banner, Consent Mode, Blockieren, Texte, Design (CSS-Variablen)
  Admin/                     Backend und gemeinsames b83-Menü
assets/                      CSS, JS, Logo
```

## Updates

Neue Version: Version im Header und in `B83_CB_VERSION` erhöhen, Abschnitt in `CHANGELOG.md` ergänzen, auf `main` pushen – die GitHub Action erstellt das Release.
