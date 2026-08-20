# Audit-Report 2026-08 — Import-Schicht + Gesamt-Plugin (v2.7.0 → v2.8.0)

Stand: 2026-08-20. Vollreview der Import-Schicht (verifiziert gegen das offizielle
OpenImmo-1.2.7-XSD) plus Review des restlichen Plugins (Frontend-Endpunkte,
Templates/JS, Admin/Core) durch drei parallele Review-Durchgaenge.
**Alle Befunde wurden in v2.8.0 behoben** (Details: CHANGELOG.md).

---

## Kritische Befunde (behoben in v2.8.0)

| # | Befund | Fundstelle (vor Fix) | Schwere |
|---|--------|----------------------|---------|
| B1 | DELETE-Aktionen nie erkannt: Code las Attribut `actiontype`, Standard definiert `aktionart`. Geloeschte Objekte blieben veroeffentlicht. | Importer.php (import_property) | hoch |
| B2 | Kein Lock im AJAX-Batch-Pfad: stuendlicher Cron konnte dieselben ZIPs parallel verarbeiten (Duplikate, verlorene renames). | Importer.php | hoch |
| B3 | Garbage Collection ignorierte `<uebertragung umfang="VOLL/TEIL">`: Teillieferung + GC-Setting = restlicher Bestand archiviert. | Importer.php (run_garbage_collection) | hoch |
| B4 | Vermarktungsart aus Preisen geraten statt aus `<vermarktungsart>`-Element: "Preis auf Anfrage"-Objekte ohne Kauf/Miete-Term = unfilterbar. | Importer.php (map_fields) | mittel-hoch |
| B5 | `energieverbrauchkennwert` nie importiert: Verbrauchsausweise ohne Kennwert im Expose (GEG-Pflichtangabe, § 87 GEG). | Importer.php (map_fields) | mittel-hoch |
| S1 | Stored XSS: JSON-LD-Ausgabe ohne JSON_HEX_TAG, `</script>` in Feed-Daten konnte ausbrechen. | SchemaOutput.php | hoch |
| S2 | Stored XSS: Geo-Koordinaten mit esc_js() in nacktem numerischen JS-Kontext der Karten-Init. | single-immobilie.php | hoch |
| A1 | `reference_page_id` ueberlebte kein Settings-Speichern: Referenz-Rewrites faktisch tot. | Settings.php (sanitize) | hoch |

## Weitere Befunde (behoben)

- `unterkellert['kpiuell']`: erfundener Attributname (XSD: `keller`) — Keller-Feature nie erkannt
- Ort/Objektart-Terme akkumulierten (append statt replace)
- Import-Historie im Cron-Pfad zeigte Laufsummen statt Datei-Zahlen
- Batch-Pfad verarbeitete nur den ersten `<anbieter>`-Knoten
- Cron-Erstimport ohne Zeitbudget (Timeout-Tod statt Fortsetzung)
- tmp_-Verzeichnisse und .processed-Dateien akkumulierten unbegrenzt
- Kein Zip-Bomben-Limit; Praefix-Pfadvergleiche akzeptierten Namensvettern
- Taxonomie-404 nach Aktivierung (Flush ohne Taxonomien)
- Expose-Rate-Limit verbrannte bei Validierungsfehlern
- MediaTools: endgueltige Loeschungen ohne Bestaetigung
- Update-Checker-require ohne Guard (fehlendes vendor/ = Fatal)
- Inquiry-Status ab `edit_posts` statt `edit_post`; Inquiry-Cleanup auf 100/Tag gedeckelt

## Methodik-Notizen

- Attributnamen (`aktionart`, `keller`, `verkaufstatus[stand]`, `uebertragung[umfang]`,
  `vermarktungsart[KAUF|MIETE_PACHT|ERBPACHT|LEASING]`) und energiepass-Kinder wurden
  direkt am XSD openimmo_127.xsd verifiziert, nicht aus Doku-Sekundaerquellen.
- Parsing-Fixes per Fixture-XML smoke-getestet (Multi-Anbieter, VOLL-Erkennung,
  DELETE/REFERENZ, Verbrauchskennwert-Fallback).
- Live-Verifikation am Staging betz-immobilien.com: Geister-Objekt mit Fallback-Titel
  (durchgelaufener DELETE-Satz), fehlender Energie-Abschnitt (Verbrauchsausweis-Fall),
  "Auf Anfrage"-Objekt ohne Vermarktungs-Term.

## Bewusst NICHT gemacht (naechste Runde)

1. **Import-Kern-Konsolidierung**: run_import (Cron) und prepare/batch/finalize
   (Dashboard) duplizieren ~150 Zeilen und sind nachweislich schon divergiert.
   Vorher Fixture-Tests als Netz aufbauen. (Aufwand 2-3 Tage, Risiko mittel)
2. **ArchiveMap**: collect_markers() laeuft bei jedem Archiv-Aufruf ungecacht,
   obwohl die Karte initial versteckt ist. Lazy-Load per AJAX oder Transient.
3. **Bot-Schutz der Formulare**: nur Honeypot + 120s-IP-Transient (Nonce ist wegen
   Page-Cache bewusst schwach). Mindest-Ausfuellzeit oder Tageskontingent ergaenzen.
4. **Fixture-Tests + PHPCS/PHPStan in CI**: haette B1/B4/B5/kpiuell allesamt gefangen.
   Hoechster Return pro Aufwandsstunde im ganzen Tooling-Bereich.
5. **Feld-Mapping nicht konfigurierbar** (Stand der Technik bei immonex): erst bei
   konkretem Kundenbedarf.

## Zusaetzlich in v2.8.0 (Feature-Schicht)

Wochenbericht-Mail (Mo 07:00), Expose-View-Counter (+ Admin-Spalte), Import-Testlauf
(Dry-Run) + Log-Viewer im Dashboard, 301 statt 404 fuer geloeschte Objekte,
taegliche Telemetrie an dbw media (**OS-Endpoint muss noch gebaut werden**:
`https://os.dbw-media.de/api/immo-telemetry.php`, Payload siehe `Telemetry::payload()`),
Settings-UI-Relaunch (vertikale Nav, Cards, Toggles).
Suchagent bewusst auf eigenes Release verschoben.
