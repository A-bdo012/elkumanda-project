
<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

require_once __DIR__ . '/../config/db.php';

$vendor_id = $_SESSION['user_id'];


// ========================================
// Get Product ID
// ========================================

$product_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: my_products.php');
    exit;
}


// ========================================
// Get Product
// Only current vendor can view it
// ========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ?
    AND vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $vendor_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);


// ========================================
// Product Not Found
// ========================================

if (!$product) {
    header('Location: my_products.php');
    exit;
}


// ========================================
// Get Gallery Images
// ========================================

$gallery_stmt = $pdo->prepare("
    SELECT id, image, created_at
    FROM product_images
    WHERE product_id = ?
    ORDER BY id ASC
");

$gallery_stmt->execute([$product_id]);

$gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);


// ========================================
// Calculate Final Price
// ========================================

$selling_price = (float) $product['selling_price'];
$discount_value = (float) $product['discount_value'];

$final_price = $selling_price;

if ($product['discount_type'] === 'percentage') {

    $final_price = $selling_price - (
        $selling_price * $discount_value / 100
    );

} elseif ($product['discount_type'] === 'fixed') {

    $final_price = $selling_price - $discount_value;

}

if ($final_price < 0) {
    $final_price = 0;
}


// ========================================
// Header / Sidebar / Topbar
// ========================================

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/vendor_sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

?>


<!-- Begin Page Content -->
<div class="container-fluid">


    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">


        <div>

            <h1 class="h3 mb-1 text-gray-800">
                Product Details
            </h1>

            <p class="mb-0 text-gray-600">
                View complete information about your product
            </p>

        </div>


        <div>


            <a
                href="my_products.php"
                class="btn btn-secondary shadow-sm mr-2"
            >

                <i class="fas fa-arrow-left mr-1"></i>

                Back to My Products

            </a>


            <a
                href="edit_product.php?id=<?php echo (int) $product['id']; ?>"
                class="btn btn-warning shadow-sm"
            >

                <i class="fas fa-edit mr-1"></i>

                Edit Product

            </a>


        </div>


    </div>


    <!-- Main Product Card -->
    <div class="card shadow mb-4">


        <!-- Card Header -->
        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">

                <i class="fas fa-box-open mr-2"></i>

                <?php echo htmlspecialchars($product['product_name']); ?>

            </h6>

        </div>


        <!-- Card Body -->
        <div class="card-body">


            <div class="row">


                <!-- ======================================== -->
                <!-- Product Image -->
                <!-- ======================================== -->

                <div class="col-lg-5 mb-4">


                    <div class="card border-left-primary h-100">


                        <div class="card-body">


                            <h6 class="font-weight-bold text-primary mb-3">

                                <i class="fas fa-image mr-2"></i>

                                Main Image

                            </h6>


                            <?php if (!empty($product['image'])): ?>


                                <div class="text-center">

                                    <img
                                        src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                        class="img-fluid"
                                        style="
                                            max-height: 400px;
                                            width: 100%;
                                            object-fit: contain;
                                            border-radius: 10px;
                                        "
                                    >

                                </div>


                            <?php else: ?>


                                <div
                                    class="d-flex align-items-center justify-content-center bg-light"
                                    style="
                                        height: 350px;
                                        border-radius: 10px;
                                    "
                                >

                                    <div class="text-center">

                                        <i class="fas fa-image fa-4x text-gray-300 mb-3"></i>

                                        <p class="text-muted mb-0">
                                            No main image available
                                        </p>

                                    </div>

                                </div>


                            <?php endif; ?>


                        </div>

                    </div>


                </div>


                <!-- ======================================== -->
                <!-- Basic Information -->
                <!-- ======================================== -->

                <div class="col-lg-7 mb-4">


                    <div class="card border-left-success h-100">


                        <div class="card-body">


                            <h6 class="font-weight-bold text-success mb-4">

                                <i class="fas fa-info-circle mr-2"></i>

                                Basic Information

                            </h6>


                            <!-- Product Name -->
                            <div class="mb-3">

                                <small class="text-muted d-block">
                                    Product Name
                                </small>

                                <h4 class="font-weight-bold text-gray-800 mb-0">

                                    <?php echo htmlspecialchars($product['product_name']); ?>

                                </h4>

                            </div>


                            <div class="row">


                                <!-- Category -->
                                <div class="col-md-6 mb-3">

                                    <small class="text-muted d-block">
                                        Category
                                    </small>

                                    <?php if (!empty($product['category'])): ?>

                                        <span class="font-weight-bold text-gray-800">

                                            <?php echo htmlspecialchars($product['category']); ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            N/A
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- Brand -->
                                <div class="col-md-6 mb-3">

                                    <small class="text-muted d-block">
                                        Brand
                                    </small>

                                    <?php if (!empty($product['brand'])): ?>

                                        <span class="font-weight-bold text-gray-800">

                                            <?php echo htmlspecialchars($product['brand']); ?>

                                        </span>

                                    <?php else: ?>

                                        <span class="text-muted">
                                            N/A
                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- Status -->
                                <div class="col-md-6 mb-3">

                                    <small class="text-muted d-block">
                                        Status
                                    </small>


                                    <?php if ($product['status'] === 'active'): ?>

                                        <span class="badge badge-success px-3 py-2">

                                            <i class="fas fa-check mr-1"></i>

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="badge badge-secondary px-3 py-2">

                                            <i class="fas fa-times mr-1"></i>

                                            Inactive

                                        </span>

                                    <?php endif; ?>

                                </div>


                                <!-- Product ID -->
                                <div class="col-md-6 mb-3">

                                    <small class="text-muted d-block">
                                        Product ID
                                    </small>

                                    <span class="font-weight-bold">

                                        #<?php echo (int) $product['id']; ?>

                                    </span>

                                </div>


                            </div>


                        </div>

                    </div>


                </div>


            </div>


            <!-- ======================================== -->
            <!-- Descriptions -->
            <!-- ======================================== -->

            <div class="row">


                <!-- Short Description -->
                <div class="col-lg-6 mb-4">


                    <div class="card h-100 border-left-info">


                        <div class="card-body">


                            <h6 class="font-weight-bold text-info mb-3">

                                <i class="fas fa-align-left mr-2"></i>

                                Short Description

                            </h6>


                            <?php if (!empty($product['short_description'])): ?>

                                <p class="text-gray-700 mb-0">

                                    <?php echo nl2br(htmlspecialchars($product['short_description'])); ?>

                                </p>

                            <?php else: ?>

                                <p class="text-muted mb-0">
                                    No short description available.
                                </p>

                            <?php endif; ?>


                        </div>

                    </div>


                </div>


                <!-- Full Description -->
                <div class="col-lg-6 mb-4">


                    <div class="card h-100 border-left-info">


                        <div class="card-body">


                            <h6 class="font-weight-bold text-info mb-3">

                                <i class="fas fa-align-justify mr-2"></i>

                                Full Description

                            </h6>


                            <?php if (!empty($product['full_description'])): ?>

                                <p class="text-gray-700 mb-0">

                                    <?php echo nl2br(htmlspecialchars($product['full_description'])); ?>

                                </p>

                            <?php else: ?>

                                <p class="text-muted mb-0">
                                    No full description available.
                                </p>

                            <?php endif; ?>


                        </div>

                    </div>


                </div>


            </div>


            <!-- ======================================== -->
            <!-- Pricing -->
            <!-- ======================================== -->

            <div class="card border-left-warning mb-4">


                <div class="card-body">


                    <h6 class="font-weight-bold text-warning mb-4">

                        <i class="fas fa-money-bill-wave mr-2"></i>

                        Pricing Information

                    </h6>


                    <div class="row">


                        <!-- Cost Price -->
                        <div class="col-md-4 mb-3">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Cost Price

                            </div>


                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?php
                                echo number_format(
                                    (float) $product['cost_price'],
                                    2
                                );
                                ?>

                                EGP

                            </div>


                        </div>


                        <!-- Selling Price -->
                        <div class="col-md-4 mb-3">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Selling Price

                            </div>


                            <div class="h5 mb-0 font-weight-bold text-success">

                                <?php
                                echo number_format(
                                    $selling_price,
                                    2
                                );
                                ?>

                                EGP

                            </div>


                        </div>


                        <!-- Final Price -->
                        <div class="col-md-4 mb-3">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Final Price

                            </div>


                            <div class="h5 mb-0 font-weight-bold text-primary">

                                <?php
                                echo number_format(
                                    $final_price,
                                    2
                                );
                                ?>

                                EGP

                            </div>


                        </div>


                    </div>


                    <hr>


                    <div class="row">


                        <!-- Discount Type -->
                        <div class="col-md-6">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Discount Type

                            </div>


                            <div class="font-weight-bold">

                                <?php if ($product['discount_type'] === 'percentage'): ?>

                                    <span class="badge badge-info px-3 py-2">

                                        Percentage

                                    </span>

                                <?php elseif ($product['discount_type'] === 'fixed'): ?>

                                    <span class="badge badge-info px-3 py-2">

                                        Fixed Amount

                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-secondary px-3 py-2">

                                        No Discount

                                    </span>

                                <?php endif; ?>

                            </div>


                        </div>


                        <!-- Discount Value -->
                        <div class="col-md-6">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Discount Value

                            </div>


                            <div class="font-weight-bold text-danger">

                                <?php if ($product['discount_type'] === 'percentage'): ?>

                                    <?php echo number_format($discount_value, 2); ?>%

                                <?php elseif ($product['discount_type'] === 'fixed'): ?>

                                    <?php echo number_format($discount_value, 2); ?> EGP

                                <?php else: ?>

                                    0.00

                                <?php endif; ?>

                            </div>


                        </div>


                    </div>


                </div>

            </div>


            <!-- ======================================== -->
            <!-- Inventory -->
            <!-- ======================================== -->

            <div class="card border-left-danger mb-4">


                <div class="card-body">


                    <h6 class="font-weight-bold text-danger mb-4">

                        <i class="fas fa-boxes mr-2"></i>

                        Inventory Information

                    </h6>


                    <div class="row">


                        <!-- Stock -->
                        <div class="col-md-6 mb-3">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Stock Quantity

                            </div>


                            <?php if ((int) $product['stock_quantity'] <= 0): ?>

                                <div>

                                    <span class="badge badge-danger px-3 py-2">

                                        <i class="fas fa-times mr-1"></i>

                                        Out of Stock

                                    </span>

                                </div>

                            <?php elseif (
                                (int) $product['stock_quantity']
                                <=
                                (int) $product['low_stock_alert']
                            ): ?>

                                <div>

                                    <span class="badge badge-warning px-3 py-2">

                                        <i class="fas fa-exclamation-triangle mr-1"></i>

                                        <?php echo (int) $product['stock_quantity']; ?>

                                        - Low Stock

                                    </span>

                                </div>

                            <?php else: ?>

                                <div class="h5 mb-0 font-weight-bold text-gray-800">

                                    <?php echo (int) $product['stock_quantity']; ?>

                                    Units

                                </div>

                            <?php endif; ?>


                        </div>


                        <!-- Low Stock Alert -->
                        <div class="col-md-6 mb-3">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Low Stock Alert

                            </div>


                            <div class="h5 mb-0 font-weight-bold text-gray-800">

                                <?php echo (int) $product['low_stock_alert']; ?>

                                Units

                            </div>


                        </div>


                    </div>


                </div>

            </div>


            <!-- ======================================== -->
            <!-- Gallery -->
            <!-- ======================================== -->

            <div class="card border-left-primary mb-4">


                <div class="card-body">


                    <div class="d-flex justify-content-between align-items-center mb-4">


                        <h6 class="font-weight-bold text-primary mb-0">

                            <i class="fas fa-images mr-2"></i>

                            Product Gallery

                        </h6>


                        <span class="badge badge-primary px-3 py-2">

                            <?php echo count($gallery_images); ?>

                            Image<?php echo count($gallery_images) != 1 ? 's' : ''; ?>

                        </span>


                    </div>


                    <?php if (!empty($gallery_images)): ?>


                        <div class="row">


                            <?php foreach ($gallery_images as $gallery): ?>


                                <div class="col-xl-3 col-lg-4 col-md-6 mb-4">


                                    <div class="card shadow-sm h-100">


                                        <img
                                            src="../uploads/products/gallery/<?php echo htmlspecialchars($gallery['image']); ?>"
                                            alt="Product Gallery"
                                            class="card-img-top"
                                            style="
                                                height: 200px;
                                                object-fit: cover;
                                                border-radius: 8px 8px 0 0;
                                            "
                                        >


                                        <div class="card-body p-2 text-center">

                                            <small class="text-muted">

                                                Gallery Image

                                            </small>

                                        </div>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        </div>


                    <?php else: ?>


                        <div class="text-center py-5">


                            <i class="fas fa-images fa-3x text-gray-300 mb-3"></i>


                            <p class="text-muted mb-0">

                                No gallery images available.

                            </p>


                        </div>


                    <?php endif; ?>


                </div>

            </div>


            <!-- ======================================== -->
            <!-- Product Date -->
            <!-- ======================================== -->

            <div class="card border-left-secondary mb-4">


                <div class="card-body">


                    <div class="row">


                        <div class="col-md-6">


                            <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">

                                Created At

                            </div>


                            <div class="font-weight-bold text-gray-800">

                                <?php
                                echo !empty($product['created_at'])
                                    ? htmlspecialchars($product['created_at'])
                                    : 'N/A';
                                ?>

                            </div>


                        </div>


                        <div class="col-md-6 text-md-right mt-3 mt-md-0">


                            <a
                                href="my_products.php"
                                class="btn btn-secondary mr-2"
                            >

                                <i class="fas fa-arrow-left mr-1"></i>

                                Back

                            </a>


                            <a
                                href="edit_product.php?id=<?php echo (int) $product['id']; ?>"
                                class="btn btn-warning"
                            >

                                <i class="fas fa-edit mr-1"></i>

                                Edit Product

                            </a>


                        </div>


                    </div>


                </div>

            </div>


        </div>

    </div>


</div>
<!-- /.container-fluid -->


<?php

require_once __DIR__ . '/includes/footer.php';

?>