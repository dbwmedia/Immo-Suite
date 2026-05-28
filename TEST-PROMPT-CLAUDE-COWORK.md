# DBW Immo Suite — Vollstaendiger Testplan fuer Claude Co-Work (Chrome Extension)

## Deine Rolle
Du bist ein manueller QA-Tester fuer das WordPress-Plugin "DBW Immo Suite". Pruefe ALLE unten aufgelisteten Punkte wie ein Mensch: klicke dich durch, schaue dir die Seiten an, pruefe die Funktionalitaet. Erstelle am Ende einen ausfuehrlichen Testbericht.

## Testumgebung
- **Archiv (Listenansicht):** https://stegmeier-weber.dbw-development.de/immobilien/
- **Beispiel-Detailseite:** https://stegmeier-weber.dbw-development.de/immobilien/traumhaft-fuer-familien-einfamilienhaus-in-top-lage/
- **WP-Admin Immobilien:** https://stegmeier-weber.dbw-development.de/wp-admin/edit.php?post_type=immobilie
- **Plugin-Einstellungen:** https://stegmeier-weber.dbw-development.de/wp-admin/edit.php?post_type=immobilie&page=dbw-immo-suite-settings
- **Import-Dashboard:** https://stegmeier-weber.dbw-development.de/wp-admin/edit.php?post_type=immobilie&page=dbw-immo-import
- **Customizer:** https://stegmeier-weber.dbw-development.de/wp-admin/customize.php

---

## TEIL 1: ARCHIV-SEITE (Frontend)

### 1.1 Grundlegende Darstellung
- [ ] Oeffne https://stegmeier-weber.dbw-development.de/immobilien/
- [ ] Wird die Seite korrekt geladen ohne PHP-Fehler oder weisse Seite?
- [ ] Ist die Ueberschrift "Immobilien" sichtbar?
- [ ] Werden Immobilien-Cards im Grid (Kachelansicht) angezeigt?
- [ ] Haben die Cards: Bild, Titel, Ort, Meta-Daten (m2, Zimmer etc.), Preis, Button?
- [ ] Sind die SVG-Icons fuer Wohnflaeche/Zimmer/Schlafzimmer/Baujahr sichtbar (Outline-Style, nicht gefuellt)?
- [ ] Ist das Layout responsive? Pruefe bei verschiedenen Breiten (Desktop 3 Spalten, Tablet 2, Mobile 1)

### 1.2 Preislabel
- [ ] Zeigt ein KAUF-Objekt das Label "Kaufpreis" ueber dem Preis?
- [ ] Zeigt ein MIET-Objekt das Label "Kaltmiete" ueber dem Preis?
- [ ] KEIN Objekt sollte "(Miete)" als Text im Preis selbst haben — das war ein alter Bug

### 1.3 Status-Tags
- [ ] Haben aktive Kauf-Objekte ein Tag wie "Haus zum Kauf" oder "Wohnung zur Miete"?
- [ ] Falls verkaufte Objekte im Archiv sichtbar sind: Haben sie ein rotes "Verkauft"-Badge?
- [ ] Falls reservierte Objekte sichtbar sind: Haben sie ein oranges "Reserviert"-Badge und Grayscale-Bild?
- [ ] Falls Referenz-Objekte sichtbar sind: Haben sie ein gruenes "Referenz"-Badge?

### 1.4 Energieeffizienz-Flag
- [ ] Haben Objekte mit Energieklasse (A-H) ein kleines farbiges Flag oben rechts im Bild?
- [ ] Ist die Farbe passend zur Klasse (gruen fuer A, rot fuer H)?

### 1.5 Filter & Suche
- [ ] Ist die Filterleiste sichtbar (Objekttyp-Dropdown, Standort-Eingabe, Suchen-Button)?
- [ ] Klicke auf den Filter-Toggle-Button (Pfeil-nach-unten) — klappen die erweiterten Filter auf?
- [ ] Funktioniert der Filter-Toggle OHNE jQuery-Fehler in der Browser-Konsole?
- [ ] Waehle einen Objekttyp → Klicke "Suchen" → Werden die Ergebnisse gefiltert?
- [ ] Gib einen Ort oder PLZ in das Standort-Feld → Suchen → Wird gefiltert?
- [ ] Oeffne die erweiterten Filter → Setze Preisbereich → Suchen → Korrekte Ergebnisse?
- [ ] Klicke "Filter zuruecksetzen" → Werden alle Filter entfernt?

### 1.6 Sortierung
- [ ] Ist das Sortier-Dropdown sichtbar (rechts oben)?
- [ ] Aendere die Sortierung auf "Preis aufsteigend" → Werden die Objekte korrekt sortiert?
- [ ] Aendere auf "Groesste zuerst" → Funktioniert die Sortierung?

### 1.7 Grid/List-Switcher
- [ ] Sind die zwei View-Buttons (Grid/Liste) sichtbar?
- [ ] Klicke auf "Liste" → Wechselt die Ansicht auf horizontale Cards?
- [ ] In der Listenansicht: Bild links, Daten rechts, Preis ganz rechts?
- [ ] Klicke auf "Grid" → Wechselt zurueck auf Kachelansicht?
- [ ] Lade die Seite neu → Wird der zuletzt gewaehlte View aus localStorage wiederhergestellt?

### 1.8 Pagination
- [ ] Falls mehr Objekte als pro Seite: Wird die Pagination angezeigt?
- [ ] Klicke auf Seite 2 → Werden die naechsten Objekte geladen?
- [ ] Hat die aktive Seite eine andere Farbe?

### 1.9 Ergebnis-Zaehler
- [ ] Wird "X Immobilien gefunden" korrekt angezeigt?
- [ ] Aendert sich die Zahl wenn du filterst?

---

## TEIL 2: DETAIL-SEITE (Single Property)

### 2.1 Grundlegende Darstellung
- [ ] Oeffne https://stegmeier-weber.dbw-development.de/immobilien/traumhaft-fuer-familien-einfamilienhaus-in-top-lage/
- [ ] Kein PHP-Error, kein weisser Screen?
- [ ] Ist der Titel sichtbar?
- [ ] Ist die Adresse unter dem Titel sichtbar (mit Location-Icon)?

### 2.2 Galerie-Slider
- [ ] Wird der Galerie-Slider mit Bildern angezeigt?
- [ ] Kann man mit den Pfeil-Buttons links/rechts durch die Bilder navigieren?
- [ ] Sind die Thumbnail-Bilder unter dem Slider sichtbar?
- [ ] Klicke auf ein Thumbnail → Scrollt der Slider zum richtigen Bild?
- [ ] Haben die Nav-Buttons ARIA-Labels? (Rechtsklick → Element untersuchen → aria-label pruefen)

### 2.3 Lightbox
- [ ] Klicke auf ein Galerie-Bild → Oeffnet sich die Lightbox (schwarzer Overlay)?
- [ ] Hat die Lightbox role="dialog" und aria-modal="true"? (im HTML pruefen)
- [ ] Funktionieren die Prev/Next-Buttons in der Lightbox?
- [ ] Druecke Escape → Schliesst sich die Lightbox?
- [ ] Druecke Pfeil-links/rechts → Navigation?
- [ ] Klicke auf den dunklen Hintergrund → Schliesst sich die Lightbox?
- [ ] Wird der Zaehler "1 / 5" korrekt angezeigt?
- [ ] Auf Mobile: Funktioniert Swipe links/rechts?

### 2.4 Floating Action Buttons
- [ ] Ist oben links ein Zurueck-Button (Pfeil) sichtbar?
- [ ] Klicke darauf → Geht es zurueck zur Uebersicht?
- [ ] Ist oben rechts ein Teilen-Button sichtbar?
- [ ] Ist oben rechts ein Drucken-Button sichtbar?
- [ ] Falls Grundrisse vorhanden: Ist unten links ein "Grundrisse & Dokumente"-Button sichtbar?

### 2.5 Key Facts (Wohnflaeche, Zimmer etc.)
- [ ] Werden die Eckdaten (Wohnflaeche, Zimmer, Grundstueck, Nutzflaeche) als Kacheln angezeigt?
- [ ] Werden nur vorhandene Daten angezeigt (keine leeren Kacheln)?

### 2.6 Beschreibung
- [ ] Wird der Beschreibungstext angezeigt?
- [ ] Ist die Ueberschrift "Beschreibung" sichtbar?

### 2.7 Ausstattung
- [ ] Wird ein Abschnitt "Ausstattung" angezeigt?
- [ ] Falls strukturierte Features vorhanden: Werden Pill-Badges angezeigt (z.B. "Balkon", "Garage", "Keller")?
- [ ] Falls Freitext-Ausstattung vorhanden: Wird der Text darunter angezeigt?
- [ ] Falls Stellplaetze vorhanden: Werden sie angezeigt?

### 2.8 Lage & Karte
- [ ] Wird der Abschnitt "Lage" angezeigt (wenn Lage-Text vorhanden)?
- [ ] Wird eine OpenStreetMap-Karte angezeigt (wenn Geo-Koordinaten vorhanden)?
- [ ] Hat die Karte einen Marker am richtigen Standort?
- [ ] Ist die Karte interaktiv (ziehen, zoomen)?
- [ ] Ist Scroll-Wheel-Zoom deaktiviert (damit man nicht versehentlich zoomt)?
- [ ] Werden Entfernungen (Infrastruktur) als Liste angezeigt?

### 2.9 Energieausweis
- [ ] Wird der Abschnitt "Energie & Heizung" angezeigt?
- [ ] Werden die Energiedaten als Grid angezeigt (Baujahr, Ausweistyp, Verbrauch etc.)?
- [ ] Wird die farbige Energieskala (A+ bis H) angezeigt?
- [ ] Zeigt der Pfeil-Indikator auf die richtige Klasse?
- [ ] Sind die Labels uebersetzt (nicht auf Englisch)?

### 2.10 Grundrisse
- [ ] Falls Grundrisse vorhanden: Wird der Abschnitt "Grundrisse" angezeigt?
- [ ] Kann man auf einen Grundriss klicken → Oeffnet sich die Lightbox?

### 2.11 Sidebar — Highlights-Box
- [ ] Ist die farbige Highlights-Box sichtbar (mit Hintergrundfarbe)?
- [ ] Werden Wohnflaeche, Zimmer, Schlafzimmer, Badezimmer, Energieklasse aufgelistet?
- [ ] Wird der Preis korrekt angezeigt (Kaufpreis ODER Kaltmiete)?
- [ ] Bei Kaufobjekten: Hausgeld und Provision sichtbar?
- [ ] Bei Mietobjekten: Nebenkosten und Warmmiete sichtbar?
- [ ] Bei "Preis auf Anfrage": Wird "Auf Anfrage" angezeigt?
- [ ] Ist die Sidebar sticky beim Scrollen (bleibt oben stehen)?

### 2.12 Sidebar — Kontakt & Formular
- [ ] Wird "Ihr Ansprechpartner" mit Name und Bild angezeigt?
- [ ] Wird die Telefonnummer als klickbarer Link angezeigt?
- [ ] Wird das Kontaktformular angezeigt (Name, E-Mail, Telefon, Nachricht)?
- [ ] Ist die Nachricht vorausgefuellt mit dem Immobilientitel?
- [ ] Sende das Formular mit Testdaten ab → Erscheint eine Erfolgsmeldung (gruen)?
- [ ] Sende das Formular OHNE E-Mail ab → Erscheint eine Fehlermeldung?
- [ ] Pruefe: Kommt die E-Mail beim Ansprechpartner (oder Admin) an?

### 2.13 Aehnliche Objekte
- [ ] Wird am Ende der Seite "Das koennte Sie auch interessieren" angezeigt?
- [ ] Werden 3 aehnliche Objekte als Cards angezeigt?
- [ ] Ist das aktuelle Objekt NICHT in der Liste?
- [ ] Haben die Cards Hover-Effekte?

### 2.14 SEO Meta-Tags
- [ ] Oeffne den Quelltext (Strg+U) und suche nach "DBW Immo Suite SEO"
- [ ] Sind og:title, og:description, og:image vorhanden?
- [ ] Ist die Description sinnvoll zusammengesetzt (Stadt, m2, Zimmer, Preis)?
- [ ] Ist og:image die URL des Titelbilds?
- [ ] Sind twitter:card und twitter:image vorhanden?
- [ ] HINWEIS: Falls Yoast oder RankMath aktiv ist, sollten KEINE dbw-Meta-Tags erscheinen

### 2.15 Lazy Loading
- [ ] Oeffne die Entwicklertools → Network-Tab → Lade die Seite
- [ ] Haben Galerie-Bilder ab dem 2. Bild loading="lazy"?
- [ ] Haben Thumbnail-Bilder loading="lazy"?
- [ ] Hat das ERSTE Galerie-Bild KEIN loading="lazy" (LCP optimiert)?

### 2.16 Print-Expose
- [ ] Druecke Strg+P (Druckvorschau)
- [ ] Wird ein sauberes A4-Layout angezeigt?
- [ ] Sind Header, Footer, Navigation, Adminbar AUSGEBLENDET?
- [ ] Ist die Karte AUSGEBLENDET?
- [ ] Ist das Kontaktformular AUSGEBLENDET?
- [ ] Sind "Aehnliche Objekte" AUSGEBLENDET?
- [ ] Wird das erste Galerie-Bild gross angezeigt?
- [ ] Werden max. 5 Bilder angezeigt (nicht alle)?
- [ ] Ist die Highlights-Box mit Rahmen statt farbigem Hintergrund?
- [ ] Sind Feature-Badges mit Rahmen statt runden Pillen?
- [ ] Sind Links nicht unterstrichen?
- [ ] Wird die Energieskala farbig gedruckt?

---

## TEIL 3: ADMIN-BACKEND

### 3.1 Plugin-Einstellungen
- [ ] Oeffne https://stegmeier-weber.dbw-development.de/wp-admin/edit.php?post_type=immobilie&page=dbw-immo-suite-settings
- [ ] Wird die Einstellungsseite korrekt geladen?
- [ ] Sind die Sektionen sichtbar: "OpenImmo Import", "Referenzen & Verkaufte Objekte"?

### 3.2 Import-Pfad
- [ ] Ist das Pfad-Dropdown sichtbar (Standard, WordPress-Root, Eigener Pfad)?
- [ ] Klicke "Pfad pruefen" → Wird eine Meldung angezeigt (gruen = existiert, rot = nicht gefunden)?

### 3.3 Referenz-Einstellungen
- [ ] Ist "Referenz-System aktivieren" mit Beschreibungstext sichtbar?
- [ ] Sind die Badge-Texte editierbar (Referenz, Verkauft)?
- [ ] Ist "Verkaufte Objekte aus normaler Liste ausblenden" vorhanden?

### 3.4 Shortcode-Dokumentation
- [ ] Scrolle nach unten → Ist die "Shortcode-Referenz" Tabelle sichtbar?
- [ ] Werden alle Shortcodes mit Beispielen aufgelistet?
- [ ] Sind Geo-Landing-Page Beispiele dabei?

### 3.5 Manueller Import
- [ ] Ist der Button "Import jetzt starten" sichtbar?
- [ ] (NUR wenn Testdaten vorhanden): Klicke Import → Wird der Fortschritt angezeigt?

### 3.6 Import-Dashboard
- [ ] Oeffne https://stegmeier-weber.dbw-development.de/wp-admin/edit.php?post_type=immobilie&page=dbw-immo-import
- [ ] Wird der System-Status angezeigt (gruen = bereit)?
- [ ] Wird die Import-Historie als Tabelle angezeigt?

### 3.7 Immobilie bearbeiten
- [ ] Oeffne eine Immobilie im Editor
- [ ] Sind die Tabs sichtbar: Basisdaten, Preise, Flaechen & Zimmer, Ausstattung, Technik & Zustand, Kontakt, Import Info?
- [ ] Tab "Basisdaten": Ist das Status-Dropdown sichtbar (Aktiv/Reserviert/Verkauft/Referenz)?
- [ ] Ist die "Status sperren" Checkbox sichtbar?
- [ ] Ist die "Als Highlight markieren" Checkbox sichtbar?
- [ ] Tab "Ausstattung": Wird die Textarea mit kommaseparierten Features angezeigt?
- [ ] Falls Features vorhanden: Wird die Vorschau mit Badges angezeigt?
- [ ] Tab "Preise": Sind alle Preisfelder vorhanden?
- [ ] Ist der WordPress-Editor (Beschreibungsfeld) verfuegbar?

### 3.8 Status aendern
- [ ] Setze den Status auf "Verkauft" → Speichern → Pruefe: Wird im Frontend das "Verkauft"-Badge angezeigt?
- [ ] Setze den Status auf "Reserviert" → Speichern → Pruefe: Wird das "Reserviert"-Badge angezeigt und das Bild grau?
- [ ] Setze den Status auf "Referenz" → Speichern → Pruefe Frontend
- [ ] Aktiviere "Status sperren" → Pruefe: Bleibt der Status bei einem Import erhalten? (nur pruefbar wenn Testdaten vorhanden)

---

## TEIL 4: GUTENBERG-BLOCKS

### 4.1 Immo Grid Block
- [ ] Erstelle eine neue Seite im Block-Editor
- [ ] Suche nach "DBW Immo Grid" → Wird der Block gefunden?
- [ ] Fuege ihn ein → Wird eine Live-Vorschau mit Immobilien angezeigt?
- [ ] Oeffne die Inspector Controls (rechte Sidebar)
- [ ] Ist "Darstellung" sichtbar mit: Anzahl, Spalten, Preis ausblenden, Datum anzeigen?
- [ ] Aendere die Spaltenanzahl auf 2 → Aendert sich die Vorschau?
- [ ] Ist "Filter" sichtbar mit: Nur Highlights, Ort/Stadt, Vermarktungsart, Objektart?
- [ ] Ist das Ort-Dropdown befuellt mit Orten und Anzahl in Klammern?
- [ ] Waehle einen Ort → Werden nur Objekte dieses Ortes angezeigt?

### 4.2 Immo References Block
- [ ] Suche nach "DBW Immo Referenzen" → Wird der Block gefunden?
- [ ] Fuege ihn ein → Wird eine Vorschau angezeigt (oder "Keine Referenzen")?
- [ ] Ist das Ort-Dropdown im Inspector verfuegbar?
- [ ] Sind die Status-Checkboxen (Verkauft, Referenz, Reserviert) sichtbar?
- [ ] Sind Spalten und Anzahl steuerbar?

---

## TEIL 5: SHORTCODES (fuer Elementor/Classic)

### 5.1 Grid Shortcode
- [ ] Erstelle eine Testseite und fuege ein: [dbw_immo_grid]
- [ ] Wird ein Grid mit 6 Immobilien angezeigt?
- [ ] Teste: [dbw_immo_grid count="3" columns="2"]
- [ ] Werden 3 Objekte in 2 Spalten angezeigt?
- [ ] Teste: [dbw_immo_grid highlights="yes"]
- [ ] Werden nur Highlight-Objekte angezeigt (oder keine, wenn keines markiert)?

### 5.2 References Shortcode
- [ ] Fuege ein: [dbw_immo_references]
- [ ] Werden verkaufte/Referenz-Objekte angezeigt?
- [ ] Haben die Bilder Grayscale?
- [ ] Teste: [dbw_immo_references count="3" columns="2"]

### 5.3 Geo-Landing-Page Test
- [ ] Finde einen Ort-Slug (WP-Admin → ImmoSuite → Ort → URL-Spalte)
- [ ] Teste: [dbw_immo_grid location="SLUG"] mit dem echten Slug
- [ ] Werden nur Objekte des gewaehlten Ortes angezeigt?
- [ ] Teste: [dbw_immo_references location="SLUG"]

---

## TEIL 6: CUSTOMIZER

- [ ] Oeffne den Customizer → Panel "Immobilien Suite"
- [ ] Ist "Design System" mit Farben (Primary, Secondary, Accent, Hintergrund) vorhanden?
- [ ] Aendere die Primaerfarbe → Aendert sich die Vorschau live?
- [ ] Ist "Archiv & Suche" mit Objekte pro Seite, Spalten, Toggles vorhanden?
- [ ] Deaktiviere "Preis anzeigen" → Verschwindet der Preis in der Vorschau?
- [ ] Ist "Detailansicht" mit Karte, Energie, Galerie, Kontakt, Aehnliche Objekte Toggles vorhanden?
- [ ] Ist "Highlights-Box Hintergrund" einstellbar?

---

## TEIL 7: CSS & KONSOLE

- [ ] Oeffne die Browser-Konsole (F12) auf der Archiv-Seite
- [ ] Gibt es JavaScript-Fehler? (insbesondere jQuery-Fehler wuerden auf den alten Code hinweisen)
- [ ] Wird frontend.css genau EINMAL geladen (nicht doppelt/dreifach)?
- [ ] Pruefe auf der Detailseite: Werden Leaflet-CSS und -JS geladen?
- [ ] Gibt es 404-Fehler fuer fehlende Assets?

---

## TEIL 8: REFERENZ-SYSTEM

### 8.1 Referenz-Seite
- [ ] Gehe zu /immobilien/referenzen/ (oder dem konfigurierten Slug)
- [ ] Wird die Referenz-Seite korrekt angezeigt?
- [ ] Werden nur verkaufte/Referenz-Objekte gelistet?
- [ ] Haben alle Bilder Grayscale?
- [ ] Werden die richtigen Badges angezeigt?

### 8.2 URL-Redirect
- [ ] Gehe direkt zu /referenzen/ (Root-Slug ohne /immobilien/)
- [ ] Wirst du per 301 auf /immobilien/referenzen/ weitergeleitet?

### 8.3 Filter-Einstellung
- [ ] Aktiviere in den Einstellungen "Verkaufte Objekte aus normaler Liste ausblenden"
- [ ] Gehe zur Archiv-Seite → Sind verkaufte, reservierte und Referenz-Objekte NICHT mehr sichtbar?
- [ ] Gehe zur Referenz-Seite → Sind sie dort weiterhin sichtbar?

---

## TESTBERICHT-FORMAT

Erstelle am Ende einen strukturierten Bericht mit folgendem Format:

### TESTBERICHT: DBW Immo Suite v1.4.0

**Testdatum:** [Datum]
**Testumgebung:** stegmeier-weber.dbw-development.de
**Tester:** Claude Co-Work

#### Zusammenfassung
- **Getestet:** X von Y Pruefpunkten
- **Bestanden:** X
- **Fehlgeschlagen:** X
- **Nicht pruefbar:** X (mit Begruendung)

#### Kritische Probleme (Blocker)
| # | Bereich | Problem | Erwartetes Verhalten | Screenshot/Details |
|---|---------|---------|---------------------|-------------------|

#### Wichtige Probleme (sollte gefixt werden)
| # | Bereich | Problem | Erwartetes Verhalten | Screenshot/Details |
|---|---------|---------|---------------------|-------------------|

#### Kleinere Probleme (Nice-to-have)
| # | Bereich | Problem | Erwartetes Verhalten | Screenshot/Details |
|---|---------|---------|---------------------|-------------------|

#### Bestandene Tests (Highlights)
- Kurze Liste der wichtigsten bestandenen Bereiche

#### Empfehlungen
- Priorisierte Liste von Verbesserungen

#### Nicht pruefbare Punkte
- Liste mit Begruendung (z.B. "Keine Testdaten fuer Import")
