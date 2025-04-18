<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth.php';

checkAdmin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = str_replace(['.', ','], '', $_POST['price']); // Remove formatting

    if (empty($name) || empty($description) || empty($price)) {
        $error = 'Semua field harus diisi';
    } else {
        try {
            $pdo->beginTransaction();

            $prompt_file_path = '';
            if (isset($_FILES['prompt_file']) && $_FILES['prompt_file']['error'] === UPLOAD_ERR_OK) {
                $prompt_file = $_FILES['prompt_file'];
                $ext = strtolower(pathinfo($prompt_file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['txt'])) {
                    throw new Exception('Format file prompt tidak valid. Gunakan file TXT.');
                }
                $prompt_file_path = '/uploads/products/prompts/' . uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../../../' . $prompt_file_path;
                
                if (!is_dir(dirname($upload_path))) {
                    mkdir(dirname($upload_path), 0777, true);
                }
                
                if (!move_uploaded_file($prompt_file['tmp_name'], $upload_path)) {
                    throw new Exception('Gagal mengupload file prompt');
                }
            }

            $thumbnail_path = '';
            if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
                $thumbnail = $_FILES['thumbnail'];
                $ext = strtolower(pathinfo($thumbnail['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    throw new Exception('Format thumbnail tidak valid. Gunakan JPG, PNG, atau WebP.');
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

            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, thumbnail, prompt_file, badge, category, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $description, $price, $thumbnail_path, $prompt_file_path, $_POST['badge'], $_POST['category']]);
            $product_id = $pdo->lastInsertId();

            if (isset($_FILES['photos']) && !empty($_FILES['photos']['name'][0])) {
                $photos = $_FILES['photos'];
                $photo_paths = [];
                $allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
                $max_file_size = 5 * 1024 * 1024;

                for ($i = 0; $i < count($photos['name']); $i++) {
                    if ($photos['error'][$i] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($photos['name'][$i], PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed_types)) {
                            throw new Exception('Format foto tidak valid. Gunakan JPG, PNG, atau WebP.');
                        }

                        if ($photos['size'][$i] > $max_file_size) {
                            throw new Exception('Ukuran foto terlalu besar. Maksimal 5MB per foto.');
                        }
                        
                        $photo_path = '/uploads/products/photos/' . uniqid() . '.' . $ext;
                        $upload_path = __DIR__ . '/../../../' . $photo_path;
                        
                        if (!is_dir(dirname($upload_path))) {
                            mkdir(dirname($upload_path), 0777, true);
                        }
                        
                        if (move_uploaded_file($photos['tmp_name'][$i], $upload_path)) {
                            $photo_paths[] = $photo_path;
                        } else {
                            throw new Exception('Gagal mengupload foto produk.');
                        }
                    } else if ($photos['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                        throw new Exception('Terjadi kesalahan saat upload foto.');
                    }
                }

                if (!empty($photo_paths)) {
                    $stmt = $pdo->prepare("INSERT INTO product_photos (product_id, photo_url) VALUES (?, ?)");
                    foreach ($photo_paths as $path) {
                        $stmt->execute([$product_id, $path]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['success_message'] = 'Produk berhasil ditambahkan';
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Produk berhasil ditambahkan']);
                exit();
            } else {
                header('Location: /admin/products');
                exit();
            }

        } catch(Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Gagal menambahkan produk: ' . $e->getMessage();
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error]);
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Produk Baru</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <h1>Tambah Produk Baru</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="/admin/products/add" method="POST" enctype="multipart/form-data" class="product-form">
            <div class="form-group">
                <label for="name">Nama Produk</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="description">Deskripsi Produk</label>
                <textarea id="description" name="description" rows="4" required></textarea>
            </div>

            <div class="form-group">
                <label for="price">Harga (Rp)</label>
                <input type="text" id="price" name="price" required
                       pattern="[0-9.,]*" title="Masukkan angka saja"
                       oninput="this.value = this.value.replace(/[^0-9.,]/g, '').replace(/(\..*)\./g, '$1');">
            </div>

            <div class="form-group">
                <label for="thumbnail">Thumbnail Produk</label>
                <input type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp" required>
                <small>Format: JPG, PNG, atau WebP</small>
            </div>

            <div class="form-group">
                <label for="prompt_file">File Prompt</label>
                <input type="file" id="prompt_file" name="prompt_file" accept=".txt" required>
                <small>Format: TXT</small>
            </div>

            <div class="form-group">
                <label for="photos">Foto-foto Produk</label>
                <div class="upload-container" id="upload-container">
                    <input type="file" id="photos" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <div class="drag-text">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop foto di sini atau klik untuk memilih</p>
                        <small>Format: JPG, PNG, atau WebP (Maks. 5MB per file)</small>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="badge">Badge Produk</label>
                <input type="text" id="badge" name="badge" placeholder="Masukkan badge produk" required>
                <small>Contoh: New, Best Seller, Limited Edition</small>
            </div>

            <div class="form-group">
                <label for="category">Kategori Produk</label>
                <select id="category" name="category" required>
                    <option value="">Pilih Kategori</option>
                    <option value="Digital Marketing">Digital Marketing</option>
                    <option value="Drawings">Drawings</option>
                    <option value="Designs">Designs</option>
                    <option value="Stock Photos">Stock Photos</option>
                    <option value="Events">Events</option>
                    <option value="Fashion">Fashion</option>
                    <option value="Architecture">Architecture</option>
                    <option value="Automobiles">Automobiles</option>
                    <option value="Food">Food</option>
                    <option value="Rakhi">Rakhi</option>
                </select>
            </div>
                <!-- Preview container dihapus sesuai permintaan -->
                <div id="upload-progress" class="upload-progress" style="display: none;">
                    <div class="progress-bar"></div>
                    <div class="progress-text">0%</div>
                </div>
            </div>

            <div class="form-actions">
                <a href="/admin/products" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Produk</button>
            </div>
        </form>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script>
    document.getElementById('price').addEventListener('input', function(e) {
        let value = this.value.replace(/[^0-9.,]/g, '');
        
        value = value.replace(/\./g, '').replace(/,/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('id-ID');
        }
        
        this.value = value;
    });

    const uploadContainer = document.getElementById('upload-container');
    const previewContainer = document.getElementById('preview-container');
    const photoInput = document.getElementById('photos');
    const progressBar = document.querySelector('.progress-bar');
    const progressText = document.querySelector('.progress-text');
    const maxFileSize = 5 * 1024 * 1024;

    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (!file.type.match('image.*')) {
                alert('Hanya file gambar yang diperbolehkan');
                return;
            }
            if (file.size > maxFileSize) {
                alert(`File ${file.name} terlalu besar. Maksimal 5MB per file`);
                return;
            }
        });
    }

    photoInput.addEventListener('change', function(e) {
        handleFiles(this.files);
    });

    uploadContainer.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.add('dragover');
    });

    uploadContainer.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
    });

    uploadContainer.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        this.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    document.querySelector('.product-form').addEventListener('submit', function(e) {
        const uploadProgress = document.getElementById('upload-progress');
        const files = document.getElementById('photos').files;
        
        if (files.length > 0) {
            e.preventDefault();
            uploadProgress.style.display = 'block';
            
            const formData = new FormData(this);
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                    progressText.textContent = Math.round(percentComplete) + '%';
                }
            });
            
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            window.location.href = '/admin/products';
                        } else {
                            alert(response.message || 'Terjadi kesalahan saat menyimpan produk');
                            uploadProgress.style.display = 'none';
                        }
                    } catch (error) {
                        window.location.href = '/admin/products';
                    }
                } else {
                    alert('Terjadi kesalahan saat mengupload file: ' + xhr.status);
                    uploadProgress.style.display = 'none';
                }
            });
            
            xhr.addEventListener('error', function() {
                alert('Terjadi kesalahan koneksi saat mengupload file');
                uploadProgress.style.display = 'none';
            });
            
            xhr.open('POST', this.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(formData);
        }
    });
    </script>
</body>
</html>