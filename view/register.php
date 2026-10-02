<?php

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notifications.php';

$pageTitle = 'Create Account - Elkumanda';

$name = '';
$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || strlen($name) < 3) {
        $errors[] = 'Name must be at least 3 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare("
            INSERT INTO users
                (name, email, password, role, is_active)
            VALUES
                (?, ?, ?, 'user', 1)
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword
        ]);

        $newUserId = (int)$pdo->lastInsertId();

        createNotification(
            $newUserId,
            'Welcome to Elkumanda',
            'Your account has been created successfully. Welcome to Elkumanda!'
        );

        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = 'user';

        header('Location: index.php');
        exit;
    }
}
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<style>
    body {
        background: #f7f7f7;
    }

    .register-section {
        padding: 80px 0;
    }

    .register-box {
        max-width: 550px;
        margin: 0 auto;
        background: #fff;
        padding: 40px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .register-box h2 {
        text-align: center;
        font-weight: 800;
        margin-bottom: 10px;
    }

    .register-subtitle {
        text-align: center;
        color: #777;
        margin-bottom: 30px;
    }

    .register-box label {
        font-weight: 700;
        margin-bottom: 8px;
    }

    .register-box input {
        width: 100%;
        height: 50px;
        border: 1px solid #ddd;
        padding: 0 15px;
        margin-bottom: 20px;
    }

    .register-btn {
        width: 100%;
        height: 50px;
        background: #7fad39;
        color: #fff;
        border: none;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
    }

    .register-btn:hover {
        background: #5f8d22;
    }

    .error-box {
        background: #f8d7da;
        color: #842029;
        padding: 15px;
        margin-bottom: 20px;
    }

    .login-link {
        text-align: center;
        margin-top: 25px;
    }

    .login-link a {
        color: #7fad39;
        font-weight: 700;
    }
</style>

<section class="register-section">

    <div class="container">

        <div class="register-box">

            <h2>
                Create Account
            </h2>

            <p class="register-subtitle">
                Create your Elkumanda account
            </p>

            <?php if (!empty($errors)): ?>

                <div class="error-box">

                    <?php foreach ($errors as $error): ?>

                        <div>
                            <?php echo htmlspecialchars($error); ?>
                        </div>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

            <form method="POST">

                <label>
                    Name
                </label>

                <input
                    type="text"
                    name="name"
                    value="<?php echo htmlspecialchars($name); ?>"
                    required>

                <label>
                    Email
                </label>

                <input
                    type="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required>

                <label>
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required>

                <label>
                    Confirm Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    required>

                <button
                    type="submit"
                    class="register-btn">

                    Create Account

                </button>

            </form>

            <div class="login-link">

                Already have an account?

                <a href="login.php">
                    Sign In
                </a>

            </div>

        </div>

    </div>

</section>

<?php include __DIR__ . '/includes/footer.php'; ?>