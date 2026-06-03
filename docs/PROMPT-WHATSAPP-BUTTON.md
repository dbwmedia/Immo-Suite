# Prompt: WhatsApp-Kontakt-Button

---

Baue fuer das WordPress-Plugin "dbw Immo Suite" (v1.10.0) einen WhatsApp-Kontakt-Button als zusaetzliche Kontaktoption auf der Einzelansicht und optional als Floating-Button.

## Kontext

Das Plugin hat bereits ein Multi-Step-Kontaktformular (`ContactModal.php`) mit CTA-Button in der Sidebar und einen Telefon-Link. WhatsApp ist im Immobilienmakler-Geschaeft einer der wichtigsten Kommunikationskanaele — viele Kunden bevorzugen WhatsApp ueber E-Mail oder Telefon.

## Vorhandene Daten

- `kontaktperson_tel` — Telefonnummer des Ansprechpartners (wird bereits mit `dbw_format_phone()` normalisiert zu +49-Format)
- `kontaktperson_vorname` + `kontaktperson_name` — Name des Ansprechpartners
- Property-Titel via `get_the_title()`
- Property-URL via `get_permalink()`

## Anforderungen

### 1. WhatsApp-Link-Generierung

**URL-Format:**
```
https://wa.me/{nummer_ohne_plus}?text={vorbefuellte_nachricht}
```

**Nummer:** `kontaktperson_tel` normalisiert (nur Ziffern, kein +, kein Leerzeichen). Beispiel: `4915122632768`

**Vorbefuellte Nachricht (URL-encoded):**
```
Hallo {Ansprechpartner-Vorname},

ich interessiere mich fuer diese Immobilie:
{Objekt-Titel}
{Objekt-URL}

Koennten Sie/Koenntest du mir weitere Informationen zukommen lassen?
```
- Du/Sie-System beruecksichtigen (`dbw_anrede()`)
- Nachricht per `rawurlencode()` encoden

### 2. Platzierungen

**A) Sidebar CTA-Stack (Primaer)**
Neuer Button im bestehenden `.dbw-cta-stack` (unterhalb "Immobilie anfragen", oberhalb Telefon-Link):
```html
<a href="https://wa.me/..." target="_blank" rel="noopener" class="dbw-cta dbw-cta--whatsapp">
    <svg class="dbw-cta__icon">...</svg>
    <span class="dbw-cta__text">Per WhatsApp anfragen</span>
</a>
```

**B) Floating-Button (Optional, konfigurierbar)**
Ein runder WhatsApp-Button unten rechts (fixed position), der auf allen Immobilien-Seiten schwebt:
- Nur auf `is_singular('immobilie')` anzeigen
- Z-Index unter Modal aber ueber Content
- Pulsierender Animations-Effekt (dezent, respektiert `prefers-reduced-motion`)
- Tooltip on Hover: "Per WhatsApp anfragen"
- Verschwindet wenn Modal offen ist

**C) Contact Modal — Zusaetzliche Option (Optional)**
Im Success-Screen des Modals: "Oder direkt per WhatsApp schreiben" Link neben dem Telefon-Link.

**D) Mobile Sticky-CTA-Bar**
WhatsApp-Icon als zusaetzlicher Button in der bestehenden Sticky-Bar (neben "Anfragen").

### 3. Design

**WhatsApp-Markenfarbe:** `#25D366` (offizielles WhatsApp-Gruen)

**Sidebar-Button:**
- Hintergrund: `#25D366`
- Text: Weiss
- Hover: Etwas dunkler (`#1DA851`)
- Gleiches Layout wie der primaere CTA-Button (flex, gap, icon + text)
- WhatsApp SVG-Icon (offizielles Logo vereinfacht als Pfad)

**Floating-Button:**
- 56x56px Kreis, `#25D366` Hintergrund
- Weisses WhatsApp-Icon (24x24)
- Box-Shadow: `0 4px 12px rgba(37, 211, 102, 0.4)`
- Position: `fixed; bottom: 24px; right: 24px;`
- Auf Mobile: `bottom: 80px` (ueber der Sticky-CTA-Bar)
- Entrance-Animation: Scale von 0 auf 1 (nach 1s Delay)

**SVG-Icon (WhatsApp):**
```svg
<svg viewBox="0 0 24 24" fill="currentColor">
  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
  <path d="M12 0C5.373 0 0 5.373 0 12c0 2.025.504 3.94 1.396 5.617L.052 23.7a.5.5 0 00.606.607l5.985-1.321A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-1.875 0-3.654-.509-5.197-1.396l-.372-.22-3.857.852.874-3.793-.242-.384A9.71 9.71 0 012.25 12 9.75 9.75 0 0112 2.25 9.75 9.75 0 0121.75 12 9.75 9.75 0 0112 21.75z"/>
</svg>
```

### 4. Backend-Einstellungen

**Settings-Seite (Tab "Darstellung" oder eigener Tab "Kontakt"):**

| Feld | Typ | Default | Beschreibung |
|------|-----|---------|-------------|
| `whatsapp_enabled` | Checkbox | An | WhatsApp-Button global aktivieren |
| `whatsapp_floating` | Checkbox | Aus | Floating-Button anzeigen |
| `whatsapp_number_override` | Tel-Input | (leer) | Globale WhatsApp-Nummer (ueberschreibt Kontaktperson). Fuer Makler die eine zentrale WhatsApp-Business-Nummer nutzen. |
| `whatsapp_message_template` | Textarea | (Standardtext) | Vorbefuellte Nachricht. Platzhalter: `{name}`, `{titel}`, `{url}`, `{ansprechpartner}` |
| `whatsapp_cta_text` | Text | "Per WhatsApp anfragen" | Button-Beschriftung |

**Customizer:**
- Toggle: `dbw_immo_single_show_whatsapp` (default: true) — zeigt/versteckt den WhatsApp-Button auf der Einzelansicht
- Toggle: `dbw_immo_whatsapp_floating` (default: false) — Floating-Button ein/aus

**Logik fuer Nummer:**
1. Wenn `whatsapp_number_override` gesetzt → diese Nummer verwenden
2. Sonst: `kontaktperson_tel` des jeweiligen Objekts
3. Wenn beides leer → Button nicht anzeigen

### 5. Technische Umsetzung

**PHP:**
- Rendering in `ContactModal.php` erweitern (Sidebar CTA-Stack) ODER eigene Klasse `src/Frontend/WhatsAppButton.php`
- Floating-Button in `wp_footer` Hook rendern (nur auf Single-Immobilie)
- Nummer-Normalisierung: `preg_replace('/[^0-9]/', '', $tel)` und fuehrendes `+` entfernen

**CSS:**
- Styles in `assets/css/frontend.css` ergaenzen
- Print: WhatsApp-Buttons ausblenden
- `prefers-reduced-motion`: Puls-Animation deaktivieren

**JS:** Minimal oder keins noetig (reine `<a href>` Links). Optional: Floating-Button verstecken wenn Modal offen ist (wenige Zeilen in `contact-modal.js`).

**Sonstiges:**
- ABSPATH Guard, namespace `DBW\ImmoSuite\Frontend`
- Desktop: `https://wa.me/...` oeffnet WhatsApp Web/Desktop-App
- Mobile: Oeffnet direkt die WhatsApp-App
- Tracking-Ready: Button hat eigene CSS-Klasse fuer Event-Tracking (Google Analytics etc.)
- `rel="noopener"` auf allen externen Links
- `aria-label` auf Floating-Button ("WhatsApp Chat oeffnen")
