<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$userName = $_SESSION['user_name'] ?? '';

$cartCount = 0;
$notificationCount = 0;

if ($isLoggedIn) {

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(ci.quantity), 0)
        FROM cart_items ci
        INNER JOIN cart c ON c.id = ci.cart_id
        WHERE c.user_id = ?
    ");

    $stmt->execute([(int)$_SESSION['user_id']]);
    $cartCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
        AND read_status = 0
    ");

    $stmt->execute([(int)$_SESSION['user_id']]);
    $notificationCount = (int)$stmt->fetchColumn();
} elseif (!empty($_COOKIE['guest_cart'])) {

    $guestCart = json_decode($_COOKIE['guest_cart'], true);

    if (is_array($guestCart)) {

        foreach ($guestCart as $quantity) {
            $cartCount += (int)$quantity;
        }
    }
}

$pageTitle = $pageTitle ?? 'Elkumanda';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="description" content="Elkumanda">
    <meta name="keywords" content="Elkumanda, Shop, Products">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/font-awesome.min.css">
    <link rel="stylesheet" href="css/elegant-icons.css">
    <link rel="stylesheet" href="css/magnific-popup.css">
    <link rel="stylesheet" href="css/nice-select.css">
    <link rel="stylesheet" href="css/owl.carousel.min.css">
    <link rel="stylesheet" href="css/slicknav.min.css">
    <link rel="stylesheet" href="css/style.css">

</head>

<body>

    <header class="header">

        <div class="header__top">

            <div class="container">

                <div class="row">

                    <div class="col-lg-6">

                        <div class="header__top__left">

                            <ul>

                                <li>
                                    <i class="fa fa-envelope"></i>
                                    support@elkumanda.com
                                </li>

                                <li>
                                    Free shipping on orders over EGP 500
                                </li>

                            </ul>

                        </div>

                    </div>

                    <div class="col-lg-6">

                        <div class="header__top__right">

                            <div class="header__top__links">

                                <?php if ($isLoggedIn && $userName !== ''): ?>

                                    <span style="font-weight:700; margin-right:15px; color:#f5f5f5 !important;">
                                        Hello, <?php echo htmlspecialchars($userName); ?>
                                    </span>

                                    <a href="my-orders.php" style="margin-right:15px;">
                                        My Orders
                                    </a>

                                    <a href="notifications.php" style="margin-right:15px;">
                                        <i class="fa fa-bell"></i>

                                        <?php if ($notificationCount > 0): ?>

                                            <span style="
                                            background:#7fad39;
                                            color:#fff;
                                            border-radius:50%;
                                            padding:2px 7px;
                                            font-size:11px;
                                            font-weight:700;
                                            margin-left:3px;
                                        ">
                                                <?php echo $notificationCount; ?>
                                            </span>

                                        <?php endif; ?>

                                    </a>

                                    <a href="logout.php">
                                        Logout
                                    </a>

                                <?php else: ?>

                                    <a href="login.php">
                                        Login
                                    </a>

                                    <a href="register.php">
                                        Register
                                    </a>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="container">

            <div class="row">

                <div class="col-lg-3">

                    <div class="header__logo">

                        <a href="index.php">

                            <h2 style="font-weight:800;">
                                ELKUMANDA
                            </h2>

                        </a>

                    </div>

                </div>

                <div class="col-lg-9">

                    <nav class="header__menu">

                        <ul>

                            <li>
                                <a href="index.php">
                                    Home
                                </a>
                            </li>

                            <li>
                                <a href="shop.php">
                                    Shop
                                </a>
                            </li>

                            <li>
                                <a href="about.php">
                                    About
                                </a>
                            </li>

                            <li>
                                <a href="blog.php">
                                    Blog
                                </a>
                            </li>

                            <li>
                                <a href="contact.php">
                                    Contact
                                </a>
                            </li>

                            <li>
                                <a href="shopping-cart.php">
                                    Cart

                                    <?php if ($cartCount > 0): ?>

                                        <span style="margin-left:5px;">
                                            (<?php echo $cartCount; ?>)
                                        </span>

                                    <?php endif; ?>

                                </a>
                            </li>

                        </ul>

                    </nav>

                </div>

            </div>

        </div>

    </header>