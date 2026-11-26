<?php
/* Mails an Gäste: Vorlagen (DE/EN), HTML im Stil der Website, Versand per mail() über Hostpoint.
   Jede gesendete Mail steht in der Tabelle mail (richtung «aus») und im Verlauf der Buchung.
   Eine Kopie geht per Bcc an info@, damit sie im Postfach sichtbar ist (die Auswertung überspringt sie). */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const MAIL_ARTEN = [
    'bestaetigung' => 'Buchungsbestätigung',
    'absage'       => 'Absage',
    'erinnerung'   => 'Zahlungserinnerung',
    'storno'       => 'Stornobestätigung',
    'info'         => 'Infos zum Aufenthalt',
    'code'         => 'Code Schlüsselsafe',
    'dank'         => 'Dank nach Abreise',
    'antwort'      => 'Antwort',
];

function en(array $b): bool { return ($b['sprache'] ?? 'de') === 'en'; }
function guide_url(array $b): string { return SITE_URL . (en($b) ? '/en/stay-guide' : '/aufenthalts-guide'); }
function luecke(string $was): string { return '‹' . $was . ' fehlt – unter Einstellungen eintragen›'; }

/* Fälligkeit der Vorauszahlung: Zahlungsfrist vor Anreise, bei kurzfristiger Buchung in 5 Tagen (spätestens 4 Tage vor Anreise). */
function zahlung_faellig_berechnen(array $b): string {
    $frist = max(0, (int)einst('zahlungsfrist'));
    $d = date('Y-m-d', strtotime($b['anreise'] . " -$frist days"));
    $bald = date('Y-m-d', strtotime('+5 days'));
    if ($d < $bald) $d = min($bald, date('Y-m-d', strtotime($b['anreise'] . ' -4 days')));
    return max($d, heute());
}

function anrede(array $b): string {
    $n = trim((string)($b['name'] ?? ''));
    return en($b) ? ($n ? "Hello {$n}" : 'Hello') : ($n ? "Guten Tag {$n}" : 'Guten Tag');
}
function gruss(array $b): string { return en($b) ? "Kind regards\nFamilie Staub\nChalet Bambi" : "Herzliche Grüsse\nFamilie Staub\nChalet Bambi"; }

function zahlungsblock(array $b, string $faellig): string {
    $inhaber = einst('kontoinhaber') ?: luecke('Kontoinhaber');
    $iban = einst('iban') ?: luecke('IBAN');
    $bank = einst('bank');
    $sofort = $faellig <= heute();
    return en($b)
        ? "Payment\nPlease transfer the full amount of " . betrag($b['preis']) . ($sofort ? ' promptly' : ' by ' . dmy($faellig)) . ":\nAccount holder: {$inhaber}\nIBAN: {$iban}" . ($bank ? "\nBank: {$bank}" : '') . "\nReference: {$b['nummer']}"
        : "Zahlung\nBitte überweisen Sie den ganzen Betrag von " . betrag($b['preis']) . ($sofort ? ' umgehend' : ' bis ' . dmy($faellig)) . ":\nKontoinhaber: {$inhaber}\nIBAN: {$iban}" . ($bank ? "\nBank: {$bank}" : '') . "\nVermerk: {$b['nummer']}";
}

function infoblock(array $b): string {
    $z = zeiten($b);
    $tax = (require APP_ROOT . '/config/pricing.php')['tourist_tax'] ?? ['adult' => 4, 'from_age' => 16];
    return en($b)
        ? "Arrival and departure\nCheck-in from {$z['ein']}, check-out by {$z['aus']}.\nChalet Bambi, Rothornstrasse 40, 6174 Sörenberg. Two free parking spaces right in front of the chalet.\nThe key is in the key safe at the entrance. We will send you the code by email 3 days before arrival" . (ist_direkt($b) ? ', once your payment has arrived' : '') . ".\n\nGood to know\nThere is no Wi-Fi in the chalet. Bags for general waste need waste stickers, available at Volg Sörenberg.\nTourist tax (not included): CHF {$tax['adult']}.– per person aged {$tax['from_age']}+ per night (half in the off-season), payable at the Sörenberg Tourist Office, Rothornstrasse 21.\n\nEverything about your stay (arrival, house rules, check-out list): " . guide_url($b)
        : "An- und Abreise\nCheck-in ab {$z['ein']} Uhr, Check-out bis {$z['aus']} Uhr.\nChalet Bambi, Rothornstrasse 40, 6174 Sörenberg. Zwei kostenlose Parkplätze direkt vor dem Chalet.\nDer Schlüssel liegt im Schlüsselsafe am Eingang. Den Code schicken wir Ihnen 3 Tage vor der Anreise per E-Mail" . (ist_direkt($b) ? ', sobald Ihre Zahlung eingegangen ist' : '') . ".\n\nGut zu wissen\nIm Chalet gibt es kein WLAN. Für Restabfall braucht es Abfallmarken, erhältlich im Volg Sörenberg.\nKurtaxe (nicht im Preis): CHF {$tax['adult']}.– pro Person ab {$tax['from_age']} Jahren und Nacht (Zwischensaison die Hälfte), zahlbar beim Tourismus-Büro Sörenberg, Rothornstrasse 21.\n\nAlles zum Aufenthalt (Anreise, Hausregeln, Checkliste für die Abreise): " . guide_url($b);
}

function aufenthalt_zeile(array $b): string {
    $n = naechte($b['anreise'], $b['abreise']);
    return en($b)
        ? dmy($b['anreise']) . ' to ' . dmy($b['abreise']) . " ({$n} nights" . ($b['personen'] ? ", {$b['personen']} guests" : '') . ')'
        : dmy($b['anreise']) . ' bis ' . dmy($b['abreise']) . " ({$n} Nächte" . ($b['personen'] ? ", {$b['personen']} Personen" : '') . ')';
}

/* Vorlage → [Betreff, Text]. Der Text ist schlicht; HTML entsteht daraus beim Senden. */
function vorlage(string $art, array $b, array $extra = []): array {
    $e = en($b);
    $nr = $b['nummer'] ? " ({$b['nummer']})" : '';
    $faellig = $b['zahlung_faellig'] ?: zahlung_faellig_berechnen($b);
    $zeile = aufenthalt_zeile($b);
    switch ($art) {
        case 'bestaetigung':
            return [
                ($e ? 'Booking confirmation Chalet Bambi' : 'Buchungsbestätigung Chalet Bambi') . $nr,
                anrede($b) . "\n\n" . ($e
                    ? "We are delighted to confirm your stay at Chalet Bambi.\n\nStay: {$zeile}\nTotal: " . betrag($b['preis'])
                    : "Gerne bestätigen wir Ihren Aufenthalt im Chalet Bambi.\n\nAufenthalt: {$zeile}\nTotal: " . betrag($b['preis']))
                . "\n\n" . zahlungsblock($b, $faellig) . "\n\n" . infoblock($b) . "\n\n"
                . ($e ? "If you have any questions, simply reply to this email. We look forward to welcoming you!" : "Bei Fragen antworten Sie einfach auf diese E-Mail. Wir freuen uns auf Sie!")
                . "\n\n" . gruss($b),
            ];
        case 'absage':
            return [
                ($e ? 'Your enquiry for Chalet Bambi' : 'Ihre Anfrage für das Chalet Bambi') . $nr,
                anrede($b) . "\n\n" . ($e
                    ? "Thank you very much for your enquiry for {$zeile}. Unfortunately the chalet is not available for these dates.\n\nYou can see the free dates at " . SITE_URL . "/en/buchen – we would be happy to welcome you another time."
                    : "Vielen Dank für Ihre Anfrage für {$zeile}. Leider ist das Chalet in dieser Zeit nicht frei.\n\nDie freien Daten sehen Sie unter " . SITE_URL . "/buchen – gerne begrüssen wir Sie ein anderes Mal.")
                . "\n\n" . gruss($b),
            ];
        case 'erinnerung':
            return [
                ($e ? 'Payment reminder Chalet Bambi' : 'Zahlungserinnerung Chalet Bambi') . $nr,
                anrede($b) . "\n\n" . ($e
                    ? "We have not yet received your payment for your stay ({$zeile}). If you have already paid, please disregard this email."
                    : "Für Ihren Aufenthalt ({$zeile}) ist bei uns noch keine Zahlung eingegangen. Falls Sie bereits bezahlt haben, betrachten Sie diese E-Mail bitte als gegenstandslos.")
                . "\n\n" . zahlungsblock($b, $faellig) . "\n\n" . gruss($b),
            ];
        case 'storno':
            return [
                ($e ? 'Cancellation Chalet Bambi' : 'Stornierung Chalet Bambi') . $nr,
                anrede($b) . "\n\n" . ($e
                    ? "We confirm the cancellation of your stay ({$zeile})." . ($b['bezahlt_am'] ? ' We will be in touch about the refund.' : '')
                    : "Wir bestätigen die Stornierung Ihres Aufenthalts ({$zeile})." . ($b['bezahlt_am'] ? ' Wegen der Rückzahlung melden wir uns bei Ihnen.' : ''))
                . "\n\n" . gruss($b),
            ];
        case 'info':
            return [
                ($e ? 'Your stay at Chalet Bambi' : 'Ihr Aufenthalt im Chalet Bambi') . ' ' . dmy($b['anreise']),
                anrede($b) . "\n\n" . ($e
                    ? "Thank you for booking Chalet Bambi. We are the owners and would like to give you some information for your stay ({$zeile})."
                    : "Vielen Dank für Ihre Buchung des Chalet Bambi. Wir sind die Eigentümer und geben Ihnen gerne ein paar Infos für Ihren Aufenthalt ({$zeile}).")
                . "\n\n" . infoblock($b) . "\n\n"
                . ($e ? "If you have any questions, simply reply to this email." : "Bei Fragen antworten Sie einfach auf diese E-Mail.")
                . "\n\n" . gruss($b),
            ];
        case 'code':
            $code = einst('tuercode') ?: luecke('Türcode');
            $z = zeiten($b);
            return [
                ($e ? 'Key safe code for Chalet Bambi' : 'Code für den Schlüsselsafe – Chalet Bambi') . $nr,
                anrede($b) . "\n\n" . ($e
                    ? "Your stay is coming up soon. The key is in the key safe at the entrance of the chalet.\n\nCode: {$code}\n\nCheck-in from {$z['ein']}, check-out by {$z['aus']}. When you leave, please put the key back into the safe and turn the code to 0000.\n\nEverything about your stay: " . guide_url($b) . "\n\nWe wish you a wonderful time in Sörenberg!"
                    : "Bald ist es so weit. Der Schlüssel liegt im Schlüsselsafe am Eingang des Chalets.\n\nCode: {$code}\n\nCheck-in ab {$z['ein']} Uhr, Check-out bis {$z['aus']} Uhr. Bei der Abreise bitte den Schlüssel wieder in den Safe legen und den Code auf 0000 drehen.\n\nAlles zum Aufenthalt: " . guide_url($b) . "\n\nWir wünschen Ihnen eine schöne Zeit in Sörenberg!")
                . "\n\n" . gruss($b),
            ];
        case 'dank':
            $url = einst('bewertung_url');
            return [
                $e ? 'Thank you for staying at Chalet Bambi' : 'Danke für Ihren Besuch im Chalet Bambi',
                anrede($b) . "\n\n" . ($e
                    ? "Thank you for staying at Chalet Bambi. We hope you had a lovely time in Sörenberg." . ($url ? "\n\nIf you enjoyed your stay, we would be very grateful for a short Google review:\n{$url}" : '') . "\n\nWe would be delighted to welcome you again."
                    : "Vielen Dank für Ihren Aufenthalt im Chalet Bambi. Wir hoffen, Sie hatten eine schöne Zeit in Sörenberg." . ($url ? "\n\nWenn es Ihnen gefallen hat, freuen wir uns sehr über eine kurze Google-Bewertung:\n{$url}" : '') . "\n\nGerne begrüssen wir Sie wieder einmal.")
                . "\n\n" . gruss($b),
            ];
        case 'antwort':
            return [(string)($extra['betreff'] ?? ''), (string)($extra['text'] ?? '')];
    }
    throw new InvalidArgumentException("Unbekannte Vorlage $art");
}

function mail_titel(string $art, bool $en): array {
    return [
        'bestaetigung' => $en ? ['Booking confirmed', 'We look forward to welcoming you.'] : ['Buchung bestätigt', 'Wir freuen uns auf Sie.'],
        'absage'       => $en ? ['Your enquiry', 'Thank you for your interest.'] : ['Ihre Anfrage', 'Danke für Ihr Interesse.'],
        'erinnerung'   => $en ? ['Payment', 'A friendly reminder.'] : ['Zahlung', 'Eine freundliche Erinnerung.'],
        'storno'       => $en ? ['Cancellation', 'Your stay has been cancelled.'] : ['Stornierung', 'Ihr Aufenthalt ist storniert.'],
        'info'         => $en ? ['Your stay', 'Good to know before you arrive.'] : ['Ihr Aufenthalt', 'Gut zu wissen vor der Anreise.'],
        'code'         => $en ? ['Arrival', 'Your key safe code.'] : ['Anreise', 'Ihr Code für den Schlüsselsafe.'],
        'dank'         => $en ? ['Thank you', 'Thank you for your stay.'] : ['Danke', 'Danke für Ihren Besuch.'],
        'antwort'      => $en ? ['Chalet Bambi', 'Our reply.'] : ['Chalet Bambi', 'Unsere Antwort.'],
    ][$art] ?? ['Chalet Bambi', 'Chalet Bambi'];
}

/* Schlichter Text → HTML-Mail (Tabellen, Inline-Stile, Farben und Schriften der Website). Erste Zeile eines Absatzes ohne Satzende = Zwischentitel. */
function mail_html(string $text, string $art, bool $en): string {
    $x = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $serif = "font-family:'Noto Serif',Georgia,'Times New Roman',serif;";
    $sans = "font-family:Inter,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;";
    $link = fn(string $s) => preg_replace('~(https?://[^\s<]+[^\s<.,;:)])~', '<a href="$1" style="color:#2D3E33;">$1</a>', $s);
    [$klein, $titel] = mail_titel($art, $en);
    $body = '';
    foreach (preg_split("/\n{2,}/", trim(str_replace("\r", '', $text))) as $absatz) {
        $zeilen = explode("\n", $absatz);
        if (count($zeilen) > 1 && mb_strlen($zeilen[0]) < 40 && !preg_match('/[.!?:,]$/u', $zeilen[0]) && !preg_match('/^(Guten Tag|Hello|Herzliche|Kind regards)/', $zeilen[0])) {
            $body .= '<p style="margin:24px 0 6px;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">' . $x(array_shift($zeilen)) . '</p>';
        }
        if (count($zeilen) === 1 && str_starts_with($zeilen[0], 'Code: ')) {
            $body .= '<p style="margin:10px 0 18px;padding:16px;background:#EDE8DF;border-radius:10px;text-align:center;' . $serif . 'font-size:30px;letter-spacing:6px;color:#2D3E33;">' . $x(trim(substr($zeilen[0], 5))) . '</p>';
            continue;
        }
        $html = implode('<br>', array_map(fn($z) => $link($x($z)), $zeilen));
        if (preg_match('/^(Herzliche Grüsse|Kind regards)/', $zeilen[0] ?? '')) {
            $html = $x($zeilen[0]) . '<br><span style="' . $serif . 'font-style:italic;font-size:19px;color:#2D3E33;">' . $x($zeilen[1] ?? '') . '</span>';
        }
        $body .= '<p style="margin:0 0 14px;">' . $html . '</p>';
    }
    return '<!doctype html><html lang="' . ($en ? 'en' : 'de') . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>Chalet Bambi</title></head>'
        . '<body style="margin:0;padding:0;background:#F0EDE4;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F0EDE4;"><tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#FBF9F4;border-radius:14px;overflow:hidden;">'
        . '<tr><td align="center" style="background:#2D3E33;padding:26px 24px 22px;">'
        . '<div style="' . $serif . 'font-size:28px;font-weight:300;letter-spacing:0.5px;color:#ffffff;">Chalet Bambi</div>'
        . '<div style="' . $sans . 'font-size:11px;letter-spacing:4px;text-transform:uppercase;color:#BFB5A8;margin-top:4px;">Sörenberg</div></td></tr>'
        . '<tr><td style="padding:34px 28px 18px;' . $sans . 'font-size:16px;line-height:1.6;color:#2C2C2C;">'
        . '<p style="margin:0;font-size:11px;letter-spacing:3px;text-transform:uppercase;color:#A05C3B;">' . $x($klein) . '</p>'
        . '<h1 style="margin:8px 0 22px;' . $serif . 'font-size:30px;font-weight:300;font-style:italic;line-height:1.2;color:#2D3E33;">' . $x($titel) . '</h1>'
        . $body . '</td></tr>'
        . '<tr><td align="center" style="background:#2D3E33;padding:22px 24px;' . $sans . 'font-size:12px;line-height:1.7;color:#BFB5A8;">'
        . 'Chalet Bambi · Rothornstrasse 40 · 6174 Sörenberg<br>'
        . '<a href="mailto:' . INFO_MAIL . '" style="color:#ffffff;text-decoration:none;">' . INFO_MAIL . '</a> · <a href="' . SITE_URL . ($en ? '/en/' : '') . '" style="color:#ffffff;text-decoration:none;">bambi-staub.ch</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/* Mail an den Gast senden. Gibt null bei Erfolg zurück, sonst eine Fehlermeldung. */
function gast_mail_senden(array $b, string $art, string $betreff, string $text, string $wer, ?string $an = null, ?string $inReplyTo = null): ?string {
    $an = $an ?? (string)$b['email'];
    if (!filter_var($an, FILTER_VALIDATE_EMAIL)) return 'Keine gültige E-Mail-Adresse.';
    if (str_contains($text . $betreff, '‹')) return 'Im Text fehlt noch etwas (‹…›). Bitte zuerst unter Einstellungen eintragen oder den Text anpassen.';
    if (trim($betreff) === '' || trim($text) === '') return 'Betreff und Text dürfen nicht leer sein.';
    $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $alt = 'a_' . bin2hex(random_bytes(12));
    $msgId = '<' . bin2hex(random_bytes(10)) . '@bambi-staub.ch>';
    $headers = 'From: ' . $enc('Chalet Bambi') . ' <' . INFO_MAIL . ">\r\n"
        . 'Reply-To: ' . INFO_MAIL . "\r\nBcc: " . INFO_MAIL . "\r\nMessage-ID: {$msgId}\r\n"
        . ($inReplyTo ? "In-Reply-To: {$inReplyTo}\r\nReferences: {$inReplyTo}\r\n" : '')
        . "X-Bambi-Admin: {$art}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n";
    $msg = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode(mail_html($text, $art, en($b))))
        . "--$alt--\r\n";
    if ($dev = getenv('BAMBI_DEV')) {
        @file_put_contents($dev . '/gast-' . $art . '-' . date('His') . '-' . bin2hex(random_bytes(2)) . '.eml', "To: {$an}\r\nSubject: " . $enc($betreff) . "\r\n$headers\r\n$msg");
    } elseif (!@mail($an, $enc($betreff), $msg, $headers, '-f' . INFO_MAIL)) {
        return 'Der Mailversand ist fehlgeschlagen. Bitte später nochmals versuchen.';
    }
    $id = isset($b['id']) ? (int)$b['id'] : null;
    q('INSERT INTO mail (richtung, message_id, von, an, betreff, datum, kurztext, kategorie, buchung_id, erledigt, erstellt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        ['aus', $msgId, INFO_MAIL, $an, $betreff, jetzt(), mb_substr($text, 0, 4000), 'gesendet', $id, jetzt()]);
    if ($id) verlauf($id, $wer, 'Mail gesendet: ' . (MAIL_ARTEN[$art] ?? $art), "an {$an}: {$betreff}");
    return null;
}
