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
if ((int)($_POST['dauer'] ?? 0) < 4) respond(false, t('Bitte füllen Sie das Formular vollständig aus.', 'Please fill in the form completely.'), [], 422);

// Höchstens 8 Anfragen pro Stunde und IP (gezählt wird erst beim Versand, abgelehnte Eingaben zählen nicht)
$rl   = sys_get_temp_dir() . '/bambi_rl_' . md5($_SERVER['REMOTE_ADDR'] ?? '0');
$hits = array_filter(json_decode(@file_get_contents($rl) ?: '[]', true) ?: [], fn($ts) => $ts > time() - 3600);
if (count($hits) >= MAX_PRO_STUNDE) respond(false, t('Zu viele Anfragen. Bitte schreiben Sie uns direkt an ', 'Too many requests. Please email us directly at ') . MAIL_TO . '.', [], 429);

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
$linenBeds     = max(0, min(4, (int)($_POST['linen_beds'] ?? 0)));

// ── Validierung ───────────────────────────────────────────────
$email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);

if ($name === '')                                       respond(false, t('Bitte geben Sie Ihren Namen an.', 'Please enter your name.'), [], 422);
if ($persons < 1 || $persons > 4)                      respond(false, t('Das Chalet bietet Platz für 1 bis 4 Personen.', 'The chalet sleeps 1 to 4 guests.'), [], 422);
if (!$email)                                            respond(false, t('Bitte prüfen Sie die E-Mail-Adresse.', 'Please check your email address.'), [], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival))    respond(false, t('Ungültiges Anreisedatum.', 'Invalid arrival date.'), [], 422);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure))  respond(false, t('Ungültiges Abreisedatum.', 'Invalid departure date.'), [], 422);

$arrivalTs   = strtotime($arrival);
$departureTs = strtotime($departure);
$todayTs     = strtotime(date('Y-m-d'));
$nights      = (int)round(($departureTs - $arrivalTs) / 86400);

if ($arrivalTs < $todayTs)  respond(false, t('Das Anreisedatum liegt in der Vergangenheit.', 'The arrival date is in the past.'), [], 422);
// Keine Höchstdauer. Nur unsinnige Daten abfangen (Abreise mehr als 3 Jahre voraus), damit die Schleifen unten begrenzt bleiben
if ($departureTs > strtotime('+3 years', $todayTs)) respond(false, t('Ungültiges Abreisedatum.', 'Invalid departure date.'), [], 422);
// Mindestaufenthalt: 3 Nächte, 7 Nächte sobald eine Nacht zwischen 15.01. und 15.03. liegt
$minNights = 3;
for ($d = new DateTime($arrival); $d->format('Y-m-d') < $departure; $d->modify('+1 day')) {
    $md = $d->format('m-d');
    if ($md >= '01-15' && $md <= '03-15') { $minNights = 7; break; }
}
if ($nights < $minNights) respond(false, $minNights > 3
    ? t('Zwischen Mitte Januar und Mitte März beträgt der Mindestaufenthalt 7 Nächte.', 'Between mid-January and mid-March the minimum stay is 7 nights.')
    : t('Mindestaufenthalt: 3 Nächte.', 'Minimum stay: 3 nights.'), [], 422);

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
            respond(false, t('Der gewählte Zeitraum ist leider nicht mehr verfügbar. Bitte wählen Sie andere Daten.', 'Unfortunately the selected period is no longer available. Please choose other dates.'), [], 409);
        }
    }
    $cur = strtotime('+1 day', $cur);
}

// ── Preis serverseitig berechnen ──────────────────────────────
$priceCfg  = require __DIR__ . '/../config/pricing.php';
$periods   = $priceCfg['periods'];
$cleanPrice = (int)$priceCfg['cleaning'];
$linenPrice = (int)$priceCfg['linen_per_bed'];
$energyPrice = (float)$priceCfg['energy_per_night'];
$tax        = $priceCfg['tourist_tax'];
// Beträge mit Rappen nur wenn nötig: 98 bzw. 19.50
function chf($n): string { return floor($n) == $n ? (string)(int)$n : number_format($n, 2, '.', ''); }
$totalPrice = 0;
$breakdown  = [];

// Rabattcode mit festem Preis pro Nacht (z. B. STAUBRABATT): ersetzt die Tagessätze, alle Zusatzkosten fallen weg
$nightRate = null;
if ($discountCode !== '') {
    foreach ((require __DIR__ . '/../config/discounts.php') as $dc) {
        if (strtoupper($dc['code']) !== $discountCode || ($dc['type'] ?? '') !== 'night_rate') continue;
        $today = date('Y-m-d');
        if ((isset($dc['valid_from']) && $today < $dc['valid_from']) || (isset($dc['valid_to']) && $today > $dc['valid_to'])) break;
        $nightRate   = (int)$dc['value'];
        $cleaning    = 'no';
        $linen       = 'own';
        $linenBeds   = 0;
        $energyPrice = 0;
        break;
    }
}

$cur = $arrivalTs;
while ($cur < $departureTs) {
    $d          = date('Y-m-d', $cur);
    $nightPrice = $nightRate;
    if ($nightPrice === null) foreach ($periods as $p) {
        if ($d >= $p['from'] && $d <= $p['to']) {
            $nightPrice = $p['price'];
            break;
        }
    }
    if ($nightPrice === null) {
        respond(false, t('Für einen Teil des gewählten Zeitraums kann kein Preis berechnet werden. Bitte kontaktieren Sie uns direkt unter ', 'No price is available for part of the selected period. Please contact us directly at ') . MAIL_TO . '.', [], 422);
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
            if ($dc['type'] === 'night_rate') {
                $discountLabel = "CHF {$dc['value']} pro Nacht, ohne Zusatzkosten";
            } elseif ($dc['type'] === 'percent') {
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

$cleaningCost = ($cleaning === 'yes') ? $cleanPrice : 0;
$linenCost    = ($linen   === 'rent') ? ($linenBeds * $linenPrice) : 0;
$energyCost   = round($nights * $energyPrice, 2);
$finalPrice   = chf($totalPrice - $discountAmount + $cleaningCost + $linenCost + $energyCost);
$energyCost   = chf($energyCost);
$energyPriceTxt = chf($energyPrice);
$kurtaxe = t("Nicht im Preis enthalten: Kurtaxe CHF {$tax['adult']}.– pro Person ab {$tax['from_age']} Jahren und Nacht (Zwischensaison die Hälfte), jünger gratis. Bitte in den ersten Tagen beim Tourismus-Büro Sörenberg, Rothornstrasse 21, bezahlen.",
    "Not included: tourist tax CHF {$tax['adult']}.– per person aged {$tax['from_age']}+ per night (half in the off-season), younger guests free. Please pay within the first few days at the Sörenberg Tourist Office, Rothornstrasse 21.");
if ($nightRate !== null) {
    $kurtaxe = t('Mit diesem Code fallen alle Zusatzkosten weg. Bitte reinigen Sie das Chalet vor der Abreise selbst, bringen Sie Ihre Bettwäsche mit und nehmen Sie sie wieder mit. ',
                 'With this code there are no extra costs. Please clean the chalet yourself before leaving and bring your own bed linen and take it home again. ') . $kurtaxe;
}

// ── E-Mail-Text zusammenbauen ─────────────────────────────────
$dir    = dirname(__DIR__, 3) . '/bambi-data';   // ausserhalb des Webroots

// Fortlaufende Nummer ab 1000, Zähler in bambi-data (gesperrt, damit zwei gleichzeitige Anfragen nicht dieselbe bekommen).
// Vergeben erst hier, nach allen Prüfungen, damit abgelehnte Eingaben keine Lücken hinterlassen.
function naechsteNummer(string $dir): ?int {
    if (!(@is_dir($dir) || @mkdir($dir, 0700, true)) || !($fh = @fopen($dir . '/nummer.txt', 'c+'))) return null;
    flock($fh, LOCK_EX);
    $nr = max(1000, (int)stream_get_contents($fh) + 1);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, (string)$nr);
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($dir . '/nummer.txt', 0600);
    return $nr;
}
$ticket = 'BAM-' . (naechsteNummer($dir) ?? date('ymdHi'));
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
if ($energyCost > 0) $body .= "Energiekosten ({$nights} × CHF {$energyPriceTxt}): CHF {$energyCost}\n";
if ($nightRate !== null) $body .= "Rabattcode {$discountCode}: CHF {$nightRate} pro Nacht, ohne Zusatzkosten. Reinigung und Bettwäsche durch die Gäste.\n";
if ($cleaningCost > 0) $body .= "Endreinigung:  CHF {$cleaningCost}\n";
if ($linenCost    > 0) $body .= "Bettwäsche ({$linenBeds} Bett" . ($linenBeds > 1 ? 'en' : '') . "): CHF {$linenCost}\n";
if ($discountAmount > 0) {
    $body .= "Rabatt ({$discountLabel}, Code {$discountCode}): –CHF {$discountAmount}\n";
}
$body .= "TOTAL:     CHF {$finalPrice}\n";
$body .= "\nOPTIONEN\n";
$body .= "Endreinigung: " . ($cleaning === 'yes' ? "Ja (+CHF {$cleanPrice})" : 'Nein (selbst reinigen)') . "\n";
$body .= "Bettwäsche:   " . ($linen === 'rent' ? "Mieten – {$linenBeds} Bett" . ($linenBeds > 1 ? 'en' : '') . " (+CHF {$linenCost})" : 'Eigene') . "\n";
if ($message !== '') {
    $body .= "\nNACHRICHT\n{$message}\n";
}

$betreff = "Buchungsanfrage {$von} bis {$bis} – {$name} ({$ticket})";

// ── Interne Mail als HTML (gleicher Stil wie die Bestätigung an den Gast) ──
$x     = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$serif = "font-family:'Noto Serif',Georgia,'Times New Roman',serif;";
$sans  = "font-family:Inter,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;";
$wochentag = fn(int $ts) => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][(int)date('w', $ts)];

$iZeile = fn(string $k, string $vHtml) =>
    '<tr><td valign="top" style="padding:8px 12px 8px 0;border-bottom:1px solid #E2DCD0;color:#5A7263;font-size:14px;white-space:nowrap;">' . $x($k) . '</td>'
    . '<td align="right" valign="top" style="padding:8px 0;border-bottom:1px solid #E2DCD0;color:#2C2C2C;font-size:14px;">' . $vHtml . '</td></tr>';
$iBlock = fn(string $titel, string $rows) =>
    '<p style="margin:28px 0 8px;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">' . $x($titel) . '</p>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="' . $sans . '">' . $rows . '</table>';

$kontaktRows = $iZeile('Name', '<strong>' . $x($name) . '</strong>')
    . $iZeile('E-Mail', '<a href="mailto:' . $x($email) . '" style="color:#2D3E33;">' . $x($email) . '</a>')
    . $iZeile('Telefon', $phone !== '' ? '<a href="tel:' . $x(preg_replace('/[^\d+]/', '', $phone)) . '" style="color:#2D3E33;">' . $x($phone) . '</a>' : '–')
    . $iZeile('Sprache', $en ? 'Englisch (englische Seite)' : 'Deutsch');

$preisRows = '';
foreach ($breakdown as $b) {
    $n = $b['nights'];
    $preisRows .= $iZeile($n . ($n > 1 ? ' Nächte' : ' Nacht') . ' × CHF ' . $b['price'], 'CHF ' . $x($b['subtotal']));
}
if ($energyCost > 0) $preisRows .= $iZeile('Energiekosten', $x("{$nights} × CHF {$energyPriceTxt}") . " · CHF {$energyCost}");
if ($nightRate !== null) $preisRows .= $iZeile('Rabattcode', $x("{$discountCode} · CHF {$nightRate} pro Nacht, ohne Zusatzkosten"));
$preisRows .= $iZeile('Endreinigung', $cleaning === 'yes' ? "CHF {$cleanPrice}" : 'selbst reinigen');
$preisRows .= $iZeile('Bettwäsche', $linen === 'rent' ? $x("{$linenBeds} Bett" . ($linenBeds > 1 ? 'en' : '')) . " · CHF {$linenCost}" : 'eigene');
if ($discountAmount > 0) $preisRows .= $iZeile('Rabatt', $x("{$discountLabel} · Code {$discountCode}") . "<br>– CHF {$discountAmount}");
$preisRows .= '<tr><td style="padding:14px 0 0;color:#2D3E33;font-size:15px;font-weight:600;">Total (berechnet)</td>'
    . '<td align="right" style="padding:14px 0 0;' . $serif . 'color:#2D3E33;font-size:22px;">CHF ' . $x($finalPrice) . '</td></tr>';

$antwortLink = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode(($en ? 'Your booking enquiry ' : 'Ihre Buchungsanfrage ') . "{$von} – {$bis} ({$ticket})");
$knopf = fn(string $href, string $label, bool $hell = false) =>
    '<td style="padding:0 6px 0 0;"><a href="' . $x($href) . '" style="display:inline-block;padding:12px 20px;border-radius:6px;' . $sans . 'font-size:13px;font-weight:600;letter-spacing:.5px;text-decoration:none;'
    . ($hell ? 'background:#ffffff;color:#2D3E33;border:1px solid #BFB5A8;' : 'background:#2D3E33;color:#ffffff;border:1px solid #2D3E33;') . '">' . $label . '</a></td>';

$kachel = fn(string $label, string $wert) =>
    '<td width="33%" valign="top" style="padding:14px 10px;text-align:center;">'
    . '<div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8C7B6B;">' . $label . '</div>'
    . '<div style="' . $serif . 'font-size:20px;color:#2D3E33;margin-top:4px;">' . $wert . '</div></td>';

$internHtml = '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>Buchungsanfrage</title></head>'
    . '<body style="margin:0;padding:0;background:#F0EDE4;">'
    . '<div style="display:none;max-height:0;overflow:hidden;">' . $x("{$name}: {$von} – {$bis}, {$nights} Nächte, {$persons} Pers., CHF {$finalPrice}") . '</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F0EDE4;"><tr><td align="center" style="padding:32px 16px;">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FBF9F4;border-radius:14px;overflow:hidden;">'
    // Kopf
    . '<tr><td style="background:#2D3E33;padding:22px 32px;' . $sans . '">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
    . '<td style="' . $serif . 'font-size:22px;font-weight:300;color:#ffffff;">Chalet Bambi</td>'
    . '<td align="right" style="font-size:12px;letter-spacing:2px;color:#BFB5A8;">' . $x($ticket) . '</td>'
    . '</tr></table></td></tr>'
    // Inhalt
    . '<tr><td style="padding:30px 32px 8px;' . $sans . 'font-size:15px;line-height:1.55;color:#2C2C2C;">'
    . '<p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">Neue Buchungsanfrage</p>'
    . '<h1 style="margin:6px 0 4px;' . $serif . 'font-size:28px;font-weight:300;font-style:italic;line-height:1.2;color:#2D3E33;">' . $x($name) . '</h1>'
    . '<p style="margin:0 0 20px;color:#5A7263;">' . $x($wochentag($arrivalTs) . " {$von}") . ' &rarr; ' . $x($wochentag($departureTs) . " {$bis}") . '</p>'
    // Übersicht
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8DF;border-radius:10px;' . $sans . '"><tr>'
    . $kachel('Nächte', $x($nights)) . $kachel('Personen', $x($persons)) . $kachel('Total', 'CHF&nbsp;' . $x($finalPrice))
    . '</tr></table>'
    // Knöpfe
    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 0;"><tr>'
    . $knopf($antwortLink, 'Gast antworten')
    . ($phone !== '' ? $knopf('tel:' . preg_replace('/[^\d+]/', '', $phone), 'Anrufen', true) : '')
    . '</tr></table>'
    . $iBlock('Kontakt', $kontaktRows)
    . $iBlock('Preis', $preisRows)
    . ($message !== ''
        ? '<p style="margin:28px 0 8px;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">Nachricht des Gastes</p>'
          . '<div style="background:#ffffff;border-left:3px solid #A05C3B;border-radius:4px;padding:14px 18px;font-size:15px;line-height:1.6;color:#2C2C2C;">' . nl2br($x($message)) . '</div>'
        : '')
    . '<p style="margin:26px 0 28px;font-size:13px;color:#8C7B6B;">Der Preis ist automatisch berechnet. Die Anfrage ist noch nicht bestätigt; der Gast hat eine Eingangsbestätigung erhalten.</p>'
    . '</td></tr>'
    // Fuss
    . '<tr><td align="center" style="background:#2D3E33;padding:16px 24px;' . $sans . 'font-size:12px;color:#BFB5A8;">'
    . 'Eingegangen am ' . date('d.m.Y') . ' um ' . date('H:i') . ' Uhr über bambi-staub.ch' . ($en ? '/en' : '')
    . '</td></tr>'
    . '</table></td></tr></table></body></html>';

$iAlt    = 'i_' . bin2hex(random_bytes(12));
$headers = 'From: ' . enc('Website ' . NAME) . ' <' . MAIL_FROM . ">\r\n" .
    'Reply-To: ' . enc($name) . ' <' . $email . ">\r\n" .
    "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$iAlt\"\r\n";
$msg = "--$iAlt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body)) .
    "--$iAlt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($internHtml)) .
    "--$iAlt--\r\n";

$bestaetigung = $en
    ? "Hello {$name}\n\nThank you for your booking enquiry. We have received it and will get back to you by email within 24 hours.\n\n" .
      "Reference: {$ticket}\nStay: {$von} to {$bis} ({$nights} nights, {$persons} guests)\nEstimated total: CHF {$finalPrice}\n{$kurtaxe}\n\n" .
      "This is an enquiry, not yet a confirmed booking.\n\nKind regards\nFamilie Staub\n" . NAME . "\nRothornstrasse 40, 6174 Sörenberg\n" . MAIL_TO . "\n"
    : "Guten Tag {$name}\n\nVielen Dank für Ihre Buchungsanfrage. Wir haben sie erhalten und melden uns innerhalb von 24 Stunden per E-Mail bei Ihnen.\n\n" .
      "Nummer: {$ticket}\nAufenthalt: {$von} bis {$bis} ({$nights} Nächte, {$persons} Personen)\nVoraussichtlicher Preis: CHF {$finalPrice}\n{$kurtaxe}\n\n" .
      "Das ist eine Anfrage, noch keine bestätigte Buchung.\n\nHerzliche Grüsse\nFamilie Staub\n" . NAME . "\nRothornstrasse 40, 6174 Sörenberg\n" . MAIL_TO . "\n";

// ── Bestätigung als HTML ──────────────────────────────────────
// Tabellen und Inline-Stile, damit Outlook und Gmail sie gleich zeigen; Chalet-Foto als eingebettetes Bild.
// Farben und Schriften wie auf der Website (forest, linen, cream, terracotta; Noto Serif bzw. Georgia).
$zeilen = [
    [t('Nummer', 'Reference'), $ticket],
    [t('Anreise', 'Arrival'), $von],
    [t('Abreise', 'Departure'), $bis],
    [t('Aufenthalt', 'Stay'), $nights . t(' Nächte · ', ' nights · ') . $persons . t(' Personen', ' guests')],
    [t('Endreinigung', 'Final cleaning'), $cleaning === 'yes' ? t('Ja', 'Yes') . " (CHF {$cleanPrice})" : t('Selbst reinigen', 'Self-cleaning')],
    [t('Bettwäsche', 'Bed linen'), $linen === 'rent' ? $linenBeds . t(' Bett', ' bed') . ($linenBeds > 1 ? t('en', 's') : '') . " · CHF {$linenCost}" : t('Eigene', 'Own')],
];
if ($discountAmount > 0) $zeilen[] = [t('Rabatt', 'Discount'), "– CHF {$discountAmount}"];
if ($energyCost > 0) array_splice($zeilen, 4, 0, [[t('Energiekosten', 'Energy costs'), "CHF {$energyCost}"]]);
if ($nightRate !== null) {
    $zeilen[] = [t('Rabattcode', 'Discount code'), $discountCode . t(" · CHF {$nightRate} pro Nacht", " · CHF {$nightRate} per night")];
}
$tabelle = '';
foreach ($zeilen as [$k, $v]) {
    // Geschützte Leerzeichen: «CHF 40» oder «2 Personen» nie auseinanderreissen, Umbruch nur nach «·»
    $v = str_replace("\u{00A0}·\u{00A0}", "\u{00A0}· ", str_replace(' ', "\u{00A0}", $v));
    $tabelle .= '<tr><td style="padding:7px 12px 7px 0;border-bottom:1px solid #E2DCD0;color:#5A7263;font-size:14px;white-space:nowrap;">' . $x($k) . '</td>'
        . '<td align="right" style="padding:7px 0;border-bottom:1px solid #E2DCD0;color:#2C2C2C;font-size:14px;">' . $x($v) . '</td></tr>';
}
$tabelle .= '<tr><td style="padding:14px 0 0;color:#2D3E33;font-size:15px;font-weight:600;">' . t('Voraussichtlicher Preis', 'Estimated total') . '</td>'
    . '<td align="right" style="padding:14px 0 0;' . $serif . 'color:#2D3E33;font-size:22px;">CHF ' . $x($finalPrice) . '</td></tr>'
    . '<tr><td colspan="2" style="padding:12px 0 0;color:#5A7263;font-size:13px;line-height:1.5;">' . $x($kurtaxe) . '</td></tr>';

$bestaetigungHtml = '<!doctype html><html lang="' . t('de', 'en') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
    . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>Chalet Bambi</title></head>'
    . '<body style="margin:0;padding:0;background:#F0EDE4;">'
    . '<div style="display:none;max-height:0;overflow:hidden;">' . t('Wir haben Ihre Anfrage erhalten und melden uns innerhalb von 24 Stunden.', 'We have received your enquiry and will get back to you within 24 hours.') . '</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F0EDE4;"><tr><td align="center" style="padding:32px 16px;">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FBF9F4;border-radius:14px;overflow:hidden;">'
    // Kopf mit Schriftzug wie auf der Website
    . '<tr><td align="center" style="background:#2D3E33;padding:26px 24px 22px;">'
    . '<div style="' . $serif . 'font-size:28px;font-weight:300;letter-spacing:0.5px;color:#ffffff;">Chalet Bambi</div>'
    . '<div style="' . $sans . 'font-size:11px;letter-spacing:4px;text-transform:uppercase;color:#BFB5A8;margin-top:4px;">Sörenberg</div>'
    . '</td></tr>'
    . '<tr><td><img src="cid:chalet@bambi-staub.ch" width="600" alt="Chalet Bambi" style="display:block;width:100%;max-width:600px;height:auto;border:0;"></td></tr>'
    // Inhalt
    . '<tr><td style="padding:34px 28px 8px;' . $sans . 'font-size:16px;line-height:1.6;color:#2C2C2C;">'
    . '<p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">' . t('Anfrage erhalten', 'Enquiry received') . '</p>'
    . '<h1 style="margin:8px 0 22px;' . $serif . 'font-size:30px;font-weight:300;font-style:italic;line-height:1.2;color:#2D3E33;">' . t('Vielen Dank für Ihre Anfrage.', 'Thank you for your enquiry.') . '</h1>'
    . '<p style="margin:0 0 14px;">' . t('Guten Tag ', 'Hello ') . $x($name) . '</p>'
    . '<p style="margin:0 0 26px;">' . t('Wir haben Ihre Buchungsanfrage erhalten und melden uns innerhalb von 24&nbsp;Stunden per E-Mail bei Ihnen.', 'We have received your booking enquiry and will get back to you by email within 24&nbsp;hours.') . '</p>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE8DF;border-radius:10px;"><tr><td style="padding:16px 22px 20px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="' . $sans . '">' . $tabelle . '</table>'
    . '</td></tr></table>'
    . '<p style="margin:18px 0 0;font-size:13px;color:#8C7B6B;">' . t('Das ist eine Anfrage, noch keine bestätigte Buchung. Die Bestätigung erhalten Sie von uns persönlich.', 'This is an enquiry, not yet a confirmed booking. We will confirm it with you personally.') . '</p>'
    . '<p style="margin:28px 0 30px;">' . t('Herzliche Grüsse', 'Kind regards') . '<br><span style="' . $serif . 'font-style:italic;font-size:19px;color:#2D3E33;">Familie Staub</span></p>'
    . '</td></tr>'
    // Fuss
    . '<tr><td align="center" style="background:#2D3E33;padding:22px 24px;' . $sans . 'font-size:12px;line-height:1.7;color:#BFB5A8;">'
    . 'Chalet Bambi · Rothornstrasse 40 · 6174 Sörenberg<br>'
    . '<a href="mailto:' . MAIL_TO . '" style="color:#ffffff;text-decoration:none;">' . MAIL_TO . '</a> · <a href="https://bambi-staub.ch' . t('', '/en/') . '" style="color:#ffffff;text-decoration:none;">bambi-staub.ch</a>'
    . '</td></tr>'
    . '</table></td></tr></table></body></html>';

// multipart/alternative: Text für einfache Programme, HTML mit eingebettetem Foto (multipart/related) für alle anderen
$alt  = 'a_' . bin2hex(random_bytes(12));
$rel  = 'r_' . bin2hex(random_bytes(12));
$foto = @file_get_contents(__DIR__ . '/../assets/mail-chalet.jpg');
$bHeaders = 'From: ' . enc(NAME) . ' <' . MAIL_FROM . ">\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n";
$bMsg = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bestaetigung)) .
    "--$alt\r\nContent-Type: multipart/related; boundary=\"$rel\"; type=\"text/html\"\r\n\r\n" .
    "--$rel\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($bestaetigungHtml)) .
    ($foto ? "--$rel\r\nContent-Type: image/jpeg; name=\"chalet-bambi.jpg\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <chalet@bambi-staub.ch>\r\n" .
        "Content-Disposition: inline; filename=\"chalet-bambi.jpg\"\r\n\r\n" . chunk_split(base64_encode($foto)) : '') .
    "--$rel--\r\n--$alt--\r\n";
$bBetreff = enc(t('Ihre Buchungsanfrage für Chalet Bambi', 'Your booking enquiry for Chalet Bambi') . " ($ticket)");

$hits[] = time();
@file_put_contents($rl, json_encode(array_values($hits)));

// ── Kopie ausserhalb des Webroots ─────────────────────────────
// Eine Datei pro Monat, nur für den Betreiber lesbar; Dateien ohne Eintrag seit 12 Monaten werden gelöscht.
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
    file_put_contents("$dev/$ticket-bestaetigung.eml", "To: $email\r\nSubject: $bBetreff\r\n$bHeaders\r\n$bMsg");
    respond(true, '', ['nights' => $nights, 'price' => $finalPrice]);
}

if (!mail(MAIL_TO, enc($betreff), $msg, $headers, '-f' . MAIL_FROM)) {
    respond(false, t('Die Anfrage konnte nicht gesendet werden. Bitte schreiben Sie uns direkt an ', 'Your enquiry could not be sent. Please email us directly at ') . MAIL_TO . '.', [], 500);
}

// Bestätigung an den Gast (Fehler hier blockieren nichts)
@mail($email, $bBetreff, $bMsg, $bHeaders, '-f' . MAIL_FROM);

respond(true, '', ['nights' => $nights, 'price' => $finalPrice]);
