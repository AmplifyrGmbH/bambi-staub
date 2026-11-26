<?php
// Liefert die Preise aus config/pricing.php an die Buchungsseiten.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
$cfg = require __DIR__ . '/../config/pricing.php';
echo json_encode([
    'cleaning'    => (int)$cfg['cleaning'],
    'linenPerBed' => (int)$cfg['linen_per_bed'],
    'energyPerNight' => (float)$cfg['energy_per_night'],
    'touristTax'  => $cfg['tourist_tax'],
    'pricing'     => array_map(fn($p) => ['from' => $p['from'], 'to' => $p['to'], 'price' => (int)$p['price']], $cfg['periods']),
], JSON_UNESCAPED_UNICODE);
