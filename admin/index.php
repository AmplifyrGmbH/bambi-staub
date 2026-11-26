<?php
/* Admin-Center Chalet Bambi – alle Seiten unter /admin (Weiterleitung per .htaccess).
   Konten: admin (alles), reinigung (nur Wechseltage, ohne Namen/Kontakt/Preise). */
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/sync.php';
require_once __DIR__ . '/../app/mail.php';
require_once __DIR__ . '/../app/automatik.php';
require_once __DIR__ . '/../app/auswertung.php';
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
    $a = automatik_lauf();
    exit(json_encode(['ok' => true, 'sync' => $r, 'automatik' => $a]));
}

// ── Kalender-Abo (ohne Anmeldung, mit Token): /admin/ical/reinigung?token=… ──
if ($seite === 'ical') {
    $t = (string)cfg('ical_feed_token');
    $art = $teile[1] ?? '';
    $soll = $art === 'alle' ? hash_hmac('sha256', 'alle', $t) : $t;
    if ($t === '' || !in_array($art, ['reinigung', 'alle'], true) || !hash_equals($soll, (string)($_GET['token'] ?? ''))) { http_response_code(403); exit; }
    header('Content-Type: text/calendar; charset=utf-8');
    exit(ical_feed($art));
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
// Rückfall, falls der Cron ausfällt: Automatik nach dem Ausliefern der Seite (PHP-FPM), höchstens alle 10 Minuten
register_shutdown_function(function (): void {
    if (getenv('BAMBI_DEV') || time() - (int)(meta_get('automatik_zuletzt') ?? 0) < 600 || !function_exists('fastcgi_finish_request')) return;
    session_write_close();
    fastcgi_finish_request();
    try { automatik_lauf(); } catch (Throwable $e) { error_log('bambi automatik: ' . $e->getMessage()); }
});

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
        $bid = (int)$b['id'];
        if ($post && $aktion === 'loeschen' && ist_direkt($b)) {
            q('DELETE FROM buchung WHERE id = ?', [$bid]);
            q("DELETE FROM vorschlag WHERE buchung_id = ?", [$bid]);
            q('UPDATE mail SET buchung_id = NULL WHERE buchung_id = ?', [$bid]);
            verlauf(null, $ich['login'], 'gelöscht', "{$b['nummer']} ({$b['name']}, " . dmy($b['anreise']) . '–' . dmy($b['abreise']) . ')');
            hinweis("Buchung {$b['nummer']} gelöscht.");
            weiter('buchungen');
        }
        if ($post && $aktion === 'storno-bestaetigen' && $b['ih_fehlt']) {
            q("UPDATE buchung SET status = 'storniert', geaendert = ? WHERE id = ?", [jetzt(), $bid]);
            verlauf($bid, $ich['login'], 'storniert', 'Storno bestätigt (fehlt im Interhome-Kalender)');
            hinweis('Als storniert markiert. Der Zeitraum ist auf der Website wieder frei.');
            weiter("buchung/$bid");
        }
        if ($post && $aktion === 'bezahlt') {
            $d = (string)($_POST['datum'] ?? heute());
            $d = datum_ok($d) ? $d : heute();
            q('UPDATE buchung SET bezahlt_am = ?, geaendert = ? WHERE id = ?', [$d, jetzt(), $bid]);
            verlauf($bid, $ich['login'], 'bezahlt', 'Zahlung eingegangen am ' . dmy($d));
            hinweis('Als bezahlt markiert. Die Code-Mail geht 3 Tage vor Anreise automatisch raus.');
            weiter("buchung/$bid");
        }
        if ($post && $aktion === 'ih-eingetragen') {
            q('UPDATE buchung SET ih_eingetragen_am = ? WHERE id = ?', [jetzt(), $bid]);
            verlauf($bid, $ich['login'], 'in Interhome eingetragen', 'von Hand bestätigt');
            hinweis('Vermerkt. Der Abgleich verknüpft die Eigentümerbuchung, sobald sie im Interhome-Kalender steht.');
            weiter("buchung/$bid");
        }
        if ($post && $aktion === 'vorschlag' && in_array($teile[4] ?? '', ['uebernehmen', 'verwerfen'], true)) {
            $v = q1("SELECT * FROM vorschlag WHERE id = ? AND buchung_id = ? AND status = 'offen'", [(int)($teile[3] ?? 0), $bid]);
            if ($v) {
                if ($teile[4] === 'uebernehmen') {
                    $fehler = vorschlag_uebernehmen($b, $v, $ich['login']);
                    if ($fehler) { hinweis($fehler, 'fehler'); weiter("buchung/$bid"); }
                    hinweis('Übernommen: ' . vorschlag_text($v));
                } else {
                    q("UPDATE vorschlag SET status = 'verworfen' WHERE id = ?", [$v['id']]);
                    verlauf($bid, $ich['login'], 'Vorschlag verworfen', vorschlag_text($v));
                    hinweis('Verworfen.');
                }
            }
            weiter("buchung/$bid");
        }
        if ($aktion === 'angaben') {
            if ($post) {
                $f = formular_angaben($_POST);
                if (is_string($f)) { seite_angaben($ich, $b, $f, $_POST); break; }
                buchung_speichern($b, $f, $ich['login']);
                weiter('');
            }
            seite_angaben($ich, $b, '', null);
            break;
        }
        if ($aktion === 'mail' && isset(MAIL_ARTEN[$teile[3] ?? ''])) {
            $art = $teile[3];
            $erlaubt = match ($art) {
                'bestaetigung', 'absage' => $b['status'] === 'anfrage',
                'storno' => $b['status'] === 'bestaetigt' && ist_direkt($b),
                'erinnerung' => $b['status'] === 'bestaetigt' && ist_direkt($b) && !$b['bezahlt_am'],
                'info', 'code' => $b['status'] === 'bestaetigt' && $b['quelle'] !== 'eigentuemer',
                'dank' => $b['status'] === 'bestaetigt' && $b['abreise'] <= heute(),
                default => false,
            };
            if (!$erlaubt) { hinweis('Diese Aktion passt nicht zum Status der Buchung.', 'fehler'); weiter("buchung/$bid"); }
            if ($art === 'bestaetigung' && ($k = konflikte($b['anreise'], $b['abreise'], $bid))) {
                hinweis('Annehmen nicht möglich, der Zeitraum überschneidet sich mit: ' . implode(', ', array_map(fn($x) => $x['text'], $k)), 'fehler');
                weiter("buchung/$bid");
            }
            $faellig = $b['zahlung_faellig'] ?: zahlung_faellig_berechnen($b);
            if ($post) {
                $ohneMail = !empty($_POST['ohne_mail']) && in_array($art, ['bestaetigung', 'absage', 'storno'], true);
                $betreff = trim(mb_substr((string)($_POST['betreff'] ?? ''), 0, 200));
                $text = trim(mb_substr(str_replace("\r", '', (string)($_POST['text'] ?? '')), 0, 20000));
                if (!$ohneMail && ($fehler = gast_mail_senden($b, $art, $betreff, $text, $ich['login']))) { seite_mail($ich, $b, $art, $betreff, $text, $fehler); break; }
                $folge = [
                    'bestaetigung' => ["status = 'bestaetigt', bestaetigt_am = ?, zahlung_faellig = ?", [jetzt(), (float)$b['preis'] > 0 ? $faellig : null], 'Angenommen. Der Zeitraum ist auf der Website gesperrt. Bitte jetzt im Interhome-Portal als Eigentümerbuchung eintragen.'],
                    'absage'       => ["status = 'abgelehnt'", [], 'Abgelehnt.'],
                    'storno'       => ["status = 'storniert'", [], 'Storniert. Der Zeitraum ist auf der Website wieder frei.' . ($b['partner_id'] ? ' Bitte die Eigentümerbuchung im Interhome-Portal löschen.' : '')],
                    'erinnerung'   => ['zahlung_erinnert_am = ?', [jetzt()], 'Erinnerung gesendet.'],
                    'info'         => ['info_gesendet_am = ?', [jetzt()], 'Infos gesendet.'],
                    'code'         => ['code_gesendet_am = ?', [jetzt()], 'Code gesendet.'],
                    'dank'         => ['dank_gesendet_am = ?', [jetzt()], 'Dankesmail gesendet.'],
                ][$art];
                q("UPDATE buchung SET {$folge[0]}, geaendert = ? WHERE id = ?", [...$folge[1], jetzt(), $bid]);
                if (in_array($art, ['bestaetigung', 'absage', 'storno'], true)) verlauf($bid, $ich['login'], ['bestaetigung' => 'angenommen', 'absage' => 'abgelehnt', 'storno' => 'storniert'][$art], $ohneMail ? 'ohne Mail an den Gast' : '');
                hinweis(($ohneMail ? '' : 'Mail gesendet. ') . $folge[2]);
                weiter("buchung/$bid");
            }
            [$betreff, $text] = vorlage($art, $b + ['zahlung_faellig' => $faellig]);
            seite_mail($ich, $b, $art, $betreff, $text, '');
            break;
        }
        if ($post && $aktion === '') {
            $f = formular_buchung($_POST, $b);
            if (is_string($f)) { seite_buchung($ich, $b, $f, $_POST); break; }
            buchung_speichern($b, $f, $ich['login']);
            weiter("buchung/$bid");
        }
        seite_buchung($ich, $b, '', null);
        break;

    case 'posteingang':
        seite_posteingang($ich);
        break;

    case 'postfach':
        if ($post) {
            $r = postfach_abrufen();
            hinweis($r['fehler'] ? 'Postfach: ' . $r['fehler'] : ($r['neu'] ? $r['neu'] . ' neue Mail(s) ausgewertet.' : 'Keine neuen Mails.'), $r['fehler'] ? 'fehler' : 'ok');
        }
        weiter('posteingang');

    case 'mail':
        $m = q1('SELECT * FROM mail WHERE id = ?', [(int)($teile[1] ?? 0)]);
        if (!$m) { http_response_code(404); seite_fehlt($ich); break; }
        $aktion = $teile[2] ?? '';
        if ($post && in_array($aktion, ['erledigt', 'offen'], true)) {
            q('UPDATE mail SET erledigt = ? WHERE id = ?', [$aktion === 'erledigt' ? 1 : 0, $m['id']]);
            weiter($aktion === 'erledigt' ? 'posteingang' : "mail/{$m['id']}");
        }
        if ($post && $aktion === 'zuordnen') {
            $nr = strtoupper(trim((string)($_POST['nummer'] ?? '')));
            $b = $nr === '' ? null : q1('SELECT * FROM buchung WHERE nummer = ? OR nummer = ?', [$nr, 'IH-' . $nr]);
            if ($nr !== '' && !$b) { hinweis("Keine Buchung mit der Nummer $nr.", 'fehler'); weiter("mail/{$m['id']}"); }
            q('UPDATE mail SET buchung_id = ? WHERE id = ?', [$b['id'] ?? null, $m['id']]);
            if ($b) verlauf((int)$b['id'], $ich['login'], 'Mail zugeordnet', (string)$m['betreff']);
            hinweis($b ? 'Zugeordnet zu ' . $b['nummer'] . '.' : 'Zuordnung entfernt.');
            weiter("mail/{$m['id']}");
        }
        if ($post && $aktion === 'antworten') {
            $b = $m['buchung_id'] ? q1('SELECT * FROM buchung WHERE id = ?', [$m['buchung_id']]) : null;
            $betreff = trim(mb_substr((string)($_POST['betreff'] ?? ''), 0, 200));
            $text = trim(mb_substr(str_replace("\r", '', (string)($_POST['text'] ?? '')), 0, 20000));
            $fehler = gast_mail_senden($b ?? ['sprache' => 'de'], 'antwort', $betreff, $text, $ich['login'], (string)$m['von'], $m['message_id'] ?: null);
            if ($fehler) { hinweis($fehler, 'fehler'); weiter("mail/{$m['id']}"); }
            q('UPDATE mail SET erledigt = 1 WHERE id = ?', [$m['id']]);
            hinweis('Antwort gesendet.');
            weiter('posteingang');
        }
        seite_mail_detail($ich, $m);
        break;

    case 'einstellungen':
        if ($post) {
            foreach (EINSTELLUNGEN as $k => [$label]) {
                $v = trim(mb_substr((string)($_POST[$k] ?? ''), 0, 300));
                if (str_starts_with($k, 'auto_')) $v = $v === '1' ? '1' : '0';
                if ($k === 'iban') $v = strtoupper(preg_replace('/\s+/', ' ', $v));
                if ($k === 'zahlungsfrist') $v = (string)max(0, min(120, (int)$v));
                if ($v !== einst($k)) { meta_set('einst_' . $k, $v); verlauf(null, $ich['login'], 'Einstellung geändert', $label . ($k === 'tuercode' ? '' : ': ' . $v)); }
            }
            hinweis('Einstellungen gespeichert.');
            weiter('einstellungen');
        }
        seite_einstellungen($ich);
        break;

    case 'geld':
        seite_geld($ich);
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
        'sprache' => ($p['sprache'] ?? 'de') === 'en' ? 'en' : 'de',
    ];
    if ($ih) { $e = trim(str_replace(['’', "'", ' ', 'CHF'], '', (string)($p['ertrag_interhome'] ?? ''))); $f['ertrag_interhome'] = $e === '' ? null : round((float)$e, 2); }
    if ($f['email'] !== null && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) return 'Die E-Mail-Adresse ist ungültig.';
    if ($f['bezahlt_am'] !== null && !datum_ok($f['bezahlt_am'])) return 'Bezahlt am: ungültiges Datum.';
    if ($f['personen'] !== null && $f['personen'] > 4) return 'Höchstens 4 Personen.';
    if (!$ih) {
        $f['anreise'] = (string)($p['anreise'] ?? ''); $f['abreise'] = (string)($p['abreise'] ?? '');
        if (!datum_ok($f['anreise']) || !datum_ok($f['abreise']) || $f['abreise'] <= $f['anreise']) return 'Bitte An- und Abreise angeben (Abreise nach der Anreise).';
        $f['status'] = array_key_exists((string)($p['status'] ?? ''), STATUS) ? (string)$p['status'] : 'anfrage';
        $preis = trim(str_replace(['’', "'", ' ', 'CHF'], '', (string)($p['preis'] ?? '')));
        $f['preis'] = $preis === '' ? null : round((float)$preis, 2);
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
        'anreise' => 'Anreise', 'abreise' => 'Abreise', 'status' => 'Status', 'preis' => 'Preis', 'sprache' => 'Sprache', 'ertrag_interhome' => 'Ertrag Interhome'][$k] ?? $k;
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

/* Felder speichern, Änderungen im Verlauf festhalten */
function buchung_speichern(array $b, array $f, string $wer): void {
    $diff = [];
    foreach ($f as $k => $v) {
        $zeig = fn($x) => $x === null || $x === '' ? '–' : ($k === 'status' ? (STATUS[$x] ?? $x) : $x);
        if ((string)$b[$k] !== (string)$v) $diff[] = feldname($k) . ': ' . $zeig($b[$k]) . ' → ' . $zeig($v);
    }
    if (!$diff) { hinweis('Keine Änderungen.'); return; }
    $f['geaendert'] = jetzt();
    // Daten einer bestätigten Direktbuchung geändert → Interhome-Sperre muss neu eingetragen werden
    if (isset($f['anreise']) && ($f['anreise'] !== $b['anreise'] || $f['abreise'] !== $b['abreise'])) $f['ih_eingetragen_am'] = null;
    q('UPDATE buchung SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($f))) . ' WHERE id = ?', [...array_values($f), $b['id']]);
    verlauf((int)$b['id'], $wer, 'geändert', implode("\n", $diff));
    hinweis('Gespeichert.');
}

/* Schnellformular für Interhome-Angaben (Personen, Kontakt, Anreisezeit) */
function formular_angaben(array $p): array|string {
    $txt = fn(string $k, int $max = 200) => ($v = trim(mb_substr((string)($p[$k] ?? ''), 0, $max))) === '' ? null : $v;
    $zahl = fn(string $k) => ($v = trim((string)($p[$k] ?? ''))) === '' ? null : max(0, (int)$v);
    $f = ['name' => $txt('name', 120), 'personen' => $zahl('personen'), 'erwachsene' => $zahl('erwachsene'), 'kinder' => $zahl('kinder'),
          'email' => $txt('email', 160), 'telefon' => $txt('telefon', 40), 'anreisezeit' => $txt('anreisezeit', 40), 'sprache' => ($p['sprache'] ?? 'de') === 'en' ? 'en' : 'de'];
    if ($f['email'] !== null && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) return 'Die E-Mail-Adresse ist ungültig.';
    if ($f['personen'] !== null && ($f['personen'] < 1 || $f['personen'] > 4)) return 'Personen: 1 bis 4.';
    return $f;
}

function vorschlag_uebernehmen(array $b, array $v, string $wer): ?string {
    $bid = (int)$b['id'];
    switch ($v['feld']) {
        case 'daten':
            [$an, $ab] = explode('|', (string)$v['neu']);
            if ($b['ih_uid']) return 'Daten von Interhome-Buchungen kommen aus dem Interhome-Kalender und werden beim nächsten Abgleich angepasst.';
            if ($b['status'] === 'bestaetigt' && ($k = konflikte($an, $ab, $bid))) return 'Nicht möglich, überschneidet sich mit: ' . implode(', ', array_map(fn($x) => $x['text'], $k));
            q('UPDATE buchung SET anreise = ?, abreise = ?, ih_eingetragen_am = NULL, geaendert = ? WHERE id = ?', [$an, $ab, jetzt(), $bid]);
            break;
        case 'status':
            q("UPDATE buchung SET status = 'storniert', geaendert = ? WHERE id = ?", [jetzt(), $bid]);
            break;
        default:
            if (!array_key_exists($v['feld'], UEBERNEHMBAR)) return 'Unbekanntes Feld.';
            q("UPDATE buchung SET {$v['feld']} = ?, geaendert = ? WHERE id = ?", [$v['neu'], jetzt(), $bid]);
    }
    q("UPDATE vorschlag SET status = 'uebernommen' WHERE id = ?", [$v['id']]);
    verlauf($bid, $wer, 'Vorschlag übernommen', vorschlag_text($v));
    return null;
}

/* Kalender-Abo: Wechseltage für die Reinigung (ohne Namen) oder alle Buchungen (für den Admin) */
function ical_feed(string $art): string {
    $esc = fn(string $s) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
    $ev = [];
    $ab = date('Y-m-d', strtotime('-30 days'));
    if ($art === 'reinigung') {
        foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle != 'eigentuemer' AND COALESCE(reinigung, 'yes') != 'no' AND abreise >= ? ORDER BY abreise", [$ab]) as $b) {
            $n = q1("SELECT * FROM buchung WHERE status = 'bestaetigt' AND anreise >= ? AND id != ? ORDER BY anreise LIMIT 1", [$b['abreise'], $b['id']]);
            $gleich = $n && $n['anreise'] === $b['abreise'];
            $desc = 'Abreise bis ' . zeiten($b)['aus'] . "\n" . ($n ? ($gleich ? 'Neue Gäste am selben Tag ab ' . zeiten($n)['ein'] : 'Nächste Anreise ' . dmy($n['anreise'])) . ($n['personen'] ? ', ' . $n['personen'] . ' Personen' : '') . ((int)$n['bettwaesche'] > 0 ? "\nBettwäsche für " . (int)$n['bettwaesche'] . ' Personen' : '') : 'Danach keine Anreise eingetragen')
                . ($b['notiz_reinigung'] ? "\n" . $b['notiz_reinigung'] : '');
            $ev[] = ['uid' => 'reinigung-' . $b['id'], 'von' => $b['abreise'], 'bis' => date('Y-m-d', strtotime($b['abreise'] . ' +1 day')), 'titel' => 'Reinigung Chalet Bambi' . ($gleich ? ' (Wechsel)' : ''), 'text' => $desc];
        }
    } else {
        foreach (q("SELECT * FROM buchung WHERE status IN ('anfrage','bestaetigt') AND abreise >= ? ORDER BY anreise", [$ab]) as $b) {
            $ev[] = ['uid' => 'buchung-' . $b['id'], 'von' => $b['anreise'], 'bis' => $b['abreise'],
                'titel' => ($b['status'] === 'anfrage' ? 'Anfrage: ' : '') . ($b['name'] ?: (QUELLE[$b['quelle']] ?? $b['quelle'])) . ($b['personen'] ? ' (' . $b['personen'] . ')' : ''),
                'text' => ($b['nummer'] ?? '') . ' · ' . (QUELLE[$b['quelle']] ?? '') . "\n" . SITE_URL . '/admin/buchung/' . $b['id']];
        }
    }
    $out = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Chalet Bambi//Admin//DE\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:" . ($art === 'reinigung' ? 'Chalet Bambi Reinigung' : 'Chalet Bambi Buchungen') . "\r\n";
    foreach ($ev as $e) {
        $out .= "BEGIN:VEVENT\r\nUID:{$e['uid']}@bambi-staub.ch\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART;VALUE=DATE:" . str_replace('-', '', $e['von'])
            . "\r\nDTEND;VALUE=DATE:" . str_replace('-', '', $e['bis']) . "\r\nSUMMARY:" . $esc($e['titel']) . "\r\nDESCRIPTION:" . $esc($e['text']) . "\r\nEND:VEVENT\r\n";
    }
    return $out . "END:VCALENDAR\r\n";
}
