<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Shopping Cart - Elkumanda';

$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : 0;

$shipping_cost = 50;

$coupon_error = '';

$coupon_success = '';

$applied_coupon = $_SESSION['applied_coupon'] ?? null;

$coupon_discount = 0;

function productImageUrl($image)

{

    if (!$image) {

        return 'img/product/product-1.jpg';
    }

    $filename = basename($image);

    if (file_exists(__DIR__ . '/../uploads/products/' . $filename)) {

        return '../uploads/products/' . $filename;
    }

    return 'img/product/product-1.jpg';
}

function getProductPrice($product)

{

    $price = (float) $product['selling_price'];

    $discount_type = $product['discount_type'] ?? 'none';

    $discount_value = (float) ($product['discount_value'] ?? 0);

    if ($discount_type === 'percentage' && $discount_value > 0) {

        $price -= ($price * $discount_value / 100);
    } elseif ($discount_type === 'fixed' && $discount_value > 0) {

        $price -= $discount_value;
    }

    return max(0, $price);
}

function getGuestCart()

{

    if (empty($_COOKIE['guest_cart'])) {

        return [];
    }

    $cart = json_decode($_COOKIE['guest_cart'], true);

    if (!is_array($cart)) {

        return [];
    }

    $result = [];

    foreach ($cart as $product_id => $quantity) {

        $product_id = (int) $product_id;

        $quantity = (int) $quantity;

        if ($product_id > 0 && $quantity > 0) {

            $result[$product_id] = $quantity;
        }
    }

    return $result;
}

function saveGuestCart($cart)

{

    $cart = array_filter($cart, function ($quantity) {

        return (int) $quantity > 0;
    });

    setcookie(

        'guest_cart',

        json_encode($cart),

        time() + (7 * 24 * 60 * 60),

        '/',

        '',

        false,

        true

    );

    $_COOKIE['guest_cart'] = json_encode($cart);
}

function getUserCartId($pdo, $user_id)

{

    $stmt = $pdo->prepare("SELECT id FROM cart WHERE user_id = ?");

    $stmt->execute([$user_id]);

    $cart = $stmt->fetch();

    if ($cart) {

        return (int) $cart['id'];
    }

    $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");

    $stmt->execute([$user_id]);

    return (int) $pdo->lastInsertId();
}

$coupon_error = '';
$applied_coupon = $_SESSION['applied_coupon'] ?? null;
$coupon_discount = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['apply_coupon'])) {
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if ($code === '') {
            $coupon_error = 'Please enter a coupon code.';
        } else {
            $stmt = $pdo->prepare("
                SELECT *
                FROM coupons
                WHERE code = ?
                LIMIT 1
            ");
            $stmt->execute([$code]);
            $coupon = $stmt->fetch();

            if (!$coupon) {
                $coupon_error = 'Invalid coupon code.';
            } elseif ($coupon['status'] !== 'active') {
                $coupon_error = 'This coupon is inactive.';
            } elseif (strtotime($coupon['start_date']) > time()) {
                $coupon_error = 'This coupon is not active yet.';
            } elseif (strtotime($coupon['end_date']) < time()) {
                $coupon_error = 'This coupon has expired.';
            } elseif (
                $coupon['max_uses'] !== null &&
                (int) $coupon['used_count'] >= (int) $coupon['max_uses']
            ) {
                $coupon_error = 'This coupon has reached its usage limit.';
            } else {
                $_SESSION['applied_coupon'] = [
                    'id' => (int) $coupon['id'],
                    'code' => $coupon['code']
                ];

                header('Location: shopping-cart.php');
                exit;
            }
        }
    }

    if (isset($_POST['remove_coupon'])) {
        unset($_SESSION['applied_coupon']);
        header('Location: shopping-cart.php');
        exit;
    }

    if (isset($_POST['apply_coupon'])) {

        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));

        if ($code === '') {

            $coupon_error = 'Please enter a coupon code.';
        } else {

            $stmt = $pdo->prepare("

                SELECT *

                FROM coupons

                WHERE code = ?

                LIMIT 1

            ");

            $stmt->execute([$code]);

            $coupon = $stmt->fetch();

            if (!$coupon) {

                $coupon_error = 'Invalid coupon code.';
            } elseif ($coupon['status'] !== 'active') {

                $coupon_error = 'This coupon is inactive.';
            } elseif (strtotime($coupon['start_date']) > time()) {

                $coupon_error = 'This coupon is not active yet.';
            } elseif (strtotime($coupon['end_date']) < time()) {

                $coupon_error = 'This coupon has expired.';
            } elseif ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {

                $coupon_error = 'This coupon has reached its usage limit.';
            } else {

                $_SESSION['applied_coupon'] = [

                    'id' => (int) $coupon['id'],

                    'code' => $coupon['code']

                ];

                header('Location: shopping-cart.php');

                exit;
            }
        }
    }

    if (isset($_POST['remove_coupon'])) {

        unset($_SESSION['applied_coupon']);

        header('Location: shopping-cart.php');

        exit;
    }

    if (isset($_POST['add_to_cart'])) {

        $product_id = (int) ($_POST['product_id'] ?? 0);

        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $pdo->prepare("

            SELECT id, stock_quantity, status

            FROM products

            WHERE id = ?

            LIMIT 1

        ");

        $stmt->execute([$product_id]);

        $product = $stmt->fetch();

        if (!$product || $product['status'] !== 'active') {

            header('Location: shop.php');

            exit;
        }

        $stock = (int) $product['stock_quantity'];

        if ($stock <= 0) {

            header('Location: shop-details.php?id=' . $product_id . '&error=out_of_stock');

            exit;
        }

        $quantity = min($quantity, $stock);

        if ($is_logged_in) {

            $cart_id = getUserCartId($pdo, $user_id);

            $stmt = $pdo->prepare("

                SELECT quantity

                FROM cart_items

                WHERE cart_id = ? AND product_id = ?

                LIMIT 1

            ");

            $stmt->execute([

                $cart_id,

                $product_id

            ]);

            $existing = $stmt->fetch();

            if ($existing) {

                $new_quantity = min(

                    (int) $existing['quantity'] + $quantity,

                    $stock

                );

                $stmt = $pdo->prepare("

                    UPDATE cart_items

                    SET quantity = ?

                    WHERE cart_id = ? AND product_id = ?

                ");

                $stmt->execute([

                    $new_quantity,

                    $cart_id,

                    $product_id

                ]);
            } else {

                $stmt = $pdo->prepare("

                    INSERT INTO cart_items (cart_id, product_id, quantity)

                    VALUES (?, ?, ?)

                ");

                $stmt->execute([

                    $cart_id,

                    $product_id,

                    $quantity

                ]);
            }
        } else {

            $cart = getGuestCart();

            if (isset($cart[$product_id])) {

                $cart[$product_id] = min(

                    $cart[$product_id] + $quantity,

                    $stock

                );
            } else {

                $cart[$product_id] = $quantity;
            }

            saveGuestCart($cart);
        }

        header('Location: shopping-cart.php');

        exit;
    }

    if (isset($_POST['update_cart'])) {

        if ($is_logged_in) {

            $cart_id = getUserCartId($pdo, $user_id);

            if (!empty($_POST['quantities']) && is_array($_POST['quantities'])) {

                foreach ($_POST['quantities'] as $product_id => $quantity) {

                    $product_id = (int) $product_id;

                    $quantity = (int) $quantity;

                    if ($quantity <= 0) {

                        $stmt = $pdo->prepare("

                            DELETE FROM cart_items

                            WHERE cart_id = ? AND product_id = ?

                        ");

                        $stmt->execute([

                            $cart_id,

                            $product_id

                        ]);

                        continue;
                    }

                    $stmt = $pdo->prepare("

                        SELECT stock_quantity

                        FROM products

                        WHERE id = ?

                        LIMIT 1

                    ");

                    $stmt->execute([$product_id]);

                    $product = $stmt->fetch();

                    if (!$product) {

                        continue;
                    }

                    $stock = (int) $product['stock_quantity'];

                    $quantity = min($quantity, $stock);

                    if ($quantity <= 0) {

                        $stmt = $pdo->prepare("

                            DELETE FROM cart_items

                            WHERE cart_id = ? AND product_id = ?

                        ");

                        $stmt->execute([

                            $cart_id,

                            $product_id

                        ]);
                    } else {

                        $stmt = $pdo->prepare("

                            UPDATE cart_items

                            SET quantity = ?

                            WHERE cart_id = ? AND product_id = ?

                        ");

                        $stmt->execute([

                            $quantity,

                            $cart_id,

                            $product_id

                        ]);
                    }
                }
            }
        } else {

            $cart = getGuestCart();

            if (!empty($_POST['quantities']) && is_array($_POST['quantities'])) {

                foreach ($_POST['quantities'] as $product_id => $quantity) {

                    $product_id = (int) $product_id;

                    $quantity = (int) $quantity;

                    if ($quantity <= 0) {

                        unset($cart[$product_id]);

                        continue;
                    }

                    $stmt = $pdo->prepare("

                        SELECT stock_quantity

                        FROM products

                        WHERE id = ?

                        LIMIT 1

                    ");

                    $stmt->execute([$product_id]);

                    $product = $stmt->fetch();

                    if (!$product) {

                        unset($cart[$product_id]);

                        continue;
                    }

                    $stock = (int) $product['stock_quantity'];

                    if ($stock <= 0) {

                        unset($cart[$product_id]);
                    } else {

                        $cart[$product_id] = min($quantity, $stock);
                    }
                }
            }

            saveGuestCart($cart);
        }

        header('Location: shopping-cart.php');

        exit;
    }

    if (isset($_POST['remove_item'])) {

        $product_id = (int) ($_POST['product_id'] ?? 0);

        if ($is_logged_in) {

            $cart_id = getUserCartId($pdo, $user_id);

            $stmt = $pdo->prepare("

                DELETE FROM cart_items

                WHERE cart_id = ? AND product_id = ?

            ");

            $stmt->execute([

                $cart_id,

                $product_id

            ]);
        } else {

            $cart = getGuestCart();

            unset($cart[$product_id]);

            saveGuestCart($cart);
        }

        header('Location: shopping-cart.php');

        exit;
    }

    if (isset($_POST['clear_cart'])) {

        if ($is_logged_in) {

            $cart_id = getUserCartId($pdo, $user_id);

            $stmt = $pdo->prepare("

                DELETE FROM cart_items

                WHERE cart_id = ?

            ");

            $stmt->execute([$cart_id]);
        } else {

            saveGuestCart([]);
        }

        header('Location: shopping-cart.php');

        exit;
    }
}

$cart_products = [];

$subtotal = 0;

$total_items = 0;

if ($is_logged_in) {

    $cart_id = getUserCartId($pdo, $user_id);

    $stmt = $pdo->prepare("

        SELECT

            ci.product_id,

            ci.quantity,

            p.product_name,

            p.selling_price,

            p.discount_type,

            p.discount_value,

            p.stock_quantity,

            p.image,

            p.status

        FROM cart_items ci

        INNER JOIN products p ON p.id = ci.product_id

        WHERE ci.cart_id = ?

        ORDER BY ci.id DESC

    ");

    $stmt->execute([$cart_id]);

    $cart_products = $stmt->fetchAll();
} else {

    $guest_cart = getGuestCart();

    if (!empty($guest_cart)) {

        $ids = array_keys($guest_cart);

        $placeholders = implode(

            ',',

            array_fill(0, count($ids), '?')

        );

        $stmt = $pdo->prepare("

            SELECT

                id AS product_id,

                product_name,

                selling_price,

                discount_type,

                discount_value,

                stock_quantity,

                image,

                status

            FROM products

            WHERE id IN ($placeholders)

        ");

        $stmt->execute($ids);

        $products = $stmt->fetchAll();

        foreach ($products as $product) {

            $product['quantity'] =

                (int) $guest_cart[$product['product_id']];

            $cart_products[] = $product;
        }
    }
}

foreach ($cart_products as $key => $product) {

    if (

        $product['status'] !== 'active' ||

        (int) $product['stock_quantity'] <= 0

    ) {

        if ($is_logged_in) {

            $cart_id = getUserCartId($pdo, $user_id);

            $stmt = $pdo->prepare("

                DELETE FROM cart_items

                WHERE cart_id = ? AND product_id = ?

            ");

            $stmt->execute([

                $cart_id,

                $product['product_id']

            ]);
        } else {

            $guest_cart = getGuestCart();

            unset($guest_cart[$product['product_id']]);

            saveGuestCart($guest_cart);
        }

        unset($cart_products[$key]);

        continue;
    }

    $stock = (int) $product['stock_quantity'];

    $quantity = min(

        (int) $product['quantity'],

        $stock

    );

    if ($quantity <= 0) {

        unset($cart_products[$key]);

        continue;
    }

    $cart_products[$key]['quantity'] = $quantity;

    $price = getProductPrice($product);

    $product_subtotal = $price * $quantity;

    $cart_products[$key]['final_price'] = $price;

    $cart_products[$key]['subtotal'] = $product_subtotal;

    $subtotal += $product_subtotal;

    $total_items += $quantity;
}

$cart_products = array_values($cart_products);

$shipping = !empty($cart_products) ? $shipping_cost : 0;

if ($applied_coupon) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM coupons
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $applied_coupon['id']]);
    $coupon = $stmt->fetch();

    if (
        !$coupon ||
        $coupon['status'] !== 'active' ||
        strtotime($coupon['start_date']) > time() ||
        strtotime($coupon['end_date']) < time() ||
        (
            $coupon['max_uses'] !== null &&
            (int) $coupon['used_count'] >= (int) $coupon['max_uses']
        ) ||
        $subtotal < (float) $coupon['min_order_amount']
    ) {
        unset($_SESSION['applied_coupon']);
        $applied_coupon = null;
    } else {
        if ($coupon['discount_type'] === 'percentage') {
            $coupon_discount = $subtotal * ((float) $coupon['discount_value'] / 100);
        } else {
            $coupon_discount = (float) $coupon['discount_value'];
        }

        $coupon_discount = min($coupon_discount, $subtotal);
    }
}

$total = max(0, $subtotal - $coupon_discount) + $shipping;

?>

<?php include __DIR__ . '/includes/header.php'; ?>

<link

    rel="stylesheet"

    href="css/jquery-ui.min.css"

    type="text/css">

<section

    class="breadcrumb-section set-bg"

    style="background-image: url('img/breadcrumb.jpg');">

    <div class="container">

        <div class="row">

            <div class="col-lg-12 text-center">

                <div class="breadcrumb__text">

                    <h2>

                        Shopping Cart

                    </h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">

                            Home

                        </a>

                        <span>

                            Shopping Cart

                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="shopping-cart spad">

    <div class="container">

        <?php if (empty($cart_products)): ?>

            <div class="row">

                <div class="col-lg-12 text-center">

                    <div style="padding:70px 20px;">

                        <i

                            class="fa fa-shopping-cart"

                            style="font-size:70px;margin-bottom:25px;">

                        </i>

                        <h3>

                            Your cart is empty

                        </h3>

                        <p style="margin:15px 0 30px;">

                            You don't have any products in your shopping cart.

                        </p>

                        <a

                            href="shop.php"

                            class="primary-btn">

                            CONTINUE SHOPPING

                        </a>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <form method="POST">

                <div class="row">

                    <div class="col-lg-8">

                        <div class="shopping__cart__table">

                            <table>

                                <thead>

                                    <tr>

                                        <th>

                                            Product

                                        </th>

                                        <th>

                                            Price

                                        </th>

                                        <th>

                                            Quantity

                                        </th>

                                        <th>

                                            Total

                                        </th>

                                        <th>

                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php foreach ($cart_products as $product): ?>

                                        <tr>

                                            <td class="product__cart__item">

                                                <div class="product__cart__item__pic">

                                                    <img

                                                        src="<?php echo htmlspecialchars(productImageUrl($product['image'])); ?>"

                                                        alt="">

                                                </div>

                                                <div class="product__cart__item__text">

                                                    <h6>

                                                        <?php

                                                        echo htmlspecialchars(

                                                            $product['product_name']

                                                        );

                                                        ?>

                                                    </h6>

                                                </div>

                                            </td>

                                            <td class="cart__price">

                                                EGP

                                                <?php

                                                echo number_format(

                                                    $product['final_price'],

                                                    2

                                                );

                                                ?>

                                            </td>

                                            <td class="quantity__item">

                                                <div class="quantity">

                                                    <div class="pro-qty-2">

                                                        <input

                                                            type="number"

                                                            name="quantities[<?php echo (int) $product['product_id']; ?>]"

                                                            value="<?php echo (int) $product['quantity']; ?>"

                                                            min="1"

                                                            max="<?php echo (int) $product['stock_quantity']; ?>">

                                                    </div>

                                                </div>

                                            </td>

                                            <td class="cart__price">

                                                EGP

                                                <?php

                                                echo number_format(

                                                    $product['subtotal'],

                                                    2

                                                );

                                                ?>

                                            </td>

                                            <td class="cart__close">

                                                <button

                                                    type="submit"

                                                    name="remove_item"

                                                    value="1"

                                                    style="border:0;background:none;"

                                                    onclick="this.form.product_id.value='<?php echo (int) $product['product_id']; ?>';">

                                                    <i class="fa fa-close"></i>

                                                </button>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                </tbody>

                            </table>

                        </div>

                        <input

                            type="hidden"

                            name="product_id"

                            value="">

                        <div

                            class="row"

                            style="margin-top:30px;">

                            <div class="col-lg-6">

                                <a

                                    href="shop.php"

                                    class="primary-btn cart-btn">

                                    CONTINUE SHOPPING

                                </a>

                            </div>

                            <div class="col-lg-6 text-right">

                                <button

                                    type="submit"

                                    name="update_cart"

                                    class="primary-btn cart-btn">

                                    UPDATE CART

                                </button>

                            </div>

                        </div>

                    </div>

                    <div class="col-lg-4">

                        <div class="cart__discount">

                            <h6>

                                Coupon

                            </h6>

                            <?php if ($coupon_error !== ''): ?>

                                <div class="alert alert-danger" style="margin-bottom:15px;">

                                    <?php echo htmlspecialchars($coupon_error); ?>

                                </div>

                            <?php endif; ?>

                            <?php if ($coupon_success !== ''): ?>

                                <div class="alert alert-success" style="margin-bottom:15px;">

                                    <?php echo htmlspecialchars($coupon_success); ?>

                                </div>

                            <?php endif; ?>

                            <?php if ($applied_coupon): ?>

                                <div style="margin-bottom:15px;">

                                    <strong>

                                        Applied Coupon:

                                    </strong>

                                    <?php echo htmlspecialchars($applied_coupon['code']); ?>

                                </div>

                                <div style="margin-bottom:20px;">

                                    <form method="POST">

                                        <button

                                            type="submit"

                                            name="remove_coupon"

                                            class="btn btn-danger"

                                            style="border:0;">

                                            REMOVE COUPON

                                        </button>

                                    </form>

                                </div>

                            <?php else: ?>

                                <form method="POST" style="margin-bottom:20px;">

                                    <div style="display:flex;gap:8px;">

                                        <input

                                            type="text"

                                            name="coupon_code"

                                            class="form-control"

                                            placeholder="Enter coupon code"

                                            style="height:45px;"

                                            required>

                                        <button

                                            type="submit"

                                            name="apply_coupon"

                                            class="btn btn-primary"

                                            style="border:0;white-space:nowrap;">

                                            APPLY

                                        </button>

                                    </div>

                                </form>

                            <?php endif; ?>

                            <h6>

                                Cart Summary

                            </h6>

                            <div

                                style="

                                display:flex;

                                justify-content:space-between;

                                margin:15px 0;

                            ">

                                <span>

                                    Items

                                </span>

                                <strong>

                                    <?php echo $total_items; ?>

                                </strong>

                            </div>

                            <div

                                style="

                                display:flex;

                                justify-content:space-between;

                                margin:15px 0;

                            ">

                                <span>

                                    Subtotal

                                </span>

                                <strong>

                                    EGP

                                    <?php

                                    echo number_format(

                                        $subtotal,

                                        2

                                    );

                                    ?>

                                </strong>

                            </div>

                            <div

                                style="

                                display:flex;

                                justify-content:space-between;

                                margin:15px 0;

                            ">

                                <span>

                                    Shipping

                                </span>

                                <strong>

                                    EGP

                                    <?php

                                    echo number_format(

                                        $shipping,

                                        2

                                    );

                                    ?>

                                </strong>

                            </div>

                            <hr>

                            <div

                                style="

                                display:flex;

                                justify-content:space-between;

                                margin:20px 0;

                            ">

                                <h5>

                                    Total

                                </h5>

                                <h5>

                                    EGP

                                    <?php

                                    echo number_format(

                                        $total,

                                        2

                                    );

                                    ?>

                                </h5>

                            </div>

                            <a

                                href="checkout.php"

                                class="primary-btn"

                                style="

                                width:100%;

                                text-align:center;

                            ">

                                PROCEED TO CHECKOUT

                            </a>

                            <button

                                type="submit"

                                name="clear_cart"

                                class="primary-btn"

                                style="

                                width:100%;

                                border:0;

                                margin-top:15px;

                            "

                                onclick="return confirm('Are you sure you want to clear your cart?');">

                                CLEAR CART

                            </button>

                        </div>

                    </div>

                </div>

            </form>

        <?php endif; ?>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>