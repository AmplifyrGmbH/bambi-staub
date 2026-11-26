# Admin-Center Chalet Bambi – Umsetzungsplan

Stand: 26.11.2026 · Status: Entwurf zur Freigabe
Festgelegt am 26.11.:
- Es gibt ein einziges Admin-Konto.
- Das Postfach wird eingebunden, ausgelesen und ausgewertet.

## 1. Ziel

Ein geschützter Bereich unter `bambi-staub.ch/admin`, in dem alle Belegungen an einem Ort zusammenlaufen:

- Direktanfragen von der Website annehmen oder ablehnen, mit fertigen Antwortmails
- Interhome-Buchungen kommen automatisch herein, aus dem Kalender (iCal) und aus den Interhome-Mails
- Das Postfach wird laufend ausgelesen. Jede Mail landet bei der richtigen Buchung, und erkannte Angaben (Personen, Kontakt, Änderungen, Storno, Abrechnung) werden übernommen.
- Sperrzeiten selbst eintragen (ersetzt `config/manual-blocks.php`)
- Übersicht: nächste Anreisen, offene Aufgaben, Belegung und Einnahmen pro Jahr

Nicht Ziel: Online-Zahlung, Bewertungen auf der Website, Ersatz von Interhome.

## 2. Ausgangslage (geprüft)

| Bereich | Heute |
|---|---|
| Hosting | Hostpoint, Apache, PHP 8.5, Deploy per GitHub Actions (FTP) |
| Verfügbarkeit | `api/availability.php` liest den Interhome-iCal (30 Min. Cache) plus `config/manual-blocks.php` |
| Interhome-iCal | liefert pro Eintrag: Reservationsnummer (`IH:209527140774`), Status (`CONFIRMED`, `Ownerbooking`, `blocked`), Gastname (nur bei `CONFIRMED`), An- und Abreise. Keine Personenzahl, kein Kontakt, kein Preis |
| Website-Anfragen | `api/submit-inquiry.php`: Mail an info@bambi-staub.ch, Bestätigung an den Gast, Kopie in `bambi-data/anfragen-JJJJ-MM.jsonl` (ausserhalb des Webroots) |
| Nummern | fortlaufend ab BAM-1000 |
| Preise | nur in `config/pricing.php` |
| Lücke | Eine angenommene Direktbuchung sperrt weder die Website noch Interhome, bis jemand sie von Hand einträgt |

## 3. Entscheide

- **Server: Hostpoint.** Alles läuft im bestehenden PHP-Projekt. Es braucht keinen zweiten Server. Der Hetzner-Server springt nur ein, falls der Hostpoint-Cron nicht genügt.
- **Datenbank: SQLite** in `bambi-data/admin.sqlite`, ausserhalb des Webroots. Eine Sicherung ist eine Dateikopie. Fehlt `pdo_sqlite`, nehmen wir MariaDB von Hostpoint mit demselben Code.
- **Kein Framework.** Schlankes PHP mit eigenem Router, wenig JavaScript.
- **Ein Admin-Konto.** Benutzer `admin`, Passwort mit `password_hash` (Argon2id). Der Verlauf hält trotzdem fest, was wann geändert wurde. Die Mail-Auswertung erscheint dort als eigener Absender.
- **Postfach per IMAP.** PHP 8.4+ hat die alte `imap`-Erweiterung nicht mehr. Wir verwenden deshalb eine IMAP-Anbindung in reinem PHP (über Sockets, ohne Erweiterung). Das prüft Etappe 0.
- **Handy zuerst.** Jede Ansicht gibt es als Liste und als Kalender.

## 4. Roadmap: Direktbuchung (Website)

Legende:
- ⚙️ = läuft automatisch
- 👤 = Admin klickt oder trägt ein
- ✉️ = Mail an den Gast

| # | Schritt | Wer | Was passiert | Status danach |
|---|---|---|---|---|
| 1 | Anfrage über /buchen | Gast | Formular wie heute. Nummer BAM-xxxx, Preis wird serverseitig berechnet | **Anfrage** |
| 2 | Anfrage erfassen | ⚙️ | Buchung und Gast in der DB. ✉️ Eingangsbestätigung an den Gast, Mail an info@ mit Link «Im Admin-Center öffnen». Der Zeitraum ist noch frei | Anfrage |
| 3 | Prüfen | 👤 | Das Admin-Center zeigt Konflikte (Interhome, Sperren, andere Anfragen), Stammgast ja/nein, Preis | Anfrage |
| 4a | Annehmen | 👤 | ✉️ Bestätigung mit Zahlungsangaben (QR-Rechnung ab Etappe 4), vor dem Senden anpassbar. Zeitraum **sofort auf der Website gesperrt** | **Bestätigt** |
| 4b | oder Ablehnen | 👤 | ✉️ freundliche Absage, Zeitraum bleibt frei | Abgelehnt (Ende) |
| 5 | In Interhome sperren | 👤 | Aufgabe «Im Interhome-Portal als Eigentümerbuchung eintragen» | Bestätigt, Aufgabe offen |
| 6 | Abgleich Interhome | ⚙️ | Der iCal-Sync findet ein `Ownerbooking` mit denselben Daten und verknüpft es. Die Aufgabe ist erledigt. Fehlt es nach 24 Std., kommt eine Erinnerung | Bestätigt ✓ Interhome |
| 7 | Gast antwortet per Mail | ⚙️ | Die Mail-Auswertung ordnet die Antwort über die BAM-Nummer im Betreff oder die Absenderadresse der Buchung zu. Fragen erscheinen als Aufgabe, mit Antwortentwurf (KI) | – |
| 8 | Zahlung | ⚙️/👤 | Fälligkeit wird überwacht, ✉️ Erinnerung bei Verzug. Zahlungseingang trägt der Admin ein | **Bezahlt** |
| 9 | 14 Tage vor Anreise | ⚙️ ✉️ | Anreiseinfo mit Link zum Aufenthalts-Guide und zum **Online-Check-in** (Personen und Alter für die Kurtaxe, Anreisezeit, Autokennzeichen, Wünsche) | Bezahlt |
| 10 | Check-in ausgefüllt | ⚙️ | Angaben stehen bei der Buchung. Fehlt der Check-in 3 Tage vor Anreise, erscheint eine Aufgabe | Bereit |
| 11 | Anreise / Aufenthalt | 👤 | Übersicht «Heute»: wer kommt, wann, wie viele | **Vor Ort** |
| 12 | Abreise | ⚙️ | Wechseltag in der Übersicht. Endreinigung planen. Beim Familiencode reinigen die Gäste selbst, also kein Eintrag | Abgereist |
| 13 | 1 Tag nach Abreise | ⚙️ ✉️ | Dank + Bitte um Google-Bewertung | Abgeschlossen |
| 14 | Archiv | ⚙️ | Einnahmen zählen für die Jahresauswertung. Gastdaten nach 2 Jahren anonymisiert (Rechnungen 10 Jahre aufbewahrt) | – |

**Storno (Direkt):** Der Gast schreibt eine Mail. Die Auswertung erkennt die Absicht und schlägt «Stornieren» vor. Der Admin bestätigt und es geht ✉️ eine Stornobestätigung an den Gast. Der Zeitraum ist auf der Website wieder frei. Aufgabe: Eigentümerbuchung in Interhome löschen. Danach prüft der Sync, ob sie verschwunden ist.

## 5. Roadmap: Interhome-Buchung

| # | Schritt | Wer | Was passiert | Status danach |
|---|---|---|---|---|
| 1 | Gast bucht bei Interhome | Interhome | Interhome verschickt eine Buchungsmail und ergänzt den iCal | – |
| 2 | Mail auslesen | ⚙️ (alle 10 Min.) | Mail wird als Interhome-Buchung erkannt. Ausgelesen werden: Reservationsnummer, Daten, Gast, Personen (Erw./Kinder), Kontakt falls vorhanden, Ertrag. Die Buchung wird angelegt | **Bestätigt** (Quelle Interhome) |
| 3 | Abgleich iCal | ⚙️ (alle 30 Min.) | Der iCal-Eintrag mit derselben Reservationsnummer bestätigt die Daten. Abweichungen werden rot markiert. Kommt der iCal zuerst, entsteht die Buchung dort und die Mail ergänzt sie | Bestätigt ✓ |
| 4 | Website sperren | ⚙️ | Zeitraum auf der Website belegt (ohne Zutun) | – |
| 5 | Admin informieren | ⚙️ | Kurze Mail an info@: «Neue Interhome-Buchung 30.01.–06.02., Fam. Baumgartner, 4 Pers.» | – |
| 6 | Lücken ergänzen | 👤 | Fehlende Angaben sind markiert (z. B. Telefon). Man ergänzt sie von Hand oder sie kommen aus späteren Interhome-Mails | Bestätigt |
| 7 | Änderung | ⚙️ | Eine Interhome-Änderungsmail und/oder geänderte iCal-Daten passen die Buchung an. Der Verlauf zeigt vorher/nachher, an info@ geht ein Hinweis | Bestätigt |
| 8 | Vor Anreise | ⚙️/👤 | Haben wir eine E-Mail des Gastes: ✉️ Anreiseinfo mit Guide-Link (wie Direktbuchung, Schritt 9). Sonst Aufgabe «Guide-Link über Interhome-Nachricht senden» | Bereit |
| 9 | Anreise / Abreise | 👤 | Übersicht «Heute» und Wechseltag mit Endreinigung (Interhome-Gäste zahlen sie über Interhome, also immer einplanen) | Vor Ort → Abgereist |
| 10 | Nach Abreise | ⚙️ | Interhome fragt selbst nach einer Bewertung, wir schicken keine eigene Mail. Mit E-Mail-Adresse optional die Bitte um eine Google-Bewertung | Abgeschlossen |
| 11 | Abrechnung | ⚙️ | Die monatliche Interhome-Abrechnung (Mail/PDF) wird ausgelesen. Der Auszahlungsbetrag wird den Buchungen zugeordnet | Abgerechnet |
| 12 | Archiv | ⚙️ | wie Direktbuchung | – |

**Storno (Interhome):** Interhome schickt eine Stornomail, oder der Eintrag verschwindet aus dem iCal. Die Buchung wird als **Storniert** markiert, die Website gibt den Zeitraum frei und an info@ geht ein Hinweis. Fehlt die Stornomail und nur der iCal-Eintrag ist weg, steht «storniert?» da, bis der Admin es bestätigt. So entsteht kein Fehlalarm, wenn der iCal kurz unvollständig ist.

**Eigentümerbuchungen und Sperren aus Interhome** (`Ownerbooking`, `blocked`) erscheinen im Kalender. Gehören sie zu einer Direktbuchung, sind sie damit verknüpft. Sonst stehen sie als eigene Sperre da.

## 6. Mail-Auswertung

**Postfach:** info@bambi-staub.ch (Hostpoint, IMAP). Laufen Interhome-Mails an eine andere Adresse, wird diese entweder als zweites Postfach angebunden oder an info@ weitergeleitet.

**Grundsätze:**
- Die Auswertung liest nur und löscht nie. Jede Mail wird genau einmal verarbeitet (IMAP-UID gespeichert) und im Postfach mit dem Schlagwort `bambi-verarbeitet` markiert.
- Die Mail selbst bleibt im Postfach. In der DB stehen nur Kopfzeilen, Kurztext und das Ergebnis der Auswertung.

**Ablauf je Mail:**
1. **Erkennen (Regeln):** Absender und Betreff sortieren in
   - Interhome-Buchung / -Änderung / -Storno / -Abrechnung
   - Antwort eines Gastes (BAM-Nummer oder bekannte Adresse)
   - eigene Formularmail (wird übersprungen, ist schon in der DB)
   - Sonstiges
2. **Auslesen:** Für bekannte Interhome-Formate gibt es feste Regeln, das ist schnell und kostenlos. Passt keine Regel oder fehlt ein Pflichtfeld, liest die KI (Anthropic-API) die Mail aus und gibt ein festes JSON zurück: Reservationsnummer, Daten, Personen, Kontakt, Betrag, Absicht.
3. **Übernehmen:**
   - Leere Felder füllt die Auswertung direkt aus, mit Herkunft «aus Mail vom …».
   - Widerspricht ein Wert einem vorhandenen, entsteht ein **Vorschlag** zum Übernehmen oder Verwerfen.
   - Storno und Datumsänderung bei Direktbuchungen sind immer nur ein Vorschlag. Bei Interhome gilt die Interhome-Mail als verbindlich.
4. **Melden:** Anfragen von Gästen («Dürfen wir den Hund mitnehmen?») erscheinen als Aufgabe, mit Antwortentwurf auf Basis von Hausregeln und Guide. Gesendet wird erst nach Freigabe.

**Ansicht «Posteingang»** im Admin-Center: Liste aller verarbeiteten Mails mit Kategorie, zugeordneter Buchung und Ergebnis. Eine falsche Zuordnung lässt sich mit einem Klick korrigieren.

**Kosten KI:** nur für Mails, die die Regeln nicht schaffen. Bei dieser Menge sind es wenige Franken pro Jahr. Ohne API-Schlüssel läuft alles mit Regeln weiter, nur ohne Rückfallebene und ohne Antwortentwürfe.

## 7. Datenmodell

```
buchung   id, nummer (BAM-1000 / IH-209527140774), quelle (website | interhome | eigentuemer | manuell),
          status (anfrage | bestaetigt | bezahlt | bereit | vor_ort | abgereist | abgeschlossen | abgelehnt | storniert),
          anreise, abreise, personen, erwachsene, kinder, gast_id, sprache, preis, ertrag_interhome,
          rabattcode, reinigung, bettwaesche, anreisezeit, interhome_uid, interhome_gesperrt, notiz, erstellt, geaendert
gast      id, name, email, telefon, strasse, plz, ort, land, notiz
sperre    id, von, bis, grund, quelle (admin | interhome)
mail      id, imap_uid, richtung (ein | aus), von, an, betreff, datum, kurztext, kategorie, buchung_id, ergebnis_json, verarbeitet_mit (regel | ki)
vorschlag id, buchung_id, mail_id, feld, alt, neu, status (offen | uebernommen | verworfen)
aufgabe   id, buchung_id, art, faellig, erledigt_am
zahlung   id, buchung_id, betrag, art, faellig, bezahlt_am
verlauf   id, buchung_id, wer (admin | sync | mail), aktion, vorher, nachher, zeit
```

## 8. Aufbau im Projekt

```
admin/          Admin-Center (Seiten, admin.css, admin.js), Router /admin/…
app/            gemeinsamer PHP-Code: DB, Auth, Mail senden, IMAP, Auswertung, Sync (per .htaccess gesperrt)
cron/           sync-ical.php (30 Min.), mail-abruf.php (10 Min.), taeglich.php (Erinnerungen, Sicherung, Löschfristen)
config/admin.php  Passwort-Hash, IMAP-Zugang, API-Schlüssel, Cron-Token (nicht im Repo, wie interhome.php)
bambi-data/     ausserhalb Webroot: admin.sqlite, Sicherungen, bisherige .jsonl
```

## 9. Sicherheit und Datenschutz

- `/admin` mit `noindex`, in `robots.txt` ausgeschlossen, nirgends verlinkt
- Ein Konto mit langem Passwort, zusätzlich Zwei-Faktor-Code (TOTP, z. B. Google Authenticator), empfohlen ab Etappe 1, weil die Seite öffentlich erreichbar ist
- Cookie `Secure`/`HttpOnly`/`SameSite=Strict`, CSRF-Token, Sperre nach 5 Fehlversuchen mit Mail an info@
- DB, Sicherungen und Zugangsdaten nur ausserhalb des Webroots bzw. gesperrt, Rechte 0600
- Tägliche Sicherung, 30 Tage aufbewahrt
- Gastdaten 2 Jahre nach Abreise anonymisiert. Rechnungen 10 Jahre aufbewahrt
- Datenschutzerklärung ergänzen: Buchungsverwaltung, Auswertung von Mails, KI-Anbieter (Anthropic, USA) als Auftragsbearbeiter

## 10. Etappen

| Etappe | Inhalt | Aufwand | Ergebnis |
|---|---|---|---|
| 0 | Server prüfen: `pdo_sqlite`, Cron, IMAP-Verbindung zu info@, Schreibrechte | 1–2 Std. | Technik geklärt |
| 1 | Login (ein Admin, 2FA), DB, iCal-Sync, Website-Anfragen in die DB (inkl. Import .jsonl), Liste + Kalender, Sperren, Website liest aus DB | 2–3 Tage | Alles an einem Ort |
| 2 | **Direkt-Roadmap Schritte 1–6:** Annehmen/Ablehnen, Mailvorlagen, Aufgabe + Abgleich Interhome, Verlauf | 2 Tage | Direktbuchung in wenigen Klicks |
| 3 | **Mail-Auswertung:** IMAP-Abruf, Regeln für Interhome-Mails, KI-Rückfallebene, Vorschläge, Posteingang-Ansicht, Gastantworten zuordnen | 3 Tage | Interhome-Roadmap Schritte 2–7 und Storno automatisch |
| 4 | Gäste: Online-Check-in, Anreise- und Dankesmail, Kalender-Abo, Übersicht «Heute» und Wechseltage | 2–3 Tage | Roadmaps Schritte 9–13 |
| 5 | Geld: Zahlungen, QR-Rechnung, Interhome-Abrechnung auslesen, Jahresauswertung, Löschfristen | 2 Tage | Einnahmen im Überblick |

Getestet wird lokal (`php -S`, SQLite, Playwright, Test-Mails als .eml) und danach live mit einer Testbuchung.

## 11. Was ich noch brauche

1. **Interhome-Mails:** Wohin gehen sie (info@ oder eine andere Adresse)? Und je ein Beispiel für Buchung, Änderung, Storno und Abrechnung, zum Beispiel als weitergeleitete Mails oder .eml (für Etappe 3).
2. **IMAP-Zugang** zu info@bambi-staub.ch (Passwort kommt nur in `config/admin.php` auf dem Server, nicht ins Repo).
3. **Zahlung Direktbuchung:** Vorauskasse oder Anzahlung (wie viel %)? IBAN für die QR-Rechnung (Etappe 5).
4. **Check-in/-out-Zeiten:** Interhome 16:00/10:00, Guide 15:00/12:00. Was gilt?
5. **Endreinigung nach Interhome-Gästen:** Wer reinigt, und braucht diese Person die Wechseltage?
6. **API-Schlüssel Anthropic** (optional, Etappe 3).
