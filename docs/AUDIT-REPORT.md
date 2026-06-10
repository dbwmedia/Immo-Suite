# CODE-AUDIT: DBW Immo Suite v1.16.0

**Audit-Datum:** 2026-06-09
**Auditor:** Claude Code (Opus 4.6) + 3 Spezial-Agenten (Security, Frontend-PHP, JS/CSS)
**Scope:** Vollstaendiger Code-Audit (Sicherheit, Qualitaet, Performance, Accessibility, DSGVO)
**Geprueft:** 33 PHP-Dateien, 7 JS-Dateien, 1 CSS-Datei, 3 Templates

---

## Zusammenfassung

- **Geprueft:** 98 von 98 Pruefpunkten
- **Bestanden:** 71
- **Probleme gefunden:** 27 (davon 2 kritisch, 11 wichtig, 14 minor)

**Gesamtbewertung: GUT (82/100)** -- Das Plugin ist solide gebaut mit sauberer Architektur. Die Kern-Sicherheit (SQL, CSRF, Auth, XXE) ist vorbildlich. Es gibt ein kritisches Zip-Slip-Problem, mehrere Accessibility-Luecken im CSS, Performance-Ineffizienzen bei Meta-Queries, und ~30 untranslierte Strings in Templates.

---

## Kritische Sicherheitsprobleme (sofort fixen)

| # | Datei:Zeile | Problem | Risiko | Empfohlener Fix |
|---|-------------|---------|--------|-----------------|
| 1 | `src/Import/Importer.php:101,246,940` | **Zip-Slip Vulnerability**: `$zip->extractTo($temp_dir)` extrahiert ZIP-Inhalte ohne Validierung der Eintrags-Pfade. Ein manipuliertes ZIP koennte Dateien via `../`-Pfade ausserhalb des Zielverzeichnisses schreiben. | **Kritisch** -- Betrifft nur Admins mit Import-Berechtigung, aber ein kompromittiertes Maklersoftware-Export koennte beliebige Dateien auf dem Server ueberschreiben. | Vor `extractTo()` alle ZIP-Eintraege pruefen: `for ($i = 0; $i < $zip->numFiles; $i++) { $name = $zip->getNameIndex($i); if (strpos($name, '..') !== false \|\| $name[0] === '/') { $this->log_debug('Zip-Slip blocked'); $zip->close(); return; } }` |
| 2 | `templates/single-immobilie.php:141` | **XSS via `the_title()`**: `the_title()` gibt den Titel ohne Escaping aus. Ein Post-Titel mit `<script>` wuerde ausgefuehrt. Quelle: OpenImmo-XML `<objekttitel>`. | **Kritisch** -- Angreifer mit Zugang zur Maklersoftware koennte XSS in den Titel injizieren. | `<?php echo esc_html(get_the_title()); ?>` |

---

## Wichtige Probleme (sollte gefixt werden)

| # | Datei:Zeile | Problem | Kategorie | Empfohlener Fix |
|---|-------------|---------|-----------|-----------------|
| 3 | `src/Admin/Settings.php:sanitize()` | **Fehlende Sanitisierung**: `contact_cc_email` fehlt in der `sanitize()`-Methode. Rohwert gelangt in die DB. | Security | `$new_input['contact_cc_email'] = sanitize_email($input['contact_cc_email'] ?? '');` |
| 4 | `src/Core/License.php:90` | **Lizenzschluessel im Klartext in DB**: `update_option(self::OPTION_KEY, $key)` speichert den Schluessel als Klartext in `wp_options`. | Security | Nur den Hash speichern: `update_option(self::OPTION_KEY, hash('sha256', $key))` |
| 5 | `src/Import/Importer.php:1295-1308` | **Log-Verzeichnis nur Apache-geschuetzt**: `.htaccess` schuetzt nicht auf NGINX/LiteSpeed. Import-Logs enthalten Dateipfade. | Info Disclosure | Logs in `wp-content/uploads/dbw-immo-logs/` mit NGINX-Hinweis in Doku, oder `error_log()` nutzen. |
| 6 | `uninstall.php:28-41` | **Unvollstaendige Cleanup**: 8 Customizer-Settings fehlen (`dbw_immo_archive_top_spacing`, `dbw_immo_single_top_spacing`, `dbw_immo_archive_show_price_sqm`, `dbw_immo_single_show_calculator`, `dbw_immo_single_show_infra_score`, `dbw_immo_single_show_price_sqm`, `dbw_immo_single_show_whatsapp`, `dbw_immo_whatsapp_floating`). Ausserdem fehlen: `dbw_immo_license_key`, `dbw_immo_license_status`, `dbw_immo_import_progress` (Transient). | Cleanup | Alle Settings aus `Customizer.php` + License-Optionen aufnehmen. |
| 7 | `assets/css/frontend.css:65-69` | **Accessibility: `outline:none` auf `:focus` ohne `:focus-visible` Fallback**: Keyboard-Fokus auf `input` und `select` wird unsichtbar. Betrifft auch `.dbw-modal__close:2018`, `.dbw-infra-cat-header:3202`, `.dbw-step__back:2094`. | A11y (WCAG 2.2) | `outline:none` nur auf `:focus:not(:focus-visible)` setzen. `:focus-visible` muss einen sichtbaren Indikator behalten. |
| 8 | `templates/single-immobilie.php:377-379` | **Leaflet-Enqueue nach `wp_head()`**: CSS/JS werden im Template (nach `wp_head()`) enqueued. Das Leaflet-CSS kann je nach Theme nicht geladen werden. | WP Standards | Leaflet-Enqueue in `wp_enqueue_scripts`-Hook verschieben, conditional auf `is_singular('immobilie')` + Geo-Koordinaten. |
| 9 | `src/Frontend/InfrastructureScore.php` | **`calculate()` wird 2x aufgerufen**: Einmal in `enqueue_assets()` (wp_enqueue_scripts) und einmal in `render()` (Template). Keine Memoization dazwischen. | Performance | `private static $cache = []` Array nach `$post_id` keyed anlegen und in `calculate()` cachen. |
| 10 | `src/Frontend/FinanceCalculator.php:31-33` | **3 get_post_meta-Aufrufe vor Meta-Cache**: `enqueue_assets()` laeuft in `wp_enqueue_scripts` bevor `the_post()` den Meta-Cache primt. Das sind 3 echte DB-Queries pro Seitenaufruf. | Performance | Check auf spaeteren Hook verschieben oder Meta-Cache manuell primen. |
| 11 | Templates (30+ Stellen) | **Untranslierte deutsche Strings**: `single-immobilie.php` und `expose.php` enthalten ca. 30 hardcodierte deutsche Strings ohne `__()` (z.B. "Wohnflaeche", "Zimmer", "Beschreibung", "Lage", "Grundrisse", "Highlights", "Kaufpreis", "Kaltmiete", "Auf Anfrage"). | i18n | Alle sichtbaren Strings in `__('...', 'dbw-immo-suite')` wrappen. |
| 12 | `assets/css/frontend.css:1801-1813,157-176` | **Unscoped CSS-Selektoren**: `.dbw-cta-phone` und mehrere Selektoren im `prefers-reduced-motion`-Block haben keinen `#dbw-immo-suite`-Prefix. Risiko fuer Theme-Konflikte. | CSS Quality | `#dbw-immo-suite .dbw-cta-phone { ... }` und Scoping in den reduced-motion-Bloecken ergaenzen. |
| 13 | `assets/js/lightbox.js:60` | **Kein `onerror`-Handler auf Lightbox-Bildern**: Wenn ein Bild 404 liefert, bleibt die Lightbox bei `opacity:0` haengen (weisser Bildschirm). | UX / Robustheit | `lbImage.onerror = function() { lbImage.style.opacity = '1'; };` hinzufuegen. |

---

## Kleinere Probleme / Verbesserungen

| # | Datei:Zeile | Problem | Kategorie | Empfohlener Fix |
|---|-------------|---------|-----------|-----------------|
| 14 | `templates/single-immobilie.php:218-219,246` | `echo $index` ohne Escaping (Loop-Counter, sicher aber unkonventionell) | Code Quality | `echo (int) $index` |
| 15 | `src/Frontend/Filter.php:272-286` | Escaping bei Zuweisung statt Ausgabe (funktioniert, aber WP-Anti-Pattern) | Code Quality | `esc_attr()` bei `echo` statt bei Variablenzuweisung |
| 16 | `src/Import/Importer.php:462-466` | Unsanitisierter XML-Attribut als Meta-Key (`distanz_` + `$type`) | Security (Low) | `sanitize_key()` auf `$type` anwenden |
| 17 | `src/Frontend/PriceComparison.php:176,185` | **Doppel-Escaping**: `esc_html($comparison['ort'])` innerhalb von `sprintf()`, Ergebnis dann nochmal mit `esc_html()` ausgegeben. Aus `&` wird `&amp;amp;` in Ortsnamen. | Bug | `esc_html()` nur bei der Ausgabe, nicht innerhalb von `sprintf()`. |
| 18 | `src/Frontend/PdfExpose.php:33` | `get_the_ID()` in `template_redirect` vor `the_post()` -- kann `false`/`0` zurueckgeben. Nonce-Verifikation schlaegt dann fehl. | Bug | `get_queried_object_id()` statt `get_the_ID()` verwenden. |
| 19 | `templates/expose.php:517,583` | `$post_id` ist im Template-Scope nicht definiert -- `isset($post_id)` Guard verhindert Crash, aber InfraScore/PriceComparison werden nie gerendert. | Bug | `$post_id` explizit an Template uebergeben oder aus `$d` extrahieren. |
| 20 | `assets/js/contact-modal.js:11` | Kein Null-Check auf `form` vor Property-Access. Crash wenn `#dbw-contact-form` fehlt. | JS Quality | `if (!form) return;` nach Zeile 7 |
| 21 | `assets/js/view-switch.js:30` | `localStorage.setItem()` ohne try/catch. Crashed in Safari Private Browsing. | JS Quality | `try { localStorage.setItem(...) } catch(e) {}` |
| 22 | `assets/js/finance-calculator.js:123-137` | 13+ DOM-Zugriffe ohne Null-Check. Crash wenn ein erwartetes Element fehlt. | JS Quality | Safe-Setter-Helper: `function set(id, val) { var e = el(id); if(e) e.textContent = val; }` |
| 23 | `assets/js/lightbox.js:11` | closeBtn-Selector `[aria-label]` ist fragil (selektiert erstes Element mit irgendeinem aria-label). | JS Quality | Spezifischeren Selector verwenden: `.dbw-lightbox-btn--close` |
| 24 | `assets/js/finance-calculator.js:~30` | PLZ-Prefix `'11'` (Berlin) fehlt in `PLZ_MAP`. Grunderwerbsteuer fuer PLZ 11xxx faellt auf 5.0% statt 6.0%. | Bug (Data) | `'11': 'Berlin'` zum `PLZ_MAP` hinzufuegen. |
| 25 | `src/Frontend/ContactForm.php:151` | Reply-To Display-Name ohne RFC-5322-Quoting. Sonderzeichen koennten den Header malformen. | Security (Low) | `'Reply-To: "' . str_replace('"', '', $name) . '" <' . $email . '>'` |
| 26 | `assets/css/frontend.css:513` | Dead CSS: `content: '\f178'` (Font Awesome Glyph) wird sofort von `content: '→'` ueberschrieben. | Code Quality | Zeile 513 entfernen. |
| 27 | `src/Frontend/CardRenderer.php:157` | `date_i18n()` Ausgabe ohne `esc_html()`. Theoretisch koennte ein Date-Format HTML enthalten. | Code Quality | `echo esc_html(date_i18n(...))` |

---

## Bestandene Pruefungen (Highlights)

### Sicherheit
- **SQL Injection**: Alle `$wpdb`-Queries verwenden `$wpdb->prepare()` korrekt. Keine direkten Queries ohne Prepare.
- **CSRF**: Alle AJAX-Handler mit `check_ajax_referer()`. License mit `check_admin_referer()`. Settings mit `settings_fields()`.
- **Auth**: Alle Admin-AJAX nur als `wp_ajax_` (nicht nopriv). `current_user_can('manage_options')` durchgehend.
- **XXE**: `safe_load_xml()` mit `LIBXML_NONET` und PHP 8.0+ Kompatibilitaet.
- **Path Traversal**: `realpath()` + Basis-Pfad-Validierung in `upload_image()`, `ajax_process_batch()`, `ajax_validate_path()`.
- **Upload Whitelist**: Nur jpg/jpeg/png/gif/webp/pdf. Kein SVG (Stored-XSS-Risiko eliminiert).
- **E-Mail**: Newlines aus Name entfernt. `is_email()` Validierung. `sanitize_email()` fuer Empfaenger.
- **Rate Limiting**: 2 Min pro E-Mail+IP mit Transient. Honeypot mit Fake-Success-Response.

### Code-Qualitaet
- **PSR-4 Autoloader** korrekt. **ABSPATH-Guards** in allen src/-Dateien. **Namespace** konsistent.
- **`wp_reset_postdata()`** nach allen Custom Queries.
- **Sanitize Callback** in Settings.php mit korrekten Funktionen pro Feldtyp.
- **Error Handling**: try/catch mit Throwable im Import. `wp_send_json_error()` konsistent.

### Performance
- **Conditional Asset Loading**: CSS/JS nur auf relevanten Seiten. Lightbox/Modal nur auf Single.
- **Bildoptimierung**: `srcset`, `sizes`, `width`/`height`, `loading="lazy"`, `fetchpriority="high"`.
- **Transient Caching**: PriceComparison cached Portfolio-Durchschnitt.

### Frontend
- **CSS Custom Properties**: 101 Referenzen. `#dbw-immo-suite` Scoping: 248 Referenzen.
- **`prefers-reduced-motion`**: 6 Bloecke. **Print Styles**: 4 Bloecke.
- **Vanilla JS**: Kein jQuery im Frontend. IIFE/Closure Pattern.
- **Accessibility**: `<dialog>` mit `showModal()` (nativer Focus Trap), ARIA-Labels, Keyboard-Navigation.

### DSGVO
- **Keine PII in DB**. Kontakte nur per E-Mail. **Privacy API** registriert. **Leaflet lokal** (kein CDN).

---

## Empfehlungen (priorisiert)

### Prioritaet 1 -- Sofort (Security)
1. **Zip-Slip Fix** (#1): ZIP-Eintraege vor Extraktion validieren.
2. **`the_title()` escapen** (#2): `echo esc_html(get_the_title())` in single-immobilie.php.

### Prioritaet 2 -- Naechstes Release
3. **Accessibility-Fixes** (#7): `outline:none` auf `:focus:not(:focus-visible)` beschraenken.
4. **Leaflet-Enqueue** (#8): In `wp_enqueue_scripts`-Hook verschieben.
5. **contact_cc_email sanitize** (#3), **License Key hashen** (#4), **Log-Verzeichnis** (#5).
6. **Uninstall.php** (#6): Alle fehlenden Settings aufnehmen.
7. **Doppel-Escaping PriceComparison** (#17) und **$post_id in expose.php** (#19) fixen.
8. **PdfExpose Nonce** (#18): `get_queried_object_id()` verwenden.
9. **PLZ 11 Berlin** (#24): PLZ_MAP ergaenzen.

### Prioritaet 3 -- Spaeter
10. **Performance**: InfrastructureScore Memoization (#9), FinanceCalculator Meta-Cache (#10).
11. **i18n**: 30+ Strings in Templates translieren (#11).
12. **CSS Scoping** (#12): Fehlende `#dbw-immo-suite`-Prefixe ergaenzen.
13. **JS Robustheit** (#20-23): Null-Checks, try/catch, spezifischere Selektoren.
14. **Late Escaping** (#15), **sanitize_key** (#16), **Reply-To Quoting** (#25).

---

## Fazit

Das Plugin ist **ueberdurchschnittlich gut** fuer ein WordPress-Plugin in dieser Groessenordnung. Die Architektur ist sauber (PSR-4, Loader Pattern, Namespace), die Kern-Sicherheit (SQL, CSRF, Auth, XXE, Path Traversal) ist vorbildlich, und die UX-Features (Multi-Step Modal, Finance Calculator, Infra Score) sind technisch ausgereift.

Die zwei kritischen Issues (Zip-Slip, `the_title()`) sind durch Admin-only-Kontext bzw. OpenImmo-XML-Quelle in ihrer Ausnutzbarkeit begrenzt, sollten aber sofort behoben werden. Die Accessibility-Luecken im CSS (fehlende Focus-Indikatoren) betreffen die WCAG-Konformitaet und sollten im naechsten Release adressiert werden.

**Score: 82/100**
