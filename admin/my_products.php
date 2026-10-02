<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

require_once __DIR__ . '/../config/db.php';

$vendor_id = $_SESSION['user_id'];

$products_per_page = 10;

$current_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;

if ($current_page < 1) {
    $current_page = 1;
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

if (!in_array($status_filter, ['', 'active', 'inactive'], true)) {
    $status_filter = '';
}

$where = ['vendor_id = ?'];
$params = [$vendor_id];

if ($search !== '') {
    $where[] = 'product_name LIKE ?';
    $params[] = '%' . $search . '%';
}

if ($status_filter !== '') {
    $where[] = 'status = ?';
    $params[] = $status_filter;
}

$where_sql = implode(' AND ', $where);

$count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM products
    WHERE $where_sql
");

$count_stmt->execute($params);

$total_products = (int) $count_stmt->fetchColumn();

$total_pages = max(
    1,
    (int) ceil($total_products / $products_per_page)
);

if ($current_page > $total_pages) {
    $current_page = $total_pages;
}

$offset = ($current_page - 1) * $products_per_page;

$stmt = $pdo->prepare("
    SELECT
        id,
        product_name,
        category,
        brand,
        selling_price,
        stock_quantity,
        status,
        image,
        created_at
    FROM products
    WHERE $where_sql
    ORDER BY id DESC
    LIMIT $products_per_page OFFSET $offset
");

$stmt->execute($params);

$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query_params = [];

if ($search !== '') {
    $query_params['search'] = $search;
}

if ($status_filter !== '') {
    $query_params['status'] = $status_filter;
}

function pageUrl($page, $query_params)
{
    $query_params['page'] = $page;
    return '?' . http_build_query($query_params);
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/vendor_sidebar.php';
require_once __DIR__ . '/includes/topbar.php';

?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>

            <h1 class="h3 mb-1 text-gray-800">
                My Products
            </h1>

            <p class="mb-0 text-gray-600">
                Manage and view all your products
            </p>

        </div>

        <a
            href="add_product.php"
            class="btn btn-primary shadow-sm">

            <i class="fas fa-plus fa-sm text-white-50 mr-1"></i>

            Add New Product

        </a>

    </div>

    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <i class="fas fa-check-circle mr-2"></i>

            Product deleted successfully.

            <button
                type="button"
                class="close"
                data-dismiss="alert">

                <span>&times;</span>

            </button>

        </div>

    <?php endif; ?>

    <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert">

            <i class="fas fa-check-circle mr-2"></i>

            Product updated successfully.

            <button
                type="button"
                class="close"
                data-dismiss="alert">

                <span>&times;</span>

            </button>

        </div>

    <?php endif; ?>

    <div class="card shadow mb-4">

        <div class="card-header py-3 d-flex justify-content-between align-items-center">

            <h6 class="m-0 font-weight-bold text-primary">

                <i class="fas fa-box mr-2"></i>

                My Products

            </h6>

            <span class="badge badge-primary px-3 py-2">

                <?php echo $total_products; ?>

                Product<?php echo $total_products != 1 ? 's' : ''; ?>

            </span>

        </div>

        <div class="card-body">

            <form method="GET" class="mb-4">

                <div class="row">

                    <div class="col-md-6 mb-2">

                        <label class="font-weight-bold text-gray-700">
                            Search by Product Name
                        </label>

                        <div class="input-group">

                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                            </div>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search product name..."
                                value="<?php echo htmlspecialchars($search); ?>">

                        </div>

                    </div>

                    <div class="col-md-3 mb-2">

                        <label class="font-weight-bold text-gray-700">
                            Filter by Status
                        </label>

                        <select
                            name="status"
                            class="form-control">

                            <option value=""
                                <?php echo $status_filter === '' ? 'selected' : ''; ?>>
                                All
                            </option>

                            <option value="active"
                                <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                                Active
                            </option>

                            <option value="inactive"
                                <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                                Inactive
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-2">

                        <label class="font-weight-bold text-gray-700 d-block">
                            &nbsp;
                        </label>

                        <div class="d-flex">

                            <button
                                type="submit"
                                class="btn btn-primary mr-2">

                                <i class="fas fa-search mr-1"></i>

                                Search

                            </button>

                            <a
                                href="my_products.php"
                                class="btn btn-secondary">

                                <i class="fas fa-sync-alt mr-1"></i>

                                Reset

                            </a>

                        </div>

                    </div>

                </div>

            </form>

            <?php if (empty($products)): ?>

                <div class="text-center py-5">

                    <div class="mb-4">

                        <i class="fas fa-box-open fa-4x text-gray-300"></i>

                    </div>

                    <h5 class="text-gray-800 font-weight-bold">
                        No Products Found
                    </h5>

                    <?php if ($search !== '' || $status_filter !== ''): ?>

                        <p class="text-gray-500 mb-4">
                            No products match your search or filter.
                        </p>

                        <a
                            href="my_products.php"
                            class="btn btn-secondary">

                            <i class="fas fa-sync-alt mr-2"></i>

                            Clear Filters

                        </a>

                    <?php else: ?>

                        <p class="text-gray-500 mb-4">
                            You haven't added any products yet.
                        </p>

                        <a
                            href="add_product.php"
                            class="btn btn-primary">

                            <i class="fas fa-plus mr-2"></i>

                            Add Your First Product

                        </a>

                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table
                        class="table table-bordered table-hover"
                        width="100%"
                        cellspacing="0">

                        <thead class="thead-light">

                            <tr>

                                <th
                                    style="width: 80px;"
                                    class="text-center">

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
                                    Selling Price
                                </th>

                                <th>
                                    Stock
                                </th>

                                <th>
                                    Status
                                </th>

                                <th
                                    style="width: 190px;"
                                    class="text-center">

                                    Actions

                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($products as $product): ?>

                                <tr>

                                    <td class="text-center align-middle">

                                        <?php if (!empty($product['image'])): ?>

                                            <img
                                                src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                                alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                                style="
                                                    width: 55px;
                                                    height: 55px;
                                                    object-fit: cover;
                                                    border-radius: 8px;
                                                    border: 1px solid #ddd;
                                                ">

                                        <?php else: ?>

                                            <div
                                                class="d-flex align-items-center justify-content-center bg-light"
                                                style="
                                                    width: 55px;
                                                    height: 55px;
                                                    border-radius: 8px;
                                                    margin: auto;
                                                ">

                                                <i class="fas fa-image text-gray-400"></i>

                                            </div>

                                        <?php endif; ?>

                                    </td>

                                    <td class="align-middle">

                                        <div class="font-weight-bold text-gray-800">

                                            <?php echo htmlspecialchars($product['product_name']); ?>

                                        </div>

                                    </td>

                                    <td class="align-middle">

                                        <?php if (!empty($product['category'])): ?>

                                            <?php echo htmlspecialchars($product['category']); ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                N/A
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="align-middle">

                                        <?php if (!empty($product['brand'])): ?>

                                            <?php echo htmlspecialchars($product['brand']); ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                N/A
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="align-middle">

                                        <span class="font-weight-bold text-success">

                                            <?php
                                            echo number_format(
                                                (float) $product['selling_price'],
                                                2
                                            );
                                            ?>

                                            EGP

                                        </span>

                                    </td>

                                    <td class="align-middle">

                                        <?php if ((int) $product['stock_quantity'] <= 0): ?>

                                            <span class="badge badge-danger">
                                                Out of Stock
                                            </span>

                                        <?php elseif ((int) $product['stock_quantity'] <= 5): ?>

                                            <span class="badge badge-warning">

                                                <?php echo (int) $product['stock_quantity']; ?>

                                                Low Stock

                                            </span>

                                        <?php else: ?>

                                            <span class="font-weight-bold">

                                                <?php echo (int) $product['stock_quantity']; ?>

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="align-middle">

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

                                    </td>

                                    <td class="text-center align-middle">

                                        <a
                                            href="view_product.php?id=<?php echo (int) $product['id']; ?>"
                                            class="btn btn-info btn-sm"
                                            title="View Product">

                                            <i class="fas fa-eye"></i>

                                        </a>

                                        <a
                                            href="edit_product.php?id=<?php echo (int) $product['id']; ?>"
                                            class="btn btn-warning btn-sm"
                                            title="Edit Product">

                                            <i class="fas fa-edit"></i>

                                        </a>

                                        <button
                                            type="button"
                                            class="btn btn-danger btn-sm"
                                            data-toggle="modal"
                                            data-target="#deleteModal"
                                            data-id="<?php echo (int) $product['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name'], ENT_QUOTES); ?>"
                                            title="Delete Product">

                                            <i class="fas fa-trash"></i>

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <div class="modern-pagination-wrapper">

                    <div class="pagination-info">

                        Showing

                        <strong>
                            <?php echo (($current_page - 1) * $products_per_page) + 1; ?>
                        </strong>

                        -

                        <strong>
                            <?php echo min($current_page * $products_per_page, $total_products); ?>
                        </strong>

                        of

                        <strong>
                            <?php echo $total_products; ?>
                        </strong>

                        products

                    </div>

                    <nav aria-label="Products pagination">

                        <ul class="pagination modern-pagination mb-0">

                            <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">

                                <?php if ($current_page > 1): ?>

                                    <a
                                        class="page-link"
                                        href="<?php echo htmlspecialchars(pageUrl($current_page - 1, $query_params)); ?>"
                                        aria-label="Previous">

                                        <i class="fas fa-chevron-left"></i>

                                    </a>

                                <?php else: ?>

                                    <span class="page-link">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>

                                <?php endif; ?>

                            </li>

                            <?php if ($total_pages <= 7): ?>

                                <?php for ($page = 1; $page <= $total_pages; $page++): ?>

                                    <li class="page-item <?php echo $page == $current_page ? 'active' : ''; ?>">

                                        <a
                                            class="page-link"
                                            href="<?php echo htmlspecialchars(pageUrl($page, $query_params)); ?>">

                                            <?php echo $page; ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>

                            <?php else: ?>

                                <?php for ($page = 1; $page <= 3; $page++): ?>

                                    <li class="page-item <?php echo $page == $current_page ? 'active' : ''; ?>">

                                        <a
                                            class="page-link"
                                            href="<?php echo htmlspecialchars(pageUrl($page, $query_params)); ?>">

                                            <?php echo $page; ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>

                                <?php if ($current_page > 4): ?>

                                    <li class="page-item disabled">

                                        <span class="page-link dots">
                                            ...
                                        </span>

                                    </li>

                                <?php endif; ?>

                                <?php if ($current_page > 3 && $current_page < $total_pages - 2): ?>

                                    <li class="page-item active">

                                        <a
                                            class="page-link"
                                            href="<?php echo htmlspecialchars(pageUrl($current_page, $query_params)); ?>">

                                            <?php echo $current_page; ?>

                                        </a>

                                    </li>

                                <?php endif; ?>

                                <?php if ($current_page < $total_pages - 3): ?>

                                    <li class="page-item disabled">

                                        <span class="page-link dots">
                                            ...
                                        </span>

                                    </li>

                                <?php endif; ?>

                                <?php for ($page = $total_pages - 2; $page <= $total_pages; $page++): ?>

                                    <li class="page-item <?php echo $page == $current_page ? 'active' : ''; ?>">

                                        <a
                                            class="page-link"
                                            href="<?php echo htmlspecialchars(pageUrl($page, $query_params)); ?>">

                                            <?php echo $page; ?>

                                        </a>

                                    </li>

                                <?php endfor; ?>

                            <?php endif; ?>

                            <li class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">

                                <?php if ($current_page < $total_pages): ?>

                                    <a
                                        class="page-link"
                                        href="<?php echo htmlspecialchars(pageUrl($current_page + 1, $query_params)); ?>"
                                        aria-label="Next">

                                        <i class="fas fa-chevron-right"></i>

                                    </a>

                                <?php else: ?>

                                    <span class="page-link">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>

                                <?php endif; ?>

                            </li>

                        </ul>

                    </nav>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>

<div
    class="modal fade"
    id="deleteModal"
    tabindex="-1"
    role="dialog"
    aria-labelledby="deleteModalLabel"
    aria-hidden="true">

    <div
        class="modal-dialog modal-dialog-centered"
        role="document">

        <div class="modal-content">

            <div class="modal-header">

                <h5
                    class="modal-title text-danger"
                    id="deleteModalLabel">

                    <i class="fas fa-exclamation-triangle mr-2"></i>

                    Delete Product

                </h5>

                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label="Close">

                    <span aria-hidden="true">
                        &times;
                    </span>

                </button>

            </div>

            <div class="modal-body">

                <p class="mb-2">
                    Are you sure you want to delete:
                </p>

                <strong
                    id="deleteProductName"
                    class="text-gray-800">
                </strong>

                <div class="text-muted small mt-3">

                    <i class="fas fa-info-circle mr-1"></i>

                    This action cannot be undone.

                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal">

                    Cancel

                </button>

                <form
                    method="POST"
                    action="delete_product.php"
                    class="d-inline">

                    <input
                        type="hidden"
                        name="id"
                        id="deleteProductId"
                        value="">

                    <button
                        type="submit"
                        class="btn btn-danger">

                        <i class="fas fa-trash mr-1"></i>

                        Confirm Delete

                    </button>

                </form>

            </div>

        </div>

    </div>

</div>

<?php

require_once __DIR__ . '/includes/footer.php';

?>

<script>
    $(document).ready(function() {

        $('#deleteModal').on('show.bs.modal', function(event) {

            var button = $(event.relatedTarget);

            var productId = button.data('id');

            var productName = button.data('name');

            $('#deleteProductId').val(productId);

            $('#deleteProductName').text(productName);

        });

    });
</script>

<style>
    .modern-pagination-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eeeeee;
    }

    .pagination-info {
        color: #858796;
        font-size: 13px;
        margin-bottom: 15px;
    }

    .pagination-info strong {
        color: #5a5c69;
    }

    .modern-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .modern-pagination .page-item {
        margin: 0;
    }

    .modern-pagination .page-link {
        border: none;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px !important;
        background: #f8f9fc;
        color: #4e73df;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
    }

    .modern-pagination .page-link:hover {
        background: #eaecf4;
        color: #224abe;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
    }

    .modern-pagination .page-item.active .page-link {
        background: #4e73df;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.30);
        transform: translateY(-1px);
    }

    .modern-pagination .page-item.disabled .page-link {
        background: #f1f1f1;
        color: #c5c5c5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .modern-pagination .page-link.dots {
        background: transparent;
        color: #858796;
        box-shadow: none;
        cursor: default;
    }

    .modern-pagination .page-link.dots:hover {
        background: transparent;
        color: #858796;
        transform: none;
        box-shadow: none;
    }

    @media (max-width: 576px) {

        .modern-pagination {
            gap: 2px;
        }

        .modern-pagination .page-link {
            width: 34px;
            height: 34px;
            font-size: 12px;
            border-radius: 8px !important;
        }

        .pagination-info {
            font-size: 12px;
        }

    }
</style>