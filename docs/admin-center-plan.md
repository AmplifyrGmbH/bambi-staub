# Admin-Center Chalet Bambi – Umsetzungsplan

Stand: 26.11.2026 · Status: Entwurf zur Freigabe

## 1. Ziel

Ein geschützter Bereich unter `bambi-staub.ch/admin`, in dem die Familie alle Belegungen an einem Ort sieht und bearbeitet:

- Direktanfragen von der Website annehmen oder ablehnen, mit fertigen Antwortmails
- Interhome-Buchungen automatisch übernehmen und mit eigenen Angaben ergänzen (Personen, Anreisezeit, Telefon, Notizen)
- Sperrzeiten selbst eintragen (ersetzt `config/manual-blocks.php`)
- Übersicht: nächste Anreisen, offene Aufgaben, Belegung und Einnahmen pro Jahr

Nicht Ziel der ersten Etappen: Online-Zahlung, Bewertungen auf der Website, ein eigenes Buchungssystem anstelle von Interhome.

## 2. Ausgangslage (geprüft)

| Bereich | Heute |
|---|---|
| Hosting | Hostpoint, Apache, PHP 8.5, Deploy per GitHub Actions (FTP) |
| Verfügbarkeit | `api/availability.php` liest den Interhome-iCal (30 Min. Cache) plus `config/manual-blocks.php` |
| Interhome-iCal | liefert pro Eintrag: Reservationsnummer (`IH:209527140774`), Status (`CONFIRMED`, `Ownerbooking`, `blocked`), Gastname (nur bei `CONFIRMED`), An- und Abreise. Keine Personenzahl, kein Telefon, keine E-Mail, kein Preis. Aktuell 18 Einträge (6 bestätigt, 3 Eigentümer, 9 gesperrt) |
| Website-Anfragen | `api/submit-inquiry.php`: Mail an info@bambi-staub.ch, Bestätigung an den Gast, Kopie als JSON-Zeile in `bambi-data/anfragen-JJJJ-MM.jsonl` (ausserhalb des Webroots, 12 Monate) |
| Nummern | fortlaufend ab BAM-1000, Zähler in `bambi-data/nummer.txt` |
| Preise | nur in `config/pricing.php` |
| Lücke | Eine angenommene Direktbuchung blockiert heute weder den Website-Kalender noch Interhome, bis sie von Hand in Interhome eingetragen ist |

## 3. Entscheide (Vorschlag)

**Server: Hostpoint reicht.** Alles läuft im selben PHP-Projekt; kein zweiter Server, keine zusätzlichen Kosten. Der Hetzner-Server wird nur gebraucht, falls der Hostpoint-Cron nicht verfügbar ist (dann ruft er den Sync auf).

**Datenbank: SQLite** in `bambi-data/admin.sqlite` (ausserhalb des Webroots).
- Für eine Ferienwohnung mit wenigen Dutzend Buchungen pro Jahr mehr als genug
- Keine Zugangsdaten, keine DB-Verwaltung bei Hostpoint
- Sicherung = Datei kopieren
- Falls `pdo_sqlite` auf Hostpoint fehlt: MariaDB von Hostpoint, gleicher Code über PDO

**Kein Framework.** Schlankes PHP mit eigenem kleinen Router, passend zum Rest der Website. HTML wird serverseitig erzeugt, etwas JavaScript nur für den Kalender und Filter.

**Zugang:** eigenes Login mit Benutzer + Passwort (`password_hash`, Argon2id). Ein Konto pro Person (David, Beat, Mäke …), damit im Verlauf steht, wer was geändert hat. Zwei-Faktor-Anmeldung (TOTP) in Etappe 4.

**Handy zuerst.** Die Familie wird das Admin-Center vor allem unterwegs nutzen. Jede Ansicht gibt es als Liste und als Kalender.

## 4. Datenmodell

```
buchung
  id, nummer (BAM-1000 / IH-209527140774), quelle (website | interhome | eigentuemer | manuell)
  status (anfrage | reserviert | bestaetigt | abgelehnt | storniert)
  anreise, abreise, naechte, personen, erwachsene, kinder
  gast_id, sprache, preis, rabattcode, reinigung, bettwaesche
  interhome_uid, interhome_status, interhome_eingetragen (ja/nein)
  anreisezeit, notiz_intern, erstellt, geaendert

gast
  id, name, email, telefon, strasse, plz, ort, land, notiz, erstellt

sperre                 ← ersetzt config/manual-blocks.php
  id, von, bis, grund, erstellt_von

zahlung                ← ab Etappe 4
  id, buchung_id, betrag, art (anzahlung | rest), faellig, bezahlt_am

mail
  id, buchung_id, vorlage, an, betreff, gesendet_am, gesendet_von

verlauf                ← jede Änderung, wer/was/wann
  id, buchung_id, benutzer, aktion, vorher, nachher, zeit

benutzer
  id, name, email, passwort_hash, totp_secret, letzter_login
```

Ein Gast kann mehrere Buchungen haben (Stammgäste werden erkannt, über die E-Mail).

## 5. Abläufe

### 5.1 Website-Anfrage

1. Gast sendet das Formular wie heute. Zusätzlich zur Mail wird die Anfrage als Buchung mit Status `anfrage` gespeichert.
2. Die Mail an info@ enthält einen Direktlink: «Im Admin-Center öffnen».
3. Im Admin-Center: **Annehmen** oder **Ablehnen**.
   - **Annehmen:** Status `bestaetigt`. Der Zeitraum ist sofort im Website-Kalender gesperrt. Eine vorbereitete Bestätigungsmail (Zahlungsangaben, Link zum Aufenthalts-Guide) erscheint. Vor dem Senden kann man sie ändern.
   - **Ablehnen:** Status `abgelehnt`, mit vorbereiteter, freundlicher Absage (z. B. «Zeitraum leider nicht mehr frei»).
4. Eine Aufgabe erscheint: **«In Interhome als Eigentümerbuchung eintragen»**. Sobald der nächste Sync für diese Daten einen `Ownerbooking`-Eintrag findet, wird die Aufgabe automatisch erledigt.
5. Optional: **Reservieren**, d. h. den Zeitraum für 48 Stunden halten, bis der Gast zusagt. Danach wird er automatisch wieder frei.

### 5.2 Interhome-Sync

- Ein Cron-Job ruft alle 30 Minuten den iCal ab.
- Jeder Eintrag wird über seine UID angelegt oder aktualisiert:
  - `CONFIRMED` → Buchung, Quelle interhome, Gastname aus dem iCal
  - `Ownerbooking` → mit einer passenden Direktbuchung verknüpft, sonst eigener Eintrag
  - `blocked` → Sperre aus Interhome (nur anzeigen)
- Änderungen erkennt der Sync selbst:
  - neue Buchung
  - verschobene Daten
  - verschwundener Eintrag, angezeigt als «storniert?», wird nie automatisch gelöscht
- Bei jeder Änderung geht eine kurze Mail an info@.
- Die eigenen Ergänzungen (Personen, Telefon, Notizen …) bleiben beim nächsten Sync erhalten. Nur die Interhome-Felder werden überschrieben.
- `api/availability.php` liest danach aus der Datenbank statt direkt aus dem iCal. Dadurch ist die Website schneller und funktioniert auch, wenn Interhome kurz nicht erreichbar ist.

### 5.3 Buchungen anreichern

Für Interhome-Buchungen fehlen Kontaktangaben. Zwei Wege, die sich ergänzen:

1. **Von Hand** im Admin-Center: Telefon, E-Mail, Personen, Anreisezeit, Notiz.
2. **Gästeformular («Online-Check-in»)**: Der Gast erhält einen persönlichen Link, etwa 14 Tage vor Anreise. Darüber gibt er selbst an:
   - Personen und Alter (für die Kurtaxe)
   - Anreisezeit
   - Autokennzeichen
   - Wünsche

   Funktioniert für Direktgäste sofort. Für Interhome-Gäste nur, wenn wir ihre E-Mail haben.

3. **Optional, Etappe 5: Interhome-Mails auslesen.** Ein Abruf liest die Interhome-Benachrichtigungen aus dem Postfach (IMAP) und füllt Personen und Kontakt automatisch ein. Voraussetzung: ein Beispiel einer solchen Mail.

### 5.4 Gästekommunikation

Vorlagen (DE/EN, im Admin-Center bearbeitbar), jeweils mit Vorschau. Gesendet wird erst nach Klick, später auf Wunsch automatisch:

| Vorlage | Zeitpunkt |
|---|---|
| Bestätigung + Zahlungsangaben | beim Annehmen |
| Absage | beim Ablehnen |
| Zahlungserinnerung | wenn Zahlung überfällig (Etappe 4) |
| Anreiseinfo mit Guide-Link und Check-in-Formular | 14 Tage vor Anreise |
| Danke + Bewertungsbitte (Google) | 1 Tag nach Abreise |

Alle gesendeten Mails stehen bei der Buchung.

### 5.5 Übersicht (Startseite Admin-Center)

- Offene Anfragen (mit Alter: «seit 2 Tagen offen»)
- Nächste An- und Abreisen (30 Tage), mit Wechseltagen für die Reinigung
- Offene Aufgaben (Interhome eintragen, Zahlung offen, Daten fehlen)
- Jahreszahlen: belegte Nächte, Auslastung, Einnahmen direkt (Interhome-Einnahmen nur, wenn wir sie erfassen)

### 5.6 Kalender-Abo

- Eine geheime iCal-Adresse mit allen Belegungen, die man im iPhone- oder Google-Kalender abonnieren kann
- Optional eine zweite Adresse nur mit Wechseltagen für die Reinigungsperson, ohne Gastdaten

## 6. Aufbau im Projekt

```
admin/                 Admin-Center (PHP-Seiten, admin.css, admin.js)
  index.php            Router: /admin, /admin/buchungen, /admin/buchung/123, /admin/kalender …
app/                   gemeinsamer PHP-Code (DB, Auth, Mail, Sync); per .htaccess gesperrt
cron/sync.php          Interhome-Sync, nur per Kommandozeile oder mit geheimem Token
config/admin.php       Benutzer-Grundeinstellungen, Token, später API-Key (nicht im Repo, wie interhome.php)
bambi-data/            ausserhalb des Webroots: admin.sqlite, Sicherungen, bestehende .jsonl
```

Der Deploy bleibt gleich. `bambi-data` liegt ausserhalb des FTP-Zielordners und wird nie überschrieben.

## 7. Sicherheit und Datenschutz

- `/admin` mit `noindex`, in `robots.txt` ausgeschlossen, nirgends verlinkt
- Sitzungs-Cookie `Secure`, `HttpOnly`, `SameSite=Strict`; Abmeldung nach 30 Tagen; CSRF-Token in jedem Formular
- Anmelde-Sperre nach 5 Fehlversuchen (15 Minuten), mit Mail an info@
- Strenge Content-Security-Policy im Admin-Bereich (nur eigene Skripte)
- Datenbank und Sicherungen nur ausserhalb des Webroots, Rechte 0600
- Tägliche Sicherung der Datenbank, 30 Tage aufbewahrt. Wöchentlich zusätzlich ein Download durch uns (lokal, nicht per Mail)
- Löschfristen:
  - Gastdaten 2 Jahre nach Abreise anonymisieren
  - Buchungen mit Rechnung 10 Jahre aufbewahren (Buchführung)
- Datenschutzerklärung ergänzen: Speicherung der Buchungsdaten, Interhome als Quelle, ggf. KI-Anbieter

## 8. KI (optional, Etappe 5)

Möglich auf Hostpoint über einen API-Schlüssel (Anthropic), der in `config/admin.php` liegt. Sinnvolle Einsätze:

- Antwortentwurf auf eine Anfrage mit Sonderwunsch («Dürfen wir den Hund mitbringen?»), auf Basis von Hausregeln und Guide
- Interhome-Mails auslesen, wenn sich das Format ändert (robuster als feste Regeln)
- Kurze Jahreszusammenfassung

Die KI schreibt nie selbst etwas an Gäste. Sie liefert Entwürfe, die man prüft und sendet. Die Kosten liegen bei dieser Menge im Bereich von wenigen Franken pro Jahr.

## 9. Etappen

| Etappe | Inhalt | Aufwand (grob) | Ergebnis |
|---|---|---|---|
| 0 | Server-Prüfung: `pdo_sqlite`, Cron, IMAP-Erweiterung, Schreibrechte `bambi-data`. Prüfskript kurz hochladen, danach löschen | 1–2 Std. | Gewissheit über Technik |
| 1 | **Grundgerüst:** Login, Datenbank, Interhome-Sync per Cron, Website-Anfragen in die DB (inkl. Import der bestehenden .jsonl), Buchungsliste + Kalender, Sperren im Admin-Center, `availability.php` liest aus der DB | 2–3 Tage | Alle Belegungen an einem Ort, Sperren ohne Code |
| 2 | **Anfragen bearbeiten:** Annehmen/Ablehnen/Reservieren, Mailvorlagen mit Vorschau, Aufgabe «In Interhome eintragen» mit automatischem Abgleich, Verlauf | 2 Tage | Direktbuchung in wenigen Klicks, keine Doppelbuchung |
| 3 | **Anreichern + Gäste:** Gast-Stammdaten, Online-Check-in per Link, Anreise-/Dankesmail, Kalender-Abo, Übersicht mit Wechseltagen | 2–3 Tage | Vollständige Gastangaben, weniger Mails von Hand |
| 4 | **Geld + Sicherheit:** Zahlungsstatus, QR-Rechnung als PDF, Jahresauswertung, Zwei-Faktor-Anmeldung, Löschfristen automatisch | 2 Tage | Überblick über Einnahmen |
| 5 | **Optional:** Interhome-Mails per IMAP, KI-Entwürfe, Preise im Admin-Center ändern | je 1 Tag | Weniger Handarbeit |

Jede Etappe ist für sich nutzbar und wird einzeln live geschaltet. Getestet wird lokal (`php -S`, SQLite, Playwright) und danach auf dem Server mit einer Testbuchung.

## 10. Offene Fragen an die Familie

1. **Wer bekommt ein Konto?** (Namen + E-Mail)
2. **Zahlung bei Direktbuchungen:** Vorauskasse, Anzahlung (wie viel %) oder vor Ort? Welches Konto (IBAN für die QR-Rechnung)?
3. **Interhome-Mail:** Bitte eine Beispiel-Benachrichtigung einer Buchung weiterleiten (für Etappe 5)
4. **Reinigung:** Wer reinigt nach Interhome-Gästen, und soll diese Person die Wechseltage sehen?
5. **Check-in/-out-Zeiten:** Interhome 16:00/10:00, Guide 15:00/12:00. Was gilt? (wird in Mails und Guide verwendet)
6. **Reservieren** (48 Std. halten): gewünscht oder nur Annehmen/Ablehnen?
