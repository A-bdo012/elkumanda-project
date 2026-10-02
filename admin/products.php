<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

/* =========================
   Brand Success Message
========================= */

$brand_success = $_SESSION['brand_success'] ?? '';
unset($_SESSION['brand_success']);


/* =========================
   Pagination
========================= */

$limit = 10;

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}


/* =========================
   Search & Filter
========================= */

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$allowed_statuses = [
    '',
    'active',
    'inactive'
];

if (!in_array($status, $allowed_statuses, true)) {
    $status = '';
}


/* =========================
   Build WHERE
========================= */

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "p.product_name LIKE ?";
    $params[] = "%$search%";
}

if ($status !== '') {

    $where[] = "p.status = ?";
    $params[] = $status;
}

$where_sql = '';

if (!empty($where)) {

    $where_sql = 'WHERE ' . implode(' AND ', $where);
}


/* =========================
   Count Products
========================= */

$count_sql = "
    SELECT COUNT(*)
    FROM products p
    $where_sql
";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);

$total_products = (int) $count_stmt->fetchColumn();

$total_pages = max(
    1,
    (int) ceil($total_products / $limit)
);

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $limit;


/* =========================
   Get Products
========================= */

$sql = "
    SELECT
        p.id,
        p.product_name,
        p.category,
        p.brand,
        p.selling_price,
        p.stock_quantity,
        p.status,
        p.image,
        p.created_at,
        u.name AS vendor_name

    FROM products p

    LEFT JOIN users u
        ON p.vendor_id = u.id

    $where_sql

    ORDER BY p.id DESC

    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$products = $stmt->fetchAll();


/* =========================
   Category Summary
========================= */

$category_sql = "
    SELECT
        category,
        COUNT(*) AS product_count

    FROM products

    WHERE category IS NOT NULL
    AND category <> ''

    GROUP BY category

    ORDER BY product_count DESC
";

$category_stmt = $pdo->query($category_sql);

$categories = $category_stmt->fetchAll();

?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<body id="page-top">

    <?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

    <?php require_once __DIR__ . '/includes/topbar.php'; ?>


    <div class="container-fluid">


        <!-- =========================
             Brand Success Message
        ========================== -->

        <?php if ($brand_success !== ''): ?>

            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert">

                <i class="fas fa-check-circle mr-2"></i>

                <?php echo htmlspecialchars($brand_success); ?>

                <button
                    type="button"
                    class="close"
                    data-dismiss="alert"
                    aria-label="Close">
                    <span aria-hidden="true">
                        &times;
                    </span>
                </button>

            </div>

        <?php endif; ?>


        <!-- =========================
             Page Heading
        ========================== -->

        <div class="d-sm-flex align-items-center justify-content-between mb-4">

            <h1 class="h3 mb-0 text-gray-800">
                Products
            </h1>


            <a
                href="add_brand.php"
                class="btn btn-primary">

                <i class="fas fa-plus mr-1"></i>

                Add New Brand

            </a>

        </div>


        <!-- =========================
             Products Card
        ========================== -->

        <div class="card shadow mb-4">


            <div class="card-header py-3">

                <h6 class="m-0 font-weight-bold text-primary">

                    <i class="fas fa-box mr-2"></i>

                    Product Management

                </h6>

            </div>


            <div class="card-body">


                <!-- =========================
                     Search & Filter
                ========================== -->

                <form
                    method="GET"
                    class="mb-4">

                    <div class="form-row align-items-end">


                        <!-- Search -->

                        <div class="col-md-6 mb-3">

                            <label>
                                Search Product
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by product name"
                                value="<?php echo htmlspecialchars($search); ?>">

                        </div>


                        <!-- Status -->

                        <div class="col-md-3 mb-3">

                            <label>
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-control">

                                <option
                                    value=""
                                    <?php echo $status === '' ? 'selected' : ''; ?>>
                                    All
                                </option>

                                <option
                                    value="active"
                                    <?php echo $status === 'active' ? 'selected' : ''; ?>>
                                    Active
                                </option>

                                <option
                                    value="inactive"
                                    <?php echo $status === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>

                            </select>

                        </div>


                        <!-- Buttons -->

                        <div class="col-md-3 mb-3">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="fas fa-search mr-1"></i>

                                Search

                            </button>


                            <a
                                href="products.php"
                                class="btn btn-secondary">

                                Reset

                            </a>

                        </div>


                    </div>

                </form>


                <!-- =========================
                     Products Table
                ========================== -->

                <div class="table-responsive">

                    <table class="table table-bordered table-hover">


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Image
                                </th>

                                <th>
                                    Product Name
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Brand
                                </th>

                                <th>
                                    Vendor
                                </th>

                                <th>
                                    Selling Price
                                </th>

                                <th>
                                    Stock
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (empty($products)): ?>

                                <tr>

                                    <td
                                        colspan="10"
                                        class="text-center">

                                        No products found.

                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php foreach ($products as $index => $product): ?>

                                    <tr>


                                        <!-- Number -->

                                        <td>

                                            <?php echo $offset + $index + 1; ?>

                                        </td>


                                        <!-- Image -->

                                        <td>

                                            <?php if (!empty($product['image'])): ?>

                                                <img
                                                    src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                    alt="Product"
                                                    style="
                                                        width:60px;
                                                        height:60px;
                                                        object-fit:cover;
                                                        border-radius:8px;
                                                    ">

                                            <?php else: ?>

                                                <span class="text-muted">
                                                    No Image
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Product Name -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['product_name']
                                            ); ?>

                                        </td>


                                        <!-- Category -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['category']
                                            ); ?>

                                        </td>


                                        <!-- Brand -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['brand']
                                            ); ?>

                                        </td>


                                        <!-- Vendor -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['vendor_name'] ?? 'Unknown'
                                            ); ?>

                                        </td>


                                        <!-- Selling Price -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['selling_price']
                                            ); ?>

                                        </td>


                                        <!-- Stock -->

                                        <td>

                                            <?php echo htmlspecialchars(
                                                $product['stock_quantity']
                                            ); ?>

                                        </td>


                                        <!-- Status -->

                                        <td>

                                            <?php if ($product['status'] === 'active'): ?>

                                                <span class="badge badge-success">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-danger">
                                                    Inactive
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Actions -->

                                        <td>

                                            <a
                                                href="view_product.php?id=<?php echo $product['id']; ?>"
                                                class="btn btn-info btn-sm"
                                                title="View">

                                                <i class="fas fa-eye"></i>

                                            </a>


                                            <a
                                                href="edit_product.php?id=<?php echo $product['id']; ?>"
                                                class="btn btn-warning btn-sm"
                                                title="Edit">

                                                <i class="fas fa-edit"></i>

                                            </a>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>


                            <?php endif; ?>


                        </tbody>

                    </table>

                </div>


                <!-- =========================
                     Pagination
                ========================== -->

                <?php if ($total_pages > 1): ?>

                    <nav class="mt-4">

                        <ul class="pagination justify-content-center">


                            <?php if ($page > 1): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
                                        Previous
                                    </a>

                                </li>

                            <?php endif; ?>


                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>

                                <li
                                    class="page-item <?php echo $i === $page ? 'active' : ''; ?>">

                                    <a
                                        class="page-link"
                                        href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">

                                        <?php echo $i; ?>

                                    </a>

                                </li>

                            <?php endfor; ?>


                            <?php if ($page < $total_pages): ?>

                                <li class="page-item">

                                    <a
                                        class="page-link"
                                        href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>">
                                        Next
                                    </a>

                                </li>

                            <?php endif; ?>


                        </ul>

                    </nav>

                <?php endif; ?>


            </div>

        </div>


        <!-- =========================
             Category Summary
        ========================== -->

        <div class="card shadow mb-4">


            <div class="card-header py-3">

                <h6 class="m-0 font-weight-bold text-primary">

                    <i class="fas fa-tags mr-2"></i>

                    Category Summary

                </h6>

            </div>


            <div class="card-body">


                <div class="table-responsive">

                    <table class="table table-bordered">


                        <thead>

                            <tr>

                                <th>
                                    #
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Products Count
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php if (empty($categories)): ?>

                                <tr>

                                    <td
                                        colspan="3"
                                        class="text-center">

                                        No categories found.

                                    </td>

                                </tr>


                            <?php else: ?>


                                <?php foreach ($categories as $index => $category): ?>

                                    <tr>


                                        <td>

                                            <?php echo $index + 1; ?>

                                        </td>


                                        <td>

                                            <?php echo htmlspecialchars(
                                                $category['category']
                                            ); ?>

                                        </td>


                                        <td>

                                            <?php echo htmlspecialchars(
                                                $category['product_count']
                                            ); ?>

                                        </td>


                                    </tr>

                                <?php endforeach; ?>


                            <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>

        </div>


    </div>


    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>