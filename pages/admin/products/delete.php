<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth.php';

checkAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id'])) {
    header('Location: /admin/products');
    exit();
}

$product_id = $_POST['product_id'];

try {
    $stmt = $pdo->prepare("SELECT thumbnail FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if ($product) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT photo_url FROM product_photos WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $photos = $stmt->fetchAll();

        if ($product['thumbnail'] && file_exists(__DIR__ . '/../../../' . ltrim($product['thumbnail'], '/'))) {
            unlink(__DIR__ . '/../../../' . ltrim($product['thumbnail'], '/'));
        }

        foreach ($photos as $photo) {
            if (file_exists(__DIR__ . '/../../../' . ltrim($photo['photo_url'], '/'))) {
                unlink(__DIR__ . '/../../../' . ltrim($photo['photo_url'], '/'));
            }
        }

        $stmt = $pdo->prepare("DELETE FROM product_photos WHERE product_id = ?");
        $stmt->execute([$product_id]);

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);

        $pdo->commit();

        $_SESSION['success_message'] = 'Produk berhasil dihapus';
    }
} catch(PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error_message'] = 'Gagal menghapus produk: ' . $e->getMessage();
}

header('Location: /admin/products');
exit();