<?php
/* Gemeinsamer Code fürs Admin-Center: Konfiguration, Datenbank (SQLite ausserhalb des Webroots), Hilfsfunktionen.
   Läuft mit PHP 8.3 (Hostpoint). Dieser Ordner ist per .htaccess gesperrt. */
declare(strict_types=1);
date_default_timezone_set('Europe/Zurich');

const APP_ROOT  = __DIR__ . '/..';
const SITE_URL  = 'https://bambi-staub.ch';
const INFO_MAIL = 'info@bambi-staub.ch';

function cfg(?string $key = null): mixed {
    static $c = null;
    if ($c === null) $c = is_file(APP_ROOT . '/config/admin.php') ? (require APP_ROOT . '/config/admin.php') : [];
    return $key === null ? $c : ($c[$key] ?? null);
}

function data_dir(): string {
    $d = getenv('BAMBI_DATA') ?: dirname(__DIR__, 3) . '/bambi-data';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

function db(): PDO {
    static $db = null;
    if ($db) return $db;
    $file = data_dir() . '/admin.sqlite';
    $neu  = !is_file($file);
    $db = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000; PRAGMA foreign_keys = ON;');
    if ($neu) @chmod($file, 0600);
    require_once __DIR__ . '/schema.php';
    schema_migrieren($db);
    return $db;
}

function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function q1(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function qv(string $sql, array $p = []): mixed { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

function meta_get(string $k): ?string { return qv('SELECT wert FROM meta WHERE schluessel = ?', [$k]); }
function meta_set(string $k, string $v): void { q('INSERT INTO meta (schluessel, wert) VALUES (?, ?) ON CONFLICT(schluessel) DO UPDATE SET wert = excluded.wert', [$k, $v]); }

function jetzt(): string { return date('Y-m-d H:i:s'); }
function heute(): string { return date('Y-m-d'); }
function h(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function verlauf(?int $buchungId, string $wer, string $aktion, string $details = ''): void {
    q('INSERT INTO verlauf (buchung_id, wer, aktion, details, zeit) VALUES (?, ?, ?, ?, ?)', [$buchungId, $wer, $aktion, $details, jetzt()]);
}

/* Mail an info@ (Hinweise aus dem Admin-Center). Lokal mit BAMBI_DEV als Datei. */
function mail_info(string $betreff, string $text): void {
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $headers = 'From: ' . $enc('Admin-Center Chalet Bambi') . ' <' . INFO_MAIL . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    $body = chunk_split(base64_encode($text));
    if ($dev = getenv('BAMBI_DEV')) { @file_put_contents($dev . '/admin-' . date('His') . '-' . bin2hex(random_bytes(3)) . '.eml', "To: " . INFO_MAIL . "\r\nSubject: " . $enc($betreff) . "\r\n$headers\r\n$body"); return; }
    @mail(INFO_MAIL, $enc($betreff), $body, $headers, '-f' . INFO_MAIL);
}

/* Datum TT.MM.JJJJ und Nächte */
function dmy(?string $d): string { return $d ? date('d.m.Y', strtotime($d)) : '–'; }
function dm(?string $d): string { return $d ? date('d.m.', strtotime($d)) : '–'; }
function wt(string $d): string { return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', strtotime($d))]; }
function naechte(string $von, string $bis): int { return (int)round((strtotime($bis . ' 12:00') - strtotime($von . ' 12:00')) / 86400); }
function betrag(mixed $n): string { if ($n === null || $n === '') return '–'; $n = (float)$n; return 'CHF ' . (floor($n) == $n ? number_format($n, 0, '.', '’') : number_format($n, 2, '.', '’')); }

/* Belegungen, die den Kalender sperren (für Website und Admin): bestätigte Buchungen + Sperren. Ende exklusiv. */
function belegungen(string $ab): array {
    $b = q("SELECT anreise AS start, abreise AS end FROM buchung WHERE status = 'bestaetigt' AND abreise >= ? ORDER BY anreise", [$ab])->fetchAll();
    $s = q('SELECT von AS start, bis AS end FROM sperre WHERE bis >= ? ORDER BY von', [$ab])->fetchAll();
    return array_merge($b, $s);
}

/* Neue Buchung anlegen (Website-Formular, manuell). Gibt die ID zurück. */
function buchung_anlegen(array $f, string $wer): int {
    $f += ['quelle' => 'manuell', 'status' => 'anfrage', 'erstellt' => jetzt()];
    $f['geaendert'] = jetzt();
    $spalten = array_keys($f);
    q('INSERT INTO buchung (' . implode(', ', $spalten) . ') VALUES (' . implode(', ', array_fill(0, count($spalten), '?')) . ')', array_values($f));
    $id = (int)db()->lastInsertId();
    verlauf($id, $wer, 'angelegt', ($f['quelle'] === 'website' ? 'Anfrage über die Website' : 'von Hand erfasst'));
    return $id;
}

const STATUS = [
    'anfrage'    => 'Anfrage',
    'bestaetigt' => 'Bestätigt',
    'abgelehnt'  => 'Abgelehnt',
    'storniert'  => 'Storniert',
];
const QUELLE = [
    'website'     => 'Website',
    'interhome'   => 'Interhome',
    'eigentuemer' => 'Eigentümer (Interhome)',
    'manuell'     => 'Von Hand',
];
