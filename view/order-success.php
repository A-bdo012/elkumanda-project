<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Order Success - Elkumanda';

if (!isset($_SESSION['user_id'])) {
    header('Location: register.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: shop.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, phone, address, total, status, created_at
    FROM orders
    WHERE id = ? AND user_id = ?
    LIMIT 1
");

$stmt->execute([$order_id, $user_id]);

$order = $stmt->fetch();

if (!$order) {
    $order = null;
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
    .order-success-section {
        padding: 80px 0;
        background: #f7f7f7;
    }

    .success-box {
        max-width: 750px;
        margin: 0 auto;
        background: #fff;
        padding: 45px;
        text-align: center;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.06);
    }

    .success-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 25px;
        border-radius: 50%;
        background: #7fad39;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 38px;
    }

    .success-box h2 {
        font-weight: 700;
        color: #1c1c1c;
        margin-bottom: 12px;
    }

    .success-box>p {
        color: #666;
        margin-bottom: 30px;
        font-size: 16px;
    }

    .order-info {
        text-align: left;
        border: 1px solid #ebebeb;
        padding: 25px;
        margin-bottom: 30px;
    }

    .order-info h4 {
        font-weight: 700;
        margin-bottom: 20px;
        color: #1c1c1c;
    }

    .order-info-row {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 12px 0;
        border-bottom: 1px solid #f1f1f1;
    }

    .order-info-row:last-child {
        border-bottom: none;
    }

    .order-info-row span:first-child {
        color: #666;
    }

    .order-info-row span:last-child {
        font-weight: 600;
        color: #1c1c1c;
        text-align: right;
    }

    .order-total {
        color: #7fad39 !important;
        font-size: 18px;
    }

    .order-status {
        color: #7fad39 !important;
        text-transform: capitalize;
    }

    .order-buttons {
        display: flex;
        justify-content: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .order-buttons a {
        display: inline-block;
        padding: 12px 28px;
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
        color: #fff;
        background: #7fad39;
        border: 1px solid #7fad39;
        transition: all 0.3s;
    }

    .order-buttons a:hover {
        background: #6d9630;
        color: #fff;
    }

    .order-buttons a.secondary {
        background: #fff;
        color: #7fad39;
    }

    .order-buttons a.secondary:hover {
        background: #7fad39;
        color: #fff;
    }

    .error-box .success-icon {
        background: #dc3545;
    }

    .error-box h2 {
        margin-bottom: 20px;
    }

    @media (max-width: 576px) {

        .success-box {
            padding: 30px 20px;
        }

        .order-info {
            padding: 20px 15px;
        }

        .order-info-row {
            flex-direction: column;
            gap: 5px;
        }

        .order-info-row span:last-child {
            text-align: left;
        }

    }
</style>

<section class="breadcrumb-section set-bg" style="background-image: url('img/breadcrumb.jpg');">

    <div class="container">

        <div class="row">

            <div class="col-lg-12 text-center">

                <div class="breadcrumb__text">

                    <h2>
                        Order Success
                    </h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">
                            Home
                        </a>

                        <span>
                            Order Success
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="order-success-section">

    <div class="container">

        <?php if ($order): ?>

            <div class="success-box">

                <div class="success-icon">
                    <i class="fa fa-check"></i>
                </div>

                <h2>
                    Order Placed Successfully!
                </h2>

                <p>
                    Thank you for your order. Your order has been received successfully.
                </p>

                <div class="order-info">

                    <h4>
                        Order Information
                    </h4>

                    <div class="order-info-row">

                        <span>
                            Order Number
                        </span>

                        <span>
                            #<?php echo htmlspecialchars($order['id']); ?>
                        </span>

                    </div>

                    <div class="order-info-row">

                        <span>
                            Phone
                        </span>

                        <span>
                            <?php echo htmlspecialchars($order['phone']); ?>
                        </span>

                    </div>

                    <div class="order-info-row">

                        <span>
                            Address
                        </span>

                        <span>
                            <?php echo htmlspecialchars($order['address']); ?>
                        </span>

                    </div>

                    <div class="order-info-row">

                        <span>
                            Total
                        </span>

                        <span class="order-total">
                            EGP <?php echo number_format((float) $order['total'], 2); ?>
                        </span>

                    </div>

                    <div class="order-info-row">

                        <span>
                            Status
                        </span>

                        <span class="order-status">
                            <?php echo htmlspecialchars($order['status']); ?>
                        </span>

                    </div>

                    <div class="order-info-row">

                        <span>
                            Order Date
                        </span>

                        <span>
                            <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                        </span>

                    </div>

                </div>

                <div class="order-buttons">

                    <a href="shop.php">
                        Continue Shopping
                    </a>

                    <a href="index.php" class="secondary">
                        Back to Home
                    </a>

                </div>

            </div>

        <?php else: ?>

            <div class="success-box error-box">

                <div class="success-icon">
                    <i class="fa fa-times"></i>
                </div>

                <h2>
                    Order Not Found
                </h2>

                <p>
                    We couldn't find this order or you don't have permission to view it.
                </p>

                <div class="order-buttons">

                    <a href="shop.php">
                        Continue Shopping
                    </a>

                    <a href="index.php" class="secondary">
                        Back to Home
                    </a>

                </div>

            </div>

        <?php endif; ?>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>