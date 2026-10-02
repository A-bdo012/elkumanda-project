<?php
require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Shop - Elkumanda';

$search = trim($_GET['search'] ?? '');
$brand = trim($_GET['brand'] ?? '');

$per_page = 12;

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $per_page;

$brandStmt = $pdo->query("
    SELECT id, name
    FROM brands
    ORDER BY name ASC
");

$brands = $brandStmt->fetchAll();

$where = ["status = 'active'"];
$params = [];

if ($search !== '') {
    $where[] = "product_name LIKE ?";
    $params[] = "%{$search}%";
}

if ($brand !== '') {
    $where[] = "brand = ?";
    $params[] = $brand;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE $whereSql
");

$countStmt->execute($params);

$total_products = (int)$countStmt->fetchColumn();

$total_pages = max(1, (int)ceil($total_products / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$productStmt = $pdo->prepare("
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
        image
    FROM products
    WHERE $whereSql
    ORDER BY id DESC
    LIMIT $per_page OFFSET $offset
");

$productStmt->execute($params);

$products = $productStmt->fetchAll();

function getFinalPrice($product)
{
    $price = (float)$product['selling_price'];

    if ($product['discount_type'] === 'percentage') {
        $discount = (float)$product['discount_value'];
        $price = $price - ($price * $discount / 100);
    } elseif ($product['discount_type'] === 'fixed') {
        $discount = (float)$product['discount_value'];
        $price = $price - $discount;
    }

    return max(0, $price);
}

function pageUrl($page)
{
    $query = [];

    if (!empty($_GET['search'])) {
        $query['search'] = $_GET['search'];
    }

    if (!empty($_GET['brand'])) {
        $query['brand'] = $_GET['brand'];
    }

    $query['page'] = $page;

    return 'shop.php?' . http_build_query($query);
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<link rel="stylesheet" href="css/jquery-ui.min.css">

<style>
    .shop-page {
        padding-top: 60px;
        padding-bottom: 80px;
    }

    .shop-sidebar {
        background: #f7f7f7;
        padding: 25px;
        margin-bottom: 30px;
    }

    .shop-sidebar h4 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        border-bottom: 2px solid #7fad39;
        padding-bottom: 12px;
    }

    .brand-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .brand-list li {
        border-bottom: 1px solid #e5e5e5;
    }

    .brand-list li a {
        display: block;
        padding: 11px 5px;
        color: #555;
        font-size: 15px;
        transition: all .2s;
    }

    .brand-list li a:hover {
        color: #7fad39;
        padding-left: 10px;
    }

    .brand-list li a.active {
        color: #7fad39;
        font-weight: 700;
        padding-left: 10px;
    }

    .search-area {
        margin-bottom: 30px;
    }

    .search-form {
        display: flex;
        width: 100%;
    }

    .search-form input {
        height: 48px;
        border: 1px solid #ddd;
        padding: 0 18px;
        flex: 1;
        outline: none;
    }

    .search-form button {
        width: 120px;
        border: none;
        background: #7fad39;
        color: white;
        font-weight: 700;
        cursor: pointer;
    }

    .search-form button:hover {
        background: #6e9631;
    }

    .shop-result {
        margin-bottom: 25px;
    }

    .shop-result h5 {
        font-weight: 600;
    }

    .product-item {
        margin-bottom: 35px;
    }

    .product-item .product-image {
        height: 260px;
        background: #f5f5f5;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .product-item .product-image img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .product-item .product-info {
        padding-top: 15px;
    }

    .product-item .product-info h6 {
        margin-bottom: 8px;
        font-size: 16px;
    }

    .product-item .product-info h6 a {
        color: #333;
    }

    .product-item .product-info h6 a:hover {
        color: #7fad39;
    }

    .product-category {
        font-size: 13px;
        color: #999;
        margin-bottom: 5px;
    }

    .product-brand {
        font-size: 13px;
        color: #777;
        margin-bottom: 7px;
    }

    .product-price {
        font-size: 17px;
        font-weight: 700;
        color: #222;
    }

    .old-price {
        font-size: 14px;
        color: #999;
        text-decoration: line-through;
        margin-left: 8px;
        font-weight: 400;
    }

    .details-btn {
        display: inline-block;
        margin-top: 10px;
        font-size: 13px;
        color: #7fad39;
        font-weight: 700;
    }

    .details-btn:hover {
        color: #567d22;
    }

    .no-products {
        padding: 70px 20px;
        text-align: center;
        background: #f8f8f8;
    }

    .no-products i {
        font-size: 45px;
        color: #aaa;
        margin-bottom: 15px;
    }

    .pagination-area {
        margin-top: 20px;
        text-align: center;
    }

    .pagination-area a,
    .pagination-area span {
        display: inline-block;
        width: 40px;
        height: 40px;
        line-height: 40px;
        border: 1px solid #ddd;
        margin: 0 3px;
        color: #555;
        background: white;
    }

    .pagination-area a:hover {
        background: #7fad39;
        color: white;
        border-color: #7fad39;
    }

    .pagination-area span.active {
        background: #7fad39;
        color: white;
        border-color: #7fad39;
    }

    @media (max-width: 991px) {
        .shop-sidebar {
            margin-bottom: 35px;
        }

        .product-item .product-image {
            height: 220px;
        }
    }
</style>

<section
    class="breadcrumb-section set-bg"
    style="background-image: url('img/breadcrumb.jpg');">

    <div class="container">

        <div class="row">

            <div class="col-lg-12 text-center">

                <div class="breadcrumb__text">

                    <h2>Shop</h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">
                            Home
                        </a>

                        <span>
                            Shop
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="shop-page">

    <div class="container">

        <div class="row">

            <div class="col-lg-3 col-md-4">

                <div class="shop-sidebar">

                    <h4>
                        Brands
                    </h4>

                    <ul class="brand-list">

                        <li>

                            <a
                                href="shop.php"
                                class="<?php echo ($brand === '') ? 'active' : ''; ?>">

                                All Brands

                            </a>

                        </li>

                        <?php foreach ($brands as $brandRow): ?>

                            <?php
                            $brandName = $brandRow['name'];
                            $isActive = ($brand === $brandName);
                            ?>

                            <li>

                                <a
                                    href="shop.php?<?php
                                                    echo http_build_query([
                                                        'brand' => $brandName,
                                                        'search' => $search
                                                    ]);
                                                    ?>"
                                    class="<?php echo $isActive ? 'active' : ''; ?>">

                                    <?php echo htmlspecialchars($brandName); ?>

                                </a>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

            </div>

            <div class="col-lg-9 col-md-8">

                <div class="search-area">

                    <form
                        method="GET"
                        action="shop.php"
                        class="search-form">

                        <?php if ($brand !== ''): ?>

                            <input
                                type="hidden"
                                name="brand"
                                value="<?php echo htmlspecialchars($brand); ?>">

                        <?php endif; ?>

                        <input
                            type="text"
                            name="search"
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search product name...">

                        <button type="submit">
                            Search
                        </button>

                    </form>

                </div>

                <div class="shop-result">

                    <h5>

                        <?php if ($brand !== ''): ?>

                            Products from:

                            <strong>
                                <?php echo htmlspecialchars($brand); ?>
                            </strong>

                        <?php else: ?>

                            All Products

                        <?php endif; ?>

                        <span
                            style="
                            float:right;
                            font-size:14px;
                            color:#999;
                        ">

                            <?php echo $total_products; ?>

                            Products

                        </span>

                    </h5>

                </div>

                <div class="row">

                    <?php if (empty($products)): ?>

                        <div class="col-12">

                            <div class="no-products">

                                <i class="fa fa-shopping-bag"></i>

                                <h4>
                                    No Products Found
                                </h4>

                                <p>
                                    There are no products matching your search.
                                </p>

                                <a
                                    href="shop.php"
                                    style="
                                    color:#7fad39;
                                    font-weight:700;
                                ">

                                    View All Products

                                </a>

                            </div>

                        </div>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                            $finalPrice = getFinalPrice($product);

                            $hasDiscount =
                                $product['discount_type'] !== 'none'
                                &&
                                (float)$product['discount_value'] > 0;
                            ?>

                            <div class="col-lg-4 col-md-6 col-sm-6">

                                <div class="product-item">

                                    <div class="product-image">

                                        <?php if (!empty($product['image'])): ?>

                                            <img
                                                src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                onerror="this.style.display='none';">

                                        <?php else: ?>

                                            <img
                                                src="img/product/default.jpg"
                                                alt="No Image">

                                        <?php endif; ?>

                                    </div>

                                    <div class="product-info">

                                        <?php if (!empty($product['category'])): ?>

                                            <div class="product-category">

                                                <?php echo htmlspecialchars($product['category']); ?>

                                            </div>

                                        <?php endif; ?>

                                        <div class="product-brand">

                                            Brand:

                                            <strong>

                                                <?php echo htmlspecialchars($product['brand'] ?? 'N/A'); ?>

                                            </strong>

                                        </div>

                                        <h6>

                                            <a
                                                href="shop-details.php?id=<?php echo (int)$product['id']; ?>">

                                                <?php echo htmlspecialchars($product['product_name']); ?>

                                            </a>

                                        </h6>

                                        <div class="product-price">

                                            EGP

                                            <?php echo number_format($finalPrice, 2); ?>

                                            <?php if ($hasDiscount): ?>

                                                <span class="old-price">

                                                    EGP

                                                    <?php echo number_format((float)$product['selling_price'], 2); ?>

                                                </span>

                                            <?php endif; ?>

                                        </div>

                                        <a
                                            class="details-btn"
                                            href="shop-details.php?id=<?php echo (int)$product['id']; ?>">

                                            View Details

                                            <i class="fa fa-arrow-right"></i>

                                        </a>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </div>

                <?php if ($total_pages > 1): ?>

                    <div class="pagination-area">

                        <?php if ($page > 1): ?>

                            <a href="<?php echo pageUrl($page - 1); ?>">
                                &laquo;
                            </a>

                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                            <?php if ($i == $page): ?>

                                <span class="active">
                                    <?php echo $i; ?>
                                </span>

                            <?php else: ?>

                                <a href="<?php echo pageUrl($i); ?>">
                                    <?php echo $i; ?>
                                </a>

                            <?php endif; ?>

                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>

                            <a href="<?php echo pageUrl($page + 1); ?>">
                                &raquo;
                            </a>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>