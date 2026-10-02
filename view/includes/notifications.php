<?php

require_once __DIR__ . '/../../config/db.php';

function createNotification($userId, $title, $message)
{
    global $pdo;

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        (int)$userId,
        $title,
        $message
    ]);
}
