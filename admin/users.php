<?php
require_once __DIR__ . '/includes/auth.php';
requireRole('admin');

require_once __DIR__ . '/../config/db.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

    if ($user_id > 0) {

        if ($action === 'make_vendor') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET role = 'vendor'
                WHERE id = ?
                AND role = 'user'
            ");

            $stmt->execute([$user_id]);

            if ($stmt->rowCount() > 0) {
                $message = 'User has been promoted to Vendor successfully.';
            } else {
                $message = 'The user could not be promoted.';
                $message_type = 'danger';
            }
        } elseif ($action === 'disable') {

            $duration = $_POST['duration'] ?? '';

            if ($duration === 'week') {
                $disabled_until = date('Y-m-d H:i:s', strtotime('+1 week'));
            } elseif ($duration === 'month') {
                $disabled_until = date('Y-m-d H:i:s', strtotime('+1 month'));
            } else {
                $disabled_until = null;
            }

            if ($disabled_until !== null) {

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET is_active = 0,
                        disabled_until = ?
                    WHERE id = ?
                    AND role != 'admin'
                ");

                $stmt->execute([$disabled_until, $user_id]);

                if ($stmt->rowCount() > 0) {
                    $message = 'Account disabled successfully.';
                } else {
                    $message = 'The account could not be disabled.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Please select a valid duration.';
                $message_type = 'danger';
            }
        } elseif ($action === 'enable') {

            $stmt = $pdo->prepare("
                UPDATE users
                SET is_active = 1,
                    disabled_until = NULL
                WHERE id = ?
                AND role != 'admin'
            ");

            $stmt->execute([$user_id]);

            if ($stmt->rowCount() > 0) {
                $message = 'Account enabled successfully.';
            } else {
                $message = 'The account could not be enabled.';
                $message_type = 'danger';
            }
        }
    }
}

$pdo->query("
    UPDATE users
    SET is_active = 1,
        disabled_until = NULL
    WHERE is_active = 0
    AND disabled_until IS NOT NULL
    AND disabled_until <= NOW()
");

$stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        is_active,
        disabled_until,
        created_at
    FROM users
    ORDER BY id DESC
");

$users = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<body id="page-top">

    <?php require_once __DIR__ . '/includes/admin_sidebar.php'; ?>

    <?php require_once __DIR__ . '/includes/topbar.php'; ?>

    <div class="container-fluid">

        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">
                User Management
            </h1>
        </div>

        <?php if ($message !== ''): ?>

            <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show">

                <?= htmlspecialchars($message) ?>

                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>

            </div>

        <?php endif; ?>

        <div class="card shadow mb-4">

            <div class="card-header py-3">

                <h6 class="m-0 font-weight-bold text-primary">
                    Users
                </h6>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered table-hover">

                        <thead>

                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Disabled Until</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if (empty($users)): ?>

                                <tr>

                                    <td colspan="7" class="text-center">
                                        No users found.
                                    </td>

                                </tr>

                            <?php else: ?>

                                <?php foreach ($users as $index => $user): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($user['name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($user['email']) ?>
                                        </td>

                                        <td>

                                            <?php if ($user['role'] === 'admin'): ?>

                                                <span class="badge badge-primary">
                                                    Admin
                                                </span>

                                            <?php elseif ($user['role'] === 'vendor'): ?>

                                                <span class="badge badge-info">
                                                    Vendor
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-secondary">
                                                    User
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ((int)$user['is_active'] === 1): ?>

                                                <span class="badge badge-success">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="badge badge-danger">
                                                    Disabled
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if (!empty($user['disabled_until'])): ?>

                                                <?= htmlspecialchars($user['disabled_until']) ?>

                                            <?php else: ?>

                                                <span class="text-muted">-</span>

                                            <?php endif; ?>

                                        </td>

                                        <td>

                                            <?php if ($user['role'] === 'user'): ?>

                                                <form method="POST" class="d-inline">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="make_vendor">

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= $user['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-info btn-sm"
                                                        onclick="return confirm('Promote this user to Vendor?')">
                                                        Make Vendor
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <?php if ($user['role'] !== 'admin' && (int)$user['is_active'] === 1): ?>

                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm"
                                                    data-toggle="modal"
                                                    data-target="#disableModal<?= $user['id'] ?>">
                                                    Disable
                                                </button>

                                            <?php elseif ($user['role'] !== 'admin' && (int)$user['is_active'] === 0): ?>

                                                <form method="POST" class="d-inline">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="enable">

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= $user['id'] ?>">

                                                    <button
                                                        type="submit"
                                                        class="btn btn-success btn-sm">
                                                        Enable
                                                    </button>

                                                </form>

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

    <?php foreach ($users as $user): ?>

        <?php if ($user['role'] !== 'admin' && (int)$user['is_active'] === 1): ?>

            <div
                class="modal fade"
                id="disableModal<?= $user['id'] ?>"
                tabindex="-1"
                role="dialog">

                <div class="modal-dialog" role="document">

                    <div class="modal-content">

                        <div class="modal-header">

                            <h5 class="modal-title">
                                Disable Account
                            </h5>

                            <button
                                type="button"
                                class="close"
                                data-dismiss="modal">

                                <span>&times;</span>

                            </button>

                        </div>

                        <form method="POST">

                            <div class="modal-body">

                                <p>
                                    Disable account for:
                                    <strong>
                                        <?= htmlspecialchars($user['name']) ?>
                                    </strong>
                                </p>

                                <input
                                    type="hidden"
                                    name="action"
                                    value="disable">

                                <input
                                    type="hidden"
                                    name="user_id"
                                    value="<?= $user['id'] ?>">

                                <div class="form-group">

                                    <label>Duration</label>

                                    <select
                                        name="duration"
                                        class="form-control"
                                        required>

                                        <option value="">
                                            Select Duration
                                        </option>

                                        <option value="week">
                                            One Week
                                        </option>

                                        <option value="month">
                                            One Month
                                        </option>

                                    </select>

                                </div>

                            </div>

                            <div class="modal-footer">

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    data-dismiss="modal">
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    class="btn btn-danger">
                                    Disable Account
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>

</html>