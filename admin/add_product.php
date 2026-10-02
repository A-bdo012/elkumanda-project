<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/includes/auth.php';

requireRole('vendor');

$message = '';
$error = '';

/* =========================
   Get Categories
========================= */

$categories = $pdo->query("
    SELECT id, name
    FROM categories
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   Get Brands
========================= */

$brands = $pdo->query("
    SELECT id, name
    FROM brands
    ORDER BY name ASC
")->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   Add Product
========================= */

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


    /* =========================
       Validation
    ========================= */

    if ($product_name === '') {

        $error = 'Product name is required.';
    } elseif ($selling_price < 0 || $cost_price < 0) {

        $error = 'Prices cannot be negative.';
    } elseif ($stock_quantity < 0 || $low_stock_alert < 0) {

        $error = 'Stock values cannot be negative.';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {

        $error = 'Invalid status.';
    } elseif (!in_array($discount_type, ['none', 'percentage', 'fixed'], true)) {

        $error = 'Invalid discount type.';
    } elseif (
        $discount_type === 'percentage' &&
        ($discount_value < 0 || $discount_value > 100)
    ) {

        $error = 'Percentage discount must be between 0 and 100.';
    } elseif (
        $discount_type === 'fixed' &&
        $discount_value < 0
    ) {

        $error = 'Discount value cannot be negative.';
    }


    /* =========================
       Image Variables
    ========================= */

    $main_image = null;
    $gallery_files = [];

    $allowed_extensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp'
    ];


    /* =========================
       Main Image Upload
    ========================= */

    if (
        $error === '' &&
        isset($_FILES['main_image']) &&
        $_FILES['main_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['main_image']['error'] !== UPLOAD_ERR_OK) {

            $error = 'Main image upload failed.';
        } else {

            $extension = strtolower(
                pathinfo(
                    $_FILES['main_image']['name'],
                    PATHINFO_EXTENSION
                )
            );


            if (!in_array($extension, $allowed_extensions, true)) {

                $error = 'Invalid main image format.';
            } else {

                $upload_dir = __DIR__ . '/../uploads/products/';


                if (!is_dir($upload_dir)) {

                    mkdir($upload_dir, 0777, true);
                }


                $main_image =
                    uniqid('product_', true) .
                    '.' .
                    $extension;


                if (
                    !move_uploaded_file(
                        $_FILES['main_image']['tmp_name'],
                        $upload_dir . $main_image
                    )
                ) {

                    $error = 'Failed to upload main image.';

                    $main_image = null;
                }
            }
        }
    }


    /* =========================
       Gallery Upload
    ========================= */

    if (
        $error === '' &&
        isset($_FILES['gallery_images']) &&
        !empty($_FILES['gallery_images']['name'][0])
    ) {

        $gallery_dir =
            __DIR__ .
            '/../uploads/products/gallery/';


        if (!is_dir($gallery_dir)) {

            mkdir($gallery_dir, 0777, true);
        }


        foreach (
            $_FILES['gallery_images']['name']
            as $key => $name
        ) {

            if (
                $_FILES['gallery_images']['error'][$key]
                !== UPLOAD_ERR_OK
            ) {

                continue;
            }


            $extension = strtolower(
                pathinfo(
                    $name,
                    PATHINFO_EXTENSION
                )
            );


            if (
                !in_array(
                    $extension,
                    $allowed_extensions,
                    true
                )
            ) {

                continue;
            }


            $file_name =
                uniqid('gallery_', true) .
                '.' .
                $extension;


            if (
                move_uploaded_file(
                    $_FILES['gallery_images']['tmp_name'][$key],
                    $gallery_dir . $file_name
                )
            ) {

                $gallery_files[] = $file_name;
            }
        }
    }


    /* =========================
       Insert Product
    ========================= */

    if ($error === '') {

        try {

            $vendor_id = (int) $_SESSION['user_id'];


            $sql = "
                INSERT INTO products (
                    vendor_id,
                    product_name,
                    short_description,
                    full_description,
                    status,
                    category,
                    brand,
                    cost_price,
                    selling_price,
                    discount_type,
                    discount_value,
                    stock_quantity,
                    low_stock_alert,
                    image
                )

                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ";


            $stmt = $pdo->prepare($sql);


            $stmt->execute([

                $vendor_id,

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

                $main_image

            ]);


            $product_id = $pdo->lastInsertId();


            /* =========================
               Insert Gallery Images
            ========================= */

            if (!empty($gallery_files)) {

                $gallery_stmt = $pdo->prepare("
                    INSERT INTO product_images (
                        product_id,
                        image
                    )

                    VALUES (?, ?)
                ");


                foreach (
                    $gallery_files
                    as $gallery_image
                ) {

                    $gallery_stmt->execute([

                        $product_id,

                        $gallery_image

                    ]);
                }
            }


            $message =
                'Product added successfully.';
        } catch (PDOException $e) {


            /* Delete uploaded main image */

            if (
                $main_image &&
                file_exists(
                    __DIR__ .
                        '/../uploads/products/' .
                        $main_image
                )
            ) {

                unlink(
                    __DIR__ .
                        '/../uploads/products/' .
                        $main_image
                );
            }


            /* Delete uploaded gallery images */

            foreach (
                $gallery_files
                as $gallery_image
            ) {

                $gallery_path =
                    __DIR__ .
                    '/../uploads/products/gallery/' .
                    $gallery_image;


                if (file_exists($gallery_path)) {

                    unlink($gallery_path);
                }
            }


            $error =
                'Something went wrong. Please try again.';
        }
    }
}


require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/vendor_sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

?>


<div class="container-fluid">


    <!-- Page Heading -->

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <h1 class="h3 mb-0 text-gray-800">
            Add Product
        </h1>

    </div>


    <!-- Success Message -->

    <?php if ($message): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <i class="fas fa-check-circle mr-2"></i>

            <?php echo htmlspecialchars($message); ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert">

                <span>
                    &times;
                </span>

            </button>

        </div>

    <?php endif; ?>


    <!-- Error Message -->

    <?php if ($error): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert">

            <i class="fas fa-exclamation-circle mr-2"></i>

            <?php echo htmlspecialchars($error); ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert">

                <span>
                    &times;
                </span>

            </button>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data">

        <div class="row">


            <!-- =========================
                 Left Column
            ========================== -->

            <div class="col-lg-8">


                <!-- Basic Information -->

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">

                            Basic Information

                        </h6>

                    </div>


                    <div class="card-body">


                        <!-- Product Name -->

                        <div class="form-group">

                            <label>
                                Product Name
                            </label>

                            <input
                                type="text"
                                name="product_name"
                                class="form-control"
                                required
                                value="<?php echo htmlspecialchars(
                                            $_POST['product_name'] ?? ''
                                        ); ?>">

                        </div>


                        <!-- Short Description -->

                        <div class="form-group">

                            <label>
                                Short Description
                            </label>

                            <textarea
                                name="short_description"
                                class="form-control"
                                rows="3"><?php echo htmlspecialchars(
                                                $_POST['short_description'] ?? ''
                                            ); ?></textarea>

                        </div>


                        <!-- Full Description -->

                        <div class="form-group">

                            <label>
                                Full Description
                            </label>

                            <textarea
                                name="full_description"
                                class="form-control"
                                rows="5"><?php echo htmlspecialchars(
                                                $_POST['full_description'] ?? ''
                                            ); ?></textarea>

                        </div>


                        <div class="row">


                            <!-- Status -->

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
                                            <?php echo (
                                                ($_POST['status'] ?? 'active')
                                                === 'active'
                                            ) ? 'selected' : ''; ?>>
                                            Active
                                        </option>

                                        <option
                                            value="inactive"
                                            <?php echo (
                                                ($_POST['status'] ?? '')
                                                === 'inactive'
                                            ) ? 'selected' : ''; ?>>
                                            Inactive
                                        </option>

                                    </select>

                                </div>

                            </div>


                            <!-- Category -->

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


                                        <?php foreach (
                                            $categories
                                            as $cat
                                        ): ?>

                                            <option
                                                value="<?php echo htmlspecialchars(
                                                            $cat['name']
                                                        ); ?>"
                                                <?php echo (
                                                    ($_POST['category'] ?? '')
                                                    === $cat['name']
                                                ) ? 'selected' : ''; ?>>

                                                <?php echo htmlspecialchars(
                                                    $cat['name']
                                                ); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>


                            <!-- Brand -->

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


                                        <?php foreach (
                                            $brands
                                            as $br
                                        ): ?>

                                            <option
                                                value="<?php echo htmlspecialchars(
                                                            $br['name']
                                                        ); ?>"
                                                <?php echo (
                                                    ($_POST['brand'] ?? '')
                                                    === $br['name']
                                                ) ? 'selected' : ''; ?>>

                                                <?php echo htmlspecialchars(
                                                    $br['name']
                                                ); ?>

                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>


                        </div>

                    </div>

                </div>


                <!-- Pricing -->

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
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
                                        value="<?php echo htmlspecialchars(
                                                    $_POST['cost_price'] ?? '0'
                                                ); ?>">

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
                                        value="<?php echo htmlspecialchars(
                                                    $_POST['selling_price'] ?? '0'
                                                ); ?>">

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
                                            <?php echo (
                                                ($_POST['discount_type'] ?? 'none')
                                                === 'none'
                                            ) ? 'selected' : ''; ?>>
                                            None
                                        </option>

                                        <option
                                            value="percentage"
                                            <?php echo (
                                                ($_POST['discount_type'] ?? '')
                                                === 'percentage'
                                            ) ? 'selected' : ''; ?>>
                                            Percentage
                                        </option>

                                        <option
                                            value="fixed"
                                            <?php echo (
                                                ($_POST['discount_type'] ?? '')
                                                === 'fixed'
                                            ) ? 'selected' : ''; ?>>
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
                                        value="<?php echo htmlspecialchars(
                                                    $_POST['discount_value'] ?? '0'
                                                ); ?>">

                                </div>

                            </div>


                        </div>

                    </div>

                </div>


                <!-- Inventory -->

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
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
                                        value="<?php echo htmlspecialchars(
                                                    $_POST['stock_quantity'] ?? '0'
                                                ); ?>">

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
                                        value="<?php echo htmlspecialchars(
                                                    $_POST['low_stock_alert'] ?? '0'
                                                ); ?>">

                                </div>

                            </div>


                        </div>

                    </div>

                </div>


            </div>


            <!-- =========================
                 Right Column
            ========================== -->

            <div class="col-lg-4">


                <!-- Main Image -->

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
                            Main Image
                        </h6>

                    </div>


                    <div class="card-body">


                        <div class="custom-file mb-3">

                            <input
                                type="file"
                                name="main_image"
                                class="custom-file-input"
                                id="mainImage"
                                accept="image/*">

                            <label
                                class="custom-file-label"
                                for="mainImage">
                                Choose image
                            </label>

                        </div>


                        <div
                            id="mainImagePreview"
                            class="image-preview-box d-none">

                            <img
                                id="mainPreviewImage"
                                src=""
                                alt="Main Image Preview">

                            <div
                                class="preview-name"
                                id="mainPreviewName"></div>

                        </div>

                    </div>

                </div>


                <!-- Gallery -->

                <div class="card shadow mb-4">

                    <div class="card-header py-3">

                        <h6 class="m-0 font-weight-bold text-primary">
                            Gallery Images
                        </h6>

                    </div>


                    <div class="card-body">


                        <div class="custom-file mb-3">

                            <input
                                type="file"
                                name="gallery_images[]"
                                class="custom-file-input"
                                id="galleryImages"
                                accept="image/*"
                                multiple>

                            <label
                                class="custom-file-label"
                                for="galleryImages">
                                Choose images
                            </label>

                        </div>


                        <div class="gallery-info mb-3">

                            <i class="fas fa-images mr-1"></i>

                            <span id="galleryCount">
                                0 images selected
                            </span>

                        </div>


                        <div
                            id="galleryPreview"
                            class="gallery-preview"></div>


                    </div>

                </div>


                <!-- Save -->

                <button
                    type="submit"
                    class="btn btn-primary btn-block btn-lg mb-4">

                    <i class="fas fa-save mr-2"></i>

                    Save Product

                </button>


            </div>

        </div>

    </form>

</div>


<style>
    .image-preview-box {

        width: 100%;

        border: 1px solid #e3e6f0;

        border-radius: 10px;

        padding: 10px;

        background: #f8f9fc;

        text-align: center;
    }


    .image-preview-box img {

        width: 100%;

        height: 220px;

        object-fit: contain;

        border-radius: 8px;

        background: #fff;
    }


    .preview-name {

        margin-top: 10px;

        font-size: 13px;

        color: #6c757d;

        word-break: break-word;
    }


    .gallery-info {

        font-size: 13px;

        color: #858796;
    }


    .gallery-preview {

        display: grid;

        grid-template-columns: repeat(2, 1fr);

        gap: 12px;
    }


    .gallery-item {

        position: relative;

        border: 1px solid #e3e6f0;

        border-radius: 10px;

        padding: 6px;

        background: #f8f9fc;

        overflow: hidden;
    }


    .gallery-item img {

        width: 100%;

        height: 110px;

        object-fit: cover;

        border-radius: 7px;

        display: block;
    }


    .gallery-item-name {

        font-size: 11px;

        color: #6c757d;

        margin-top: 6px;

        white-space: nowrap;

        overflow: hidden;

        text-overflow: ellipsis;

        padding-right: 5px;
    }


    .gallery-remove {

        position: absolute;

        top: 10px;

        right: 10px;

        width: 28px;

        height: 28px;

        border: none;

        border-radius: 50%;

        background: #e74a3b;

        color: #fff;

        display: flex;

        align-items: center;

        justify-content: center;

        cursor: pointer;

        z-index: 2;

        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }


    .gallery-remove:hover {

        background: #c0392b;
    }


    @media (max-width: 575px) {

        .gallery-preview {

            grid-template-columns: repeat(2, 1fr);

        }

    }
</style>


<script>
    /* =========================
   Main Image Preview
========================= */

    const mainImageInput =
        document.getElementById('mainImage');

    const mainImagePreview =
        document.getElementById('mainImagePreview');

    const mainPreviewImage =
        document.getElementById('mainPreviewImage');

    const mainPreviewName =
        document.getElementById('mainPreviewName');


    mainImageInput.addEventListener(
        'change',
        function() {

            const file = this.files[0];


            if (!file) {

                mainImagePreview.classList.add('d-none');

                mainPreviewImage.src = '';

                mainPreviewName.textContent = '';

                return;
            }


            if (!file.type.startsWith('image/')) {

                this.value = '';

                mainImagePreview.classList.add('d-none');

                return;
            }


            const reader = new FileReader();


            reader.onload = function(event) {

                mainPreviewImage.src =
                    event.target.result;

                mainPreviewName.textContent =
                    file.name;

                mainImagePreview.classList.remove(
                    'd-none'
                );
            };


            reader.readAsDataURL(file);


            this.nextElementSibling.textContent =
                file.name;
        }
    );


    /* =========================
       Gallery
    ========================= */

    const galleryInput =
        document.getElementById('galleryImages');

    const galleryPreview =
        document.getElementById('galleryPreview');

    const galleryCount =
        document.getElementById('galleryCount');


    let galleryFiles = [];


    galleryInput.addEventListener(
        'change',
        function() {

            const newFiles =
                Array.from(this.files);


            newFiles.forEach(function(file) {

                if (!file.type.startsWith('image/')) {

                    return;
                }


                const exists =
                    galleryFiles.some(
                        function(existingFile) {

                            return (
                                existingFile.name === file.name &&
                                existingFile.size === file.size &&
                                existingFile.lastModified === file.lastModified
                            );
                        }
                    );


                if (!exists) {

                    galleryFiles.push(file);
                }

            });


            updateGalleryInput();

            renderGallery();
        }
    );


    function updateGalleryInput() {

        const dataTransfer =
            new DataTransfer();


        galleryFiles.forEach(function(file) {

            dataTransfer.items.add(file);

        });


        galleryInput.files =
            dataTransfer.files;


        if (galleryFiles.length === 0) {

            galleryInput.nextElementSibling.textContent =
                'Choose images';

        } else {

            galleryInput.nextElementSibling.textContent =
                galleryFiles.length +
                ' images selected';
        }
    }


    function renderGallery() {

        galleryPreview.innerHTML = '';


        galleryCount.textContent =
            galleryFiles.length +
            ' image' +
            (galleryFiles.length === 1 ? '' : 's') +
            ' selected';


        galleryFiles.forEach(
            function(file, index) {

                const item =
                    document.createElement('div');

                item.className =
                    'gallery-item';


                const image =
                    document.createElement('img');


                const name =
                    document.createElement('div');

                name.className =
                    'gallery-item-name';

                name.textContent =
                    file.name;


                const removeButton =
                    document.createElement('button');

                removeButton.type =
                    'button';

                removeButton.className =
                    'gallery-remove';

                removeButton.innerHTML =
                    '<i class="fas fa-times"></i>';


                removeButton.addEventListener(
                    'click',
                    function() {

                        galleryFiles.splice(index, 1);

                        updateGalleryInput();

                        renderGallery();
                    }
                );


                const reader =
                    new FileReader();


                reader.onload =
                    function(event) {

                        image.src =
                            event.target.result;
                    };


                reader.readAsDataURL(file);


                item.appendChild(image);

                item.appendChild(name);

                item.appendChild(removeButton);

                galleryPreview.appendChild(item);
            }
        );
    }


    /* =========================
       File Labels
    ========================= */

    document
        .querySelectorAll('.custom-file-input')
        .forEach(function(input) {

            input.addEventListener(
                'change',
                function() {

                    const label =
                        this.nextElementSibling;


                    if (this.id === 'galleryImages') {

                        return;
                    }


                    if (this.files.length === 1) {

                        label.textContent =
                            this.files[0].name;

                    } else if (this.files.length > 1) {

                        label.textContent =
                            this.files.length +
                            ' images selected';

                    } else {

                        label.textContent =
                            'Choose image';
                    }

                }
            );

        });
</script>


<?php require_once __DIR__ . '/includes/footer.php'; ?>