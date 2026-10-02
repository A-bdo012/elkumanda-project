<?php



require_once __DIR__ . '/includes/auth.php';



$pageTitle = 'My Orders - Elkumanda';



if (!isset($_SESSION['user_id'])) {

    header('Location: login.php');

    exit;
}



$user_id = (int) $_SESSION['user_id'];



$stmt = $pdo->prepare("

    SELECT

        id,

        phone,

        address,

        total,

        status,

        created_at

    FROM orders

    WHERE user_id = ?

    ORDER BY id DESC

");



$stmt->execute([$user_id]);



$orders = $stmt->fetchAll();



?>



<?php include __DIR__ . '/includes/header.php'; ?>



<section class="breadcrumb-section set-bg" data-setbg="img/breadcrumb.jpg">



    <div class="container">



        <div class="row">



            <div class="col-lg-12">



                <div class="breadcrumb__text">



                    <h2>My Orders</h2>



                    <div class="breadcrumb__option">



                        <a href="index.php">

                            Home

                        </a>



                        <span>

                            My Orders

                        </span>



                    </div>



                </div>



            </div>



        </div>



    </div>



</section>



<section class="my-orders-section">



    <div class="container">



        <div class="section-title">



            <h2>

                My Orders

            </h2>



            <p>

                Track and manage your orders

            </p>



        </div>



        <?php if (empty($orders)): ?>



            <div class="empty-orders">



                <div class="empty-orders-icon">

                    <i class="fa fa-shopping-bag"></i>

                </div>



                <h3>

                    You have no orders yet

                </h3>



                <p>

                    Start shopping and place your first order.

                </p>



                <a href="shop.php" class="primary-btn">

                    START SHOPPING

                </a>



            </div>



        <?php else: ?>



            <div class="orders-list">



                <?php foreach ($orders as $order): ?>



                    <?php



                    $status = strtolower(trim($order['status']));



                    $statusClass = 'pending';

                    $shipping_cost = 50;
                    $coupon_discount = (float) ($order['coupon_discount'] ?? 0);
                    $subtotal = (float) $order['total'] - $shipping_cost + $coupon_discount;
                    $subtotal = max(0, $subtotal);



                    if ($status === 'processing') {

                        $statusClass = 'processing';
                    } elseif ($status === 'completed') {

                        $statusClass = 'completed';
                    } elseif ($status === 'cancelled') {

                        $statusClass = 'cancelled';
                    }



                    ?>



                    <div class="order-card">



                        <div class="order-card-top">



                            <div>



                                <span class="order-label">

                                    ORDER NUMBER

                                </span>



                                <h3>

                                    #<?php echo (int) $order['id']; ?>

                                </h3>



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



                        <div class="order-card-divider"></div>



                        <div class="order-card-info">



                            <div class="order-info-box order-price-box">

                                <span>
                                    <i class="fa fa-money"></i>
                                    ORDER SUMMARY
                                </span>

                                <div class="price-line">
                                    <span>Subtotal</span>
                                    <strong>
                                        EGP <?php echo number_format($subtotal, 2); ?>
                                    </strong>
                                </div>

                                <?php if ($coupon_discount > 0): ?>
                                    <div class="price-line coupon-line">
                                        <span>
                                            Coupon Discount
                                            <?php if (!empty($order['coupon_code'])): ?>
                                                <small>(<?php echo htmlspecialchars($order['coupon_code']); ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                        <strong>
                                            - EGP <?php echo number_format($coupon_discount, 2); ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                                <div class="price-line">
                                    <span>Shipping</span>
                                    <strong>
                                        EGP <?php echo number_format($shipping_cost, 2); ?>
                                    </strong>
                                </div>

                                <div class="price-line total-line">
                                    <span>Total</span>
                                    <strong>
                                        EGP <?php echo number_format((float) $order['total'], 2); ?>
                                    </strong>
                                </div>

                            </div>

                        </div>



                        <div class="order-info-box">



                            <span>

                                <i class="fa fa-money"></i>

                                TOTAL

                            </span>



                            <strong>

                                EGP <?php echo number_format((float) $order['total'], 2); ?>

                            </strong>



                        </div>



                        <div class="order-info-box">



                            <span>

                                <i class="fa fa-truck"></i>

                                SHIPPING

                            </span>



                            <strong>

                                <strong>

                                    EGP 50.00

                                </strong>

                            </strong>



                        </div>



                    </div>



                    <div class="order-card-bottom">



                        <div class="order-delivery">



                            <i class="fa fa-map-marker"></i>



                            <span>

                                <?php echo htmlspecialchars($order['address']); ?>

                            </span>



                        </div>



                        <a

                            href="order-details.php?id=<?php echo (int) $order['id']; ?>"

                            class="view-order-btn">

                            VIEW ORDER

                            <i class="fa fa-arrow-right"></i>

                        </a>



                    </div>



            </div>



        <?php endforeach; ?>



    </div>



<?php endif; ?>



</div>



</section>



<style>
    .my-orders-section {

        padding: 70px 0 80px;

        background: #f8f8f8;

    }



    .my-orders-section .section-title {

        text-align: center;

        margin-bottom: 40px;

    }



    .my-orders-section .section-title h2 {

        font-size: 32px;

        font-weight: 700;

        margin-bottom: 8px;

    }



    .my-orders-section .section-title p {

        color: #777;

        margin-bottom: 0;

    }



    .orders-list {

        max-width: 1000px;

        margin: 0 auto;

    }



    .order-card {

        background: #ffffff;

        border: 1px solid #eeeeee;

        border-radius: 6px;

        margin-bottom: 25px;

        padding: 28px 30px;

        box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);

    }



    .order-card-top {

        display: flex;

        align-items: center;

        justify-content: space-between;

    }



    .order-label {

        display: block;

        color: #999999;

        font-size: 11px;

        font-weight: 700;

        letter-spacing: 1px;

        margin-bottom: 5px;

    }



    .order-card-top h3 {

        font-size: 22px;

        font-weight: 700;

        margin: 0;

    }



    .order-status {

        display: inline-flex;

        align-items: center;

        gap: 7px;

        padding: 8px 15px;

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



    .order-card-divider {

        height: 1px;

        background: #eeeeee;

        margin: 22px 0;

    }



    .order-card-info {

        display: flex;

        align-items: stretch;

        margin-bottom: 25px;

    }



    .order-info-box {

        flex: 1;

        padding: 0 25px;

        border-right: 1px solid #eeeeee;

    }



    .order-info-box:first-child {

        padding-left: 0;

    }



    .order-info-box:last-child {

        border-right: 0;

    }



    .order-info-box span {

        display: block;

        color: #999999;

        font-size: 11px;

        font-weight: 700;

        letter-spacing: .7px;

        margin-bottom: 8px;

    }



    .order-info-box span i {

        margin-right: 5px;

    }



    .order-info-box strong {

        display: block;

        color: #222222;

        font-size: 15px;

        font-weight: 700;

    }



    .order-price-box {
        min-width: 280px;
    }

    .order-price-box>span {
        margin-bottom: 12px;
    }

    .price-line {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 7px;
        font-size: 13px;
        color: #777777;
    }

    .price-line strong {
        font-size: 13px;
        color: #333333;
    }

    .price-line small {
        color: #7fad39;
        font-size: 11px;
        font-weight: 700;
    }

    .coupon-line {
        color: #7fad39;
    }

    .coupon-line strong {
        color: #7fad39;
    }

    .total-line {
        border-top: 1px solid #eeeeee;
        margin-top: 10px;
        padding-top: 10px;
        margin-bottom: 0;
    }

    .total-line span,
    .total-line strong {
        color: #222222;
        font-size: 15px;
        font-weight: 700;
    }

    .order-card-bottom {

        display: flex;

        align-items: center;

        justify-content: space-between;

        padding-top: 20px;

        border-top: 1px solid #eeeeee;

    }



    .order-delivery {

        display: flex;

        align-items: center;

        gap: 10px;

        max-width: 65%;

        color: #777777;

        font-size: 13px;

    }



    .order-delivery i {

        color: #7fad39;

        font-size: 17px;

    }



    .order-delivery span {

        overflow: hidden;

        text-overflow: ellipsis;

        white-space: nowrap;

    }



    .view-order-btn {

        display: inline-flex;

        align-items: center;

        gap: 8px;

        background: #7fad39;

        color: #ffffff !important;

        padding: 11px 20px;

        border-radius: 3px;

        font-size: 12px;

        font-weight: 700;

        text-decoration: none !important;

        transition: all .3s;

    }



    .view-order-btn:hover {

        background: #6f9c32;

        color: #ffffff !important;

    }



    .empty-orders {

        max-width: 650px;

        margin: 0 auto;

        background: #ffffff;

        text-align: center;

        padding: 60px 30px;

        border: 1px solid #eeeeee;

        border-radius: 6px;

    }



    .empty-orders-icon {

        margin-bottom: 20px;

    }



    .empty-orders-icon i {

        font-size: 55px;

        color: #7fad39;

    }



    .empty-orders h3 {

        font-size: 24px;

        font-weight: 700;

        margin-bottom: 10px;

    }



    .empty-orders p {

        color: #777777;

        margin-bottom: 25px;

    }



    .empty-orders .primary-btn {

        display: inline-block;

        text-decoration: none;

    }



    @media (max-width: 767px) {



        .my-orders-section {

            padding: 50px 0;

        }



        .order-card {

            padding: 22px 18px;

        }



        .order-card-top {

            align-items: flex-start;

            gap: 15px;

        }



        .order-status {

            font-size: 11px;

            padding: 7px 11px;

        }



        .order-card-info {

            display: block;

        }



        .order-info-box {

            border-right: 0;

            border-bottom: 1px solid #eeeeee;

            padding: 12px 0;

        }



        .order-info-box:first-child {

            padding-top: 0;

        }



        .order-info-box:last-child {

            border-bottom: 0;

        }



        .order-price-box {
            min-width: 280px;
        }

        .order-price-box>span {
            margin-bottom: 12px;
        }

        .price-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 7px;
            font-size: 13px;
            color: #777777;
        }

        .price-line strong {
            font-size: 13px;
            color: #333333;
        }

        .price-line small {
            color: #7fad39;
            font-size: 11px;
            font-weight: 700;
        }

        .coupon-line {
            color: #7fad39;
        }

        .coupon-line strong {
            color: #7fad39;
        }

        .total-line {
            border-top: 1px solid #eeeeee;
            margin-top: 10px;
            padding-top: 10px;
            margin-bottom: 0;
        }

        .total-line span,
        .total-line strong {
            color: #222222;
            font-size: 15px;
            font-weight: 700;
        }

        .order-card-bottom {

            display: block;

        }



        .order-delivery {

            max-width: 100%;

            margin-bottom: 18px;

        }



        .order-delivery span {

            white-space: normal;

        }



        .view-order-btn {

            width: 100%;

            justify-content: center;

        }



    }
</style>



<?php include __DIR__ . '/includes/footer.php'; ?>