<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth.php';

checkAdmin();

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) ? strtoupper($_GET['order']) : 'DESC';

$allowed_sort_fields = ['name', 'price', 'created_at'];
if (!in_array($sort, $allowed_sort_fields)) {
    $sort = 'created_at';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    try {
        $stmt = $pdo->prepare("SELECT thumbnail FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        
        if ($product && $product['thumbnail'] && file_exists(__DIR__ . '/../../' . $product['thumbnail'])) {
            unlink(__DIR__ . '/../../' . $product['thumbnail']);
        }
        
        $stmt = $pdo->prepare("SELECT photo_url FROM product_photos WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $photos = $stmt->fetchAll();
        
        foreach ($photos as $photo) {
            if (file_exists(__DIR__ . '/../../' . $photo['photo_url'])) {
                unlink(__DIR__ . '/../../' . $photo['photo_url']);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM product_photos WHERE product_id = ?");
        $stmt->execute([$product_id]);
        
        $_SESSION['success_message'] = 'Produk berhasil dihapus';
        header('Location: /admin/products');
        exit();
    } catch(PDOException $e) {
        $error = "Gagal menghapus produk: " . $e->getMessage();
    }
}

$query = "SELECT * FROM products";
$params = [];

if ($search) {
    $query .= " WHERE name LIKE ? OR description LIKE ?";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$query .= " ORDER BY {$sort} {$order}";

$products = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = "Error fetching products: " . $e->getMessage();
}

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Produk</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .search-sort-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-form {
            flex: 1;
            max-width: 400px;
        }
        
        .sort-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .sort-controls select {
            padding: 10px 15px;
            border-radius: 8px;
            background-color: #1a1c3d;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
        }
        
        .sort-controls label {
            color: #a3a8ff;
        }
        
        .product-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 2rem;
            background-color: #161837;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .product-table th,
        .product-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .product-table th {
            background-color: #1f2142;
            font-weight: 600;
            color: #a3a8ff;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }
        
        .product-table tr:last-child td {
            border-bottom: none;
        }
        
        .product-table tr:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .thumbnail-preview {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
        }
        
        .thumbnail-preview:hover {
            transform: scale(1.1);
        }
        
        .admin-actions {
            margin-bottom: 20px;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #4b6cb7, #182848);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(75, 108, 183, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ff6b6b, #ee0979);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(238, 9, 121, 0.3);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8a8eaf;
        }
        
        .price-column {
            font-weight: 600;
            color: #6d78ff;
        }
        
        @media (max-width: 768px) {
            .search-sort-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                max-width: 100%;
                width: 100%;
            }
            
            .product-table {
                display: block;
                overflow-x: auto;
            }
            
            .product-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Manajemen Produk</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="search-sort-container">
            <form class="search-form" method="GET">
                <div class="form-group" style="margin: 0;">
                    <input type="text" name="search" placeholder="Cari produk..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </form>
            <div class="sort-controls">
                <label>Urutkan:</label>
                <select name="sort" onchange="updateSort(this.value)">
                    <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Tanggal</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Nama</option>
                    <option value="price" <?php echo $sort === 'price' ? 'selected' : ''; ?>>Harga</option>
                </select>
                <select name="order" onchange="updateOrder(this.value)">
                    <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Menurun</option>
                    <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Menaik</option>
                </select>
            </div>
        </div>

        <div class="admin-actions">
            <a href="/admin/products/add" class="btn btn-primary">Tambah Produk Baru</a>
        </div>

        <table class="product-table">
            <thead>
                <tr>
                    <th>Gambar</th>
                    <th>Nama Produk</th>
                    <th>Harga</th>
                    <th>Tanggal Dibuat</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td>
                        <?php if ($product['thumbnail']): ?>
                            <img src="<?php echo $product['thumbnail']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="thumbnail-preview">
                        <?php else: ?>
                            <div class="thumbnail-preview" style="background: #2a2d5a; display: flex; align-items: center; justify-content: center;">
                                <span style="color: #8a8eaf;">No Image</span>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td class="price-column">Rp <?php echo number_format($product['price'], 0, ',', '.'); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($product['created_at'])); ?></td>
                    <td>
                        <div class="product-actions">
                            <a href="/admin/products/edit?id=<?php echo $product['id']; ?>" class="btn-edit">Edit</a>
                            <form action="/admin/products" method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <button type="submit" class="btn-delete">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="5" class="empty-state">
                        <div>
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 5H4C2.9 5 2 5.9 2 7V17C2 18.1 2.9 19 4 19H20C21.1 19 22 18.1 22 17V7C22 5.9 21.1 5 20 5Z" stroke="#8a8eaf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M12 12C13.1046 12 14 11.1046 14 10C14 8.89543 13.1046 8 12 8C10.8954 8 10 8.89543 10 10C10 11.1046 10.8954 12 12 12Z" stroke="#8a8eaf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M18 15C17.66 14.5 16.86 14 16 14H8C7.14 14 6.34 14.5 6 15" stroke="#8a8eaf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <p style="margin-top: 10px;">Tidak ada produk ditemukan</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
    function updateSort(value) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('sort', value);
        window.location.search = urlParams.toString();
    }

    function updateOrder(value) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('order', value);
        window.location.search = urlParams.toString();
    }
    </script>
</body>
</html>