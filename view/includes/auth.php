<?php

if (session_status() === PHP_SESSION_NONE) {

    session_name('ELKUMANDA_USER_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/elkumanda/view',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) && !empty($_COOKIE['user_remember_token'])) {

    $token = $_COOKIE['user_remember_token'];
    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT
            rt.user_id,
            rt.expires_at,
            u.name,
            u.email,
            u.role,
            u.is_active,
            u.disabled_until
        FROM remember_tokens rt
        INNER JOIN users u ON rt.user_id = u.id
        WHERE rt.token_hash = ?
        LIMIT 1
    ");

    $stmt->execute([$token_hash]);

    $user = $stmt->fetch();

    if (
        $user &&
        strtotime($user['expires_at']) > time() &&
        (int)$user['is_active'] === 1 &&
        (
            empty($user['disabled_until']) ||
            strtotime($user['disabled_until']) <= time()
        )
    ) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
    } elseif ($user) {

        $stmt = $pdo->prepare("
            DELETE FROM remember_tokens
            WHERE token_hash = ?
        ");

        $stmt->execute([$token_hash]);

        setcookie(
            'user_remember_token',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/elkumanda/view',
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
