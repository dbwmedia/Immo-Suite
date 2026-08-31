# Datenschutz-Übersicht: ImmoSuite

**Stand: Plugin-Version 2.9.0**

## Was die ImmoSuite macht

Die ImmoSuite ist ein WordPress-Plugin, das Immobilienangebote automatisch
aus der Maklersoftware übernimmt (OpenImmo-Schnittstelle) und auf der Website
darstellt: Objektlisten mit Filtern, Detailseiten mit Karte und Exposé sowie
Kontakt- und Exposé-Anfragen. Weitere Informationen:
<https://www.dennisbuchwald.de/apps/immo-suite>

---

Diese Übersicht beschreibt, welche personenbezogenen Daten die ImmoSuite
verarbeitet, wohin sie fließen und welche Einstellungen das beeinflussen.
Sie ist als Arbeitsgrundlage für die Datenschutzerklärung, das
Verarbeitungsverzeichnis und die Prüfung durch eine Rechtsberatung gedacht.

Die Angaben beschreiben den technischen Ist-Zustand des Plugins.
**Sie ersetzen keine Rechtsberatung.** Rechtliche Einordnungen und
Rechtsgrundlagen sind Vorschläge und müssen im Einzelfall geprüft werden.

Ein fertiger Textbaustein für die Datenschutzerklärung liegt im Backend unter
**Immobilien -> Einstellungen -> Datenschutz** und lässt sich dort kopieren.
Er passt sich automatisch an, ob die Anfragen-Ablage aktiv ist und welche
Löschfrist eingestellt wurde.

---

## 1. Grundhaltung der Software

- Setzt **keine Cookies**.
- Bindet **keine externen Schriftarten und keine externen Skripte** ein.
  Die Kartenbibliothek (Leaflet) liegt lokal im Plugin, kein CDN, kein API-Key.
- Kein Tracking, kein Analytics, keine Profilbildung.

---

## 2. Daten von Website-Besuchern

### 2.1 Kontaktanfrage zu einem Objekt (zweistufiges Formular)

Erhoben werden:

- Name, E-Mail-Adresse, Telefonnummer (optional), Nachricht
- Anliegen: Besichtigung / mehr Infos / Preis und Finanzierung / Rückruf
- Bevorzugter Kontaktweg
- Objektbezug (welche Immobilie)

Je nach gewähltem Anliegen zusätzlich:

- Wunschtermin (Datum und Tageszeit)
- Bedarfsangaben (Mehrfachauswahl)
- Finanzierungsstatus
- Gewünschte Rückrufzeit

Die Datenschutz-Checkbox ist Pflichtfeld. Es wird ein **Zeitstempel der
Zustimmung** gespeichert und in der Benachrichtigungs-Mail ausgegeben
(Nachweis nach Art. 7 DSGVO).

Vorgeschlagene Rechtsgrundlage: Art. 6 Abs. 1 lit. b DSGVO (vorvertragliche
Maßnahme auf Anfrage der betroffenen Person).

### 2.2 Exposé-Anfrage

Name, E-Mail-Adresse, Telefonnummer, Objektbezug, Consent-Zeitstempel.

### 2.3 Wohin die Anfragedaten gehen

- **Immer** per E-Mail an die im Backend hinterlegte Empfängeradresse,
  optional zusätzlich an eine CC-Adresse und an die im Objekt hinterlegte
  Kontaktperson.
- **Optional** Speicherung in der Anfragen-Ablage (eigener Inhaltstyp
  `immo_anfrage`): nicht öffentlich, nicht über die REST-API abrufbar,
  von der Website-Suche ausgeschlossen, nur für angemeldete Redakteure
  sichtbar. Abschaltbar unter *Einstellungen -> Anfragen*.
- **Automatische Löschung: standardmäßig nach 180 Tagen** (per Cron,
  hartes Löschen ohne Papierkorb). Der Wert ist einstellbar;
  bei `0` findet keine automatische Löschung statt.
- Angebunden an den WordPress-Datenschutz-Export und die
  Löschfunktion (Art. 15 und Art. 17 DSGVO): Die Suche erfolgt über die
  E-Mail-Adresse der anfragenden Person.

### 2.4 Spamschutz

- Honeypot-Feld (unsichtbares Formularfeld, keine Datenerhebung).
- Rate-Limit: Die IP-Adresse wird **ausschließlich als Hashwert** in einem
  Transient zwischengespeichert. Zwei Minuten pro Objekt, zusätzlich ein
  Stundenzähler pro IP-Hash. Keine Klartext-IP, keine dauerhafte Speicherung.
- Vorgeschlagene Rechtsgrundlage: Art. 6 Abs. 1 lit. f DSGVO.

### 2.5 Aufrufzähler

Pro Immobilie wird ein Zähler hochgezählt (Gesamtwert und Wochenwert),
rein serverseitig in den Metadaten des Objekts. Keine IP-Adresse, kein Cookie,
keine Wiedererkennung, keine Unterscheidung einzelner Besucher.
Angemeldete Redakteure und erkennbare Bots werden nicht gezählt.

### 2.6 Browser-Speicher (kein Cookie)

| Schlüssel | Speicher | Inhalt |
|---|---|---|
| `dbw_immo_favorites` | localStorage | IDs der gemerkten Objekte (Merkliste) |
| `dbw_immo_view` | localStorage | Gewählte Ansicht: Kachel, Liste oder Karte |
| `dbwVtCard` | sessionStorage | Bild-ID für eine Übergangsanimation, einmalig |

Diese Werte verbleiben im Browser und werden nicht an den Server oder an Dritte
übertragen. Vorgeschlagene Einordnung: § 25 Abs. 2 TDDDG (vom Nutzer
ausdrücklich gewünschte Funktion).

---

## 3. Übermittlung an Dritte

| Empfänger | Wann | Was |
|---|---|---|
| **OpenStreetMap Foundation** (UK), Host `tile.openstreetmap.org` | Erst nach aktivem Klick auf "Karte laden" oder bei erteilter Einwilligung im Cookie-Tool | IP-Adresse des Besuchers. OSM setzt keine Cookies |
| **WhatsApp / Meta Platforms** (USA) | Erst wenn der Besucher den WhatsApp-Button anklickt | Es handelt sich nur um einen `wa.me`-Link. Vorher besteht keine Verbindung |
| **dbw media**, `os.dbw-media.de` | Täglich, serverseitig, abschaltbar | Technischer Statusbericht, siehe 3.2 |
| **GitHub** | Serverseitig, Update-Prüfung | Abruf der Versionsinformation. Keine Besucherdaten |
| **Hoster / Mailserver** | Bei jeder Anfrage | Versand der Benachrichtigungs-Mail |

### 3.1 Karte (OpenStreetMap)

- Standard ist die Zwei-Klick-Lösung: Statt der Karte erscheint ein Platzhalter
  mit Hinweis. Erst der Klick lädt die Kacheln und überträgt die IP-Adresse.
- Ist im Consent-Tool ein Service für OpenStreetMap vorhanden und akzeptiert,
  lädt die Karte direkt. Erkannt werden Services, die "OpenStreetMap" oder
  "OSM" in Name, ID, Anbieter oder Hosts tragen; die Service-ID lässt sich im
  Customizer festlegen. Unterstützt werden Borlabs Cookie (3.x und 2.x) und
  die WP Consent API.
- Anbieter: OpenStreetMap Foundation, St John's Innovation Centre, Cowley Road,
  Cambridge, CB4 0WS, United Kingdom.
- Der EU-Angemessenheitsbeschluss für das Vereinigte Königreich wurde im
  Dezember 2025 verlängert und gilt bis zum 27.12.2031.
- Zu prüfen: ob die Auslieferung der Kartenkacheln über CDN-Infrastruktur
  außerhalb der EU gesondert zu erwähnen ist.

### 3.2 Technischer Statusbericht (Telemetrie)

Einmal täglich per Cron, ausschließlich Server zu Server. Inhalt:

- Domain der Website
- Plugin-Version, WordPress-Version, PHP-Version
- Lizenzstatus (gültig ja/nein)
- Anzahl veröffentlichter Objekte
- Datum, Status und Fehlerzahl des letzten Imports
- Typ einer aktiven Störungsmeldung
- Zeitstempel des Versands

**Keine Besucherdaten, keine Anfragedaten, keine Objektinhalte.** Zweck ist das
Erkennen defekter Importschnittstellen vor der Kundenmeldung. Abschaltbar unter
*Einstellungen -> Lizenz und Telemetrie*; der Endpunkt lässt sich per Filter
`dbw_immo_telemetry_endpoint` umbiegen (zum Beispiel auf einen eigenen Server).
Wird die Website von einer Agentur betreut, gehört dieser Punkt in den
Auftragsverarbeitungsvertrag.

---

## 4. Personenbezogene Daten Dritter im Objektbestand

Dieser Bereich wird häufig übersehen, enthält aber den größten Fremdbezug.

- Aus der Maklersoftware (onOffice, FlowFact, JustImmo und andere) kommen per
  OpenImmo-Schnittstelle die **Daten der Ansprechpartner**: Vorname, Name,
  Firma, E-Mail-Adresse, Telefonnummer und **Foto**. Diese werden auf der
  Objektseite, im Kontaktbereich und im Exposé **öffentlich angezeigt**.
- Ebenfalls importiert werden **Objektadresse** (Straße, Hausnummer, PLZ, Ort)
  und **Geokoordinaten**. Bei bewohnten Objekten kann daraus ein Personenbezug
  zu Eigentümern oder Mietern entstehen.
- Die Adressanzeige lässt sich im Customizer abschalten
  (*Adresse anzeigen*). Ist sie aus, entfallen zugleich die Karte und die
  Adressangabe im Exposé sowie in den strukturierten Daten.
- Die Adresse fließt bei aktivierter Anzeige in das Schema.org-Markup
  (JSON-LD, `RealEstateListing`) und damit potenziell in Suchmaschinen.
- Datenquelle: Die Maklersoftware legt XML- oder ZIP-Dateien in einem Ordner
  auf dem Webserver ab. Verarbeitete Dateien werden nach dem Import
  aufgeräumt. Für diese Übermittlung ist das Verhältnis zum
  Software-Anbieter zu klären.

---

## 5. Technische und organisatorische Punkte

- Objektbilder liegen in eigenen Ordnern (`uploads/immobilien/<Objekt-ID>/`)
  und sind aus der Mediathek ausgeblendet. Beim Löschen eines Objekts werden
  sie mitgelöscht, ein täglicher Cronjob räumt verwaiste Dateien ab.
- Die Exposé- und Druckansicht ist mit einem Sicherheitstoken (Nonce)
  geschützt. Es entsteht keine dauerhaft gespeicherte Datei.
- Import-Historie und Fehlermeldungen enthalten ausschließlich technische
  Daten, keine Besucherdaten.
- Automatische Benachrichtigungen (Wochenreport, Störungsmeldung zum Import)
  gehen an eine im Backend hinterlegte Adresse und enthalten nur Kennzahlen
  und technische Meldungen, keine Anfragedaten.
- Bei der Deinstallation werden die Einstellungen und die vom Plugin
  angelegten Daten entfernt (`uninstall.php`).

---

## 6. Punkte für die rechtliche Prüfung

1. Ist die Löschfrist von 180 Tagen für gespeicherte Anfragen angemessen?
2. Genügt die Zwei-Klick-Lösung bei OpenStreetMap, oder soll die Karte
   zwingend über das Consent-Tool laufen?
3. Ist die gehashte IP-Adresse im Spamschutz als Pseudonymisierung zutreffend
   beschrieben?
4. Veröffentlichung von Name, Telefonnummer, E-Mail-Adresse und Foto der
   Ansprechpartner: ist eine Einwilligung der Mitarbeitenden erforderlich?
5. Anzeige der genauen Objektadresse und der Geokoordinaten bei bewohnten
   Objekten: zulässig, oder nur ungefähre Lage?
6. Erforderliche Verträge: Auftragsverarbeitung mit dem Betreuer der Website
   (Hosting, Wartung, technischer Statusbericht) und mit dem Anbieter der
   Maklersoftware.
7. Formulierung des Eintrags im Verarbeitungsverzeichnis für die
   Anfragen-Ablage.

---

## 7. Abgrenzung

Diese Übersicht beschreibt ausschließlich die ImmoSuite.
Gesondert zu prüfen sind: Consent-Tool, Theme und übrige Plugins,
Schriftarten, Analytics- und Werbe-Pixel, Formulare außerhalb des
Immobilienbereichs, Hosting, Newsletter und Social-Media-Einbindungen.
