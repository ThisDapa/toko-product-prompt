<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function toCRC16($str)
{
    $crc = 0xFFFF;
    $strlen = strlen($str);

    for ($c = 0; $c < $strlen; $c++) {
        $crc ^= (ord($str[$c]) << 8);
        for ($i = 0; $i < 8; $i++) {
            if (($crc & 0x8000) !== 0) {
                $crc = ($crc << 1) ^ 0x1021;
            } else {
                $crc <<= 1;
            }
        }
    }

    $hex = strtoupper(dechex($crc & 0xFFFF));
    return str_pad($hex, 4, "0", STR_PAD_LEFT);
}

function createQRIS($nominal, $path)
{
    try {
        if (!is_numeric($nominal) || $nominal <= 0) {
            throw new Exception("Nominal harus berupa angka positif.");
        }

        $qris = $_ENV['QRCODE_TEXT'];
        if (!$qris) {
            throw new Exception("QRCODE_TEXT tidak ditemukan di environment variables.");
        }

        $qris2 = substr($qris, 0, -4);
        $replaceQris = str_replace("010211", "010212", $qris2);

        $pecahQris = explode("5802ID", $replaceQris);
        if (count($pecahQris) !== 2) {
            throw new Exception("Format QRIS tidak valid.");
        }

        $length = str_pad(strlen($nominal), 2, "0", STR_PAD_LEFT);
        $uang = "54" . $length . $nominal . "5802ID";

        $raw = $pecahQris[0] . $uang . $pecahQris[1];
        $crc = toCRC16($raw);
        $output = $raw . $crc;

        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $output,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $result = $builder->build();

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result->saveToFile($path);
        return $path;
    } catch (Exception $e) {
        error_log("Error creating QRIS: " . $e->getMessage());
        throw $e;
    }
}

checkAuth();

if (!isset($_GET['id'])) {
    header('Location: /');
    exit();
}

$transaksi_id = $_GET['id'];
$product_id = $_GET['product'];
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE transaksi_id = :transaksi_id");
    $stmt->execute(['transaksi_id' => $transaksi_id]);
    $existing_transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_transaction) {
        if ($existing_transaction['status'] == true) {
            header('Location: /');
            exit();
        }
        $fee = $existing_transaction['fee'];
        $total_harga = $existing_transaction['total_harga'];
        $product_id = $existing_transaction['kode_produk'];
        $product_stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $product_stmt->execute(['id' => $product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            header('Location: /');
            exit();
        }
        $qrisDir = __DIR__ . '/../assets/img/qris';
        $qrisPath = $qrisDir . '/' . $transaksi_id . '.png';
        if (!file_exists($qrisPath)) {
            createQRIS($total_harga, $qrisPath);
        }
        if (isset($_POST['cancel'])) {
            try {
                $stmt = $pdo->prepare("DELETE FROM transaksi WHERE transaksi_id = :transaksi_id AND user_id = :user_id");
                $stmt->execute([
                    'transaksi_id' => $transaksi_id,
                    'user_id' => $user_id
                ]);
                if (file_exists($qrisPath)) {
                    unlink($qrisPath);
                }
                header('Location: /');
                exit();
            } catch (PDOException $e) {
                die("Gagal membatalkan transaksi: " . $e->getMessage());
            }
        }
    } else {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->execute(['id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            header('Location: /');
            exit();
        }
        function getRandomAvailableFee($pdo)
        {
            $all = range(0, 375);
            $used = $pdo->query("SELECT fee FROM transaksi")->fetchAll(PDO::FETCH_COLUMN);
            $available = array_diff($all, $used);
            if (empty($available)) {
                return false;
            }
            return $available[array_rand($available)];
        }
        $fee = getRandomAvailableFee($pdo);
        $total_harga = $product['price'] + $fee;
        $qrisDir = __DIR__ . '/../assets/img/qris';
        if (!is_dir($qrisDir)) {
            mkdir($qrisDir, 0755, true);
        }
        $qrisPath = $qrisDir . '/' . $transaksi_id . '.png';
        try {
            if (!file_exists($qrisPath)) {
                createQRIS($total_harga, $qrisPath);
            }
            if (isset($_POST['cancel'])) {
                $stmt = $pdo->prepare("DELETE FROM transaksi WHERE transaksi_id = :transaksi_id");
                $stmt->execute(['transaksi_id' => $transaksi_id]);
                if (file_exists($qrisPath)) {
                    unlink($qrisPath);
                }
                header('Location: /pages/home.php');
                exit();
            }
            $stmt = $pdo->prepare("INSERT INTO transaksi (transaksi_id, user_id, fee, total_harga, nama_produk, kode_produk, status) VALUES (:transaksi_id, :user_id, :fee, :total_harga, :nama_produk, :kode_produk, :status)");
            $stmt->execute([
                'transaksi_id' => $transaksi_id,
                'user_id' => $user_id,
                'fee' => $fee,
                'total_harga' => $total_harga,
                'nama_produk' => $product['name'],
                'kode_produk' => $product_id,
                'status' => false
            ]);
        } catch (Exception $e) {
            if (file_exists($qrisPath)) {
                unlink($qrisPath);
            }
            throw $e;
        }
    }
} catch (PDOException $e) {
    die("Gagal mengambil data produk: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Pembayaran - <?= htmlspecialchars($product['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/home.css">
    <link rel="stylesheet" href="/assets/css/payment.css">
</head>

<body>
    <div class="container">
        <a href="/pages/purchase.php?id=<?= $product_id ?>" class="back-button">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>

        <div class="payment-container">
            <div class="payment-header">
                <h1 class="payment-title">Pembayaran QRIS</h1>
                <p class="payment-subtitle">Scan QR code di bawah ini untuk melakukan pembayaran</p>
            </div>

            <div class="payment-amount">
                Rp <?= number_format($total_harga, 0, ',', '.') ?>
            </div>

            <div class="qr-container">
                <img src="/assets/img/qris/<?= htmlspecialchars($transaksi_id) ?>.png" alt="QRIS Code" class="qr-code">
            </div>

            <div class="timer">
                Waktu tersisa: <span id="countdown">15:00</span>
            </div>

            <div class="payment-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        Buka aplikasi e-wallet atau m-banking yang mendukung QRIS
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        Scan QR code yang ditampilkan di atas
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        Periksa detail pembayaran dan selesaikan transaksi
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        Setelah pembayaran berhasil, Anda akan diarahkan ke halaman konfirmasi
                    </div>
                </div>
            </div>

            <div class="payment-info">
                <p><i class="fas fa-info-circle"></i> Pembayaran akan otomatis diverifikasi oleh sistem</p>
            </div>
            <form method="post" style="text-align: center; margin-top: 20px;">
                <button type="submit" name="cancel" class="cancel-button">
                    <i class="fas fa-times"></i> Batalkan Transaksi
                </button>
            </form>
        </div>
        </form>
    </div>

    <script>
        function startCountdown() {
            const transactionId = '<?= $transaksi_id ?>';
            const productId = '<?= $product_id ?>';
            let timeLeft;

            const savedTime = localStorage.getItem(`countdown_${transactionId}`);
            const expireTime = localStorage.getItem(`expire_${transactionId}`);

            if (savedTime && expireTime && new Date().getTime() < parseInt(expireTime)) {
                timeLeft = parseInt(savedTime);
            } else {
                timeLeft = 15 * 60;
                const newExpireTime = new Date().getTime() + (15 * 60 * 1000);
                localStorage.setItem(`expire_${transactionId}`, newExpireTime);
                localStorage.setItem(`countdown_${transactionId}`, timeLeft);
            }

            const countdownElement = document.getElementById('countdown');

            function updateCountdown() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;

                countdownElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

                localStorage.setItem(`countdown_${transactionId}`, timeLeft);

                if (timeLeft <= 0) {
                    clearInterval(timer);
                    localStorage.removeItem(`countdown_${transactionId}`);
                    localStorage.removeItem(`expire_${transactionId}`);
                    window.location.href = '/pages/purchase.php?id=' + productId;
                }

                timeLeft--;
            }

            updateCountdown();

            const timer = setInterval(updateCountdown, 1000);

            function checkTransactionStatus() {
                const totalHarga = <?= $total_harga ?>;
                fetch(`/api/check_qris_status.php?id=${transactionId}&total_harga=${totalHarga}&product_id=${productId}&user_id=<?= $user_id ?>`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === true && data.price_verified && data.redirect) {
                            localStorage.removeItem(`countdown_${transactionId}`);
                            localStorage.removeItem(`expire_${transactionId}`);
                            window.location.href = data.redirect;
                        } else if (data.cancelled) {
                            localStorage.removeItem(`countdown_${transactionId}`);
                            localStorage.removeItem(`expire_${transactionId}`);
                            alert('Transaksi telah dibatalkan karena melebihi batas waktu 15 menit');
                            window.location.href = data.redirect || '/pages/purchase.php?id=' + productId;
                        }
                    })
                    .catch(error => {
                        console.error('Error checking transaction status:', error);
                    });
            }

            checkTransactionStatus();
            const statusChecker = setInterval(checkTransactionStatus, 10000);

            window.addEventListener('beforeunload', function() {
                clearInterval(statusChecker);
            });
        }

        document.addEventListener('DOMContentLoaded', startCountdown);
    </script>
</body>

</html>