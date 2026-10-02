<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Product Details - Elkumanda';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: shop.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        id,
        product_name,
        short_description,
        full_description,
        status,
        category,
        brand,
        selling_price,
        discount_type,
        discount_value,
        stock_quantity,
        low_stock_alert,
        image
    FROM products
    WHERE id = ? AND status = 'active'
    LIMIT 1
");

$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: shop.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT image
    FROM product_images
    WHERE product_id = ?
    ORDER BY id ASC
");

$stmt->execute([$id]);
$galleryImages = $stmt->fetchAll();

function productImageUrl($filename)
{
    $filename = trim((string) $filename);

    if ($filename === '') {
        return 'img/product/default.jpg';
    }

    if (preg_match('/^https?:\/\//i', $filename)) {
        return $filename;
    }

    $filename = basename(str_replace('\\', '/', $filename));

    $projectRoot = realpath(__DIR__ . '/..');
    $uploadsRoot = $projectRoot . DIRECTORY_SEPARATOR . 'uploads';

    $possibleFiles = [
        $uploadsRoot . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $filename,
        $uploadsRoot . DIRECTORY_SEPARATOR . $filename,
        $uploadsRoot . DIRECTORY_SEPARATOR . 'product' . DIRECTORY_SEPARATOR . $filename,
        $uploadsRoot . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . 'gallery' . DIRECTORY_SEPARATOR . $filename
    ];

    foreach ($possibleFiles as $file) {

        if (is_file($file)) {

            $relativePath = str_replace(
                '\\',
                '/',
                substr($file, strlen($projectRoot) + 1)
            );

            return '../' . $relativePath;
        }
    }

    if (is_dir($uploadsRoot)) {

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $uploadsRoot,
                FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {

            if (
                $file->isFile() &&
                strcasecmp($file->getFilename(), $filename) === 0
            ) {

                $fullPath = $file->getPathname();

                $relativePath = str_replace(
                    '\\',
                    '/',
                    substr($fullPath, strlen($projectRoot) + 1)
                );

                return '../' . $relativePath;
            }
        }
    }

    return 'img/product/default.jpg';
}

$allImages = [];

if (!empty($product['image'])) {
    $allImages[] = $product['image'];
}

foreach ($galleryImages as $galleryImage) {

    if (!empty($galleryImage['image'])) {
        $allImages[] = $galleryImage['image'];
    }
}

$allImages = array_values(array_unique($allImages));

$image = !empty($allImages)
    ? productImageUrl($allImages[0])
    : 'img/product/default.jpg';

$galleryImageUrls = [];

foreach ($allImages as $imageName) {
    $galleryImageUrls[] = productImageUrl($imageName);
}

$originalPrice = (float) $product['selling_price'];
$finalPrice = $originalPrice;

if ($product['discount_type'] === 'percentage') {

    $finalPrice =
        $originalPrice -
        (
            $originalPrice *
            (float) $product['discount_value'] /
            100
        );
} elseif ($product['discount_type'] === 'fixed') {

    $finalPrice =
        $originalPrice -
        (float) $product['discount_value'];
}

$finalPrice = max(0, $finalPrice);

$hasDiscount = $finalPrice < $originalPrice;

$stock = (int) $product['stock_quantity'];
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
    .product-main-image {
        width: 100%;
        height: 520px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f7f7f7;
        overflow: hidden;
    }

    .product-main-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-gallery {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 15px;
    }

    .product-gallery-item {
        width: 85px;
        height: 85px;
        border: 1px solid #ddd;
        cursor: pointer;
        overflow: hidden;
        background: #f7f7f7;
        transition: 0.2s;
    }

    .product-gallery-item.active {
        border: 2px solid #7fad39;
    }

    .product-gallery-item:hover {
        border-color: #7fad39;
    }

    .product-gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .product-info-box {
        padding: 20px 0;
    }

    .product-info-box h4 {
        font-size: 30px;
        font-weight: 700;
        margin-bottom: 15px;
    }

    .product-info-box h3 {
        font-size: 28px;
        font-weight: 700;
        margin: 20px 0;
    }

    .product-info-box h3 span {
        font-size: 18px;
        color: #999;
        text-decoration: line-through;
        margin-left: 10px;
        font-weight: 400;
    }

    .product-short-description {
        color: #666;
        line-height: 1.8;
        margin-bottom: 25px;
    }

    .product-meta {
        border-top: 1px solid #eee;
        border-bottom: 1px solid #eee;
        padding: 18px 0;
        margin-bottom: 25px;
    }

    .product-meta p {
        margin-bottom: 8px;
        color: #555;
    }

    .product-meta p:last-child {
        margin-bottom: 0;
    }

    .product-meta strong {
        color: #222;
    }

    .stock-available {
        color: #6fa22e;
        font-weight: 700;
    }

    .stock-out {
        color: #dc3545;
        font-weight: 700;
    }

    .quantity-area {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
    }

    .quantity-area input {
        width: 80px;
        height: 50px;
        border: 1px solid #ddd;
        text-align: center;
        font-size: 16px;
    }

    .add-cart-btn {
        display: inline-block;
        padding: 15px 35px;
        background: #7fad39;
        color: #fff;
        border: none;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
    }

    .add-cart-btn:hover {
        background: #5f8d22;
        color: #fff;
    }

    .add-cart-btn.disabled {
        background: #aaa;
        cursor: not-allowed;
    }

    .details-tabs {
        margin-top: 60px;
    }

    .details-tabs .nav-tabs {
        border-bottom: 1px solid #ddd;
    }

    .details-tabs .nav-link {
        color: #555;
        font-weight: 700;
    }

    .details-tabs .nav-link.active {
        color: #7fad39;
    }

    .description-box {
        padding: 30px 0;
        color: #666;
        line-height: 1.9;
    }

    @media (max-width: 991px) {

        .product-main-image {
            height: 400px;
            margin-bottom: 15px;
        }

    }

    @media (max-width: 575px) {

        .product-gallery-item {
            width: 70px;
            height: 70px;
        }

    }
</style>

<section class="shop-details">

    <div class="product__details__pic">

        <div class="container">

            <div class="row">

                <div class="col-lg-12">

                    <div class="product__details__breadcrumb">

                        <a href="index.php">
                            Home
                        </a>

                        <a href="shop.php">
                            Shop
                        </a>

                        <span>
                            Product Details
                        </span>

                    </div>

                </div>

            </div>

            <div class="row">

                <div class="col-lg-6">

                    <div class="product-main-image">

                        <img
                            id="mainProductImage"
                            src="<?php echo htmlspecialchars($image); ?>"
                            alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                            onerror="this.onerror=null;this.src='img/product/default.jpg';">

                    </div>

                    <?php if (!empty($galleryImageUrls)): ?>

                        <div class="product-gallery">

                            <?php foreach ($galleryImageUrls as $index => $galleryImageUrl): ?>

                                <div
                                    class="product-gallery-item <?php echo $index === 0 ? 'active' : ''; ?>"
                                    onclick='changeProductImage(<?php echo json_encode($galleryImageUrl); ?>, this)'>

                                    <img
                                        src="<?php echo htmlspecialchars($galleryImageUrl); ?>"
                                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                        onerror="this.onerror=null;this.src='img/product/default.jpg';">

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="col-lg-6">

                    <div class="product-info-box">

                        <h4>
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </h4>

                        <div class="rating">

                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star"></i>
                            <i class="fa fa-star-o"></i>

                        </div>

                        <h3>

                            EGP
                            <?php echo number_format($finalPrice, 2); ?>

                            <?php if ($hasDiscount): ?>

                                <span>
                                    EGP
                                    <?php echo number_format($originalPrice, 2); ?>
                                </span>

                            <?php endif; ?>

                        </h3>

                        <?php if (!empty($product['short_description'])): ?>

                            <p class="product-short-description">

                                <?php
                                echo nl2br(
                                    htmlspecialchars(
                                        $product['short_description']
                                    )
                                );
                                ?>

                            </p>

                        <?php endif; ?>

                        <div class="product-meta">

                            <?php if (!empty($product['brand'])): ?>

                                <p>

                                    <strong>
                                        Brand:
                                    </strong>

                                    <?php echo htmlspecialchars($product['brand']); ?>

                                </p>

                            <?php endif; ?>

                            <?php if (!empty($product['category'])): ?>

                                <p>

                                    <strong>
                                        Category:
                                    </strong>

                                    <?php echo htmlspecialchars($product['category']); ?>

                                </p>

                            <?php endif; ?>

                            <p>

                                <strong>
                                    Stock:
                                </strong>

                                <?php if ($stock > 0): ?>

                                    <span class="stock-available">

                                        <?php echo $stock; ?>
                                        Available

                                    </span>

                                <?php else: ?>

                                    <span class="stock-out">
                                        Out of Stock
                                    </span>

                                <?php endif; ?>

                            </p>

                        </div>

                        <?php if ($stock > 0): ?>

                            <form
                                method="POST"
                                action="shopping-cart.php">

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?php echo (int) $product['id']; ?>">

                                <div class="quantity-area">

                                    <span>
                                        Quantity:
                                    </span>

                                    <input
                                        type="number"
                                        name="quantity"
                                        value="1"
                                        min="1"
                                        max="<?php echo $stock; ?>"
                                        required>

                                </div>

                                <button
                                    type="submit"
                                    name="add_to_cart"
                                    class="add-cart-btn">

                                    Add To Cart

                                </button>

                            </form>

                        <?php else: ?>

                            <button
                                class="add-cart-btn disabled"
                                disabled>

                                Out Of Stock

                            </button>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

            <div class="row">

                <div class="col-lg-12">

                    <div class="details-tabs">

                        <ul
                            class="nav nav-tabs"
                            role="tablist">

                            <li class="nav-item">

                                <a
                                    class="nav-link active"
                                    data-toggle="tab"
                                    href="#description"
                                    role="tab">

                                    Description

                                </a>

                            </li>

                            <li class="nav-item">

                                <a
                                    class="nav-link"
                                    data-toggle="tab"
                                    href="#information"
                                    role="tab">

                                    Additional Information

                                </a>

                            </li>

                        </ul>

                        <div class="tab-content">

                            <div
                                class="tab-pane active"
                                id="description"
                                role="tabpanel">

                                <div class="description-box">

                                    <?php if (!empty($product['full_description'])): ?>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $product['full_description']
                                            )
                                        );
                                        ?>

                                    <?php elseif (!empty($product['short_description'])): ?>

                                        <?php
                                        echo nl2br(
                                            htmlspecialchars(
                                                $product['short_description']
                                            )
                                        );
                                        ?>

                                    <?php else: ?>

                                        No description available.

                                    <?php endif; ?>

                                </div>

                            </div>

                            <div
                                class="tab-pane"
                                id="information"
                                role="tabpanel">

                                <div class="description-box">

                                    <p>

                                        <strong>
                                            Product Name:
                                        </strong>

                                        <?php echo htmlspecialchars($product['product_name']); ?>

                                    </p>

                                    <p>

                                        <strong>
                                            Brand:
                                        </strong>

                                        <?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?>

                                    </p>

                                    <p>

                                        <strong>
                                            Category:
                                        </strong>

                                        <?php echo htmlspecialchars($product['category'] ?? 'N/A'); ?>

                                    </p>

                                    <p>

                                        <strong>
                                            Stock Quantity:
                                        </strong>

                                        <?php echo $stock; ?>

                                    </p>

                                    <p>

                                        <strong>
                                            Status:
                                        </strong>

                                        <?php echo htmlspecialchars($product['status']); ?>

                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<script>
    function changeProductImage(image, element) {

        var mainImage = document.getElementById('mainProductImage');

        mainImage.onerror = function() {

            this.onerror = null;
            this.src = 'img/product/default.jpg';

        };

        mainImage.src = image;

        document
            .querySelectorAll('.product-gallery-item')
            .forEach(function(item) {

                item.classList.remove('active');

            });

        element.classList.add('active');

    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>