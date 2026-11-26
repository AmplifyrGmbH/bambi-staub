<?php
/* KI-Auswertung von Mails über die Anthropic-API (Messages API, direkt per curl:
   das Projekt wird per FTP ohne Composer deployt, darum kein SDK).
   Strukturierte Ausgabe per output_config.format (JSON-Schema). Ohne API-Schlüssel: null. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const KI_MODELL = 'claude-opus-5-5';

function ki_verfuegbar(): bool { return (string)cfg('anthropic_api_key') !== ''; }

/* Ein Aufruf mit JSON-Schema. Gibt das geparste Objekt oder null zurück (Fehler stehen in meta ki_fehler). */
function ki_json(string $system, string $nutzer, array $schema, int $maxTokens = 4000): ?array {
    $key = (string)cfg('anthropic_api_key');
    if ($key === '') return null;
    $anfrage = [
        'model' => KI_MODELL,
        'max_tokens' => $maxTokens,
        'output_config' => ['effort' => 'low', 'format' => ['type' => 'json_schema', 'schema' => $schema]],
        'fallbacks' => 'default',   // lehnt das Modell ab, springt serverseitig ein anderes ein
        'system' => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
        'messages' => [['role' => 'user', 'content' => $nutzer]],
    ];
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $key, 'anthropic-version: 2026-10-21', 'anthropic-beta: server-side-fallback-2026-07-01'],
        CURLOPT_POSTFIELDS => json_encode($anfrage, JSON_UNESCAPED_UNICODE),
    ]);
    $roh = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $r = is_string($roh) ? json_decode($roh, true) : null;
    if ($code !== 200 || !is_array($r)) {
        meta_set('ki_fehler', jetzt() . ": HTTP $code " . mb_substr(is_array($r) ? (string)($r['error']['message'] ?? '') : (string)curl_error($ch), 0, 300));
        return null;
    }
    if (($r['stop_reason'] ?? '') === 'refusal') { meta_set('ki_fehler', jetzt() . ': abgelehnt'); return null; }
    foreach ($r['content'] ?? [] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $j = json_decode((string)$block['text'], true);
            if (is_array($j)) { meta_set('ki_fehler', ''); return $j; }
        }
    }
    meta_set('ki_fehler', jetzt() . ': keine gültige Antwort (' . ($r['stop_reason'] ?? '?') . ')');
    return null;
}
