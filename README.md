# DBW Immo Suite

**Professionelles Immobilien-Management & OpenImmo Import fuer WordPress**

Die **DBW Immo Suite** ist eine leistungsstarke, flexible Loesung fuer Immobilienmakler und Agenturen, die ihre Objekte professionell auf WordPress praesentieren moechten. Das Plugin bietet einen vollautomatisierten Import via OpenImmo-Schnittstelle, ein modernes Frontend mit fortschrittlichen Filtern, interaktive Karten, Kontaktformulare und umfassende Anpassungsmoeglichkeiten.

Entwickelt fuer den professionellen Einsatz und nahtlose Integration in jede WordPress-Website.

---

## Features

### Automatisierter Import (OpenImmo)
- **Vollstaendiger OpenImmo XML Support** — importiert automatisch aus OnOffice, FlowFact, JustImmo etc.
- **ZIP & Loose XML** — verarbeitet ZIP-Archive und einzelne XML-Dateien
- **Intelligenter Sync** — erstellt, aktualisiert, archiviert und loescht Objekte automatisch
- **Batch Processing** — stabil auch bei grossen Datenmengen durch AJAX-Stapelverarbeitung
- **Hash-basierte Duplikat-Erkennung** — ueberspringt unveraenderte Dateien
- **Garbage Collection** — archiviert Objekte die nicht mehr im Feed sind (optional)
- **Media Handling** — automatischer Import von Bildern, Grundrissen und Kontaktfotos
- **Ausstattungs-Parser** — extrahiert strukturierte Features (Balkon, Garage, Keller etc.) aus OpenImmo XML

### Modernes Frontend

#### Archiv & Listenansicht
- **Grid View** (Kachelansicht) + **List View** (horizontal) + **Karten-View** mit Live-Switcher
- **Kartenansicht** — alle gefilterten Objekte als Marker auf OpenStreetMap (Leaflet lokal, Consent-faehig), Popups mit Bild/Titel/Preis, Auto-Zoom
- **Merkliste (Favoriten)** — Herz auf jeder Karte, localStorage-basiert (kein Account, DSGVO-neutral), Merklisten-Ansicht mit Zaehler in der Toolbar
- **AJAX-Filterung ohne Reload** — Skeleton-Loading, Live-Ergebniszahl, URL-Sync (pushState), No-JS-Fallback
- **Erweiterte Filterleiste** — Objekttyp, Standort mit Autocomplete, Vermarktungsart, Preis-Slider mit Portfolio-Histogramm, Flaeche, Zimmer-Pills (1+ bis 5+)
- **Aktive Filter als Chips** — entfernbar, inkl. "Alle zuruecksetzen"
- **Sortierung** — Datum, Preis (aufsteigend/absteigend inkl. Kauf+Miete gemischt), Flaeche — per AJAX
- **Pagination** mit konfigurierbarer Seitengroesse
- **Energieeffizienz-Flags** im EnEV-Farbschema (A+ bis H)
- **Dynamische Status-Tags** — "Haus zum Kauf", "Wohnung zur Miete", "Verkauft", "Reserviert", "Referenz"
- **Intelligentes Preislabel** — zeigt automatisch "Kaufpreis" oder "Kaltmiete"

#### Detailseiten
- **Galerie-Slider** mit Thumbnail-Leiste (aktives Thumb markiert), Bildzaehler, Pfeiltasten-Navigation und nativer Lightbox
- **Sticky Sektions-Navigation** mit Scroll-Spy und Lesefortschritts-Balken
- **Bild-Morph** — Kartenbild morpht per View Transition in das Hero-Bild der Detailseite
- **Toast-Benachrichtigungen + Micro-Interactions** — Herz-Animation, Count-up-Eckdaten, "Link kopiert"-Toast
- **Ausstattungs-Badges** — strukturierte Merkmale als Pill-Tags (Balkon, Garage, Kamin etc.)
- **OpenStreetMap-Karte** via Leaflet.js (kein API-Key noetig) mit Marker
- **Energieausweis-Skala** — grafische Darstellung mit Pfeil-Indikator
- **Multi-Step Kontakt-Modal** — Typeform-Style 2-Step-Flow: Intent-Auswahl (4 animierte SVG-Cards) → Kontaktdaten mit intent-spezifischen Feldern, Progress-Bar, Datenschutz-Checkbox, AJAX-Submit, animiertem Erfolgs-Screen mit Makler-Kontaktkarte, Honeypot + Rate Limiting, Mobile-Bottom-Sheet + Sticky-CTA-Bar
- **Kaufnebenkosten- & Finanzierungsrechner** — automatische Nebenkosten-Aufschluesselung (Grunderwerbsteuer per PLZ, Notar, Grundbuch, Provision) + interaktive Finanzierung mit 3 Slidern (Eigenkapital, Zinssatz, Tilgung), Live-Berechnung, alle Raten im Backend anpassbar
- **Infrastruktur-Score** — Walk-Score-Stil mit SVG-Ring (0–10), 5 gewichteten Kategorie-Balken (OEPNV, Einkaufen, Bildung, Gastronomie, Verkehr), aufklappbaren Distanz-Details, auch im Expose
- **Energiekosten-Rechner** — geschaetzte Heizkosten basierend auf Energiepass-Daten, konfigurierbarer Energiepreis per Slider, 9 Energietraeger
- **Preis-pro-Quadratmeter** — automatische Berechnung mit Vergleich zum Standort-Durchschnitt, Abweichungs-Badge, optionale Archiv-Karten-Badges, Transient-Cache
- **WhatsApp-Kontakt-Button** — gruener CTA in Sidebar, optionaler Floating-Button (pulsierend), Mobile-Sticky-Bar-Icon, Modal-Success-Link, vorbefuellte Nachricht mit Platzhaltern, globale Nummer oder pro Objekt, Du/Sie-System
- **Highlights-Sidebar** — Sticky-Box mit Eckdaten, Preis, Provision, Energieklasse
- **Aehnliche Objekte** — "Das koennte Sie auch interessieren" mit 3-stufigem Fallback
- **Floating Action Buttons** — Zurueck, Teilen (Web Share API), Expose/PDF-Download, Grundrisse-Anker
- **Professionelles Expose** — standalone A4-Seite ohne Theme (Cover mit Hero-Bild, Eckdaten, Beschreibung, Ausstattung, Lage, Energie, Bildergalerie, Grundrisse, Kontakt + Disclaimer), automatischer Print-Dialog, "Als PDF speichern" im Browser, Nonce-geschuetzt, null Abhaengigkeiten

#### SEO & Performance
- **Schema.org JSON-LD** — RealEstateListing auf Detailseiten (Preis, Flaeche, Zimmer, Geo, Energieklasse, Availability), BreadcrumbList auf Archiv+Detail, RealEstateAgent sitewide
- **Open Graph + Twitter Card Meta-Tags** — automatisch generiert aus Objektdaten
- **Canonical-Tag** auf gefilterten Archiv-Seiten gegen Duplicate Content
- **Conditional Asset Loading** — CSS/JS nur auf Immobilien-Seiten oder bei Shortcode/Block-Nutzung
- **Responsive Images** — `srcset`, `sizes`, `width`/`height` auf allen Bildern, `fetchpriority="high"` fuer LCP
- **Lazy Loading** fuer Galerie-Bilder (erstes Bild eager fuer LCP)
- **Entrance Animations** — Staggered Card-Reveal, Section Fade-Up, Shimmer Loading
- **Alt-Text Fallback** — "Objekttitel — Bild N" wenn kein Alt gesetzt
- **Saubere URLs** mit konfigurierbarem CPT-Slug
- **Kompatibel mit Yoast / RankMath** — SEO-Tags werden nur ausgegeben wenn kein SEO-Plugin aktiv ist

### Gutenberg-Bloecke

#### `dbw/immo-grid` — Immobilien Grid
Zeigt aktuelle Immobilien in einem konfigurierbaren Grid an.

**Inspector Controls:**
- Anzahl (1-24) und Spalten (1-4)
- Filter nach Ort/Stadt, Vermarktungsart, Objektart
- **Ort automatisch aus aktueller Seite** — Toggle fuer dynamische Ort-Aufloesung aus dem Seitenkontext (ideal fuer Standort-Templates mit GP Elements o.Ae.)
- Nur Highlights anzeigen
- Preis ausblenden, Datum anzeigen

#### `dbw/immo-references` — Referenzen
Zeigt verkaufte und Referenz-Objekte an.

**Inspector Controls:**
- Anzahl und Spalten
- Filter nach Ort/Stadt
- **Ort automatisch aus aktueller Seite** — wie beim Grid-Block
- Status-Filter (Verkauft, Referenz, Reserviert)
- Preis ausblenden, Verkaufsdatum anzeigen

### Shortcodes (Elementor / Classic Editor)

```
[dbw_immo_grid]
[dbw_immo_grid count="6" columns="3" location="muenchen"]
[dbw_immo_grid marketing="kauf" type="haus" highlights="yes"]
[dbw_immo_grid count="3" columns="2" hide_price="yes" show_date="yes"]

[dbw_immo_references]
[dbw_immo_references location="muenchen" count="6" columns="2"]
[dbw_immo_references status="verkauft" hide_price="yes"]
[dbw_immo_references location="current" count="6"]
```

**Geo-Landing-Pages:** Kombiniere beide Shortcodes mit `location="ort-slug"` um stadtspezifische Immobilien-Seiten zu erstellen. Mit `location="current"` wird der Ort automatisch aus der aktuellen Seite bezogen - ideal fuer Standort-Templates (z.B. GP Elements auf einem `standort`-CPT).

### Referenz-System
- **Status-Management** — Aktiv, Reserviert, Verkauft, Referenz (per Dropdown im Editor)
- **Status-Lock** — verhindert Ueberschreibung durch Import
- **Automatische Referenz-Seite** unter `/immobilien/referenzen/`
- **301-Redirect** von isolierten Root-Seiten
- **Grayscale-Bilder** + farbige Status-Badges fuer verkaufte/referenz/reservierte Objekte
- **Filter-Option** — Verkaufte/Reservierte Objekte aus normalem Archiv ausblenden

### Globale Du/Sie-Anrede
- **Umschaltbar im Backend** — Radio-Toggle zwischen "Sie" (Standard) und "Du"
- **Wirkt global** auf alle Plugin-Texte: Kontaktformular, Modal, E-Mails, Templates
- **Explizite String-Paare** statt fehleranfaelligem Auto-Regex (deutsche Sprache!)
- **Live-Vorschau** im Admin bei Umschaltung
- **Helper-Funktion** `dbw_anrede($sie, $du)` fuer Entwickler
- **i18n-kompatibel** — beide Formen als separate uebersetzbare Strings in der .pot-Datei

### WordPress Customizer
- **Design System** — Primary, Secondary, Accent, Hintergrundfarbe, Eckenradius
- **Archiv-Steuerung** — Objekte/Seite, Spalten, Sichtbarkeit (Preis, Flaeche, Zimmer, Baujahr, Energie)
- **Detailseite** — Toggles fuer Karte, Energie, Galerie, Kontakt, Teilen, Expose/PDF, Aehnliche Objekte, Finanzierungsrechner, Infrastruktur-Score, Preis/m², WhatsApp, WhatsApp-Floating
- **Highlights-Box** — Hintergrundfarbe und Textfarbe

### Admin-Backend
- **7 Tabs im Property-Editor** — Basisdaten, Preise, Flaechen, Ausstattung, Technik, Kontakt, Import Info
- **Import-Dashboard** — System-Status, manueller Import-Trigger, Historie (letzte 20 Laeufe)
- **Einstellungen** — Import-Pfad (Dropdown + Validierung), CPT-Slug, Referenz-System, Darstellung (Du/Sie-Anrede, Preis/m², WhatsApp), Rechner (Finanzierung, Energiekosten), Maklerfirma (SEO), Shortcode-Doku
- **Automatischer WP-Cron Import** — stuendlich, mit Lock-Mechanismus

---

## Installation

### Systemanforderungen
- WordPress 6.0+
- PHP 7.4+ (empfohlen: 8.1+)
- PHP-Erweiterungen: `zip`, `simplexml` (wird beim Aktivieren geprueft)

### Einrichtung
1. Plugin hochladen und aktivieren
2. **Customizer** > Immobilien Suite — Design anpassen
3. **ImmoSuite** > Einstellungen — Import-Pfad konfigurieren
4. Maklersoftware fuer FTP-Upload nach `/wp-content/uploads/openimmo/` einrichten
5. Import laeuft automatisch via WP-Cron oder manuell ueber das Import-Dashboard

### Fuer Entwickler
```bash
git clone [repo-url]
cd "dbw Immo Suite"
npm install
npm run build    # Gutenberg-Bloecke kompilieren
```

Die kompilierten Block-Assets sind im `build/` Ordner enthalten — das Plugin funktioniert auch ohne npm.

---

## Dateistruktur

```
dbw-immo-suite.php          # Plugin-Entry, Autoloader, Activation/Deactivation
uninstall.php               # Saubere Deinstallation
src/
  Core/
    Plugin.php              # Hook-Registrierung, Asset-Loading
    Loader.php              # Action/Filter Queue
    Anrede.php              # Globale Du/Sie-Anrede Helper
    PageGenerator.php       # Automatische Referenz-Seite
    Rewrites.php            # URL-Rewrites fuer Referenzen
  PostTypes/
    Property.php            # CPT "immobilie" mit konfigurierbarem Slug
  Taxonomies/
    PropertyType.php        # Taxonomie "objektart"
    MarketingType.php       # Taxonomie "vermarktungsart"
    Location.php            # Taxonomie "ort"
  Admin/
    Settings.php            # Einstellungsseite + Shortcode-Doku
    PropertyDetails.php     # Meta-Boxes mit 7 Tabs
    ImportDashboard.php     # Import-Zentrale
    Customizer.php          # 25+ Customizer Controls
  Frontend/
    CardRenderer.php        # Zentrale Card-Komponente (DRY)
    Filter.php              # Filterleiste + Query-Modifikation + Sortierung
    TemplateLoader.php      # Theme/Plugin Template-Fallback
    Shortcode.php           # [dbw_immo_grid] + [dbw_immo_references]
    LocationResolver.php    # Dynamische Ort-Aufloesung aus Seitenkontext
    EnergyRenderer.php      # Energieausweis-Skala + Energiekosten-Rechner
    ContactForm.php         # AJAX-Kontaktformular
    ContactModal.php        # Multi-Step Contact Modal (Typeform-Style)
    FinanceCalculator.php   # Kaufnebenkosten- & Finanzierungsrechner
    InfrastructureScore.php # Walk-Score-artiger Standort-Score (SVG Ring + Bars)
    PriceComparison.php     # Preis-pro-qm mit Bestandsvergleich
    WhatsAppButton.php      # WhatsApp-Kontaktbutton (Sidebar + Floating)
    PdfExpose.php           # Standalone Expose-Seite (Print/PDF)
    SeoMeta.php             # Open Graph + Twitter Card Tags + Canonical
    SchemaOutput.php        # Schema.org JSON-LD (RealEstateListing, BreadcrumbList, RealEstateAgent)
  Import/
    Importer.php            # OpenImmo XML Parser + Batch AJAX
  blocks/
    GridBlock.php           # Server-Side Render fuer dbw/immo-grid
    ReferencesBlock.php     # Server-Side Render fuer dbw/immo-references
    immo-grid/              # Block-Source (JS + block.json)
    immo-references/        # Block-Source (JS + block.json)
templates/
  archive-immobilie.php     # Archiv-Template
  single-immobilie.php      # Detailseiten-Template
  expose.php                # Standalone Expose (A4 Print/PDF)
assets/
  css/frontend.css          # Alle Frontend-Styles inkl. Print
  js/frontend.js            # Filter-Toggle + Entrance Animations (Vanilla JS)
  js/view-switch.js         # Grid/List-Switcher
  js/lightbox.js            # Gallery Lightbox mit Focus-Trap
  js/contact-modal.js       # Multi-Step Contact Modal
  js/finance-calculator.js  # Kaufnebenkosten- & Finanzierungsrechner
  js/infra-score.js         # Infrastruktur-Score Animation (IntersectionObserver)
  js/admin.js               # Import-AJAX mit Nonce
docs/
  AUDIT-PROMPT.md           # Wiederverwendbarer Audit-Prompt
  TODO.md                   # Bekannte Altlasten + Feature-Roadmap
  PROMPT-KAUFNEBENKOSTEN.md # Prompt fuer Finanzierungsrechner
  PROMPT-PDF-EXPOSE.md      # Prompt fuer PDF-Expose-Generator
  PROMPT-ENERGIEKOSTEN.md   # Prompt fuer Energiekosten-Rechner
  PROMPT-INFRASTRUKTUR-SCORE.md # Prompt fuer Infrastruktur-Score
  PROMPT-WHATSAPP-BUTTON.md # Prompt fuer WhatsApp-Button
  PROMPT-PREIS-PRO-QM.md    # Prompt fuer Preis-pro-qm-Vergleich
languages/
  dbw-immo-suite.pot        # Uebersetzungsvorlage
```

---

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) fuer die vollstaendige Versionshistorie.

---

## Lizenz & Support

Die Nutzung dieses Plugins setzt eine gueltige Lizenz voraus.
Support-Anfragen richten Sie bitte direkt an den Entwickler.

**Entwickelt von:**

**Dennis Buchwald**
DBW Media
E-Mail: hallo@dbw-media.de
Web: [dbw-media.de](https://dbw-media.de)

*Professional Web Development & Digital Solutions.*
