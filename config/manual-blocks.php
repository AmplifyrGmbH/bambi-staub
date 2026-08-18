<?php
// Manuelle Sperrzeiträume – für Buchungen, die Interhome nicht in den iCal exportiert
// (Eigentümerbuchungen, externe Buchungen, OB-Blöcke etc.)
//
// Format: 'start' = erster belegter Tag, 'end' = Abreisetag (EXKLUSIV, wie iCal)
// Beispiel: start 2026-08-03, end 2026-08-10 → Tage 3./4./5./6./7./8./9. belegt
//
// Nach jeder neuen Buchung im Interhome Owner Portal hier eintragen, falls
// sie nicht automatisch im Kalender erscheint.
return [
    // August 2026 – Buchungen aus dem Owner Portal (nicht im iCal-Export)
    ['start' => '2026-08-01', 'end' => '2026-08-03', 'label' => 'Belegt'],
    ['start' => '2026-08-03', 'end' => '2026-08-10', 'label' => 'Belegt'],
    ['start' => '2026-08-10', 'end' => '2026-08-17', 'label' => 'Belegt'],
    ['start' => '2026-08-17', 'end' => '2026-08-23', 'label' => 'Belegt'],
    ['start' => '2026-08-24', 'end' => '2026-08-29', 'label' => 'Belegt'],

    // September 2026
    ['start' => '2026-09-06', 'end' => '2026-09-07', 'label' => 'Eigentümerbuchung'],
    ['start' => '2026-09-08', 'end' => '2026-09-20', 'label' => 'Externe Buchung'],
];
