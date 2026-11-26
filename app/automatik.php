<?php
/* Automatische Abläufe. Läuft über /admin/cron (alle 10 Min.) und als Rückfall bei Aufrufen des Admin-Centers.
   - Postfach abrufen und auswerten
   - Interhome-Gästen die Infos senden, sobald ihre E-Mail da ist
   - Code-Mail 3 Tage vor Anreise (Direktbuchung nur wenn bezahlt)
   - Dankesmail 1 Tag nach Abreise (Direktbuchungen)
   - Gastdaten 2 Jahre nach Abreise anonymisieren */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/postfach.php';

const CODE_TAGE_VORHER = 3;

/* Warum geht (noch) keine Code-Mail? null = bereit oder nicht nötig */
function code_hindernis(array $b): ?string {
    if ($b['status'] !== 'bestaetigt' || $b['quelle'] === 'eigentuemer' || $b['code_gesendet_am']) return null;
    if (ist_direkt($b) && !$b['bezahlt_am'] && (float)$b['preis'] > 0) return 'Zahlung offen: Code zurückgehalten';
    if (!$b['email']) return $b['quelle'] === 'manuell' ? null : 'E-Mail fehlt für die Code-Mail';   // von Hand erfasst (Familie, Bekannte): Code geht persönlich
    if (einst('tuercode') === '') return 'Türcode fehlt in den Einstellungen';
    return null;
}

function automatik_lauf(bool $mitPostfach = true): array {
    $r = ['postfach' => null, 'info' => 0, 'code' => 0, 'dank' => 0, 'anonymisiert' => 0];
    $lock = fopen(data_dir() . '/automatik.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return $r;
    try {
        meta_set('automatik_zuletzt', (string)time());
        if ($mitPostfach) $r['postfach'] = postfach_abrufen();
        $heute = heute();

        // Infos an Interhome-Gäste (einmal, sobald E-Mail vorhanden, nur für Künftiges)
        if (einst('auto_info_ih') === '1') {
            foreach (q("SELECT * FROM buchung WHERE quelle = 'interhome' AND status = 'bestaetigt' AND ih_fehlt = 0 AND email IS NOT NULL AND email != ''
                        AND info_gesendet_am IS NULL AND anreise > ?", [$heute])->fetchAll() as $b) {
                [$betreff, $text] = vorlage('info', $b);
                if (gast_mail_senden($b, 'info', $betreff, $text, 'automatik') === null) { q('UPDATE buchung SET info_gesendet_am = ? WHERE id = ?', [jetzt(), $b['id']]); $r['info']++; }
            }
        }

        // Code-Mail ab 3 Tage vor Anreise (bis und mit Anreisetag)
        if (einst('auto_code') === '1' && einst('tuercode') !== '') {
            $bis = date('Y-m-d', strtotime('+' . CODE_TAGE_VORHER . ' days'));
            foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle != 'eigentuemer' AND ih_fehlt = 0 AND code_gesendet_am IS NULL
                        AND anreise BETWEEN ? AND ?", [$heute, $bis])->fetchAll() as $b) {
                if (code_hindernis($b) !== null) continue;
                [$betreff, $text] = vorlage('code', $b);
                if (gast_mail_senden($b, 'code', $betreff, $text, 'automatik') === null) {
                    q('UPDATE buchung SET code_gesendet_am = ? WHERE id = ?', [jetzt(), $b['id']]);
                    // Interhome-Gäste mit kurzfristig eingetragener E-Mail: Infos sind im Guide-Link der Code-Mail enthalten
                    if ($b['quelle'] === 'interhome' && !$b['info_gesendet_am']) q('UPDATE buchung SET info_gesendet_am = ? WHERE id = ?', [jetzt(), $b['id']]);
                    $r['code']++;
                }
            }
        }

        // Dank 1–3 Tage nach Abreise (Direktbuchungen)
        if (einst('auto_dank') === '1') {
            foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle IN ('website','manuell') AND email IS NOT NULL AND email != ''
                        AND dank_gesendet_am IS NULL AND abreise BETWEEN ? AND ?", [date('Y-m-d', strtotime('-3 days')), date('Y-m-d', strtotime('-1 day'))])->fetchAll() as $b) {
                [$betreff, $text] = vorlage('dank', $b);
                if (gast_mail_senden($b, 'dank', $betreff, $text, 'automatik') === null) { q('UPDATE buchung SET dank_gesendet_am = ? WHERE id = ?', [jetzt(), $b['id']]); $r['dank']++; }
            }
        }

        // Löschfrist: Gastdaten 2 Jahre nach Abreise anonymisieren (Beträge bleiben für die Buchhaltung)
        $grenze = date('Y-m-d', strtotime('-2 years'));
        foreach (q('SELECT id FROM buchung WHERE abreise < ? AND anonymisiert_am IS NULL', [$grenze])->fetchAll() as $b) {
            q("UPDATE buchung SET name = CASE WHEN name IS NULL THEN NULL ELSE 'anonymisiert' END, email = NULL, telefon = NULL, adresse = NULL, nachricht = NULL, notiz = NULL, anonymisiert_am = ? WHERE id = ?", [jetzt(), $b['id']]);
            q('UPDATE mail SET kurztext = NULL, von = NULL, von_name = NULL, an = NULL, ergebnis = NULL WHERE buchung_id = ?', [$b['id']]);
            $r['anonymisiert']++;
        }
        q("DELETE FROM mail WHERE buchung_id IS NULL AND erstellt < ?", [$grenze]);
        q('DELETE FROM login_versuch WHERE zeit < ?', [time() - 30 * 86400]);
    } finally {
        flock($lock, LOCK_UN); fclose($lock);
    }
    return $r;
}
