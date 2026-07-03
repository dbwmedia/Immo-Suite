# CODE-AUDIT: DBW Immo Suite v2.1.3 (Re-Audit)

**Audit-Datum:** 2026-07-03
**Auditor:** Claude Code (Fable 5) + 4 Spezial-Agenten (Security, Performance, DSGVO, Frontend/UX)
**Scope:** Vollstaendiger Re-Audit nach v2.0-Release, inkl. Verifikation aller Findings aus dem v1.16-Audit (AUDIT-REPORT.md)
**Ergebnis-Release:** v2.2.0 (alle kritischen + wichtigen Findings behoben)

---

## Zusammenfassung

- **Alle 7 Findings aus dem v1.16-Audit sind im Code verifiziert gefixt** (Zip-Slip, the_title-XSS, contact_cc_email, Lizenz-Hash, Log-Schutz, Meta-Key-Sanitize, Reply-To-Quoting).
- Der v2.0-Code (AJAX-Filter, Favorites, ArchiveMap, ExposeRequest, PdfExpose, Import-Batching) ist sicherheitstechnisch ueberdurchschnittlich sauber: durchgaengig Nonce-/Cap-Checks, `$wpdb->prepare`, Escaping, keine SSRF-/unserialize-/REST-Angriffsflaeche.
- **Keine kritischen Security-Findings.** Ein DSGVO-kritischer Default-Bug und ein produktionsrelevanter Cache-Bug wurden gefunden und in v2.2.0 behoben.

## Top-Findings (alle in v2.2.0 behoben)

| # | Bereich | Finding | Fix in v2.2.0 |
|---|---------|---------|----------------|
| 1 | DSGVO (kritisch) | Single-Karte lud OSM-Tiles ohne Consent, wenn Customizer nie gespeichert (Template-Default `false` statt `true`, single-immobilie.php) | Default auf `true` |
| 2 | Bug (hoch) | Nonces in gecachten Seiten: Kontaktformular, Expose-Anfrage und PDF-Link brachen nach 12-24h Page-Cache still | Nonce-Refresh per AJAX beim Modal-Oeffnen; PDF-Link mit HMAC-Signatur |
| 3 | Performance (hoch) | Autoload-Leak: eine autogeladene Option pro ZIP-Dateiname (unbegrenzt wachsend) | Eine Option, autoload=false, Cap 20, Migration |
| 4 | Security (wichtig) | ContactForm akzeptierte nicht-veroeffentlichte Objekte (Enumeration); Rate-Limit per E-Mail umgehbar | publish-Check + IP-only-Key |
| 5 | Performance (hoch) | Import: 1 Objekt pro AJAX-Request + kompletter XML-Reparse pro Request | 8 Objekte pro Batch |
| 6 | UX (hoch) | FOUC beim View-Restore; Skeleton 220px vs. Karte 280px (Layout-Shift); Fehler-Fallback = harter Reload | Inline-Restore vor Paint; Hoehe angeglichen; Retry+Toast+Restore |
| 7 | A11y (hoch) | Ergebniszaehler ohne aria-live; Badge-Kontraste unter WCAG AA | role=status/aria-live; dunklere Badge-Farben |
| 8 | Cleanup | uninstall.php: Histogramm-/avg_sqm-/Rate-Limit-Transients fehlten | ergaenzt inkl. Timeout-Zeilen |

Weitere behobene Punkte: Marker-Payload nur bei aktiver Kartenansicht, "Aehnliche Objekte"-Query mit Cache-Priming, GC ohne N+1, Dashicons-Font durch Inline-SVGs ersetzt (~85KB), defer auf allen Scripts, AbortController im Filter, Favorites-Tab-Sync, Toast-aria-live-Region frueh, reduced-motion fuer Smooth-Scrolling, Scroll-Spy-Jank-Guard, Lightbox-Timeout-Cleanup, Histogramm-Invalidierung bei Trash/Delete, :focus-visible in Modals, Chips-/Sort-Labels.

## DSGVO-Paket (neu in v2.2.0)

- Consent-Zeitstempel in Anfrage-Mails (Art.-7-Nachweis).
- Einstellungs-Tab "Datenschutz" mit kopierbarem Datenschutzerklaerungs-Baustein + Betreiber-Hinweisen (AVV, Speicherdauer).
- Empfehlung fuers Marketing: "datenschutzfreundlich by design" statt "DSGVO-konform" (konform kann nur der Betreiber sein).

## Bewusst offen gelassen (nice-to-have)

- Pfad-Prefix-Checks ohne Trennzeichen-Vergleich (Importer/Settings, admin-only, kein direkt ausnutzbarer Bug) — `trailingslashit()` beim Vergleich waere sauberer.
- Custom-Importpfad wird beim Speichern nicht auf ABSPATH beschraenkt (nur im AJAX-Validator; Admin = vertrauenswuerdig im WP-Threat-Model).
- Restliche Inline-Styles in single-immobilie.php (~40 Stellen, groesste Frontend-Altlast; Umzug nach frontend.css lohnt als eigenes Refactoring).
- CSS-Breakpoints vereinheitlichen (480/600/640/768/900/1024 gemischt), Minification der Assets.
- Restliche i18n-Kleinigkeiten (" Zi." in CardRenderer, sprintf-Muster).
- md5(IP) ist Pseudonymisierung, keine Anonymisierung — in Doku korrekt benannt (Datenschutz-Tab).

## Naechste Schritte (Roadmap, unveraendert)

Siehe ROADMAP.md: Anfragen-Inbox als CPT → Suchagent → Objekt-Statistiken → License Control Center; danach Auto-Geocoding (Nominatim) fuer Karte/Infra-Score bei Feeds ohne Geo-Daten.
