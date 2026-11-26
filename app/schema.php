<?php
/* Datenbankschema und einmaliger Import bestehender Daten (Website-Anfragen aus .jsonl, manuelle Sperren). */
declare(strict_types=1);

function schema_migrieren(PDO $db): void {
    $v = (int)$db->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) schema_v1($db);
    if ($v < 2) schema_v2($db);
}

/* Etappen 2–5: Annehmen/Ablehnen, Interhome-Verknüpfung, Gast-Mails, Postfach, Vorschläge, Geld */
function schema_v2(PDO $db): void {
    $db->exec('BEGIN IMMEDIATE');
    if ((int)$db->query('PRAGMA user_version')->fetchColumn() >= 2) { $db->exec('COMMIT'); return; }
    foreach ([
        'bestaetigt_am TEXT', 'zahlung_faellig TEXT', 'zahlung_erinnert_am TEXT', 'partner_id INTEGER', 'ih_eingetragen_am TEXT',
        'info_gesendet_am TEXT', 'code_gesendet_am TEXT', 'dank_gesendet_am TEXT', 'ertrag_interhome REAL', 'anonymisiert_am TEXT',
    ] as $spalte) $db->exec("ALTER TABLE buchung ADD COLUMN $spalte");
    $db->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS mail (
        id INTEGER PRIMARY KEY,
        richtung TEXT NOT NULL,            -- ein | aus
        imap_schluessel TEXT UNIQUE,       -- ordner|uidvalidity|uid
        message_id TEXT, von TEXT, von_name TEXT, an TEXT, betreff TEXT, datum TEXT,
        kurztext TEXT, kategorie TEXT,     -- interhome_buchung | interhome_aenderung | interhome_storno | interhome_abrechnung | interhome | gast | formular | intern | sonstiges | gesendet
        buchung_id INTEGER, ergebnis TEXT, verarbeitet_mit TEXT, erledigt INTEGER NOT NULL DEFAULT 0, erstellt TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS mail_buchung ON mail (buchung_id);
    CREATE TABLE IF NOT EXISTS vorschlag (
        id INTEGER PRIMARY KEY, buchung_id INTEGER NOT NULL, mail_id INTEGER, feld TEXT NOT NULL, alt TEXT, neu TEXT,
        status TEXT NOT NULL DEFAULT 'offen', erstellt TEXT NOT NULL
    );
    SQL);
    // Bereits bestätigte Direktbuchungen gelten als bestätigt am Tag der letzten Änderung
    $db->exec("UPDATE buchung SET bestaetigt_am = geaendert WHERE status = 'bestaetigt' AND quelle IN ('website','manuell')");
    $db->exec('PRAGMA user_version = 2');
    $db->exec('COMMIT');
}

function schema_v1(PDO $db): void {
    $db->exec('BEGIN IMMEDIATE');   // zwei gleichzeitige Erstaufrufe: nur einer migriert
    if ((int)$db->query('PRAGMA user_version')->fetchColumn() >= 1) { $db->exec('COMMIT'); return; }

    $db->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS buchung (
        id INTEGER PRIMARY KEY,
        nummer TEXT UNIQUE,
        quelle TEXT NOT NULL,              -- website | interhome | eigentuemer | manuell
        status TEXT NOT NULL,              -- anfrage | bestaetigt | abgelehnt | storniert
        anreise TEXT NOT NULL, abreise TEXT NOT NULL,
        personen INTEGER, erwachsene INTEGER, kinder INTEGER,
        name TEXT, email TEXT, telefon TEXT, adresse TEXT, sprache TEXT,
        preis REAL, rabattcode TEXT, reinigung TEXT, bettwaesche INTEGER,
        nachricht TEXT, anreisezeit TEXT, notiz TEXT, notiz_reinigung TEXT,
        bezahlt_am TEXT, reinigung_erledigt_am TEXT,
        ih_uid TEXT UNIQUE, ih_status TEXT, ih_fehlt INTEGER NOT NULL DEFAULT 0,
        erstellt TEXT NOT NULL, geaendert TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS buchung_datum ON buchung (anreise, abreise);
    CREATE TABLE IF NOT EXISTS sperre (
        id INTEGER PRIMARY KEY, von TEXT NOT NULL, bis TEXT NOT NULL, grund TEXT,
        quelle TEXT NOT NULL DEFAULT 'admin', ih_uid TEXT UNIQUE, erstellt TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS verlauf (
        id INTEGER PRIMARY KEY, buchung_id INTEGER, wer TEXT NOT NULL, aktion TEXT NOT NULL, details TEXT, zeit TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS verlauf_buchung ON verlauf (buchung_id);
    CREATE TABLE IF NOT EXISTS meta (schluessel TEXT PRIMARY KEY, wert TEXT);
    CREATE TABLE IF NOT EXISTS login_versuch (id INTEGER PRIMARY KEY, ip TEXT, benutzer TEXT, zeit INTEGER NOT NULL);
    SQL);

    // Bestehende Website-Anfragen übernehmen
    $jetzt = date('Y-m-d H:i:s');
    $ins = $db->prepare('INSERT OR IGNORE INTO buchung (nummer, quelle, status, anreise, abreise, personen, name, email, telefon, adresse, sprache, preis,
        rabattcode, reinigung, bettwaesche, nachricht, erstellt, geaendert) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $dateien = glob(data_dir() . '/anfragen*.jsonl') ?: [];
    sort($dateien);
    foreach ($dateien as $datei) {
        foreach (file($datei, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $zeile) {
            $a = json_decode($zeile, true);
            if (!is_array($a) || empty($a['anreise']) || empty($a['abreise'])) continue;
            $zeit = !empty($a['zeit']) ? date('Y-m-d H:i:s', strtotime($a['zeit'])) : $jetzt;
            $ins->execute([$a['ticket'] ?? null, 'website', 'anfrage', $a['anreise'], $a['abreise'], $a['personen'] ?? null, $a['name'] ?? null,
                $a['email'] ?? null, $a['telefon'] ?? null, $a['adresse'] ?? null, $a['sprache'] ?? 'de', $a['preis'] ?? null,
                ($a['rabattcode'] ?? '') ?: null, $a['reinigung'] ?? null, $a['bettwaesche'] ?? null, ($a['nachricht'] ?? '') ?: null, $zeit, $zeit]);
            if ($ins->rowCount()) {
                $db->prepare('INSERT INTO verlauf (buchung_id, wer, aktion, details, zeit) VALUES (?, ?, ?, ?, ?)')
                   ->execute([(int)$db->lastInsertId(), 'system', 'importiert', 'aus der bisherigen Anfragen-Ablage', $jetzt]);
            }
        }
    }

    // Manuelle Sperren aus config/manual-blocks.php übernehmen
    $mf = APP_ROOT . '/config/manual-blocks.php';
    if (is_file($mf)) {
        foreach ((array)(require $mf) as $b) {
            if (!empty($b['start']) && !empty($b['end'])) {
                $db->prepare('INSERT INTO sperre (von, bis, grund, quelle, erstellt) VALUES (?, ?, ?, ?, ?)')
                   ->execute([$b['start'], $b['end'], $b['label'] ?? 'Gesperrt', 'admin', $jetzt]);
            }
        }
    }

    $db->exec('PRAGMA user_version = 1');
    $db->exec('COMMIT');
}
