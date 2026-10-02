<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Order Details - Elkumanda';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: my-orders.php');
    exit;
}

$order_stmt = $pdo->prepare("
    SELECT
        id,
        phone,
        address,
        total,
        coupon_code,
        coupon_discount,
        status,
        created_at
    FROM orders
    WHERE id = ?
    AND user_id = ?
    LIMIT 1
");

$order_stmt->execute([
    $order_id,
    $user_id
]);

$order = $order_stmt->fetch();

if (!$order) {
    header('Location: my-orders.php');
    exit;
}

$items_stmt = $pdo->prepare("
    SELECT
        oi.product_id,
        p.product_name,
        p.selling_price,
        p.discount_type,
        p.discount_value,
        p.image,
        COUNT(oi.id) AS quantity
    FROM order_item oi
    INNER JOIN products p ON p.id = oi.product_id
    WHERE oi.order_id = ?
    GROUP BY
        oi.product_id,
        p.product_name,
        p.selling_price,
        p.discount_type,
        p.discount_value,
        p.image
    ORDER BY MIN(oi.id) ASC
");

$items_stmt->execute([$order_id]);

$items = $items_stmt->fetchAll();

function getOrderFinalPrice($price, $discount_type, $discount_value)
{
    $price = (float) $price;
    $discount_value = (float) $discount_value;

    if ($discount_type === 'percentage' && $discount_value > 0) {
        $price -= ($price * $discount_value / 100);
    } elseif ($discount_type === 'fixed' && $discount_value > 0) {
        $price -= $discount_value;
    }

    return max(0, $price);
}

$items_subtotal = 0;

foreach ($items as $item) {

    $price = getOrderFinalPrice(
        $item['selling_price'],
        $item['discount_type'],
        $item['discount_value']
    );

    $quantity = (int) $item['quantity'];

    $items_subtotal += $price * $quantity;
}

$shipping = !empty($items) ? 50 : 0;

$order_total = (float) $order['total'];

$coupon_discount = (float) ($order['coupon_discount'] ?? 0);

$coupon_code = trim((string) ($order['coupon_code'] ?? ''));

/*
 * Subtotal is calculated from the final order total:
 *
 * Total = Subtotal - Coupon Discount + Shipping
 *
 * Therefore:
 *
 * Subtotal = Total - Shipping + Coupon Discount
 */
$summary_subtotal = $order_total - $shipping + $coupon_discount;

$summary_subtotal = max(0, $summary_subtotal);

$status = strtolower(trim($order['status']));

$statusClass = 'pending';

if ($status === 'processing') {
    $statusClass = 'processing';
} elseif ($status === 'completed') {
    $statusClass = 'completed';
} elseif ($status === 'cancelled') {
    $statusClass = 'cancelled';
}

?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section class="breadcrumb-section set-bg" data-setbg="img/breadcrumb.jpg">

    <div class="container">

        <div class="row">

            <div class="col-lg-12">

                <div class="breadcrumb__text">

                    <h2>Order Details</h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">
                            Home
                        </a>

                        <a href="my-orders.php">
                            My Orders
                        </a>

                        <span>
                            Order #<?php echo (int) $order['id']; ?>
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="order-details-section">

    <div class="container">

        <div class="order-details-header">

            <div>

                <span class="order-label">
                    ORDER NUMBER
                </span>

                <h2>
                    #<?php echo (int) $order['id']; ?>
                </h2>

                <p>
                    Placed on
                    <?php
                    echo date(
                        'd M Y - h:i A',
                        strtotime($order['created_at'])
                    );
                    ?>
                </p>

            </div>

            <div class="order-status <?php echo $statusClass; ?>">

                <?php if ($status === 'pending'): ?>

                    <i class="fa fa-clock-o"></i>

                <?php elseif ($status === 'processing'): ?>

                    <i class="fa fa-refresh"></i>

                <?php elseif ($status === 'completed'): ?>

                    <i class="fa fa-check"></i>

                <?php elseif ($status === 'cancelled'): ?>

                    <i class="fa fa-times"></i>

                <?php else: ?>

                    <i class="fa fa-info-circle"></i>

                <?php endif; ?>

                <?php echo htmlspecialchars(ucfirst($order['status'])); ?>

            </div>

        </div>

        <div class="row">

            <div class="col-lg-8">

                <div class="order-box">

                    <div class="order-box-title">

                        <h4>
                            Order Items
                        </h4>

                        <span>
                            <?php echo count($items); ?>
                            Product<?php echo count($items) !== 1 ? 's' : ''; ?>
                        </span>

                    </div>

                    <?php if (empty($items)): ?>

                        <div class="no-items">

                            <i class="fa fa-shopping-bag"></i>

                            <p>
                                No products found in this order.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="order-items">

                            <?php foreach ($items as $item): ?>

                                <?php

                                $price = getOrderFinalPrice(
                                    $item['selling_price'],
                                    $item['discount_type'],
                                    $item['discount_value']
                                );

                                $quantity = (int) $item['quantity'];

                                $subtotal = $price * $quantity;

                                $image = trim((string) $item['image']);

                                if ($image !== '') {

                                    $imagePath = '../uploads/products/' . $image;

                                    if (!file_exists(__DIR__ . '/../uploads/products/' . $image)) {
                                        $imagePath = 'img/product/product-1.jpg';
                                    }
                                } else {

                                    $imagePath = 'img/product/product-1.jpg';
                                }

                                ?>

                                <div class="order-item">

                                    <div class="order-item-image">

                                        <img
                                            src="<?php echo htmlspecialchars($imagePath); ?>"
                                            alt="<?php echo htmlspecialchars($item['product_name']); ?>">

                                    </div>

                                    <div class="order-item-info">

                                        <h5>
                                            <?php echo htmlspecialchars($item['product_name']); ?>
                                        </h5>

                                        <p>
                                            EGP <?php echo number_format($price, 2); ?>
                                            ×
                                            <?php echo $quantity; ?>
                                        </p>

                                    </div>

                                    <div class="order-item-total">

                                        EGP <?php echo number_format($subtotal, 2); ?>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <div class="col-lg-4">

                <div class="order-box order-summary-box">

                    <div class="order-box-title">

                        <h4>
                            Order Summary
                        </h4>

                    </div>

                    <div class="summary-row">

                        <span>
                            Subtotal
                        </span>

                        <strong>
                            EGP <?php echo number_format($summary_subtotal, 2); ?>
                        </strong>

                    </div>

                    <?php if ($coupon_discount > 0): ?>

                        <div class="summary-row coupon-row">

                            <span>
                                Coupon Discount

                                <?php if ($coupon_code !== ''): ?>

                                    <small>
                                        (<?php echo htmlspecialchars($coupon_code); ?>)
                                    </small>

                                <?php endif; ?>

                            </span>

                            <strong>
                                - EGP <?php echo number_format($coupon_discount, 2); ?>
                            </strong>

                        </div>

                    <?php endif; ?>

                    <div class="summary-row">

                        <span>
                            Shipping
                        </span>

                        <strong>
                            EGP <?php echo number_format($shipping, 2); ?>
                        </strong>

                    </div>

                    <div class="summary-divider"></div>

                    <div class="summary-total">

                        <span>
                            Total
                        </span>

                        <strong>
                            EGP <?php echo number_format($order_total, 2); ?>
                        </strong>

                    </div>

                </div>

                <div class="order-box customer-box">

                    <div class="order-box-title">

                        <h4>
                            Delivery Information
                        </h4>

                    </div>

                    <div class="customer-info">

                        <div class="customer-info-row">

                            <i class="fa fa-phone"></i>

                            <div>

                                <span>
                                    Phone
                                </span>

                                <strong>
                                    <?php echo htmlspecialchars($order['phone']); ?>
                                </strong>

                            </div>

                        </div>

                        <div class="customer-info-row">

                            <i class="fa fa-map-marker"></i>

                            <div>

                                <span>
                                    Address
                                </span>

                                <strong>
                                    <?php echo nl2br(htmlspecialchars($order['address'])); ?>
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="order-back">

            <a href="my-orders.php">

                <i class="fa fa-arrow-left"></i>

                BACK TO MY ORDERS

            </a>

        </div>

    </div>

</section>

<style>
    .order-details-section {
        padding: 70px 0 80px;
        background: #f8f8f8;
    }

    .order-details-header {
        max-width: 1000px;
        margin: 0 auto 30px;
        background: #ffffff;
        border: 1px solid #eeeeee;
        border-radius: 6px;
        padding: 28px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
    }

    .order-label {
        display: block;
        color: #999999;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }

    .order-details-header h2 {
        margin: 0 0 6px;
        font-size: 26px;
        font-weight: 700;
    }

    .order-details-header p {
        margin: 0;
        color: #888888;
        font-size: 13px;
    }

    .order-status {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 16px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 700;
    }

    .order-status.pending {
        background: #fff3cd;
        color: #856404;
    }

    .order-status.processing {
        background: #cfe2ff;
        color: #084298;
    }

    .order-status.completed {
        background: #d1e7dd;
        color: #0f5132;
    }

    .order-status.cancelled {
        background: #f8d7da;
        color: #842029;
    }

    .order-box {
        background: #ffffff;
        border: 1px solid #eeeeee;
        border-radius: 6px;
        margin-bottom: 25px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
        overflow: hidden;
    }

    .order-box-title {
        padding: 22px 25px;
        border-bottom: 1px solid #eeeeee;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .order-box-title h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 700;
    }

    .order-box-title span {
        color: #999999;
        font-size: 12px;
    }

    .order-item {
        display: flex;
        align-items: center;
        padding: 20px 25px;
        border-bottom: 1px solid #eeeeee;
    }

    .order-item:last-child {
        border-bottom: 0;
    }

    .order-item-image {
        width: 75px;
        height: 75px;
        flex-shrink: 0;
        margin-right: 18px;
        border: 1px solid #eeeeee;
        border-radius: 4px;
        overflow: hidden;
    }

    .order-item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .order-item-info {
        flex: 1;
    }

    .order-item-info h5 {
        margin: 0 0 8px;
        font-size: 15px;
        font-weight: 700;
    }

    .order-item-info p {
        margin: 0;
        color: #888888;
        font-size: 13px;
    }

    .order-item-total {
        margin-left: 20px;
        font-size: 15px;
        font-weight: 700;
        color: #222222;
    }

    .order-summary-box {
        padding-bottom: 5px;
    }

    .summary-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        padding: 15px 25px 5px;
        color: #777777;
        font-size: 14px;
    }

    .summary-row strong {
        color: #222222;
        white-space: nowrap;
    }

    .summary-row small {
        color: #7fad39;
        font-size: 11px;
        font-weight: 700;
    }

    .summary-row.coupon-row {
        color: #7fad39;
    }

    .summary-row.coupon-row strong {
        color: #7fad39;
    }

    .summary-divider {
        height: 1px;
        background: #eeeeee;
        margin: 18px 25px;
    }

    .summary-total {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 5px 25px 20px;
    }

    .summary-total span {
        font-size: 16px;
        font-weight: 700;
    }

    .summary-total strong {
        color: #7fad39;
        font-size: 20px;
        font-weight: 700;
    }

    .customer-box {
        padding-bottom: 10px;
    }

    .customer-info {
        padding: 20px 25px;
    }

    .customer-info-row {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 22px;
    }

    .customer-info-row:last-child {
        margin-bottom: 5px;
    }

    .customer-info-row>i {
        width: 22px;
        color: #7fad39;
        font-size: 17px;
        text-align: center;
        margin-top: 3px;
    }

    .customer-info-row span {
        display: block;
        color: #999999;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .7px;
        margin-bottom: 5px;
    }

    .customer-info-row strong {
        display: block;
        color: #333333;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.6;
    }

    .no-items {
        text-align: center;
        padding: 50px 20px;
        color: #999999;
    }

    .no-items i {
        font-size: 45px;
        color: #7fad39;
        margin-bottom: 15px;
    }

    .no-items p {
        margin: 0;
    }

    .order-back {
        max-width: 1000px;
        margin: 5px auto 0;
    }

    .order-back a {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #7fad39;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
    }

    .order-back a:hover {
        color: #6f9c32;
    }

    @media (max-width: 767px) {

        .order-details-section {
            padding: 50px 0;
        }

        .order-details-header {
            padding: 22px 18px;
            display: block;
        }

        .order-status {
            margin-top: 18px;
        }

        .order-item {
            padding: 18px;
            align-items: flex-start;
        }

        .order-item-image {
            width: 65px;
            height: 65px;
            margin-right: 12px;
        }

        .order-item-info h5 {
            font-size: 14px;
        }

        .order-item-total {
            margin-left: 8px;
            font-size: 13px;
        }

        .order-box-title {
            padding: 20px 18px;
        }

        .summary-row,
        .summary-total,
        .customer-info {
            padding-left: 18px;
            padding-right: 18px;
        }

        .summary-divider {
            margin-left: 18px;
            margin-right: 18px;
        }

    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>