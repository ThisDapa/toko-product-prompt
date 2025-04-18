<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID transaksi tidak ditemukan']);
    exit();
}

$total_harga = isset($_GET['total_harga']) ? floatval($_GET['total_harga']) : 0;

$transaksi_id = $_GET['id'];
$user_id = $_GET['user_id'];

function cancel_transaction($pdo, $transaksi_id, $user_id) {
    try {
        $check_stmt = $pdo->prepare("SELECT * FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id AND status = false");
        $check_stmt->execute([
            'transaksi_id' => $transaksi_id,
            'user_id' => $user_id
        ]);
        
        $transaction = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$transaction) {
            return ['success' => false, 'message' => 'Transaksi tidak ditemukan atau sudah selesai'];
        }
        
        $created_time = strtotime($transaction['created_at']);
        $current_time = time();
        $time_diff_minutes = ($current_time - $created_time) / 60;
        
        if ($time_diff_minutes >= 15) {
            $stmt = $pdo->prepare("DELETE FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id AND status = false");
            $stmt->execute([
                'transaksi_id' => $transaksi_id,
                'user_id' => $user_id
            ]);
            
            $qrisPath = __DIR__ . '/../assets/img/qris/' . $transaksi_id . '.png';
            if (file_exists($qrisPath)) {
                unlink($qrisPath);
            }
            
            return ['success' => true, 'cancelled' => true, 'message' => 'Transaksi dibatalkan karena melebihi batas waktu'];
        }
        
        return ['success' => true, 'cancelled' => false];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function checkQRIS($merchantKey, $apiKey) {
    $url = "https://gateway.okeconnect.com/api/mutasi/qris/{$merchantKey}/{$apiKey}";

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        return false;
    }

    $data = json_decode($response, true);
    return $data;
}

try {
    $cancel_result = cancel_transaction($pdo, $transaksi_id, $user_id);
    
    if ($cancel_result['success'] && $cancel_result['cancelled']) {
        echo json_encode([
            'status' => false,
            'cancelled' => true,
            'message' => 'Transaksi telah dibatalkan karena melebihi batas waktu 15 menit',
            'redirect' => '/pages/purchase.php?id=' . (isset($_GET['product_id']) ? $_GET['product_id'] : '')
        ]);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT t.*, p.price FROM transaksi t 
                          JOIN products p ON t.kode_produk = p.id 
                          WHERE t.transaksi_id = :transaksi_id AND t.user_id = :user_id");
    $stmt->execute([
        'transaksi_id' => $transaksi_id,
        'user_id' => $user_id
    ]);
    
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
    $orkut_transaction = checkQRIS($_ENV['MERCHANT_KEY'], $_ENV['APIKEY_ORKUT']);
    echo json_encode($orkut_transaction);
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['error' => 'Transaksi tidak ditemukan']);
        exit();
    }
    
    if (!$orkut_transaction) {
        http_response_code(500);
        echo json_encode(['error' => 'Gagal mendapatkan status transaksi']);
        exit();
    }

    $price_verified = false;

    if (isset($orkut_transaction['data']) && is_array($orkut_transaction['data'])) {
        foreach ($orkut_transaction['data'] as $transactions) {
            if (intval($transactions['amount']) === intval($total_harga)) {
                $price_verified = true;

                $stmt = $pdo->prepare("UPDATE transaksi SET status = TRUE WHERE transaksi_id = :transaksi_id");
                $stmt->execute(['transaksi_id' => $transaksi_id]);
            }
        }
    }


    echo json_encode([
        'status' => (bool)$transaction['status'],
        'price_verified' => $price_verified,
        'redirect' => ($transaction['status'] && $price_verified) ? '/pages/download_prompt.php?id=' . $transaksi_id : null
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan server', 'message' => $e->getMessage()]);
    error_log($e->getMessage());
}