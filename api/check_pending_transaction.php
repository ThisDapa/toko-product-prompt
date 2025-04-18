<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized', 'status' => false]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT t.*, p.name as product_name, p.description as product_description, p.thumbnail 
                          FROM transaksi t 
                          LEFT JOIN products p ON t.kode_produk = p.id 
                          WHERE t.user_id = :user_id AND t.status = false 
                          ORDER BY t.created_at DESC LIMIT 1");
    $stmt->execute([
        'user_id' => $user_id
    ]);
    
    $pending_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pending_transaction) {
        echo json_encode(['has_pending' => false]);
        exit();
    }
    
    echo json_encode([
        'has_pending' => true,
        'transaction_id' => $pending_transaction['transaksi_id'],
        'product_id' => $pending_transaction['kode_produk'],
        'product_name' => $pending_transaction['nama_produk'],
        'product_description' => $pending_transaction['product_description'],
        'product_thumbnail' => $pending_transaction['thumbnail'],
        'total' => $pending_transaction['total_harga'],
        'created_at' => $pending_transaction['created_at']
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'status' => false]);
    exit();
}