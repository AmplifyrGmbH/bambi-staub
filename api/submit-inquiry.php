<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Honeypot ──────────────────────────────────────────────────
if (!empty($_POST['website'])) {
    // Bot erkannt – still erfolgreich antworten
    echo json_encode(['ok' => true]);
    exit;
}

// ── Hilfsfunktionen ───────────────────────────────────────────
function esc(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function respond(bool $ok, string $error = '', array $extra = []): never {
    $payload = array_merge(['ok' => $ok], $ok ? $extra : ['error' => $error]);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Eingaben einlesen ─────────────────────────────────────────
$name          = esc($_POST['name']          ?? '');
$rawEmail      = trim($_POST['email']        ?? '');
$phone         = esc($_POST['phone']         ?? '');
$persons       = (int)($_POST['persons']     ?? 0);
$arrival       = trim($_POST['arrival']      ?? '');
$departure     = trim($_POST['departure']    ?? '');
$message       = esc($_POST['message']       ?? '');
$discountCode  = strtoupper(trim($_POST['discount_code'] ?? ''));

// ── Validierung ───────────────────────────────────────────────
$email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);

if (empty($name))                                       respond(false, 'Name fehlt.');
if (!$email)                                            respond(false, 'Ungültige E-Mail-Adresse.');
if ($persons < 1 || $persons > 10)                     respond(false, 'Ungültige Personenzahl (1–10).');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrival))    respond(false, 'Ungültiges Anreisedatum.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $departure))  respond(false, 'Ungültiges Abreisedatum.');

$arrivalTs   = strtotime($arrival);
$departureTs = strtotime($departure);
$todayTs     = strtotime(date('Y-m-d'));
$nights      = (int)(($departureTs - $arrivalTs) / 86400);

if ($arrivalTs < $todayTs)  respond(false, 'Anreisedatum liegt in der Vergangenheit.');
if ($nights < 3)            respond(false, 'Mindestaufenthalt: 3 Nächte.');
if ($nights > 90)           respond(false, 'Aufenthalt zu lang (max. 90 Nächte).');

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
        curl_close($ch);
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
            respond(false, 'Der gewählte Zeitraum ist leider nicht mehr verfügbar. Bitte wähle andere Daten.');
        }
    }
    $cur += 86400;
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
        respond(false, 'Für einen Teil des gewählten Zeitraums kann kein Preis berechnet werden. Bitte kontaktiere uns direkt unter info@bambi-staub.ch.');
    }
    $totalPrice += $nightPrice;

    $last = count($breakdown) - 1;
    if ($last >= 0 && $breakdown[$last]['price'] === $nightPrice) {
        $breakdown[$last]['nights']++;
        $breakdown[$last]['subtotal'] += $nightPrice;
    } else {
        $breakdown[] = ['price' => $nightPrice, 'nights' => 1, 'subtotal' => $nightPrice];
    }
    $cur += 86400;
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

$finalPrice = $totalPrice - $discountAmount;

// ── E-Mail-Text zusammenbauen ─────────────────────────────────
$breakdownLines = '';
foreach ($breakdown as $b) {
    $n = $b['nights'];
    $breakdownLines .= $n . ' Nacht' . ($n > 1 ? 'e' : '') . ' × CHF ' . $b['price'] . ' = CHF ' . $b['subtotal'] . "\n";
}

$body  = "Buchungsanfrage Chalet Bambi – Sörenberg\n";
$body .= str_repeat('─', 48) . "\n\n";
$body .= "KONTAKT\n";
$body .= "Name:      {$name}\n";
$body .= "E-Mail:    {$email}\n";
$body .= "Telefon:   " . ($phone ?: '–') . "\n";
$body .= "Personen:  {$persons}\n\n";
$body .= "AUFENTHALT\n";
$body .= "Anreise:   " . date('d.m.Y', $arrivalTs) . "\n";
$body .= "Abreise:   " . date('d.m.Y', $departureTs) . "\n";
$body .= "Nächte:    {$nights}\n\n";
$body .= "PREIS (berechnet)\n";
$body .= $breakdownLines;
if ($discountAmount > 0) {
    $body .= "Rabatt ({$discountLabel}): –CHF {$discountAmount}\n";
}
$body .= "TOTAL:     CHF {$finalPrice}\n";
if (!empty($message)) {
    $body .= "\nNACHRICHT\n{$message}\n";
}

// ── Via web3forms senden ──────────────────────────────────────
$postData = http_build_query([
    'access_key' => '41f2c8e9-2b02-48b7-a80f-655b95eeb0c1',
    'subject'    => 'Buchungsanfrage Chalet Bambi – ' . date('d.m.Y', $arrivalTs) . ' bis ' . date('d.m.Y', $departureTs),
    'from_name'  => $name,
    'email'      => $email,
    'message'    => $body,
    'botcheck'   => '',
]);

$ch = curl_init('https://api.web3forms.com/submit');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 200 && ($result['success'] ?? false)) {
    respond(true, '', ['nights' => $nights, 'price' => $finalPrice]);
} else {
    respond(false, 'Die Nachricht konnte nicht gesendet werden. Bitte schreib uns direkt an info@bambi-staub.ch.');
}
