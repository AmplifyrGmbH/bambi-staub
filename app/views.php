<?php
/* HTML-Ansichten des Admin-Centers. Alles serverseitig, ohne Framework. */
declare(strict_types=1);

const MONATE = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

function layout(?array $ich, string $titel, string $aktiv, string $inhalt): void {
    $v = @filemtime(APP_ROOT . '/admin/admin.css') ?: 1;
    $nav = '';
    if ($ich) {
        $punkte = $ich['rolle'] === 'reinigung'
            ? ['reinigung' => 'Reinigung']
            : ['' => 'Übersicht', 'buchungen' => 'Buchungen', 'kalender' => 'Kalender', 'posteingang' => 'Posteingang', 'sperren' => 'Sperren', 'reinigung' => 'Reinigung', 'geld' => 'Geld', 'einstellungen' => 'Einstellungen'];
        $offen = $ich['rolle'] === 'admin' ? (int)qv("SELECT COUNT(*) FROM mail WHERE erledigt = 0 AND richtung = 'ein'") : 0;
        foreach ($punkte as $p => $l) $nav .= '<a href="/admin' . ($p ? "/$p" : '') . '"' . ($p === $aktiv ? ' aria-current="page"' : '') . '>' . $l . ($p === 'posteingang' && $offen ? ' <span class="zahl">' . $offen . '</span>' : '') . '</a>';
        $nav = '<nav class="nav">' . $nav . '</nav>'
             . '<form method="post" action="/admin/logout" class="logout">' . csrf_feld() . '<button type="submit" class="link">Abmelden</button></form>';
    }
    $hinweis = '';
    if (!empty($_SESSION['hinweis'])) {
        [$art, $text] = $_SESSION['hinweis'];
        unset($_SESSION['hinweis']);
        $hinweis = '<div class="hinweis hinweis-' . h($art) . '" role="status">' . h($text) . '</div>';
    }
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#2D3E33">'
       . '<title>' . h($titel) . ' · Chalet Bambi Admin</title>'
       . '<link rel="icon" href="/favicons_bambi/favicon.ico"><link rel="stylesheet" href="/admin/admin.css?v=' . $v . '">'
       . '<script src="/admin/admin.js?v=' . (@filemtime(APP_ROOT . '/admin/admin.js') ?: 1) . '" defer></script></head><body>'
       . '<header class="kopf"><a href="/admin" class="marke">Chalet Bambi <span>Admin</span></a>' . $nav . '</header>'
       . '<main class="inhalt">' . $hinweis . $inhalt . '</main></body></html>';
}

function badge(string $text, string $art): string { return '<span class="badge badge-' . h($art) . '">' . h($text) . '</span>'; }
function quelle_badge(array $b): string { return badge(QUELLE[$b['quelle']] ?? $b['quelle'], 'q-' . $b['quelle']); }
function status_badge(array $b): string {
    if ($b['ih_fehlt'] && $b['status'] === 'bestaetigt') return badge('Storniert?', 'warn');
    $x = badge(STATUS[$b['status']] ?? $b['status'], 's-' . $b['status']);
    if ($b['status'] === 'bestaetigt' && ist_direkt($b) && (float)$b['preis'] > 0) $x .= $b['bezahlt_am'] ? badge('bezahlt', 's-bestaetigt') : badge('Zahlung offen', $b['zahlung_faellig'] && $b['zahlung_faellig'] < heute() ? 'warn' : 'leer');
    if ($b['quelle'] === 'eigentuemer' && $b['partner_id']) $x .= badge('= Direktbuchung', 'leer');
    return $x;
}
function anzeigename(array $b): string { return $b['name'] ?: ($b['quelle'] === 'eigentuemer' ? 'Eigentümerbuchung' : ($b['nummer'] ?: 'ohne Namen')); }

/* Was fehlt noch? (für Interhome-Buchungen kurz vor Anreise) */
function fehlende_angaben(array $b): array {
    $f = [];
    if ($b['quelle'] === 'eigentuemer') return $f;
    if (!$b['personen']) $f[] = 'Personen';
    if (!$b['email']) $f[] = 'E-Mail';
    if (!$b['telefon']) $f[] = 'Telefon';
    return $f;
}

function buchung_zeile(array $b): string {
    $n = naechte($b['anreise'], $b['abreise']);
    // «fehlt» erst 14 Tage vor Anreise anzeigen: Interhome meldet Personen/Kontakt meist erst kurz vorher
    $fehlt = $b['status'] === 'bestaetigt' && $b['abreise'] >= heute() && $b['anreise'] <= date('Y-m-d', strtotime('+14 days')) ? fehlende_angaben($b) : [];
    return '<a class="zeile" href="/admin/buchung/' . (int)$b['id'] . '">'
        . '<span class="z-datum"><b>' . wt($b['anreise']) . ' ' . dm($b['anreise']) . '</b> – ' . wt($b['abreise']) . ' ' . dmy($b['abreise']) . '<small>' . $n . ' Nächte</small></span>'
        . '<span class="z-name">' . h(anzeigename($b)) . '<small>' . h($b['nummer'] ?? '') . ($b['personen'] ? ' · ' . (int)$b['personen'] . ' Pers.' : '') . '</small></span>'
        . '<span class="z-badges">' . quelle_badge($b) . status_badge($b) . ($fehlt ? badge('fehlt: ' . implode(', ', $fehlt), 'leer') : '') . '</span>'
        . '</a>';
}

// ── Anmeldung ─────────────────────────────────────────────────────────
function seite_login(string $fehler): void {
    layout(null, 'Anmelden', '', '<section class="karte login">'
        . '<h1>Anmelden</h1>'
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '')
        . '<form method="post" action="/admin/login">' . csrf_feld()
        . '<label>Benutzername<input name="login" autocomplete="username" autocapitalize="none" required autofocus></label>'
        . '<label>Passwort<input name="passwort" type="password" autocomplete="current-password" required></label>'
        . '<button type="submit" class="knopf">Anmelden</button></form></section>');
}

function seite_fehlt(array $ich): void {
    layout($ich, 'Nicht gefunden', '', '<section class="karte"><h1>Nicht gefunden</h1><p><a href="/admin">Zur Übersicht</a></p></section>');
}

// ── Übersicht ─────────────────────────────────────────────────────────
function seite_uebersicht(array $ich): void {
    $heute = heute();
    $in30 = date('Y-m-d', strtotime('+30 days'));
    $in7 = date('Y-m-d', strtotime('+7 days'));

    // Aufgaben
    $aHtml = '';
    foreach (aufgaben_liste() as [$href, $text, $unter]) {
        $aHtml .= '<a class="aufgabe" href="' . h($href) . '"><b>' . h($text) . '</b><span>' . h($unter) . '</span></a>';
    }
    $anzahl = substr_count($aHtml, 'class="aufgabe"');

    // Heute
    $hHtml = '';
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND (anreise = ? OR abreise = ? OR (anreise < ? AND abreise > ?)) ORDER BY anreise", [$heute, $heute, $heute, $heute]) as $b) {
        $art = $b['abreise'] === $heute ? 'Abreise bis ' . zeiten($b)['aus'] : ($b['anreise'] === $heute ? 'Anreise ab ' . zeiten($b)['ein'] . ($b['anreisezeit'] ? ' (' . $b['anreisezeit'] . ')' : '') : 'vor Ort bis ' . dm($b['abreise']));
        $hHtml .= '<a class="ereignis" href="/admin/buchung/' . (int)$b['id'] . '"><span class="e-datum">' . h($art) . '</span><span>' . h(anzeigename($b)) . ($b['personen'] ? ' · ' . (int)$b['personen'] . ' Pers.' : '') . ($b['telefon'] ? ' · ' . h($b['telefon']) : '') . '</span></a>';
    }

    // Nächste 30 Tage: An- und Abreisen
    $ereignisse = [];
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND ((anreise BETWEEN ? AND ?) OR (abreise BETWEEN ? AND ?))", [$heute, $in30, $heute, $in30]) as $b) {
        if ($b['anreise'] >= $heute && $b['anreise'] <= $in30) $ereignisse[] = [$b['anreise'], 1, 'an', $b];
        if ($b['abreise'] >= $heute && $b['abreise'] <= $in30) $ereignisse[] = [$b['abreise'], 0, 'ab', $b];
    }
    usort($ereignisse, fn($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);
    $eHtml = '';
    foreach ($ereignisse as [$d, , $art, $b]) {
        $eHtml .= '<a class="ereignis" href="/admin/buchung/' . (int)$b['id'] . '"><span class="e-datum">' . ($d === $heute ? 'Heute' : wt($d) . ' ' . dm($d)) . '</span>'
            . '<span class="e-art e-' . $art . '">' . ($art === 'an' ? 'Anreise' : 'Abreise') . '</span>'
            . '<span>' . h(anzeigename($b)) . ($art === 'an' && $b['personen'] ? ' · ' . (int)$b['personen'] . ' Pers.' : '') . ($art === 'an' && $b['anreisezeit'] ? ' · ' . h($b['anreisezeit']) : '') . '</span></a>';
    }

    // Jahreszahlen
    $jahr = date('Y');
    $naechteNach = ['interhome' => 0, 'direkt' => 0, 'eigentuemer' => 0];
    $einnahmen = 0.0; $ertragIh = 0.0;
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND anreise <= ? AND abreise >= ?", ["$jahr-12-31", "$jahr-01-01"]) as $b) {
        $von = max($b['anreise'], "$jahr-01-01"); $bis = min($b['abreise'], ($jahr + 1) . '-01-01');
        $n = max(0, naechte($von, $bis));
        $k = $b['quelle'] === 'interhome' ? 'interhome' : ($b['quelle'] === 'eigentuemer' ? 'eigentuemer' : 'direkt');
        $naechteNach[$k] += $n;
        if ($k === 'direkt' && substr($b['anreise'], 0, 4) === $jahr) $einnahmen += (float)$b['preis'];
        if ($k === 'interhome' && substr($b['anreise'], 0, 4) === $jahr) $ertragIh += (float)$b['ertrag_interhome'];
    }
    $tageImJahr = (int)date('z', mktime(0, 0, 0, 12, 31, (int)$jahr)) + 1;
    $belegt = array_sum($naechteNach);

    // Sync
    $ok = meta_get('sync_ok'); $fehler = meta_get('sync_fehler');
    $sync = '<form method="post" action="/admin/sync" class="sync">' . csrf_feld() . '<input type="hidden" name="zurueck" value="">'
        . '<span>Interhome-Kalender: ' . ($ok ? 'abgeglichen ' . date('d.m. H:i', strtotime($ok)) : 'noch nie abgeglichen') . ($fehler ? ' · <b class="fehler">' . h($fehler) . '</b>' : '') . '</span>'
        . '<button type="submit" class="link">Jetzt abgleichen</button></form>'
        . '<p class="sync klein">Postfach: ' . (($p = meta_get('postfach_ok')) ? 'abgerufen ' . date('d.m. H:i', strtotime($p)) : 'noch nie abgerufen')
        . (($pf = meta_get('postfach_fehler')) ? ' · <b class="fehler">' . h($pf) . '</b>' : '') . (($kf = meta_get('ki_fehler')) ? ' · KI: <b class="fehler">' . h($kf) . '</b>' : '') . '</p>';

    layout($ich, 'Übersicht', '', '<h1>Übersicht</h1>'
        . ($hHtml ? '<section class="karte heute"><h2>Heute, ' . wt($heute) . ' ' . dmy($heute) . '</h2>' . $hHtml . '</section>' : '')
        . '<div class="raster">'
        . '<section class="karte"><h2>Zu erledigen <span class="zahl">' . $anzahl . '</span></h2>' . ($aHtml ?: '<p class="leer">Nichts offen.</p>') . '</section>'
        . '<section class="karte"><h2>Nächste 30 Tage</h2>' . ($eHtml ?: '<p class="leer">Keine An- oder Abreisen.</p>') . '</section>'
        . '</div>'
        . '<section class="karte"><h2>' . $jahr . '</h2><div class="kennzahlen">'
        . '<div><b>' . $belegt . '</b><span>belegte Nächte (' . round($belegt / $tageImJahr * 100) . ' %)</span></div>'
        . '<div><b>' . $naechteNach['interhome'] . '</b><span>Nächte Interhome</span></div>'
        . '<div><b>' . $naechteNach['direkt'] . '</b><span>Nächte direkt</span></div>'
        . '<div><b>' . $naechteNach['eigentuemer'] . '</b><span>Nächte Eigentümer</span></div>'
        . '<div><b>' . betrag($einnahmen) . '</b><span>Einnahmen direkt</span></div>'
        . ($ertragIh > 0 ? '<div><b>' . betrag($ertragIh) . '</b><span>Ertrag Interhome (erfasst)</span></div>' : '')
        . '</div></section>'
        . '<p class="aktionen"><a class="knopf" href="/admin/neu">+ Buchung erfassen</a></p>'
        . $sync);
}

// ── Buchungsliste ─────────────────────────────────────────────────────
function seite_buchungen(array $ich): void {
    $filter = (string)($_GET['f'] ?? 'kommend');
    $suche = trim((string)($_GET['q'] ?? ''));
    $heute = heute();
    [$where, $p, $order] = match ($filter) {
        'anfragen'  => ["status = 'anfrage'", [], 'anreise'],
        'vergangen' => ['abreise < ?', [$heute], 'anreise DESC'],
        'alle'      => ['1', [], 'anreise DESC'],
        default     => ["abreise >= ? AND status IN ('anfrage','bestaetigt')", [$heute], 'anreise'],
    };
    if ($suche !== '') {
        $where .= ' AND (name LIKE ? OR nummer LIKE ? OR email LIKE ? OR telefon LIKE ?)';
        $like = '%' . $suche . '%';
        array_push($p, $like, $like, $like, $like);
    }
    $liste = q("SELECT * FROM buchung WHERE $where ORDER BY $order LIMIT 300", $p)->fetchAll();
    $tabs = '';
    foreach (['kommend' => 'Kommend', 'anfragen' => 'Anfragen', 'vergangen' => 'Vergangen', 'alle' => 'Alle'] as $k => $l) {
        $tabs .= '<a href="/admin/buchungen?f=' . $k . ($suche ? '&amp;q=' . urlencode($suche) : '') . '"' . ($k === $filter ? ' aria-current="page"' : '') . '>' . $l . '</a>';
    }
    layout($ich, 'Buchungen', 'buchungen', '<div class="titelzeile"><h1>Buchungen</h1><a class="knopf" href="/admin/neu">+ Erfassen</a></div>'
        . '<div class="werkzeuge"><nav class="tabs">' . $tabs . '</nav>'
        . '<form method="get" class="suche"><input type="hidden" name="f" value="' . h($filter) . '"><input type="search" name="q" value="' . h($suche) . '" placeholder="Name, Nummer, E-Mail …" aria-label="Suchen"></form>'
        . '<a href="/admin/kalender" class="link">Als Kalender</a></div>'
        . '<div class="liste">' . (implode('', array_map('buchung_zeile', $liste)) ?: '<p class="leer">Keine Buchungen.</p>') . '</div>');
}

// ── Kalender ──────────────────────────────────────────────────────────
function seite_kalender(array $ich): void {
    $m = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['m'] ?? '')) ? $_GET['m'] : date('Y-m');
    $start = strtotime($m . '-01');
    $vor = date('Y-m', strtotime('-1 month', $start)); $nach = date('Y-m', strtotime('+1 month', $start));
    $ende = date('Y-m-d', strtotime('+2 months', $start));
    $eintraege = [];
    foreach (q("SELECT * FROM buchung WHERE status IN ('anfrage','bestaetigt') AND NOT (quelle = 'eigentuemer' AND partner_id IS NOT NULL) AND anreise < ? AND abreise > ?", [$ende, date('Y-m-d', $start)]) as $b) {
        $eintraege[] = ['von' => $b['anreise'], 'bis' => $b['abreise'], 'text' => anzeigename($b), 'art' => $b['status'] === 'anfrage' ? 'anfrage' : $b['quelle'], 'href' => '/admin/buchung/' . $b['id']];
    }
    foreach (q('SELECT * FROM sperre WHERE von < ? AND bis > ?', [$ende, date('Y-m-d', $start)]) as $s) {
        $eintraege[] = ['von' => $s['von'], 'bis' => $s['bis'], 'text' => $s['grund'] ?: 'Gesperrt', 'art' => 'sperre', 'href' => '/admin/sperren'];
    }
    $legende = '';
    foreach (['interhome' => 'Interhome', 'website' => 'Direkt (Website)', 'manuell' => 'Von Hand', 'eigentuemer' => 'Eigentümer', 'anfrage' => 'Anfrage (offen)', 'sperre' => 'Gesperrt'] as $k => $l) {
        $legende .= '<span><i class="k-' . $k . '"></i>' . $l . '</span>';
    }
    layout($ich, 'Kalender', 'kalender', '<div class="titelzeile"><h1>Kalender</h1><a href="/admin/buchungen" class="link">Als Liste</a></div>'
        . '<div class="monatsnav"><a class="knopf hell" href="/admin/kalender?m=' . $vor . '" aria-label="Vorheriger Monat">‹</a>'
        . '<a class="link" href="/admin/kalender">Heute</a>'
        . '<a class="knopf hell" href="/admin/kalender?m=' . $nach . '" aria-label="Nächster Monat">›</a></div>'
        . '<div class="monate">' . monat((int)date('Y', $start), (int)date('n', $start), $eintraege) . monat((int)date('Y', strtotime('+1 month', $start)), (int)date('n', strtotime('+1 month', $start)), $eintraege) . '</div>'
        . '<p class="legende">' . $legende . '</p>'
        . '<p class="klein">Ein Balken zeigt die Nächte. Ein Klick auf einen Tag ohne Belegung erfasst dort eine neue Buchung.</p>');
}

/* Wie viele Tage läuft der Balken ab $d in dieser Woche (und diesem Monat) noch? Begrenzt die Beschriftung. */
function balkenlaenge(string $d, string $bis): int {
    $ts = strtotime($d . ' 12:00');
    $bisWoche = 8 - (int)date('N', $ts);
    $bisMonat = (int)date('t', $ts) - (int)date('j', $ts) + 1;
    return max(1, min(7, $bisWoche, $bisMonat, naechte($d, $bis)));
}

function monat(int $j, int $mo, array $eintraege): string {
    $erster = mktime(12, 0, 0, $mo, 1, $j);
    $tage = (int)date('t', $erster);
    $leer = ((int)date('N', $erster)) - 1;
    $html = '<section class="monat"><h2>' . MONATE[$mo] . ' ' . $j . '</h2><div class="wochentage"><span>Mo</span><span>Di</span><span>Mi</span><span>Do</span><span>Fr</span><span>Sa</span><span>So</span></div><div class="tage">';
    $html .= str_repeat('<span class="tag leer"></span>', $leer);
    for ($t = 1; $t <= $tage; $t++) {
        $d = sprintf('%04d-%02d-%02d', $j, $mo, $t);
        $belegt = ''; $treffer = null;
        foreach ($eintraege as $e) {
            if ($d >= $e['von'] && $d < $e['bis']) {
                $treffer = $e;
                $erst = $d === $e['von'] || $t === 1 || (int)date('N', strtotime($d)) === 1;
                $belegt .= '<a class="balken k-' . h($e['art']) . ($d === $e['von'] ? ' beginn' : '') . (date('Y-m-d', strtotime($d . ' +1 day')) === $e['bis'] ? ' ende' : '') . '" href="' . h($e['href']) . '" title="' . h($e['text'] . ': ' . dm($e['von']) . '–' . dm($e['bis'])) . '">'
                    . ($erst ? '<span class="l' . balkenlaenge($d, $e['bis']) . '">' . h($e['text']) . '</span>' : '') . '</a>';
            }
        }
        $abreise = '';
        foreach ($eintraege as $e) if ($e['bis'] === $d && $e['art'] !== 'sperre' && $e['art'] !== 'anfrage') $abreise = '<i class="ab k-' . h($e['art']) . '" title="Abreise ' . h($e['text']) . '"></i>';
        $klasse = 'tag' . ($d === heute() ? ' heute' : '') . ($d < heute() ? ' vorbei' : '');
        $nr = $treffer ? '<span class="nr">' . $t . '</span>' : '<a class="nr" href="/admin/neu?von=' . $d . '&amp;bis=' . date('Y-m-d', strtotime($d . ' +3 days')) . '" title="Hier erfassen">' . $t . '</a>';
        $html .= '<div class="' . $klasse . '">' . $nr . $abreise . $belegt . '</div>';
    }
    return $html . '</div></section>';
}

// ── Neue Buchung / Detail ─────────────────────────────────────────────
function feld(string $label, string $name, mixed $wert, string $typ = 'text', string $extra = ''): string {
    return '<label>' . h($label) . '<input type="' . $typ . '" name="' . $name . '" value="' . h($wert ?? '') . '" ' . $extra . '></label>';
}
function bereich(string $label, string $name, mixed $wert, int $zeilen = 3, string $extra = ''): string {
    return '<label>' . h($label) . '<textarea name="' . $name . '" rows="' . $zeilen . '" ' . $extra . '>' . h($wert ?? '') . '</textarea></label>';
}
function auswahl(string $label, string $name, array $optionen, mixed $wert): string {
    $o = '';
    foreach ($optionen as $k => $l) $o .= '<option value="' . h($k) . '"' . ((string)$k === (string)$wert ? ' selected' : '') . '>' . h($l) . '</option>';
    return '<label>' . h($label) . '<select name="' . $name . '">' . $o . '</select></label>';
}

function felder_gast(array $w): string {
    return '<fieldset><legend>Gast</legend><div class="felder">'
        . feld('Name', 'name', $w['name'] ?? '', 'text', 'autocomplete="off"')
        . feld('E-Mail', 'email', $w['email'] ?? '', 'email', 'autocomplete="off"')
        . feld('Telefon', 'telefon', $w['telefon'] ?? '', 'tel', 'autocomplete="off"')
        . bereich('Adresse', 'adresse', $w['adresse'] ?? '', 2)
        . feld('Personen', 'personen', $w['personen'] ?? '', 'number', 'min="1" max="4" inputmode="numeric"')
        . feld('davon Erwachsene (ab 16)', 'erwachsene', $w['erwachsene'] ?? '', 'number', 'min="0" max="4" inputmode="numeric"')
        . feld('davon Kinder', 'kinder', $w['kinder'] ?? '', 'number', 'min="0" max="4" inputmode="numeric"')
        . feld('Anreisezeit', 'anreisezeit', $w['anreisezeit'] ?? '', 'text', 'placeholder="z. B. ca. 16 Uhr"')
        . '</div></fieldset>';
}

function seite_neu(array $ich, string $fehler, array $w): void {
    layout($ich, 'Buchung erfassen', 'buchungen', '<h1>Buchung erfassen</h1>'
        . '<p class="klein">Für Buchungen per Telefon, Mail oder Familie. Website- und Interhome-Buchungen kommen von selbst.</p>'
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '')
        . '<form method="post" action="/admin/neu" class="formular">' . csrf_feld()
        . '<fieldset><legend>Aufenthalt</legend><div class="felder">'
        . feld('Anreise', 'anreise', $w['anreise'] ?? '', 'date', 'required')
        . feld('Abreise', 'abreise', $w['abreise'] ?? '', 'date', 'required')
        . auswahl('Status', 'status', ['bestaetigt' => 'Bestätigt (sperrt den Kalender)', 'anfrage' => 'Anfrage (noch offen)'], $w['status'] ?? 'bestaetigt')
        . feld('Preis total (CHF)', 'preis', $w['preis'] ?? '', 'text', 'inputmode="decimal"')
        . auswahl('Sprache', 'sprache', ['de' => 'Deutsch', 'en' => 'Englisch'], $w['sprache'] ?? 'de')
        . '</div></fieldset>'
        . felder_gast($w)
        . '<fieldset><legend>Notizen</legend><div class="felder">'
        . bereich('Interne Notiz', 'notiz', $w['notiz'] ?? '', 3)
        . bereich('Notiz für die Reinigung (sieht Margrit)', 'notiz_reinigung', $w['notiz_reinigung'] ?? '', 2)
        . '</div></fieldset>'
        . '<p class="aktionen"><button type="submit" class="knopf">Speichern</button> <a href="/admin/buchungen" class="link">Abbrechen</a></p></form>');
}

function seite_buchung(array $ich, array $b, string $fehler, ?array $w): void {
    $w = $w ?? $b;
    $ih = (bool)$b['ih_uid'];
    $n = naechte($b['anreise'], $b['abreise']);
    $k = in_array($b['status'], ['anfrage', 'bestaetigt'], true) ? konflikte($b['anreise'], $b['abreise'], (int)$b['id']) : [];
    $kHtml = $k ? '<p class="warnung">Überschneidung mit: ' . implode(', ', array_map(fn($x) => $x['id'] ? '<a href="/admin/buchung/' . (int)$x['id'] . '">' . h($x['text']) . '</a>' : h($x['text']), $k)) . '</p>' : '';

    $info = '<dl class="infos">'
        . '<div><dt>Nummer</dt><dd>' . h($b['nummer'] ?? '–') . '</dd></div>'
        . '<div><dt>Quelle</dt><dd>' . h(QUELLE[$b['quelle']] ?? $b['quelle']) . ($ih ? ' · ' . h($b['ih_status']) : '') . '</dd></div>'
        . '<div><dt>Nächte</dt><dd>' . $n . '</dd></div>'
        . ($b['preis'] !== null ? '<div><dt>Preis</dt><dd>' . betrag($b['preis']) . '</dd></div>' : '')
        . ($b['rabattcode'] ? '<div><dt>Rabattcode</dt><dd>' . h($b['rabattcode']) . '</dd></div>' : '')
        . '<div><dt>Endreinigung</dt><dd>' . ($b['reinigung'] === 'no' ? 'durch die Gäste' : 'ja') . '</dd></div>'
        . ($b['bettwaesche'] !== null && !$ih ? '<div><dt>Bettwäsche</dt><dd>' . ((int)$b['bettwaesche'] > 0 ? 'gemietet für ' . (int)$b['bettwaesche'] . ' Pers.' : 'eigene') . '</dd></div>' : '')
        . ($b['ertrag_interhome'] !== null ? '<div><dt>Ertrag Interhome</dt><dd>' . betrag($b['ertrag_interhome']) . '</dd></div>' : '')
        . ($b['zahlung_faellig'] ? '<div><dt>Zahlung fällig</dt><dd>' . dmy($b['zahlung_faellig']) . ($b['bezahlt_am'] ? ' · bezahlt ' . dmy($b['bezahlt_am']) : '') . '</dd></div>' : '')
        . ($b['partner_id'] ? '<div><dt>Interhome</dt><dd><a href="/admin/buchung/' . (int)$b['partner_id'] . '">' . ($b['quelle'] === 'eigentuemer' ? 'gehört zur Direktbuchung' : 'als Eigentümerbuchung gesperrt ✓') . '</a></dd></div>' : '')
        . '<div><dt>Mails</dt><dd>' . mail_status($b) . '</dd></div>'
        . '<div><dt>Erfasst</dt><dd>' . date('d.m.Y H:i', strtotime($b['erstellt'])) . '</dd></div>'
        . '</dl>'
        . ($b['nachricht'] ? '<div class="nachricht"><b>Nachricht des Gastes</b><p>' . nl2br(h($b['nachricht'])) . '</p></div>' : '');

    $kontakt = '';
    if ($b['email']) $kontakt .= '<a class="knopf hell" href="mailto:' . h($b['email']) . '?subject=' . rawurlencode('Chalet Bambi ' . dmy($b['anreise']) . ' – ' . dmy($b['abreise']) . ' (' . $b['nummer'] . ')') . '">E-Mail</a>';
    if ($b['telefon']) $kontakt .= '<a class="knopf hell" href="tel:' . h(preg_replace('/[^\d+]/', '', $b['telefon'])) . '">Anrufen</a>';

    $aufenthalt = $ih
        ? '<p class="klein">Daten und Status kommen von Interhome und werden beim Abgleich aktualisiert.</p>'
        : '<fieldset><legend>Aufenthalt</legend><div class="felder">'
          . feld('Anreise', 'anreise', $w['anreise'], 'date', 'required') . feld('Abreise', 'abreise', $w['abreise'], 'date', 'required')
          . auswahl('Status', 'status', STATUS, $w['status'])
          . feld('Preis total (CHF)', 'preis', $w['preis'] ?? '', 'text', 'inputmode="decimal"')
          . auswahl('Sprache', 'sprache', ['de' => 'Deutsch', 'en' => 'Englisch'], $w['sprache'] ?? 'de')
          . feld('Bezahlt am', 'bezahlt_am', $w['bezahlt_am'] ?? '', 'date')
          . '</div></fieldset>';

    $verlauf = '';
    foreach (q('SELECT * FROM verlauf WHERE buchung_id = ? ORDER BY id DESC', [$b['id']]) as $v) {
        $verlauf .= '<li><time>' . date('d.m.Y H:i', strtotime($v['zeit'])) . '</time> <b>' . h($v['aktion']) . '</b> <small>' . h($v['wer']) . '</small>'
            . ($v['details'] ? '<div>' . h($v['details']) . '</div>' : '') . '</li>';
    }

    $gefahr = buchung_aktionen($b);
    if ($b['ih_fehlt'] && $b['status'] === 'bestaetigt') {
        $gefahr .= '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '/storno-bestaetigen" class="warnung">' . csrf_feld()
            . '<p>Diese Buchung ist nicht mehr im Interhome-Kalender. Ist sie storniert? Bitte im Interhome-Portal prüfen.</p>'
            . '<button type="submit" class="knopf" data-bestaetigen="Als storniert markieren? Der Zeitraum wird auf der Website wieder frei.">Storno bestätigen</button></form>';
    }
    if (in_array($b['quelle'], ['website', 'manuell'], true)) {
        $gefahr .= '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '/loeschen" class="loeschen">' . csrf_feld()
            . '<button type="submit" class="link rot" data-bestaetigen="Buchung ' . h($b['nummer']) . ' endgültig löschen? (Für Tests und Doppelte. Sonst besser «Abgelehnt» oder «Storniert».)">Buchung löschen</button></form>';
    }

    layout($ich, anzeigename($b), 'buchungen', '<p class="zurueck"><a href="/admin/buchungen" class="link">‹ Buchungen</a></p>'
        . '<div class="titelzeile"><h1>' . h(anzeigename($b)) . '</h1><span>' . quelle_badge($b) . status_badge($b) . '</span></div>'
        . '<p class="unterzeile">' . wt($b['anreise']) . ' ' . dmy($b['anreise']) . ' → ' . wt($b['abreise']) . ' ' . dmy($b['abreise']) . ' · ' . $n . ' Nächte' . ($b['personen'] ? ' · ' . (int)$b['personen'] . ' Personen' : '') . '</p>'
        . ($kontakt ? '<p class="aktionen">' . $kontakt . '</p>' : '')
        . buchung_vorschlaege($b)
        . $kHtml
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '')
        . '<div class="raster breit">'
        . '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '" class="formular karte">' . csrf_feld()
        . $aufenthalt . felder_gast($w)
        . '<fieldset><legend>Notizen</legend><div class="felder">'
        . bereich('Interne Notiz', 'notiz', $w['notiz'] ?? '', 3)
        . bereich('Notiz für die Reinigung (sieht Margrit)', 'notiz_reinigung', $w['notiz_reinigung'] ?? '', 2)
        . ($ih ? auswahl('Sprache für Mails', 'sprache', ['de' => 'Deutsch', 'en' => 'Englisch'], $w['sprache'] ?? 'de') . feld('Ertrag Interhome (CHF)', 'ertrag_interhome', $w['ertrag_interhome'] ?? '', 'text', 'inputmode="decimal"') : '')
        . '</div></fieldset>'
        . '<p class="aktionen"><button type="submit" class="knopf">Speichern</button></p></form>'
        . '<div><section class="karte">' . $info . '</section>' . $gefahr
        . buchung_mails($b)
        . '<section class="karte"><h2>Verlauf</h2><ul class="verlauf">' . ($verlauf ?: '<li class="leer">–</li>') . '</ul></section></div>'
        . '</div>');
}

// ── Sperren ───────────────────────────────────────────────────────────
function seite_sperren(array $ich): void {
    $zeilen = '';
    foreach (q('SELECT * FROM sperre WHERE bis >= ? ORDER BY von', [heute()]) as $s) {
        $letzter = date('Y-m-d', strtotime($s['bis'] . ' -1 day'));
        $zeilen .= '<div class="zeile statisch"><span class="z-datum"><b>' . wt($s['von']) . ' ' . dm($s['von']) . '</b> – ' . wt($letzter) . ' ' . dmy($letzter) . '<small>' . naechte($s['von'], $s['bis']) . ' Nächte</small></span>'
            . '<span class="z-name">' . h($s['grund']) . '</span><span class="z-badges">'
            . ($s['quelle'] === 'admin'
                ? '<form method="post" action="/admin/sperren/' . (int)$s['id'] . '/loeschen">' . csrf_feld() . '<button type="submit" class="link rot" data-bestaetigen="Sperre aufheben?">Aufheben</button></form>'
                : badge('aus Interhome', 'q-interhome'))
            . '</span></div>';
    }
    layout($ich, 'Sperren', 'sperren', '<h1>Sperren</h1>'
        . '<p class="klein">Gesperrte Tage sind auf der Website nicht buchbar. Interhome-Sperren kommen automatisch und lassen sich nur im Interhome-Portal ändern.</p>'
        . '<form method="post" action="/admin/sperren/neu" class="formular karte">' . csrf_feld()
        . '<div class="felder drei">' . feld('Erster gesperrter Tag', 'von', '', 'date', 'required') . feld('Letzter gesperrter Tag', 'bis', '', 'date', 'required')
        . feld('Grund (nur intern)', 'grund', '', 'text', 'placeholder="z. B. Renovation, Familie"') . '</div>'
        . '<p class="klein">Gesperrt werden die Nächte vom ersten bis und mit dem letzten Tag. Bitte die Sperre auch im Interhome-Portal eintragen.</p>'
        . '<p class="aktionen"><button type="submit" class="knopf">Sperren</button></p></form>'
        . '<div class="liste">' . ($zeilen ?: '<p class="leer">Keine Sperren.</p>') . '</div>');
}

// ── Reinigung (Margrit): nur Wechseltage, keine Namen/Kontakte/Preise ──
function seite_reinigung(array $ich): void {
    $von = date('Y-m-d', strtotime('-7 days')); $bis = date('Y-m-d', strtotime('+60 days'));
    $abreisen = q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND abreise BETWEEN ? AND ? AND COALESCE(reinigung, 'yes') != 'no' AND quelle != 'eigentuemer' ORDER BY abreise", [$von, $bis])->fetchAll();
    $karten = '';
    foreach ($abreisen as $b) {
        if ($b['abreise'] < heute() && $b['reinigung_erledigt_am']) continue;   // Erledigtes aus der Vergangenheit ausblenden
        $naechste = q1("SELECT * FROM buchung WHERE status = 'bestaetigt' AND anreise >= ? AND id != ? ORDER BY anreise LIMIT 1", [$b['abreise'], $b['id']]);
        $gleich = $naechste && $naechste['anreise'] === $b['abreise'];
        $erledigt = (bool)$b['reinigung_erledigt_am'];
        $karten .= '<section class="karte reinigung' . ($erledigt ? ' erledigt' : '') . ($gleich ? ' eilig' : '') . '">'
            . '<h2>' . ($b['abreise'] === heute() ? 'Heute, ' : '') . wt($b['abreise']) . ' ' . dmy($b['abreise']) . '</h2>'
            . '<ul>'
            . '<li>Abreise bis ' . zeiten($b)['aus'] . ($b['personen'] ? ' · ' . (int)$b['personen'] . ' Personen waren da' : '') . '</li>'
            . ($naechste
                ? '<li>' . ($gleich ? '<b>Neue Gäste kommen am selben Tag ab ' . zeiten($naechste)['ein'] . '</b>' : 'Nächste Anreise: ' . wt($naechste['anreise']) . ' ' . dmy($naechste['anreise']))
                  . ($naechste['personen'] ? ' · ' . (int)$naechste['personen'] . ' Personen' : '') . '</li>'
                  . ((int)$naechste['bettwaesche'] > 0 ? '<li>Bettwäsche bereitlegen für ' . (int)$naechste['bettwaesche'] . ' Personen</li>' : '')
                : '<li>Danach noch keine Anreise eingetragen</li>')
            . '</ul>'
            . ($b['notiz_reinigung'] ? '<p class="notiz">' . nl2br(h($b['notiz_reinigung'])) . '</p>' : '')
            . '<form method="post" action="/admin/reinigung/' . (int)$b['id'] . '/' . ($erledigt ? 'offen' : 'erledigt') . '">' . csrf_feld()
            . ($erledigt
                ? '<p class="ok">✓ Erledigt am ' . date('d.m. H:i', strtotime($b['reinigung_erledigt_am'])) . ' <button type="submit" class="link">rückgängig</button></p>'
                : '<button type="submit" class="knopf">Reinigung erledigt</button>')
            . '</form></section>';
    }
    layout($ich, 'Reinigung', 'reinigung', '<h1>Reinigung</h1>'
        . '<p class="klein">Wechseltage der nächsten 60 Tage. Grün = erledigt, orange = am selben Tag kommen neue Gäste.'
        . (cfg('ical_feed_token') ? ' <a href="webcal://' . h(parse_url(SITE_URL, PHP_URL_HOST)) . '/admin/ical/reinigung?token=' . h(rawurlencode((string)cfg('ical_feed_token'))) . '">Im Handy-Kalender abonnieren</a>' : '') . '</p>'
        . '<div class="raster">' . ($karten ?: '<p class="leer">Keine Reinigung geplant.</p>') . '</div>'
        . '<form method="post" action="/admin/reinigung/meldung" class="formular karte">' . csrf_feld()
        . '<h2>Etwas melden</h2>' . bereich('Was ist kaputt oder fehlt?', 'text', '', 3, 'required placeholder="z. B. Glühbirne im Bad defekt, Abwaschmittel fast leer"')
        . '<p class="aktionen"><button type="submit" class="knopf">Meldung senden</button></p></form>');
}

// ── Aufgaben (Übersicht) ──────────────────────────────────────────────
/* Liste [Link, Text, Unterzeile], dringendes zuerst */
function aufgaben_liste(): array {
    $heute = heute();
    $a = [];
    $unter = fn(array $b) => anzeigename($b) . ' · ' . dm($b['anreise']) . '–' . dmy($b['abreise']);
    $link = fn(array $b, string $zu = '') => '/admin/buchung/' . (int)$b['id'] . $zu;

    // Code-Mail steht an, kann aber nicht raus
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle != 'eigentuemer' AND ih_fehlt = 0 AND code_gesendet_am IS NULL AND anreise BETWEEN ? AND ?",
               [$heute, date('Y-m-d', strtotime('+' . CODE_TAGE_VORHER . ' days'))]) as $b) {
        if ($h = code_hindernis($b)) $a[] = [$link($b, $b['quelle'] === 'interhome' && !$b['email'] ? '/angaben' : ''), $h, $unter($b)];
        elseif (einst('auto_code') !== '1') $a[] = [$link($b, '/mail/code'), 'Code-Mail senden', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'anfrage' AND abreise >= ? ORDER BY erstellt", [$heute]) as $b) {
        $tage = (int)floor((time() - strtotime($b['erstellt'])) / 86400);
        $a[] = [$link($b), 'Anfrage beantworten' . ($tage > 0 ? " (seit $tage " . ($tage === 1 ? 'Tag' : 'Tagen') . ')' : ' (heute)'), $unter($b)];
    }
    foreach (q("SELECT m.*, b.name, b.nummer FROM mail m LEFT JOIN buchung b ON b.id = m.buchung_id WHERE m.erledigt = 0 AND m.richtung = 'ein' ORDER BY m.datum") as $m) {
        $a[] = ['/admin/mail/' . (int)$m['id'], ($m['kategorie'] === 'gast' ? 'Mail beantworten: ' : 'Mail prüfen: ') . mb_strimwidth((string)$m['betreff'], 0, 60, '…'),
                ($m['von_name'] ?: $m['von']) . ($m['nummer'] ? ' · ' . $m['nummer'] : ' · keiner Buchung zugeordnet')];
    }
    foreach (q("SELECT v.buchung_id, COUNT(*) AS n, b.* FROM vorschlag v JOIN buchung b ON b.id = v.buchung_id WHERE v.status = 'offen' GROUP BY v.buchung_id") as $b) {
        $b['id'] = $b['buchung_id'];
        $a[] = [$link($b), $b['n'] . ' Vorschlag' . ($b['n'] > 1 ? 'e' : '') . ' aus Mails prüfen', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE ih_fehlt = 1 AND status = 'bestaetigt' AND abreise >= ? ORDER BY anreise", [$heute]) as $b) {
        $a[] = [$link($b), 'Fehlt im Interhome-Kalender: storniert?', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle IN ('website','manuell') AND partner_id IS NULL AND abreise >= ? ORDER BY anreise", [$heute]) as $b) {
        if (!$b['ih_eingetragen_am']) $a[] = [$link($b), 'Im Interhome-Portal als Eigentümerbuchung eintragen', $unter($b)];
        elseif (strtotime($b['ih_eingetragen_am']) < time() - 86400) $a[] = [$link($b), 'Eigentümerbuchung fehlt noch im Interhome-Kalender', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE status IN ('storniert','abgelehnt') AND quelle IN ('website','manuell') AND partner_id IS NOT NULL AND abreise >= ?", [$heute]) as $b) {
        $a[] = [$link($b), 'Eigentümerbuchung im Interhome-Portal löschen', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle IN ('website','manuell') AND bezahlt_am IS NULL AND preis > 0 AND abreise >= ? ORDER BY anreise", [$heute]) as $b) {
        $ueber = $b['zahlung_faellig'] && $b['zahlung_faellig'] < $heute;
        $a[] = [$link($b), ($ueber ? 'Zahlung überfällig seit ' . dm($b['zahlung_faellig']) : 'Zahlung offen' . ($b['zahlung_faellig'] ? ' bis ' . dm($b['zahlung_faellig']) : '')) . ' (' . betrag($b['preis']) . ')', $unter($b)];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle = 'interhome' AND anreise BETWEEN ? AND ? ORDER BY anreise", [$heute, date('Y-m-d', strtotime('+7 days'))]) as $b) {
        if ($f = fehlende_angaben($b)) $a[] = [$link($b, '/angaben'), 'Angaben eintragen: ' . implode(', ', $f), $unter($b)];
    }
    return $a;
}

// ── Buchungsdetail: Aktionen, Mails, Vorschläge ─────────────────────────
function buchung_aktionen(array $b): string {
    $id = (int)$b['id'];
    $l = fn(string $zu, string $text, bool $hell = true) => '<a class="knopf' . ($hell ? ' hell' : '') . '" href="/admin/buchung/' . $id . $zu . '">' . h($text) . '</a>';
    $f = fn(string $zu, string $inhalt) => '<form method="post" action="/admin/buchung/' . $id . $zu . '" class="inline">' . csrf_feld() . $inhalt . '</form>';
    $x = [];
    $direkt = ist_direkt($b);
    if ($b['status'] === 'anfrage') {
        $x[] = $l('/mail/bestaetigung', 'Annehmen …', false);
        $x[] = $l('/mail/absage', 'Ablehnen …');
    }
    if ($b['status'] === 'bestaetigt' && $direkt) {
        if (!$b['bezahlt_am'] && (float)$b['preis'] > 0) {
            $x[] = $f('/bezahlt', '<label class="kompakt">Zahlung eingegangen am<input type="date" name="datum" value="' . heute() . '" max="' . heute() . '"></label><button type="submit" class="knopf">Bezahlt</button>');
            $x[] = $l('/mail/erinnerung', 'Zahlungserinnerung …');
        }
        if (!$b['partner_id'] && !$b['ih_eingetragen_am']) $x[] = $f('/ih-eingetragen', '<button type="submit" class="knopf hell">In Interhome eingetragen</button>');
    }
    if ($b['status'] === 'bestaetigt' && $b['quelle'] !== 'eigentuemer') {
        if ($b['quelle'] === 'interhome') $x[] = $l('/angaben', 'Angaben eintragen', !fehlende_angaben($b));
        if ($b['email']) {
            if ($b['quelle'] === 'interhome' && !$b['info_gesendet_am']) $x[] = $l('/mail/info', 'Infos senden …');
            if (!$b['code_gesendet_am']) $x[] = $l('/mail/code', 'Code jetzt senden …');
            if ($direkt && $b['abreise'] <= heute() && !$b['dank_gesendet_am']) $x[] = $l('/mail/dank', 'Dankesmail …');
        }
    }
    if ($b['status'] === 'bestaetigt' && $direkt) $x[] = $l('/mail/storno', 'Stornieren …');
    return $x ? '<section class="karte"><h2>Aktionen</h2><div class="aktionen">' . implode('', $x) . '</div>'
        . ($b['status'] === 'bestaetigt' && $direkt && !$b['partner_id'] ? '<p class="klein">Interhome-Sperre: bitte im Interhome-Portal als Eigentümerbuchung ' . dmy($b['anreise']) . '–' . dmy($b['abreise']) . ' eintragen. Der Abgleich erkennt sie dann von selbst.</p>' : '')
        . '</section>' : '';
}

function mail_status(array $b): string {
    $x = [];
    foreach (['bestaetigt_am' => 'Bestätigung', 'info_gesendet_am' => 'Infos', 'code_gesendet_am' => 'Code', 'zahlung_erinnert_am' => 'Erinnerung', 'dank_gesendet_am' => 'Dank'] as $k => $l) {
        if (!empty($b[$k]) && ($k !== 'bestaetigt_am' || qv("SELECT 1 FROM mail WHERE buchung_id = ? AND richtung = 'aus'", [$b['id']]))) $x[] = $l . ' ' . date('d.m.', strtotime($b[$k]));
    }
    if (!$x && $b['status'] === 'bestaetigt' && !$b['code_gesendet_am'] && $b['quelle'] !== 'eigentuemer') $x[] = 'Code folgt ' . dm(date('Y-m-d', strtotime($b['anreise'] . ' -' . CODE_TAGE_VORHER . ' days')));
    return $x ? h(implode(' · ', $x)) : '–';
}

function buchung_mails(array $b): string {
    $z = '';
    foreach (q('SELECT * FROM mail WHERE buchung_id = ? ORDER BY datum DESC LIMIT 30', [$b['id']]) as $m) {
        $z .= '<li><a href="/admin/mail/' . (int)$m['id'] . '"><time>' . date('d.m. H:i', strtotime((string)$m['datum'])) . '</time> ' . ($m['richtung'] === 'aus' ? '→ ' : '← ') . h(mb_strimwidth((string)$m['betreff'], 0, 70, '…')) . '</a>'
            . (!$m['erledigt'] ? ' ' . badge('offen', 'warn') : '') . '</li>';
    }
    return $z ? '<section class="karte"><h2>Mails</h2><ul class="verlauf">' . $z . '</ul></section>' : '';
}

function buchung_vorschlaege(array $b): string {
    $z = '';
    foreach (q("SELECT v.*, m.betreff, m.datum FROM vorschlag v LEFT JOIN mail m ON m.id = v.mail_id WHERE v.buchung_id = ? AND v.status = 'offen' ORDER BY v.id", [$b['id']]) as $v) {
        $basis = '/admin/buchung/' . (int)$b['id'] . '/vorschlag/' . (int)$v['id'];
        $z .= '<div class="vorschlag"><p><b>' . h(vorschlag_text($v)) . '</b><br><small>aus ' . ($v['mail_id'] ? '<a href="/admin/mail/' . (int)$v['mail_id'] . '">Mail vom ' . date('d.m.', strtotime((string)$v['datum'])) . '</a>' : 'Mail') . '</small></p>'
            . '<form method="post" action="' . $basis . '/uebernehmen" class="inline">' . csrf_feld() . '<button type="submit" class="knopf"' . ($v['feld'] === 'status' ? ' data-bestaetigen="Buchung als storniert markieren? Der Zeitraum wird auf der Website frei."' : '') . '>Übernehmen</button></form> '
            . '<form method="post" action="' . $basis . '/verwerfen" class="inline">' . csrf_feld() . '<button type="submit" class="link">Verwerfen</button></form></div>';
    }
    return $z ? '<section class="warnung"><p><b>Vorschläge aus Mails</b></p>' . $z . '</section>' : '';
}

// ── Mail an den Gast: Vorschau und Senden ───────────────────────────────
function seite_mail(array $ich, array $b, string $art, string $betreff, string $text, string $fehler): void {
    $titel = ['bestaetigung' => 'Anfrage annehmen', 'absage' => 'Anfrage ablehnen', 'storno' => 'Buchung stornieren', 'erinnerung' => 'Zahlungserinnerung',
              'info' => 'Infos senden', 'code' => 'Code senden', 'dank' => 'Dankesmail'][$art] ?? 'Mail senden';
    $knopf = ['bestaetigung' => 'Annehmen und Mail senden', 'absage' => 'Ablehnen und Mail senden', 'storno' => 'Stornieren und Mail senden'][$art] ?? 'Mail senden';
    $ohne = in_array($art, ['bestaetigung', 'absage', 'storno'], true);
    $warn = '';
    if (!$b['email']) $warn .= '<p class="warnung">Für diese Buchung ist keine E-Mail-Adresse eingetragen.' . ($ohne ? ' Es geht nur «ohne Mail».' : '') . '</p>';
    if (str_contains($text, '‹')) $warn .= '<p class="warnung">Im Text fehlt noch etwas (‹…›). Bitte unter <a href="/admin/einstellungen">Einstellungen</a> eintragen und diese Seite neu laden, oder den Text anpassen.</p>';
    if ($art === 'code' && ist_direkt($b) && !$b['bezahlt_am'] && (float)$b['preis'] > 0) $warn .= '<p class="warnung">Achtung: Die Buchung ist noch nicht als bezahlt markiert.</p>';
    if ($art === 'bestaetigung' && ($b['nachricht'] ?? '')) $warn .= '<div class="nachricht"><b>Nachricht des Gastes</b><p>' . nl2br(h($b['nachricht'])) . '</p></div>';
    layout($ich, $titel, 'buchungen', '<p class="zurueck"><a href="/admin/buchung/' . (int)$b['id'] . '" class="link">‹ ' . h(anzeigename($b)) . '</a></p>'
        . '<h1>' . h($titel) . '</h1>'
        . '<p class="unterzeile">' . h(anzeigename($b)) . ' · ' . dmy($b['anreise']) . ' – ' . dmy($b['abreise']) . ($b['email'] ? ' · an ' . h($b['email']) : '') . ' · ' . (en($b) ? 'Englisch' : 'Deutsch') . '</p>'
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '') . $warn
        . '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '/mail/' . h($art) . '" class="formular karte">' . csrf_feld()
        . '<div class="felder eins">' . feld('Betreff', 'betreff', $betreff, 'text', 'required') . bereich('Text (wird als gestaltete Mail verschickt)', 'text', $text, 22, 'required') . '</div>'
        . ($ohne ? '<label class="haken"><input type="checkbox" name="ohne_mail" value="1"' . (!$b['email'] ? ' checked' : '') . '> Ohne Mail an den Gast (nur Status ändern)</label>' : '')
        . '<p class="aktionen"><button type="submit" class="knopf">' . h($knopf) . '</button> <a class="link" href="/admin/buchung/' . (int)$b['id'] . '">Abbrechen</a></p></form>');
}

// ── Schnellformular Interhome-Angaben ───────────────────────────────────
function seite_angaben(array $ich, array $b, string $fehler, ?array $w): void {
    $w = $w ?? $b;
    layout($ich, 'Angaben eintragen', 'buchungen', '<p class="zurueck"><a href="/admin/buchung/' . (int)$b['id'] . '" class="link">‹ Buchung</a></p>'
        . '<h1>Angaben eintragen</h1><p class="unterzeile">' . h(anzeigename($b)) . ' · ' . wt($b['anreise']) . ' ' . dmy($b['anreise']) . ' – ' . dmy($b['abreise']) . '</p>'
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '')
        . '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '/angaben" class="formular karte">' . csrf_feld() . '<div class="felder">'
        . feld('Name', 'name', $w['name'] ?? '', 'text', 'autocomplete="off"')
        . feld('Personen', 'personen', $w['personen'] ?? '', 'number', 'min="1" max="4" inputmode="numeric"')
        . feld('davon Erwachsene', 'erwachsene', $w['erwachsene'] ?? '', 'number', 'min="0" max="4" inputmode="numeric"')
        . feld('davon Kinder', 'kinder', $w['kinder'] ?? '', 'number', 'min="0" max="4" inputmode="numeric"')
        . feld('E-Mail', 'email', $w['email'] ?? '', 'email', 'autocomplete="off"')
        . feld('Telefon', 'telefon', $w['telefon'] ?? '', 'tel', 'autocomplete="off"')
        . feld('Anreisezeit', 'anreisezeit', $w['anreisezeit'] ?? '', 'text', 'placeholder="z. B. ca. 17 Uhr"')
        . auswahl('Sprache für Mails', 'sprache', ['de' => 'Deutsch', 'en' => 'Englisch'], $w['sprache'] ?? 'de')
        . '</div>'
        . (einst('auto_info_ih') === '1' && !$b['info_gesendet_am'] ? '<p class="klein">Sobald eine E-Mail eingetragen ist, gehen die Infos zum Aufenthalt automatisch an den Gast, der Code 3 Tage vor Anreise.</p>' : '')
        . '<p class="aktionen"><button type="submit" class="knopf">Speichern</button></p></form>');
}

// ── Posteingang ─────────────────────────────────────────────────────────
function seite_posteingang(array $ich): void {
    $filter = ($_GET['f'] ?? 'offen') === 'alle' ? 'alle' : 'offen';
    $liste = q("SELECT m.*, b.nummer, b.name FROM mail m LEFT JOIN buchung b ON b.id = m.buchung_id WHERE " . ($filter === 'offen' ? "m.erledigt = 0 AND m.richtung = 'ein'" : '1') . ' ORDER BY m.datum DESC LIMIT 200')->fetchAll();
    $z = '';
    foreach ($liste as $m) {
        $e = json_decode((string)$m['ergebnis'], true) ?: [];
        $z .= '<a class="zeile" href="/admin/mail/' . (int)$m['id'] . '"><span class="z-datum"><b>' . date('d.m. H:i', strtotime((string)$m['datum'])) . '</b><small>' . ($m['richtung'] === 'aus' ? '→ ' . h($m['an']) : h($m['von_name'] ?: $m['von'])) . '</small></span>'
            . '<span class="z-name">' . h(mb_strimwidth((string)$m['betreff'], 0, 90, '…')) . '<small>' . h($e['ki']['zusammenfassung'] ?? '') . '</small></span>'
            . '<span class="z-badges">' . badge(MAIL_KATEGORIE[$m['kategorie']] ?? (string)$m['kategorie'], str_starts_with((string)$m['kategorie'], 'interhome') ? 'q-interhome' : ($m['kategorie'] === 'gast' ? 'q-website' : 'leer'))
            . ($m['nummer'] ? badge((string)$m['nummer'], 'leer') : '') . (!$m['erledigt'] ? badge('offen', 'warn') : '') . '</span></a>';
    }
    layout($ich, 'Posteingang', 'posteingang', '<div class="titelzeile"><h1>Posteingang</h1>'
        . '<form method="post" action="/admin/postfach">' . csrf_feld() . '<button type="submit" class="knopf hell">Jetzt abrufen</button></form></div>'
        . '<p class="klein">Mails an ' . INFO_MAIL . ' werden alle 10 Minuten gelesen (nur lesend, im Postfach ändert sich nichts), der passenden Buchung zugeordnet und ausgewertet.'
        . (ki_verfuegbar() ? '' : ' <b>Ohne API-Schlüssel nur mit einfachen Regeln.</b>') . '</p>'
        . '<nav class="tabs"><a href="/admin/posteingang"' . ($filter === 'offen' ? ' aria-current="page"' : '') . '>Offen</a><a href="/admin/posteingang?f=alle"' . ($filter === 'alle' ? ' aria-current="page"' : '') . '>Alle</a></nav>'
        . '<div class="liste">' . ($z ?: '<p class="leer">' . ($filter === 'offen' ? 'Nichts offen.' : 'Noch keine Mails.') . '</p>') . '</div>');
}

function seite_mail_detail(array $ich, array $m): void {
    $e = json_decode((string)$m['ergebnis'], true) ?: [];
    $ki = $e['ki'] ?? null;
    $b = $m['buchung_id'] ? q1('SELECT * FROM buchung WHERE id = ?', [$m['buchung_id']]) : null;
    $mid = (int)$m['id'];
    $info = '<dl class="infos">'
        . '<div><dt>' . ($m['richtung'] === 'aus' ? 'An' : 'Von') . '</dt><dd>' . h($m['richtung'] === 'aus' ? $m['an'] : trim(($m['von_name'] ? $m['von_name'] . ' ' : '') . '<' . $m['von'] . '>')) . '</dd></div>'
        . '<div><dt>Datum</dt><dd>' . date('d.m.Y H:i', strtotime((string)$m['datum'])) . '</dd></div>'
        . '<div><dt>Kategorie</dt><dd>' . h(MAIL_KATEGORIE[$m['kategorie']] ?? $m['kategorie']) . ($m['verarbeitet_mit'] ? ' <small>(' . ($m['verarbeitet_mit'] === 'ki' ? 'KI' : 'Regeln') . ')</small>' : '') . '</dd></div>'
        . '<div><dt>Buchung</dt><dd>' . ($b ? '<a href="/admin/buchung/' . (int)$b['id'] . '">' . h($b['nummer'] . ' · ' . anzeigename($b)) . '</a>' : '–') . '</dd></div>'
        . '</dl>'
        . ($ki ? '<p><b>Zusammenfassung:</b> ' . h($ki['zusammenfassung']) . '</p>' : '')
        . (!empty($e['uebernommen']) ? '<p class="ok">Übernommen: ' . h(implode(' · ', $e['uebernommen'])) . '</p>' : '')
        . (!empty($e['vorschlaege']) && $b ? '<p class="warnung">' . (int)$e['vorschlaege'] . ' Vorschlag/Vorschläge bei der <a href="/admin/buchung/' . (int)$b['id'] . '">Buchung</a> prüfen.</p>' : '');
    $zuordnen = $m['richtung'] === 'ein' ? '<form method="post" action="/admin/mail/' . $mid . '/zuordnen" class="formular zuordnen">' . csrf_feld()
        . feld('Andere Buchung (Nummer, z. B. BAM-1004 oder IH-…)', 'nummer', $b['nummer'] ?? '', 'text', 'autocomplete="off"') . '<button type="submit" class="knopf hell">Zuordnen</button></form>' : '';
    $antwort = '';
    if ($m['richtung'] === 'ein' && $m['von'] && !in_array($m['kategorie'], ['intern', 'formular'], true) && !str_contains((string)$m['von'], 'interhome')) {
        $betreff = preg_match('/^(re|aw):/i', (string)$m['betreff']) ? (string)$m['betreff'] : 'Re: ' . $m['betreff'];
        $antwort = '<form method="post" action="/admin/mail/' . $mid . '/antworten" class="formular karte">' . csrf_feld() . '<h2>Antworten</h2><div class="felder eins">'
            . feld('Betreff', 'betreff', $betreff, 'text', 'required')
            . bereich('Text' . ($ki && $ki['antwortentwurf'] ? ' (Entwurf der KI, bitte prüfen)' : ''), 'text', (string)($ki['antwortentwurf'] ?? ''), 12, 'required') . '</div>'
            . '<p class="aktionen"><button type="submit" class="knopf">Antwort senden</button></p></form>';
    }
    $status = '<form method="post" action="/admin/mail/' . $mid . '/' . ($m['erledigt'] ? 'offen' : 'erledigt') . '" class="inline">' . csrf_feld()
        . '<button type="submit" class="knopf' . ($m['erledigt'] ? ' hell' : '') . '">' . ($m['erledigt'] ? 'Wieder offen' : 'Erledigt') . '</button></form>';
    layout($ich, (string)$m['betreff'], 'posteingang', '<p class="zurueck"><a href="/admin/posteingang" class="link">‹ Posteingang</a></p>'
        . '<div class="titelzeile"><h1>' . h($m['betreff'] ?: '(ohne Betreff)') . '</h1>' . ($m['richtung'] === 'ein' ? $status : '') . '</div>'
        . '<div class="raster breit"><div><section class="karte"><pre class="mailtext">' . h($m['kurztext'] ?? '') . '</pre></section>' . $antwort . '</div>'
        . '<div><section class="karte">' . $info . $zuordnen . '</section></div></div>');
}

// ── Einstellungen ───────────────────────────────────────────────────────
function seite_einstellungen(array $ich): void {
    $felder = '';
    foreach (EINSTELLUNGEN as $k => [$label]) {
        $felder .= str_starts_with($k, 'auto_')
            ? '<label class="haken"><input type="checkbox" name="' . $k . '" value="1"' . (einst($k) === '1' ? ' checked' : '') . '> ' . h($label) . '</label>'
            : feld($label, $k, einst($k), $k === 'zahlungsfrist' ? 'number' : 'text', ($k === 'zahlungsfrist' ? 'min="0" max="120"' : 'autocomplete="off"') . ($k === 'tuercode' ? ' inputmode="numeric"' : ''));
    }
    $t = (string)cfg('ical_feed_token');
    $host = parse_url(SITE_URL, PHP_URL_HOST);
    $abo = $t ? '<p>Alle Buchungen (für euren Kalender): <code class="kopier">webcal://' . h($host) . '/admin/ical/alle?token=' . h(hash_hmac('sha256', 'alle', $t)) . '</code></p>'
        . '<p>Wechseltage für Margrit (ohne Namen): <code class="kopier">webcal://' . h($host) . '/admin/ical/reinigung?token=' . h(rawurlencode($t)) . '</code></p>' : '<p class="leer">Kein Token konfiguriert.</p>';
    $stand = '<ul class="verlauf">'
        . '<li>Automatik (Cron): ' . (($z = (int)(meta_get('automatik_zuletzt') ?? 0)) ? 'zuletzt ' . date('d.m. H:i', $z) . ($z < time() - 3600 ? ' <b class="fehler">– läuft der Cron?</b>' : '') : 'noch nie') . '</li>'
        . '<li>Postfach: ' . (($p = meta_get('postfach_ok')) ? 'ok ' . date('d.m. H:i', strtotime($p)) : 'noch nie') . (($f = meta_get('postfach_fehler')) ? ' · <b class="fehler">' . h($f) . '</b>' : '') . '</li>'
        . '<li>KI-Auswertung: ' . (ki_verfuegbar() ? 'aktiv (' . KI_MODELL . ')' : 'kein API-Schlüssel') . (($f = meta_get('ki_fehler')) ? ' · <b class="fehler">' . h($f) . '</b>' : '') . '</li>'
        . '<li>Interhome-Kalender: ' . (($s = meta_get('sync_ok')) ? 'ok ' . date('d.m. H:i', strtotime($s)) : 'noch nie') . '</li>'
        . '</ul>';
    layout($ich, 'Einstellungen', 'einstellungen', '<h1>Einstellungen</h1>'
        . '<form method="post" action="/admin/einstellungen" class="formular karte">' . csrf_feld()
        . '<p class="klein">Code, Kontoangaben und Zahlungsfrist erscheinen in den Mails an die Gäste. Sie liegen in der Datenbank, nicht im Code.</p>'
        . '<div class="felder">' . $felder . '</div>'
        . '<p class="aktionen"><button type="submit" class="knopf">Speichern</button></p></form>'
        . '<div class="raster"><section class="karte"><h2>Kalender-Abo</h2>' . $abo . '<p class="klein">Im iPhone: Einstellungen → Kalender → Accounts → Kalenderabo hinzufügen, Adresse einfügen.</p></section>'
        . '<section class="karte"><h2>Stand</h2>' . $stand . '</section></div>');
}

// ── Geld ────────────────────────────────────────────────────────────────
function seite_geld(array $ich): void {
    $jahr = preg_match('/^\d{4}$/', (string)($_GET['j'] ?? '')) ? (int)$_GET['j'] : (int)date('Y');
    $monate = array_fill(1, 12, ['direkt' => 0.0, 'bezahlt' => 0.0, 'ih' => 0.0, 'n_direkt' => 0, 'n_ih' => 0, 'n_eig' => 0]);
    $summe = ['direkt' => 0.0, 'bezahlt' => 0.0, 'ih' => 0.0, 'ih_ohne' => 0];
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND anreise BETWEEN ? AND ?", ["$jahr-01-01", "$jahr-12-31"]) as $b) {
        $mo = (int)substr($b['anreise'], 5, 2);
        $n = naechte($b['anreise'], $b['abreise']);
        if (ist_direkt($b)) {
            $monate[$mo]['direkt'] += (float)$b['preis']; $summe['direkt'] += (float)$b['preis']; $monate[$mo]['n_direkt'] += $n;
            if ($b['bezahlt_am']) { $monate[$mo]['bezahlt'] += (float)$b['preis']; $summe['bezahlt'] += (float)$b['preis']; }
        } elseif ($b['quelle'] === 'interhome') {
            $monate[$mo]['ih'] += (float)$b['ertrag_interhome']; $summe['ih'] += (float)$b['ertrag_interhome']; $monate[$mo]['n_ih'] += $n;
            if ($b['ertrag_interhome'] === null) $summe['ih_ohne']++;
        } else $monate[$mo]['n_eig'] += $n;
    }
    $zeilen = '';
    foreach ($monate as $mo => $w) {
        $zeilen .= '<tr><th scope="row">' . MONATE[$mo] . '</th><td>' . $w['n_direkt'] . '</td><td>' . $w['n_ih'] . '</td><td>' . $w['n_eig'] . '</td><td>' . ($w['direkt'] ? betrag($w['direkt']) : '–') . '</td><td>' . ($w['ih'] ? betrag($w['ih']) : '–') . '</td></tr>';
    }
    $offen = '';
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle IN ('website','manuell') AND bezahlt_am IS NULL AND preis > 0 ORDER BY COALESCE(zahlung_faellig, anreise)") as $b) {
        $offen .= '<a class="zeile" href="/admin/buchung/' . (int)$b['id'] . '"><span class="z-datum"><b>' . ($b['zahlung_faellig'] ? 'fällig ' . dmy($b['zahlung_faellig']) : 'ohne Frist') . '</b><small>Anreise ' . dmy($b['anreise']) . '</small></span>'
            . '<span class="z-name">' . h(anzeigename($b)) . '<small>' . h($b['nummer']) . '</small></span><span class="z-badges"><b>' . betrag($b['preis']) . '</b></span></a>';
    }
    layout($ich, 'Geld', 'geld', '<div class="titelzeile"><h1>Geld ' . $jahr . '</h1><div class="monatsnav"><a class="knopf hell" href="/admin/geld?j=' . ($jahr - 1) . '" aria-label="Vorjahr">‹</a><a class="knopf hell" href="/admin/geld?j=' . ($jahr + 1) . '" aria-label="Nächstes Jahr">›</a></div></div>'
        . '<section class="karte"><div class="kennzahlen">'
        . '<div><b>' . betrag($summe['direkt']) . '</b><span>Direktbuchungen (bestätigt)</span></div>'
        . '<div><b>' . betrag($summe['bezahlt']) . '</b><span>davon bezahlt</span></div>'
        . '<div><b>' . betrag($summe['direkt'] - $summe['bezahlt']) . '</b><span>offen</span></div>'
        . '<div><b>' . betrag($summe['ih']) . '</b><span>Ertrag Interhome' . ($summe['ih_ohne'] ? ' (' . $summe['ih_ohne'] . ' Buchungen ohne Betrag)' : '') . '</span></div>'
        . '<div><b>' . betrag($summe['direkt'] + $summe['ih']) . '</b><span>Total</span></div>'
        . '</div><p class="klein">Gezählt nach Anreisedatum. Der Interhome-Ertrag kommt aus den Interhome-Mails (Buchung, Abrechnung) oder wird bei der Buchung von Hand eingetragen.</p></section>'
        . '<section class="karte"><h2>Offene Zahlungen</h2><div class="liste">' . ($offen ?: '<p class="leer">Keine.</p>') . '</div></section>'
        . '<section class="karte"><h2>Pro Monat</h2><div class="tabelle"><table><thead><tr><th>Monat</th><th>Nächte direkt</th><th>Nächte Interhome</th><th>Nächte Eigentümer</th><th>Direkt CHF</th><th>Interhome CHF</th></tr></thead><tbody>' . $zeilen . '</tbody></table></div></section>');
}
