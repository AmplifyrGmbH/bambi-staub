<?php
/* Anmeldung: Konten aus config/admin.php (Passwort-Hash), Sitzung in bambi-data, CSRF-Schutz, Sperre nach Fehlversuchen. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const LOGIN_MAX_FEHLER = 5;           // pro 15 Minuten und IP
const SITZUNG_TAGE     = ['admin' => 30, 'reinigung' => 90];

function sitzung_starten(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $dir = data_dir() . '/sitzungen';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $lebensdauer = max(SITZUNG_TAGE) * 86400;
    ini_set('session.gc_maxlifetime', (string)$lebensdauer);
    ini_set('session.use_strict_mode', '1');
    session_save_path($dir);
    session_name('bambi_admin');
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params(['lifetime' => $lebensdauer, 'path' => '/admin', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

/* Angemeldeter Benutzer oder null. Abgelaufene Sitzungen werden beendet. */
function benutzer(): ?array {
    sitzung_starten();
    $u = $_SESSION['benutzer'] ?? null;
    if (!$u) return null;
    $konto = cfg('users')[$u] ?? null;
    $tage = SITZUNG_TAGE[$konto['rolle'] ?? ''] ?? 1;
    if (!$konto || time() - (int)($_SESSION['seit'] ?? 0) > $tage * 86400 || ($_SESSION['hash'] ?? '') !== substr(hash('sha256', $konto['hash']), 0, 16)) {
        abmelden();
        return null;
    }
    return ['login' => $u, 'name' => $konto['name'] ?? $u, 'rolle' => $konto['rolle'] ?? 'admin'];
}

function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '?'; }

function login_gesperrt(): bool {
    return (int)qv('SELECT COUNT(*) FROM login_versuch WHERE ip = ? AND zeit > ?', [ip(), time() - 900]) >= LOGIN_MAX_FEHLER;
}

/* true bei Erfolg. Fehlversuche werden gezählt; beim Erreichen der Sperre geht eine Mail an info@. */
function anmelden(string $login, string $passwort): bool {
    $login = strtolower(trim($login));
    $konto = cfg('users')[$login] ?? null;
    if ($konto && password_verify($passwort, $konto['hash'])) {
        q('DELETE FROM login_versuch WHERE ip = ?', [ip()]);
        sitzung_starten();
        session_regenerate_id(true);
        $_SESSION = ['benutzer' => $login, 'seit' => time(), 'hash' => substr(hash('sha256', $konto['hash']), 0, 16), 'csrf' => bin2hex(random_bytes(16))];
        verlauf(null, $login, 'angemeldet', ip());
        return true;
    }
    q('INSERT INTO login_versuch (ip, benutzer, zeit) VALUES (?, ?, ?)', [ip(), mb_substr($login, 0, 40), time()]);
    q('DELETE FROM login_versuch WHERE zeit < ?', [time() - 86400]);
    if (login_gesperrt() && (int)qv('SELECT COUNT(*) FROM login_versuch WHERE ip = ? AND zeit > ?', [ip(), time() - 900]) === LOGIN_MAX_FEHLER) {
        mail_info('Admin-Center: Anmeldung gesperrt', 'Nach ' . LOGIN_MAX_FEHLER . " Fehlversuchen ist die Anmeldung für 15 Minuten gesperrt.\n\nIP: " . ip() . "\nBenutzername: {$login}\nZeit: " . date('d.m.Y H:i') . "\n\nWaren das nicht Sie, ist das kein Grund zur Sorge, solange das Passwort sicher ist.\n");
    }
    return false;
}

function abmelden(): void {
    sitzung_starten();
    $_SESSION = [];
    session_destroy();
    setcookie('bambi_admin', '', ['expires' => time() - 3600, 'path' => '/admin']);
}

function csrf_token(): string {
    sitzung_starten();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_feld(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_pruefen(): void {
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Das Formular ist abgelaufen. Bitte die Seite neu laden und nochmals versuchen.');
    }
}
