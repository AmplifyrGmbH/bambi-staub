<?php
// EINZIGE Stelle für alle Preise von Chalet Bambi (CHF).
// Server (Anfrage, Mails) und Buchungsseiten DE/EN lesen von hier
// (die Buchungsseiten über /api/pricing.php). Nach einer Änderung genügt der Push.
return [
    // Endreinigung pauschal
    'cleaning'      => 98,
    // Bettwäsche pro Person
    'linen_per_bed' => 20,
    // Energiekosten pro Nacht, immer im Preis
    'energy_per_night' => 6.5,

    // Kurtaxe: NICHT im Preis, Gäste zahlen selbst beim Tourismus-Büro Sörenberg.
    // Nur für den Hinweis auf der Buchungsseite und in der Bestätigung (Quelle: soerenberg.ch/de/kurtaxe)
    'tourist_tax' => ['adult' => 4, 'from_age' => 16],

    // Preis pro Nacht. "from" und "to" sind inklusive (letzter Tag, an dem dieser Preis gilt).
    // Zeiträume müssen lückenlos sein, sonst erscheint "Preis auf Anfrage".
    'periods' => [
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
    ],
];
