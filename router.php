<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');
$uri = urldecode($uri);

// Serve existing files directly
$file = __DIR__ . $uri;
if ($uri !== '' && file_exists($file) && !is_dir($file)) {
    return false;
}

// Map clean URLs to .html files
if ($uri === '' || $uri === '/') {
    include __DIR__ . '/index.html';
} elseif (file_exists(__DIR__ . $uri . '.html')) {
    include __DIR__ . $uri . '.html';
} else {
    http_response_code(404);
    echo '404 Not Found';
}
