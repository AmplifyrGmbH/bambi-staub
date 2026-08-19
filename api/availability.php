<?php
header('Content-Type: application/json; charset=utf-8');

$configFile = __DIR__ . '/../config/interhome.php';
$cacheFile  = __DIR__ . '/../cache/availability.json';
$cacheTTL   = 300; // 5 Minuten

// Cache frisch genug? Direkt zurückgeben.
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    readfile($cacheFile);
    exit;
}

// Config laden
if (!file_exists($configFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'Config fehlt. config/interhome.php anlegen.']);
    exit;
}
$config  = require $configFile;
$icalUrl = $config['ical_url'] ?? '';

if (empty($icalUrl)) {
    http_response_code(503);
    echo json_encode(['error' => 'ical_url nicht konfiguriert.']);
    exit;
}

// iCal abrufen
$icalData = false;

// Zuerst cURL (zuverlässiger bei Hostpoint)
if (function_exists('curl_init')) {
    $ch = curl_init($icalUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'ChaletBambi/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $icalData = curl_exec($ch);
    if (curl_errno($ch)) {
        $icalData = false;
    }
    curl_close($ch);
}

// Fallback: file_get_contents
if ($icalData === false) {
    $ctx      = stream_context_create(['http' => ['timeout' => 10]]);
    $icalData = @file_get_contents($icalUrl, false, $ctx);
}

// Bei Fehler: abgelaufenen Cache zurückgeben, falls vorhanden
if ($icalData === false || empty($icalData)) {
    if (file_exists($cacheFile)) {
        readfile($cacheFile);
    } else {
        http_response_code(503);
        echo json_encode(['error' => 'Verfügbarkeit konnte nicht abgerufen werden.']);
    }
    exit;
}

// iCal parsen – VEVENT-Blöcke extrahieren
$bookings = [];
preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $icalData, $matches);

foreach ($matches[0] as $event) {
    $start = null;
    $end   = null;

    // DTSTART;VALUE=DATE:20261210  oder  DTSTART:20261210T000000Z
    if (preg_match('/DTSTART(?:;[^:\r\n]*)?:(\d{8})/', $event, $m)) {
        $raw   = $m[1];
        $start = substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
    }
    if (preg_match('/DTEND(?:;[^:\r\n]*)?:(\d{8})/', $event, $m)) {
        $raw = $m[1];
        $end = substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
    }

    if ($start && $end && $start < $end) {
        $bookings[] = ['start' => $start, 'end' => $end];
    }
}

// Manuelle Sperrtage dazumergen
$manualFile = __DIR__ . '/../config/manual-blocks.php';
if (file_exists($manualFile)) {
    $manual = require $manualFile;
    foreach ($manual as $block) {
        if (!empty($block['start']) && !empty($block['end'])) {
            $bookings[] = ['start' => $block['start'], 'end' => $block['end']];
        }
    }
}

$json = json_encode([
    'bookings'  => $bookings,
    'cached_at' => date('c'),
], JSON_UNESCAPED_UNICODE);

// Cache schreiben
$cacheDir = dirname($cacheFile);
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}
@file_put_contents($cacheFile, $json);

echo $json;
