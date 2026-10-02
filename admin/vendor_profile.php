<?php

require_once __DIR__ . '/includes/auth.php';
requireRole('vendor');

require_once __DIR__ . '/../config/db.php';

$user_id = (int) $_SESSION['user_id'];

$error = '';
$success = '';

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE id = ?
    AND role = 'vendor'
    LIMIT 1
");

$stmt->execute([$user_id]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {

            $error = 'Please enter your name.';
        } elseif (strlen($name) < 2 || strlen($name) > 100) {

            $error = 'Name must be between 2 and 100 characters.';
        } elseif ($email === '') {

            $error = 'Please enter your email.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $error = 'Please enter a valid email address.';
        } else {

            $check = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                AND id != ?
                LIMIT 1
            ");

            $check->execute([
                $email,
                $user_id
            ]);

            if ($check->fetch()) {

                $error = 'This email is already in use.';
            } else {

                $update = $pdo->prepare("
                    UPDATE users
                    SET
                        name = ?,
                        email = ?
                    WHERE id = ?
                    AND role = 'vendor'
                ");

                $update->execute([
                    $name,
                    $email,
                    $user_id
                ]);

                $_SESSION['user_name'] = $name;

                $user['name'] = $name;
                $user['email'] = $email;

                $success = 'Profile updated successfully.';
            }
        }
    }


    if ($action === 'change_password') {

        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '') {

            $error = 'Please enter your current password.';
        } elseif ($new_password === '') {

            $error = 'Please enter your new password.';
        } elseif (strlen($new_password) < 6) {

            $error = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {

            $error = 'New password and confirmation password do not match.';
        } else {

            $password_stmt = $pdo->prepare("
                SELECT password
                FROM users
                WHERE id = ?
                AND role = 'vendor'
                LIMIT 1
            ");

            $password_stmt->execute([$user_id]);

            $password_data = $password_stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$password_data ||
                !password_verify($current_password, $password_data['password'])
            ) {

                $error = 'Current password is incorrect.';
            } else {

                $new_password_hash = password_hash(
                    $new_password,
                    PASSWORD_DEFAULT
                );

                $update_password = $pdo->prepare("
                    UPDATE users
                    SET password = ?
                    WHERE id = ?
                    AND role = 'vendor'
                ");

                $update_password->execute([
                    $new_password_hash,
                    $user_id
                ]);

                $success = 'Password changed successfully.';
            }
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/vendor_sidebar.php';
include __DIR__ . '/includes/topbar.php';
?>

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">

        <h1 class="h3 mb-0 text-gray-800">
            My Profile
        </h1>

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


    <div class="row">


        <!-- Profile Information -->

        <div class="col-lg-6 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Profile Information
                    </h6>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="update_profile">

                        <div class="form-group">

                            <label for="name">
                                Name
                            </label>

                            <input
                                type="text"
                                name="name"
                                id="name"
                                class="form-control"
                                value="<?php echo htmlspecialchars($user['name']); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="email">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                id="email"
                                class="form-control"
                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                required>

                        </div>


                        <div class="form-group">

                            <label>
                                Role
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="Vendor"
                                readonly>

                        </div>


                        <div class="form-group">

                            <label>
                                Account Created
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                value="<?php echo htmlspecialchars($user['created_at']); ?>"
                                readonly>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-save"></i>
                            Save Changes

                        </button>

                    </form>

                </div>

            </div>

        </div>


        <!-- Change Password -->

        <div class="col-lg-6 mb-4">

            <div class="card shadow h-100">

                <div class="card-header py-3">

                    <h6 class="m-0 font-weight-bold text-primary">
                        Change Password
                    </h6>

                </div>

                <div class="card-body">

                    <form method="POST">

                        <input
                            type="hidden"
                            name="action"
                            value="change_password">


                        <div class="form-group">

                            <label for="current_password">
                                Current Password
                            </label>

                            <input
                                type="password"
                                name="current_password"
                                id="current_password"
                                class="form-control"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="new_password">
                                New Password
                            </label>

                            <input
                                type="password"
                                name="new_password"
                                id="new_password"
                                class="form-control"
                                minlength="6"
                                required>

                        </div>


                        <div class="form-group">

                            <label for="confirm_password">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                name="confirm_password"
                                id="confirm_password"
                                class="form-control"
                                minlength="6"
                                required>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-primary">

                            <i class="fas fa-key"></i>
                            Change Password

                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>