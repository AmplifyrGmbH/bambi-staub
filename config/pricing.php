<?php
// Preiszeiträume für Chalet Bambi – Preis pro Nacht in CHF.
// "from" und "to" sind inklusive (letzter Tag, an dem diese Preis gilt).
// Zeiträume müssen lückenlos sein, sonst erscheint "Preis auf Anfrage".
return [
    // Sommer 2026
    ['from' => '2026-08-18', 'to' => '2026-08-31', 'price' => 120, 'label' => 'Hochsaison Sommer'],
    // Herbst 2026
    ['from' => '2026-09-01', 'to' => '2026-10-31', 'price' => 110, 'label' => 'Nebensaison Herbst'],
    // Spätherbst 2026
    ['from' => '2026-11-01', 'to' => '2026-12-20', 'price' => 100, 'label' => 'Nebensaison'],
    // Weihnachten / Neujahr 2026/27
    ['from' => '2026-12-21', 'to' => '2027-01-07', 'price' => 170, 'label' => 'Hochsaison Winter'],
    // 2. & 3. Januarwoche 2027
    ['from' => '2027-01-08', 'to' => '2027-01-21', 'price' => 120, 'label' => 'Januarwochen'],
    // Hauptwintersaison 2027
    ['from' => '2027-01-22', 'to' => '2027-03-07', 'price' => 170, 'label' => 'Hochsaison Winter'],
    // Frühling 2027
    ['from' => '2027-03-08', 'to' => '2027-04-30', 'price' => 105, 'label' => 'Nebensaison Frühling'],
    // Sommer 2027
    ['from' => '2027-05-01', 'to' => '2027-10-31', 'price' => 120, 'label' => 'Hochsaison Sommer'],
];
