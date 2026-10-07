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

if ($path === '/bank/account' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getBankAccount();
}

if ($path === '/bank/create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    createBankAccount();
}

if ($path === '/bank/deposit/initialize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    initializePaystackDeposit(getJsonBody());
}

if ($path === '/bank/deposit/verify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyPaystackDeposit(getJsonBody());
}

if ($path === '/bank/transactions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    getBankTransactions();
}

if ($path === '/bank/withdraw' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    withdrawFromBank(getJsonBody());
}

sendJson(['success' => false, 'message' => 'Endpoint not found'], 404);
