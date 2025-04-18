<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

checkAuth();

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT t.transaksi_id, t.created_at, p.name as product_name, p.thumbnail, p.price, p.prompt_file FROM transaksi t JOIN products p ON t.kode_produk = p.id WHERE t.user_id = :user_id AND t.status = true ORDER BY t.created_at DESC");
    $stmt->execute(['user_id' => $user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal mengambil data transaksi: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History Pembelian</title>
    <link rel="stylesheet" href="/assets/css/purchase.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/history.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">
                <img src="/assets/img/favicon.ico" alt="Logo" class="logo-img">
                <span>TokoSaya</span>
            </div>
            <button class="navbar-menu-btn" onclick="toggleMenu()">
                ☰
            </button>
            <div class="navbar-links">
                <a href="/pages/home.php">Home</a>
                <a href="/pages/history.php">History</a>
                <a href="/pages/purchase.php">Purchase</a>
                <a href="/pages/auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <script>
        function toggleMenu() {
            const navLinks = document.querySelector('.navbar-links');
            navLinks.classList.toggle('active');
        }
    </script>
    <div class="history-container">
        <div class="history-title">History Pembelian</div>
        <?php if (empty($transactions)) : ?>
            <div class="no-history">Belum ada transaksi pembelian.</div>
        <?php else : ?>
            <div style="overflow-x:auto;">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Thumbnail</th>
                        <th>Nama Produk</th>
                        <th>Tanggal</th>
                        <th>Harga</th>
                        <th>Prompt</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $trx) : ?>
                    <tr>
                        <td>
                            <?php 
                            $thumbPath = '/uploads/products/' . htmlspecialchars($trx['thumbnail']);
                            $thumbFile = __DIR__ . '/../uploads/products/' . $trx['thumbnail'];
                            if (!empty($trx['thumbnail']) && file_exists($thumbFile) && is_file($thumbFile)) {
                                echo '<img class="product-thumb" src="' . $thumbPath . '" alt="Thumbnail">';
                            } else {
                                echo '<div style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;background:#eee;color:#888;border-radius:8px;font-size:0.9rem;">No Image</div>';
                            }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($trx['product_name']); ?></td>
                        <td><?php echo date('d M Y H:i', strtotime($trx['created_at'])); ?></td>
                        <td>Rp<?php echo number_format($trx['price'],0,',','.'); ?></td>
                        <td>
                            <?php if (!empty($trx['prompt_file'])) : ?>
                                <a class="download-btn" href="/pages/download_prompt.php?id=<?php echo urlencode($trx['transaksi_id']); ?>" target="_blank">Download Ulang</a>
                            <?php else: ?>
                                <span style="color:#aaa;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>