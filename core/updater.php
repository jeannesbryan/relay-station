<?php
require_once 'ssl_shield.php';
// RELAY STATION: OVER-THE-AIR (OTA) UPDATER ENGINE
// Menarik rilis terbaru dari pusat komando dan menambal sistem secara otomatis.

session_start();

// Keamanan Lapis Baja: Hanya Kapten yang boleh memicu pembaruan
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("[ FATAL ERROR ] Akses Ditolak.");
}

// 1. Konfigurasi Pusat Komando
$remote_beacon_url = 'https://raw.githubusercontent.com/jeannesbryan/relay-station/main/version.json?t=' . time();
$current_version = '4.0'; // Versi stasiun saat ini

// 2. Cek Pembaruan (Ping Remote)
$ch = curl_init($remote_beacon_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$remote_json = curl_exec($ch);
curl_close($ch);

if (!$remote_json) {
    die("<h3 style='color:red;'>[ SIGNAL LOST ] Gagal menghubungi Pusat Komando. Coba lagi nanti.</h3>");
}

$update_data = json_decode($remote_json, true);

if (version_compare($update_data['version'], $current_version, '>')) {
    
    // Jika user mengonfirmasi eksekusi (via parameter ?execute=true)
    if (isset($_GET['execute']) && $_GET['execute'] == 'true') {
        
        $zip_url = $update_data['download_url'];
        $temp_zip = '../temp_update.zip';
        
        // A. Unduh Paket Pembaruan
        file_put_contents($temp_zip, file_get_contents($zip_url));
        
        // B. Eksekusi Ekstraksi (Bedah Sistem)
        $zip = new ZipArchive;
        if ($zip->open($temp_zip) === TRUE) {
            
            // Failsafe: Daftar area terlarang yang TIDAK BOLEH ditimpa
            $protected_zones = ['data/relay_core.sqlite', 'media/'];
            
            $extract_path = '../';
            
            // Ekstrak file satu per satu untuk menghindari penimpaan data vital
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                
                $is_protected = false;
                foreach ($protected_zones as $zone) {
                    if (strpos($filename, $zone) === 0) {
                        $is_protected = true; break;
                    }
                }
                
                if (!$is_protected) {
                    // Ekstrak dan timpa file lama
                    $zip->extractTo($extract_path, array($filename));
                }
            }
            $zip->close();
            
            // C. Hapus file ZIP sementara
            unlink($temp_zip);
            
            // D. (Opsional) Jalankan skrip migrasi database jika ada
            if (file_exists('../core/upgrade_db.php')) {
                include '../core/upgrade_db.php';
                unlink('../core/upgrade_db.php'); // Hapus setelah dieksekusi
            }
            
            echo "<script>alert('Pembaruan ke v{$update_data['version']} BERHASIL!'); window.location.href='../console.php';</script>";
            exit;
            
        } else {
            die("<h3 style='color:red;'>[ ERROR ] Gagal membongkar paket pembaruan. File ZIP mungkin korup.</h3>");
        }
    }
    
    // Jika baru mengecek, tampilkan layar konfirmasi gaya Terminal UI
    echo '<!DOCTYPE html><html lang="en"><head><title>SYSTEM UPDATE</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css"></head><body class="t-crt t-center-screen">';
    echo '<div class="t-center-box t-card warning mb-0" style="max-width: 500px;">';
    echo '<h2 class="t-card-header">> SYSTEM_UPDATE_DETECTED</h2>';
    echo "<p class='mb-2'>Versi Saat Ini: <strong>v{$current_version}</strong></p>";
    echo "<p class='mb-3'>Versi Terbaru: <strong class='text-success t-blink'>v{$update_data['version']}</strong></p>";
    echo "<div class='t-window mb-4'><div class='t-window-header'>CHANGELOG</div><pre class='t-window-body' style='font-size:12px;'>".htmlspecialchars($update_data['changelog'])."</pre></div>";
    echo '<div class="d-flex justify-content-between gap-3">';
    echo '<a href="../console.php" class="t-btn w-100">[ BATAL ]</a>';
    echo '<a href="updater.php?execute=true" class="t-btn warning w-100 t-glow">[ INITIATE_UPGRADE ]</a>';
    echo '</div></div></body></html>';

} else {
    echo "<script>alert('Sistem Anda sudah berada di versi terbaru (v{$current_version}).'); window.location.href='../console.php';</script>";
}
?>