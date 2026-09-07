# OpenImmo-Felder: Was die Immo Suite liest und wo es landet

Stand: 07.09.2026 (v2.12.0) · Referenz: OpenImmo 1.2.7

Diese Datei ist die Landkarte zwischen dem XML aus der Maklersoftware und dem, was auf der Website
steht. Sie beantwortet drei Fragen:

1. Welches XML-Feld fuellt welches Meta-Feld?
2. Wo taucht der Wert im Frontend auf?
3. Was liest das Plugin bewusst **nicht**?

Der Import liegt in [`src/Import/Importer.php`](../src/Import/Importer.php), Methode `map_fields()`.

> **Merksatz:** Ein neues Feld wirkt erst nach einem **Vollabgleich** in der Maklersoftware. Das XML
> wird nach dem Import nicht aufbewahrt, das Plugin kann fehlende Werte nicht nachtraeglich
> ergaenzen. Details unter [Neu-Import](#neu-import).

---

## `<preise>`

| XML | Meta | Frontend |
|---|---|---|
| `kaufpreis` | `kaufpreis` | Highlights, Karten, Preis-pro-qm, Nebenkostenrechner |
| `kaltmiete` | `kaltmiete` | Highlights, Karten |
| `warmmiete` | `warmmiete` | Highlights |
| `nebenkosten` | `nebenkosten` | Highlights (Miete) |
| `hausgeld` | `hausgeld` | Highlights (Kauf) |
| `heizkosten` | `heizkosten` | Highlights + Expose (Miete) |
| `heizkosten_enthalten` | `heizkosten_enthalten` | Zeile "In den Nebenkosten enthalten", wenn kein Betrag da ist |
| `kaution` | `kaution` | Highlights + Expose (Miete) |
| `kaution_text` | `kaution_text` | schlaegt den Betrag, z. B. "3 Nettokaltmieten" |
| `aussen_courtage` | `provision_kaeufer` | Highlights "Kaeuferprovision" |
| `innen_courtage` | `provision_verkaeufer` | derzeit nur gespeichert |
| `courtage_hinweis` | `courtage_hinweis` | Hinweiszeile unter den Highlights + Expose |
| `provisionspflichtig` | `provisionspflichtig` | derzeit nur gespeichert |
| `stp_carport`, `stp_duplex`, `stp_freiplatz`, `stp_garage`, `stp_parkhaus`, `stp_tiefgarage`, `stp_sonstige` | `stellplatz_preise` (Liste), `stellplatz_kaufpreis_gesamt`, `stellplatz_miete_gesamt` | Ausstattung ("2 Tiefgaragenstellplaetze a 8.000 €"), Highlights (Gesamtbetrag), Expose |

### Stellplaetze im Detail

Jede Stellplatzart ist ein eigenes Element mit drei Attributen:

```xml
<stp_tiefgarage anzahl="2" stellplatzkaufpreis="8000.00" stellplatzmiete="" />
```

Der Preis gilt **pro Stellplatz**, nicht als Summe. Die Ausstattung zeigt deshalb die
Pro-Stueck-Schreibweise, die Highlights-Box den Gesamtbetrag (Anzahl x Preis), so wie
ImmobilienScout24 es auch macht.

Leere `stp_*`-Elemente werden uebersprungen: die meisten Exporte schicken alle sieben Arten mit,
auch die ungenutzten. Fehlt `anzahl`, steht aber ein Preis, wird von einem Stellplatz ausgegangen.
Dezimalwerte mit deutschem Komma ("65,50") werden korrekt gelesen, obwohl das Schema den Punkt
vorsieht.

Bewusst **nicht** eingerechnet: Kaufnebenkosten- und Finanzierungsrechner. Die Grunderwerbsteuer
haengt am Kaufpreis der Immobilie, ein stillschweigend addierter Stellplatz wuerde die Rechnung
falsch machen.

---

## `<flaechen>`

| XML | Meta | Frontend |
|---|---|---|
| `wohnflaeche` | `wohnflaeche` | Eckdaten, Highlights, Karten, Preis pro qm |
| `nutzflaeche` | `nutzflaeche` | Eckdaten |
| `grundstuecksflaeche` | `grundstuecksflaeche` | Eckdaten |
| `anzahl_zimmer` | `anzahl_zimmer` | Eckdaten, Highlights, Karten |
| `anzahl_schlafzimmer` | `anzahl_schlafzimmer` | Highlights |
| `anzahl_badezimmer` | `anzahl_badezimmer` | Highlights |
| `anzahl_stellplaetze` | `anzahl_stellplaetze` | Ausstattung (Fallback ohne Preisangabe) |

Fehlt `anzahl_stellplaetze`, wird die Anzahl aus den `stp_*`-Elementen summiert. onOffice fuellt das
Feld, andere Maklersoftware nicht immer.

---

## `<geo>`

| XML | Meta | Frontend |
|---|---|---|
| `plz`, `ort` | `plz`, `ort` | Adresse, Taxonomie `ort`, Preisvergleich |
| `strasse`, `hausnummer` | `strasse`, `hausnummer` | Adresse (nur bei Freigabe, siehe unten) |
| `geokoordinaten[breitengrad/laengengrad]` | `geo_breite`, `geo_laenge` | Karte, Archiv-Karte, Schema |
| `etage` | `etage` | Eckdaten-Zeile + Objektdaten ("2 von 4") |
| `anzahl_etagen` | `anzahl_etagen` | dito |

---

## `<verwaltung_techn>`

| XML | Meta | Verwendung |
|---|---|---|
| `openimmo_obid` | `openimmo_id` | Schluessel fuer Update statt Neuanlage, Garbage Collection |
| `objektnr_extern` | `objektnr_extern` | Objektdaten ("Objektnummer", z. B. 2026-104) |
| `aktion[aktionart]` | — | steuert DELETE/CHANGE waehrend des Imports |

---

## `<verwaltung_objekt>`

| XML | Meta | Frontend |
|---|---|---|
| `objektadresse_freigeben` | `adresse_freigegeben` | **schaltet die Adresse objektgenau ab**, siehe unten |
| `verfuegbar_ab` | `verfuegbar_ab` | Objektdaten |
| `abdatum` | `verfuegbar_ab_datum` | Objektdaten, Fallback wenn kein Freitext da ist |
| `haustiere` | `haustiere` | Objektdaten ("Erlaubt" / "Nicht erlaubt") |
| `vermietet` | `vermietet` | Objektdaten, nur wenn "ja" |
| `denkmalgeschuetzt` | `denkmalgeschuetzt` | Objektdaten, nur wenn "ja" |
| `wbs_sozialwohnung` | `wbs_sozialwohnung` | Objektdaten, nur wenn "ja" |

### Adressfreigabe

Booleans aus diesem Block werden **dreiwertig** gespeichert: `'1'`, `'0'` oder `''` (Feld fehlt im
Paket). Der Unterschied zwischen "nein" und "nicht angegeben" ist hier entscheidend.

`\DBW\ImmoSuite\dbw_show_address($post_id)` ist die einzige Stelle, an der die Sichtbarkeit
entschieden wird. Sie prueft zwei Tore:

1. den globalen Customizer-Schalter *Adresse anzeigen*
2. `adresse_freigegeben !== '0'`

Fehlt das Feld, bleibt es beim bisherigen Verhalten. Nur ein ausdrueckliches Nein blendet aus, und
zwar ueberall: Detailseite, Karte, Marker in der Archiv-Karte, Expose und das strukturierte
Schema.org-Markup. Im Backend erscheint bei betroffenen Objekten ein roter Hinweis im Reiter
Basisdaten.

---

## `<zustand_angaben>`

| XML | Meta | Frontend |
|---|---|---|
| `baujahr` | `energiepass_baujahr` | Energie-Block |
| `zustand[zustand_art]` | `zustand_art` | Objektdaten, uebersetzt (GEPFLEGT → "Gepflegt") |
| `alter[alter_attr]` | `objekt_alter` | Objektdaten ("Altbau" / "Neubau") |
| `letztemodernisierung` | `letzte_modernisierung` | Objektdaten |
| `verkaufstatus` | `_dbw_immo_status` | Status-Tag, ausser der manuelle Override ist gesetzt |

Der Zustand ist im Schema ein **Attribut**, kein Kindelement. Bis v2.12.0 las der Importer
`<zustand_art>` als Element, das Feld war deshalb immer leer. Beide Schreibweisen werden jetzt
akzeptiert. Unbekannte Werte werden lesbar gemacht statt verworfen.

### `<energiepass>`

| XML | Meta |
|---|---|
| `epart` | `energiepass_art` |
| `gueltig_bis` | `energiepass_gueltig_bis` |
| `primaerenergietraeger` | `energiepass_traeger` |
| `wertklasse` | `energiepass_wertklasse` |
| `endenergiebedarf` / `energieverbrauchkennwert` | `energiepass_endenergie`, `energiepass_verbrauchkennwert` |
| `mitwarmwasser` | `energiepass_mitwarmwasser` |
| `baujahr` | `energiepass_baujahr` (ueberschreibt den Wert aus `zustand_angaben` nur, wenn gefuellt) |

Ein Verbrauchsausweis fuehrt seinen kWh-Wert in `energieverbrauchkennwert`, ein Bedarfsausweis in
`endenergiebedarf`. Fuer die GEG-Pflichtangabe wird auf den jeweils vorhandenen Wert
zurueckgegriffen.

---

## `<ausstattung>`

Ergebnis ist die Badge-Liste `_dbw_immo_features` plus zwei Einzelwerte.

**Boolesche Elemente** (`<kamin>true</kamin>`): fahrstuhl, gartennutzung, kamin, klimatisiert,
rollstuhlgerecht, barrierefrei, seniorengerecht, swimmingpool, wasch_trockenraum, wintergarten,
dv_verkabelung, sauna, bibliothek, gaestewc, kabel_sat_tv, abstellraum, fahrradraum, dachboden,
rolladen, wellnessbereich, sporteinrichtungen.

> Nur ein ausdrueckliches `true`, `1` oder `ja` zaehlt. Ein leeres Element ist keine Bestaetigung.
> Bis v2.12.0 galt allein die Anwesenheit als Ja, was Merkmale erzeugt hat, die niemand hatte.

**Attribut-Saetze** (`<kueche EBK="1"/>`):

| XML | Wird zu |
|---|---|
| `kueche` | Einbaukueche, Offene Kueche, Pantrykueche |
| `bad` | Dusche, Badewanne, Bad mit Fenster, Bidet |
| `balkon_terrassen` | Balkon, Terrasse |
| `heizungsart` | Ofenheizung, Etagenheizung, Zentralheizung, Fernwaerme, Fussbodenheizung |
| `befeuerung` | Oel-, Gas-, Elektro-, Solarheizung, Erdwaerme, Luft-Wasser-Waermepumpe, Fernwaerme, Blockheizkraftwerk, Pelletheizung |
| `energietyp` | Passivhaus, Niedrigenergiehaus, Neubaustandard, KfW 40, KfW 60 |
| `sicherheitstechnik` | Alarmanlage, Videoueberwachung, Polizeiruf |
| `bauweise` | Massiv-, Fertig-, Holzbauweise |
| `dachform` | Sattel-, Walm-, Krueppelwalm-, Mansard-, Pult-, Flach-, Pyramidendach |
| `stellplatzart` | Garage, Tiefgarage, Carport, Stellplatz, Parkhaus, Duplex-Stellplatz |
| `boden` | generisch aus dem Attributnamen (PARKETT → Parkett), das Schema kennt 15 Belaege |

**Sonderfaelle:**

| XML | Meta / Ergebnis |
|---|---|
| `unterkellert[keller]` | Badge "Keller" (JA/VOLL) oder "Teilkeller" (TEIL) |
| `moebliert[moeb]` | Badge "Moebliert" (JA/VOLL) oder "Teilmoebliert" (TEIL) |
| `ausstatt_kategorie` | `ausstattungsqualitaet` → Objektdaten (Luxus, Gehoben, Standard, Einfach) |
| `ausricht_balkon_terrasse` | `ausrichtung` → Objektdaten ("Sued, West"), bewusst eine Zeile statt acht Badges |

---

## Weitere Bloecke

| XML | Meta | Frontend |
|---|---|---|
| `<objektkategorie><objektart>` | Taxonomie `objektart` | Filter, Status-Tag |
| `<objektkategorie><vermarktungsart>` | Taxonomie `vermarktungsart` | Filter, Status-Tag; Preis-Heuristik nur als Fallback |
| `<objektkategorie><nutzungsart>` | Taxonomie `objektart` | Wohnen / Gewerbe |
| `<freitexte><lage>` | `text_lage` | Abschnitt Lage |
| `<freitexte><ausstatt_beschr>` | `text_ausstattung` | Abschnitt Ausstattung |
| `<freitexte><sonstige_angaben>` | `text_sonstiges` | Abschnitt Sonstiges |
| `<infrastruktur><distanzen>` | `distanz_<typ>`, `infrastruktur_all` | Infrastruktur-Score |
| `<kontaktperson>` | `kontaktperson_*` | Makler-Karte, Kontaktmodal, Expose |
| `<anhaenge><anhang>` | Attachments mit `_openimmo_gruppe` | Galerie (BILD/TITELBILD), Grundrisse (GRUNDRISS) |

---

## Bewusst nicht gelesen

**Dokumente und Links.** PDFs aus `gruppe="DOKUMENTE"` werden importiert und landen in der
Mediathek, aber nichts zeigt sie an. Video- und 360-Grad-Touren kommen als
`<anhang location="EXTERN" gruppe="FILMLINK">` mit einer URL im `<pfad>`; der Anhang-Import kennt
nur lokale Dateien (`file_exists`), der Link faellt still durch. Beides steht in
[ROADMAP.md](ROADMAP.md) unter "Video / 360°-Touren".

**Gewerbe und Ferien.** `kantine_cafeteria`, `brauereibindung`, `hallenhoehe`, `max_personen`,
`nichtraucher`, `geschlecht`, `als_ferien` und Verwandte. Fuer Wohnimmobilien-Makler irrelevant.

**Anlage-Kennzahlen.** `nettorendite`, `mieteinnahmen_ist`, `mieteinnahmen_soll`, `x_fache`. Erst
sinnvoll, wenn ein Kunde ernsthaft Kapitalanlagen vermarktet.

**`user_defined_*`.** Herstellerspezifische Erweiterungen ohne verlaessliche Semantik.

---

## Neu-Import

Der Import schreibt Meta-Felder, er liest sie nicht zurueck ins XML. Ein neu unterstuetztes Feld ist
deshalb erst nach einer erneuten Uebertragung sichtbar:

1. **Vollabgleich in der Maklersoftware** — alle Objekte markieren und an das Website-Portal
   uebertragen. Der zuverlaessige Weg.
2. **Aufbewahrte Pakete erneut einlesen** — verarbeitete ZIPs bleiben 14 Tage als
   `<name>.zip.processed` im Import-Ordner liegen. Wichtig: mit **neuem Dateinamen** kopieren, sonst
   greift die Hash-Sperre (Option `dbw_immo_xml_hashes`, Schluessel ist der Dateiname) und das Paket
   wird als "identisch" uebersprungen.

Ein Vollabgleich ueberschreibt Felder, die jemand von Hand im Backend geaendert hat. Ausgenommen ist
nur der Status, der hat den Override-Haken im Reiter Basisdaten.

---

## Wenn ein Feld nicht ankommt

1. **Feldzuordnung im Portal pruefen.** Was die Maklersoftware nicht exportiert, kann das Plugin
   nicht importieren. In onOffice unter Extras → Einstellungen → Portale beim jeweiligen Portal.
2. **Ins Paket schauen.** Vor dem naechsten Cron-Lauf eine ZIP aus dem Import-Verzeichnis
   wegkopieren und die `<preise>` bzw. `<ausstattung>` ansehen.
3. **Objekt im Backend pruefen.** Immobilie oeffnen, Reiter Preise / Technik. Steht der Wert dort
   nicht, kam er nicht an. Steht er dort und nicht im Frontend, ist es ein Anzeigeproblem.
