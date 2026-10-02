<?php

require_once __DIR__ . '/../../config/db.php';

function createNotification($userId, $title, $message, $link = null)
{
    global $pdo;

    if (
        empty($link) &&
        $title === 'New Order' &&
        preg_match('/#(\d+)/', $message, $matches)
    ) {
        $order_id = (int) $matches[1];

        if ($order_id > 0) {
            $link = 'order-details.php?id=' . $order_id;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            link,
            read_status
        )
        VALUES (?, ?, ?, ?, 0)
    ");

    $stmt->execute([
        (int) $userId,
        $title,
        $message,
        $link
    ]);
}

function getNotifications($userId)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            message,
            link,
            created_at,
            read_status
        FROM notifications
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 5
    ");

    $stmt->execute([
        (int) $userId
    ]);

    return $stmt->fetchAll();
}

function getUnreadNotificationsCount($userId)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
        AND read_status = 0
    ");

    $stmt->execute([
        (int) $userId
    ]);

    return (int) $stmt->fetchColumn();
}