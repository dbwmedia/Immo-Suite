# Dynamic Location Source (v2.1.0)

## Ziel

Grid- und Referenzen-Block (sowie Shortcodes) koennen den Ort-Filter automatisch aus der aktuell aufgerufenen Seite beziehen, statt ihn fest im Editor zu waehlen.

**Use-Case:** Geo-Landing-Pages mit einem GP-Elements-Template fuer den CPT `standort`. Ein Template, viele Standort-Seiten - das Grid zeigt automatisch die Immobilien des jeweiligen Ortes.

## Architektur

### LocationResolver (`src/Frontend/LocationResolver.php`)

Zentrale statische Methode `LocationResolver::resolve()` mit 3-stufiger Aufloesung:

1. **Taxonomie-Archiv:** `is_tax('ort')` → Term-Slug des Archivs
2. **Singular + Meta/ACF:** Meta-Feld `ort_name` (per Filter `dbw_immo_location_meta_key` aenderbar), erst `get_field()` (ACF), dann `get_post_meta()` als Fallback
3. **Singular + zugewiesener `ort`-Term:** `get_the_terms($post_id, 'ort')` → erster Term-Slug

Ergebnis laeuft durch Filter `dbw_immo_resolved_location($slug, $queried_object)`.

### Block-Integration

Neues Attribut `locationSource` (default: `manual`, Werte: `manual` | `current`) in `block.json` beider Bloecke.

- **Editor:** ToggleControl "Ort automatisch aus aktueller Seite" im Filter-Panel. Bei `current` wird der manuelle Ort-Dropdown ausgeblendet.
- **PHP-Render:** Bei `locationSource === 'current'` wird `LocationResolver::resolve()` aufgerufen. Leerer Resolver = leere Ausgabe (kein ungefiltertes Voll-Grid).

### Shortcode-Integration

`location="current"` als Sonderwert in `[dbw_immo_grid]` und `[dbw_immo_references]`. Loest den Ort ueber denselben Resolver auf.

## Filter-Referenz

| Filter | Default | Beschreibung |
|--------|---------|-------------|
| `dbw_immo_location_meta_key` | `ort_name` | Meta-Key fuer die Ort-Aufloesung aus Post-Meta |
| `dbw_immo_resolved_location` | (resolved slug) | Ueberschreibt den aufgeloesten Ort-Slug |

## Beispiele

### Block (Gutenberg)
Toggle "Ort automatisch aus aktueller Seite" im Inspector aktivieren.

### Shortcode
```
[dbw_immo_grid location="current" count="6" columns="3"]
[dbw_immo_references location="current" count="6"]
```

### Filter: Meta-Key aendern
```php
add_filter('dbw_immo_location_meta_key', function() {
    return 'city_name'; // Custom meta field
});
```

### Filter: Ort ueberschreiben
```php
add_filter('dbw_immo_resolved_location', function($slug, $queried_object) {
    if ($queried_object instanceof WP_Post && $queried_object->post_type === 'my_cpt') {
        return 'custom-slug';
    }
    return $slug;
}, 10, 2);
```
