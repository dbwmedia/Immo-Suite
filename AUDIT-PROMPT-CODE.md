# DBW Immo Suite — Code-Audit Prompt

## Deine Rolle

Du bist ein erfahrener WordPress-Plugin-Entwickler und Security-Auditor. Pruefe den gesamten Code des Plugins "DBW Immo Suite" auf Sicherheit, Code-Qualitaet, Performance und Best Practices. Lies jeden relevanten File, analysiere ihn und erstelle am Ende einen ausfuehrlichen Audit-Bericht.

## Plugin-Struktur

```
dbw-immo-suite/
├── dbw-immo-suite.php          # Main plugin file, constants, autoloader
├── src/
│   ├── Core/
│   │   ├── Plugin.php          # Bootstrap, hook registration via Loader
│   │   ├── Loader.php          # Central hook loader pattern
│   │   ├── Anrede.php          # Du/Sie toggle helper
│   │   ├── License.php         # License key validation (SHA256)
│   │   ├── MediaCleanup.php    # Auto-delete attachments on post delete
│   │   ├── Privacy.php         # DSGVO export/eraser stubs
│   │   └── Rewrites.php        # CPT registration, rewrite rules
│   ├── Admin/
│   │   ├── Settings.php        # Settings page with tabs
│   │   ├── Customizer.php      # Theme customizer integration
│   │   ├── MetaBoxes.php       # Custom meta boxes for immobilie CPT
│   │   └── PageGenerator.php   # Auto-create reference page
│   ├── Frontend/
│   │   ├── CardRenderer.php    # Property card rendering (archive + blocks)
│   │   ├── ContactForm.php     # AJAX contact handler + rate limiting
│   │   ├── ContactModal.php    # Multi-step dialog modal
│   │   ├── EnergyRenderer.php  # Energy scale + cost calculator
│   │   ├── Filter.php          # Archive filter + pagination
│   │   ├── FinanceCalculator.php # Kaufnebenkosten calculator
│   │   ├── InfrastructureScore.php # Walk Score style infra rating
│   │   ├── PdfExpose.php       # PDF/print expose endpoint
│   │   ├── PriceComparison.php # Price/sqm comparison
│   │   ├── SchemaOutput.php    # JSON-LD structured data
│   │   ├── SeoMeta.php         # OG/Twitter meta, robots, sitemap
│   │   ├── Shortcode.php       # [dbw_immo_grid] / [dbw_immo_references]
│   │   └── WhatsAppButton.php  # WhatsApp CTA variations
│   ├── Import/
│   │   └── Importer.php        # OpenImmo XML parser, ZIP handler, AJAX batch
│   └── blocks/
│       ├── GridBlock.php       # Gutenberg grid block server render
│       └── ReferencesBlock.php # Gutenberg references block server render
├── templates/
│   ├── single-immobilie.php    # Detail page template
│   ├── archive-immobilie.php   # Archive template
│   └── expose.php              # Print/PDF expose template
├── assets/
│   ├── css/frontend.css        # All frontend styles
│   ├── js/                     # Frontend JS files
│   └── vendor/leaflet/         # Leaflet 1.9.4 local copy
├── build/blocks/               # Compiled Gutenberg blocks
└── uninstall.php               # Cleanup on plugin deletion
```

---

## TEIL 1: SICHERHEIT (OWASP Top 10)

### 1.1 SQL Injection
- [ ] Pruefe ALLE Datenbank-Queries: Werden `$wpdb->prepare()` oder WP-API-Funktionen verwendet?
- [ ] Suche nach direkten `$wpdb->query()` Aufrufen ohne prepare
- [ ] Pruefe `WP_Query` Argumente: Werden User-Inputs escaped?
- [ ] Suche nach `esc_sql()` — sollte NICHT direkt verwendet werden (prepare ist besser)

### 1.2 Cross-Site Scripting (XSS)
- [ ] Pruefe ALLE `echo`/`print` Statements in Templates: Wird `esc_html()`, `esc_attr()`, `esc_url()` verwendet?
- [ ] Pruefe `wp_kses_post()` fuer Rich-Text-Ausgabe (Beschreibungen)
- [ ] Suche nach `echo $variable` ohne Escaping — jeder Output muss escaped sein
- [ ] Pruefe JavaScript-Ausgaben: Wird `esc_js()` oder `wp_json_encode()` verwendet?
- [ ] Pruefe `wp_add_inline_script()` Aufrufe auf korrekte Escapierung
- [ ] Sind SVG-Uploads blockiert? (Stored XSS Risiko)

### 1.3 Cross-Site Request Forgery (CSRF)
- [ ] Pruefe ALLE AJAX-Handler: Wird `check_ajax_referer()` aufgerufen?
- [ ] Pruefe ALLE admin_post Hooks: Wird `wp_verify_nonce()` verwendet?
- [ ] Pruefe Settings-Seite: Wird `settings_fields()` korrekt eingebunden?
- [ ] Pruefe ALLE Formulare: Wird `wp_nonce_field()` verwendet?

### 1.4 Authentifizierung & Autorisierung
- [ ] Pruefe ALLE AJAX-Handler: Wird `current_user_can()` geprueft wo noetig?
- [ ] Frontend AJAX (Kontaktformular): Ist korrekt als `wp_ajax_nopriv_` registriert?
- [ ] Admin AJAX (Import): Ist NUR als `wp_ajax_` registriert (nicht nopriv)?
- [ ] Pruefe Settings-Seite: Wird `manage_options` Capability geprueft?

### 1.5 Path Traversal / File Inclusion
- [ ] Pruefe `Importer.php`: Wird `realpath()` fuer Pfad-Validierung verwendet?
- [ ] Pruefe Upload-Funktionen: Werden Dateitypen per Whitelist geprueft?
- [ ] Welche Dateitypen sind erlaubt? (Sollte: jpg/jpeg/png/gif/webp/pdf, KEIN svg)
- [ ] Pruefe ZIP-Extraktion: Wird gegen Zip-Slip geschuetzt?
- [ ] Pruefe `PdfExpose.php`: Wird der `?expose=1` Parameter sicher verarbeitet?

### 1.6 XML External Entity (XXE)
- [ ] Pruefe `Importer.php`: Wird `LIBXML_NONET` beim XML-Laden verwendet?
- [ ] Wird `libxml_disable_entity_loader()` auf PHP < 8.0 aufgerufen?
- [ ] Wird auf PHP 8.0+ korrekt darauf verzichtet?

### 1.7 E-Mail Header Injection
- [ ] Pruefe `ContactForm.php`: Werden Newlines aus dem Name-Feld entfernt?
- [ ] Werden Reply-To Header sicher zusammengebaut?
- [ ] Wird die E-Mail-Adresse mit `is_email()` validiert?

### 1.8 Rate Limiting / Spam
- [ ] Pruefe Kontaktformular: Ist Rate Limiting implementiert?
- [ ] Wie lang ist das Zeitfenster? (Sollte mind. 2 Minuten sein)
- [ ] Ist ein Honeypot-Feld implementiert?
- [ ] Wird das Honeypot serverseitig geprueft?

---

## TEIL 2: CODE-QUALITAET

### 2.1 PHP Standards
- [ ] Wird ein konsistenter Namespace verwendet? (`DBW\ImmoSuite\*`)
- [ ] Haben ALLE src/ Dateien den ABSPATH-Guard?
- [ ] Ist die ABSPATH-Pruefung NACH der namespace-Deklaration? (Plugin-Konvention)
- [ ] Wird PSR-4 Autoloading korrekt implementiert?
- [ ] Pruefe auf PHP 8.0/8.1/8.2 Kompatibilitaet (deprecated functions, type errors)

### 2.2 WordPress Coding Standards
- [ ] Werden WordPress-Funktionen korrekt verwendet (keine direkten PHP-Alternativen)?
  - `get_post_meta()` statt direkter DB-Zugriff
  - `wp_enqueue_script/style()` statt direkte `<script>`/`<link>` Tags
  - `wp_mail()` statt `mail()`
  - `wp_remote_get()` statt `file_get_contents()` fuer URLs
- [ ] Werden Hooks korrekt registriert und entfernt?
- [ ] Wird `wp_reset_postdata()` nach Custom Queries aufgerufen?
- [ ] Werden Transients korrekt verwendet (set/get/delete)?

### 2.3 Internationalisierung (i18n)
- [ ] Sind ALLE sichtbaren Strings in `__()` oder `_e()` gewrapped?
- [ ] Wird die korrekte Text Domain `dbw-immo-suite` verwendet?
- [ ] Gibt es hardcodierte deutsche Strings ohne Translation-Wrapper?
- [ ] Werden Pluralformen mit `_n()` behandelt?

### 2.4 Error Handling
- [ ] Werden AJAX-Fehler mit `wp_send_json_error()` zurueckgegeben?
- [ ] Gibt es try/catch Bloecke wo noetig (XML-Parsing, File-Operationen)?
- [ ] Werden PHP Warnings/Notices vermieden? (Pruefe mit `WP_DEBUG = true`)
- [ ] Werden undefined array keys sauber geprueft (`isset()` / Null-Coalescing)?

### 2.5 Datenvalidierung & Sanitisierung
- [ ] Werden ALLE `$_POST`/`$_GET`/`$_REQUEST` Werte sanitisiert?
- [ ] Werden die richtigen Sanitize-Funktionen verwendet?
  - `sanitize_text_field()` fuer einzeilige Strings
  - `sanitize_textarea_field()` fuer mehrzeilige Strings
  - `sanitize_email()` fuer E-Mails
  - `absint()` / `intval()` fuer Zahlen
  - `sanitize_key()` fuer Slugs/Keys
- [ ] Werden Settings beim Speichern sanitisiert (sanitize_callback)?

---

## TEIL 3: PERFORMANCE

### 3.1 Datenbank-Queries
- [ ] Gibt es N+1 Query-Probleme? (Queries in Schleifen)
- [ ] Werden `get_post_meta()` Aufrufe minimiert? (Einzelabruf vs. `get_post_custom()`)
- [ ] Werden unnoetige Queries auf Seiten ausgefuehrt die das Plugin nicht betreffen?
- [ ] Sind Custom Queries mit Limits versehen (kein `posts_per_page => -1` ohne Grund)?

### 3.2 Asset Loading
- [ ] Werden CSS/JS NUR auf Seiten geladen wo sie gebraucht werden?
- [ ] Werden Frontend-Assets im Footer geladen (`in_footer: true`)?
- [ ] Werden Admin-Assets NUR im Admin geladen?
- [ ] Wird Leaflet NUR auf Detailseiten mit Karte geladen?
- [ ] Sind CSS/JS-Dateien minifiziert oder ist Versionierung fuer Cache-Busting vorhanden?

### 3.3 Bildoptimierung
- [ ] Werden `srcset` und `sizes` Attribute auf Bildern gesetzt?
- [ ] Wird `loading="lazy"` korrekt verwendet (NICHT auf LCP/Above-the-fold Bildern)?
- [ ] Wird `fetchpriority="high"` auf dem ersten/wichtigsten Bild gesetzt?
- [ ] Werden `width` und `height` Attribute gesetzt (CLS-Prevention)?

### 3.4 Caching
- [ ] Werden WordPress Transients fuer teure Berechnungen genutzt?
- [ ] Pruefe `PriceComparison.php`: Wird der Portfolio-Durchschnitt gecacht?
- [ ] Haben Transients sinnvolle Ablaufzeiten?
- [ ] Werden Transients korrekt invalidiert wenn sich Daten aendern?

---

## TEIL 4: WORDPRESS-INTEGRATION

### 4.1 Hooks & Filter
- [ ] Werden alle Hooks sauber in Plugin.php/Loader.php registriert?
- [ ] Gibt es Hooks die zu spaet oder zu frueh registriert werden?
- [ ] Werden Actions/Filters mit korrekten Prioritaeten verwendet?
- [ ] Gibt es Konflikte mit gaengigen Plugins (Yoast, RankMath, WooCommerce)?

### 4.2 Uninstall / Cleanup
- [ ] Existiert `uninstall.php`?
- [ ] Werden ALLE Plugin-Optionen entfernt?
- [ ] Werden ALLE Theme-Mods entfernt?
- [ ] Werden ALLE Custom Post Types und deren Daten entfernt?
- [ ] Werden ALLE Taxonomien und deren Terms entfernt?
- [ ] Werden Transients aufgeraeumt?
- [ ] Werden Cron-Jobs entfernt?

### 4.3 Gutenberg Blocks
- [ ] Ist `block.json` korrekt konfiguriert?
- [ ] Werden Server-Side-Render Callbacks korrekt registriert?
- [ ] Werden Block-Attribute serverseitig validiert/sanitisiert?
- [ ] Werden die Blocks korrekt im Editor angezeigt (ServerSideRender)?

### 4.4 Custom Post Type
- [ ] Ist der CPT mit korrekten Capabilities registriert?
- [ ] Sind die Labels vollstaendig (singular, plural, Menu-Name etc.)?
- [ ] Sind die Taxonomien korrekt verknuepft?
- [ ] Funktionieren Permalinks nach Flush korrekt?

---

## TEIL 5: DSGVO / DATENSCHUTZ

### 5.1 Datenverarbeitung
- [ ] Werden personenbezogene Daten in der Datenbank gespeichert? (Sollte NICHT)
- [ ] Werden Kontaktanfragen NUR per E-Mail versendet (kein DB-Log)?
- [ ] Werden IP-Adressen geloggt? (Sollte NICHT, ausser fuer Rate-Limiting Transients)
- [ ] Werden Rate-Limiting Transients automatisch geloescht? (TTL vorhanden?)

### 5.2 Externe Verbindungen
- [ ] Welche externen Services werden kontaktiert? (OSM Tiles, GitHub Updater)
- [ ] Werden externe Verbindungen erst nach User-Consent hergestellt?
- [ ] Werden keine Google-Dienste verwendet (Fonts, Maps, Analytics)?
- [ ] Wird Leaflet lokal ausgeliefert (kein CDN)?

### 5.3 Privacy API
- [ ] Ist der Privacy Data Exporter registriert?
- [ ] Ist der Privacy Data Eraser registriert?
- [ ] Geben beide "keine Daten" zurueck? (Korrekt, da keine PII gespeichert)

---

## TEIL 6: IMPORT-SICHERHEIT

### 6.1 XML Parsing
- [ ] Wird `safe_load_xml()` fuer ALLE XML-Operationen verwendet?
- [ ] Werden die korrekten libxml Flags gesetzt?
- [ ] Wird die XML-Struktur validiert bevor Daten extrahiert werden?
- [ ] Werden alle extrahierten Werte sanitisiert bevor sie als Post-Meta gespeichert werden?

### 6.2 Datei-Upload / ZIP
- [ ] Werden hochgeladene Dateien per Whitelist gefiltert?
- [ ] Werden Dateinamen sanitisiert?
- [ ] Wird gegen Zip-Slip (Directory Traversal in ZIP) geschuetzt?
- [ ] Werden temporaere Dateien nach dem Import aufgeraeumt?
- [ ] Wird die maximale Dateiigroesse geprueft?

### 6.3 Batch Processing
- [ ] Ist der AJAX-Batch-Import gegen Timeout-Probleme abgesichert?
- [ ] Werden Fortschritts-Informationen sicher uebermittelt?
- [ ] Kann der Import nur von berechtigten Usern gestartet werden?

---

## TEIL 7: LIZENZ-SYSTEM

### 7.1 Implementierung
- [ ] Werden Lizenzschluessel gehasht gespeichert (SHA256)?
- [ ] Werden Klartext-Keys NICHT im Code committed?
- [ ] Ist `LICENSE-KEYS.txt` in `.gitignore`?
- [ ] Wird die Lizenzpruefung serverseitig durchgefuehrt?
- [ ] Kann die Lizenzpruefung leicht umgangen werden? (Schwachstellenanalyse)

### 7.2 Funktionssperre
- [ ] Werden ohne Lizenz tatsaechlich nur Admin-Seiten geladen?
- [ ] Sind Frontend, Import und Blocks ohne Lizenz deaktiviert?
- [ ] Wird der Lizenz-Tab immer angezeigt (auch ohne gueltige Lizenz)?

---

## TEIL 8: FRONTEND-CODE (CSS/JS)

### 8.1 JavaScript
- [ ] Werden Event-Listener korrekt aufgeraeumt (Memory Leaks)?
- [ ] Wird `DOMContentLoaded` oder `defer` korrekt verwendet?
- [ ] Werden globale Variablen vermieden (IIFE/Module Pattern)?
- [ ] Sind Vanilla JS Implementierungen korrekt (keine jQuery-Abhaengigkeit)?
- [ ] Werden Accessibility-Features implementiert (Focus Trap, ARIA, Keyboard Navigation)?

### 8.2 CSS
- [ ] Werden CSS Custom Properties konsistent verwendet?
- [ ] Sind die Selektoren spezifisch genug um Theme-Konflikte zu vermeiden?
- [ ] Wird `#dbw-immo-suite` als Scope-Selektor verwendet?
- [ ] Funktioniert das Layout responsive (Mobile, Tablet, Desktop)?
- [ ] Wird `prefers-reduced-motion` respektiert?
- [ ] Werden `print` Styles korrekt implementiert?

---

## AUDIT-BERICHT FORMAT

### CODE-AUDIT: DBW Immo Suite v[VERSION]

**Audit-Datum:** [Datum]
**Auditor:** Claude Code
**Scope:** Vollstaendiger Code-Audit (Sicherheit, Qualitaet, Performance)

#### Zusammenfassung
- **Geprueft:** X von Y Pruefpunkten
- **Bestanden:** X
- **Probleme gefunden:** X (davon X kritisch, X wichtig, X minor)

#### Kritische Sicherheitsprobleme (sofort fixen)
| # | Datei:Zeile | Problem | Risiko | Empfohlener Fix |
|---|-------------|---------|--------|----------------|

#### Wichtige Probleme (sollte gefixt werden)
| # | Datei:Zeile | Problem | Kategorie | Empfohlener Fix |
|---|-------------|---------|-----------|----------------|

#### Kleinere Probleme / Verbesserungen
| # | Datei:Zeile | Problem | Kategorie | Empfohlener Fix |
|---|-------------|---------|-----------|----------------|

#### Bestandene Pruefungen (Highlights)
- Kurze Liste der wichtigsten positiven Befunde

#### Empfehlungen (priorisiert)
1. ...
2. ...
3. ...
