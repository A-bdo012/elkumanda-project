<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/notifications.php';

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($order_id <= 0) {
    die('Invalid order ID.');
}

$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.user_id,
        o.phone,
        o.address,
        o.total,
        o.coupon_code,
        o.coupon_discount,
        o.status,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
    LIMIT 1
");

$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found.');
}

$stmt = $pdo->prepare("
    SELECT
        oi.product_id,
        p.product_name,
        p.image,
        p.selling_price,
        p.discount_type,
        p.discount_value,
        COUNT(oi.id) AS quantity
    FROM order_item oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
    GROUP BY
        oi.product_id,
        p.product_name,
        p.image,
        p.selling_price,
        p.discount_type,
        p.discount_value
    ORDER BY oi.product_id ASC
");

$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

function getDiscountedPrice($price, $discount_type, $discount_value)
{
    $price = (float) $price;
    $discount_value = (float) $discount_value;

    if ($discount_type === 'percentage' && $discount_value > 0) {
        $price = $price - ($price * $discount_value / 100);
    } elseif ($discount_type === 'fixed' && $discount_value > 0) {
        $price = $price - $discount_value;
    }

    return max(0, $price);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $new_status = $_POST['status'] ?? '';

    $allowed_statuses = [
        'pending',
        'processing',
        'shipped',
        'completed',
        'cancelled'
    ];

    if (in_array($new_status, $allowed_statuses, true)) {

        $stmt = $pdo->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $new_status,
            $order_id
        ]);

        header("Location: order-details.php?id=" . $order_id . "&updated=1");
        exit;
    }
}

$page_title = 'Order Details';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/admin_sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Order #<?= htmlspecialchars($order['id']) ?>
            </h1>

            <p class="mb-0 text-muted">
                Order details and customer information
            </p>

        </div>

        <a href="orders.php" class="btn btn-secondary">

            <i class="fas fa-arrow-left"></i>
            Back to Orders

        </a>

    </div>


    <?php if (isset($_GET['updated'])): ?>

        <div class="alert alert-success">
            Order status updated successfully.
        </div>

    <?php endif; ?>


    <div class="row">

        <div class="col-lg-6 mb-4">

            <div class="card shadow">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Customer Information
                    </h6>

                </div>

                <div class="card-body">

                    <p>
                        <strong>Name:</strong>
                        <?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?>
                    </p>

                    <p>
                        <strong>Email:</strong>
                        <?= htmlspecialchars($order['customer_email'] ?? '-') ?>
                    </p>

                    <p>
                        <strong>Phone:</strong>
                        <?= htmlspecialchars($order['phone'] ?? '-') ?>
                    </p>

                    <p class="mb-0">
                        <strong>Address:</strong>
                        <?= htmlspecialchars($order['address'] ?? '-') ?>
                    </p>

                </div>

            </div>

        </div>


        <div class="col-lg-6 mb-4">

            <div class="card shadow">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Order Information
                    </h6>

                </div>

                <div class="card-body">

                    <p>
                        <strong>Order ID:</strong>
                        #<?= htmlspecialchars($order['id']) ?>
                    </p>

                    <p>
                        <strong>Total:</strong>
                        <?= number_format((float) $order['total'], 2) ?> EGP
                    </p>

                    <p>
                        <strong>Date:</strong>
                        <?= htmlspecialchars($order['created_at']) ?>
                    </p>


                    <form method="POST">

                        <label class="font-weight-bold">
                            Status
                        </label>

                        <div class="input-group">

                            <select name="status" class="form-control">

                                <?php

                                $statuses = [
                                    'pending',
                                    'processing',
                                    'shipped',
                                    'completed',
                                    'cancelled'
                                ];

                                ?>

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= $status ?>"
                                        <?= ($order['status'] === $status) ? 'selected' : '' ?>>

                                        <?= ucfirst($status) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="input-group-append">

                                <button type="submit" class="btn btn-primary">
                                    Update
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>


    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                Order Products
            </h6>

        </div>

        <div class="card-body">

            <?php if (empty($items)): ?>

                <div class="alert alert-warning mb-0">
                    No products found for this order.
                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-bordered">

                        <thead>

                            <tr>

                                <th>Image</th>
                                <th>Product</th>
                                <th>Original Price</th>
                                <th>Discount</th>
                                <th>Final Price</th>
                                <th>Quantity</th>
                                <th>Total</th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($items as $item): ?>

                                <?php

                                $original_price = (float) $item['selling_price'];

                                $discount_type = strtolower(
                                    trim($item['discount_type'] ?? 'none')
                                );

                                $discount_value = (float) (
                                    $item['discount_value'] ?? 0
                                );

                                $quantity = (int) $item['quantity'];

                                $final_price = getDiscountedPrice(
                                    $original_price,
                                    $discount_type,
                                    $discount_value
                                );

                                $item_total = $final_price * $quantity;

                                ?>

                                <tr>

                                    <td style="width: 80px;">

                                        <?php if (!empty($item['image'])): ?>

                                            <img
                                                src="../uploads/products/<?= htmlspecialchars($item['image']) ?>"
                                                alt=""
                                                style="width:60px;height:60px;object-fit:cover;">

                                        <?php else: ?>

                                            <span class="text-muted">
                                                No Image
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $item['product_name'] ?? 'Deleted Product'
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= number_format(
                                            $original_price,
                                            2
                                        ) ?> EGP

                                    </td>


                                    <td>

                                        <?php if (
                                            $discount_type === 'percentage'
                                            && $discount_value > 0
                                        ): ?>

                                            <span class="badge badge-danger">

                                                <?= number_format(
                                                    $discount_value,
                                                    2
                                                ) ?>% OFF

                                            </span>

                                        <?php elseif (
                                            $discount_type === 'fixed'
                                            && $discount_value > 0
                                        ): ?>

                                            <span class="badge badge-danger">

                                                <?= number_format(
                                                    $discount_value,
                                                    2
                                                ) ?> EGP OFF

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                No Discount
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($final_price < $original_price): ?>

                                            <span
                                                class="text-muted"
                                                style="text-decoration: line-through;">

                                                <?= number_format(
                                                    $original_price,
                                                    2
                                                ) ?> EGP

                                            </span>

                                            <br>

                                            <strong class="text-success">

                                                <?= number_format(
                                                    $final_price,
                                                    2
                                                ) ?> EGP

                                            </strong>

                                        <?php else: ?>

                                            <strong>

                                                <?= number_format(
                                                    $final_price,
                                                    2
                                                ) ?> EGP

                                            </strong>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= $quantity ?>

                                    </td>


                                    <td>

                                        <strong>

                                            <?= number_format(
                                                $item_total,
                                                2
                                            ) ?> EGP

                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <?php

                $items_subtotal = 0;

                foreach ($items as $item) {

                    $original_price = (float) $item['selling_price'];

                    $discount_type = strtolower(
                        trim($item['discount_type'] ?? 'none')
                    );

                    $discount_value = (float) (
                        $item['discount_value'] ?? 0
                    );

                    $quantity = (int) $item['quantity'];

                    $final_price = getDiscountedPrice(
                        $original_price,
                        $discount_type,
                        $discount_value
                    );

                    $items_subtotal += $final_price * $quantity;
                }


                $shipping = 50;

                $order_total = (float) $order['total'];

                $coupon_code = trim(
                    (string) ($order['coupon_code'] ?? '')
                );

                $coupon_discount = (float) (
                    $order['coupon_discount'] ?? 0
                );

                ?>


                <div class="row justify-content-end mt-4">

                    <div class="col-md-5">

                        <div class="card border-left-primary shadow-sm">

                            <div class="card-body">


                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Subtotal
                                    </span>

                                    <strong>
                                        <?= number_format(
                                            $items_subtotal,
                                            2
                                        ) ?> EGP
                                    </strong>

                                </div>


                                <?php if ($coupon_discount > 0): ?>

                                    <div class="d-flex justify-content-between mb-2">

                                        <span>

                                            Coupon Discount

                                            <?php if ($coupon_code !== ''): ?>

                                                <small class="text-muted">

                                                    (
                                                    <?= htmlspecialchars(
                                                        $coupon_code
                                                    ) ?>
                                                    )

                                                </small>

                                            <?php endif; ?>

                                        </span>

                                        <strong class="text-danger">

                                            -
                                            <?= number_format(
                                                $coupon_discount,
                                                2
                                            ) ?> EGP

                                        </strong>

                                    </div>

                                <?php endif; ?>


                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Shipping
                                    </span>

                                    <strong>

                                        <?= number_format(
                                            $shipping,
                                            2
                                        ) ?> EGP

                                    </strong>

                                </div>


                                <hr>


                                <div class="d-flex justify-content-between">

                                    <strong>
                                        Total
                                    </strong>

                                    <strong class="text-primary">

                                        <?= number_format(
                                            $order_total,
                                            2
                                        ) ?> EGP

                                    </strong>

                                </div>


                            </div>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php

require_once __DIR__ . '/includes/footer.php';

?>