<?php

if (session_status() === PHP_SESSION_NONE) {

    session_name('ELKUMANDA_ADMIN_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/elkumanda/admin',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) && !empty($_COOKIE['admin_remember_token'])) {

    $token = $_COOKIE['admin_remember_token'];
    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT
            rt.user_id,
            rt.expires_at,
            u.name,
            u.role,
            u.is_active,
            u.disabled_until
        FROM remember_tokens rt
        INNER JOIN users u ON rt.user_id = u.id
        WHERE rt.token_hash = ?
        LIMIT 1
    ");

    $stmt->execute([$token_hash]);
    $rememberUser = $stmt->fetch();

    if ($rememberUser) {

        if (
            strtotime($rememberUser['expires_at']) > time() &&
            (int)$rememberUser['is_active'] === 1
        ) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $rememberUser['user_id'];
            $_SESSION['user_name'] = $rememberUser['name'];
            $_SESSION['role'] = $rememberUser['role'];
        } else {

            $deleteToken = $pdo->prepare("
                DELETE FROM remember_tokens
                WHERE token_hash = ?
            ");

            $deleteToken->execute([$token_hash]);

            setcookie(
                'admin_remember_token',
                '',
                [
                    'expires' => time() - 3600,
                    'path' => '/elkumanda/admin',
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );
        }
    }
}

function requireRole(string $role): void
{
    if (
        !isset($_SESSION['user_id']) ||
        !isset($_SESSION['role']) ||
        $_SESSION['role'] !== $role
    ) {
        header('Location: index.php');
        exit;
    }
}
