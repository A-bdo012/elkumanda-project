<?php

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Login - Elkumanda';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($email === '' || $password === '') {

        $error = 'Please enter your email and password.';
    } else {

        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                email,
                password,
                role,
                is_active,
                disabled_until
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {

            $error = 'Invalid email or password.';
        } elseif ((int) $user['is_active'] !== 1) {

            $error = 'Your account is disabled.';
        } elseif (
            !empty($user['disabled_until']) &&
            strtotime($user['disabled_until']) > time()
        ) {

            $error = 'Your account is temporarily disabled.';
        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            if ($remember) {

                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date(
                    'Y-m-d H:i:s',
                    time() + (30 * 24 * 60 * 60)
                );

                $stmt = $pdo->prepare("
                    DELETE FROM remember_tokens
                    WHERE user_id = ?
                ");

                $stmt->execute([
                    (int) $user['id']
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO remember_tokens
                        (user_id, token_hash, expires_at)
                    VALUES
                        (?, ?, ?)
                ");

                $stmt->execute([
                    (int) $user['id'],
                    $token_hash,
                    $expires_at
                ]);

                setcookie(
                    'remember_token',
                    $token,
                    [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            } else {

                setcookie(
                    'remember_token',
                    '',
                    [
                        'expires' => time() - 3600,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );
            }

            if (!empty($_COOKIE['guest_cart'])) {

                $guestCart = json_decode(
                    $_COOKIE['guest_cart'],
                    true
                );

                if (is_array($guestCart)) {

                    $stmt = $pdo->prepare("
                        SELECT id
                        FROM cart
                        WHERE user_id = ?
                        LIMIT 1
                    ");

                    $stmt->execute([
                        (int) $user['id']
                    ]);

                    $cart = $stmt->fetch();

                    if ($cart) {

                        $cartId = (int) $cart['id'];
                    } else {

                        $stmt = $pdo->prepare("
                            INSERT INTO cart (user_id)
                            VALUES (?)
                        ");

                        $stmt->execute([
                            (int) $user['id']
                        ]);

                        $cartId = (int) $pdo->lastInsertId();
                    }

                    foreach ($guestCart as $productId => $quantity) {

                        $productId = (int) $productId;
                        $quantity = (int) $quantity;

                        if ($productId > 0 && $quantity > 0) {

                            $stmt = $pdo->prepare("
                                SELECT stock_quantity
                                FROM products
                                WHERE id = ?
                                AND status = 'active'
                                LIMIT 1
                            ");

                            $stmt->execute([
                                $productId
                            ]);

                            $product = $stmt->fetch();

                            if ($product) {

                                $quantity = min(
                                    $quantity,
                                    (int) $product['stock_quantity']
                                );

                                if ($quantity > 0) {

                                    $stmt = $pdo->prepare("
                                        SELECT id, quantity
                                        FROM cart_items
                                        WHERE cart_id = ?
                                        AND product_id = ?
                                        LIMIT 1
                                    ");

                                    $stmt->execute([
                                        $cartId,
                                        $productId
                                    ]);

                                    $item = $stmt->fetch();

                                    if ($item) {

                                        $newQuantity = min(
                                            (int) $item['quantity'] + $quantity,
                                            (int) $product['stock_quantity']
                                        );

                                        $stmt = $pdo->prepare("
                                            UPDATE cart_items
                                            SET quantity = ?
                                            WHERE id = ?
                                        ");

                                        $stmt->execute([
                                            $newQuantity,
                                            (int) $item['id']
                                        ]);
                                    } else {

                                        $stmt = $pdo->prepare("
                                            INSERT INTO cart_items
                                                (cart_id, product_id, quantity)
                                            VALUES
                                                (?, ?, ?)
                                        ");

                                        $stmt->execute([
                                            $cartId,
                                            $productId,
                                            $quantity
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }

                setcookie(
                    'guest_cart',
                    '',
                    [
                        'expires' => time() - 3600,
                        'path' => '/'
                    ]
                );
            }

            header('Location: index.php');
            exit;
        }
    }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
    .login-section {
        padding: 80px 0;
        background: #f7f7f7;
    }

    .login-box {
        max-width: 500px;
        margin: 0 auto;
        background: #fff;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .login-box h2 {
        text-align: center;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .login-subtitle {
        text-align: center;
        color: #777;
        margin-bottom: 30px;
    }

    .login-box label {
        font-weight: 700;
        margin-bottom: 8px;
    }

    .login-box input[type="email"],
    .login-box input[type="password"] {
        width: 100%;
        height: 50px;
        border: 1px solid #ddd;
        padding: 0 15px;
        margin-bottom: 20px;
    }

    .remember-box {
        margin-bottom: 20px;
    }

    .remember-box label {
        font-weight: 400;
        margin-left: 5px;
        cursor: pointer;
    }

    .login-btn {
        width: 100%;
        height: 50px;
        background: #7fad39;
        color: #fff;
        border: none;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
    }

    .login-btn:hover {
        background: #6d9630;
    }

    .error-box {
        background: #f8d7da;
        color: #842029;
        padding: 15px;
        margin-bottom: 20px;
    }

    .register-link {
        text-align: center;
        margin-top: 25px;
    }

    .register-link a {
        color: #7fad39;
        font-weight: 700;
    }
</style>

<section class="breadcrumb-section set-bg" style="background-image: url('img/breadcrumb.jpg');">

    <div class="container">

        <div class="row">

            <div class="col-lg-12 text-center">

                <div class="breadcrumb__text">

                    <h2>
                        Login
                    </h2>

                    <div class="breadcrumb__option">

                        <a href="index.php">
                            Home
                        </a>

                        <span>
                            Login
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>

<section class="login-section">

    <div class="container">

        <div class="login-box">

            <h2>
                Welcome Back
            </h2>

            <p class="login-subtitle">
                Login to your Elkumanda account
            </p>

            <?php if ($error): ?>

                <div class="error-box">

                    <?php echo htmlspecialchars($error); ?>

                </div>

            <?php endif; ?>

            <form method="POST">

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required>

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required>

                <div class="remember-box">

                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                        id="rememberMe">

                    <label for="rememberMe">
                        Remember Me
                    </label>

                </div>

                <button
                    type="submit"
                    class="login-btn">

                    Login

                </button>

            </form>

            <div class="register-link">

                Don't have an account?

                <a href="register.php">
                    Create Account
                </a>

            </div>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>