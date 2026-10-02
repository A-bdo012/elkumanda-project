<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

require_once __DIR__ . '/../config/db.php';

$vendor_id = (int) $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_products.php');
    exit;
}

$product_id = (int) $_GET['id'];

$stmt = $pdo->prepare("
    SELECT *
    FROM products
    WHERE id = ? AND vendor_id = ?
    LIMIT 1
");

$stmt->execute([
    $product_id,
    $vendor_id
]);

$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: my_products.php');
    exit;
}

$category_stmt = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
");

$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

$brand_stmt = $pdo->query("
    SELECT id, name
    FROM brands
    ORDER BY name ASC
");

$brands = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);

$gallery_stmt = $pdo->prepare("
    SELECT id, image
    FROM product_images
    WHERE product_id = ?
    ORDER BY id ASC
");

$gallery_stmt->execute([$product_id]);

$gallery_images = $gallery_stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $product_name = trim($_POST['product_name'] ?? '');
    $short_description = trim($_POST['short_description'] ?? '');
    $full_description = trim($_POST['full_description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $category = trim($_POST['category'] ?? '');
    $brand = trim($_POST['brand'] ?? '');

    $cost_price = (float) ($_POST['cost_price'] ?? 0);
    $selling_price = (float) ($_POST['selling_price'] ?? 0);

    $discount_type = $_POST['discount_type'] ?? 'none';
    $discount_value = (float) ($_POST['discount_value'] ?? 0);

    $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
    $low_stock_alert = (int) ($_POST['low_stock_alert'] ?? 0);

    if ($product_name === '') {
        $error = 'Product name is required.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $error = 'Invalid status.';
    } elseif (!in_array($discount_type, ['none', 'percentage', 'fixed'], true)) {
        $error = 'Invalid discount type.';
    } else {

        $allowed_extensions = [
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        ];

        $products_path = __DIR__ . '/../uploads/products/';
        $gallery_path = __DIR__ . '/../uploads/products/gallery/';

        try {

            $pdo->beginTransaction();

            $old_main_image = $product['image'];

            $new_main_image = $old_main_image;

            if (
                isset($_FILES['main_image']) &&
                $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                if ($_FILES['main_image']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Failed to upload main image.');
                }

                $extension = strtolower(
                    pathinfo(
                        $_FILES['main_image']['name'],
                        PATHINFO_EXTENSION
                    )
                );

                if (!in_array($extension, $allowed_extensions, true)) {
                    throw new Exception('Invalid main image format.');
                }

                $new_main_image = 'product_' . uniqid('', true) . '.' . $extension;

                if (!move_uploaded_file(
                    $_FILES['main_image']['tmp_name'],
                    $products_path . $new_main_image
                )) {
                    throw new Exception('Failed to save main image.');
                }
            }

            $update_stmt = $pdo->prepare("
                UPDATE products
                SET
                    product_name = ?,
                    short_description = ?,
                    full_description = ?,
                    status = ?,
                    category = ?,
                    brand = ?,
                    cost_price = ?,
                    selling_price = ?,
                    discount_type = ?,
                    discount_value = ?,
                    stock_quantity = ?,
                    low_stock_alert = ?,
                    image = ?
                WHERE id = ? AND vendor_id = ?
            ");

            $update_stmt->execute([
                $product_name,
                $short_description,
                $full_description,
                $status,
                $category,
                $brand,
                $cost_price,
                $selling_price,
                $discount_type,
                $discount_value,
                $stock_quantity,
                $low_stock_alert,
                $new_main_image,
                $product_id,
                $vendor_id
            ]);

            $delete_gallery_ids = $_POST['delete_gallery_ids'] ?? [];

            if (is_array($delete_gallery_ids) && !empty($delete_gallery_ids)) {

                $delete_gallery_ids = array_map(
                    'intval',
                    $delete_gallery_ids
                );

                $delete_gallery_ids = array_values(
                    array_filter(
                        $delete_gallery_ids,
                        function ($id) {
                            return $id > 0;
                        }
                    )
                );

                if (!empty($delete_gallery_ids)) {

                    $placeholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($delete_gallery_ids),
                            '?'
                        )
                    );

                    $gallery_delete_stmt = $pdo->prepare("
                        SELECT id, image
                        FROM product_images
                        WHERE product_id = ?
                        AND id IN ($placeholders)
                    ");

                    $gallery_delete_stmt->execute(
                        array_merge(
                            [$product_id],
                            $delete_gallery_ids
                        )
                    );

                    $images_to_delete = $gallery_delete_stmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );

                    $delete_stmt = $pdo->prepare("
                        DELETE FROM product_images
                        WHERE product_id = ?
                        AND id IN ($placeholders)
                    ");

                    $delete_stmt->execute(
                        array_merge(
                            [$product_id],
                            $delete_gallery_ids
                        )
                    );

                    foreach ($images_to_delete as $image) {

                        if (!empty($image['image'])) {

                            $image_name = basename($image['image']);

                            $image_path = $gallery_path . $image_name;

                            if (file_exists($image_path)) {
                                unlink($image_path);
                            }
                        }
                    }
                }
            }

            if (
                isset($_FILES['gallery_images']) &&
                isset($_FILES['gallery_images']['name']) &&
                is_array($_FILES['gallery_images']['name'])
            ) {

                $gallery_count = count($_FILES['gallery_images']['name']);

                for ($i = 0; $i < $gallery_count; $i++) {

                    if (
                        $_FILES['gallery_images']['error'][$i]
                        === UPLOAD_ERR_NO_FILE
                    ) {
                        continue;
                    }

                    if (
                        $_FILES['gallery_images']['error'][$i]
                        !== UPLOAD_ERR_OK
                    ) {
                        continue;
                    }

                    $extension = strtolower(
                        pathinfo(
                            $_FILES['gallery_images']['name'][$i],
                            PATHINFO_EXTENSION
                        )
                    );

                    if (!in_array($extension, $allowed_extensions, true)) {
                        continue;
                    }

                    $gallery_image_name =
                        'gallery_' .
                        uniqid('', true) .
                        '.' .
                        $extension;

                    if (move_uploaded_file(
                        $_FILES['gallery_images']['tmp_name'][$i],
                        $gallery_path . $gallery_image_name
                    )) {

                        $gallery_insert_stmt = $pdo->prepare("
                            INSERT INTO product_images
                            (product_id, image)
                            VALUES (?, ?)
                        ");

                        $gallery_insert_stmt->execute([
                            $product_id,
                            $gallery_image_name
                        ]);
                    }
                }
            }

            $pdo->commit();

            if (
                !empty($old_main_image) &&
                $new_main_image !== $old_main_image
            ) {

                $old_image_name = basename($old_main_image);

                $old_image_path =
                    $products_path . $old_image_name;

                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }

            header('Location: my_products.php?updated=1');
            exit;
        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (
                isset($new_main_image) &&
                $new_main_image !== $product['image']
            ) {

                $new_image_path =
                    $products_path . basename($new_main_image);

                if (file_exists($new_image_path)) {
                    unlink($new_image_path);
                }
            }

            $error = 'Something went wrong. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/vendor_sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Edit Product
            </h1>

            <p class="mb-0 text-gray-600">
                Update your product information and images
            </p>
        </div>

        <a
            href="my_products.php"
            class="btn btn-secondary shadow-sm">

            <i class="fas fa-arrow-left fa-sm mr-1"></i>

            Back to Products

        </a>

    </div>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="fas fa-exclamation-circle mr-2"></i>

            <?php echo htmlspecialchars($error); ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert">

                <span>&times;</span>

            </button>

        </div>

    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        id="editProductForm">

        <div class="row">

            <div class="col-lg-8">

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">

                            <i class="fas fa-info-circle mr-2"></i>

                            Basic Information

                        </h6>

                    </div>

                    <div class="card-body">

                        <div class="form-group">

                            <label>
                                Product Name
                            </label>

                            <input
                                type="text"
                                name="product_name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($product['product_name']); ?>"
                                required>

                        </div>

                        <div class="form-group">

                            <label>
                                Short Description
                            </label>

                            <textarea
                                name="short_description"
                                class="form-control"
                                rows="3"><?php echo htmlspecialchars($product['short_description'] ?? ''); ?></textarea>

                        </div>

                        <div class="form-group">

                            <label>
                                Full Description
                            </label>

                            <textarea
                                name="full_description"
                                class="form-control"
                                rows="5"><?php echo htmlspecialchars($product['full_description'] ?? ''); ?></textarea>

                        </div>

                        <div class="row">

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Status
                                    </label>

                                    <select
                                        name="status"
                                        class="form-control">

                                        <option
                                            value="active"
                                            <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>

                                            Active

                                        </option>

                                        <option
                                            value="inactive"
                                            <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>

                                            Inactive

                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Category
                                    </label>

                                    <select
                                        name="category"
                                        class="form-control">

                                        <option value="">
                                            Select Category
                                        </option>

                                        <?php foreach ($categories as $category_item): ?>

                                            <option
                                                value="<?php echo htmlspecialchars($category_item['name']); ?>"
                                                <?php echo $product['category'] === $category_item['name'] ? 'selected' : ''; ?>>

                                                <?php echo htmlspecialchars($category_item['name']); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="form-group">

                                    <label>
                                        Brand
                                    </label>

                                    <select
                                        name="brand"
                                        class="form-control">

                                        <option value="">
                                            Select Brand
                                        </option>

                                        <?php foreach ($brands as $brand_item): ?>

                                            <option
                                                value="<?php echo htmlspecialchars($brand_item['name']); ?>"
                                                <?php echo $product['brand'] === $brand_item['name'] ? 'selected' : ''; ?>>

                                                <?php echo htmlspecialchars($brand_item['name']); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">

                            <i class="fas fa-tag mr-2"></i>

                            Pricing

                        </h6>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Cost Price
                                    </label>

                                    <input
                                        type="number"
                                        name="cost_price"
                                        class="form-control"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($product['cost_price']); ?>">

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Selling Price
                                    </label>

                                    <input
                                        type="number"
                                        name="selling_price"
                                        class="form-control"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($product['selling_price']); ?>">

                                </div>

                            </div>

                        </div>

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Discount Type
                                    </label>

                                    <select
                                        name="discount_type"
                                        class="form-control">

                                        <option
                                            value="none"
                                            <?php echo $product['discount_type'] === 'none' ? 'selected' : ''; ?>>

                                            None

                                        </option>

                                        <option
                                            value="percentage"
                                            <?php echo $product['discount_type'] === 'percentage' ? 'selected' : ''; ?>>

                                            Percentage

                                        </option>

                                        <option
                                            value="fixed"
                                            <?php echo $product['discount_type'] === 'fixed' ? 'selected' : ''; ?>>

                                            Fixed

                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Discount Value
                                    </label>

                                    <input
                                        type="number"
                                        name="discount_value"
                                        class="form-control"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($product['discount_value']); ?>">

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">

                            <i class="fas fa-boxes mr-2"></i>

                            Inventory

                        </h6>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Stock Quantity
                                    </label>

                                    <input
                                        type="number"
                                        name="stock_quantity"
                                        class="form-control"
                                        min="0"
                                        value="<?php echo htmlspecialchars($product['stock_quantity']); ?>">

                                </div>

                            </div>

                            <div class="col-md-6">

                                <div class="form-group">

                                    <label>
                                        Low Stock Alert
                                    </label>

                                    <input
                                        type="number"
                                        name="low_stock_alert"
                                        class="form-control"
                                        min="0"
                                        value="<?php echo htmlspecialchars($product['low_stock_alert']); ?>">

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-lg-4">

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">

                            <i class="fas fa-image mr-2"></i>

                            Main Image

                        </h6>

                    </div>

                    <div class="card-body">

                        <div class="main-image-box">

                            <?php if (!empty($product['image'])): ?>

                                <img
                                    id="mainImagePreview"
                                    src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                    alt="Main Product Image">

                            <?php else: ?>

                                <div id="mainImagePlaceholder">

                                    <i class="fas fa-image fa-3x text-gray-300"></i>

                                    <p class="text-muted mt-2 mb-0">
                                        No main image
                                    </p>

                                </div>

                                <img
                                    id="mainImagePreview"
                                    src=""
                                    alt="Main Product Image"
                                    style="display:none;">

                            <?php endif; ?>

                        </div>

                        <div class="custom-file mt-3">

                            <input
                                type="file"
                                name="main_image"
                                class="custom-file-input"
                                id="mainImageInput"
                                accept="image/*">

                            <label
                                class="custom-file-label"
                                for="mainImageInput">

                                Replace Main Image

                            </label>

                        </div>

                        <small class="form-text text-muted">
                            JPG, JPEG, PNG, GIF or WEBP
                        </small>

                    </div>

                </div>

                <div class="card shadow mb-4">

                    <div class="card-header py-3 d-flex justify-content-between align-items-center">

                        <h6 class="m-0 font-weight-bold text-primary">

                            <i class="fas fa-images mr-2"></i>

                            Gallery

                        </h6>

                        <span class="badge badge-primary">

                            <?php echo count($gallery_images); ?>

                        </span>

                    </div>

                    <div class="card-body">

                        <?php if (!empty($gallery_images)): ?>

                            <div class="gallery-grid">

                                <?php foreach ($gallery_images as $gallery): ?>

                                    <div
                                        class="gallery-item"
                                        id="gallery-<?php echo (int) $gallery['id']; ?>">

                                        <img
                                            src="../uploads/products/gallery/<?php echo htmlspecialchars($gallery['image']); ?>"
                                            alt="Gallery Image">

                                        <button
                                            type="button"
                                            class="gallery-delete-btn"
                                            onclick="deleteGalleryImage(<?php echo (int) $gallery['id']; ?>)">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                        <input
                                            type="checkbox"
                                            name="delete_gallery_ids[]"
                                            value="<?php echo (int) $gallery['id']; ?>"
                                            id="delete-<?php echo (int) $gallery['id']; ?>"
                                            class="gallery-delete-input">

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="text-center py-4">

                                <i class="fas fa-images fa-3x text-gray-300 mb-3"></i>

                                <p class="text-muted mb-0">
                                    No gallery images yet
                                </p>

                            </div>

                        <?php endif; ?>

                        <div class="mt-4">

                            <label class="btn btn-outline-primary btn-block mb-2">

                                <i class="fas fa-plus mr-2"></i>

                                Add More Images

                                <input
                                    type="file"
                                    name="gallery_images[]"
                                    id="galleryInput"
                                    accept="image/*"
                                    multiple
                                    hidden>

                            </label>

                            <div
                                id="newGalleryPreview"
                                class="new-gallery-preview">
                            </div>

                        </div>

                        <small class="text-muted">
                            You can select multiple images at once.
                        </small>

                    </div>

                </div>

            </div>

        </div>

        <div class="card shadow mb-4">

            <div class="card-body">

                <div class="d-flex justify-content-end">

                    <a
                        href="my_products.php"
                        class="btn btn-secondary mr-2">

                        Cancel

                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        <i class="fas fa-save mr-2"></i>

                        Save Changes

                    </button>

                </div>

            </div>

        </div>

    </form>

</div>

<style>
    .main-image-box {
        width: 100%;
        height: 280px;
        border: 2px dashed #d1d3e2;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8f9fc;
    }

    .main-image-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .gallery-item {
        position: relative;
        height: 130px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #e3e6f0;
        background: #f8f9fc;
    }

    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .gallery-delete-btn {
        position: absolute;
        top: 7px;
        right: 7px;
        width: 34px;
        height: 34px;
        border: none;
        border-radius: 50%;
        background: #e74a3b;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
    }

    .gallery-delete-btn:hover {
        background: #c0392b;
    }

    .gallery-delete-input {
        display: none;
    }

    .gallery-item.marked-delete {
        opacity: 0.35;
        border: 2px solid #e74a3b;
    }

    .gallery-item.marked-delete::after {
        content: "Marked for deletion";
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(231, 74, 59, 0.9);
        color: #fff;
        text-align: center;
        padding: 6px;
        font-size: 11px;
    }

    .new-gallery-preview {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        margin-top: 12px;
    }

    .new-gallery-preview img {
        width: 100%;
        height: 90px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #e3e6f0;
    }

    @media (max-width: 576px) {

        .gallery-grid {
            grid-template-columns: 1fr;
        }

        .new-gallery-preview {
            grid-template-columns: 1fr;
        }

    }
</style>

<script>
    $(document).ready(function() {

        $('#mainImageInput').on('change', function(event) {

            var file = event.target.files[0];

            if (!file) {
                return;
            }

            var reader = new FileReader();

            reader.onload = function(e) {

                $('#mainImagePreview')
                    .attr('src', e.target.result)
                    .show();

                $('#mainImagePlaceholder').hide();

            };

            reader.readAsDataURL(file);

            $(this)
                .next('.custom-file-label')
                .html(file.name);

        });

        $('#galleryInput').on('change', function(event) {

            var files = event.target.files;

            $('#newGalleryPreview').html('');

            for (var i = 0; i < files.length; i++) {

                if (!files[i].type.startsWith('image/')) {
                    continue;
                }

                var reader = new FileReader();

                reader.onload = function(e) {

                    $('#newGalleryPreview').append(
                        '<img src="' +
                        e.target.result +
                        '" alt="New Gallery Image">'
                    );

                };

                reader.readAsDataURL(files[i]);
            }

        });

    });

    function deleteGalleryImage(id) {

        var item = $('#gallery-' + id);
        var input = $('#delete-' + id);
        var button = item.find('.gallery-delete-btn');

        if (input.is(':checked')) {

            input.prop('checked', false);

            item.removeClass('marked-delete');

            button.html('<i class="fas fa-trash"></i>');

        } else {

            input.prop('checked', true);

            item.addClass('marked-delete');

            button.html('<i class="fas fa-undo"></i>');

        }

    }
</script>

<?php

require_once __DIR__ . '/includes/footer.php';

?>