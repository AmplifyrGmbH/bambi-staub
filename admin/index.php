<?php
/* Admin-Center Chalet Bambi – alle Seiten unter /admin (Weiterleitung per .htaccess).
   Konten: admin (alles), reinigung (nur Wechseltage, ohne Namen/Kontakt/Preise). */
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sync.php';
require_once __DIR__ . '/../app/views.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

$pfad = trim(substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH), strlen('/admin')), '/');
$teile = $pfad === '' ? [] : explode('/', $pfad);
$seite = $teile[0] ?? '';
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

function weiter(string $ziel): never { header('Location: /admin' . ($ziel === '' ? '' : '/' . ltrim($ziel, '/'))); exit; }
function hinweis(string $text, string $art = 'ok'): void { sitzung_starten(); $_SESSION['hinweis'] = [$art, $text]; }

// ── Cron (ohne Anmeldung, mit Token): /admin/cron?token=… ──────────────
if ($seite === 'cron') {
    header('Content-Type: application/json');
    $t = (string)cfg('cron_token');
    if ($t === '' || !hash_equals($t, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit('{"ok":false}'); }
    $r = sync_interhome(true);
    sicherung_taeglich();
    exit(json_encode(['ok' => true, 'sync' => $r]));
}

// ── Anmeldung ─────────────────────────────────────────────────────────
if ($seite === 'login') {
    $fehler = '';
    if ($post) {
        csrf_pruefen();
        if (login_gesperrt()) $fehler = 'Zu viele Fehlversuche. Bitte in 15 Minuten nochmals versuchen.';
        elseif (($ziel = (string)($_SESSION['ziel'] ?? '')) !== null && anmelden((string)($_POST['login'] ?? ''), (string)($_POST['passwort'] ?? ''))) {
            $u = benutzer();   // anmelden() leert die Sitzung, das Ziel ist oben schon gemerkt
            weiter($u['rolle'] === 'reinigung' ? 'reinigung' : (preg_match('#^[a-z0-9/_-]*$#', $ziel) ? $ziel : ''));
        } else $fehler = 'Benutzername oder Passwort stimmt nicht.';
    }
    seite_login($fehler);
    exit;
}

$ich = benutzer();
if (!$ich) {
    sitzung_starten();
    if (!$post) $_SESSION['ziel'] = $pfad;
    weiter('login');
}
if ($seite === 'logout' && $post) { csrf_pruefen(); abmelden(); weiter('login'); }
if ($post) csrf_pruefen();

// Reinigung sieht nur ihre Seite
if ($ich['rolle'] === 'reinigung' && $seite !== 'reinigung') weiter('reinigung');

// Interhome im Hintergrund aktuell halten (höchstens alle 30 Minuten)
try { sync_interhome(); sicherung_taeglich(); } catch (Throwable $e) { error_log('bambi sync: ' . $e->getMessage()); }

switch ($seite) {
    case '':
        seite_uebersicht($ich);
        break;

    case 'sync':
        if ($post) {
            $r = sync_interhome(true);
            hinweis(isset($r['fehler']) ? 'Interhome-Kalender nicht erreichbar. Später nochmals versuchen.' : 'Mit Interhome abgeglichen: ' . ($r['neu'] ?? 0) . ' neu, ' . ($r['geaendert'] ?? 0) . ' geändert, ' . ($r['fehlt'] ?? 0) . ' fehlend.', isset($r['fehler']) ? 'fehler' : 'ok');
        }
        weiter((string)($_POST['zurueck'] ?? ''));

    case 'buchungen':
        seite_buchungen($ich);
        break;

    case 'kalender':
        seite_kalender($ich);
        break;

    case 'neu':
        if ($post) {
            $f = formular_buchung($_POST, null);
            if (is_string($f)) { seite_neu($ich, $f, $_POST); break; }
            $f['nummer'] = naechste_nummer();
            $id = buchung_anlegen($f, $ich['login']);
            hinweis('Buchung ' . $f['nummer'] . ' angelegt.');
            weiter("buchung/$id");
        }
        seite_neu($ich, '', ['anreise' => $_GET['von'] ?? '', 'abreise' => $_GET['bis'] ?? '']);
        break;

    case 'buchung':
        $b = q1('SELECT * FROM buchung WHERE id = ?', [(int)($teile[1] ?? 0)]);
        if (!$b) { http_response_code(404); seite_fehlt($ich); break; }
        $aktion = $teile[2] ?? '';
        if ($post && $aktion === 'loeschen' && in_array($b['quelle'], ['website', 'manuell'], true)) {
            q('DELETE FROM buchung WHERE id = ?', [$b['id']]);
            verlauf(null, $ich['login'], 'gelöscht', "{$b['nummer']} ({$b['name']}, " . dmy($b['anreise']) . '–' . dmy($b['abreise']) . ')');
            hinweis("Buchung {$b['nummer']} gelöscht.");
            weiter('buchungen');
        }
        if ($post && $aktion === 'storno-bestaetigen' && $b['ih_fehlt']) {
            q("UPDATE buchung SET status = 'storniert', geaendert = ? WHERE id = ?", [jetzt(), $b['id']]);
            verlauf((int)$b['id'], $ich['login'], 'storniert', 'Storno bestätigt (fehlt im Interhome-Kalender)');
            hinweis('Als storniert markiert. Der Zeitraum ist auf der Website wieder frei.');
            weiter("buchung/{$b['id']}");
        }
        if ($post && $aktion === '') {
            $f = formular_buchung($_POST, $b);
            if (is_string($f)) { seite_buchung($ich, $b, $f, $_POST); break; }
            $diff = [];
            foreach ($f as $k => $v) {
                $zeig = fn($x) => $x === null || $x === '' ? '–' : ($k === 'status' ? (STATUS[$x] ?? $x) : $x);
                if ((string)$b[$k] !== (string)$v) $diff[] = feldname($k) . ': ' . $zeig($b[$k]) . ' → ' . $zeig($v);
            }
            if ($diff) {
                $f['geaendert'] = jetzt();
                q('UPDATE buchung SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($f))) . ' WHERE id = ?', [...array_values($f), $b['id']]);
                verlauf((int)$b['id'], $ich['login'], 'geändert', implode("\n", $diff));
                hinweis('Gespeichert.');
            } else hinweis('Keine Änderungen.');
            weiter("buchung/{$b['id']}");
        }
        seite_buchung($ich, $b, '', null);
        break;

    case 'sperren':
        if ($post && ($teile[1] ?? '') === 'neu') {
            $von = (string)($_POST['von'] ?? ''); $bis = (string)($_POST['bis'] ?? '');
            if (!datum_ok($von) || !datum_ok($bis) || $bis < $von) { hinweis('Bitte gültige Daten eingeben (bis frühestens am selben Tag).', 'fehler'); weiter('sperren'); }
            $ende = date('Y-m-d', strtotime($bis . ' +1 day'));   // gespeichert wird exklusiv
            q('INSERT INTO sperre (von, bis, grund, quelle, erstellt) VALUES (?, ?, ?, ?, ?)', [$von, $ende, mb_substr(trim((string)($_POST['grund'] ?? '')), 0, 120) ?: 'Gesperrt', 'admin', jetzt()]);
            verlauf(null, $ich['login'], 'Sperre angelegt', dmy($von) . '–' . dmy($bis));
            $k = konflikte($von, $ende, null, true);
            hinweis('Gesperrt: ' . dmy($von) . ' bis ' . dmy($bis) . '.' . ($k ? ' Achtung: überschneidet sich mit ' . count($k) . ' Buchung(en).' : ''), $k ? 'fehler' : 'ok');
            weiter('sperren');
        }
        if ($post && ($teile[2] ?? '') === 'loeschen') {
            $s = q1("SELECT * FROM sperre WHERE id = ? AND quelle = 'admin'", [(int)($teile[1] ?? 0)]);
            if ($s) { q('DELETE FROM sperre WHERE id = ?', [$s['id']]); verlauf(null, $ich['login'], 'Sperre gelöscht', dmy($s['von']) . '–' . dmy(date('Y-m-d', strtotime($s['bis'] . ' -1 day')))); hinweis('Sperre gelöscht.'); }
            weiter('sperren');
        }
        seite_sperren($ich);
        break;

    case 'reinigung':
        $id = (int)($teile[1] ?? 0);
        if ($post && $id && in_array($teile[2] ?? '', ['erledigt', 'offen'], true)) {
            $erledigt = $teile[2] === 'erledigt';
            q('UPDATE buchung SET reinigung_erledigt_am = ? WHERE id = ?', [$erledigt ? jetzt() : null, $id]);
            verlauf($id, $ich['login'], $erledigt ? 'Reinigung erledigt' : 'Reinigung wieder offen');
            weiter('reinigung');
        }
        if ($post && ($teile[1] ?? '') === 'meldung') {
            $text = trim(mb_substr((string)($_POST['text'] ?? ''), 0, 2000));
            if ($text !== '') {
                verlauf(null, $ich['login'], 'Meldung', $text);
                mail_info('Meldung von ' . $ich['name'], "{$ich['name']} meldet aus dem Chalet:\n\n{$text}\n\n" . date('d.m.Y H:i') . "\n");
                hinweis('Danke, die Meldung ist verschickt.');
            }
            weiter('reinigung');
        }
        seite_reinigung($ich);
        break;

    default:
        http_response_code(404);
        seite_fehlt($ich);
}

// ── Formular prüfen: gibt Felder (Array) oder Fehlermeldung (String) zurück ──
function datum_ok(string $d): bool { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; }

function formular_buchung(array $p, ?array $alt): array|string {
    $ih = $alt && $alt['ih_uid'];
    $txt = fn(string $k, int $max = 200) => ($v = trim(mb_substr(str_replace("\r", '', (string)($p[$k] ?? '')), 0, $max))) === '' ? null : $v;
    $zahl = fn(string $k) => ($v = trim((string)($p[$k] ?? ''))) === '' ? null : max(0, (int)$v);
    $f = [
        'name' => $txt('name', 120), 'email' => $txt('email', 160), 'telefon' => $txt('telefon', 40), 'adresse' => $txt('adresse', 300),
        'personen' => $zahl('personen'), 'erwachsene' => $zahl('erwachsene'), 'kinder' => $zahl('kinder'),
        'anreisezeit' => $txt('anreisezeit', 40), 'notiz' => $txt('notiz', 4000), 'notiz_reinigung' => $txt('notiz_reinigung', 1000),
        'bezahlt_am' => $txt('bezahlt_am', 10),
    ];
    if ($f['email'] !== null && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) return 'Die E-Mail-Adresse ist ungültig.';
    if ($f['bezahlt_am'] !== null && !datum_ok($f['bezahlt_am'])) return 'Bezahlt am: ungültiges Datum.';
    if ($f['personen'] !== null && $f['personen'] > 4) return 'Höchstens 4 Personen.';
    if (!$ih) {
        $f['anreise'] = (string)($p['anreise'] ?? ''); $f['abreise'] = (string)($p['abreise'] ?? '');
        if (!datum_ok($f['anreise']) || !datum_ok($f['abreise']) || $f['abreise'] <= $f['anreise']) return 'Bitte An- und Abreise angeben (Abreise nach der Anreise).';
        $f['status'] = array_key_exists((string)($p['status'] ?? ''), STATUS) ? (string)$p['status'] : 'anfrage';
        $preis = trim(str_replace(['’', "'", ' ', 'CHF'], '', (string)($p['preis'] ?? '')));
        $f['preis'] = $preis === '' ? null : round((float)$preis, 2);
        $f['sprache'] = ($p['sprache'] ?? 'de') === 'en' ? 'en' : 'de';
        if ($f['status'] === 'bestaetigt' && ($k = konflikte($f['anreise'], $f['abreise'], $alt['id'] ?? null))) {
            return 'Bestätigen nicht möglich, der Zeitraum überschneidet sich mit: ' . implode(', ', array_map(fn($x) => $x['text'], $k));
        }
        if (!$alt) { $f['quelle'] = 'manuell'; $f['reinigung'] = 'yes'; }
    }
    return $f;
}

function feldname(string $k): string {
    return ['name' => 'Name', 'email' => 'E-Mail', 'telefon' => 'Telefon', 'adresse' => 'Adresse', 'personen' => 'Personen', 'erwachsene' => 'Erwachsene',
        'kinder' => 'Kinder', 'anreisezeit' => 'Anreisezeit', 'notiz' => 'Notiz', 'notiz_reinigung' => 'Notiz Reinigung', 'bezahlt_am' => 'Bezahlt am',
        'anreise' => 'Anreise', 'abreise' => 'Abreise', 'status' => 'Status', 'preis' => 'Preis', 'sprache' => 'Sprache'][$k] ?? $k;
}

/* Überschneidungen mit bestätigten Buchungen und Sperren (Ende exklusiv). */
function konflikte(string $von, string $bis, ?int $ohneId, bool $nurBuchungen = false): array {
    $k = [];
    // Eigentümerbuchungen aus Interhome sind meist unsere eigenen Direktbuchungen (dort zum Sperren eingetragen): kein Konflikt.
    foreach (q("SELECT id, nummer, name, anreise, abreise FROM buchung WHERE status = 'bestaetigt' AND quelle != 'eigentuemer' AND anreise < ? AND abreise > ? AND id != ?", [$bis, $von, $ohneId ?? 0]) as $b) {
        $k[] = ['text' => ($b['name'] ?: $b['nummer']) . ' (' . dm($b['anreise']) . '–' . dm($b['abreise']) . ')', 'id' => $b['id']];
    }
    if (!$nurBuchungen) foreach (q('SELECT von, bis, grund FROM sperre WHERE von < ? AND bis > ?', [$bis, $von]) as $s) {
        $k[] = ['text' => 'Sperre ' . dm($s['von']) . '–' . dm(date('Y-m-d', strtotime($s['bis'] . ' -1 day'))), 'id' => null];
    }
    return $k;
}

/* Fortlaufende Nummer BAM-…, derselbe Zähler wie das Website-Formular. */
function naechste_nummer(): string {
    $fh = fopen(data_dir() . '/nummer.txt', 'c+');
    flock($fh, LOCK_EX);
    $nr = max(1000, (int)stream_get_contents($fh) + 1);
    ftruncate($fh, 0); rewind($fh); fwrite($fh, (string)$nr);
    flock($fh, LOCK_UN); fclose($fh);
    return 'BAM-' . $nr;
}

/* Tägliche Sicherung der Datenbank (30 Tage aufbewahrt). */
function sicherung_taeglich(): void {
    $dir = data_dir() . '/sicherungen';
    $ziel = $dir . '/admin-' . date('Y-m-d') . '.sqlite';
    if (is_file($ziel)) return;
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    db()->exec('VACUUM INTO ' . db()->quote($ziel));
    @chmod($ziel, 0600);
    foreach (glob($dir . '/admin-*.sqlite') ?: [] as $f) if (filemtime($f) < time() - 30 * 86400) @unlink($f);
}
