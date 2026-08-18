<?php
// Preiszeiträume für Chalet Bambi – Preis pro Nacht in CHF.
// "from" und "to" sind inklusive (letzter Tag, an dem diese Preis gilt).
// Zeiträume müssen lückenlos sein, sonst erscheint "Preis auf Anfrage".
return [
    ['from' => '2026-08-18', 'to' => '2026-08-30', 'price' => 120, 'label' => 'Hochsaison Sommer'],
    ['from' => '2026-08-31', 'to' => '2026-10-25', 'price' => 110, 'label' => 'Nebensaison Herbst'],
    ['from' => '2026-10-26', 'to' => '2026-12-20', 'price' => 100, 'label' => 'Nebensaison'],
    ['from' => '2026-12-21', 'to' => '2027-03-07', 'price' => 170, 'label' => 'Hochsaison Winter'],
    ['from' => '2027-03-08', 'to' => '2027-04-25', 'price' => 105, 'label' => 'Nebensaison Frühling'],
    ['from' => '2027-04-26', 'to' => '2027-08-31', 'price' => 120, 'label' => 'Hochsaison Sommer'],
];
