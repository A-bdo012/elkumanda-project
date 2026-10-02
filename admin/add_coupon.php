<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/notifications.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $occasion = trim($_POST['occasion'] ?? '');
    $discount_type = $_POST['discount_type'] ?? '';
    $discount_value = (float) ($_POST['discount_value'] ?? 0);
    $min_order_amount = (float) ($_POST['min_order_amount'] ?? 0);
    $max_uses = !empty($_POST['max_uses']) ? (int) $_POST['max_uses'] : null;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $show_to_users = isset($_POST['show_to_users']) ? 1 : 0;

    if ($code === '') {
        $error = 'Coupon code is required.';
    } elseif ($occasion === '') {
        $error = 'Please select an occasion.';
    } elseif (!in_array($discount_type, ['percentage', 'fixed'], true)) {
        $error = 'Invalid discount type.';
    } elseif ($discount_value <= 0) {
        $error = 'Discount value must be greater than 0.';
    } elseif ($min_order_amount < 0) {
        $error = 'Minimum order amount cannot be negative.';
    } elseif ($start_date === '' || $end_date === '') {
        $error = 'Start date and end date are required.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = 'End date must be after start date.';
    }

    if ($error === '') {

        try {

            $pdo->beginTransaction();

            $check = $pdo->prepare("
                SELECT id
                FROM coupons
                WHERE code = ?
                LIMIT 1
            ");

            $check->execute([$code]);

            if ($check->fetch()) {
                throw new Exception('Coupon code already exists.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO coupons (
                    code,
                    occasion,
                    discount_type,
                    discount_value,
                    min_order_amount,
                    max_uses,
                    used_count,
                    start_date,
                    end_date,
                    status,
                    show_to_users
                )
                VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $code,
                $occasion,
                $discount_type,
                $discount_value,
                $min_order_amount,
                $max_uses,
                $start_date,
                $end_date,
                $status,
                $show_to_users
            ]);

            if ($show_to_users === 1) {

                if ($discount_type === 'percentage') {
                    $discountText = number_format($discount_value, 2) . '% OFF';
                } else {
                    $discountText = number_format($discount_value, 2) . ' EGP OFF';
                }

                $endDateText = date(
                    'd M Y, h:i A',
                    strtotime($end_date)
                );

                $message = "🎉 {$occasion} Coupon! Use code {$code} and get {$discountText}. Offer ends on {$endDateText}.";

                $usersStmt = $pdo->query("
                    SELECT id
                    FROM users
                    WHERE role = 'user'
                ");

                $users = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($users as $userId) {

                    createNotification(
                        (int) $userId,
                        'New Coupon Available',
                        $message,
                        null
                    );
                }
            }

            $pdo->commit();

            $_SESSION['success'] = $show_to_users
                ? 'Coupon added successfully and notification sent to users.'
                : 'Coupon added successfully.';

            header('Location: coupons.php');
            exit;

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>Add Coupon - Elkumanda</title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <link
        href="vendor/fontawesome-free/css/all.min.css"
        rel="stylesheet"
        type="text/css"
    >

    <link
        href="css/sb-admin-2.min.css"
        rel="stylesheet"
    >

</head>

<body id="page-top">

<div id="wrapper">

    <?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

    <div id="content-wrapper" class="d-flex flex-column">

        <div id="content">

            <?php require_once __DIR__ . '/includes/topbar.php'; ?>

            <div class="container-fluid">

                <div class="d-sm-flex align-items-center justify-content-between mb-4">

                    <h1 class="h3 mb-0 text-gray-800">
                        Add Coupon
                    </h1>

                    <a
                        href="coupons.php"
                        class="btn btn-secondary btn-sm"
                    >
                        <i class="fas fa-arrow-left"></i>
                        Back
                    </a>

                </div>

                <?php if ($error !== ''): ?>

                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>

                <?php endif; ?>

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
                            Create New Coupon
                        </h6>

                    </div>

                    <div class="card-body">

                        <form method="POST">

                            <div class="form-group">

                                <label>
                                    Coupon Code
                                </label>

                                <input
                                    type="text"
                                    name="code"
                                    class="form-control"
                                    required
                                    value="<?= htmlspecialchars($_POST['code'] ?? '') ?>"
                                >

                            </div>

                            <div class="form-group">

                                <label>
                                    Occasion
                                </label>

                                <select
                                    name="occasion"
                                    class="form-control"
                                    required
                                >

                                    <option value="">
                                        Select Occasion
                                    </option>

                                    <option value="New Year">
                                        🎉 New Year
                                    </option>

                                    <option value="Valentine's Day">
                                        ❤️ Valentine's Day
                                    </option>

                                    <option value="Ramadan">
                                        🌙 Ramadan
                                    </option>

                                    <option value="Eid">
                                        🕌 Eid
                                    </option>

                                    <option value="Mother's Day">
                                        💐 Mother's Day
                                    </option>

                                    <option value="Back to School">
                                        🎒 Back to School
                                    </option>

                                    <option value="Halloween">
                                        🎃 Halloween
                                    </option>

                                    <option value="Black Friday">
                                        🛍️ Black Friday
                                    </option>

                                    <option value="Christmas">
                                        🎄 Christmas
                                    </option>

                                    <option value="Birthday">
                                        🎂 Birthday
                                    </option>

                                    <option value="Special Offer">
                                        ⭐ Special Offer
                                    </option>

                                </select>

                            </div>

                            <div class="form-row">

                                <div class="form-group col-md-6">

                                    <label>
                                        Discount Type
                                    </label>

                                    <select
                                        name="discount_type"
                                        class="form-control"
                                        required
                                    >

                                        <option value="percentage">
                                            Percentage
                                        </option>

                                        <option value="fixed">
                                            Fixed
                                        </option>

                                    </select>

                                </div>

                                <div class="form-group col-md-6">

                                    <label>
                                        Discount Value
                                    </label>

                                    <input
                                        type="number"
                                        name="discount_value"
                                        class="form-control"
                                        step="0.01"
                                        min="0.01"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="form-row">

                                <div class="form-group col-md-6">

                                    <label>
                                        Minimum Order Amount
                                    </label>

                                    <input
                                        type="number"
                                        name="min_order_amount"
                                        class="form-control"
                                        step="0.01"
                                        min="0"
                                        value="0"
                                    >

                                </div>

                                <div class="form-group col-md-6">

                                    <label>
                                        Maximum Uses
                                    </label>

                                    <input
                                        type="number"
                                        name="max_uses"
                                        class="form-control"
                                        min="1"
                                    >

                                </div>

                            </div>

                            <div class="form-row">

                                <div class="form-group col-md-6">

                                    <label>
                                        Start Date
                                    </label>

                                    <input
                                        type="datetime-local"
                                        name="start_date"
                                        class="form-control"
                                        required
                                    >

                                </div>

                                <div class="form-group col-md-6">

                                    <label>
                                        End Date
                                    </label>

                                    <input
                                        type="datetime-local"
                                        name="end_date"
                                        class="form-control"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="form-group">

                                <label>
                                    Status
                                </label>

                                <select
                                    name="status"
                                    class="form-control"
                                >

                                    <option value="active">
                                        Active
                                    </option>

                                    <option value="inactive">
                                        Inactive
                                    </option>

                                </select>

                            </div>

                            <div class="form-group">

                                <div class="custom-control custom-checkbox">

                                    <input
                                        type="checkbox"
                                        class="custom-control-input"
                                        id="show_to_users"
                                        name="show_to_users"
                                        value="1"
                                    >

                                    <label
                                        class="custom-control-label"
                                        for="show_to_users"
                                    >
                                        Show this coupon to users and send notification
                                    </label>

                                </div>

                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary"
                            >
                                <i class="fas fa-save"></i>
                                Create Coupon
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

        <?php require_once __DIR__ . '/includes/footer.php'; ?>

    </div>

</div>

<a
    class="scroll-to-top rounded"
    href="#page-top"
>
    <i class="fas fa-angle-up"></i>
</a>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>

</body>

</html>