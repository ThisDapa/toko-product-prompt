<?php
require_once __DIR__ . '/../config/database.php';

session_start();

$error = '';

if (isset($_SESSION['user_id'])) {
    header('Location: /pages/home');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "All fields are required";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: /');
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } catch(PDOException $e) {
            $error = "Login failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
    <div class="container">
        <div class="login-box">
            <img src="/assets/img/favicon.ico" alt="Logo" class="logo-img">
            <h2>Welcome back!</h2>
            <p>Sign in to access your account</p>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email address*" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <input type="password" name="password" placeholder="Password*">
                <button type="submit" class="login-btn">Login</button>
            </form>
            <div class="links">
                <a href="#">Forgot password?</a>
                <a href="/auth/register">Create a new account</a>
            </div>
        </div>
    </div>
</body>
</html>