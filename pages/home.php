<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Website Auto Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/home.css">
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
    <div class="category-bar">
        <div class="category-item">
            <i class="fas fa-bullhorn"></i>
            <span>Digital Marketing</span>
        </div>
        <div class="category-item">
            <i class="fas fa-paint-brush"></i>
            <span>Drawings</span>
        </div>
        <div class="category-item">
            <i class="fas fa-palette"></i>
            <span>Designs</span>
        </div>
        <div class="category-item">
            <i class="fas fa-image"></i>
            <span>Stock Photos</span>
        </div>
        <div class="category-item">
            <i class="fas fa-calendar-alt"></i>
            <span>Events</span>
        </div>
        <div class="category-item">
            <i class="fas fa-tshirt"></i>
            <span>Fashion</span>
        </div>
        <div class="category-item">
            <i class="fas fa-drafting-compass"></i>
            <span>Architecture</span>
        </div>
        <div class="category-item">
            <i class="fas fa-car"></i>
            <span>Automobiles</span>
        </div>
        <div class="category-item">
            <i class="fas fa-hamburger"></i>
            <span>Food</span>
        </div>
        <div class="category-item">
            <i class="fas fa-handshake"></i>
            <span>Rakhi</span>
        </div>
    </div>
    <?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

checkAuth();

try {
    $stmt = $pdo->query("SELECT id, name, price, thumbnail, badge FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal mengambil data produk: " . $e->getMessage());
}
?>
<div class="container">
    <h1 class="title-product">Daftar Product</h1>
    <div class="grid">
        <?php if (!empty($products)) : ?>
            <?php foreach ($products as $product) : ?>
                <a href="/pages/purchase.php?id=<?= $product['id'] ?>">
                <div class="card">
                    <img src="<?= htmlspecialchars($product['thumbnail']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <div class="badge"><?= htmlspecialchars($product['badge']) ?? 'Unknown' ?></div>
                    <div class="content">
                        <div class="info">
                        <h2><?= htmlspecialchars($product['name']) ?></h2>
                        <p>Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                        </div>
                        <button class="add-to-cart-btn">
                            <i class="fas fa-shopping-cart"></i> +
                        </button>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php else : ?>
            <p>Tidak ada produk yang tersedia.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>