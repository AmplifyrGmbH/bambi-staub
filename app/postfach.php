<?php
/* Postfach info@bambi-staub.ch per IMAP lesen, ohne PHP-Erweiterung (Sockets, TLS).
   Nur lesend: BODY.PEEK verändert keine Flags, Mails bleiben ungelesen und werden nie gelöscht.
   Welche Mails schon verarbeitet sind, steht in der Tabelle mail (Ordner|UIDVALIDITY|UID). */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

final class Imap {
    private $s;
    private int $n = 0;

    public function __construct(string $host, int $port, int $timeout = 15) {
        $this->s = @stream_socket_client("ssl://$host:$port", $errno, $err, $timeout, STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]));
        if (!$this->s) throw new RuntimeException("IMAP-Verbindung fehlgeschlagen: $err");
        stream_set_timeout($this->s, $timeout);
        $this->zeile();   // Begrüssung
    }

    private function zeile(): string {
        $z = fgets($this->s);
        if ($z === false) throw new RuntimeException('IMAP: Verbindung abgebrochen');
        return $z;
    }

    /* Befehl senden, Antwort als Liste [Zeile, Literal?] bis zum Tag zurückgeben */
    public function befehl(string $cmd): array {
        $tag = 'B' . (++$this->n);
        fwrite($this->s, "$tag $cmd\r\n");
        $teile = [];
        while (true) {
            $z = $this->zeile();
            if (str_starts_with($z, "$tag ")) {
                if (!preg_match('/^' . $tag . ' OK/i', $z)) throw new RuntimeException('IMAP: ' . trim(substr($z, strlen($tag) + 1)));
                return $teile;
            }
            $literal = null;
            if (preg_match('/\{(\d+)\}\r\n$/', $z, $m)) {
                $literal = '';
                $rest = (int)$m[1];
                while ($rest > 0) { $chunk = fread($this->s, min(65536, $rest)); if ($chunk === false || $chunk === '') throw new RuntimeException('IMAP: Literal unvollständig'); $literal .= $chunk; $rest -= strlen($chunk); }
                $z .= $this->zeile();   // Rest der Zeile nach dem Literal, z. B. ")"
            }
            $teile[] = [$z, $literal];
        }
    }

    public static function quote(string $s): string { return '"' . addcslashes($s, '"\\') . '"'; }

    public function login(string $u, string $p): void { $this->befehl('LOGIN ' . self::quote($u) . ' ' . self::quote($p)); }

    /* Ordner wählen (nur lesend). Gibt UIDVALIDITY zurück. */
    public function oeffnen(string $ordner): string {
        $uv = '0';
        foreach ($this->befehl('EXAMINE ' . self::quote($ordner)) as [$z]) if (preg_match('/UIDVALIDITY (\d+)/i', $z, $m)) $uv = $m[1];
        return $uv;
    }

    public function uids_seit(string $datum): array {
        $uids = [];
        foreach ($this->befehl('UID SEARCH SINCE ' . date('j-M-Y', strtotime($datum))) as [$z]) {
            if (preg_match('/^\* SEARCH ?(.*)$/i', trim($z), $m) && trim($m[1]) !== '') $uids = array_merge($uids, array_map('intval', explode(' ', trim($m[1]))));
        }
        sort($uids);
        return $uids;
    }

    public function roh(int $uid): ?string {
        foreach ($this->befehl("UID FETCH $uid (BODY.PEEK[])") as [, $lit]) if ($lit !== null) return $lit;
        return null;
    }

    public function abmelden(): void { try { $this->befehl('LOGOUT'); } catch (Throwable) {} fclose($this->s); }
}

// ── MIME: Rohmail → Kopfzeilen, Text, Anhänge (nur Namen) ───────────────
function kopf_dekodieren(string $s): string {
    $r = @iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
    return $r === false ? $s : $r;
}

function mime_teilen(string $roh): array {
    $roh = str_replace("\r\n", "\n", $roh);
    $pos = strpos($roh, "\n\n");
    $kopfText = $pos === false ? $roh : substr($roh, 0, $pos);
    $body = $pos === false ? '' : substr($roh, $pos + 2);
    $kopf = [];
    foreach (explode("\n", preg_replace("/\n[ \t]+/", ' ', $kopfText)) as $z) {
        if (preg_match('/^([^:]+):\s*(.*)$/', $z, $m)) $kopf[strtolower($m[1])] = $kopf[strtolower($m[1])] ?? $m[2];
    }
    return [$kopf, $body];
}

function teil_param(string $kopfwert, string $name): ?string {
    return preg_match('/' . $name . '\*?=\s*"?([^";]+)"?/i', $kopfwert, $m) ? trim($m[1]) : null;
}

/* Liefert ['text' => …, 'html' => …, 'anhaenge' => [name, …]] */
function mime_inhalt(array $kopf, string $body, ?array &$aus = null): array {
    $aus ??= ['text' => '', 'html' => '', 'anhaenge' => []];
    $typ = strtolower($kopf['content-type'] ?? 'text/plain');
    $disp = strtolower($kopf['content-disposition'] ?? '');
    if (str_starts_with($typ, 'multipart/')) {
        $grenze = teil_param($kopf['content-type'], 'boundary');
        if (!$grenze) return $aus;
        foreach (array_slice(explode('--' . $grenze, $body), 1) as $teil) {
            if (str_starts_with($teil, '--')) break;
            [$k, $b] = mime_teilen(ltrim($teil, "\n"));
            mime_inhalt($k, $b, $aus);
        }
        return $aus;
    }
    $name = teil_param($kopf['content-disposition'] ?? '', 'filename') ?? teil_param($kopf['content-type'] ?? '', 'name');
    $enc = strtolower(trim($kopf['content-transfer-encoding'] ?? ''));
    $daten = $enc === 'base64' ? (string)base64_decode(preg_replace('/\s+/', '', $body)) : ($enc === 'quoted-printable' ? quoted_printable_decode($body) : $body);
    if (str_starts_with($disp, 'attachment') || ($name && !str_starts_with($typ, 'text/'))) { $aus['anhaenge'][] = kopf_dekodieren((string)$name); return $aus; }
    $cs = teil_param($kopf['content-type'] ?? '', 'charset') ?? 'UTF-8';
    if (strtoupper($cs) !== 'UTF-8') $daten = (string)@mb_convert_encoding($daten, 'UTF-8', $cs);
    if (str_starts_with($typ, 'text/html')) $aus['html'] .= $daten;
    elseif (str_starts_with($typ, 'text/')) $aus['text'] .= ($aus['text'] ? "\n" : '') . $daten;
    return $aus;
}

function html_zu_text(string $html): string {
    $html = preg_replace('~<(script|style|head)[^>]*>.*?</\1>~is', '', $html);
    $html = preg_replace('~<(br|/p|/div|/tr|/h\d|/li)[^>]*>~i', "\n", $html);
    $html = preg_replace('~</td>~i', ' ', $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t);
    return trim(preg_replace("/\n\s*\n+/", "\n\n", $t));
}

function mail_lesen(string $roh): array {
    [$kopf, $body] = mime_teilen($roh);
    $inhalt = mime_inhalt($kopf, $body);
    $text = trim($inhalt['text']) !== '' ? $inhalt['text'] : html_zu_text($inhalt['html']);
    $vonRoh = kopf_dekodieren($kopf['from'] ?? '');
    $von = preg_match('/<([^>]+)>/', $vonRoh, $m) ? $m[1] : trim($vonRoh);
    return [
        'von' => strtolower(trim($von)), 'von_name' => trim(preg_replace('/<[^>]+>/', '', $vonRoh), ' "'),
        'an' => kopf_dekodieren($kopf['to'] ?? ''), 'betreff' => kopf_dekodieren($kopf['subject'] ?? ''),
        'datum' => ($t = strtotime($kopf['date'] ?? '')) ? date('Y-m-d H:i:s', $t) : jetzt(),
        'message_id' => trim($kopf['message-id'] ?? ''), 'antwort_auf' => trim($kopf['in-reply-to'] ?? ''),
        'admin_art' => trim($kopf['x-bambi-admin'] ?? ''),
        'text' => trim(str_replace("\r", '', $text)), 'anhaenge' => $inhalt['anhaenge'],
    ];
}

/* Neue Mails abholen und auswerten. Höchstens $max pro Lauf. Gibt [neu, fehler] zurück. */
function postfach_abrufen(int $max = 25): array {
    $c = cfg('imap');
    if (!$c || empty($c['host'])) return ['neu' => 0, 'fehler' => 'kein IMAP-Zugang konfiguriert'];
    require_once __DIR__ . '/auswertung.php';
    $lock = fopen(data_dir() . '/postfach.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['neu' => 0, 'fehler' => null];
    $neu = 0;
    try {
        $imap = new Imap((string)$c['host'], (int)($c['port'] ?? 993));
        $imap->login((string)$c['user'], (string)$c['password']);
        // Beim ersten Abruf nur die letzten 30 Tage, danach alles seit dem letzten Abruf (mit 2 Tagen Überlappung)
        $seit = meta_get('postfach_seit') ?? date('Y-m-d', strtotime('-30 days'));
        foreach ((array)($c['folders'] ?? ['INBOX']) as $ordner) {
            $uv = $imap->oeffnen($ordner);
            foreach ($imap->uids_seit($seit) as $uid) {
                $schluessel = "$ordner|$uv|$uid";
                if (qv('SELECT 1 FROM mail WHERE imap_schluessel = ?', [$schluessel])) continue;
                if ($neu >= $max) break 2;
                $roh = $imap->roh($uid);
                if ($roh === null) continue;
                mail_verarbeiten($schluessel, mail_lesen($roh));
                $neu++;
            }
        }
        $imap->abmelden();
        if ($neu < $max) meta_set('postfach_seit', date('Y-m-d', strtotime('-2 days')));
        meta_set('postfach_ok', jetzt());
        meta_set('postfach_fehler', '');
        return ['neu' => $neu, 'fehler' => null];
    } catch (Throwable $e) {
        $alt = meta_get('postfach_fehler');
        meta_set('postfach_fehler', jetzt() . ': ' . $e->getMessage());
        // Einmal melden, nicht bei jedem Versuch
        if (!$alt) mail_info('Admin-Center: Postfach nicht erreichbar', "Der Abruf von " . INFO_MAIL . " ist fehlgeschlagen:\n\n" . $e->getMessage() . "\n\nWurde das Passwort geändert? Dann muss es in config/admin.php nachgeführt werden.\n");
        return ['neu' => $neu, 'fehler' => $e->getMessage()];
    } finally {
        flock($lock, LOCK_UN); fclose($lock);
    }
}
