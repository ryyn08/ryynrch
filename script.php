<?php
/**
 * RYYN 404 - React Channel Backend Router
 * Nama File: script.php
 */

session_start();
header('Content-Type: text/html; charset=UTF-8');
date_default_timezone_set('Asia/Jakarta');

const BASE_URL = 'https://react-channelwa.vercel.app/api';
const UA = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36';
const TELEGRAM_TOKEN = "8272585724:AAEDXLqT3k7SzTRkEjH-HALWioVmgr_orvg";
const OWNER_ID = "7246739496";

const COOLDOWN_TIME = 600; // 10 Menit
const MAX_LIMIT_PER_DAY = 5; // Batasan 5x sehari

function generateDeviceId() {
    return bin2hex(random_bytes(16));
}

function postRequest($url, $payload) {
    $ch = curl_init($url);
    $jsonData = json_encode($payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: ' . UA,
        'Origin: https://react-channelwa.vercel.app',
        'Referer: https://react-channelwa.vercel.app/'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Handler Reset Limit Otomatis saat Jam 00:00 WIB
$todayDate = date('Y-m-d');
if (!isset($_SESSION['reset_date']) || $_SESSION['reset_date'] !== $todayDate) {
    $_SESSION['usage_count'] = 0;
    $_SESSION['reset_date'] = $todayDate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urlInput = isset($_POST['url']) ? trim($_POST['url']) : null;
    $emojiInput = isset($_POST['emoji']) ? trim($_POST['emoji']) : '';

    if (empty($urlInput) || empty($emojiInput)) {
        die("<script>alert('Harap isi semua kolom!'); window.history.back();</script>");
    }

    // --- VALIDASI FORMAT EMOJI KOMA ---
    // Memeriksa jika input mengandung emoji berdempetan tanpa koma
    if (preg_match('/[\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}][\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}]/u', $emojiInput)) {
        die("<script>
            alert('PERINGATAN! Input emoji salah. Format harus dipisah menggunakan koma yang bener.\\n\\nContoh Salah: 😂🥰😮\\nContoh Benar: 😮,🥰,😂');
            window.history.back();
        </script>");
    }

    // --- VALIDASI LIMIT HARIAN (5X SEHARI) ---
    if ($_SESSION['usage_count'] >= MAX_LIMIT_PER_DAY) {
        die("<script>alert('Akses Ditolak! Kamu sudah mencapai batasan harian (Maksimal 5x dalam 1 hari). Limit akan di-reset otomatis pada jam 00:00 WIB.'); window.history.back();</script>");
    }

    // --- VALIDASI COOLDOWN (10 MENIT) ---
    if (isset($_COOKIE['react_cd'])) {
        $remaining = $_COOKIE['react_cd'] - time();
        if ($remaining > 0) {
            $mins = floor($remaining / 60);
            $secs = $remaining % 60;
            die("<script>alert('Server sedang Cooldown! Sisa waktu tunggu: $mins menit $secs detik.'); window.history.back();</script>");
        }
    }

    try {
        $deviceId = generateDeviceId();
        $deviceName = "Device-" . rand(1000, 9999);

        // Step 1: Register Device
        $regPayload = ['deviceId' => $deviceId, 'name' => $deviceName];
        $regAction = postRequest(BASE_URL . '/register-device', $regPayload);
        $deviceKey = isset($regAction['deviceKey']) ? $regAction['deviceKey'] : null;
        
        if (!$deviceKey) {
            throw new Exception(isset($regAction['message']) ? $regAction['message'] : 'Gagal register device');
        }

        // Step 2: Inject Reaksi
        $injPayload = ['deviceKey' => $deviceKey, 'url' => $urlInput, 'emojis' => $emojiInput];
        $injAction = postRequest(BASE_URL . '/inject', $injPayload);

        // Menambah Hit Penghitung Limit jika Berhasil
        $_SESSION['usage_count']++;

        // Membuat Cookie Masa Cooldown 10 Menit ke depan
        $cdExpiredTime = time() + COOLDOWN_TIME;
        setcookie('react_cd', $cdExpiredTime, $cdExpiredTime, "/");

        $timeString = date('H:i', $cdExpiredTime);
        $currentTime = date('d/m/Y, H:i:s');

        // Mengambil IP User
        $userIp = $_SERVER['REMOTE_ADDR'];
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { $userIp = $_SERVER['HTTP_CLIENT_IP']; }
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { $userIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; }

        // Kirim laporan Telegram
        $tgMessage = "HI SIR THERE IS A NEW MESSAGE\n\n" .
                     "IP : " . $userIp . "\n" .
                     "LINK CH : " . $urlInput . "\n" .
                     "REAKSI EMOJI : " . $emojiInput . "\n" .
                     "SISA LIMIT HARI INI : " . (MAX_LIMIT_PER_DAY - $_SESSION['usage_count']) . "\n" .
                     "AKTIF KEMBALI : " . $timeString . " WIB\n" .
                     "WAKTU : " . $currentTime;

        $tgUrl = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
        postRequest($tgUrl, ['chat_id' => OWNER_ID, 'text' => $tgMessage]);

        $msgResult = isset($injAction['message']) ? $injAction['message'] : '';
        echo "<script>
            alert('REAKSI TERKIRIM!\\nSukses memberikan react [$emojiInput]. $msgResult\\nKamu bisa menggunakan alat ini lagi pada jam: $timeString WIB.');
            window.location.href = 'index.html';
        </script>";

    } catch (Exception $e) {
        $err = $e->getMessage();
        echo "<script>alert('GAGAL!\\nTerjadi kesalahan: $err'); window.history.back();</script>";
    }
} else {
    header('Location: index.html');
    exit;
}
?>
