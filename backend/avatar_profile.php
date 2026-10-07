<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getCurrentUserProfile(): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $pdo = getDb();
    $stmt = $pdo->prepare('
        SELECT id, username, email, avatar_skin, avatar_outfit, avatar_style, avatar_accessory
        FROM users
        WHERE id = :id
        LIMIT 1
    ');

    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJson(['success' => false, 'message' => 'User not found'], 404);
    }

    sendJson([
        'success' => true,
        'profile' => $user
    ]);
}

function saveCurrentUserProfile(array $input): void
{
    if (empty($_SESSION['user_id'])) {
        sendJson(['success' => false, 'message' => 'Authentication required'], 401);
    }

    $skin = trim((string) ($input['avatar_skin'] ?? 'light'));
    $outfit = trim((string) ($input['avatar_outfit'] ?? 'blue'));
    $style = trim((string) ($input['avatar_style'] ?? 'classic'));
    $accessory = trim((string) ($input['avatar_accessory'] ?? 'none'));

    $allowedSkin = ['light', 'tan', 'brown', 'dark'];
    $allowedOutfit = ['blue', 'purple', 'green', 'red', 'gold'];
    $allowedStyle = ['classic', 'hero', 'street', 'royal'];
    $allowedAccessory = ['none', 'glasses', 'hat', 'visor'];

    if (!in_array($skin, $allowedSkin, true)) $skin = 'light';
    if (!in_array($outfit, $allowedOutfit, true)) $outfit = 'blue';
    if (!in_array($style, $allowedStyle, true)) $style = 'classic';
    if (!in_array($accessory, $allowedAccessory, true)) $accessory = 'none';

    $pdo = getDb();
    $stmt = $pdo->prepare('
        UPDATE users
        SET avatar_skin = :skin,
            avatar_outfit = :outfit,
            avatar_style = :style,
            avatar_accessory = :accessory
        WHERE id = :id
    ');

    $stmt->execute([
        ':skin' => $skin,
        ':outfit' => $outfit,
        ':style' => $style,
        ':accessory' => $accessory,
        ':id' => $_SESSION['user_id']
    ]);

    sendJson([
        'success' => true,
        'message' => 'Avatar saved',
        'profile' => [
            'avatar_skin' => $skin,
            'avatar_outfit' => $outfit,
            'avatar_style' => $style,
            'avatar_accessory' => $accessory
        ]
    ]);
}
