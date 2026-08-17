# Anleitung: onOffice an die dbw Immo Suite anbinden

Diese Anleitung beschreibt, wie Immobilien aus **onOffice enterprise** per OpenImmo-Schnittstelle
automatisch auf einer WordPress-Website mit der dbw Immo Suite landen.

Ablauf in einem Satz: onOffice legt die Objekte als ZIP-Paket per FTP auf dem Webserver ab,
die Immo Suite liest dieses Verzeichnis stuendlich aus und legt die Immobilien in WordPress an.

```
onOffice  ──(OpenImmo ZIP per FTP)──>  /wp-content/uploads/openimmo/  ──(Cron, stuendlich)──>  WordPress
```

---

## Schritt 1: Zielordner auf dem Webserver anlegen

Standard-Ziel der Immo Suite:

```
/wp-content/uploads/openimmo/
```

Der Ordner muss existieren und dem Systembenutzer der Website gehoeren (bei Plesk: der
Abo-Benutzer). Sonst kann PHP die ZIPs weder lesen noch aufraeumen.

Alternativ moeglich: `<WordPress-Root>/openimmo/`. Empfohlen ist der Uploads-Pfad, weil er
garantiert innerhalb von `open_basedir` liegt.

## Schritt 2: FTP-Zugang fuer onOffice einrichten (Beispiel Plesk)

Einen **eigenen** FTP-Benutzer anlegen, nicht den Hauptzugang weitergeben:

1. Plesk: *Websites & Domains* > Domain waehlen > **FTP-Zugang** > *FTP-Zugang hinzufuegen*
2. Name: z. B. `onoffice`
3. Home-Verzeichnis: `/httpdocs/wp-content/uploads/openimmo`
4. Passwort vergeben (moeglichst ohne Sonderzeichen, die in FTP-Clients Probleme machen)
5. Speichern

Danach notieren: **Server (Host), Benutzername, Passwort, Verzeichnis**.
Wenn das Home-Verzeichnis bereits auf den Ordner zeigt, ist das Zielverzeichnis in onOffice
schlicht `/`.

## Schritt 3: Portal-Anbindung in onOffice anlegen

Menuepfad in onOffice enterprise:

> **Extras > Einstellungen > Grundeinstellungen > Reiter "Portale"**

1. Ueber das **Plus-Symbol** eine neue Portalanbindung anlegen.
2. Als Portal die Anbindung fuer die eigene Website waehlen (je nach Vertrag heisst der Eintrag
   "Homepage", "eigene Homepage" oder "OpenImmo-Schnittstelle").
   *Ist kein solcher Eintrag vorhanden, muss onOffice die individuelle Schnittstelle erst
   freischalten. Das laeuft ueber ein Ticket beim onOffice-Support und kann kostenpflichtig sein.*
3. FTP-Daten eintragen:
   - **FTP-Server**: Host aus Schritt 2
   - **FTP-User** und **FTP-Passwort**
   - **Verzeichnis**: `/` (bzw. der Pfad zum `openimmo`-Ordner, falls der FTP-Zugang hoeher liegt)
   - In den *erweiterten Einstellungen*: **passiver Modus** aktivieren
4. Haekchen **"Portal aktiv"** setzen.
5. Speichern (Disketten-Symbol).

Nach dem Speichern zeigt onOffice den **FTP-Zugangsdatencheck**. Erst wenn dort "FTP-Verbindung
OK" steht, funktioniert die Uebertragung. Bei Fehlern: Gross-/Kleinschreibung pruefen und
verwechslungsanfaellige Zeichen (l / I / 1, O / 0) im Passwort ausschliessen.

### Voll- oder Teilabgleich

Wenn onOffice die Wahl anbietet: **Vollabgleich** (kompletter Bestand in jedem Paket) ist die
sichere Variante. Nur damit darf in WordPress die Garbage Collection aktiviert werden (Schritt 5).
Bei Teilabgleich uebertraegt onOffice nur Aenderungen; geloeschte Objekte kommen dann als
`actiontype="DELETE"` und werden von der Immo Suite ebenfalls korrekt verarbeitet.

### FTP-Ordner: haeufigster Stolperstein

Der FTP-Zugangsdatencheck prueft nur den **Login**, nicht den Ordner. onOffice weist selbst darauf
hin: "FTP Ordner = / oder .". Zeigt der FTP-Zugang bereits auf das Website-Verzeichnis, ist ein
Wert wie `/httpdocs/openimmo/` eine Ebene zu tief und das Paket landet im Nirgendwo, obwohl
onOffice "FTP Verbindung in Ordnung" meldet.

Einziger belastbarer Test: ein Objekt uebertragen und pruefen, ob die ZIP tatsaechlich im Ordner
liegt (Plesk-Dateimanager oder "Verzeichnis pruefen" in den Plugin-Einstellungen).

Der in onOffice eingetragene Ordner und der Import-Pfad im Plugin muessen auf **dasselbe**
Verzeichnis zeigen:

| onOffice FTP-Ordner | Preset im Plugin |
|---|---|
| `.../wp-content/uploads/openimmo/` | Uploads-Verzeichnis (empfohlen) |
| `<WordPress-Root>/openimmo/` | WordPress-Root |

### Automatischer Vollabgleich

In der Portaluebersicht gibt es die Spalte **Automatischer Vollabgleich**. Ist sie leer, geht nur
raus, was jemand aktiv uebertraegt, und der Bestand auf der Website driftet mit der Zeit
auseinander.

Der automatische Vollabgleich (einmal taeglich moeglich) muss vom **onOffice-Support
freigeschaltet** werden, danach lassen sich die Zeiten selbst einstellen. Fuer die Website ist das
empfohlen.

Faustregel fuer WordPress: **Garbage Collection erst aktivieren, wenn der automatische
Vollabgleich laeuft.** Vorher wuerden Objekte archiviert, die im Teilpaket nur nicht mitkamen.
Loeschungen kommen ohnehin als `actiontype="DELETE"` an und werden korrekt verarbeitet.

## Schritt 4: Immobilien fuer die Uebertragung freigeben

Die Portalanbindung allein uebertraegt nichts. Jedes Objekt muss freigegeben werden:

1. Immobiliendatensatz oeffnen > Reiter **Vermarktung**
2. Beim neuen Portal das Haekchen setzen
3. Speichern, danach die Uebertragung anstossen

Im Reiter Vermarktung stehen alle aktiven Portale untereinander. Wer ein Objekt gleichzeitig auf
ImmobilienScout24 **und** die eigene Website bringen will, setzt beide Haekchen und uebertraegt
einmal. Es sind also zwei Haken, aber nur ein Arbeitsgang.

Noch kuerzer geht es mit **Portalgruppen**: mehrere Portale zu einer Gruppe (z. B. "Online")
zusammenfassen, dann genuegt ein Klick. Die Portaluebersicht zeigt in der Spalte "Anz. Gruppen",
ob bereits Gruppen existieren.

Fuer den ersten Test reicht ein einzelnes Objekt. Kurz darauf muss im Ordner
`/wp-content/uploads/openimmo/` eine ZIP-Datei liegen. Liegt dort nichts, ist das Problem noch
in onOffice oder beim FTP-Zugang und nicht in WordPress.

### Adressfreigabe nicht vergessen

Im Objekt rechts unter **Adressfreigabe** steuert onOffice, welche Daten nach draussen gehen.
Steht "Portale / HTML Expose" auf **nein**, liefert das OpenImmo-Paket keine Strasse und
Hausnummer, sondern nur PLZ und Ort.

Fuer die Immo Suite heisst das: Karte, Standort-Features und der Infrastruktur-Score arbeiten dann
nur grob auf Ortsebene. Wer eine exakte Karte auf der Website will, muss die Adressfreigabe fuer
Portale auf **ja** setzen.

Nach dem ersten Import laesst sich das pruefen: Im Objekt in WordPress muessen die Felder
`strasse`, `hausnummer` und die Geokoordinaten (`geo_breite`, `geo_laenge`) gefuellt sein.

Das Feld **Eigene Internetseite > Veroeffentlichen** betrifft dagegen nur die von onOffice selbst
gehostete Website und hat auf diese Anbindung keinen Einfluss.

## Schritt 5: WordPress konfigurieren

1. **Immobilien > Einstellungen > Reiter "Import"**
2. **Pfad zu XML-Dateien**: den Preset *Uploads-Verzeichnis* waehlen
   (`/wp-content/uploads/openimmo/`)
3. Auf **Verzeichnis pruefen** klicken. Die Rueckmeldung nennt den aufgeloesten Pfad und die
   Anzahl gefundener ZIP-/XML-Dateien. Steht dort "0 Dateien", ist noch nichts angekommen.
4. **URL-Slug** setzen (z. B. `immobilien`). Danach einmal die Permalinks neu speichern.
5. **Garbage Collection**: nur aktivieren, wenn onOffice den **kompletten Bestand** liefert
   (Vollabgleich). Sonst werden Objekte archiviert, die im Teil-Paket nur zufaellig fehlen.
6. Speichern.

## Schritt 6: Ersten Import ausfuehren

**Immobilien > Import**

- Button **Import starten** stoesst den Lauf manuell an.
- Das Dashboard zeigt Historie (angelegt / aktualisiert / Fehler) und den naechsten Cron-Termin.
- Danach automatisch: der Cron `dbw_immo_cron_hook` laeuft **stuendlich**.

Die Immo Suite merkt sich pro ZIP-Datei einen Hash. Unveraenderte Pakete werden uebersprungen,
ein stuendlicher Lauf kostet also kaum Last.

### Was mit den ZIP-Dateien passiert

Verarbeitete Pakete werden nicht geloescht, sondern in `.processed` umbenannt (z. B.
`107939_..._20260817143538.zip.processed`). Damit bleibt nachvollziehbar, was angekommen ist, und
nichts wird doppelt importiert.

Bei taeglichem Vollabgleich sammeln sich die Dateien allerdings an. Den Ordner gelegentlich
aufraeumen, alte `.processed`-Dateien koennen geloescht werden.

## Schritt 7: Kontrolle

- **Immobilien** in der WordPress-Uebersicht: sind die Objekte da, inkl. Bildern?
- Frontend: `/immobilien/` (bzw. der gewaehlte Slug) zeigt das Archiv.
- Ein Objekt oeffnen: Titelbild, Grundriss, Preis, Energieausweis pruefen.
- **Import-Ueberwachung**: Die Suite meldet sich per Admin-Hinweis und E-Mail, wenn ein Lauf
  fehlschlaegt oder laenger kein frisches Paket ankommt. Damit faellt ein kaputter FTP-Zugang
  auf, bevor die Website wochenlang veraltet ist.

## Verkaufte Objekte als Referenz zeigen

Der Alltagsfall: Ein Objekt ist verkauft, soll aus den Portalen verschwinden, auf der eigenen
Website aber als Referenz sichtbar bleiben.

**Vorgehen in onOffice:**

1. Im Objekt den **Vermarktungsstatus** auf verkauft (bzw. vermietet) setzen.
2. Bei **ImmobilienScout24** das Objekt loeschen. Dort darf nichts Verkauftes stehen bleiben.
3. Beim Portal **Website** das Objekt **nicht** loeschen, sondern erneut uebertragen
   (Aktualisieren-Symbol).

**Wichtigste Regel:** Verkaufte Objekte muessen im Portal "Website" aktiv bleiben. Wer sie dort
loescht, sendet ein `DELETE` und das Objekt verschwindet auch aus WordPress. Ebenso sollte die
Option "Immobilie archivieren, wenn sie in allen Portalen geloescht ist" nicht dazu fuehren, dass
Referenzen verloren gehen.

**Was die Immo Suite daraus macht:**

Der Importer liest `<zustand_angaben><verkaufstatus>` (Elementwert oder Attribut `stand`):

| OpenImmo | Status in WordPress |
|---|---|
| VERKAUFT, VERMIETET | `verkauft` |
| RESERVIERT | `reserviert` |
| (leer / sonstiges) | `aktiv` |

Bei `verkauft` passiert automatisch:

- Badge "Verkauft" auf der Karte, optional in Graustufen
- Objekt verschwindet aus dem Hauptarchiv, wenn "Archiv bereinigen" aktiv ist
- Objekt erscheint im Referenzen-Block bzw. `[dbw_immo_references]`
  (Standardfilter: `verkauft,referenz`)
- Verkaufsdatum wird gesetzt, und zwar auf den **Zeitpunkt des Imports**, nicht auf den
  tatsaechlichen Verkaufstag
- Detailseite wird auf `noindex` gesetzt, Schema.org meldet `SoldOut`

Der Preis laesst sich ueber die Einstellung "Preise ausblenden" bei verkauften Objekten
unterdruecken.

**Detailseiten abschalten (optional):** Mit der Einstellung "Detailseiten deaktivieren" bekommen
verkaufte und Referenz-Objekte keine eigene Unterseite mehr. Karten und Kartenmarker sind dann
nicht mehr klickbar, der Expose-Button entfaellt, und wer eine alte URL aufruft, landet auf der
Referenz-Seite. Sinnvoll, weil ein Expose ohne gueltigen Preis und ohne Anfragemoeglichkeit
niemandem mehr hilft. Standardmaessig ist die Option aus, die Detailseiten bleiben also erhalten.

**Hinweis zum Referenz-Haekchen in onOffice:** Das Feld "Referenz" unter *Eigene Internetseite*
wertet die Immo Suite derzeit nicht aus. Fuer den Referenzbereich genuegt der Status `verkauft`,
weil der Referenzen-Block beide Status zeigt.

**Status im Backend uebersteuern:** Wird der Status in WordPress manuell gesetzt, laesst sich das
mit dem Flag `_dbw_immo_manual_status_override` sperren. Dann ueberschreibt kein Import diesen
Status mehr.

---

## Fehlersuche

| Symptom | Ursache und Loesung |
|---|---|
| onOffice meldet "FTP-Login unvollstaendig" | Zugangsdaten falsch. Gross-/Kleinschreibung und Host pruefen, passiven Modus aktivieren. |
| ZIP liegt im Ordner, WordPress importiert nicht | Pfad in den Einstellungen pruefen ("Verzeichnis pruefen"). Bei Fehlermeldung zu `open_basedir` den Uploads-Pfad verwenden. |
| "Verzeichnis existiert, aber ist nicht lesbar" | Dateirechte. Der Ordner und die ZIPs muessen dem Systembenutzer der Website gehoeren. |
| Import laeuft, aber keine Bilder | Die ZIP enthaelt keine Anhaenge. In onOffice pruefen, ob Bilder mit uebertragen werden. |
| Objekte verschwinden nach dem Import | Garbage Collection ist aktiv, onOffice liefert aber nur Teilabgleich. Option deaktivieren. |
| "Import laeuft bereits" | Ein Lauf haengt. Nach 5 Minuten loest sich die Sperre automatisch. |
| Nichts kommt mehr an | FTP-Passwort abgelaufen oder Portal in onOffice deaktiviert. Zugangsdatencheck in onOffice erneut ausfuehren. |

## Hinweise fuer andere Maklersoftware

Der Weg ist bei FlowFact, JustImmo, Propstack und immoware24 identisch: OpenImmo-Export per FTP
auf denselben Ordner. Nur die Menuepfade in der jeweiligen Software unterscheiden sich.
Wichtig bleibt in allen Faellen: **Vollabgleich = Garbage Collection erlaubt, Teilabgleich = nicht.**
