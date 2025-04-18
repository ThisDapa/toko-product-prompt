<?php
require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($email) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Username must be between 3 and 50 characters";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $error = "Email already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, username, password, role) VALUES (?, ?, ?, 'user')");
                $stmt->execute([$email, $username, $hashed_password]);
                $success = "Registration successful! You can now login.";
                header("Location: /");
                exit();
            }
        } catch(PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account</title>
    <link rel="stylesheet" href="/assets/css/register.css">
</head>
<body>
    <main>
        <div class="container">
            <img src="/assets/img/favicon.ico" alt="Logo" class="logo-img">
            <h1>Create account</h1>
            <p>Join hundreds of thousands of AI enthusiasts</p>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="email" name="email" placeholder="Email address*" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                <input type="text" name="username" placeholder="Username*" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                <input type="password" name="password" placeholder="Password*">
                <input type="password" name="confirm_password" placeholder="Confirm Password*">
                <button type="submit" class="continue">Create Account</button>
            </form>
            <p class="login-link"><a href="/auth/login">I already have an account</a></p>
        </div>
    </main>
</body>
</html>