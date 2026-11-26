# Admin-Center Chalet Bambi – Umsetzungsplan

Stand: 26.11.2026 · Status: Etappe 0 und 1 umgesetzt (live unter /admin)

Umsetzungsnotizen Etappe 0/1:
- Hostpoint läuft mit PHP 8.3 (nicht 8.5), die IMAP-Erweiterung ist vorhanden. SQLite liegt in `bambi-data/admin.sqlite`, Sicherungen täglich in `bambi-data/sicherungen/`.
- Zugangsdaten (`config/admin.php`) schreibt der Deploy aus dem GitHub-Secret `BAMBI_ADMIN_CONFIG` (base64). Nach einer Änderung muss das Secret neu gesetzt werden: `base64 -i config/admin.php | gh secret set BAMBI_ADMIN_CONFIG`.
- Interhome gibt allen Sperren dieselbe UID. Darum gilt für Sperren der Schlüssel UID + Daten.
- Website und Formular lesen die Belegung aus der Datenbank. Fällt sie aus, greift der alte Weg (`app/availability_legacy.php`).
- Cron-Adresse für den späteren Mail-Abruf: `/admin/cron?token=…` (Token in `config/admin.php`). Bis dahin gleicht jeder Aufruf von Website oder Admin-Center höchstens alle 30 Minuten ab.
Festgelegt am 26.11.:
- Konten: ein Admin-Konto (alles) und ein Konto für die Putzfrau (nur Reinigungsansicht).
- Das Postfach info@bambi-staub.ch wird eingebunden, ausgelesen und ausgewertet.
- Zahlungen erfasst der Admin von Hand, es gibt keine Bankanbindung.
- Mit der Bestätigung gehen alle Infos an den Gast. Nur der Code wird 3 Tage vor Anreise verschickt.
- Personen und Kontakt bei Interhome-Buchungen trägt der Admin meist von Hand ein, weil Interhome sie erst kurz vor Anreise mitteilt.
- Der Code ist fest und im Admin-Center hinterlegt. Er wird nur verschickt, wenn die Buchung als bezahlt markiert ist.
- Interhome-Gäste bekommen Infos und Code von uns, sobald ihre E-Mail eingetragen ist.
- Direktbuchungen zahlen 100 % im Voraus per Überweisung.
- Check-in ab 15:00, Check-out bis 12:00 (wie im Guide).

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
- **Zwei Konten:**
  - **Admin:** sieht und ändert alles.
  - **Reinigung (Putzfrau):** sieht nur ihre Ansicht, die Liste der Wechseltage mit:
    - Abreise und nächste Anreise am selben Tag ja/nein
    - Anzahl Personen
    - Bettwäsche gemietet ja/nein
    - Endreinigung ja/nein (beim Familiencode nein)
    - Notiz «für die Reinigung»

    Sie sieht keine Namen, Kontakte oder Preise. Sie kann «Reinigung erledigt» und eine Meldung (z. B. «Glühbirne Bad defekt») eintragen. Zusätzlich gibt es für sie ein Kalender-Abo mit den Wechseltagen. Ihre Anmeldung bleibt auf dem Handy 90 Tage aktiv.

  Passwörter werden mit `password_hash` (Argon2id) gespeichert. Der Verlauf hält fest, wer was geändert hat (Admin, Reinigung, Sync, Mail-Auswertung).
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
| 4a | Annehmen | 👤 | ✉️ Bestätigung mit **allem auf einmal**: Zahlungsangaben, Anreise und Parkieren, Check-in/-out-Zeiten, Link zum Aufenthalts-Guide, Kurtaxe, Hinweis «Code folgt 3 Tage vor Anreise». Vor dem Senden anpassbar. Zeitraum **sofort auf der Website gesperrt** | **Bestätigt** |
| 4b | oder Ablehnen | 👤 | ✉️ freundliche Absage, Zeitraum bleibt frei | Abgelehnt (Ende) |
| 5 | In Interhome sperren | 👤 | Aufgabe «Im Interhome-Portal als Eigentümerbuchung eintragen» | Bestätigt, Aufgabe offen |
| 6 | Abgleich Interhome | ⚙️ | Der iCal-Sync findet ein `Ownerbooking` mit denselben Daten und verknüpft es. Die Aufgabe ist erledigt. Fehlt es nach 24 Std., kommt eine Erinnerung | Bestätigt ✓ Interhome |
| 7 | Gast antwortet per Mail | ⚙️ | Die Mail-Auswertung ordnet die Antwort über die BAM-Nummer im Betreff oder die Absenderadresse der Buchung zu. Fragen erscheinen als Aufgabe, mit Antwortentwurf (KI) | – |
| 8 | Zahlung | 👤 | **Manuell:** Der Admin sieht den Zahlungseingang auf dem Konto und klickt «Bezahlt». Das Admin-Center zeigt nur offene Beträge und das Fälligkeitsdatum. Eine Erinnerung verschickt der Admin per Knopf, nicht automatisch. Keine Bankanbindung | **Bezahlt** |
| 9 | 3 Tage vor Anreise | ⚙️ ✉️ | **Code-Mail:** Code + kurze Erinnerung (Anreisezeit, Guide-Link). Ist die Buchung noch nicht als bezahlt markiert, wird nichts gesendet. Stattdessen erscheint eine Aufgabe «Zahlung prüfen, Code zurückgehalten» | Bereit |
| 11 | Anreise / Aufenthalt | 👤 | Übersicht «Heute»: wer kommt, wann, wie viele | **Vor Ort** |
| 12 | Abreise | ⚙️ | Wechseltag erscheint bei der Putzfrau. Beim Familiencode reinigen die Gäste selbst, also kein Eintrag | Abgereist |
| 13 | 1 Tag nach Abreise | ⚙️ ✉️ | Dank + Bitte um Google-Bewertung | Abgeschlossen |
| 14 | Archiv | ⚙️ | Einnahmen zählen für die Jahresauswertung. Gastdaten nach 2 Jahren anonymisiert (Rechnungen 10 Jahre aufbewahrt) | – |

**Storno (Direkt):** Der Gast schreibt eine Mail. Die Auswertung erkennt die Absicht und schlägt «Stornieren» vor. Der Admin bestätigt und es geht ✉️ eine Stornobestätigung an den Gast. Der Zeitraum ist auf der Website wieder frei. Aufgabe: Eigentümerbuchung in Interhome löschen. Danach prüft der Sync, ob sie verschwunden ist.

## 5. Roadmap: Interhome-Buchung

| # | Schritt | Wer | Was passiert | Status danach |
|---|---|---|---|---|
| 1 | Gast bucht bei Interhome | Interhome | Interhome verschickt eine Buchungsmail und ergänzt den iCal | – |
| 2 | Mail auslesen | ⚙️ (alle 10 Min.) | Mail wird als Interhome-Buchung erkannt. Ausgelesen wird, was drinsteht: Reservationsnummer, Daten, Gastname, Ertrag. Personen und Kontakt fehlen meist | **Bestätigt** (Quelle Interhome) |
| 3 | Abgleich iCal | ⚙️ (alle 30 Min.) | Der iCal-Eintrag mit derselben Reservationsnummer bestätigt die Daten. Abweichungen werden rot markiert. Kommt der iCal zuerst, entsteht die Buchung dort und die Mail ergänzt sie | Bestätigt ✓ |
| 4 | Website sperren | ⚙️ | Zeitraum auf der Website belegt (ohne Zutun) | – |
| 5 | Admin informieren | ⚙️ | Kurze Mail an info@: «Neue Interhome-Buchung 30.01.–06.02., Fam. Baumgartner, 4 Pers.» | – |
| 6 | Lücken ergänzen | 👤 | Interhome teilt Personen und Kontakt meist erst kurz vor Anreise mit. Bei der Buchung steht «Angaben fehlen», 7 Tage vor Anreise erscheint eine Aufgabe. Der Admin trägt sie von Hand ein (Schnellformular, auf dem Handy in 30 Sekunden). Enthält eine Interhome-Mail die Angaben doch, füllt die Auswertung sie selbst aus | Bestätigt |
| 7 | Änderung | ⚙️ | Eine Interhome-Änderungsmail und/oder geänderte iCal-Daten passen die Buchung an. Der Verlauf zeigt vorher/nachher, an info@ geht ein Hinweis | Bestätigt |
| 8 | Vor Anreise | ⚙️/👤 | Haben wir eine E-Mail des Gastes: ✉️ Infos + Code wie bei der Direktbuchung (Code 3 Tage vor Anreise). Fehlt die E-Mail 3 Tage vor Anreise, erscheint eine Aufgabe «E-Mail des Gastes eintragen». Bezahlt ist bei Interhome immer (Interhome kassiert) | Bereit |
| 9 | Anreise / Abreise | 👤 | Übersicht «Heute». Der Wechseltag erscheint bei der Putzfrau (Endreinigung immer, Interhome-Gäste zahlen sie über Interhome) | Vor Ort → Abgereist |
| 10 | Nach Abreise | ⚙️ | Interhome fragt selbst nach einer Bewertung, wir schicken keine eigene Mail. Mit E-Mail-Adresse optional die Bitte um eine Google-Bewertung | Abgeschlossen |
| 11 | Abrechnung | ⚙️ | Die monatliche Interhome-Abrechnung (Mail/PDF) wird ausgelesen. Der Auszahlungsbetrag wird den Buchungen zugeordnet | Abgerechnet |
| 12 | Archiv | ⚙️ | wie Direktbuchung | – |

**Storno (Interhome):** Interhome schickt eine Stornomail, oder der Eintrag verschwindet aus dem iCal. Die Buchung wird als **Storniert** markiert, die Website gibt den Zeitraum frei und an info@ geht ein Hinweis. Fehlt die Stornomail und nur der iCal-Eintrag ist weg, steht «storniert?» da, bis der Admin es bestätigt. So entsteht kein Fehlalarm, wenn der iCal kurz unvollständig ist.

**Eigentümerbuchungen und Sperren aus Interhome** (`Ownerbooking`, `blocked`) erscheinen im Kalender. Gehören sie zu einer Direktbuchung, sind sie damit verknüpft. Sonst stehen sie als eigene Sperre da.

## 6. Mail-Auswertung

**Postfach:** info@bambi-staub.ch (Hostpoint, IMAP). Die Anmeldung wurde am 26.11. getestet und funktioniert. **Im Postfach ist bisher keine einzige Interhome-Mail.** Interhome schreibt also an eine andere Adresse. Diese Adresse muss im Interhome-Eigentümerkonto auf info@bambi-staub.ch geändert werden, oder die Interhome-Mails werden automatisch an info@ weitergeleitet.

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
| 4 | Gäste + Reinigung: Code-Mail 3 Tage vorher, Dankesmail, Schnellformular für Interhome-Angaben, Konto und Ansicht für die Putzfrau inkl. Kalender-Abo, Übersicht «Heute» | 2–3 Tage | Roadmaps Schritte 9–13 |
| 5 | Geld: Zahlungen manuell erfassen, Interhome-Abrechnung auslesen, Jahresauswertung, Löschfristen | 1–2 Tage | Einnahmen im Überblick |

Getestet wird lokal (`php -S`, SQLite, Playwright, Test-Mails als .eml) und danach live mit einer Testbuchung.

## 11. IMAP-Zugang (Postfach info@bambi-staub.ch)

| Angabe | Wert |
|---|---|
| Server | `imap.mail.hostpoint.ch` |
| Port / Verschlüsselung | 993, SSL/TLS |
| Benutzername | `info@bambi-staub.ch` (ganze Adresse) |
| Passwort | erhalten, liegt lokal in `config/admin.php` (nicht im Repo) |
| Ordner | Posteingang. Falls Mail-Regeln Interhome-Mails in einen Unterordner verschieben, auch dessen Namen |

- Das Passwort kommt nur in `config/admin.php` auf dem Server (gesperrt, nicht im Repo, nicht in Mails). Ich lege die Datei per FTP ab oder ihr tragt das Passwort selbst ein.
- Ändert ihr das Postfach-Passwort später, muss es dort nachgeführt werden. Das Admin-Center meldet, wenn die Anmeldung fehlschlägt.
- Die Auswertung setzt keine Mails auf «gelesen». Ihr seht in Apple Mail/Outlook weiterhin alles wie bisher.

## 12. Offene Punkte

1. **Beispiel-Mails von Interhome:** je eine Buchung, Änderung, Storno und Monatsabrechnung, weitergeleitet oder als .eml (für Etappe 3)
2. **Vorauskasse:** Zahlungsfrist (Vorschlag: 30 Tage vor Anreise, bei kurzfristiger Buchung sofort), Kontoinhaber und IBAN für die Bestätigungsmail
3. **Code:** Er wird im Admin-Center eingetragen, nicht im Repo.
4. **Interhome-Inserat:** Dort stehen 16:00/10:00. An 15:00/12:00 anpassen lassen?
5. **Interhome-Mails an info@:** Absenderadresse im Interhome-Konto ändern oder Weiterleitung einrichten (heute ist keine Interhome-Mail im Postfach)
6. **Putzfrau:** Name und Sprache der Ansicht (DE). Hat sie ein Smartphone?
7. **API-Schlüssel Anthropic** (Etappe 3), siehe Antwort im Chat
