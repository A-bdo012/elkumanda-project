<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: coupons.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM coupons
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    $_SESSION['coupon_error'] = 'Coupon not found.';
    header('Location: coupons.php');
    exit;
}

$delete = $pdo->prepare("
    DELETE FROM coupons
    WHERE id = ?
");

$delete->execute([$id]);

$_SESSION['coupon_success'] = 'Coupon deleted successfully.';

header('Location: coupons.php');
exit;
