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
            : ['' => 'Übersicht', 'buchungen' => 'Buchungen', 'kalender' => 'Kalender', 'sperren' => 'Sperren', 'reinigung' => 'Reinigung'];
        foreach ($punkte as $p => $l) $nav .= '<a href="/admin' . ($p ? "/$p" : '') . '"' . ($p === $aktiv ? ' aria-current="page"' : '') . '>' . $l . '</a>';
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

/* Check-in/-out: direkt 15:00/12:00 (wie im Guide); Interhome-Inserat heute 16:00/10:00 */
function zeiten(array $b): array { return $b['quelle'] === 'interhome' ? ['ein' => '16:00', 'aus' => '10:00'] : ['ein' => '15:00', 'aus' => '12:00']; }

function badge(string $text, string $art): string { return '<span class="badge badge-' . h($art) . '">' . h($text) . '</span>'; }
function quelle_badge(array $b): string { return badge(QUELLE[$b['quelle']] ?? $b['quelle'], 'q-' . $b['quelle']); }
function status_badge(array $b): string {
    if ($b['ih_fehlt'] && $b['status'] === 'bestaetigt') return badge('Storniert?', 'warn');
    return badge(STATUS[$b['status']] ?? $b['status'], 's-' . $b['status']);
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
    $aufgaben = [];
    foreach (q("SELECT * FROM buchung WHERE status = 'anfrage' AND abreise >= ? ORDER BY erstellt", [$heute]) as $b) {
        $tage = (int)floor((time() - strtotime($b['erstellt'])) / 86400);
        $aufgaben[] = [$b, 'Anfrage beantworten' . ($tage > 0 ? " (seit $tage " . ($tage === 1 ? 'Tag' : 'Tagen') . ')' : ' (heute)')];
    }
    foreach (q("SELECT * FROM buchung WHERE ih_fehlt = 1 AND status = 'bestaetigt' AND abreise >= ? ORDER BY anreise", [$heute]) as $b) {
        $aufgaben[] = [$b, 'Fehlt im Interhome-Kalender: storniert?'];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle = 'interhome' AND anreise BETWEEN ? AND ? ORDER BY anreise", [$heute, $in7]) as $b) {
        if ($f = fehlende_angaben($b)) $aufgaben[] = [$b, 'Angaben eintragen: ' . implode(', ', $f)];
    }
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND quelle IN ('website','manuell') AND bezahlt_am IS NULL AND preis > 0 AND abreise >= ? ORDER BY anreise", [$heute]) as $b) {
        $aufgaben[] = [$b, 'Zahlung offen (' . betrag($b['preis']) . ')'];
    }
    $aHtml = '';
    foreach ($aufgaben as [$b, $text]) {
        $aHtml .= '<a class="aufgabe" href="/admin/buchung/' . (int)$b['id'] . '"><b>' . h($text) . '</b><span>' . h(anzeigename($b)) . ' · ' . dm($b['anreise']) . '–' . dmy($b['abreise']) . '</span></a>';
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
    $einnahmen = 0.0;
    foreach (q("SELECT * FROM buchung WHERE status = 'bestaetigt' AND anreise <= ? AND abreise >= ?", ["$jahr-12-31", "$jahr-01-01"]) as $b) {
        $von = max($b['anreise'], "$jahr-01-01"); $bis = min($b['abreise'], ($jahr + 1) . '-01-01');
        $n = max(0, naechte($von, $bis));
        $k = $b['quelle'] === 'interhome' ? 'interhome' : ($b['quelle'] === 'eigentuemer' ? 'eigentuemer' : 'direkt');
        $naechteNach[$k] += $n;
        if ($k === 'direkt' && substr($b['anreise'], 0, 4) === $jahr) $einnahmen += (float)$b['preis'];
    }
    $tageImJahr = (int)date('z', mktime(0, 0, 0, 12, 31, (int)$jahr)) + 1;
    $belegt = array_sum($naechteNach);

    // Sync
    $ok = meta_get('sync_ok'); $fehler = meta_get('sync_fehler');
    $sync = '<form method="post" action="/admin/sync" class="sync">' . csrf_feld() . '<input type="hidden" name="zurueck" value="">'
        . '<span>Interhome-Kalender: ' . ($ok ? 'abgeglichen ' . date('d.m. H:i', strtotime($ok)) : 'noch nie abgeglichen') . ($fehler ? ' · <b class="fehler">' . h($fehler) . '</b>' : '') . '</span>'
        . '<button type="submit" class="link">Jetzt abgleichen</button></form>';

    layout($ich, 'Übersicht', '', '<h1>Übersicht</h1>'
        . '<div class="raster">'
        . '<section class="karte"><h2>Zu erledigen <span class="zahl">' . count($aufgaben) . '</span></h2>' . ($aHtml ?: '<p class="leer">Nichts offen.</p>') . '</section>'
        . '<section class="karte"><h2>Nächste 30 Tage</h2>' . ($eHtml ?: '<p class="leer">Keine An- oder Abreisen.</p>') . '</section>'
        . '</div>'
        . '<section class="karte"><h2>' . $jahr . '</h2><div class="kennzahlen">'
        . '<div><b>' . $belegt . '</b><span>belegte Nächte (' . round($belegt / $tageImJahr * 100) . ' %)</span></div>'
        . '<div><b>' . $naechteNach['interhome'] . '</b><span>Nächte Interhome</span></div>'
        . '<div><b>' . $naechteNach['direkt'] . '</b><span>Nächte direkt</span></div>'
        . '<div><b>' . $naechteNach['eigentuemer'] . '</b><span>Nächte Eigentümer</span></div>'
        . '<div><b>' . betrag($einnahmen) . '</b><span>Einnahmen direkt</span></div>'
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
    foreach (q("SELECT * FROM buchung WHERE status IN ('anfrage','bestaetigt') AND anreise < ? AND abreise > ?", [$ende, date('Y-m-d', $start)]) as $b) {
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

    $gefahr = '';
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
        . $kHtml
        . ($fehler ? '<p class="fehler" role="alert">' . h($fehler) . '</p>' : '')
        . '<div class="raster breit">'
        . '<form method="post" action="/admin/buchung/' . (int)$b['id'] . '" class="formular karte">' . csrf_feld()
        . $aufenthalt . felder_gast($w)
        . '<fieldset><legend>Notizen</legend><div class="felder">'
        . bereich('Interne Notiz', 'notiz', $w['notiz'] ?? '', 3)
        . bereich('Notiz für die Reinigung (sieht Margrit)', 'notiz_reinigung', $w['notiz_reinigung'] ?? '', 2)
        . ($ih ? feld('Bezahlt am', 'bezahlt_am', $w['bezahlt_am'] ?? '', 'date') : '')
        . '</div></fieldset>'
        . '<p class="aktionen"><button type="submit" class="knopf">Speichern</button></p></form>'
        . '<div><section class="karte">' . $info . '</section>' . $gefahr
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
        . '<p class="klein">Wechseltage der nächsten 60 Tage. Grün = erledigt, orange = am selben Tag kommen neue Gäste.</p>'
        . '<div class="raster">' . ($karten ?: '<p class="leer">Keine Reinigung geplant.</p>') . '</div>'
        . '<form method="post" action="/admin/reinigung/meldung" class="formular karte">' . csrf_feld()
        . '<h2>Etwas melden</h2>' . bereich('Was ist kaputt oder fehlt?', 'text', '', 3, 'required placeholder="z. B. Glühbirne im Bad defekt, Abwaschmittel fast leer"')
        . '<p class="aktionen"><button type="submit" class="knopf">Meldung senden</button></p></form>');
}
