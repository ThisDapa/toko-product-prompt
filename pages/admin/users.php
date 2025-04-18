<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

checkAdmin();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['role'])) {
    $userId = filter_var($_POST['user_id'], FILTER_SANITIZE_NUMBER_INT);
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';

    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$role, $userId]);
        $message = 'User role updated successfully';
    } catch(PDOException $e) {
        $message = 'Failed to update user role';
    }
}

try {
    $stmt = $pdo->query("SELECT id, email, role FROM users ORDER BY id");
    $users = $stmt->fetchAll();
} catch(PDOException $e) {
    $users = [];
    $message = 'Failed to fetch users';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
    <div class="container">
        <h1>User Management</h1>
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <table class="users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <select name="role">
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                                <button type="submit">Update Role</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><a href="/pages/home">Back to Home</a></p>
    </div>
</body>
</html>