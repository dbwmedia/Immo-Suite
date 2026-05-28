# Changelog

Alle wesentlichen Aenderungen an der DBW Immo Suite werden hier dokumentiert.

Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.1.0/)
und dieses Projekt verwendet [Semantic Versioning](https://semver.org/lang/de/).

---

## [1.4.0] — 2026-05-28

Grosses Produktionsreife-Release mit Security-Fixes, neuen Features und umfassender Code-Konsolidierung.

### Sicherheit
- AJAX Nonce-Verifizierung fuer alle 4 Import-Endpoints (prepare, batch, finalize, run)
- Path-Traversal-Schutz in `ajax_finalize_import` — loose_files werden gegen den konfigurierten Import-Pfad validiert
- Nonce wird via `wp_localize_script` an admin.js uebergeben

### Hinzugefuegt
- **Geo-Landing-Pages**: Ort-Filter in beiden Gutenberg-Blocks (`dbw/immo-grid`, `dbw/immo-references`)
- **Neuer Shortcode `[dbw_immo_grid]`** mit Parametern: count, columns, marketing, type, location, highlights, hide_price, show_date
- **Erweiterter Shortcode `[dbw_immo_references]`** mit location, columns, status (kommasepariert)
- **Spalten-Steuerung** (1-4) in beiden Blocks und Shortcodes
- **OpenStreetMap-Karte** auf Detailseiten via Leaflet.js (kein API-Key noetig)
- **Kontaktformular** auf Detailseiten — AJAX-basiert mit wp_mail, Nonce-Schutz, Validierung
- **SEO Meta-Tags** — Open Graph (og:title, og:description, og:image) + Twitter Cards, automatisch aus Objektdaten generiert, kompatibel mit Yoast/RankMath
- **Ausstattungs-Features** — OpenImmo `<ausstattung>` Parser fuer Balkon, Terrasse, Keller, Garage, Aufzug, Pool, Kamin etc.
- **Ausstattungs-Tab** im Property-Editor mit editierbarer Komma-Liste und Badge-Vorschau
- **Ausstattungs-Badges** auf Detailseiten als Pill-Tags
- **Status-Dropdown** im Property-Editor (Aktiv/Reserviert/Verkauft/Referenz)
- **Status-Lock** — verhindert Ueberschreibung durch OpenImmo-Import
- **Reserviert-Status** — oranges Badge + Grayscale-Bild, wird aus Archiv gefiltert
- **PHP Extension Checks** — Admin-Warnung wenn ZipArchive oder simplexml fehlt
- **Aktivierungs-Hook** — flush_rewrite_rules beim Aktivieren
- **Deaktivierungs-Hook** — Cron-Cleanup beim Deaktivieren
- **Uninstall-Routine** (uninstall.php) — raumt Options, Transients, Cron, Theme Mods auf
- **Shortcode-Dokumentation** als Tabelle in den Plugin-Einstellungen
- **Lazy Loading** fuer Galerie-Bilder (erstes Bild eager, Rest lazy)
- **Alt-Text Fallback** — "Objekttitel — Bild N" fuer Bilder ohne Alt
- **.pot Datei** fuer Uebersetzungen (987 Strings)
- **.distignore** fuer sauberes ZIP-Packaging
- **Referenz-Seite Auto-Recovery** — wird automatisch neu erstellt wenn geloescht
- **Admin-Notice** wenn Referenz-Seite nicht erstellt werden kann
- **Editor-Support** im CPT fuer manuelle Beschreibungen

### Geaendert
- **CardRenderer.php** — zentrale Card-Komponente ersetzt 5 duplizierte Implementierungen (-475 Zeilen)
- **Preislabel** zeigt jetzt korrekt "Kaufpreis" oder "Kaltmiete" (war vorher immer "Kaufpreis")
- **Preissortierung** nutzt COALESCE(kaufpreis, kaltmiete) — mischt Kauf- und Mietobjekte korrekt
- **CPT-Slug** wird jetzt aus den Plugin-Einstellungen angewendet (war vorher hardcoded)
- **jQuery entfernt** — frontend.js komplett in Vanilla JS umgeschrieben
- **filter_sold_from_main** zeigt nur noch Status=aktiv (vorher konnten reservierte Objekte durchrutschen)
- **Filter-Dropdowns** nutzen sanitize_title() fuer case-insensitive Slug-Matching
- **Energietraeger** wird als "Fluessiggas" statt "FLUESSIGGAS" angezeigt
- **EnergyRenderer** komplett auf CSS-Klassen umgestellt (inline Styles entfernt)
- **Galerie-Slider**, Navigation, Thumbnails nutzen CSS-Klassen statt inline Styles
- **Sidebar** nutzt CSS-Klasse fuer Sticky-Verhalten
- **Infrastruktur-Entfernungen** nutzen CSS-Klassen und escaped Output
- **Print-Expose** komplett ueberarbeitet — blendet Karte, Formular, Aehnliche Objekte aus, limitiert auf 5 Bilder
- **Aehnliche Objekte** — 3-stufiger Fallback (Typ+Vermarktung → nur Typ → neueste 3), nutzt $id statt get_the_ID()
- **Block-Build-Artefakte** werden im Git mitgefuehrt (Plugin funktioniert ohne npm)
- **Version** auf 1.4.0 aktualisiert (Header, Konstante, package.json)

### Behoben
- Fatal Error: `new WP_Query()` ohne Namespace-Prefix in single-immobilie.php
- Doppelte Registrierung von `register_block_categories` in Plugin.php
- Dreifaches CSS-Enqueuing (Plugin.php x2 + TemplateLoader.php)
- Aehnliche Objekte leer weil get_the_ID() nach endwhile null zurueckgibt
- ReferencesBlock ignorierte hide_price_sold Plugin-Setting (war hardcoded true)
- Referenz-Seite konnte nicht wiederhergestellt werden wenn manuell geloescht
- Trashed Referenz-Seiten wurden nicht als geloescht erkannt

### Accessibility
- `aria-label` auf Galerie-Navigationspfeilen
- `aria-label` auf Lightbox Close/Prev/Next Buttons
- `role="dialog"` und `aria-modal="true"` auf Lightbox-Overlay
- Alt-Text Fallback fuer alle Galerie- und Thumbnail-Bilder

---

## [1.3.0] — 2026-04-02

### Hinzugefuegt
- Glassmorphism Floating Actions im Galerie-Slider (Zurueck, Teilen, Drucken, Grundrisse)
- Print-Expose Layout (`@media print`) — A4-Format ohne Web-Elemente
- Web Share API fuer natives Teilen (WhatsApp, AirDrop etc.)
- Highlights-Box auf Detailseiten mit Customizer-Farbsteuerung
- Erweitertes Energiepass-Rendering mit grafischer Pfeil-Skala
- Aehnliche Objekte am Fuss der Detailseite
- Native Lightbox mit Keyboard-Navigation und Touch-Swipe
- Import-Pfad Einstellungsseite mit Dropdown, AJAX-Validierung und Server-Info

### Geaendert
- Immobilien-Grid nutzt durchgehend Outline-SVGs statt gefuellter Icons

### Behoben
- Scroll/Sticky-Verhalten zwischen Agent-Card und Highlights
- printf durch echo ersetzt (PHP 8.x ArgumentCountError)
- ModSecurity WAF 503 durch key-basierte Pfad-Auswahl vermieden

---

## [1.2.0] — 2026-02-25

### Hinzugefuegt
- Gutenberg-Block `dbw/immo-references` fuer Referenzen und verkaufte Objekte
- Gutenberg-Block `dbw/immo-grid` zum Anzeigen und Filtern aktueller Immobilien
- Inspector Controls im Block-Editor (Taxonomie-Filter, Preis-Ausblendung, Layout)
- SEO-freundliche URL-Struktur fuer Referenz-Seiten (`/immobilien/referenzen/`)

### Behoben
- 301-Redirect verhindert Crawlen doppelter Root-Seiten
- Block-Pfad Korrektur fuer fehlerfreie Registrierung im Editor

### Kompatibilitaet
- Shortcode `[dbw_immo_references]` bleibt voll funktionsfaehig

---

## [1.1.0] — 2026-02-20

### Hinzugefuegt
- Referenz- und Verkauft-System via Shortcode
- Dynamische Status-Badges (Verkauft, Reserviert, Referenz)

---

## [1.0.0] — 2026-02-18

### Hinzugefuegt
- Erste stabile Version der DBW Immo Suite
- OpenImmo XML Importer mit Batch-Processing und Logging
- Responsive Grid & List View mit Umschalter
- Erweiterte Such- und Filterleiste (AJAX-ready)
- WordPress Customizer Integration fuer Styling und Layout
- Vollstaendige CSS Isolation (`#dbw-immo-suite`)
- Performance-Optimierungen fuer grosse Objektbestaende
