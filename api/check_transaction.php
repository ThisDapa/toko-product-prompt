<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized', 'status' => false]);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Missing transaction ID', 'status' => false]);
    exit();
}

$transaksi_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id");
    $stmt->execute([
        'transaksi_id' => $transaksi_id,
        'user_id' => $user_id
    ]);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['error' => 'Transaction not found', 'status' => false]);
        exit();
    }
    
    echo json_encode([
        'status' => (bool)$transaction['status'],
        'transaction_id' => $transaction['transaksi_id'],
        'product_name' => $transaction['nama_produk'],
        'total' => $transaction['total_harga']
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'status' => false]);
    exit();
}