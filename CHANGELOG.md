# Changelog

## 7.7.0 – 2026-11-11

### Neu
- Einstellungs-Button auf Smartphones (unter 768 px Breite) optional ausblenden, Tab „Design“ → „Einstellungs-Button“; Standard bleibt sichtbar

## 7.6.0 – 2026-11-11

### Neu
- Einstellung „Sprache des Banners“ (Tab „Allgemein“): automatisch nach der Website-Sprache, immer Deutsch oder immer Englisch. Ersetzt den Schalter „Englisch“
- Hinweis im Backend, wenn die Website auf Englisch eingestellt ist, der Banner aber fest auf Deutsch steht

### Geändert
- „Automatisch“ zeigt bei jeder nicht deutschen Website-Sprache die englischen Texte (bisher Deutsch)
- Bestehende Websites behalten ihr Verhalten: Schalter „Englisch“ an → Automatisch, aus → Immer Deutsch. Neue Installationen starten mit Automatisch

## 7.5.0 – 2026-11-06

### Neu
- Dienst-Vorlage „Google Ads“ (Kategorie Marketing) mit Conversion-ID (AW-…) oder Einbindung über den Tag Manager; Zustimmung setzt im Consent Mode ad_storage, ad_user_data und ad_personalization
- Shortcode `[b83_cookie_list]` für die Datenschutzerklärung: alle aktiven Dienste je Kategorie mit Anbieter, Zweck, Cookies, Drittland und Datenschutzlink, dazu ein Link zu den Cookie-Einstellungen

### Geändert
- Google Analytics und Google Ads teilen sich eine gtag.js-Einbindung

## 7.4.0 – 2026-11-01

### Neu
- Einstellungs-Button frei platzierbar (Tab „Design“): rundes Symbol in der Ecke oder schmaler Reiter am Rand, der beim Überfahren „Cookie-Einstellungen“ zeigt
- Seite (links, rechts oder wie der Banner) unabhängig von der Banner-Position, dazu Abstand unten bzw. Höhe am Rand
- Vorschau des Einstellungs-Buttons im Tab „Design“, mit angedeutetem „Nach oben“-Button

### Geändert
- Bestehende Websites behalten das bisherige Verhalten (rundes Symbol, Seite wie der Banner)

## 7.3.2 – 2026-11-01

### Behoben
- b83-Übersicht: Plugin-Icons erschienen als defekte Bilder
- Icons von Pro-Versionen fehlten im b83-Menü (b83-Menü Version 3)

## 7.3.1 – 2026-11-01

### Behoben
- Elementor-Video-Widgets wurden doppelt blockiert (Widget-Filter und the_content): Nach „Inhalt laden“ erschien ein zweiter Platzhalter, der sich nicht mehr öffnen ließ
- „Inhalt laden“ öffnet Platzhalter jetzt auch, wenn der Dienst auf der Seite schon geladen ist, etwa in später geöffneten Popups oder Tabs

## 7.3.0 – 2026-11-01

### Neu
- b83-Menü (Version 2): Übersicht und Menüpunkte zeigen das Icon jedes b83-Plugins

## 7.2.0 – 2026-11-01

### Neu
- Eigenes Icon und Banner im b83-Stil: erscheinen in der Update-Liste und unter „Details ansehen“
- „Details ansehen“ zeigt jetzt eine Beschreibung des Plugins

## 7.1.2 – 2026-11-01

### Behoben
- YouTube-Platzhalter ließ sich nicht öffnen, wenn das Widget aus dem Elementor-Cache kam, YouTube in den Einstellungen aber nicht (mehr) aktiv war: „Inhalt laden“ lädt den Inhalt jetzt in jedem Fall
- Elementor-Video-Widget lud die YouTube-API (youtube.com/iframe_api) schon vor der Zustimmung
- WP Rocket und andere Lazy-Load-Plugins schrieben das iFrame im Platzhalter auf about:blank um; das Video blieb nach der Zustimmung leer
- Beim Speichern der Einstellungen wird jetzt auch der Elementor-Cache geleert

## 7.1.1 – 2026-10-16

### Behoben
- Theme- bzw. Elementor-Stile (z. B. roter Rahmen, Unterstreichung, große Abstände) überschrieben die Buttons im Banner, den Einstellungs-Button am Bildschirmrand und den Button in Platzhaltern
- „Details“-Knöpfe bei den Kategorien sind jetzt klein, ohne Unterstreichung und mit Pfeil
- Einwilligungs-ID wurde in der Theme-Farbe für Code (oft rot) dargestellt
- Überschrift und Links im Banner werden nicht mehr von Theme-Vorgaben verändert

## 7.1.0 – 2026-10-16

### Neu
- Tab „Design“ mit Live-Vorschau
- Box: Hintergrund, Textfarbe, Rahmenfarbe und -stärke, Eckenabrundung, Schatten (keiner bis stark), Breite, Innenabstand
- Buttons: Hintergrund, Schrift- und Rahmenfarbe jeweils normal und beim Überfahren, Abrundung (bis Pillenform), Rahmenstärke – getrennt für „Zustimmen/Ablehnen/Speichern“ und „Einstellungen“
- Schrift der Website übernehmen oder Systemschrift
- Warnung bei schwer lesbaren Farbkombinationen (Kontrast unter 4,5 : 1)

### Geändert
- Position, Akzentfarbe und eigenes CSS liegen jetzt im Tab „Design“

## 7.0.0 – 2026-10-15

Neuaufbau nach b83-Standard.

### Neu
- Zweistufiger Banner: „Alle akzeptieren“, „Alle ablehnen“ und „Einstellungen“ gleich gestaltet; zweite Ebene mit Kategorien und Diensten
- Dienste-Katalog mit Pflichtangaben (Anbieter, Zweck, Cookies, Drittland, Datenschutzlink): Google Tag Manager, Google Analytics, Meta Pixel, YouTube, Vimeo, Google Maps
- Eigene Dienste (z. B. Wetter-Widgets) mit Skript-Code, Domains zum Blockieren und Cookie-Namen
- Blockieren von YouTube, Vimeo, Google Maps und eigenen Domains auch in Elementor, oEmbeds und Widgets; Zustimmung je Dienst direkt im Platzhalter
- Einwilligungsprotokoll in eigener Tabelle mit Einwilligungs-ID, ohne IP-Adresse; Suche, Statistik, CSV-Export, automatische Löschung
- Deutsch und Englisch
- Widerruf stoppt Dienste sofort und löscht ihre Cookies
- „Alle Besucher erneut fragen“ per Knopfdruck
- Automatische Ausnahmen für WP Rocket, Seiten-Cache wird nach dem Speichern geleert
- Gemeinsames b83-Menü, Updates über GitHub

### Behoben
- Ablehnen war weniger auffällig als Zustimmen
- Einwilligungen enthielten keine Angaben zu Diensten, Anbietern und Drittlandübermittlung
- Protokoll ging bei Seiten-Cache verloren (abgelaufenes Sicherheitstoken) und war auf 1.000 Einträge begrenzt
- Banner-Zustand wurde serverseitig berechnet und vom Seiten-Cache für alle Besucher eingefroren
- „Video laden“ erteilte stillschweigend die Zustimmung für alle Marketing-Dienste
- Elementor-Videos, Vimeo und Google Maps wurden nicht blockiert

### Kompatibilität
- Shortcodes `[b83_cookie_settings]` und `[b83_youtube]` funktionieren weiter
- Einstellungen und Protokoll aus 6.x werden automatisch übernommen; alle Besucher werden einmal neu gefragt
