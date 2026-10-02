<?php

if (session_status() === PHP_SESSION_NONE) {

    session_name('ELKUMANDA_ADMIN_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/elkumanda/admin',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $sql = "SELECT * FROM users WHERE email = ?";
    $statement = $pdo->prepare($sql);
    $statement->execute([$email]);

    $user = $statement->fetch();

    if ($user) {

        if (!empty($user['disabled_until']) && strtotime($user['disabled_until']) <= time()) {

            $update = $pdo->prepare("
                UPDATE users
                SET is_active = 1, disabled_until = NULL
                WHERE id = ?
            ");

            $update->execute([$user['id']]);

            $user['is_active'] = 1;
            $user['disabled_until'] = null;
        }

        if ((int)$user['is_active'] === 0) {

            $error = 'Your account has been disabled. Please try again later.';
        } elseif ($password === $user['password']) {

            if ($user['role'] !== 'admin' && $user['role'] !== 'vendor') {

                $error = 'You are not allowed to access the dashboard.';
            } else {

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                if ($remember) {

                    $token = bin2hex(random_bytes(32));
                    $token_hash = hash('sha256', $token);

                    $expires_at = date(
                        'Y-m-d H:i:s',
                        time() + (30 * 24 * 60 * 60)
                    );

                    $deleteOld = $pdo->prepare("
                        DELETE FROM remember_tokens
                        WHERE user_id = ?
                    ");

                    $deleteOld->execute([$user['id']]);

                    $insertToken = $pdo->prepare("
                        INSERT INTO remember_tokens
                        (user_id, token_hash, expires_at)
                        VALUES (?, ?, ?)
                    ");

                    $insertToken->execute([
                        $user['id'],
                        $token_hash,
                        $expires_at
                    ]);

                    setcookie(
                        'admin_remember_token',
                        $token,
                        [
                            'expires' => time() + (30 * 24 * 60 * 60),
                            'path' => '/elkumanda/admin',
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );
                } else {

                    setcookie(
                        'admin_remember_token',
                        '',
                        [
                            'expires' => time() - 3600,
                            'path' => '/elkumanda/admin',
                            'httponly' => true,
                            'samesite' => 'Lax'
                        ]
                    );
                }

                if ($user['role'] === 'admin') {

                    header('Location: dashboard.php');
                } elseif ($user['role'] === 'vendor') {

                    header('Location: vendor.php');
                } else {

                    header('Location: ../index.php');
                }

                exit;
            }
        } else {

            $error = 'Invalid email or password.';
        }
    } else {

        $error = 'Invalid email or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>Elkumanda - Login</title>

    <link
        href="vendor/fontawesome-free/css/all.min.css"
        rel="stylesheet"
        type="text/css">

    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <link href="css/sb-admin-2.min.css" rel="stylesheet">

</head>

<body class="bg-gradient-primary">

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-xl-10 col-lg-12 col-md-9">

                <div class="card o-hidden border-0 shadow-lg my-5">

                    <div class="card-body p-0">

                        <div class="row">

                            <div class="col-lg-6 d-none d-lg-block bg-login-image"></div>

                            <div class="col-lg-6">

                                <div class="p-5">

                                    <div class="text-center">

                                        <h1 class="h4 text-gray-900 mb-4">
                                            Welcome Back!
                                        </h1>

                                    </div>

                                    <?php if ($error): ?>

                                        <div class="alert alert-danger">

                                            <?= htmlspecialchars($error) ?>

                                        </div>

                                    <?php endif; ?>

                                    <form class="user" method="POST">

                                        <div class="form-group">

                                            <input
                                                type="email"
                                                name="email"
                                                required
                                                class="form-control form-control-user"
                                                placeholder="Enter Email Address...">

                                        </div>

                                        <div class="form-group">

                                            <input
                                                type="password"
                                                name="password"
                                                required
                                                class="form-control form-control-user"
                                                placeholder="Password">

                                        </div>

                                        <div class="form-group">

                                            <div class="custom-control custom-checkbox small">

                                                <input
                                                    type="checkbox"
                                                    name="remember"
                                                    value="1"
                                                    class="custom-control-input"
                                                    id="customCheck">

                                                <label
                                                    class="custom-control-label"
                                                    for="customCheck">
                                                    Remember Me
                                                </label>

                                            </div>

                                        </div>

                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-user btn-block">
                                            Login
                                        </button>

                                        <hr>

                                    </form>

                                    <div class="text-center">

                                        <a
                                            class="small"
                                            href="forgot-password.html">
                                            Forgot Password?
                                        </a>

                                    </div>

                                    <div class="text-center">

                                        <a
                                            class="small"
                                            href="register.html">
                                            Create an Account!
                                        </a>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script src="vendor/jquery/jquery.min.js"></script>

    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="js/sb-admin-2.min.js"></script>

</body>

</html>