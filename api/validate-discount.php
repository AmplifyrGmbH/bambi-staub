<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$code = strtoupper(trim($_POST['code'] ?? ''));
if (empty($code)) {
    echo json_encode(['valid' => false]);
    exit;
}

$discounts = require __DIR__ . '/../config/discounts.php';
$today     = date('Y-m-d');

foreach ($discounts as $d) {
    if (strtoupper($d['code']) !== $code) continue;

    if (isset($d['valid_from']) && $today < $d['valid_from']) {
        echo json_encode(['valid' => false, 'message' => 'Code noch nicht gültig.']);
        exit;
    }
    if (isset($d['valid_to']) && $today > $d['valid_to']) {
        echo json_encode(['valid' => false, 'message' => 'Code abgelaufen.']);
        exit;
    }

    echo json_encode([
        'valid' => true,
        'type'  => $d['type'],
        'value' => $d['value'],
    ]);
    exit;
}

echo json_encode(['valid' => false, 'message' => 'Ungültiger Code.']);
