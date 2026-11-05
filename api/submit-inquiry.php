<?php
/* Buchungsanfrage (/buchen und /en/buchen) -> E-Mail an Familie Staub, per mail() über Hostpoint.
   Prüft Verfügbarkeit und rechnet den Preis serverseitig. Antwort immer JSON: {ok, nights, price} oder {ok:false, message}. */
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

const MAIL_TO   = 'info@bambi-staub.ch';
const MAIL_FROM = 'info@bambi-staub.ch';
const NAME      = 'Chalet Bambi Sörenberg';
const MAX_PRO_STUNDE = 8;               // Einsendungen pro IP

date_default_timezone_set('Europe/Zurich');

// ── Hilfsfunktionen ───────────────────────────────────────────
$en = ($_POST['lang'] ?? '') === 'en';

function t(string $de, string $en): string { return $GLOBALS['en'] ? $en : $de; }

function respond(bool $ok, string $message = '', array $extra = [], int $code = 200): never {
    http_response_code($ok ? 200 : $code);
    echo json_encode(array_merge(['ok' => $ok], $ok ? $extra : ['message' => $message]), JSON_UNESCAPED_UNICODE);
    exit;
}

// Einzeiler ohne Zeilenumbrüche (Schutz gegen Header-Injection), Längen begrenzt
function clean($v, int $max = 200): string {
    return mb_substr(trim(str_replace(["\r", "\n", "\t"], ' ', (string)$v)), 0, $max);
}
function text($v, int $max = 3000): string { return mb_substr(trim(str_replace("\r", '', (string)$v)), 0, $max); }
function enc(string $s): string { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Method not allowed', [], 405);

// ── Spamschutz ────────────────────────────────────────────────
if (!empty($_POST['website'])) respond(true, '', ['nights' => 0, 'price' => 0]);   // Honeypot: still erfolgreich antworten
if ((int)($_POST['dauer'] ?? 0) < 4) respond(false, t('Bitte fülle das Formular vollständig aus.', 'Please fill in the form completely.'), [], 422);

// Höchstens 8 Anfragen pro Stunde und IP (gezählt wird erst beim Versand, abgelehnte Eingaben zählen nicht)
$rl   = sys_get_temp_dir() . '/bambi_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
$hits = array_filter(json_decode(@file_get_contents($rl) ?: '[]', true) ?: [], fn($ts) => $ts > time() - 3600);
if (count($hits) >= MAX_PRO_STUNDE) respond(false, t('Zu viele Anfragen. Bitte schreib uns direkt an ', 'Too many requests. Please email us directly at ') . MAIL_TO . '.', [], 429);

// ── Eingaben einlesen ─────────────────────────────────────────
$name          = clean($_POST['name']        ?? '', 120);
$rawEmail      = clean($_POST['email']       ?? '', 120);
$phone         = clean($_POST['phone']       ?? '', 40);
$persons       = (int)($_POST['persons']     ?? 0);
$arrival       = trim($_POST['arrival']      ?? '');
$departure     = trim($_POST['departure']    ?? '');
$message       = text($_POST['message']      ?? '');
$discountCode  = strtoupper(clean($_POST['discount_code'] ?? '', 40));
$cleaning      = ($_POST['cleaning'] ?? 'yes') === 'no' ? 'no' : 'yes';
$linen         = ($_POST['linen']    ?? 'own') === 'rent' ? 'rent' : 'own';
$linenBeds     = max(0, min(10, (int)($_POST['linen_beds'] ?? 0)));

// ── Validierung ───────────────────────────────────────────────
$email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);

if ($name === '')                                       respond(false, t('Bitte gib deinen Namen an.', 'Please enter your name.'), [], 422);
if ($persons < 1 || $persons > 10)                     respond(false, t('Ungültige Personenzahl (1–10).', 'Invalid number of guests (1–10).'), [], 422);
if (!$email)                                            respond(false, t('Bitte prüfe die E-Mail-Adresse.', 'Please check your email address.'), [], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival))    respond(false, t('Ungültiges Anreisedatum.', 'Invalid arrival date.'), [], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure))  respond(false, t('Ungültiges Abreisedatum.', 'Invalid departure date.'), [], 422);

$arrivalTs   = strtotime($arrival);
$departureTs = strtotime($departure);
$todayTs     = strtotime(date('Y-m-d'));
$nights      = (int)round(($departureTs - $arrivalTs) / 86400);

if ($arrivalTs < $todayTs)  respond(false, t('Das Anreisedatum liegt in der Vergangenheit.', 'The arrival date is in the past.'), [], 422);
if ($nights < 3)            respond(false, t('Mindestaufenthalt: 3 Nächte.', 'Minimum stay: 3 nights.'), [], 422);
if ($nights > 90)           respond(false, t('Aufenthalt zu lang (max. 90 Nächte).', 'Stay too long (max. 90 nights).'), [], 422);

// ── Verfügbarkeit serverseitig prüfen ─────────────────────────
$cacheFile = __DIR__ . '/../cache/availability.json';
$bookings  = [];

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
    $cached   = json_decode(file_get_contents($cacheFile), true);
    $bookings = $cached['bookings'] ?? [];
} else {
    $config  = require __DIR__ . '/../config/interhome.php';
    $icalUrl = $config['ical_url'] ?? '';
    if ($icalUrl) {
        $ch = curl_init($icalUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $icalData = curl_exec($ch);
        if ($icalData) {
            preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $icalData, $matches);
            foreach ($matches[0] as $event) {
                $s = $e = null;
                if (preg_match('/DTSTART(?:;[^:\r\n]*)?:(\d{8})/', $event, $m)) {
                    $s = substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
                }
                if (preg_match('/DTEND(?:;[^:\r\n]*)?:(\d{8})/', $event, $m)) {
                    $e = substr($m[1],0,4).'-'.substr($m[1],4,2).'-'.substr($m[1],6,2);
                }
                if ($s && $e && $s < $e) $bookings[] = ['start' => $s, 'end' => $e];
            }
        }
    }
}

// Manuelle Sperrtage einbeziehen
$manualFile = __DIR__ . '/../config/manual-blocks.php';
if (file_exists($manualFile)) {
    foreach (require $manualFile as $block) {
        if (!empty($block['start']) && !empty($block['end'])) {
            $bookings[] = ['start' => $block['start'], 'end' => $block['end']];
        }
    }
}

$cur = $arrivalTs;
while ($cur < $departureTs) {
    $d = date('Y-m-d', $cur);
    foreach ($bookings as $b) {
        if ($d >= $b['start'] && $d < $b['end']) {
            respond(false, t('Der gewählte Zeitraum ist leider nicht mehr verfügbar. Bitte wähle andere Daten.', 'Unfortunately the selected period is no longer available. Please choose other dates.'), [], 409);
        }
    }
    $cur = strtotime('+1 day', $cur);
}

// ── Preis serverseitig berechnen ──────────────────────────────
$periods   = require __DIR__ . '/../config/pricing.php';
$totalPrice = 0;
$breakdown  = [];

$cur = $arrivalTs;
while ($cur < $departureTs) {
    $d          = date('Y-m-d', $cur);
    $nightPrice = null;
    foreach ($periods as $p) {
        if ($d >= $p['from'] && $d <= $p['to']) {
            $nightPrice = $p['price'];
            break;
        }
    }
    if ($nightPrice === null) {
        respond(false, t('Für einen Teil des gewählten Zeitraums kann kein Preis berechnet werden. Bitte kontaktiere uns direkt unter ', 'No price is available for part of the selected period. Please contact us directly at ') . MAIL_TO . '.', [], 422);
    }
    $totalPrice += $nightPrice;

    $last = count($breakdown) - 1;
    if ($last >= 0 && $breakdown[$last]['price'] === $nightPrice) {
        $breakdown[$last]['nights']++;
        $breakdown[$last]['subtotal'] += $nightPrice;
    } else {
        $breakdown[] = ['price' => $nightPrice, 'nights' => 1, 'subtotal' => $nightPrice];
    }
    $cur = strtotime('+1 day', $cur);
}

// ── Rabattcode prüfen ─────────────────────────────────────────
$discountAmount = 0;
$discountLabel  = '';

if (!empty($discountCode)) {
    $discounts = require __DIR__ . '/../config/discounts.php';
    $today     = date('Y-m-d');
    foreach ($discounts as $dc) {
        if (strtoupper($dc['code']) !== $discountCode) continue;
        $valid = true;
        if (isset($dc['valid_from']) && $today < $dc['valid_from']) $valid = false;
        if (isset($dc['valid_to'])   && $today > $dc['valid_to'])   $valid = false;
        if ($valid) {
            if ($dc['type'] === 'percent') {
                $discountAmount = (int)round($totalPrice * $dc['value'] / 100);
                $discountLabel  = $dc['value'] . '% Rabatt';
            } else {
                $discountAmount = min((int)$dc['value'], $totalPrice);
                $discountLabel  = 'CHF ' . $dc['value'] . ' Rabatt';
            }
        }
        break;
    }
}

$cleaningCost = ($cleaning === 'yes') ? 80 : 0;
$linenCost    = ($linen   === 'rent') ? ($linenBeds * 20) : 0;
$finalPrice   = $totalPrice - $discountAmount + $cleaningCost + $linenCost;

// ── E-Mail-Text zusammenbauen ─────────────────────────────────
$ticket = 'BAM-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
$von    = date('d.m.Y', $arrivalTs);
$bis    = date('d.m.Y', $departureTs);

$breakdownLines = '';
foreach ($breakdown as $b) {
    $n = $b['nights'];
    $breakdownLines .= $n . ($n > 1 ? ' Nächte' : ' Nacht') . ' × CHF ' . $b['price'] . ' = CHF ' . $b['subtotal'] . "\n";
}

$body  = "Buchungsanfrage Chalet Bambi – Sörenberg ($ticket)\n";
$body .= date('d.m.Y H:i') . ($en ? ' · über die englische Seite' : '') . "\n";
$body .= str_repeat('─', 48) . "\n\n";
$body .= "KONTAKT\n";
$body .= "Name:      {$name}\n";
$body .= "E-Mail:    {$email}\n";
$body .= "Telefon:   " . ($phone ?: '–') . "\n";
$body .= "Personen:  {$persons}\n\n";
$body .= "AUFENTHALT\n";
$body .= "Anreise:   {$von}\n";
$body .= "Abreise:   {$bis}\n";
$body .= "Nächte:    {$nights}\n\n";
$body .= "PREIS (berechnet)\n";
$body .= $breakdownLines;
if ($cleaningCost > 0) $body .= "Endreinigung:  CHF {$cleaningCost}\n";
if ($linenCost    > 0) $body .= "Bettwäsche ({$linenBeds} Bett" . ($linenBeds > 1 ? 'en' : '') . "): CHF {$linenCost}\n";
if ($discountAmount > 0) {
    $body .= "Rabatt ({$discountLabel}, Code {$discountCode}): –CHF {$discountAmount}\n";
}
$body .= "TOTAL:     CHF {$finalPrice}\n";
$body .= "\nOPTIONEN\n";
$body .= "Endreinigung: " . ($cleaning === 'yes' ? 'Ja (+CHF 80)' : 'Nein (selbst reinigen)') . "\n";
$body .= "Bettwäsche:   " . ($linen === 'rent' ? "Mieten – {$linenBeds} Bett" . ($linenBeds > 1 ? 'en' : '') . " (+CHF {$linenCost})" : 'Eigene') . "\n";
if ($message !== '') {
    $body .= "\nNACHRICHT\n{$message}\n";
}

$betreff = "Buchungsanfrage {$von} bis {$bis} – {$name} ({$ticket})";
$headers = 'From: ' . enc('Website ' . NAME) . ' <' . MAIL_FROM . ">\r\n" .
    'Reply-To: ' . enc($name) . ' <' . $email . ">\r\n" .
    "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
$msg = chunk_split(base64_encode($body));

$bestaetigung = $en
    ? "Hello {$name}\n\nThank you for your booking enquiry. We have received it and will get back to you by email within 24 hours.\n\n" .
      "Reference: {$ticket}\nStay: {$von} to {$bis} ({$nights} nights, {$persons} guests)\nEstimated total: CHF {$finalPrice}\n\n" .
      "This is an enquiry, not yet a confirmed booking.\n\nKind regards\nFamilie Staub\n" . NAME . "\nRothornstrasse 40, 6174 Sörenberg\n" . MAIL_TO . "\n"
    : "Hallo {$name}\n\nDanke für deine Buchungsanfrage. Wir haben sie erhalten und melden uns innerhalb von 24 Stunden per E-Mail bei dir.\n\n" .
      "Nummer: {$ticket}\nAufenthalt: {$von} bis {$bis} ({$nights} Nächte, {$persons} Personen)\nVoraussichtlicher Preis: CHF {$finalPrice}\n\n" .
      "Das ist eine Anfrage, noch keine bestätigte Buchung.\n\nHerzliche Grüsse\nFamilie Staub\n" . NAME . "\nRothornstrasse 40, 6174 Sörenberg\n" . MAIL_TO . "\n";

$hits[] = time();
@file_put_contents($rl, json_encode(array_values($hits)));

// ── Kopie ausserhalb des Webroots ─────────────────────────────
// Eine Datei pro Monat, nur für den Betreiber lesbar; Dateien ohne Eintrag seit 12 Monaten werden gelöscht.
$dir = dirname(__DIR__, 3) . '/bambi-data';
if (@is_dir($dir) || @mkdir($dir, 0700, true)) {
    $kopie = $dir . '/anfragen-' . date('Y-m') . '.jsonl';
    $log = ['ticket' => $ticket, 'zeit' => date('c'), 'sprache' => $en ? 'en' : 'de', 'name' => $name, 'email' => $email, 'telefon' => $phone,
            'personen' => $persons, 'anreise' => $arrival, 'abreise' => $departure, 'naechte' => $nights, 'preis' => $finalPrice,
            'reinigung' => $cleaning, 'bettwaesche' => $linen === 'rent' ? $linenBeds : 0, 'rabattcode' => $discountCode, 'nachricht' => $message];
    if (@file_put_contents($kopie, json_encode($log, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX)) @chmod($kopie, 0600);
    foreach (glob($dir . '/anfragen*.jsonl') ?: [] as $alt) {
        if (filemtime($alt) < strtotime('-12 months')) @unlink($alt); else @chmod($alt, 0600);
    }
}

// Lokaler Test (php -S mit BAMBI_DEV=Ordner): Mail als .eml ablegen statt versenden
if ($dev = getenv('BAMBI_DEV')) {
    file_put_contents("$dev/$ticket.eml", "To: " . MAIL_TO . "\r\nSubject: " . enc($betreff) . "\r\n$headers\r\n$msg");
    file_put_contents("$dev/$ticket-bestaetigung.txt", "To: $email\n\n$bestaetigung");
    respond(true, '', ['nights' => $nights, 'price' => $finalPrice]);
}

if (!mail(MAIL_TO, enc($betreff), $msg, $headers, '-f' . MAIL_FROM)) {
    respond(false, t('Die Anfrage konnte nicht gesendet werden. Bitte schreib uns direkt an ', 'Your enquiry could not be sent. Please email us directly at ') . MAIL_TO . '.', [], 500);
}

// Bestätigung an den Gast (Fehler hier blockieren nichts)
$h = 'From: ' . enc(NAME) . ' <' . MAIL_FROM . ">\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
@mail($email, enc(t('Deine Buchungsanfrage für Chalet Bambi', 'Your booking enquiry for Chalet Bambi') . " ($ticket)"), chunk_split(base64_encode($bestaetigung)), $h, '-f' . MAIL_FROM);

respond(true, '', ['nights' => $nights, 'price' => $finalPrice]);
