# Prompt: Preis-pro-m²-Vergleich

---

Baue fuer das WordPress-Plugin "dbw Immo Suite" (v1.10.0) eine Preis-pro-Quadratmeter-Anzeige mit Vergleichswert auf der Einzelansicht und optional auf den Karten in der Archivansicht.

## Kontext

Das Plugin zeigt bereits Kaufpreis/Kaltmiete und Wohnflaeche an. Fuer Kaeufer ist der **Preis pro m²** die wichtigste Vergleichskennzahl — er wird aber nirgends berechnet oder angezeigt. Noch wertvoller: Ein Vergleich mit dem Durchschnitt der gleichen Stadt oder Objektart, damit der Besucher sofort einschaetzen kann ob der Preis fair ist.

## Vorhandene Daten

**Pro Objekt (Post-Meta):**
- `kaufpreis` — Kaufpreis als Float
- `kaltmiete` — Kaltmiete als Float
- `wohnflaeche` — Wohnflaeche in m² als Float
- `ort` — Stadt/Ortsname

**Pro Objektart (Taxonomy):**
- `objektart` — z.B. "Wohnung", "Haus", "Grundstueck"

**Fuer Durchschnittsberechnung:**
- Alle veroeffentlichten Immobilien im gleichen Ort und/oder gleicher Objektart (via `WP_Query` + Meta-Aggregation)

## Anforderungen

### 1. Berechnung

**Preis pro m²:**
```
preis_pro_qm = kaufpreis / wohnflaeche  (Kauf)
preis_pro_qm = kaltmiete / wohnflaeche  (Miete)
```
Nur berechnen wenn beide Werte > 0.

**Durchschnitt (dynamisch aus eigenem Bestand):**
```sql
AVG(kaufpreis / wohnflaeche) WHERE ort = '{gleicher_ort}' AND kaufpreis > 0 AND wohnflaeche > 0
```
- Getrennt fuer Kauf und Miete
- Optional zusaetzlich nach Objektart filtern
- Mindestens 3 Vergleichsobjekte noetig, sonst keinen Vergleich anzeigen
- **Transient-Cache:** Durchschnitt pro Ort als Transient cachen (24h), nicht bei jedem Seitenaufruf neu berechnen

**Abweichung:**
```
abweichung_prozent = ((preis_pro_qm - durchschnitt) / durchschnitt) * 100
```

### 2. Anzeige — Einzelansicht

**Position:** In der Highlights-Sidebar (unterhalb der bestehenden Preis-Zeile) ODER als eigene kompakte Info-Zeile.

**Design — Kompakte Variante (empfohlen):**
```
┌──────────────────────────────────────────┐
│  Preis pro m²           3.853 €/m²      │
│  ▼ 12% unter dem Schnitt in Leinfelden  │
│  ████████████░░░░  Durchschnitt: 4.380 € │
└──────────────────────────────────────────┘
```

**Elemente:**
- **Preis pro m²** gross und prominent
- **Vergleichsbalken:** Horizontaler Balken der zeigt wo das Objekt im Vergleich liegt
  - Marker fuer aktuelles Objekt
  - Bereich: Min bis Max der Vergleichsobjekte
  - Durchschnitt als Mittellinie
- **Abweichungs-Badge:**
  - Gruen + Pfeil runter: "12% unter dem Schnitt" (guenstiger)
  - Grau/Neutral: "Im Durchschnitt" (±5%)
  - Orange + Pfeil hoch: "8% ueber dem Schnitt" (teurer)
- **Disclaimer-Zeile:** "Basierend auf X aktiven Objekten in {Ort}" (klein, grau)

**Fuer Mietobjekte:**
- "Miete pro m²: 12,50 €/m²"
- Vergleich analog

### 3. Anzeige — Archiv-Karten (Optional)

Kleines Badge auf der Karte (aehnlich dem Energie-Flag):
- Nur wenn aktiviert im Customizer
- Kompakt: "3.853 €/m²"
- Farbe je nach Abweichung (Gruen/Grau/Orange)

### 4. Backend-Einstellungen

**Settings-Seite (Tab "Darstellung"):**

| Feld | Typ | Default | Beschreibung |
|------|-----|---------|-------------|
| `show_price_per_sqm` | Checkbox | An | Preis/m² auf Einzelansicht anzeigen |
| `show_price_per_sqm_comparison` | Checkbox | An | Vergleich mit Durchschnitt anzeigen |
| `show_price_per_sqm_archive` | Checkbox | Aus | Preis/m² auf Karten in Archivansicht |
| `price_per_sqm_min_comparables` | Number | 3 | Mindestanzahl Vergleichsobjekte fuer Durchschnittsanzeige |
| `price_per_sqm_cache_hours` | Number | 24 | Cache-Dauer fuer Durchschnittswerte in Stunden |

**Customizer:**
- Toggle: `dbw_immo_single_show_price_sqm` (default: true)
- Toggle: `dbw_immo_archive_show_price_sqm` (default: false)

### 5. Technische Umsetzung

**PHP:** Neue Klasse `src/Frontend/PriceComparison.php`

```php
namespace DBW\ImmoSuite\Frontend;

class PriceComparison {
    public static function calculate($post_id) { ... }
    public static function get_area_average($ort, $type = 'kauf') { ... }
    public static function render_single($post_id) { ... }
    public static function render_archive_badge($post_id) { ... }
}
```

**Durchschnittsberechnung (effizient):**
```php
// Direkte DB-Query statt WP_Query fuer Performance
global $wpdb;
$avg = $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(CAST(pm_price.meta_value AS DECIMAL(12,2)) / CAST(pm_area.meta_value AS DECIMAL(10,2)))
     FROM {$wpdb->posts} p
     INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = %s
     INNER JOIN {$wpdb->postmeta} pm_area ON p.ID = pm_area.post_id AND pm_area.meta_key = 'wohnflaeche'
     INNER JOIN {$wpdb->postmeta} pm_ort ON p.ID = pm_ort.post_id AND pm_ort.meta_key = 'ort'
     WHERE p.post_type = 'immobilie' AND p.post_status = 'publish'
     AND pm_ort.meta_value = %s
     AND CAST(pm_price.meta_value AS DECIMAL(12,2)) > 0
     AND CAST(pm_area.meta_value AS DECIMAL(10,2)) > 0",
    $price_key, $ort
));
```

**Caching:**
```php
$cache_key = 'dbw_avg_sqm_' . sanitize_key($ort) . '_' . $type;
$avg = get_transient($cache_key);
if (false === $avg) {
    $avg = self::calculate_average($ort, $type);
    set_transient($cache_key, $avg, $cache_hours * HOUR_IN_SECONDS);
}
```

**Cache Invalidierung:** Hook auf `save_post_immobilie` → relevante Transients loeschen.

**CSS:** In `assets/css/frontend.css` ergaenzen
- Vergleichsbalken als CSS mit `linear-gradient` oder `:before`/`:after`
- Abweichungs-Badge Farben: `--dbw-price-below: #28a745`, `--dbw-price-above: #e67e22`, `--dbw-price-neutral: var(--dbw-gray)`
- Responsive: Balken skaliert mit Container
- Print: Balken-Farben mit `print-color-adjust: exact`

**JS:** Kein JavaScript noetig (reine PHP-Berechnung + HTML).

**Integration:**
- Single-Template: Aufruf in Sidebar (Highlights-Bereich) oder als eigene Zeile
- Archive: Aufruf in `CardRenderer::render()` (optional, nach Energie-Flag)
- Expose: Preis/m² in Eckdaten-Tabelle aufnehmen (Seite 2)
- Schema.org: `priceSpecification` um `unitText: "SQM"` erweitern

**Sonstiges:**
- ABSPATH Guard, namespace `DBW\ImmoSuite\Frontend`
- Zahlen im deutschen Format (`number_format($val, 0, ',', '.')`)
- Du/Sie in Disclaimer-Text (`dbw_anrede()`)
- Alle Texte in `__()` fuer i18n
