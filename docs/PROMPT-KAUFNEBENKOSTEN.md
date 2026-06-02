# Prompt: Kaufnebenkosten- & Finanzierungsrechner

---

Baue fuer das WordPress-Plugin "dbw Immo Suite" einen interaktiven Kaufnebenkosten- und Finanzierungsrechner als neue Section auf der Einzelansicht (`single-immobilie.php`).

## Kontext

Das Plugin verwaltet Immobilien als Custom Post Type "immobilie". Folgende Meta-Felder sind relevant und bereits vorhanden:
- `kaufpreis` — Kaufpreis als Float
- `kaltmiete` — Kaltmiete (wenn Mietobjekt)
- `provision_kaeufer` — Kaeufer-Provision als Text (z.B. "3,57% inkl. MwSt.")
- `plz` — Postleitzahl (fuer Bundesland-Erkennung → Grunderwerbsteuer)
- `ort` — Stadt

Der Rechner soll **nur bei Kaufobjekten** angezeigt werden (wenn `kaufpreis > 0`).

## Anforderungen

### 1. Customizer-Integration
- Neuer Toggle im Customizer: "Finanzierungsrechner anzeigen" (default: true)
- Abschnitt: `dbw_immo_single_show_calculator`
- Nur rendern wenn aktiviert UND Kaufpreis vorhanden

### 2. Kaufnebenkosten-Berechnung (automatisch)
Zeige eine aufgeschluesselte Tabelle:
- **Kaufpreis** (aus Meta)
- **Grunderwerbsteuer** — automatisch nach Bundesland anhand PLZ:
  - Baden-Wuerttemberg: 5,0%
  - Bayern: 3,5%
  - Berlin: 6,0%
  - Brandenburg: 6,5%
  - Bremen: 5,0%
  - Hamburg: 5,5%
  - Hessen: 6,0%
  - Mecklenburg-Vorpommern: 6,0%
  - Niedersachsen: 5,0%
  - Nordrhein-Westfalen: 6,5%
  - Rheinland-Pfalz: 5,0%
  - Saarland: 6,5%
  - Sachsen: 5,5%
  - Sachsen-Anhalt: 5,0%
  - Schleswig-Holstein: 6,5%
  - Thueringen: 5,0%
- **Notarkosten**: 1,5% vom Kaufpreis
- **Grundbuchamt**: 0,5% vom Kaufpreis
- **Maklerprovision**: Wert aus `provision_kaeufer` parsen (Prozentsatz extrahieren), oder 0 wenn leer
- **Gesamtkosten** (Kaufpreis + alle Nebenkosten)

### 3. Finanzierungsrechner (interaktiv)
Drei Slider/Input-Felder die der User anpassen kann:
- **Eigenkapital** (Slider: 0€ bis Kaufpreis, Default: 20% vom Gesamtpreis)
- **Zinssatz** (Slider: 1,0% bis 6,0%, Default: 3,5%, Step: 0,1%)
- **Tilgung** (Slider: 1,0% bis 5,0%, Default: 2,0%, Step: 0,1%)

Ergebnis live berechnen (ohne Reload):
- **Darlehenssumme** = Gesamtkosten - Eigenkapital
- **Monatliche Rate** = Darlehenssumme × (Zinssatz + Tilgung) / 12
- **Zinskosten nach 10 Jahren** (vereinfacht)

### 4. UI/UX Design
- Eigene `.dbw-section` im Template (unterhalb Beschreibung, oberhalb Energie)
- Style konsistent mit dem Rest des Plugins (CSS Custom Properties nutzen)
- Slider mit Live-Update (JavaScript, kein jQuery)
- Zahlen im deutschen Format (1.000,00)
- Mobile-responsive (Slider untereinander statt nebeneinander)
- `prefers-reduced-motion` respektieren (keine Slider-Animationen)
- Ergebnis-Box visuell hervorgehoben (aehnlich Highlights-Card)

### 5. Technische Umsetzung
- **PHP**: Neue Klasse `src/Frontend/FinanceCalculator.php`
- **JS**: Neue Datei `assets/js/finance-calculator.js` (Vanilla JS, kein jQuery)
- **CSS**: Styles in `assets/css/frontend.css` ergaenzen
- **Registrierung**: In `Plugin.php` eintragen, JS nur auf Single-Immobilie laden
- PLZ-zu-Bundesland-Mapping als JS-Objekt (die ersten 1-2 Ziffern der PLZ genuegen fuer die Zuordnung)
- Daten via `wp_localize_script()` an JS uebergeben (Kaufpreis, Provision, PLZ)
- ABSPATH Guard in PHP-Datei, namespace `DBW\ImmoSuite\Frontend`
- Du/Sie System beruecksichtigen (`dbw_anrede()`)
