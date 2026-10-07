<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireAuth(): array
{
    if (empty($_SESSION['user_id'])) {
        sendJson([
            'success' => false,
            'message' => 'Authentication required'
        ], 401);
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'email' => (string) ($_SESSION['email'] ?? '')
    ];
}

function registerUser(array $input): void
{
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim(strtolower((string) ($input['email'] ?? '')));
    $password = (string) ($input['password'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        sendJson([
            'success' => false,
            'message' => 'Username, email, and password are required'
        ], 400);
    }

    if (strlen($username) < 3) {
        sendJson([
            'success' => false,
            'message' => 'Username must be at least 3 characters'
        ], 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJson([
            'success' => false,
            'message' => 'Please provide a valid email'
        ], 400);
    }

    if (strlen($password) < 6) {
        sendJson([
            'success' => false,
            'message' => 'Password must be at least 6 characters'
        ], 400);
    }

    $pdo = getDb();

    $check = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
    $check->execute([
        ':username' => $username,
        ':email' => $email
    ]);

    if ($check->fetch()) {
        sendJson([
            'success' => false,
            'message' => 'User already exists'
        ], 409);
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('
        INSERT INTO users (username, email, password_hash, avatar_name, avatar_skin, current_world_id, x, y, z)
        VALUES (:username, :email, :password_hash, :avatar_name, :avatar_skin, :current_world_id, :x, :y, :z)
    ');

    $stmt->execute([
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $hash,
        ':avatar_name' => $username,
        ':avatar_skin' => 'default',
        ':current_world_id' => 1,
        ':x' => 0,
        ':y' => 0,
        ':z' => 0
    ]);

    sendJson([
        'success' => true,
        'message' => 'Registration successful',
        'user' => [
            'username' => $username,
            'email' => $email
        ]
    ], 201);
}

function loginUser(array $input): void
{
    $login = trim((string) ($input['login'] ?? ''));
    $password = (string) ($input['password'] ?? '');

    if ($login === '' || $password === '') {
        sendJson([
            'success' => false,
            'message' => 'Login and password are required'
        ], 400);
    }

    $pdo = getDb();

    $stmt = $pdo->prepare('
        SELECT id, username, email, password_hash
        FROM users
        WHERE username = :login OR email = :login
        LIMIT 1
    ');

    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        sendJson([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    sendJson([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]
    ]);
}

function logoutUser(): void
{
    session_unset();
    session_destroy();

    sendJson([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

function getAuthenticatedUser(): void
{
    $user = requireAuth();

    sendJson([
        'success' => true,
        'user' => $user
    ]);
}

function getWorlds(): void
{
    $pdo = getDb();
    $stmt = $pdo->query('SELECT * FROM worlds ORDER BY id ASC');
    $worlds = $stmt->fetchAll();

    sendJson([
        'success' => true,
        'worlds' => $worlds
    ]);
}

function joinWorld(array $input): void
{
    $user = requireAuth();
    $worldId = (int) ($input['world_id'] ?? 0);

    if ($worldId <= 0) {
        sendJson([
            'success' => false,
            'message' => 'A valid world is required'
        ], 400);
    }

    $pdo = getDb();
    $worldCheck = $pdo->prepare('SELECT id, spawn_x, spawn_z FROM worlds WHERE id = :id LIMIT 1');
    $worldCheck->execute([':id' => $worldId]);
    $world = $worldCheck->fetch();

    if (!$world) {
        sendJson([
            'success' => false,
            'message' => 'World not found'
        ], 404);
    }

    $update = $pdo->prepare('
        UPDATE users
        SET current_world_id = :world_id,
            x = :x,
            y = 0,
            z = :z
        WHERE id = :user_id
    ');

    $update->execute([
        ':world_id' => $worldId,
        ':x' => (float) $world['spawn_x'],
        ':z' => (float) $world['spawn_z'],
        ':user_id' => $user['id']
    ]);

    $upsert = $pdo->prepare('
        INSERT INTO world_player_positions (user_id, world_id, x, y, z, yaw)
        VALUES (:user_id, :world_id, :x, :y, :z, :yaw)
        ON DUPLICATE KEY UPDATE
            x = VALUES(x),
            y = VALUES(y),
            z = VALUES(z),
            yaw = VALUES(yaw),
            last_update = CURRENT_TIMESTAMP
    ');

    $upsert->execute([
        ':user_id' => $user['id'],
        ':world_id' => $worldId,
        ':x' => (float) $world['spawn_x'],
        ':y' => 0,
        ':z' => (float) $world['spawn_z'],
        ':yaw' => 0
    ]);

    sendJson([
        'success' => true,
        'message' => 'Joined world successfully',
        'world' => $world,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'world_id' => $worldId
        ]
    ]);
}

function savePlayerPosition(array $input): void
{
    $user = requireAuth();
    $worldId = (int) ($input['world_id'] ?? 0);
    $x = (float) ($input['x'] ?? 0);
    $y = (float) ($input['y'] ?? 0);
    $z = (float) ($input['z'] ?? 0);
    $yaw = (float) ($input['yaw'] ?? 0);

    if ($worldId <= 0) {
        sendJson([
            'success' => false,
            'message' => 'World id is required'
        ], 400);
    }

    $pdo = getDb();
    $stmt = $pdo->prepare('
        UPDATE users
        SET current_world_id = :world_id,
            x = :x,
            y = :y,
            z = :z
        WHERE id = :user_id
    ');

    $stmt->execute([
        ':world_id' => $worldId,
        ':x' => $x,
        ':y' => $y,
        ':z' => $z,
        ':user_id' => $user['id']
    ]);

    $upsert = $pdo->prepare('
        INSERT INTO world_player_positions (user_id, world_id, x, y, z, yaw)
        VALUES (:user_id, :world_id, :x, :y, :z, :yaw)
        ON DUPLICATE KEY UPDATE
            x = VALUES(x),
            y = VALUES(y),
            z = VALUES(z),
            yaw = VALUES(yaw),
            last_update = CURRENT_TIMESTAMP
    ');

    $upsert->execute([
        ':user_id' => $user['id'],
        ':world_id' => $worldId,
        ':x' => $x,
        ':y' => $y,
        ':z' => $z,
        ':yaw' => $yaw
    ]);

    sendJson([
        'success' => true,
        'message' => 'Position saved'
    ]);
}
