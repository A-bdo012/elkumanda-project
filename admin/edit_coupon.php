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
    SELECT *
    FROM coupons
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    header('Location: coupons.php');
    exit;
}

$error = '';

$code = $coupon['code'];
$occasion = $coupon['occasion'] ?? '';
$discount_type = $coupon['discount_type'];
$discount_value = $coupon['discount_value'];
$min_order_amount = $coupon['min_order_amount'];
$max_uses = $coupon['max_uses'];
$start_date = date('Y-m-d\TH:i', strtotime($coupon['start_date']));
$end_date = date('Y-m-d\TH:i', strtotime($coupon['end_date']));
$status = $coupon['status'];
$show_to_users = (int) ($coupon['show_to_users'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $code = strtoupper(trim($_POST['code'] ?? ''));
    $occasion = trim($_POST['occasion'] ?? '');
    $discount_type = $_POST['discount_type'] ?? '';
    $discount_value = trim($_POST['discount_value'] ?? '');
    $min_order_amount = trim($_POST['min_order_amount'] ?? '0');
    $max_uses = trim($_POST['max_uses'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $status = $_POST['status'] ?? '';
    $show_to_users = isset($_POST['show_to_users']) ? 1 : 0;

    if ($code === '') {

        $error = 'Please enter coupon code.';
    } elseif (strlen($code) < 3 || strlen($code) > 50) {

        $error = 'Coupon code must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[A-Z0-9_-]+$/', $code)) {

        $error = 'Coupon code can only contain letters, numbers, hyphens and underscores.';
    } elseif ($occasion === '') {

        $error = 'Please select an occasion.';
    } elseif (!in_array($discount_type, ['percentage', 'fixed'], true)) {

        $error = 'Invalid discount type.';
    } elseif (
        $discount_value === '' ||
        !is_numeric($discount_value) ||
        (float) $discount_value <= 0
    ) {

        $error = 'Please enter a valid discount value.';
    } elseif (
        $discount_type === 'percentage' &&
        (float) $discount_value > 100
    ) {

        $error = 'Percentage discount cannot be greater than 100%.';
    } elseif (
        $min_order_amount === '' ||
        !is_numeric($min_order_amount) ||
        (float) $min_order_amount < 0
    ) {

        $error = 'Please enter a valid minimum order amount.';
    } elseif (
        $max_uses !== '' &&
        (!ctype_digit($max_uses) || (int) $max_uses < 1)
    ) {

        $error = 'Max uses must be a positive number.';
    } elseif ($start_date === '') {

        $error = 'Please select start date.';
    } elseif ($end_date === '') {

        $error = 'Please select end date.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {

        $error = 'End date must be after start date.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = 'Invalid status.';
    } else {

        $check = $pdo->prepare("
            SELECT id
            FROM coupons
            WHERE code = ?
            AND id != ?
            LIMIT 1
        ");

        $check->execute([$code, $id]);

        if ($check->fetch()) {

            $error = 'This coupon code already exists.';
        } else {

            $update = $pdo->prepare("
                UPDATE coupons
                SET
                    code = ?,
                    occasion = ?,
                    discount_type = ?,
                    discount_value = ?,
                    min_order_amount = ?,
                    max_uses = ?,
                    start_date = ?,
                    end_date = ?,
                    status = ?,
                    show_to_users = ?
                WHERE id = ?
            ");

            $update->execute([
                $code,
                $occasion,
                $discount_type,
                (float) $discount_value,
                (float) $min_order_amount,
                $max_uses === '' ? null : (int) $max_uses,
                $start_date,
                $end_date,
                $status,
                $show_to_users,
                $id
            ]);

            $_SESSION['coupon_success'] = 'Coupon updated successfully.';

            header('Location: coupons.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/admin_sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <h1 class="h3 mb-0 text-gray-800">
            Edit Coupon
        </h1>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <?php echo htmlspecialchars($error); ?>

            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>

        </div>

    <?php endif; ?>

    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                Edit Coupon Information
            </h6>

        </div>

        <div class="card-body">

            <form method="POST">

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label for="code">
                            Coupon Code
                        </label>

                        <input
                            type="text"
                            name="code"
                            id="code"
                            class="form-control"
                            value="<?php echo htmlspecialchars($code); ?>"
                            required>

                    </div>

                    <div class="form-group col-md-6">

                        <label for="occasion">
                            Occasion
                        </label>

                        <select
                            name="occasion"
                            id="occasion"
                            class="form-control"
                            required>

                            <option value="">
                                Select Occasion
                            </option>

                            <option
                                value="New Year"
                                <?php echo $occasion === 'New Year' ? 'selected' : ''; ?>>
                                🎉 New Year
                            </option>

                            <option
                                value="Valentine's Day"
                                <?php echo $occasion === "Valentine's Day" ? 'selected' : ''; ?>>
                                ❤️ Valentine's Day
                            </option>

                            <option
                                value="Ramadan"
                                <?php echo $occasion === 'Ramadan' ? 'selected' : ''; ?>>
                                🌙 Ramadan
                            </option>

                            <option
                                value="Eid"
                                <?php echo $occasion === 'Eid' ? 'selected' : ''; ?>>
                                🕌 Eid
                            </option>

                            <option
                                value="Mother's Day"
                                <?php echo $occasion === "Mother's Day" ? 'selected' : ''; ?>>
                                💐 Mother's Day
                            </option>

                            <option
                                value="Back to School"
                                <?php echo $occasion === 'Back to School' ? 'selected' : ''; ?>>
                                🎒 Back to School
                            </option>

                            <option
                                value="Halloween"
                                <?php echo $occasion === 'Halloween' ? 'selected' : ''; ?>>
                                🎃 Halloween
                            </option>

                            <option
                                value="Black Friday"
                                <?php echo $occasion === 'Black Friday' ? 'selected' : ''; ?>>
                                🛍️ Black Friday
                            </option>

                            <option
                                value="Christmas"
                                <?php echo $occasion === 'Christmas' ? 'selected' : ''; ?>>
                                🎄 Christmas
                            </option>

                            <option
                                value="Birthday"
                                <?php echo $occasion === 'Birthday' ? 'selected' : ''; ?>>
                                🎂 Birthday
                            </option>

                            <option
                                value="Special Offer"
                                <?php echo $occasion === 'Special Offer' ? 'selected' : ''; ?>>
                                ⭐ Special Offer
                            </option>

                        </select>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label for="discount_type">
                            Discount Type
                        </label>

                        <select
                            name="discount_type"
                            id="discount_type"
                            class="form-control">

                            <option
                                value="percentage"
                                <?php echo $discount_type === 'percentage' ? 'selected' : ''; ?>>
                                Percentage (%)
                            </option>

                            <option
                                value="fixed"
                                <?php echo $discount_type === 'fixed' ? 'selected' : ''; ?>>
                                Fixed Amount
                            </option>

                        </select>

                    </div>

                    <div class="form-group col-md-6">

                        <label for="discount_value">
                            Discount Value
                        </label>

                        <input
                            type="number"
                            name="discount_value"
                            id="discount_value"
                            class="form-control"
                            value="<?php echo htmlspecialchars($discount_value); ?>"
                            min="0"
                            step="0.01"
                            required>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label for="min_order_amount">
                            Minimum Order Amount
                        </label>

                        <input
                            type="number"
                            name="min_order_amount"
                            id="min_order_amount"
                            class="form-control"
                            value="<?php echo htmlspecialchars($min_order_amount); ?>"
                            min="0"
                            step="0.01">

                    </div>

                    <div class="form-group col-md-6">

                        <label for="max_uses">
                            Maximum Uses
                        </label>

                        <input
                            type="number"
                            name="max_uses"
                            id="max_uses"
                            class="form-control"
                            value="<?php echo htmlspecialchars($max_uses ?? ''); ?>"
                            min="1"
                            step="1"
                            placeholder="Leave empty for unlimited">

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label for="start_date">
                            Start Date
                        </label>

                        <input
                            type="datetime-local"
                            name="start_date"
                            id="start_date"
                            class="form-control"
                            value="<?php echo htmlspecialchars($start_date); ?>"
                            required>

                    </div>

                    <div class="form-group col-md-6">

                        <label for="end_date">
                            End Date
                        </label>

                        <input
                            type="datetime-local"
                            name="end_date"
                            id="end_date"
                            class="form-control"
                            value="<?php echo htmlspecialchars($end_date); ?>"
                            required>

                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group col-md-6">

                        <label for="status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-control">

                            <option
                                value="active"
                                <?php echo $status === 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>

                            <option
                                value="inactive"
                                <?php echo $status === 'inactive' ? 'selected' : ''; ?>>
                                Inactive
                            </option>

                        </select>

                    </div>

                    <div class="form-group col-md-6">

                        <label>
                            Visibility
                        </label>

                        <div class="custom-control custom-checkbox mt-2">

                            <input
                                type="checkbox"
                                class="custom-control-input"
                                id="show_to_users"
                                name="show_to_users"
                                value="1"
                                <?php echo $show_to_users === 1 ? 'checked' : ''; ?>>

                            <label
                                class="custom-control-label"
                                for="show_to_users">
                                Show this coupon to users
                            </label>

                        </div>

                    </div>

                </div>

                <hr>

                <button
                    type="submit"
                    class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    Update Coupon
                </button>

                <a
                    href="coupons.php"
                    class="btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>