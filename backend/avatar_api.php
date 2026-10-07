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

if ($path === '/avatar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getCurrentUserProfile();
}

if ($path === '/avatar/save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    saveCurrentUserProfile(getJsonBody());
}

sendJson(['success' => false, 'message' => 'Endpoint not found'], 404);
