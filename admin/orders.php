<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$stmt = $pdo->query("
    SELECT 
        o.id,
        o.phone,
        o.address,
        o.total,
        o.status,
        o.created_at,
        u.name AS customer_name,
        u.email AS customer_email
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
");

$orders = $stmt->fetchAll();

?>

<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>
<?php require_once __DIR__ . '/includes/topbar.php'; ?>

<div class="container-fluid">

    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Orders
            </h1>

            <p class="mb-0 text-gray-600">
                Manage and view all customer orders
            </p>
        </div>

    </div>

    <!-- Orders Card -->
    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                All Orders
            </h6>

        </div>

        <div class="card-body">

            <?php if (isset($_GET['updated'])): ?>

                <div class="alert alert-success alert-dismissible fade show">

                    <i class="fas fa-check-circle mr-2"></i>

                    Order status updated successfully.

                    <button type="button"
                        class="close"
                        data-dismiss="alert"
                        aria-label="Close">

                        <span aria-hidden="true">&times;</span>

                    </button>

                </div>

            <?php endif; ?>


            <div class="table-responsive">

                <table class="table table-bordered"
                    width="100%"
                    cellspacing="0">

                    <thead>

                        <tr>

                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (!empty($orders)): ?>

                            <?php foreach ($orders as $order): ?>

                                <?php

                                $status = strtolower(trim($order['status']));

                                switch ($status) {

                                    case 'pending':
                                        $badge = 'warning';
                                        break;

                                    case 'processing':
                                        $badge = 'info';
                                        break;

                                    case 'completed':
                                        $badge = 'success';
                                        break;

                                    case 'cancelled':
                                        $badge = 'danger';
                                        break;

                                    default:
                                        $badge = 'secondary';
                                        break;
                                }

                                ?>

                                <tr>

                                    <!-- Order ID -->
                                    <td>
                                        <strong>
                                            #<?= (int) $order['id'] ?>
                                        </strong>
                                    </td>


                                    <!-- Customer -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $order['customer_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>


                                    <!-- Email -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $order['customer_email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>


                                    <!-- Phone -->
                                    <td>
                                        <?= htmlspecialchars(
                                            $order['phone'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>


                                    <!-- Total -->
                                    <td>

                                        <strong>
                                            EGP
                                            <?= number_format(
                                                (float) $order['total'],
                                                2
                                            ) ?>
                                        </strong>

                                    </td>


                                    <!-- Status -->
                                    <td>

                                        <span class="badge badge-<?= $badge ?>">

                                            <?= htmlspecialchars(
                                                ucfirst($order['status']),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- Date -->
                                    <td>

                                        <?php

                                        $orderDate = strtotime(
                                            $order['created_at']
                                        );

                                        echo $orderDate
                                            ? date(
                                                'd M Y h:i A',
                                                $orderDate
                                            )
                                            : '-';

                                        ?>

                                    </td>


                                    <!-- Action -->
                                    <td>

                                        <a
                                            href="order-details.php?id=<?= (int) $order['id'] ?>"
                                            class="btn btn-sm btn-primary">

                                            <i class="fas fa-eye mr-1"></i>
                                            View

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8"
                                    class="text-center py-4">

                                    <i class="fas fa-shopping-bag fa-2x text-gray-300 mb-3"></i>

                                    <p class="mb-0 text-gray-500">
                                        No orders found.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>