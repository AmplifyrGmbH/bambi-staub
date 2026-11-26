<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = urldecode($uri);

// Wie .htaccess: interne Ordner sperren, /admin an das Admin-Center
if (preg_match('#^/(config|cache|app|docs)(/|$)#', $uri)) { http_response_code(404); return true; }
if (preg_match('#^/admin(/|$)#', $uri) && !is_file(__DIR__ . $uri)) { include __DIR__ . '/admin/index.php'; return true; }

// Serve existing files directly
$file = __DIR__ . $uri;
if ($uri !== '' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Map clean URLs to .html files
if ($uri === '' || $uri === '/') {
    include __DIR__ . '/index.html';
} elseif (file_exists(__DIR__ . $uri . '/index.html')) {
    include __DIR__ . $uri . '/index.html';
} elseif (file_exists(__DIR__ . $uri . '.html')) {
    include __DIR__ . $uri . '.html';
} else {
    http_response_code(404);
    include __DIR__ . '/404.html';
}
