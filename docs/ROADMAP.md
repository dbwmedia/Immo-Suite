# Roadmap — DBW Immo Suite

Stand: 2026-06-10 (nach v1.18.0). Ergebnis aus Produkt-/Code-Review.
Priorisierung: P1 = als naechstes, P2 = danach, P3 = bei Bedarf/Nachfrage.

---

## P1 — Strategische Features (tragen den Abo-Preis)

### 1. Anfragen-Inbox als CPT
**Die groesste strukturelle Luecke.** ContactForm und ExposeRequest versenden nur Mails —
landet eine Mail im Spam, ist der Lead weg. Die Intent-Daten aus dem Multi-Step-Modal
(Besichtigung/Info/Preis/Rueckruf) sind perfekt strukturierte Lead-Qualifizierung und
werden aktuell weggeworfen.

- CPT `immo_anfrage`: Name, Kontakt, Intent, Objekt-Referenz, Status (Neu/Kontaktiert/Erledigt)
- Listenansicht mit Intent-Badge, Mail bleibt als Notification
- DSGVO: Auto-Loeschung nach X Tagen einstellbar; Privacy-Exporter/Eraser (Stubs aus v1.13.0) bekommen echte Daten
- Fundament fuer Statistiken, Suchagent und jedes spaetere CRM-Argument

### 2. Suchagent / E-Mail-Alerts
"Deine Website sammelt Suchauftraege wie IS24 — aber die Leads gehoeren dir."
- Formular auf der 0-Ergebnisse-Seite + Filterleiste ("Suche speichern")
- Doppel-Opt-in, Cron-Abgleich neuer Objekte gegen gespeicherte Filter-Kriterien
- Erzeugt wiederkehrende Leads statt einmaliger Besuche

### 3. Objekt-Statistiken fuer Makler
Kein DACH-Mitbewerber liefert das eingebaut. Cookielos zaehlen (DSGVO-Argument).
- Views, Expose-Downloads, Anfragen, Conversion pro Objekt
- Dashboard-Uebersicht + ggf. PDF-Report ("Vermarktungsnachweis" fuer Eigentuemer)
- Hilft Maklern beim *Einkauf* neuer Objekte — mehr wert als jedes Frontend-Feature

### 4. License Control Center (idea.md, Stufe 1+2)
Vor Skalierung auf >20 Kunden zwingend — 30 statische Keys im Code skalieren nicht.
- **Stufe 1:** Schlanke WP-Instanz auf updates.dbw-media.de: `POST /license/validate`
  (Key + Domain), `GET /update/dbw-immo-suite` (signierte ZIP-URL). Plugin-seitig
  woechentlicher Check mit **Grace Period** (Server down ≠ Kunde gesperrt).
  Keys in DB = Jahres-Lizenzen mit Ablauf moeglich (passt zur Abo-Strategie).
- **Stufe 2:** Telemetrie beim Update-Check (Version, WP/PHP, letzter Import-Status)
  → Installationsuebersicht fast gratis, proaktiver Support ("Import schlaegt seit 3 Tagen fehl").
- **Nicht bauen:** eigenes Release-Upload-UI — GitHub-Release-Webhook spiegeln reicht.
- **Stufe 3 (erst >25 Kunden):** Feature-Flags nur fuer Add-ons (z.B. IS24-API-Modul),
  nicht fuer Tiering des Kernprodukts ("ein Plan, alles drin" beibehalten).

---

## P2 — Frontend-Features mit Verkaufswert

### 5. Auto-Geocoding + eigene Distanz-Berechnung (OSM)
Der Infrastruktur-Score haengt komplett an `distanz_*`-Feldern aus dem OpenImmo-Feed —
viele Maklersoftwares liefern die nicht. Karten bleiben leer, wenn lat/lng fehlt.
- Beim Import: Nominatim-Geocoding wenn Geo-Daten fehlen (Rate-Limit beachten, cachen)
- Ausbaustufe: Distanzen (OEPNV, Supermarkt, Schule...) via Overpass API selbst berechnen
  → Infra-Score funktioniert bei *jedem* Kunden

### 6. KI-Expose-Texte
Makler hassen Texten. Button "Text generieren" im Property-Editor (Beschreibung/Lage
aus strukturierten Daten), eigener API-Key des Kunden. Positionierung: als Entwurf,
Makler prueft (passt zur dbw-KI-Haltung: Werkzeug, nicht Entscheider).

### 7. Video / 360°-Touren pro Objekt
YouTube/Vimeo/Matterport-URL (OpenImmo liefert Video-URLs oft mit). Consent-Placeholder
nach dem Muster des Map-Consents. Matterport-Embed = Premium-Optik fuer wenig Aufwand.

### 8. Marker-Clustering fuer die Archiv-Karte
Ab ~50 Objekten sinnvoll: Leaflet.markercluster lokal buendeln (wie Leaflet selbst).

---

## P3 — Quick Wins UI/UX (je < 1 Tag)

- **Filter-Chips:** Aktive Filter als entfernbare Chips neben "23 Immobilien gefunden"
  ("Haus ×", "bis 500.000 € ×")
- **Share-Fallback ohne alert():** `navigator.clipboard.writeText()` + "Link kopiert"-Toast
  (Desktop-Chrome hat kein Web Share — genau da sieht es aktuell am schlechtesten aus)
- **Galerie:** Bildzaehler "3/17" + Pfeiltasten-/Swipe-Navigation
- **0-Ergebnisse-Seite:** Filter-Reset-Button + 3 aktuelle Objekte + CTA "Suchauftrag
  hinterlassen" (Einstieg in den Suchagenten)
- **Intent-Vorauswahl im Kontakt-Modal:** `data-intent` am Open-Button → direkt zu Step 2
  (z.B. vom Finanzierungsrechner mit Intent "Finanzierung")
- **Import-Dashboard:** "Naechster Cron-Import: heute 14:00 / Letzter Lauf: vor 2h,
  3 aktualisiert" prominent ueber dem Button
- **Preisfilter kontextsensitiv:** Bei Vermarktungsart "Miete" Step 100 statt 1000 und
  nur das passende Meta-Feld abfragen
- **Merkliste erweitern:** Herz auch auf der Detailseite; "Anfrage zu N gemerkten
  Objekten" als Intent im Kontakt-Modal
- **Sticky-Sidebar-CTA:** "Immobilie anfragen"-Button mit in die sticky Highlights-Box
  (Preis + CTA in einem Blickfeld, wie es die mobile Sticky-Bar schon macht)

---

## P3 — Backend/Workflow

- **Import-Monitoring mit Alarm:** E-Mail/Admin-Notice wenn Import fehlschlaegt oder
  >48h kein Feed ankam (FTP kaputt = Website veraltet, Makler merkt es sonst Wochen spaeter)
- **Objektliste aufwerten:** Admin-Spalten (Status, Preis, Ort, zuletzt importiert,
  Anfragen-Count) + Warn-Badge bei unvollstaendigen Objekten (kein Bild, keine Geo-Daten,
  kein Energieausweis — Pflichtangabe!)
- **Dashboard-Widget:** "3 neue Anfragen, letzter Import vor 2h, 14 aktive Objekte"
- **Onboarding-Checkliste:** Nach Aktivierung gefuehrte Schritte (Lizenz → Import-Pfad →
  erster Import → Customizer) — macht das Setup-Paket skalierbarer
- **AJAX-Filter im Archiv:** Filteraenderung ohne Full-Page-Reload (pushState + bestehende
  Entrance-Animations) — groesster gefuehlter Qualitaetssprung, mittlerer Aufwand
- **Inline-Styles aus single-immobilie.php ziehen** (~50 Stellen): Highlights-Card und
  Gallery-Buttons sind aktuell nicht per Customizer/Child-Theme stylebar

---

## Bewusst zurueckgestellt

- **IS24/Immowelt-API-Sync:** Wochen Aufwand, API-Zugang pro Makler noetig — erst ab
  ~30 Kunden sinnvoll, dann ggf. als Add-on-Modul (Feature-Flag, Stufe 3)
- **Multi-Language:** deutscher Zielmarkt, Du/Sie-System waere zu verflechten — erst bei
  konkreter Nachfrage (i18n-Basis ist seit v1.17.1 gelegt)

---

## Pricing-Notiz

Suchagent + Anfragen-Inbox + Statistiken rechtfertigen den Sprung auf 499 EUR weit mehr
als Feature-Paritaet. Das Statistik-/Lead-Paket kann spaeter das Argument fuer eine
"Pro"-Stufe (~699 EUR) sein, ohne das "alles drin"-Versprechen des Basisplans zu brechen.
