<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth.php';

checkAdmin();

$error = '';
$success = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: /admin/products');
    exit();
}

$product_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: /admin/products');
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM product_photos WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $photos = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error fetching product: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = str_replace(['.', ','], '', $_POST['price']);

    if (empty($name) || empty($description) || empty($price)) {
        $error = 'Semua field harus diisi';
    } else {
        try {
            $pdo->beginTransaction();

            $thumbnail_path = $product['thumbnail'];
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbnail = $_FILES['thumbnail'];
                $ext = strtolower(pathinfo($thumbnail['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    throw new Exception('Format thumbnail tidak valid. Gunakan JPG, PNG, atau WebP.');
                }
                
                if ($thumbnail_path && file_exists(__DIR__ . '/../../../' . $thumbnail_path)) {
                    unlink(__DIR__ . '/../../../' . $thumbnail_path);
                }
                
                $thumbnail_path = '/uploads/products/' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../../' . $thumbnail_path;
                
                if (!is_dir(dirname($upload_path))) {
                    mkdir(dirname($upload_path), 0777, true);
                }
                
                if (!move_uploaded_file($thumbnail['tmp_name'], $upload_path)) {
                    throw new Exception('Gagal mengupload thumbnail');
                }
            }

            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, thumbnail = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $thumbnail_path, $product_id]);

            if (isset($_FILES['photos']) && $_FILES['photos']['name'][0] !== '') {
                $photos = $_FILES['photos'];
                $photo_paths = [];

                for ($i = 0; $i < count($photos['name']); $i++) {
                    if ($photos['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($photos['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                            continue;
                        }
                        
                        $photo_path = '/uploads/products/photos/' . uniqid() . '.' . $ext;
                        $upload_path = __DIR__ . '/../../../' . $photo_path;
                        
                        if (!is_dir(dirname($upload_path))) {
                            mkdir(dirname($upload_path), 0777, true);
                        }
                        
                        if (move_uploaded_file($photos['tmp_name'][$i], $upload_path)) {
                            $photo_paths[] = $photo_path;
                        }
                    }
                }

                if (!empty($photo_paths)) {
                    $stmt = $pdo->prepare("INSERT INTO product_photos (product_id, photo_url) VALUES (?, ?)");
                    foreach ($photo_paths as $path) {
                        $stmt->execute([$product_id, $path]);
                    }
                }
            }

            if (isset($_POST['delete_photos']) && is_array($_POST['delete_photos'])) {
                foreach ($_POST['delete_photos'] as $photo_id) {
                    $stmt = $pdo->prepare("SELECT photo_url FROM product_photos WHERE id = ? AND product_id = ?");
                    $stmt->execute([$photo_id, $product_id]);
                    $photo = $stmt->fetch();
                    
                    if ($photo) {
                        if (file_exists(__DIR__ . '/../../../' . $photo['photo_url'])) {
                            unlink(__DIR__ . '/../../../' . $photo['photo_url']);
                        }
                        
                        $stmt = $pdo->prepare("DELETE FROM product_photos WHERE id = ?");
                        $stmt->execute([$photo_id]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Produk berhasil diperbarui';
            header('Location: /admin/products');
            exit();

        } catch(Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Gagal memperbarui produk: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Produk</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .current-thumbnail {
            margin-top: 10px;
            margin-bottom: 20px;
        }
        
        .current-thumbnail img {
            max-width: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .photo-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .photo-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }
        
        .photo-delete {
            position: absolute;
            top: 5px;
            right: 5px;
            background-color: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .photo-delete:hover {
            background-color: rgba(220, 53, 69, 1);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Edit Produk</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="/admin/products/edit?id=<?php echo $product_id; ?>" method="POST" enctype="multipart/form-data" class="product-form">
            <div class="form-group">
                <label for="name">Nama Produk</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Deskripsi Produk</label>
                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="price">Harga (Rp)</label>
                <input type="text" id="price" name="price" required
                       pattern="[0-9.,]*" title="Masukkan angka saja"
                       value="<?php echo number_format($product['price'], 0, ',', '.'); ?>"
                       oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*)\./g, '$1');">
            </div>

            <div class="form-group">
                <label for="thumbnail">Thumbnail Produk</label>
                <?php if ($product['thumbnail']): ?>
                    <div class="current-thumbnail">
                        <p>Thumbnail saat ini:</p>
                        <img src="<?php echo $product['thumbnail']; ?>" alt="Current thumbnail">
                    </div>
                <?php endif; ?>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp">
                <small>Format: JPG, PNG, atau WebP. Biarkan kosong jika tidak ingin mengubah thumbnail.</small>
            </div>

            <?php if (!empty($photos)): ?>
            <div class="form-group">
                <label>Foto-foto Produk Saat Ini</label>
                <div class="photo-gallery">
                    <?php foreach ($photos as $photo): ?>
                    <div class="photo-item">
                        <img src="<?php echo $photo['photo_url']; ?>" alt="Product photo">
                        <input type="checkbox" name="delete_photos[]" value="<?php echo $photo['id']; ?>" id="delete_photo_<?php echo $photo['id']; ?>" style="display: none;">
                        <label for="delete_photo_<?php echo $photo['id']; ?>" class="photo-delete" title="Hapus foto">×</label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small>Centang foto untuk menghapusnya</small>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="photos">Tambah Foto-foto Produk Baru</label>
                <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
                <small>Pilih beberapa file. Format: JPG, PNG, atau WebP</small>
            </div>

            <div class="form-actions">
                <a href="/admin/products" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('price').addEventListener('input', function(e) {
        let value = this.value.replace(/[^0-9.,]/g, '');
        
        value = value.replace(/\./g, '').replace(/,/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('id-ID');
        }
        
        this.value = value;
    });
    </script>
</body>
</html>