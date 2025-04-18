<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

checkAuth();

if (!isset($_GET['id'])) {
    header('Location: /');
    exit();
}

$product_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT t.*, t.transaksi_id, p.name as product_name, p.description as product_description, p.thumbnail, p.price as total_harga 
                          FROM transaksi t 
                          LEFT JOIN products p ON t.kode_produk = p.id 
                          WHERE t.user_id = :user_id AND t.status = false 
                          ORDER BY t.created_at DESC LIMIT 1");
    $stmt->execute([
        'user_id' => $user_id
    ]);

    $pending_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal mengecek transaksi: " . $e->getMessage());
}

function generateTransaksiID($lastId = 1)
{
    $prefix = 'TRX';
    $date = date('Ymd');
    $urutan = str_pad($lastId, 4, '0', STR_PAD_LEFT);
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 3));

    return "{$prefix}-{$date}{$urutan}{$random}";
}

$transaksi_id = generateTransaksiID();

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute(['id' => $product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        header('Location: /');
        exit();
    }
} catch (PDOException $e) {
    die("Gagal mengambil data produk: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Detail Produk - <?= htmlspecialchars($product['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/purchase.css">
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
    <div class="back-button-container">
        <a href="/" class="back-button">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="container">
        <h1 class="title-product"><?= htmlspecialchars($product['name']) ?></h1>

        <div class="product-details">
            <div class="product-image-container">
                <img src="<?= htmlspecialchars($product['thumbnail']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
            </div>

            <div class="content">
                <div class="product-description"><?= htmlspecialchars($product['description']) ?></div>

                <p class="price">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>

                <button class="get-prompt-btn" onclick="openModal()">
                    <i class="fas fa-shopping-cart"></i> Beli Sekarang
                    <span class="btn-shine"></span>
                </button>

                <div class="purchase-info">
                    <p>Setelah pembelian, Anda akan mendapatkan akses ke file prompt yang dapat digunakan dengan ChatGPT Image atau di Promptbase. Prompt dapat dikembalikan jika tidak berfungsi sesuai harapan. Dengan membeli prompt ini, Anda menyetujui ketentuan layanan kami.</p>
                    <p style="margin-top: 10px;"><i class="fas fa-clock"></i> Ditambahkan 6 jam yang lalu</p>
                </div>
            </div>
        </div>

        <h2 class="title-section">Gallery</h2>
        <div class="product-gallery">
            <?php
            try {
                $stmt = $pdo->prepare("SELECT * FROM product_photos WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $photos = [];
            }

            if (empty($photos)) {
                echo '<div class="gallery-item"><img src="' . htmlspecialchars($product['thumbnail']) . '" alt="' . htmlspecialchars($product['name']) . '"></div>';
            } else {
                foreach ($photos as $photo) {
                    echo '<div class="gallery-item"><img src="' . htmlspecialchars($photo['photo_url']) . '" alt="' . htmlspecialchars($product['name']) . '"></div>';
                }
            }
            ?>
        </div>

        <h2 class="title-section">Produk Terkait</h2>
        <div class="related-products-grid">
            <?php
            try {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id != ? ORDER BY RAND() LIMIT 4");
                $stmt->execute([$product_id]);
                $related_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($related_products as $related) {
                    echo '<div class="related-product-card">';
                    echo '<img src="' . htmlspecialchars($related['thumbnail']) . '" alt="' . htmlspecialchars($related['name']) . '" class="related-product-img">';
                    echo '<div class="related-product-content">';
                    echo '<h3 class="related-product-title">' . htmlspecialchars($related['name']) . '</h3>';
                    echo '<div class="related-product-price">Rp ' . number_format($related['price'], 0, ',', '.') . '</div>';
                    echo '<a href="/pages/purchase.php?id=' . $related['id'] . '" class="get-prompt-btn" style="margin-top: 15px; font-size: 0.9rem; padding: 12px 20px;">';
                    echo '<i class="fas fa-eye"></i> Lihat Detail';
                    echo '</a>';
                    echo '</div>';
                    echo '</div>';
                }
            } catch (PDOException $e) {
                echo '<p>Gagal memuat produk terkait</p>';
            }
            ?>
        </div>

        <div class="modal" id="confirmTransaction" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Konfirmasi Pembayaran</h2>
                    <span class="close-modal" id="closeModalBtn">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="product-summary">
                        <h3>Detail Pesanan</h3>
                        <div class="product-summary-desc">
                            <p><strong>Produk:</strong> <?= htmlspecialchars($product['name']) ?></p>
                            <p><strong>Jumlah yang di beli:</strong> 1</p>
                            <p><strong>Total harga:</strong> Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="cancel-button" id="cancelBtn">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <a href="/pages/payment.php?id=<?= $transaksi_id ?>&product=<?= $product_id ?>" class="confirm-button">
                        <i class="fas fa-check"></i> Konfirmasi Pembayaran
                    </a>
                </div>
            </div>
        </div>

        <div class="modal" id="pendingTransactionModal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Transaksi Belum Selesai</h2>
                </div>
                <div class="modal-body">
                    <div class="product-summary">
                        <h3>Anda memiliki transaksi yang belum selesai</h3>
                        <div class="product-summary-desc">
                            <p>Anda memiliki transaksi yang belum dibayar. Silakan selesaikan pembayaran terlebih dahulu atau batalkan transaksi sebelumnya.</p>
                            <div id="pendingProductInfo" style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
                                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                                    <img id="pendingProductImage" src="" alt="Product Image" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                    <div>
                                        <h4 id="pendingProductName" style="margin: 0 0 5px 0; color: var(--text-light);"></h4>
                                        <p id="pendingProductQuantity" style="margin: 0; color: var(--primary-color); font-weight: bold;">Jumlah Beli : 1</p>
                                        <p id="pendingProductPrice" style="margin: 0; color: var(--primary-color); font-weight: bold;"></p>
                                    </div>
                                </div>
                                <p id="pendingProductDescription" style="margin: 0; font-size: 0.9rem; color: var(--text-muted);"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="cancel-button" id="cancelTransactionBtn">
                        <i class="fas fa-times"></i> Batalkan Transaksi
                    </button>
                    <a href="" id="continuePendingPaymentBtn" class="confirm-button">
                        <i class="fas fa-check"></i> Lanjutkan Pembayaran
                    </a>
                </div>
            </div>
        </div>
        <script>
            function openModal() {
                if (pendingTransaction) {
                    openPendingModal();
                    return;
                }
                if (purchaseModal) {
                    purchaseModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    setTimeout(() => {
                        purchaseModal.classList.add('show');
                    }, 10);
                }
            }
            const purchaseModal = document.getElementById('confirmTransaction');
            const pendingModal = document.getElementById('pendingTransactionModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const cancelTransactionBtn = document.getElementById('cancelTransactionBtn');
            const continuePendingPaymentBtn = document.getElementById('continuePendingPaymentBtn');
            const closePendingBtn = document.getElementById('closePendingModalBtn');

            function closeModal() {
                if (purchaseModal) {
                    purchaseModal.classList.remove('show');
                    setTimeout(() => {
                        purchaseModal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }, 300);
                }
            }

            document.getElementById('closeModalBtn').onclick = function() {
                closeModal();
            }

            function openPendingModal() {
                if (pendingModal) {
                    pendingModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                    if (pendingTransaction) {
                        document.getElementById('pendingProductImage').src = pendingTransaction.thumbnail;
                        document.getElementById('pendingProductName').textContent = pendingTransaction.product_name;
                        document.getElementById('pendingProductPrice').textContent = "Rp " + Number(pendingTransaction.total_harga).toLocaleString('id-ID', { maximumFractionDigits: 0 });
                        document.getElementById('pendingProductDescription').textContent = pendingTransaction.product_description;
                        continuePendingPaymentBtn.href = "/pages/payment.php?id=" + pendingTransaction.transaksi_id + "&product=" + pendingTransaction.kode_produk;
                    }
                }
            }

            function closePendingModal() {
                if (pendingModal) {
                    pendingModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            }

            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
            if (cancelTransactionBtn) {
                cancelTransactionBtn.addEventListener('click', function() {
                    closePendingModal();
                    const transaksiId = pendingTransaction.transaksi_id;
                    if (!transaksiId) {
                        alert("ID transaksi tidak ditemukan.");
                        return;
                    }
                    fetch(`/api/cancel_transaction.php?id=${transaksiId}`, {
                            method: 'GET',
                            credentials: 'include'
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message);
                                location.reload();
                            } else if (data.error) {
                                alert(data.error);
                            } else {
                                alert('Gagal membatalkan transaksi');
                            }
                        })
                        .catch(err => {
                            alert('Terjadi kesalahan saat membatalkan transaksi.');
                        });
                });
            }

            const pendingTransaction = <?= json_encode($pending_transaction) ?>;
            if (pendingTransaction) {
                openPendingModal();
            }

            window.onclick = function(event) {
                if (event.target == purchaseModal) {
                    closeModal();
                }
            }
        </script>
</body>
</html>