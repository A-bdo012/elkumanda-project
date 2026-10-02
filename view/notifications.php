<?php

require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pageTitle = 'Notifications - Elkumanda';

$userId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT id, title, message, created_at, read_status
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->execute([$userId]);

$notifications = $stmt->fetchAll();

$stmt = $pdo->prepare("
    UPDATE notifications
    SET read_status = 1
    WHERE user_id = ?
");

$stmt->execute([$userId]);

?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
    body {
        background: #f7f7f7;
    }

    .notifications-section {
        padding: 80px 0;
    }

    .notifications-box {
        max-width: 800px;
        margin: 0 auto;
        background: #fff;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .notifications-box h2 {
        font-weight: 800;
        margin-bottom: 30px;
    }

    .notification-item {
        padding: 20px;
        border: 1px solid #eee;
        margin-bottom: 15px;
        background: #fff;
    }

    .notification-item.unread {
        border-left: 4px solid #7fad39;
        background: #f8fbf3;
    }

    .notification-title {
        font-weight: 800;
        font-size: 18px;
        margin-bottom: 8px;
    }

    .notification-message {
        color: #666;
        margin-bottom: 10px;
    }

    .notification-date {
        color: #999;
        font-size: 13px;
    }

    .no-notifications {
        text-align: center;
        color: #777;
        padding: 30px 0;
    }
</style>

<section class="notifications-section">

    <div class="container">

        <div class="notifications-box">

            <h2>
                Notifications
            </h2>

            <?php if (!empty($notifications)): ?>

                <?php foreach ($notifications as $notification): ?>

                    <div class="notification-item <?php echo (int)$notification['read_status'] === 0 ? 'unread' : ''; ?>">

                        <div class="notification-title">
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </div>

                        <div class="notification-message">
                            <?php echo htmlspecialchars($notification['message']); ?>
                        </div>

                        <div class="notification-date">
                            <?php echo htmlspecialchars($notification['created_at']); ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="no-notifications">
                    No notifications yet.
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>