<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$success = $_SESSION['coupon_success'] ?? '';
$error = $_SESSION['coupon_error'] ?? '';

unset($_SESSION['coupon_success']);
unset($_SESSION['coupon_error']);

$stmt = $pdo->query("
    SELECT *
    FROM coupons
    ORDER BY id DESC
");

$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/admin_sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <h1 class="h3 mb-0 text-gray-800">
            Coupons
        </h1>

        <a href="add_coupon.php" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Add Coupon
        </a>

    </div>

    <?php if ($success !== ''): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">

            <?php echo htmlspecialchars($success); ?>

            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>

        </div>

    <?php endif; ?>

    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">

            <?php echo htmlspecialchars($error); ?>

            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>

        </div>

    <?php endif; ?>

    <div class="card shadow mb-4">

        <div class="card-header py-3">

            <h6 class="m-0 font-weight-bold text-primary">
                Coupon List
            </h6>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered" width="100%" cellspacing="0">

                    <thead>

                        <tr>

                            <th>#</th>
                            <th>Code</th>
                            <th>Occasion</th>
                            <th>Discount</th>
                            <th>Min Order</th>
                            <th>Usage</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th>Visible to Users</th>
                            <th>Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($coupons)): ?>

                            <tr>

                                <td colspan="11" class="text-center">
                                    No coupons found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($coupons as $coupon): ?>

                                <tr>

                                    <td>
                                        <?php echo (int) $coupon['id']; ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?php echo htmlspecialchars($coupon['code']); ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <?php if (!empty($coupon['occasion'])): ?>

                                            <span class="badge badge-info">
                                                <?php echo htmlspecialchars($coupon['occasion']); ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ($coupon['discount_type'] === 'percentage'): ?>

                                            <?php echo htmlspecialchars($coupon['discount_value']); ?>%

                                        <?php else: ?>

                                            <?php echo number_format((float) $coupon['discount_value'], 2); ?> EGP

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php echo number_format((float) $coupon['min_order_amount'], 2); ?> EGP

                                    </td>

                                    <td>

                                        <?php echo (int) $coupon['used_count']; ?>

                                        /

                                        <?php if ($coupon['max_uses'] === null): ?>

                                            Unlimited

                                        <?php else: ?>

                                            <?php echo (int) $coupon['max_uses']; ?>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php echo htmlspecialchars($coupon['start_date']); ?>

                                    </td>

                                    <td>

                                        <?php echo htmlspecialchars($coupon['end_date']); ?>

                                    </td>

                                    <td>

                                        <?php if ($coupon['status'] === 'active'): ?>

                                            <span class="badge badge-success">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-secondary">
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php if ((int) $coupon['show_to_users'] === 1): ?>

                                            <span class="badge badge-success">

                                                <i class="fas fa-eye"></i>
                                                Yes

                                            </span>

                                        <?php else: ?>

                                            <span class="badge badge-secondary">

                                                <i class="fas fa-eye-slash"></i>
                                                No

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <a
                                            href="edit_coupon.php?id=<?php echo (int) $coupon['id']; ?>"
                                            class="btn btn-sm btn-primary"
                                        >

                                            <i class="fas fa-edit"></i>

                                        </a>

                                        <a
                                            href="delete_coupon.php?id=<?php echo (int) $coupon['id']; ?>"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Are you sure you want to delete this coupon?');"
                                        >

                                            <i class="fas fa-trash"></i>

                                        </a>

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

<?php include __DIR__ . '/includes/footer.php'; ?>