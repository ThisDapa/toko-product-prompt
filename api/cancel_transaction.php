<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["error" => "Unauthorized", "status" => false]);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'Missing transaction ID', 'status' => false]);
    exit();
}

$transaksi_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id AND status = false");
    $stmt->execute([
        'transaksi_id' => $transaksi_id,
        'user_id' => $user_id
    ]);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$transaction) {
        echo json_encode(['error' => 'Transaction not found or already completed', 'success' => false]);
        exit();
    }
    
    $stmt = $pdo->prepare("DELETE FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id AND status = false");
    $stmt->execute([
        'transaksi_id' => $transaksi_id,
        'user_id' => $user_id
    ]);
    
    $qrisPath = __DIR__ . '/../assets/img/qris/' . $transaksi_id . '.png';
    if (file_exists($qrisPath)) {
        unlink($qrisPath);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Transaksi berhasil dibatalkan'
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage(), 'success' => false]);
    exit();
}