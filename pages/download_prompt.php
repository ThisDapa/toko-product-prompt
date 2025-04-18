<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

checkAuth();

$transaksi_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT t.*, p.prompt_file FROM transaksi t 
                          JOIN products p ON t.kode_produk = p.id 
                          WHERE t.transaksi_id = :transaksi_id 
                          AND t.user_id = :user_id 
                          AND t.status = true");
    $stmt->execute([
        'transaksi_id' => $transaksi_id,
        'user_id' => $user_id
    ]);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        header('Location: /');
        exit();
    }
    
    $promptFile = $transaction['prompt_file'];
    $downloadPath = '/uploads/products/prompts/' . $promptFile;
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Download Prompt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <style>
        .download-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .success-icon {
            color: #4CAF50;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .download-title {
            color: #333;
            margin-bottom: 20px;
        }
        
        .download-button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            transition: background-color 0.3s;
        }
        
        .download-button:hover {
            background-color: #45a049;
        }
        
        .back-home {
            display: block;
            margin-top: 20px;
            color: #666;
            text-decoration: none;
        }
        
        .back-home:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-logo">
                <img src="/assets/img/favicon.ico" alt="Logo" class="logo-img">
                <span>TokoSaya</span>
            </div>
            <div class="navbar-links">
                <a href="/pages/home.php">Home</a>
                <a href="/pages/history.php">History</a>
                <a href="/pages/purchase.php">Purchase</a>
                <a href="/pages/auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    <div class="download-container">
        <i class="fas fa-check-circle success-icon"></i>
        <h1 class="download-title">Pembayaran Berhasil!</h1>
        <p>Terima kasih telah melakukan pembelian. Anda sekarang dapat mengunduh prompt yang telah Anda beli.</p>
        
        <a href="<?php echo htmlspecialchars($downloadPath); ?>" class="download-button" download>
            <i class="fas fa-download"></i> Download Prompt
        </a>
        
        <a href="/" class="back-home">
            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
        </a>
    </div>
</body>
</html>