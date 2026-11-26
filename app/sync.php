<?php
/* Interhome-Sync: liest den iCal und gleicht ihn mit der Datenbank ab.
   CONFIRMED → Buchung (Quelle interhome), Ownerbooking → Buchung (Quelle eigentuemer), blocked → Sperre.
   Eigene Ergänzungen (Personen, Kontakt, Notizen) bleiben erhalten; nur Interhome-Felder werden aktualisiert. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const SYNC_ALTER_MAX = 1800;   // Sekunden: älter → beim nächsten Aufruf neu abgleichen

function ical_url(): string {
    $f = APP_ROOT . '/config/interhome.php';
    return is_file($f) ? (string)((require $f)['ical_url'] ?? '') : '';
}

function ical_laden(string $url): ?string {
    if ($url === '') return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'ChaletBambi/1.0']);
    $r = curl_exec($ch);
    $ok = $r !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200 && str_contains((string)$r, 'BEGIN:VCALENDAR');
    return $ok ? (string)$r : null;
}

/* VEVENTs → [uid, summary, description, start, end] (Datum JJJJ-MM-TT, Ende exklusiv) */
function ical_parsen(string $ical): array {
    $ical = preg_replace("/\r?\n[ \t]/", '', $ical);   // umbrochene Zeilen zusammenfügen
    preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $ical, $m);
    $ev = [];
    foreach ($m[1] as $e) {
        $feld = function (string $name) use ($e): ?string {
            return preg_match('/^' . $name . '(?:;[^:\r\n]*)?:(.*)$/m', $e, $x) ? trim(str_replace(['\\,', '\\;', '\\n'], [',', ';', ' '], $x[1])) : null;
        };
        $s = $feld('DTSTART'); $en = $feld('DTEND'); $uid = $feld('UID');
        if (!$uid || !$s || !$en || !preg_match('/^(\d{4})(\d{2})(\d{2})/', $s, $a) || !preg_match('/^(\d{4})(\d{2})(\d{2})/', $en, $b)) continue;
        $start = "$a[1]-$a[2]-$a[3]"; $end = "$b[1]-$b[2]-$b[3]";
        if ($start >= $end) continue;
        $ev[] = ['uid' => $uid, 'summary' => (string)$feld('SUMMARY'), 'description' => (string)$feld('DESCRIPTION'), 'start' => $start, 'end' => $end];
    }
    return $ev;
}

/* Abgleich, falls der letzte älter als SYNC_ALTER_MAX ist (oder $erzwingen). Gibt eine kurze Zusammenfassung zurück. */
function sync_interhome(bool $erzwingen = false): array {
    $zuletzt = (int)(meta_get('sync_zuletzt') ?? 0);
    if (!$erzwingen && time() - $zuletzt < SYNC_ALTER_MAX) return ['uebersprungen' => true];

    $lock = fopen(data_dir() . '/sync.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['uebersprungen' => true];   // läuft gerade anderswo
    try {
        meta_set('sync_zuletzt', (string)time());   // auch bei Fehler: nicht bei jedem Seitenaufruf neu versuchen
        $ical = ical_laden(ical_url());
        if ($ical === null) {
            meta_set('sync_fehler', jetzt() . ': Interhome-Kalender nicht erreichbar');
            return ['fehler' => 'Interhome-Kalender nicht erreichbar'];
        }
        $r = sync_abgleichen(ical_parsen($ical));
        meta_set('sync_ok', jetzt());
        meta_set('sync_fehler', '');
        return $r;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* Direktbuchung ↔ Eigentümerbuchung in Interhome (dort zum Sperren eingetragen) über identische Daten verknüpfen.
   Fällt die Eigentümerbuchung weg, wird die Verknüpfung gelöst. */
function interhome_verknuepfen(): void {
    foreach (q("SELECT d.id, d.nummer, e.id AS eid, e.nummer AS enr FROM buchung d JOIN buchung e ON e.quelle = 'eigentuemer' AND e.status = 'bestaetigt' AND e.ih_fehlt = 0
                AND e.anreise = d.anreise AND e.abreise = d.abreise AND e.partner_id IS NULL
                WHERE d.quelle IN ('website','manuell') AND d.status = 'bestaetigt' AND d.partner_id IS NULL")->fetchAll() as $p) {
        q('UPDATE buchung SET partner_id = ?, ih_eingetragen_am = COALESCE(ih_eingetragen_am, ?) WHERE id = ?', [$p['eid'], jetzt(), $p['id']]);
        q('UPDATE buchung SET partner_id = ? WHERE id = ?', [$p['id'], $p['eid']]);
        verlauf((int)$p['id'], 'sync', 'in Interhome gesperrt', "Eigentümerbuchung {$p['enr']} gefunden und verknüpft");
        verlauf((int)$p['eid'], 'sync', 'verknüpft', "gehört zur Direktbuchung {$p['nummer']}");
    }
    // Eigentümerbuchung verschwunden oder Daten geändert → Verknüpfung lösen
    foreach (q("SELECT d.id, e.id AS eid FROM buchung d JOIN buchung e ON e.id = d.partner_id
                WHERE d.quelle IN ('website','manuell') AND (e.ih_fehlt = 1 OR e.status != 'bestaetigt' OR e.anreise != d.anreise OR e.abreise != d.abreise)")->fetchAll() as $p) {
        q('UPDATE buchung SET partner_id = NULL WHERE id IN (?, ?)', [$p['id'], $p['eid']]);
        verlauf((int)$p['id'], 'sync', 'Interhome-Sperre fehlt', 'die verknüpfte Eigentümerbuchung ist nicht mehr passend im Interhome-Kalender');
    }
}

function sync_abgleichen(array $events): array {
    $r = ['neu' => [], 'geaendert' => [], 'fehlt' => [], 'zurueck' => []];
    $gesehenB = []; $gesehenS = [];
    $db = db();
    $db->beginTransaction();
    foreach ($events as $e) {
        // Sperre aus Interhome. Alle Sperren tragen dieselbe UID («IH-RES-@interhome.com»), darum zählen die Daten mit.
        if (!preg_match('/^IH:(\d+)\s*\/\s*(.+)$/i', $e['summary'], $m)) {
            $schluessel = $e['uid'] . '|' . $e['start'] . '|' . $e['end'];
            $gesehenS[] = $schluessel;
            q("INSERT INTO sperre (von, bis, grund, quelle, ih_uid, erstellt) VALUES (?, ?, 'Gesperrt (Interhome)', 'interhome', ?, ?)
               ON CONFLICT(ih_uid) DO NOTHING", [$e['start'], $e['end'], $schluessel, jetzt()]);
            continue;
        }
        $gesehenB[] = $e['uid'];
        $ihNr = $m[1]; $ihStatus = trim($m[2]);
        $quelle = stripos($ihStatus, 'owner') !== false ? 'eigentuemer' : 'interhome';
        $name = ($e['description'] !== '' && strtolower($e['description']) !== 'n.a.') ? $e['description'] : null;

        $alt = q1('SELECT * FROM buchung WHERE ih_uid = ?', [$e['uid']]);
        if (!$alt && ($ausMail = q1('SELECT * FROM buchung WHERE nummer = ? AND ih_uid IS NULL', ['IH-' . $ihNr]))) {
            // Buchung entstand schon aus der Interhome-Mail: mit dem Kalendereintrag verknüpfen
            q('UPDATE buchung SET ih_uid = ?, ih_status = ? WHERE id = ?', [$e['uid'], $ihStatus, $ausMail['id']]);
            $alt = q1('SELECT * FROM buchung WHERE id = ?', [$ausMail['id']]);
        }
        if (!$alt) {
            $id = buchung_anlegen(['nummer' => 'IH-' . $ihNr, 'quelle' => $quelle, 'status' => 'bestaetigt', 'anreise' => $e['start'], 'abreise' => $e['end'],
                'name' => $name, 'ih_uid' => $e['uid'], 'ih_status' => $ihStatus, 'reinigung' => 'yes'], 'sync');
            $r['neu'][] = $id;
            continue;
        }
        $aenderung = [];
        if ($alt['anreise'] !== $e['start'] || $alt['abreise'] !== $e['end']) {
            $aenderung[] = 'Daten ' . dmy($alt['anreise']) . '–' . dmy($alt['abreise']) . ' → ' . dmy($e['start']) . '–' . dmy($e['end']);
        }
        if ($alt['ih_status'] !== $ihStatus) $aenderung[] = "Interhome-Status {$alt['ih_status']} → {$ihStatus}";
        q('UPDATE buchung SET anreise = ?, abreise = ?, ih_status = ?, quelle = ?, name = COALESCE(name, ?), ih_fehlt = 0, geaendert = ? WHERE id = ?',
          [$e['start'], $e['end'], $ihStatus, $quelle, $name, $aenderung || $alt['ih_fehlt'] ? jetzt() : $alt['geaendert'], $alt['id']]);
        if ($aenderung) { verlauf((int)$alt['id'], 'sync', 'geändert', implode('; ', $aenderung)); $r['geaendert'][] = (int)$alt['id']; }
        if ($alt['ih_fehlt']) { verlauf((int)$alt['id'], 'sync', 'wieder da', 'wieder im Interhome-Kalender'); $r['zurueck'][] = (int)$alt['id']; }
    }

    // Verschwundene Einträge: nur wenn der Kalender plausibel ist (nicht leer) und nur für Künftiges.
    // Interhome nimmt Vergangenes aus dem Kalender; das ist kein Storno.
    if ($events) {
        $platz = fn(array $a) => $a ? implode(',', array_fill(0, count($a), '?')) : "''";
        $fehlend = q("SELECT id, nummer, anreise, abreise FROM buchung WHERE ih_uid IS NOT NULL AND ih_fehlt = 0 AND status = 'bestaetigt'
                      AND abreise > ? AND ih_uid NOT IN (" . $platz($gesehenB) . ')', array_merge([heute()], $gesehenB))->fetchAll();
        foreach ($fehlend as $f) {
            q('UPDATE buchung SET ih_fehlt = 1, geaendert = ? WHERE id = ?', [jetzt(), $f['id']]);
            verlauf((int)$f['id'], 'sync', 'fehlt im Interhome-Kalender', 'möglicherweise storniert – bitte prüfen');
            $r['fehlt'][] = (int)$f['id'];
        }
        q("DELETE FROM sperre WHERE quelle = 'interhome' AND ih_uid NOT IN (" . $platz($gesehenS) . ')', $gesehenS);
    }
    interhome_verknuepfen();
    $db->commit();

    // Hinweis an info@ bei Neuem / Änderungen
    $zeilen = [];
    foreach (['neu' => 'Neu', 'geaendert' => 'Geändert', 'fehlt' => 'Fehlt im Interhome-Kalender (storniert?)', 'zurueck' => 'Wieder im Kalender'] as $k => $titel) {
        foreach ($r[$k] as $id) {
            $b = q1('SELECT * FROM buchung WHERE id = ?', [$id]);
            if (!$b || $b['abreise'] < heute()) continue;
            $zeilen[] = "$titel: " . dmy($b['anreise']) . ' – ' . dmy($b['abreise']) . ' · ' . ($b['name'] ?: QUELLE[$b['quelle']]) . " ({$b['nummer']})\n  " . SITE_URL . "/admin/buchung/{$id}";
        }
    }
    if ($zeilen && meta_get('sync_erstlauf') !== null) {
        mail_info('Interhome: ' . count($zeilen) . ' Änderung' . (count($zeilen) > 1 ? 'en' : ''), "Der Abgleich mit dem Interhome-Kalender hat Folgendes gefunden:\n\n" . implode("\n\n", $zeilen) . "\n");
    }
    meta_set('sync_erstlauf', '1');   // beim allerersten Abgleich keine Mail (alles wäre «neu»)
    return array_map(fn($v) => is_array($v) ? count($v) : $v, $r);
}
