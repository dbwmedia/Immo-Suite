# Audit-Prompt: dbw Immo Suite

Diesen Prompt in einem neuen Chat verwenden, um das Plugin unabhaengig pruefen zu lassen.

---

Fuehre ein vollstaendiges Security-, Performance-, Accessibility-, Code-Quality-, SEO- und DSGVO/Datenschutz-Audit des WordPress-Plugins "dbw Immo Suite" durch. Das Plugin importiert OpenImmo-XML-Daten von Immobilien-Maklersoftware, speichert sie als Custom Post Type "immobilie" und rendert sie im Frontend mit Archiv-/Einzelansicht, Kontaktformular-Modal und Gutenberg-Bloecken.

Pruefe jeden der folgenden Bereiche und bewerte ihn auf einer Skala von 1-10. Liste alle Findings mit Datei:Zeilennummer und Severity (Kritisch/Hoch/Mittel/Niedrig).

**Sicherheit:**
- XXE-Schutz: Welche libxml-Flags werden bei simplexml_load_file() gesetzt? Ist LIBXML_NOENT dabei (das waere falsch — es expandiert Entities)?
- Path Traversal: realpath()-Validierung in allen Dateipfaden (Import, Upload, AJAX)
- Email Header Injection: Werden Newlines aus Header-Feldern (Reply-To, Subject) entfernt?
- XSS: Output Escaping in allen Templates (esc_html, esc_attr, esc_url). Wird .html() in JavaScript verwendet?
- CSRF: Nonce-Verification auf allen AJAX-Handlern, Konsistenz JS<>PHP Nonce-Namen
- SQL: $wpdb->prepare() ueberall, keine direkte String-Interpolation in SQL
- Upload-Whitelist: Welche Dateitypen sind erlaubt? Ist SVG dabei (Stored XSS)?
- ABSPATH Guards auf allen PHP-Dateien in src/
- Log-Dateien: Sind sie oeffentlich erreichbar oder geschuetzt?
- Rate Limiting auf Formularen
- Capability Checks auf Admin-Endpoints

**Performance:**
- DB-Queries pro Seitenaufruf: get_post_custom() vs. einzelne get_post_meta() Calls
- Bild-Optimierung: srcset, width/height, lazy loading, sizes-Attribut
- Leaflet/externe Libraries: Werden sie via wp_enqueue geladen oder inline?
- CSS/JS: Conditional Loading, Dateigroesse, Caching-Strategie
- Importer: Queries pro Property, Batch-Verarbeitung

**Accessibility (WCAG 2.1 AA):**
- Sind alle interaktiven Elemente `<button>` oder `<a>` (keine `<div onclick>`)?
- Haben alle Bilder sinnvolle alt-Texte?
- Focus-Management: Lightbox und Modal — Focus-Trap, Focus-Return beim Schliessen?
- aria-labels auf allen Icon-Buttons?
- prefers-reduced-motion auf allen Animationen?
- Farbkontraste (pruefe CSS-Farbwerte gegen WCAG AA 4.5:1)
- Keyboard-Navigation: Gallery, Filter, Modal, Lightbox

**Code Quality:**
- WordPress Best Practices (Sanitization, Escaping, Nonces)
- i18n: Sind alle user-facing Strings in __() gewrapped?
- Beschreibungstexte: wp_kses_post() vs. esc_html()?
- CSS: Custom Properties Konsistenz, !important Nutzung, Spezifitaetsprobleme
- JavaScript: jQuery vs. Vanilla Konsistenz, Error Handling
- PHP: @-Operator Nutzung, deprecated APIs

**Schema.org / SEO:**
- JSON-LD: RealEstateListing Vollstaendigkeit (agent, dateModified, priceSpecification fuer Miete)
- Meta-Tags: Single + Archive + Taxonomy Seiten
- document_title_parts Filter
- Sitemap: Werden noindex-Seiten (verkauft/referenz) aus der Sitemap gefiltert?
- Open Graph: og:image Dimensionen, og:locale, twitter:card Fallback
- robots meta fuer verkaufte Objekte

**DSGVO / Datenschutz:**
- Werden personenbezogene Daten verarbeitet? Wo und welche? (Kontaktformular: Name, E-Mail, Telefon, IP-Adresse)
- Wird eine Einwilligung (Consent) eingeholt bevor personenbezogene Daten verarbeitet werden? (Datenschutz-Checkbox)
- Werden personenbezogene Daten gespeichert? Wenn ja: Wo, wie lange, und gibt es eine Loeschmoeglichkeit?
- E-Mail-Versand: Werden personenbezogene Daten per wp_mail() versendet? Ist das im Datenschutzhinweis dokumentierbar?
- Rate-Limiting: Werden IP-Adressen oder E-Mail-Hashes in Transients gespeichert? Wie lange? Ist das DSGVO-konform?
- Externe Ressourcen: Werden externe CDNs geladen (Leaflet, Google Fonts, unpkg.com)? Jede externe Ressource uebertraegt die IP des Besuchers an Dritte — das erfordert Einwilligung oder Rechtsgrundlage.
- OpenStreetMap Tiles: Die Karte laedt Tiles von tile.openstreetmap.org — uebertraegt IP-Adresse. Ist ein Consent-Layer oder Platzhalter ("Karte laden") implementiert?
- Cookies / localStorage: Wird localStorage fuer Favoriten oder View-Preference genutzt? Sind das technisch notwendige Speicherungen (kein Consent noetig) oder Tracking?
- Log-Dateien: Enthalten die Import-Logs personenbezogene Daten? (Dateinamen mit Kundennummern etc.)
- WhatsApp-Links: Wird bei WhatsApp-Buttons die Telefonnummer des Kontakts an wa.me uebertragen? Datenschutzrechtliche Einordnung.
- Kontaktperson-Daten: Sind Maklerdaten (Name, Telefon, E-Mail, Foto) auf der Website oeffentlich — ist das durch Arbeitsvertrag/Einwilligung gedeckt? (Plugin-Ebene: Hinweis in Doku genuegend)
- Gibt es einen wp_privacy_personal_data_exporter und wp_privacy_personal_data_eraser Hook? (DSGVO-Auskunfts- und Loeschrecht)
- Empfehlungen: Welche Massnahmen sollten ergriffen werden um DSGVO-Konformitaet sicherzustellen? (Consent-Layer, Datenschutzhinweise, Datenminimierung, Aufbewahrungsfristen)

Gib am Ende eine priorisierte Top-10-Liste der wichtigsten Verbesserungen.
