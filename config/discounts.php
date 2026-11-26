<?php
// Rabattcodes für Chalet Bambi.
// type: 'percent' (Prozent vom Gesamtpreis), 'fixed' (CHF-Betrag) oder
//       'night_rate' (fester Preis pro Nacht, keine Zusatzkosten; Reinigung und Bettwäsche durch die Gäste)
// valid_from / valid_to sind optional (Format: 'YYYY-MM-DD')
return [
    // Familie Staub: CHF 20 pro Nacht, sonst nichts
    ['code' => 'STAUBRABATT', 'type' => 'night_rate', 'value' => 20],

    // Beispiele – auskommentiert:
    // ['code' => 'FREUNDE20', 'type' => 'percent', 'value' => 20],
    // ['code' => 'WINTER50',  'type' => 'fixed',   'value' => 50, 'valid_from' => '2026-12-01', 'valid_to' => '2027-02-28'],
];
