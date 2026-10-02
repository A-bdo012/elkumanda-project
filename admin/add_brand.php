<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');

    if ($name === '') {

        $error = 'Please enter brand name.';
    } elseif (strlen($name) < 2) {

        $error = 'Brand name must be at least 2 characters.';
    } elseif (strlen($name) > 100) {

        $error = 'Brand name is too long.';
    } else {

        // Check if brand already exists
        $check = $pdo->prepare("
            SELECT id
            FROM brands
            WHERE name = ?
            LIMIT 1
        ");

        $check->execute([$name]);

        if ($check->fetch()) {

            $error = 'This brand already exists.';
        } else {

            // Insert brand
            $stmt = $pdo->prepare("
                INSERT INTO brands (name)
                VALUES (?)
            ");

            $stmt->execute([$name]);

            // Success message
            $_SESSION['brand_success'] = 'Brand "' . $name . '" added successfully.';

            // Go back to Products page
            header('Location: products.php');
            exit;
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/admin_sidebar.php';
include __DIR__ . '/includes/topbar.php';

?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Add Brand
            </h1>

            <p class="mb-0 text-gray-600">
                Add a new brand to your store.
            </p>
        </div>

        <a href="products.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Products
        </a>

    </div>


    <!-- Error -->
    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <i class="fas fa-exclamation-circle mr-2"></i>

            <?php echo htmlspecialchars($error); ?>

            <button
                type="button"
                class="close"
                data-dismiss="alert"
                aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>

        </div>

    <?php endif; ?>


    <!-- Add Brand Card -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-tag mr-2"></i>
                Add New Brand
            </h6>

        </div>

        <div class="card-body">

            <form method="POST">

                <div class="form-group">

                    <label for="name" class="font-weight-bold">
                        Brand Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        class="form-control"
                        placeholder="Enter brand name"
                        maxlength="100"
                        required>

                </div>

                <button
                    type="submit"
                    class="btn btn-primary">
                    <i class="fas fa-plus mr-1"></i>
                    Add Brand
                </button>

                <a
                    href="products.php"
                    class="btn btn-light ml-2">
                    Cancel
                </a>

            </form>

        </div>

    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>