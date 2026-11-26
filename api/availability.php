<?php
/* Belegte Zeiträume für den Buchungskalender der Website.
   Quelle: Datenbank des Admin-Centers (Interhome-Abgleich alle 30 Min., bestätigte Direktbuchungen, Sperren).
   Fällt die Datenbank aus, liest der alte Weg direkt den Interhome-Kalender. Ende jeweils exklusiv (wie iCal). */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
try {
    require_once __DIR__ . '/../app/sync.php';
    try { sync_interhome(); } catch (Throwable $e) { error_log('bambi sync: ' . $e->getMessage()); }
    echo json_encode(['bookings' => belegungen(date('Y-m-d', strtotime('-1 day'))), 'cached_at' => date('c')], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('bambi availability: ' . $e->getMessage());
    require __DIR__ . '/../app/availability_legacy.php';
}
