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
    // Nur als Fallback nötig, falls die Interhome iCal-URL eine Buchung nicht enthält.
    // Beispiel:
    // ['start' => '2026-12-24', 'end' => '2026-12-27', 'label' => 'Gesperrt'],
];
