<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');

function getBankAccount(): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $pdo = getDb();
    $stmt = $pdo->prepare('
        SELECT id, user_id, balance, account_number, account_type, created_at
        FROM bank_accounts
        WHERE user_id = :user_id
    ');
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $account = $stmt->fetch();

    if (!$account) {
        sendJson([
            'success' => true,
            'account' => null
        ]);
        return;
    }

    sendJson([
        'success' => true,
        'account' => $account
    ]);
}

function createBankAccount(): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $pdo = getDb();

    $checkStmt = $pdo->prepare('SELECT id FROM bank_accounts WHERE user_id = :user_id');
    $checkStmt->execute([':user_id' => $_SESSION['user_id']]);
    if ($checkStmt->fetch()) {
        sendJson(['success' => false, 'message' => 'Bank account already exists'], 409);
    }

    $accountNumber = 'NGZ' . str_pad((string) $_SESSION['user_id'], 10, '0', STR_PAD_LEFT);

    $insertStmt = $pdo->prepare('
        INSERT INTO bank_accounts (user_id, account_number, account_type, balance)
        VALUES (:user_id, :account_number, :account_type, 0)
    ');
    $insertStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':account_number' => $accountNumber,
        ':account_type' => 'savings'
    ]);

    sendJson([
        'success' => true,
        'message' => 'Bank account created',
        'account' => [
            'account_number' => $accountNumber,
            'account_type' => 'savings',
            'balance' => 0
        ]
    ], 201);
}

function initializePaystackDeposit(array $input): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    if (empty(PAYSTACK_SECRET_KEY)) {
        sendJson(['success' => false, 'message' => 'Payment processing not configured'], 500);
    }

    $amount = (float) ($input['amount'] ?? 0);
    $email = trim((string) ($input['email'] ?? ''));

    if ($amount < 100) {
        sendJson(['success' => false, 'message' => 'Minimum deposit is 100 NGN'], 400);
    }

    if ($amount > 5000000) {
        sendJson(['success' => false, 'message' => 'Maximum deposit is 5,000,000 NGN'], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson(['success' => false, 'message' => 'Valid email required'], 400);
    }

    $reference = 'NGZ-' . bin2hex(random_bytes(8));
    $amountInKobo = (int) ($amount * 100);

    $curlHandle = curl_init();
    curl_setopt_array($curlHandle, [
        CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $email,
            'amount' => $amountInKobo,
            'reference' => $reference,
            'metadata' => [
                'user_id' => $_SESSION['user_id'],
                'transaction_type' => 'bank_deposit'
            ]
        ])
    ]);

    $response = curl_exec($curlHandle);
    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    curl_close($curlHandle);

    if ($httpCode !== 200) {
        sendJson(['success' => false, 'message' => 'Payment initialization failed'], 500);
    }

    $responseData = json_decode($response, true);

    if (empty($responseData['status']) || !$responseData['status']) {
        sendJson(['success' => false, 'message' => $responseData['message'] ?? 'Payment error'], 500);
    }

    $pdo = getDb();
    $insertStmt = $pdo->prepare('
        INSERT INTO paystack_deposits (user_id, amount, paystack_reference, paystack_access_code, authorization_url, status)
        VALUES (:user_id, :amount, :reference, :access_code, :auth_url, :status)
    ');
    $insertStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':amount' => $amount,
        ':reference' => $reference,
        ':access_code' => $responseData['data']['access_code'] ?? '',
        ':auth_url' => $responseData['data']['authorization_url'] ?? '',
        ':status' => 'pending'
    ]);

    sendJson([
        'success' => true,
        'message' => 'Deposit initialized',
        'authorization_url' => $responseData['data']['authorization_url'] ?? '',
        'reference' => $reference,
        'amount' => $amount
    ], 201);
}

function verifyPaystackDeposit(array $input): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    if (empty(PAYSTACK_SECRET_KEY)) {
        sendJson(['success' => false, 'message' => 'Payment processing not configured'], 500);
    }

    $reference = trim((string) ($input['reference'] ?? ''));

    if (!$reference) {
        sendJson(['success' => false, 'message' => 'Reference required'], 400);
    }

    $curlHandle = curl_init();
    curl_setopt_array($curlHandle, [
        CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . urlencode($reference),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
        ]
    ]);

    $response = curl_exec($curlHandle);
    $httpCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
    curl_close($curlHandle);

    if ($httpCode !== 200) {
        sendJson(['success' => false, 'message' => 'Verification failed'], 500);
    }

    $responseData = json_decode($response, true);

    if (empty($responseData['status']) || !$responseData['status']) {
        sendJson(['success' => false, 'message' => 'Transaction verification error'], 500);
    }

    $transactionData = $responseData['data'];

    if ($transactionData['status'] !== 'success') {
        sendJson(['success' => false, 'message' => 'Payment not completed'], 400);
    }

    $pdo = getDb();

    $updatePaystackStmt = $pdo->prepare('
        UPDATE paystack_deposits SET status = :status, paid_at = NOW() WHERE paystack_reference = :reference AND user_id = :user_id
    ');
    $updatePaystackStmt->execute([
        ':status' => 'completed',
        ':reference' => $reference,
        ':user_id' => $_SESSION['user_id']
    ]);

    $amount = (float) ($transactionData['amount'] / 100);

    $updateBankStmt = $pdo->prepare('
        UPDATE bank_accounts SET balance = balance + :amount WHERE user_id = :user_id
    ');
    $updateBankStmt->execute([
        ':amount' => $amount,
        ':user_id' => $_SESSION['user_id']
    ]);

    $insertTransactionStmt = $pdo->prepare('
        INSERT INTO bank_transactions (user_id, transaction_type, amount, description, reference_id, status)
        VALUES (:user_id, :type, :amount, :description, :reference_id, :status)
    ');
    $insertTransactionStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':type' => 'deposit',
        ':amount' => $amount,
        ':description' => 'Paystack deposit',
        ':reference_id' => $reference,
        ':status' => 'completed'
    ]);

    $updateCurrencyStmt = $pdo->prepare('
        UPDATE user_currency SET balance = balance + :amount WHERE user_id = :user_id
    ');
    $updateCurrencyStmt->execute([
        ':amount' => $amount,
        ':user_id' => $_SESSION['user_id']
    ]);

    sendJson([
        'success' => true,
        'message' => 'Deposit verified and credited',
        'amount' => $amount,
        'reference' => $reference
    ]);
}

function getBankTransactions(): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $pdo = getDb();
    $stmt = $pdo->prepare('
        SELECT id, transaction_type, amount, description, status, created_at
        FROM bank_transactions
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 50
    ');
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $transactions = $stmt->fetchAll();

    sendJson([
        'success' => true,
        'transactions' => $transactions
    ]);
}

function withdrawFromBank(array $input): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $amount = (float) ($input['amount'] ?? 0);

    if ($amount < 1000) {
        sendJson(['success' => false, 'message' => 'Minimum withdrawal is 1,000 NGN'], 400);
    }

    $pdo = getDb();

    $bankStmt = $pdo->prepare('SELECT balance FROM bank_accounts WHERE user_id = :user_id');
    $bankStmt->execute([':user_id' => $_SESSION['user_id']]);
    $bankResult = $bankStmt->fetch();

    if (!$bankResult || (float) $bankResult['balance'] < $amount) {
        sendJson(['success' => false, 'message' => 'Insufficient bank balance'], 400);
    }

    $updateBankStmt = $pdo->prepare('
        UPDATE bank_accounts SET balance = balance - :amount WHERE user_id = :user_id
    ');
    $updateBankStmt->execute([
        ':amount' => $amount,
        ':user_id' => $_SESSION['user_id']
    ]);

    $reference = 'WTH-' . bin2hex(random_bytes(8));
    $insertTransactionStmt = $pdo->prepare('
        INSERT INTO bank_transactions (user_id, transaction_type, amount, description, reference_id, status)
        VALUES (:user_id, :type, :amount, :description, :reference_id, :status)
    ');
    $insertTransactionStmt->execute([
        ':user_id' => $_SESSION['user_id'],
        ':type' => 'withdrawal',
        ':amount' => $amount,
        ':description' => 'Bank withdrawal',
        ':reference_id' => $reference,
        ':status' => 'completed'
    ]);

    $updateCurrencyStmt = $pdo->prepare('
        UPDATE user_currency SET balance = balance + :amount WHERE user_id = :user_id
    ');
    $updateCurrencyStmt->execute([
        ':amount' => $amount,
        ':user_id' => $_SESSION['user_id']
    ]);

    sendJson([
        'success' => true,
        'message' => 'Withdrawal completed',
        'amount' => $amount,
        'reference' => $reference
    ]);
}
