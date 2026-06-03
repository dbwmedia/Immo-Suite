# dbw Immo Suite — Offene Punkte & Roadmap

Stand: 2026-06-03 (nach v1.12.0)

---

## Bekannte Altlasten (kein Launch-Blocker)

### Code Quality
- [ ] **Inline-Styles im Single-Template** — ~50+ `style="..."` Attribute in `single-immobilie.php` (Highlights-Card, Agent-Card, Sidebar, Gallery-Buttons). In CSS-Klassen in `frontend.css` auslagern.
- [ ] **i18n fehlend** — ~20 hardcoded deutsche Strings im Single-Template ohne `__()`: "Wohnflaeche", "Zimmer", "Grundstueck", "Beschreibung", "Ausstattung", "Lage", "Entfernungen", "Grundrisse", "Highlights", "Kaufpreis", "Kaltmiete" etc.
- [ ] **Settings Callbacks** — `text_callback()` und `checkbox_callback()` geben `$desc`/`$label` ohne Escaping aus (derzeit nur hardcoded Strings, aber unsicheres Pattern). `wp_kses_post()` nutzen.
- [ ] **jQuery in admin.js** — Einzige Datei mit jQuery, Rest ist Vanilla. Kein Blocker (WP Admin nutzt jQuery), aber Inkonsistenz.
- [ ] **CSS !important** — ~75x im Stylesheet (Grossteil Print-Styles — vertretbar, aber aufraeum-wuerdig).

### Performance
- [ ] **Importer: ~25 update_post_meta() pro Property** — `map_fields()` macht einzelne DB-Writes. Batch-Update waere effizienter bei grossen Imports (200+ Objekte).
- [ ] **Leaflet von CDN ohne SRI** — `unpkg.com` Abhaengigkeit ohne `integrity`-Attribut. Entweder self-hosten oder SRI-Hash setzen.
- [ ] **Doppelter get_post_custom()** — `single-immobilie.php` Zeile 16 und Zeile 368 (Distanz-Felder). Zweiten Aufruf entfernen.

### Accessibility
- [ ] **Gallery-Slider Keyboard-Navigation** — Horizontal-Scroll hat keine Arrow-Key-Shortcuts. Nur Tab auf einzelne Slides moeglich.
- [ ] **Lightbox aria-labelledby** — Hat `aria-label` aber kein dynamisches Label mit Bildnummer. Besser: `aria-labelledby` auf Counter-Element.

### SEO
- [ ] **og:url auf Archivseiten** — `output_archive_meta_tags()` gibt kein `og:url` aus.
- [ ] **og:image auf Archivseiten** — Kein Default-Bild (z.B. Logo) fuer Social Shares von Archiv-/Taxonomieseiten.
- [ ] **og:type** — Aktuell `website` auf Single-Seiten, `article` oder `product` waere passender.

### Sicherheit (Niedrig)
- [ ] **Log-Schutz nur Apache** — `.htaccess` schuetzt Logs nur auf Apache. Nginx braucht eigene Config (Doku-Hinweis reicht).
- [ ] **@-Operator** — `@unlink()`, `@file_put_contents()` in Importer. Maskiert Fehlerursachen.

---

## Feature-Ideen (v2.0+)

- [x] ~~Kaufnebenkosten- & Finanzierungsrechner~~ (v1.9.0)
- [x] ~~PDF-Expose-Download~~ (v1.10.0 — standalone Expose-Seite mit Print/PDF)
- [x] ~~Energiekosten-Rechner~~ (v1.11.0 — inline in Energieausweis-Sektion mit Slider)
- [x] ~~Infrastruktur-Score~~ (v1.11.0 — Walk-Score-Style mit SVG-Ring + Kategorie-Bars)
- [x] ~~WhatsApp-Button~~ (v1.12.0 — Sidebar + Floating + Modal-Integration)
- [x] ~~Preis-pro-qm-Vergleich~~ (v1.12.0 — mit Bestandsdurchschnitt, positiv-only)
- [ ] REST API Endpoints
- [ ] Immobilien-Vergleich (Side-by-Side)
- [ ] Merkliste/Favoriten (localStorage)
- [ ] E-Mail-Alerts bei neuen passenden Objekten
