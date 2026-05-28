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
- **Grid View** (Kachelansicht) + **List View** (horizontal) mit Live-Switcher
- **Erweiterte Filterleiste** — Objekttyp, Standort (Ort/PLZ), Vermarktungsart, Preis, Flaeche, Zimmer
- **Sortierung** — Datum, Preis (aufsteigend/absteigend inkl. Kauf+Miete gemischt), Flaeche
- **Pagination** mit konfigurierbarer Seitengroesse
- **Energieeffizienz-Flags** im EnEV-Farbschema (A+ bis H)
- **Dynamische Status-Tags** — "Haus zum Kauf", "Wohnung zur Miete", "Verkauft", "Reserviert", "Referenz"
- **Intelligentes Preislabel** — zeigt automatisch "Kaufpreis" oder "Kaltmiete"

#### Detailseiten
- **Galerie-Slider** mit Thumbnail-Leiste, Prev/Next-Navigation und nativer Lightbox
- **Ausstattungs-Badges** — strukturierte Merkmale als Pill-Tags (Balkon, Garage, Kamin etc.)
- **OpenStreetMap-Karte** via Leaflet.js (kein API-Key noetig) mit Marker
- **Energieausweis-Skala** — grafische Darstellung mit Pfeil-Indikator
- **Kontaktformular** — AJAX-basiert mit wp_mail, Nonce-Schutz, Validierung
- **Highlights-Sidebar** — Sticky-Box mit Eckdaten, Preis, Provision, Energieklasse
- **Aehnliche Objekte** — "Das koennte Sie auch interessieren" mit 3-stufigem Fallback
- **Floating Action Buttons** — Zurueck, Teilen (Web Share API), Drucken, Grundrisse-Anker
- **Print-Expose** — sauberes A4-Layout beim Drucken (automatisch, ohne Extra-Klick)

#### SEO & Performance
- **Open Graph + Twitter Card Meta-Tags** — automatisch generiert aus Objektdaten
- **Lazy Loading** fuer Galerie-Bilder (erstes Bild eager fuer LCP)
- **Alt-Text Fallback** — "Objekttitel — Bild N" wenn kein Alt gesetzt
- **Saubere URLs** mit konfigurierbarem CPT-Slug
- **Kompatibel mit Yoast / RankMath** — SEO-Tags werden nur ausgegeben wenn kein SEO-Plugin aktiv ist

### Gutenberg-Bloecke

#### `dbw/immo-grid` — Immobilien Grid
Zeigt aktuelle Immobilien in einem konfigurierbaren Grid an.

**Inspector Controls:**
- Anzahl (1-24) und Spalten (1-4)
- Filter nach Ort/Stadt, Vermarktungsart, Objektart
- Nur Highlights anzeigen
- Preis ausblenden, Datum anzeigen

#### `dbw/immo-references` — Referenzen
Zeigt verkaufte und Referenz-Objekte an.

**Inspector Controls:**
- Anzahl und Spalten
- Filter nach Ort/Stadt
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
```

**Geo-Landing-Pages:** Kombiniere beide Shortcodes mit `location="ort-slug"` um stadtspezifische Immobilien-Seiten zu erstellen.

### Referenz-System
- **Status-Management** — Aktiv, Reserviert, Verkauft, Referenz (per Dropdown im Editor)
- **Status-Lock** — verhindert Ueberschreibung durch Import
- **Automatische Referenz-Seite** unter `/immobilien/referenzen/`
- **301-Redirect** von isolierten Root-Seiten
- **Grayscale-Bilder** + farbige Status-Badges fuer verkaufte/referenz/reservierte Objekte
- **Filter-Option** — Verkaufte/Reservierte Objekte aus normalem Archiv ausblenden

### WordPress Customizer
- **Design System** — Primary, Secondary, Accent, Hintergrundfarbe, Eckenradius
- **Archiv-Steuerung** — Objekte/Seite, Spalten, Sichtbarkeit (Preis, Flaeche, Zimmer, Baujahr, Energie)
- **Detailseite** — Toggles fuer Karte, Energie, Galerie, Kontakt, Teilen, Drucken, Aehnliche Objekte
- **Highlights-Box** — Hintergrundfarbe und Textfarbe

### Admin-Backend
- **7 Tabs im Property-Editor** — Basisdaten, Preise, Flaechen, Ausstattung, Technik, Kontakt, Import Info
- **Import-Dashboard** — System-Status, manueller Import-Trigger, Historie (letzte 20 Laeufe)
- **Einstellungen** — Import-Pfad (Dropdown + Validierung), CPT-Slug, Referenz-System, Shortcode-Doku
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
    EnergyRenderer.php      # Energieausweis-Skala + Archiv-Flag
    ContactForm.php         # AJAX-Kontaktformular
    SeoMeta.php             # Open Graph + Twitter Card Tags
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
assets/
  css/frontend.css          # Alle Frontend-Styles inkl. Print
  js/frontend.js            # Filter-Toggle (Vanilla JS)
  js/view-switch.js         # Grid/List-Switcher
  js/admin.js               # Import-AJAX mit Nonce
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
