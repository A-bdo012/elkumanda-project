<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$total_users = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'user'
")->fetchColumn();

$total_products = (int) $pdo->query("
    SELECT COUNT(*)
    FROM products
")->fetchColumn();

$total_orders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
")->fetchColumn();

$pending_orders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE status = 'pending'
")->fetchColumn();

$completed_orders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE status = 'completed'
")->fetchColumn();

$shipped_orders = (int) $pdo->query("
    SELECT COUNT(*)
    FROM orders
    WHERE status = 'shipped'
")->fetchColumn();

$low_stock_stmt = $pdo->query("
    SELECT
        id,
        product_name,
        stock_quantity,
        low_stock_alert,
        status
    FROM products
    WHERE stock_quantity <= low_stock_alert
    ORDER BY stock_quantity ASC, id DESC
");

$low_stock_products = $low_stock_stmt->fetchAll(PDO::FETCH_ASSOC);

$latest_orders_stmt = $pdo->query("
    SELECT
        o.id,
        o.user_id,
        o.phone,
        o.total,
        o.status,
        o.created_at,
        u.name AS user_name
    FROM orders o
    LEFT JOIN users u
        ON u.id = o.user_id
    ORDER BY o.id DESC
    LIMIT 5
");

$latest_orders = $latest_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

$latest_users_stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        created_at
    FROM users
    WHERE role = 'user'
    ORDER BY id DESC
    LIMIT 5
");

$latest_users = $latest_users_stmt->fetchAll(PDO::FETCH_ASSOC);

$top_products_stmt = $pdo->query("
    SELECT
        p.id,
        p.product_name,
        COUNT(oi.id) AS total_sold
    FROM order_item oi
    INNER JOIN products p
        ON p.id = oi.product_id
    GROUP BY p.id, p.product_name
    ORDER BY total_sold DESC, p.id DESC
    LIMIT 5
");

$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/admin_sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <h1 class="h3 mb-0 text-gray-800">
            Dashboard
        </h1>

    </div>


    <!-- Row 1 -->

    <div class="row">

        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Users
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_users; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Products
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_products; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-box fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-info shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Orders
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_orders; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Row 2 -->

    <div class="row">

        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-warning shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Orders
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $pending_orders; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-success shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Completed Orders
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $completed_orders; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="col-xl-4 col-md-6 mb-4">

            <div class="card border-left-primary shadow h-100 py-2">

                <div class="card-body">

                    <div class="row no-gutters align-items-center">

                        <div class="col mr-2">

                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Shipped Orders
                            </div>

                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $shipped_orders; ?>
                            </div>

                        </div>

                        <div class="col-auto">
                            <i class="fas fa-truck fa-2x text-gray-300"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- Row 3 - Low Stock -->

    <div class="row">

        <div class="col-12 mb-4">

            <div class="card shadow">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-warning">
                        Products Nearing Low Stock
                    </h6>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered" width="100%" cellspacing="0">

                            <thead>

                                <tr>

                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Stock Quantity</th>
                                    <th>Low Stock Alert</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($low_stock_products)): ?>

                                    <tr>

                                        <td colspan="5" class="text-center text-success">

                                            <i class="fas fa-check-circle"></i>
                                            All products have sufficient stock.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($low_stock_products as $product): ?>

                                        <tr>

                                            <td>
                                                <?php echo (int) $product['id']; ?>
                                            </td>

                                            <td>

                                                <strong>
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </strong>

                                            </td>

                                            <td>

                                                <span class="badge badge-danger">

                                                    <?php echo (int) $product['stock_quantity']; ?>

                                                </span>

                                            </td>

                                            <td>
                                                <?php echo (int) $product['low_stock_alert']; ?>
                                            </td>

                                            <td>

                                                <?php if ($product['status'] === 'active'): ?>

                                                    <span class="badge badge-success">
                                                        Active
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        Inactive
                                                    </span>

                                                <?php endif; ?>

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

    </div>


    <!-- Row 4 -->

    <div class="row">


        <!-- Latest Orders -->

        <div class="col-xl-4 col-lg-6 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Latest 5 Orders
                    </h6>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-sm">

                            <thead>

                                <tr>

                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($latest_orders)): ?>

                                    <tr>

                                        <td colspan="4" class="text-center">
                                            No orders found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($latest_orders as $order): ?>

                                        <tr>

                                            <td>
                                                <?php echo (int) $order['id']; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($order['user_name'] ?? 'Guest'); ?>
                                            </td>

                                            <td>
                                                <?php echo number_format((float) $order['total'], 2); ?>
                                                EGP
                                            </td>

                                            <td>

                                                <?php if ($order['status'] === 'pending'): ?>

                                                    <span class="badge badge-warning">
                                                        Pending
                                                    </span>

                                                <?php elseif ($order['status'] === 'completed'): ?>

                                                    <span class="badge badge-success">
                                                        Completed
                                                    </span>

                                                <?php elseif ($order['status'] === 'shipped'): ?>

                                                    <span class="badge badge-primary">
                                                        Shipped
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                                    </span>

                                                <?php endif; ?>

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


        <!-- Latest Users -->

        <div class="col-xl-4 col-lg-6 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-success">
                        Latest 5 Users
                    </h6>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-sm">

                            <thead>

                                <tr>

                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($latest_users)): ?>

                                    <tr>

                                        <td colspan="3" class="text-center">
                                            No users found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($latest_users as $user): ?>

                                        <tr>

                                            <td>
                                                <?php echo (int) $user['id']; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($user['email']); ?>
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


        <!-- Top Products -->

        <div class="col-xl-4 col-lg-12 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-info">
                        Top 5 Most Purchased Products
                    </h6>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered table-sm">

                            <thead>

                                <tr>

                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Purchased</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php if (empty($top_products)): ?>

                                    <tr>

                                        <td colspan="3" class="text-center">
                                            No purchases found.
                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($top_products as $index => $product): ?>

                                        <tr>

                                            <td>
                                                <?php echo $index + 1; ?>
                                            </td>

                                            <td>
                                                <?php echo htmlspecialchars($product['product_name']); ?>
                                            </td>

                                            <td>

                                                <span class="badge badge-info">

                                                    <?php echo (int) $product['total_sold']; ?>

                                                </span>

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

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>