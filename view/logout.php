<?php

session_name('ELKUMANDA_USER_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (!empty($_COOKIE['user_remember_token'])) {

    $token = $_COOKIE['user_remember_token'];
    $token_hash = hash('sha256', $token);

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

$_SESSION = [];

session_destroy();

setcookie(
    'ELKUMANDA_USER_SESSION',
    '',
    [
        'expires' => time() - 3600,
        'path' => '/elkumanda/view',
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);

header('Location: index.php');
exit;
