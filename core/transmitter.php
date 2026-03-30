<?php
// RELAY STATION: TRANSMITTER ENGINE (V4 - Final Cut)
// Menangani pengiriman pesan Publik, Direct, dan Ghost Protocol

date_default_timezone_set('UTC'); // Wajib UTC agar tidak salah meledak

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_file = '../data/relay_core.sqlite';
    
    // 1. Tangkap Input Konsol
    $content = trim($_POST['content'] ?? '');
    $visibility = $_POST['visibility'] ?? 'public';
    $target_planet = trim($_POST['target_planet'] ?? '');
    
    // 2. Deteksi Ghost Protocol (Centang kotak ledakan)
    $is_ghost = isset($_POST['ghost_protocol']) ? true : false;
    $expiry_date = null;
    if ($is_ghost) {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
    }

    // 3. Identifikasi Koordinat Lokal Kapten
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $my_planet_url = $protocol . $_SERVER['HTTP_HOST'];
    $author_alias = 'LOCAL_COMMAND'; 
    
    // Cegah tembakan kosong
    if ($content === '') {
        header("Location: ../index.php?error=empty_payload");
        exit;
    }

    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 4. TULIS KE MEMORI LOKAL
        // Menyimpan semua data termasuk timer ledakan (expiry_date) ke SQLite
        $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, target_planet, is_remote, author_alias, expiry_date) VALUES (:content, :visibility, :target, 0, :author, :expiry)");
        $stmt->execute([
            ':content' => htmlspecialchars($content),
            ':visibility' => $visibility,
            ':target' => $target_planet,
            ':author' => $author_alias,
            ':expiry' => $expiry_date
        ]);
        
        // --- [ PILIHAN SENJATA MERIAM ] ---

        if ($visibility === 'public') {
            // [ SCATTER BEAM ] Tembak ke semua sekutu di Peta Bintang
            $query = $db->query("SELECT planet_url FROM following");
            $allies = $query->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($allies) > 0) {
                // Rakit Kapsul JSON
                $payload = json_encode([
                    "content" => $content,
                    "author_alias" => $author_alias,
                    "from_planet" => $my_planet_url,
                    "visibility" => "public",
                    "expiry_date" => $expiry_date // Kirim timer ledakan ke teman
                ]);

                // Senjata Mesin (Multi-cURL)
                $mh = curl_multi_init();
                $curl_array = [];
                foreach ($allies as $i => $ally) {
                    $target_url = rtrim($ally['planet_url'], '/') . '/api_inbox.php';
                    $curl_array[$i] = curl_init($target_url);
                    curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl_array[$i], CURLOPT_POST, true);
                    curl_setopt($curl_array[$i], CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($curl_array[$i], CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($payload)
                    ]);
                    curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 5); // Timeout anti-hang
                    curl_multi_add_handle($mh, $curl_array[$i]);
                }
                
                // Tembak serentak
                $running = null;
                do { curl_multi_exec($mh, $running); } while ($running);
                
                // Tarik selongsong peluru
                foreach ($allies as $i => $ally) { curl_multi_remove_handle($mh, $curl_array[$i]); }
                curl_multi_close($mh);
            }

        } elseif ($visibility === 'direct') {
            // [ LASER LINK ] Tembak spesifik ke satu target
            if (!empty($target_planet)) {
                // Rakit Kapsul JSON
                $payload = json_encode([
                    "content" => $content,
                    "author_alias" => $author_alias,
                    "from_planet" => $my_planet_url,
                    "visibility" => "direct",
                    "expiry_date" => $expiry_date // Kirim timer ledakan ke target
                ]);

                $target_url = rtrim($target_planet, '/') . '/api_inbox.php';
                
                // Senjata Runtun (Single cURL)
                $ch = curl_init($target_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Timeout anti-hang
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Misi selesai, kembali ke Radar
        header("Location: ../index.php?status=transmission_successful");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ TRANSMISSION FAILED ] Core Memory Error: " . $e->getMessage() . "</h3>");
    }
} else {
    die("<h3 style='color:red;'>[ ERROR ] Invalid Protocol. Gunakan konsol utama.</h3>");
}