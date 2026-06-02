# Prompt: PDF-Expose-Download

---

Baue fuer das WordPress-Plugin "dbw Immo Suite" eine PDF-Expose-Generierung, die den aktuellen Drucken-Button ersetzt.

## Kontext

Das Plugin hat aktuell einen Drucken-Button in der Gallery (oben rechts, SVG-Printer-Icon) der `window.print()` aufruft. Das Ergebnis ist haesslich — der Browser druckt die komplette Seite mit Theme-Header/Footer. Es gibt zwar Print-Styles in `frontend.css`, aber das Resultat ist kein professionelles Expose.

Ersetze diesen Button durch einen "PDF herunterladen"-Button, der ein schoenes, Makler-taugliches PDF generiert.

## Vorhandene Daten (alle als Post-Meta)

**Basis:** kaufpreis, kaltmiete, warmmiete, hausgeld, nebenkosten, provision_kaeufer
**Flaechen:** wohnflaeche, nutzflaeche, grundstuecksflaeche, anzahl_zimmer, anzahl_schlafzimmer, anzahl_badezimmer, anzahl_stellplaetze
**Adresse:** strasse, hausnummer, plz, ort, geo_breite, geo_laenge
**Energie:** energiepass_art, energiepass_endenergie, energiepass_wertklasse, energiepass_traeger, energiepass_gueltig_bis, energiepass_baujahr
**Kontakt:** kontaktperson_vorname, kontaktperson_name, kontaktperson_email, kontaktperson_tel, kontaktperson_bild_url
**Texte:** post_content (Beschreibung), text_lage, text_ausstattung, text_sonstiges
**Bilder:** Featured Image + Attachments (mit _openimmo_gruppe: TITELBILD, BILD, GRUNDRISS)
**Features:** _dbw_immo_features (Array)
**SEO/Org:** dbw_immo_suite_settings → org_name, org_logo_url, org_phone, org_email, org_url

## Anforderungen

### 1. PDF-Layout (A4 Hochformat)

**Seite 1 — Titelseite:**
- Grosses Titelbild (volle Breite, obere Haelfte)
- Objekttitel gross
- Key Facts als Icon-Grid (Flaeche, Zimmer, Baujahr, Preis)
- Adresse
- Makler-Logo + Firmenname unten

**Seite 2 — Details:**
- Objektbeschreibung (post_content)
- Ausstattungs-Features als Pill/Tag-Grid
- Preisdetails-Tabelle (Kaufpreis, Nebenkosten, Provision, Hausgeld)

**Seite 3 — Lage & Energie:**
- Lagebeschreibung (text_lage)
- Statische Karten-Grafik (OpenStreetMap Static Tile oder Screenshot)
- Energieausweis-Visualisierung (Farbskala A+ bis H mit Marker)

**Seite 4 — Bilder & Grundriss:**
- Bildergalerie (2×2 Grid oder 3er-Layout)
- Grundriss(e) gross

**Letzte Seite — Kontakt:**
- Ansprechpartner mit Foto
- Telefon, E-Mail
- Makler-Branding (Logo, Adresse, Website)
- Disclaimer / Haftungsausschluss

### 2. Technische Umsetzung

**Ansatz A (empfohlen): Server-seitig mit PHP**
- Neue Klasse `src/Frontend/PdfExpose.php`
- AJAX-Endpoint: `wp_ajax_dbw_immo_generate_pdf` + `wp_ajax_nopriv_dbw_immo_generate_pdf`
- Nonce-geschuetzt
- Nutze eine leichtgewichtige PHP-PDF-Library:
  - **TCPDF** (GPL, kein Composer noetig, single-file includable) oder
  - **Dompdf** (HTML → PDF, einfacher fuer Layout) oder
  - **mPDF** (HTML → PDF, guter CSS-Support)
- HTML-Template rendern, dann in PDF konvertieren
- PDF als Download zurueckgeben (Content-Disposition: attachment)

**Ansatz B (Alternative): Browser-seitig**
- html2canvas + jsPDF
- Vorteil: Kein Server-Load
- Nachteil: Schlechtere Qualitaet, Browser-abhaengig

### 3. Button-Integration
- Ersetze den aktuellen Print-Button (SVG-Printer-Icon) in `single-immobilie.php`
- Neues Icon: Download-Icon (SVG)
- Tooltip: "Expose als PDF herunterladen"
- Loading-State waehrend PDF-Generierung (Spinner im Button)
- aria-label fuer Accessibility

### 4. Customizer
- Toggle: "PDF-Expose aktivieren" (default: true)
- Optional: Farb-Override fuer PDF (falls abweichend vom Frontend-Theme)

### 5. Technische Details
- ABSPATH Guard, namespace `DBW\ImmoSuite\Frontend`
- Bilder: `wp_get_attachment_image_url($id, 'large')` fuer Qualitaet
- Nonce: `dbw_immo_pdf_nonce`
- Capability: Kein Check noetig (oeffentlich zugaenglich, wie die Seite selbst)
- Rate Limiting: 1 PDF pro Minute pro IP (Transient-basiert)
- Dateiname: `{sanitize_title($title)}-expose.pdf`
- Memory: `wp_raise_memory_limit('admin')` vor PDF-Generierung
- Du/Sie System beruecksichtigen wo Text im PDF vorkommt
