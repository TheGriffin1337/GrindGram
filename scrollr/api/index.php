<?php
// ============================================
// SCROLLR - Główny router API
// ============================================
require_once __DIR__ . '/includes/helpers.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = str_replace('/scrollr/api', '', $uri);
$method = $_SERVER['REQUEST_METHOD'];

// Router
match(true) {
    // AUTH
    $uri === '/auth/register' && $method === 'POST' => require __DIR__ . '/api/auth_register.php',
    $uri === '/auth/login'    && $method === 'POST' => require __DIR__ . '/api/auth_login.php',
    $uri === '/auth/logout'   && $method === 'POST' => require __DIR__ . '/api/auth_logout.php',
    $uri === '/auth/me'       && $method === 'GET'  => require __DIR__ . '/api/auth_me.php',

    // POSTS
    $uri === '/posts'         && $method === 'GET'  => require __DIR__ . '/api/posts_list.php',
    $uri === '/posts'         && $method === 'POST' => require __DIR__ . '/api/posts_create.php',
    preg_match('#^/posts/(\d+)$#', $uri, $m) && $method === 'GET'    => require __DIR__ . '/api/posts_get.php',
    preg_match('#^/posts/(\d+)/like$#', $uri, $m) && $method === 'POST' => require __DIR__ . '/api/posts_like.php',
    preg_match('#^/posts/(\d+)$#', $uri, $m) && $method === 'DELETE' => require __DIR__ . '/api/posts_delete.php',

    // AI
    $uri === '/ai/generate'   && $method === 'POST' => require __DIR__ . '/api/ai_generate.php',

    // domyślnie
    default => json_error("Endpoint nie istnieje: {$method} {$uri}", 404),
};
