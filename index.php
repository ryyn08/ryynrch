<?php
/**
 * RYYN 404 - React Channel All-in-One System
 * Nama File: index.php (Gabungan HTML, CSS, & PHP)
 */

session_start();
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

// PROSES LOGIKA BACKEND JIKA FORM DI-SUBMIT (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $urlInput = isset($_POST['url']) ? trim($_POST['url']) : null;
    $emojiInput = isset($_POST['emoji']) ? trim($_POST['emoji']) : '';

    if (empty($urlInput) || empty($emojiInput)) {
        die("<script>alert('Harap isi semua kolom!'); window.history.back();</script>");
    }

    // --- VALIDASI FORMAT EMOJI KOMA ---
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
            window.location.href = 'index.php';
        </script>";
        exit;

    } catch (Exception $e) {
        $err = $e->getMessage();
        echo "<script>alert('GAGAL!\\nTerjadi kesalahan: $err'); window.history.back();</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RYYN 404 • React Channel</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Space+Grotesk:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-deep: #030014;
            --bg-card: rgba(17, 25, 40, 0.75);
            --border-light: rgba(255, 255, 255, 0.08);
            --border-hover: rgba(124, 58, 237, 0.5);
            --primary: #8b5cf6;  
            --secondary: #06b6d4;
            --text-main: #ffffff;
            --text-muted: #9ca3af;
            --font-head: 'Space Grotesk', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;
            --glass-blur: blur(16px);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background-color: var(--bg-deep);
            color: var(--text-main);
            font-family: var(--font-body);
            overflow-x: hidden;
            min-height: 100vh;
            font-size: 0.8rem;
        }

        /* Background Effects */
        .bg-fixed { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -2; background: linear-gradient(to bottom, #030014, #0f0728); }
        .cyber-grid {
            position: fixed; width: 200%; height: 200%; top: -50%; left: -50%; z-index: -1;
            background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px; transform: perspective(500px) rotateX(60deg); animation: gridMove 20s linear infinite; opacity: 0.35;
        }
        @keyframes gridMove { 100% { transform: perspective(500px) rotateX(60deg) translateY(50px); } }

        /* Navbar */
        nav {
            position: fixed; top: 15px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 850px; padding: 8px 18px;
            background: rgba(10, 10, 15, 0.6); backdrop-filter: var(--glass-blur);
            border: 1px solid var(--border-light); border-radius: 100px;
            display: flex; justify-content: space-between; align-items: center; z-index: 999;
        }
        .brand { font-family: var(--font-head); font-weight: 700; font-size: 1.05rem; }
        .brand span { color: var(--primary); }

        .status-widget {
            display: flex; align-items: center; gap: 8px;
            padding: 4px 12px; background: rgba(255,255,255,0.05);
            border-radius: 20px; border: 1px solid rgba(255,255,255,0.1);
        }
        /* Titik hijau tetap berkedip dengan lancar */
        .dot { width: 7px; height: 7px; background: #4ade80; border-radius: 50%; box-shadow: 0 0 8px #4ade80; animation: pulse 1.5s infinite; }
        .dot.cd { background: #f87171; box-shadow: 0 0 8px #f87171; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
        .status-text { font-size: 0.65rem; font-weight: 700; font-family: 'Space Grotesk'; text-transform: uppercase; letter-spacing: 0.5px; }

        header { padding: 110px 20px 25px; text-align: center; max-width: 700px; margin: 0 auto; }
        .badge-pill {
            display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 50px;
            background: rgba(139, 92, 246, 0.1); border: 1px solid rgba(139, 92, 246, 0.2);
            font-size: 0.65rem; color: #d8b4fe; margin-bottom: 12px;
        }
        h1 { font-family: var(--font-head); font-size: 2.1rem; margin-bottom: 10px; background: linear-gradient(180deg, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1.2; }
        header p { font-size: 0.78rem; }

        .main-container { max-width: 440px; margin: 0 auto; padding: 15px; }
        .card {
            background: var(--bg-card); border: 1px solid var(--border-light);
            border-radius: 18px; padding: 22px; backdrop-filter: var(--glass-blur);
        }
        .input-group { margin-bottom: 15px; text-align: left; }
        .input-group label { display: block; margin-bottom: 6px; font-size: 0.75rem; color: var(--text-muted); font-weight: 600; }
        .input-group input {
            width: 100%; padding: 10px 15px; background: rgba(0,0,0,0.3);
            border: 1px solid var(--border-light); border-radius: 10px; color: white;
            font-size: 0.8rem; transition: 0.3s;
        }
        .input-group input:focus { border-color: var(--primary); box-shadow: 0 0 12px rgba(139, 92, 246, 0.2); }

        .btn-submit {
            width: 100%; padding: 11px; border-radius: 10px; border: none;
            background: linear-gradient(135deg, var(--primary), #6d28d9);
            color: white; font-weight: 700; font-size: 0.8rem; cursor: pointer; transition: 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3); }

        footer { text-align: center; padding: 40px; color: var(--text-muted); font-size: 0.7rem; }
    </style>
</head>
<body>

    <div class="bg-fixed"></div>
    <div class="cyber-grid"></div>

    <nav>
        <div class="brand">RYYN<span> 404</span></div>
        <div class="status-widget">
            <div class="dot" id="statusDot"></div>
            <span class="status-text" id="statusLabel">SERVER : ACTIVE</span>
        </div>
    </nav>

    <header>
        <div class="badge-pill">
            <i class="fas fa-bolt"></i>
            <span>RYYN 404 AUTOMATION</span>
        </div>
        <h1>Whatsapp Channel<br>React pesan</h1>
        <p style="color: var(--text-muted);">Masukan link pesan channel dan emoji menggunakan pemisah koma untuk memberikan reaksi otomatis.</p>
    </header>

    <div class="main-container">
        <form class="card" action="" method="POST">
            <div class="input-group">
                <label><i class="fas fa-link"></i> URL PESAN CHANNEL</label>
                <input type="text" name="url" placeholder="https://whatsapp.com/channel/xxx/123" required>
            </div>
            <div class="input-group">
                <label><i class="fas fa-face-smile"></i> EMOJI REAKSI (GUNAKAN KOMA)</label>
                <input type="text" name="emoji" placeholder="contoh: 😮,🥰,😂" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane"></i> KIRIM REAKSI
            </button>
        </form>
    </div>

    <footer>
        &copy; 2026 <strong>RYYN 404</strong> • All Rights Reserved.
    </footer>

    <script>
        function checkStatus() {
            const cookies = document.cookie.split(';');
            let isCooldown = false;
            for(let i=0; i < cookies.length; i++) {
                if(cookies[i].trim().startsWith('react_cd=')) {
                    isCooldown = true;
                    break;
                }
            }
            const dot = document.getElementById('statusDot');
            const label = document.getElementById('statusLabel');
            if(isCooldown) {
                dot.classList.add('cd');
                label.innerText = "SERVER : COOLDOWN";
            } else {
                dot.classList.remove('cd');
                label.innerText = "SERVER : ACTIVE";
            }
        }
        window.onload = checkStatus;
    </script>
</body>
</html>
