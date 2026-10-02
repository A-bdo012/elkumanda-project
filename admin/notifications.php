<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/notifications.php';

$user_id = (int) $_SESSION['user_id'];

/* =========================
   Mark All as Read
========================= */

if (isset($_GET['mark_all']) && $_GET['mark_all'] === '1') {

    $stmt = $pdo->prepare("
        UPDATE notifications
        SET read_status = 1
        WHERE user_id = ?
        AND read_status = 0
    ");

    $stmt->execute([$user_id]);

    header('Location: notifications.php');
    exit;
}

/* =========================
   Open Notification
========================= */

if (isset($_GET['read'])) {

    $notification_id = (int) $_GET['read'];

    // Mark notification as read
    $stmt = $pdo->prepare("
        UPDATE notifications
        SET read_status = 1
        WHERE id = ?
        AND user_id = ?
    ");

    $stmt->execute([
        $notification_id,
        $user_id
    ]);

    // Get notification link
    $link_stmt = $pdo->prepare("
        SELECT link
        FROM notifications
        WHERE id = ?
        AND user_id = ?
        LIMIT 1
    ");

    $link_stmt->execute([
        $notification_id,
        $user_id
    ]);

    $notification_link = $link_stmt->fetchColumn();

    // If notification has a link, open it
    if (!empty($notification_link)) {
        header('Location: ' . $notification_link);
        exit;
    }

    // Otherwise return to notifications
    header('Location: notifications.php');
    exit;
}

/* =========================
   Get Notifications
========================= */

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
");

$stmt->execute([$user_id]);

$notifications = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';

if ($_SESSION['role'] === 'admin') {
    include __DIR__ . '/includes/admin_sidebar.php';
} else {
    include __DIR__ . '/includes/vendor_sidebar.php';
}

include __DIR__ . '/includes/topbar.php';

?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Notifications
            </h1>

            <p class="mb-0 text-gray-600">
                View all your notifications
            </p>
        </div>

        <?php if (!empty($notifications)): ?>

            <a href="notifications.php?mark_all=1"
                class="btn btn-sm btn-primary shadow-sm">

                <i class="fas fa-check-double fa-sm text-white-50"></i>
                Mark All as Read

            </a>

        <?php endif; ?>

    </div>

    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                All Notifications
            </h6>

        </div>

        <div class="card-body p-0">

            <?php if (empty($notifications)): ?>

                <div class="text-center py-5">

                    <i class="fas fa-bell-slash fa-3x text-gray-300 mb-3"></i>

                    <h5 class="text-gray-600">
                        No notifications
                    </h5>

                    <p class="text-muted mb-0">
                        You don't have any notifications yet.
                    </p>

                </div>

            <?php else: ?>

                <div class="list-group list-group-flush">

                    <?php foreach ($notifications as $notification): ?>

                        <?php

                        $isUnread =
                            ((int) $notification['read_status'] === 0);

                        $notificationUrl =
                            'notifications.php?read=' .
                            (int) $notification['id'];

                        ?>

                        <a
                            href="<?= htmlspecialchars($notificationUrl) ?>"
                            class="list-group-item list-group-item-action <?= $isUnread ? 'bg-light' : ''; ?>"
                            style="text-decoration:none;">

                            <div class="d-flex align-items-start">

                                <div class="mr-3">

                                    <div class="icon-circle <?= $isUnread ? 'bg-primary' : 'bg-secondary'; ?>">

                                        <i class="fas fa-bell text-white"></i>

                                    </div>

                                </div>

                                <div class="flex-grow-1">

                                    <div class="d-flex justify-content-between align-items-start">

                                        <h6 class="mb-1 <?= $isUnread ? 'font-weight-bold text-gray-800' : 'text-gray-600'; ?>">

                                            <?= htmlspecialchars($notification['title']); ?>

                                        </h6>

                                        <?php if ($isUnread): ?>

                                            <span class="badge badge-primary">
                                                New
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-secondary">
                                                Read
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <p class="mb-1 text-gray-600">

                                        <?= htmlspecialchars($notification['message']); ?>

                                    </p>

                                    <small class="text-gray-500">

                                        <i class="far fa-clock"></i>

                                        <?= htmlspecialchars($notification['created_at']); ?>

                                    </small>

                                </div>

                            </div>

                        </a>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php

include __DIR__ . '/includes/footer.php';

?>