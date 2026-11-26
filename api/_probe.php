<?php
// Einmalige Server-Prüfung für das Admin-Center (Etappe 0). Wird danach wieder gelöscht.
header('Content-Type: application/json'); header('X-Robots-Tag: noindex');
$data = dirname(__DIR__, 3) . '/bambi-data';
$r = ['php' => PHP_VERSION, 'sapi' => PHP_SAPI,
  'pdo_sqlite' => extension_loaded('pdo_sqlite'), 'sqlite_version' => class_exists('SQLite3') ? SQLite3::version()['versionString'] : null,
  'pdo_mysql' => extension_loaded('pdo_mysql'), 'curl' => extension_loaded('curl'), 'openssl' => extension_loaded('openssl'),
  'imap_ext' => extension_loaded('imap'), 'mbstring' => extension_loaded('mbstring'), 'sodium' => extension_loaded('sodium'),
  'argon2id' => defined('PASSWORD_ARGON2ID'), 'max_execution_time' => ini_get('max_execution_time'), 'memory_limit' => ini_get('memory_limit'),
  'data_dir_writable' => is_dir($data) && is_writable($data), 'config_admin' => file_exists(__DIR__ . '/../config/admin.php'),
  'disk_free_mb' => (int)(@disk_free_space($data) / 1048576), 'proc_open' => function_exists('proc_open'), 'exec' => function_exists('exec')];
if ($r['pdo_sqlite'] && $r['data_dir_writable']) {
  try { $db = new PDO("sqlite:$data/_probe.sqlite"); $db->exec('CREATE TABLE IF NOT EXISTS t(x)'); $db->exec("INSERT INTO t VALUES(1)"); $r['sqlite_write'] = true; $db = null; @unlink("$data/_probe.sqlite"); }
  catch (Throwable $e) { $r['sqlite_write'] = $e->getMessage(); }
}
$fp = @stream_socket_client('ssl://imap.mail.hostpoint.ch:993', $en, $es, 8);
$r['imap_reach'] = $fp ? trim(fgets($fp)) !== '' : "$en $es"; if ($fp) fclose($fp);
$ch = curl_init('https://api.anthropic.com/v1/models'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_NOBODY => true]);
curl_exec($ch); $r['anthropic_reach'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo json_encode($r, JSON_PRETTY_PRINT);
