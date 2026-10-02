<?php

session_name('ELKUMANDA_ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!empty($_COOKIE['admin_remember_token'])) {

    $token = $_COOKIE['admin_remember_token'];
    $token_hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        DELETE FROM remember_tokens
        WHERE token_hash = ?
    ");

    $stmt->execute([$token_hash]);

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

$_SESSION = [];

session_destroy();

setcookie(
    'ELKUMANDA_ADMIN_SESSION',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/elkumanda/admin',
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);

header('Location: index.php');
exit;
