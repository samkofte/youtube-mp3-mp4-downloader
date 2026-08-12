<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Yeni api.php isteklerini (örneğin /api.php/search) doğru şekilde yakala
if (strpos($uri, '/api.php') === 0) {
    $_SERVER['PATH_INFO'] = str_replace('/api.php', '', $uri);
    require __DIR__ . '/api.php';
    exit;
}

// Eski Node.js yollarını (örneğin /api/search) bizim yeni api.php'ye yönlendir.
if (strpos($uri, '/api/') === 0) {
    $_SERVER['REQUEST_URI'] = str_replace('/api/', '/api.php/', $_SERVER['REQUEST_URI']);
    $_SERVER['PATH_INFO'] = str_replace('/api/', '/', $uri);
    require __DIR__ . '/api.php';
    exit;
}

// Statik dosyaları (js, css, html) doğrudan sun.
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

// Kök dizini istenirse index.html sun.
require __DIR__ . '/index.html';
