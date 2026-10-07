<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api', '', $requestUri);

if ($path === '/health') {
    sendJson([
        'success' => true,
        'app' => APP_NAME,
        'environment' => APP_ENV,
        'status' => 'ok'
    ]);
}

if ($path === '/status') {
    try {
        $pdo = getDb();
        $pdo->query('SELECT 1');
        sendJson([
            'success' => true,
            'database' => 'connected',
            'message' => 'Backend ready'
        ]);
    } catch (Throwable $e) {
        sendJson([
            'success' => false,
            'database' => 'disconnected',
            'message' => 'Database unavailable'
        ], 500);
    }
}

if ($path === '/register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    registerUser(getJsonBody());
}

if ($path === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    loginUser(getJsonBody());
}

if ($path === '/logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    logoutUser();
}

if ($path === '/me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getAuthenticatedUser();
}

if ($path === '/worlds' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getWorlds();
}

if ($path === '/worlds/join' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    joinWorld(getJsonBody());
}

if ($path === '/worlds/save-position' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    savePlayerPosition(getJsonBody());
}

sendJson([
    'success' => false,
    'message' => 'Endpoint not found'
], 404);
