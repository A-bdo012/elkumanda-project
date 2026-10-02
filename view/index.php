<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Elkumanda';

$categories = [];
$coupons = [];
$products = [];
$sliderProducts = [];

try {

    $stmt = $pdo->query("
        SELECT id, name
        FROM categories
        ORDER BY name ASC
        LIMIT 6
    ");

    $categories = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            short_description,
            category,
            brand,
            selling_price,
            discount_type,
            discount_value,
            stock_quantity,
            image,
            created_at
        FROM products
        WHERE status = 'active'
        AND stock_quantity > 0
        ORDER BY id DESC
        LIMIT 8
    ");

    $products = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            id,
            product_name,
            short_description,
            selling_price,
            discount_type,
            discount_value,
            image
        FROM products
        WHERE status = 'active'
        AND stock_quantity > 0
        AND image IS NOT NULL
        AND image != ''
        ORDER BY id DESC
        LIMIT 5
    ");

    $sliderProducts = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            id,
            code,
            occasion,
            discount_type,
            discount_value,
            min_order_amount,
            start_date,
            end_date
        FROM coupons
        WHERE status = 'active'
        AND show_to_users = 1
        AND (start_date IS NULL OR start_date <= NOW())
        AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY id DESC
        LIMIT 3
    ");

    $coupons = $stmt->fetchAll();
} catch (PDOException $e) {

    $categories = [];
    $products = [];
    $coupons = [];
    $sliderProducts = [];
}

function getProductFinalPrice($product)
{
    $price = (float) $product['selling_price'];

    if ($product['discount_type'] === 'percentage') {
        $price -= $price * ((float) $product['discount_value'] / 100);
    } elseif ($product['discount_type'] === 'fixed') {
        $price -= (float) $product['discount_value'];
    }

    return max(0, $price);
}

function getProductDiscountPercent($product)
{
    $sellingPrice = (float) $product['selling_price'];

    if ($sellingPrice <= 0) {
        return 0;
    }

    if ($product['discount_type'] === 'percentage') {
        return (float) $product['discount_value'];
    }

    if ($product['discount_type'] === 'fixed') {
        return ((float) $product['discount_value'] / $sellingPrice) * 100;
    }

    return 0;
}

function getProductImage($image)
{
    if (empty($image)) {
        return 'img/product/product-1.jpg';
    }

    $filename = basename($image);

    if (file_exists(__DIR__ . '/../uploads/products/' . $filename)) {
        return '../uploads/products/' . $filename;
    }

    return 'img/product/product-1.jpg';
}

include __DIR__ . '/includes/header.php';

?>

<style>
    .elkumanda-product-slider {
        position: relative;
        width: 100%;
        overflow: hidden;
        margin-bottom: 35px;
    }

    .elkumanda-slider-wrapper {
        position: relative;
        width: 100%;
    }

    .elkumanda-slide {
        display: none;
        position: relative;
        width: 100%;
        height: 420px;
        overflow: hidden;
        background: #f5f5f5;
    }

    .elkumanda-slide.active {
        display: block;
    }

    .elkumanda-slide img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .elkumanda-slide-overlay {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg,
                rgba(0, 0, 0, 0.65) 0%,
                rgba(0, 0, 0, 0.25) 50%,
                rgba(0, 0, 0, 0) 100%);
    }

    .elkumanda-slide-content {
        position: absolute;
        left: 60px;
        top: 50%;
        transform: translateY(-50%);
        max-width: 500px;
        color: #fff;
    }

    .elkumanda-slide-content span {
        display: block;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 2px;
        margin-bottom: 12px;
        text-transform: uppercase;
    }

    .elkumanda-slide-content h2 {
        font-size: 42px;
        line-height: 1.15;
        font-weight: 700;
        margin-bottom: 15px;
        color: #fff;
    }

    .elkumanda-slide-content p {
        font-size: 16px;
        line-height: 1.6;
        margin-bottom: 25px;
        color: #fff;
    }

    .elkumanda-slide-btn {
        display: inline-block;
        padding: 12px 28px;
        background: #7fad39;
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        text-decoration: none;
        text-transform: uppercase;
    }

    .elkumanda-slide-btn:hover {
        color: #fff;
    }

    .elkumanda-slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 45px;
        height: 45px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        cursor: pointer;
        z-index: 10;
        font-size: 18px;
    }

    .elkumanda-slider-arrow:hover {
        background: #7fad39;
        color: #fff;
    }

    .elkumanda-slider-prev {
        left: 20px;
    }

    .elkumanda-slider-next {
        right: 20px;
    }

    .elkumanda-slider-dots {
        position: absolute;
        bottom: 18px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 8px;
        z-index: 10;
    }

    .elkumanda-slider-dot {
        width: 9px;
        height: 9px;
        border: 0;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        padding: 0;
        cursor: pointer;
    }

    .elkumanda-slider-dot.active {
        background: #7fad39;
    }

    @media (max-width: 767px) {

        .elkumanda-slide {
            height: 330px;
        }

        .elkumanda-slide-content {
            left: 30px;
            right: 30px;
        }

        .elkumanda-slide-content h2 {
            font-size: 28px;
        }

        .elkumanda-slide-content p {
            font-size: 14px;
        }

        .elkumanda-slider-arrow {
            width: 38px;
            height: 38px;
        }

        .elkumanda-slider-prev {
            left: 10px;
        }

        .elkumanda-slider-next {
            right: 10px;
        }
    }
</style>


<?php if (!empty($sliderProducts)): ?>

    <section class="elkumanda-product-slider">

        <div class="container">

            <div class="elkumanda-slider-wrapper">

                <?php foreach ($sliderProducts as $index => $sliderProduct): ?>

                    <?php
                    $sliderPrice = getProductFinalPrice($sliderProduct);
                    $sliderDiscount = getProductDiscountPercent($sliderProduct);
                    ?>

                    <div
                        class="elkumanda-slide <?= $index === 0 ? 'active' : '' ?>"
                        data-slide="<?= $index ?>">

                        <img
                            src="<?= htmlspecialchars(getProductImage($sliderProduct['image'])) ?>"
                            alt="<?= htmlspecialchars($sliderProduct['product_name']) ?>">

                        <div class="elkumanda-slide-overlay"></div>

                        <div class="elkumanda-slide-content">

                            <span>Featured Product</span>

                            <h2>
                                <?= htmlspecialchars($sliderProduct['product_name']) ?>
                            </h2>

                            <?php if (!empty($sliderProduct['short_description'])): ?>

                                <p>
                                    <?= htmlspecialchars(
                                        mb_strimwidth(
                                            $sliderProduct['short_description'],
                                            0,
                                            130,
                                            '...'
                                        )
                                    ) ?>
                                </p>

                            <?php else: ?>

                                <p>
                                    Discover this product and shop now at Elkumanda.
                                </p>

                            <?php endif; ?>

                            <?php if ($sliderDiscount > 0): ?>

                                <p style="margin-bottom:20px; font-weight:700;">

                                    EGP <?= number_format($sliderPrice, 2) ?>

                                    <span style="text-decoration:line-through; opacity:.7; margin-left:8px;">

                                        EGP <?= number_format(
                                                (float) $sliderProduct['selling_price'],
                                                2
                                            ) ?>

                                    </span>

                                </p>

                            <?php endif; ?>

                            <a
                                href="shop-details.php?id=<?= (int) $sliderProduct['id'] ?>"
                                class="elkumanda-slide-btn">
                                Shop Now
                            </a>

                        </div>

                    </div>

                <?php endforeach; ?>


                <?php if (count($sliderProducts) > 1): ?>

                    <button
                        type="button"
                        class="elkumanda-slider-arrow elkumanda-slider-prev"
                        onclick="elkumandaChangeSlide(-1)">
                        <i class="fa fa-angle-left"></i>
                    </button>

                    <button
                        type="button"
                        class="elkumanda-slider-arrow elkumanda-slider-next"
                        onclick="elkumandaChangeSlide(1)">
                        <i class="fa fa-angle-right"></i>
                    </button>

                    <div class="elkumanda-slider-dots">

                        <?php foreach ($sliderProducts as $index => $sliderProduct): ?>

                            <button
                                type="button"
                                class="elkumanda-slider-dot <?= $index === 0 ? 'active' : '' ?>"
                                onclick="elkumandaGoToSlide(<?= $index ?>)"></button>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

<?php endif; ?>


<section class="hero">

    <div class="container">

        <div class="row">

            <div class="col-lg-3">

                <div class="hero__categories">

                    <div class="hero__categories__all">

                        <i class="fa fa-bars"></i>

                        <span>All Categories</span>

                    </div>

                    <ul>

                        <?php if (!empty($categories)): ?>

                            <?php foreach ($categories as $category): ?>

                                <li>
                                    <a href="shop.php?category=<?= (int) $category['id'] ?>">
                                        <?= htmlspecialchars($category['name']) ?>
                                    </a>
                                </li>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <li>
                                <a href="shop.php">
                                    All Products
                                </a>
                            </li>

                        <?php endif; ?>

                    </ul>

                </div>

            </div>

            <div class="col-lg-9">

                <div class="hero__search">

                    <div class="hero__search__form">

                        <form action="shop.php" method="GET">

                            <input
                                type="text"
                                name="search"
                                placeholder="What do you need?">

                            <button type="submit" class="site-btn">
                                SEARCH
                            </button>

                        </form>

                    </div>

                    <div class="hero__search__phone">

                        <div class="hero__search__phone__icon">
                            <i class="fa fa-phone"></i>
                        </div>

                        <div class="hero__search__phone__text">

                            <h5>+20 100 000 0000</h5>

                            <span>Support 24/7</span>

                        </div>

                    </div>

                </div>

                <div
                    class="hero__item set-bg"
                    data-setbg="img/hero/banner.jpg">

                    <div class="hero__text">

                        <span>ELKUMANDA</span>

                        <h2>
                            Quality Products<br>
                            Great Prices
                        </h2>

                        <p>
                            Discover our latest products and special offers.
                        </p>

                        <a href="shop.php" class="primary-btn">
                            SHOP NOW
                        </a>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="categories">

    <div class="container">

        <div class="section-title">

            <h2>Featured Categories</h2>

        </div>

        <div class="row">

            <?php foreach ($categories as $category): ?>

                <div class="col-lg-2 col-md-4 col-sm-6">

                    <div class="categories__item">

                        <a href="shop.php?category=<?= (int) $category['id'] ?>">

                            <div class="categories__item__text">

                                <h5>
                                    <?= htmlspecialchars($category['name']) ?>
                                </h5>

                            </div>

                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<?php if (!empty($coupons)): ?>

    <section class="latest-product spad">

        <div class="container">

            <div class="section-title">

                <h2>Special Offers</h2>

            </div>

            <div class="row">

                <?php foreach ($coupons as $coupon): ?>

                    <div class="col-lg-4 col-md-6">

                        <div
                            style="
                            border:1px solid #eeeeee;
                            padding:25px;
                            margin-bottom:30px;
                            background:#ffffff;
                            text-align:center;
                        ">

                            <?php if (!empty($coupon['occasion'])): ?>

                                <h4>
                                    <?= htmlspecialchars($coupon['occasion']) ?>
                                </h4>

                            <?php endif; ?>

                            <h3 style="margin:15px 0;">
                                <?= htmlspecialchars($coupon['code']) ?>
                            </h3>

                            <h5 style="margin-bottom:15px;">

                                <?php if ($coupon['discount_type'] === 'percentage'): ?>

                                    <?= number_format(
                                        (float) $coupon['discount_value'],
                                        0
                                    ) ?>% OFF

                                <?php else: ?>

                                    EGP <?= number_format(
                                            (float) $coupon['discount_value'],
                                            2
                                        ) ?> OFF

                                <?php endif; ?>

                            </h5>

                            <?php if ((float) $coupon['min_order_amount'] > 0): ?>

                                <p>

                                    Minimum order:

                                    <strong>
                                        EGP <?= number_format(
                                                (float) $coupon['min_order_amount'],
                                                2
                                            ) ?>
                                    </strong>

                                </p>

                            <?php endif; ?>

                            <?php if (!empty($coupon['end_date'])): ?>

                                <p>

                                    Valid until:

                                    <?= date(
                                        'd M Y, h:i A',
                                        strtotime($coupon['end_date'])
                                    ) ?>

                                </p>

                            <?php endif; ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </section>

<?php endif; ?>


<section class="services spad">

    <div class="container">

        <div class="row">

            <div class="col-lg-3 col-md-6 col-sm-6">

                <div class="services__item">

                    <i class="fa fa-truck"></i>

                    <h6>Free Delivery</h6>

                    <p>Free delivery on selected orders.</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">

                <div class="services__item">

                    <i class="fa fa-refresh"></i>

                    <h6>Easy Returns</h6>

                    <p>Simple and convenient return process.</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">

                <div class="services__item">

                    <i class="fa fa-credit-card"></i>

                    <h6>Secure Payment</h6>

                    <p>Your orders are handled securely.</p>

                </div>

            </div>

            <div class="col-lg-3 col-md-6 col-sm-6">

                <div class="services__item">

                    <i class="fa fa-headphones"></i>

                    <h6>24/7 Support</h6>

                    <p>We are here to help you anytime.</p>

                </div>

            </div>

        </div>

    </div>

</section>


<section class="from-blog spad">

    <div class="container">

        <div class="section-title">

            <h2>Shop With Elkumanda</h2>

            <p>
                Find the products you need at great prices.
            </p>

        </div>

        <div class="text-center">

            <a href="shop.php" class="primary-btn">
                START SHOPPING
            </a>

        </div>

    </div>

</section>


<script>
    let elkumandaCurrentSlide = 0;
    let elkumandaSliderTimer;

    function elkumandaShowSlide(index) {
        const slides = document.querySelectorAll('.elkumanda-slide');
        const dots = document.querySelectorAll('.elkumanda-slider-dot');

        if (!slides.length) {
            return;
        }

        if (index >= slides.length) {
            elkumandaCurrentSlide = 0;
        } else if (index < 0) {
            elkumandaCurrentSlide = slides.length - 1;
        } else {
            elkumandaCurrentSlide = index;
        }

        slides.forEach(function(slide) {
            slide.classList.remove('active');
        });

        dots.forEach(function(dot) {
            dot.classList.remove('active');
        });

        slides[elkumandaCurrentSlide].classList.add('active');

        if (dots[elkumandaCurrentSlide]) {
            dots[elkumandaCurrentSlide].classList.add('active');
        }
    }

    function elkumandaChangeSlide(direction) {
        elkumandaShowSlide(
            elkumandaCurrentSlide + direction
        );

        elkumandaRestartSlider();
    }

    function elkumandaGoToSlide(index) {
        elkumandaShowSlide(index);

        elkumandaRestartSlider();
    }

    function elkumandaStartSlider() {
        const slides = document.querySelectorAll('.elkumanda-slide');

        if (slides.length <= 1) {
            return;
        }

        elkumandaSliderTimer = setInterval(function() {

            elkumandaShowSlide(
                elkumandaCurrentSlide + 1
            );

        }, 5000);
    }

    function elkumandaRestartSlider() {
        clearInterval(elkumandaSliderTimer);

        elkumandaStartSlider();
    }

    document.addEventListener('DOMContentLoaded', function() {
        elkumandaShowSlide(0);

        elkumandaStartSlider();
    });
</script>


<?php include __DIR__ . '/includes/footer.php'; ?>