<?php
declare(strict_types=1);

define('APP_NAME', 'NewGenz Metaverse');
define('APP_ENV', 'development');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'metaverse_db');
define('DB_USER', 'root');
define('DB_PASS', '');

define('API_BASE_URL', '/api');

function sendJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}
