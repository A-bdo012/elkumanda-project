<?php

require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/../admin/includes/notifications.php';

$pageTitle = 'Checkout - Elkumanda';

if (!isset($_SESSION['user_id'])) {

    header('Location: register.php');

    exit;
}

$user_id = (int) $_SESSION['user_id'];

$shipping_cost = 50;

$user_stmt = $pdo->prepare("

    SELECT id, name, email

    FROM users

    WHERE id = ?

");

$user_stmt->execute([$user_id]);

$user = $user_stmt->fetch();

if (!$user) {

    session_destroy();

    header('Location: register.php');

    exit;
}

$cart_stmt = $pdo->prepare("

    SELECT id

    FROM cart

    WHERE user_id = ?

    LIMIT 1

");

$cart_stmt->execute([$user_id]);

$cart = $cart_stmt->fetch();

if (!$cart) {

    header('Location: shopping-cart.php');

    exit;
}

$cart_id = (int) $cart['id'];

function getFinalPrice($price, $discount_type, $discount_value)

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

$error = '';

$items_stmt = $pdo->prepare("

    SELECT

        ci.product_id,

        ci.quantity,

        p.product_name,

        p.selling_price,

        p.discount_type,

        p.discount_value,

        p.stock_quantity,

        p.status,

        p.image

    FROM cart_items ci

    INNER JOIN products p ON p.id = ci.product_id

    WHERE ci.cart_id = ?

    ORDER BY ci.id DESC

");

$items_stmt->execute([$cart_id]);

$cart_items = $items_stmt->fetchAll();

$valid_items = [];

$subtotal = 0;

foreach ($cart_items as $item) {

    if ($item['status'] !== 'active') {

        continue;
    }

    if ((int) $item['stock_quantity'] <= 0) {

        continue;
    }

    $quantity = (int) $item['quantity'];

    if ($quantity <= 0) {

        continue;
    }

    if ($quantity > (int) $item['stock_quantity']) {

        $quantity = (int) $item['stock_quantity'];
    }

    $price = getFinalPrice(

        $item['selling_price'],

        $item['discount_type'],

        $item['discount_value']

    );

    $item_subtotal = $price * $quantity;

    $subtotal += $item_subtotal;

    $item['quantity'] = $quantity;

    $item['final_price'] = $price;

    $item['subtotal'] = $item_subtotal;

    $valid_items[] = $item;
}

if (empty($valid_items)) {

    header('Location: shopping-cart.php');

    exit;
}

$applied_coupon = $_SESSION['applied_coupon'] ?? null;
$coupon_discount = 0;
$coupon_data = null;

if ($applied_coupon) {
    $coupon_stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? LIMIT 1");
    $coupon_stmt->execute([(int) $applied_coupon['id']]);
    $coupon_data = $coupon_stmt->fetch();

    if (
        !$coupon_data ||
        $coupon_data['status'] !== 'active' ||
        strtotime($coupon_data['start_date']) > time() ||
        strtotime($coupon_data['end_date']) < time() ||
        ($coupon_data['max_uses'] !== null && (int) $coupon_data['used_count'] >= (int) $coupon_data['max_uses']) ||
        $subtotal < (float) $coupon_data['min_order_amount']
    ) {
        unset($_SESSION['applied_coupon']);
        $applied_coupon = null;
        $coupon_data = null;
    } else {
        if ($coupon_data['discount_type'] === 'percentage') {
            $coupon_discount = $subtotal * ((float) $coupon_data['discount_value'] / 100);
        } else {
            $coupon_discount = (float) $coupon_data['discount_value'];
        }
        $coupon_discount = min($coupon_discount, $subtotal);
    }
}

$total = max(0, $subtotal - $coupon_discount) + $shipping_cost;

$phone = '';

$address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $phone = trim($_POST['phone'] ?? '');

    $address = trim($_POST['address'] ?? '');

    if ($phone === '') {

        $error = 'Please enter your phone number.';
    } elseif (strlen($phone) > 30) {

        $error = 'Phone number is too long.';
    } elseif ($address === '') {

        $error = 'Please enter your address.';
    } elseif (strlen($address) > 255) {

        $error = 'Address is too long.';
    } else {

        try {

            $pdo->beginTransaction();

            $locked_stmt = $pdo->prepare("

                SELECT

                    ci.product_id,

                    ci.quantity,

                    p.product_name,

                    p.selling_price,

                    p.discount_type,

                    p.discount_value,

                    p.stock_quantity,

                    p.low_stock_alert,

                    p.vendor_id,

                    p.status

                FROM cart_items ci

                INNER JOIN products p ON p.id = ci.product_id

                WHERE ci.cart_id = ?

                FOR UPDATE

            ");

            $locked_stmt->execute([$cart_id]);

            $locked_items = $locked_stmt->fetchAll();

            if (empty($locked_items)) {

                throw new Exception('Your cart is empty.');
            }

            $order_subtotal = 0;

            foreach ($locked_items as $item) {

                if ($item['status'] !== 'active') {

                    throw new Exception(

                        'One of the products in your cart is no longer available.'

                    );
                }

                $quantity = (int) $item['quantity'];

                $stock = (int) $item['stock_quantity'];

                if ($quantity <= 0) {

                    throw new Exception('Invalid product quantity.');
                }

                if ($stock < $quantity) {

                    throw new Exception(

                        'Not enough stock for: ' . $item['product_name']

                    );
                }

                $price = getFinalPrice(

                    $item['selling_price'],

                    $item['discount_type'],

                    $item['discount_value']

                );

                $order_subtotal += $price * $quantity;
            }

            $order_coupon_discount = 0;
            $order_coupon = null;

            if ($applied_coupon) {
                $order_coupon_stmt = $pdo->prepare("
        SELECT *
        FROM coupons
        WHERE id = ?
        FOR UPDATE
    ");
                $order_coupon_stmt->execute([(int) $applied_coupon['id']]);
                $order_coupon = $order_coupon_stmt->fetch();

                if (
                    !$order_coupon ||
                    $order_coupon['status'] !== 'active' ||
                    strtotime($order_coupon['start_date']) > time() ||
                    strtotime($order_coupon['end_date']) < time() ||
                    ($order_coupon['max_uses'] !== null && (int) $order_coupon['used_count'] >= (int) $order_coupon['max_uses']) ||
                    $order_subtotal < (float) $order_coupon['min_order_amount']
                ) {
                    throw new Exception('The applied coupon is no longer valid.');
                }

                if ($order_coupon['discount_type'] === 'percentage') {
                    $order_coupon_discount = $order_subtotal * ((float) $order_coupon['discount_value'] / 100);
                } else {
                    $order_coupon_discount = (float) $order_coupon['discount_value'];
                }

                $order_coupon_discount = min($order_coupon_discount, $order_subtotal);
            }

            $order_total = max(0, $order_subtotal - $order_coupon_discount) + $shipping_cost;

            $order_stmt = $pdo->prepare("
    INSERT INTO orders (
        user_id,
        phone,
        address,
        total,
        coupon_code,
        coupon_discount,
        status
    )
    VALUES (?, ?, ?, ?, ?, ?, 'pending')
");

            $order_stmt->execute([
                $user_id,
                $phone,
                $address,
                $order_total,
                $order_coupon ? $order_coupon['code'] : null,
                $order_coupon_discount
            ]);

            $order_id = (int) $pdo->lastInsertId();

            if ($order_coupon) {
                $coupon_update_stmt = $pdo->prepare("
        UPDATE coupons
        SET used_count = used_count + 1
        WHERE id = ?
    ");
                $coupon_update_stmt->execute([(int) $order_coupon['id']]);
                unset($_SESSION['applied_coupon']);
            }

            createNotification(

                $user_id,

                'Order Created',

                'Your order #' . $order_id . ' has been created successfully.',

                'order-success.php?id=' . $order_id

            );

            $admin_stmt = $pdo->query("

                SELECT id

                FROM users

                WHERE role = 'admin'

            ");

            $admins = $admin_stmt->fetchAll();

            foreach ($admins as $admin) {

                createNotification(

                    (int) $admin['id'],

                    'New Order',

                    'A new order #' . $order_id . ' has been created.',

                    'order-details.php?id=' . $order_id

                );
            }

            $order_item_stmt = $pdo->prepare("

                INSERT INTO order_item (

                    order_id,

                    product_id

                )

                VALUES (?, ?)

            ");

            $stock_stmt = $pdo->prepare("

                UPDATE products

                SET stock_quantity = stock_quantity - ?

                WHERE id = ?

            ");

            foreach ($locked_items as $item) {

                $product_id = (int) $item['product_id'];

                $quantity = (int) $item['quantity'];

                $current_stock = (int) $item['stock_quantity'];

                $low_stock_alert = (int) $item['low_stock_alert'];

                $vendor_id = (int) $item['vendor_id'];

                for ($i = 0; $i < $quantity; $i++) {

                    $order_item_stmt->execute([

                        $order_id,

                        $product_id

                    ]);
                }

                $stock_stmt->execute([

                    $quantity,

                    $product_id

                ]);

                $new_stock = $current_stock - $quantity;

                if ($new_stock <= $low_stock_alert) {

                    createNotification(

                        $vendor_id,

                        'Low Stock Alert',

                        'Product "' . $item['product_name'] . '" has reached the minimum stock level. Current stock: ' . $new_stock,

                        'my_products.php?search=' . urlencode($item['product_name'])

                    );
                }
            }

            $delete_items_stmt = $pdo->prepare("

                DELETE FROM cart_items

                WHERE cart_id = ?

            ");

            $delete_items_stmt->execute([$cart_id]);

            $pdo->commit();

            header('Location: order-success.php?id=' . $order_id);

            exit;
        } catch (Exception $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

?>

<?php include __DIR__ . '/includes/header.php'; ?>

<section class="breadcrumb-section set-bg" data-setbg="img/breadcrumb.jpg">

    <div class="container">

        <div class="row">

            <div class="col-lg-12">

                <div class="breadcrumb__text">

                    <h2>Checkout</h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">

                            Home

                        </a>

                        <span>

                            Checkout

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="checkout spad">

    <div class="container">

        <?php if ($error !== ''): ?>

            <div

                style="

                    background:#f8d7da;

                    color:#842029;

                    padding:15px 20px;

                    margin-bottom:30px;

                    border-radius:4px;

                    border:1px solid #f5c2c7;

                ">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>

        <div class="row">

            <div class="col-lg-8">

                <div class="checkout__form">

                    <h4>

                        Billing Details

                    </h4>

                    <form method="POST">

                        <div class="row">

                            <div class="col-lg-6">

                                <div class="checkout__input">

                                    <p>

                                        Full Name<span>*</span>

                                    </p>

                                    <input

                                        type="text"

                                        value="<?php echo htmlspecialchars($user['name']); ?>"

                                        readonly>

                                </div>

                            </div>

                            <div class="col-lg-6">

                                <div class="checkout__input">

                                    <p>

                                        Email<span>*</span>

                                    </p>

                                    <input

                                        type="email"

                                        value="<?php echo htmlspecialchars($user['email']); ?>"

                                        readonly>

                                </div>

                            </div>

                        </div>

                        <div class="checkout__input">

                            <p>

                                Phone<span>*</span>

                            </p>

                            <input

                                type="text"

                                name="phone"

                                value="<?php echo htmlspecialchars($phone); ?>"

                                placeholder="Enter your phone number"

                                required>

                        </div>

                        <div class="checkout__input">

                            <p>

                                Address<span>*</span>

                            </p>

                            <input

                                type="text"

                                name="address"

                                value="<?php echo htmlspecialchars($address); ?>"

                                placeholder="Enter your delivery address"

                                required>

                        </div>

                        <button

                            type="submit"

                            class="site-btn"

                            style="border:0; cursor:pointer;">

                            PLACE ORDER

                        </button>

                    </form>

                </div>

            </div>

            <div class="col-lg-4">

                <div class="checkout__order">

                    <h4>

                        Your Order

                    </h4>

                    <?php foreach ($valid_items as $item): ?>

                        <div class="checkout__order__products">

                            <span>

                                <?php echo htmlspecialchars($item['product_name']); ?>

                                × <?php echo (int) $item['quantity']; ?>

                            </span>

                            <span>

                                EGP <?php echo number_format((float) $item['subtotal'], 2); ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                    <ul>

                        <li>

                            Subtotal

                            <span>

                                EGP <?php echo number_format($subtotal, 2); ?>

                            </span>

                        </li>

                        <?php if ($coupon_discount > 0): ?>
                            <li>
                                Coupon Discount
                                <span>
                                    - EGP <?php echo number_format($coupon_discount, 2); ?>
                                </span>
                            </li>
                        <?php endif; ?>

                        <li>

                            Shipping

                            <span>

                                EGP <?php echo number_format($shipping_cost, 2); ?>

                            </span>

                        </li>

                        <li>

                            Total

                            <span>

                                EGP <?php echo number_format($total, 2); ?>

                            </span>

                        </li>

                    </ul>

                    <div

                        style="

                            margin-top:20px;

                            padding:15px;

                            background:#f7f7f7;

                            border-radius:4px;

                            font-size:13px;

                            color:#777;

                        ">

                        <i class="fa fa-truck"></i>

                        Cash on Delivery

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>