<?php
require_once 'ssl_shield.php';
// ==========================================================
// 🚀 RELAY STATION: TRANSMITTER ENGINE (V6.2)
// Handles Public, Direct, Ghost Protocol, Media, Sonar Pulse, ACKs, 
// and Scorched Earth & Global Purge Protocols.
// Now equipped with Symmetric Handshake Token injector.
// ==========================================================

date_default_timezone_set('UTC'); // Enforce UTC to prevent Ghost Protocol timing issues

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Capture Console Input
    $content = trim($_POST['content'] ?? '');
    
    // [ E2E ] Capture specific ciphertext for local database
    $content_local = trim($_POST['content_local'] ?? $content); 
    
    $visibility = $_POST['visibility'] ?? 'public';
    $target_planet = trim($_POST['target_planet'] ?? '');
    
    // 2. Detect Ghost Protocol (Self-destruct timer)
    $is_ghost = isset($_POST['ghost_protocol']) ? true : false;
    $expiry_date = null;
    if ($is_ghost) {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
    }

    // 3. Identify Local Commander Coordinates (Subfolder Aware)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    // Track dynamic folder path
    $script_path = dirname($_SERVER['SCRIPT_NAME']); 
    $base_path = dirname($script_path); 
    if ($base_path === '\\' || $base_path === '/') {
        $base_path = '';
    }

    $my_planet_url = rtrim($protocol . $host . $base_path, '/');
    $author_alias = 'LOCAL_COMMAND'; 
    
    // Prevent empty payload transmission (Unless it's a structural purge signal)
    if ($content === '' && $visibility !== 'scorched_earth') {
        $redirect = ($visibility === 'direct') ? '../direct.php' : '../console.php';
        header("Location: $redirect?error=empty_payload");
        exit;
    }

    // ==========================================
    // 🛡️ [ DOUBLE SHIELD PROTOCOL: SONAR VALIDATION ]
    // ==========================================
    if ($visibility === 'sonar_pulse') {
        if (!preg_match('/^[a-zA-Z0-9]{1,15}$/', $content)) {
            header("Location: ../console.php?error=invalid_sonar");
            exit;
        }
        $content = strtoupper($content); // Force uppercase for Morse Payload
    }

    // 🚀 [ TACTICAL SIGNAL CLASSIFICATION ]
    // Signals that bypass media processing and local database insertion
    $tactical_signals = ['sonar_pulse', 'ack_receipt', 'scorched_earth', 'global_purge'];

    // ==========================================
    // 🖼️ & 🎙️ [ ADVANCED MEDIA MATRIX ]
    // ==========================================
    $final_media_url = null;
    $media_urls = []; // Container for all processed media

    // Skip media processing for Tactical Signals
    if (!in_array($visibility, $tactical_signals)) {
        $upload_dir = '../media/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

        // Priority 1: Capture Base64 from JS Compressor (WebP/Array)
        if (!empty($_POST['media_base64'])) {
            $mb_raw = trim($_POST['media_base64']);
            $mb_items = [];
            
            if (strpos($mb_raw, '[') === 0) {
                $mb_items = json_decode($mb_raw, true) ?? [];
            } else {
                $mb_items = [$mb_raw];
            }

            foreach ($mb_items as $media_base64) {
                if (empty($media_base64)) continue;
                list($type, $media_base64) = explode(';', $media_base64);
                list(, $media_base64)      = explode(',', $media_base64);
                $media_data = base64_decode($media_base64);
                
                $filename = uniqid('sig_') . '.webp';
                $filepath = $upload_dir . $filename;
                
                if (file_put_contents($filepath, $media_data)) {
                    $media_urls[] = $my_planet_url . '/media/' . $filename;
                }
            }
        } 
        
        // Priority 1.5: Capture Base64 from PTT Audio Recorder (WebM/Ogg)
        if (!empty($_POST['audio_base64'])) {
            $audio_base64 = $_POST['audio_base64'];
            list($type, $audio_base64) = explode(';', $audio_base64);
            list(, $audio_base64)      = explode(',', $audio_base64);
            $media_data = base64_decode($audio_base64);
            
            $ext = 'webm'; // Default fallback
            if (strpos($type, 'audio/mp4') !== false || strpos($type, 'video/mp4') !== false) $ext = 'm4a';
            elseif (strpos($type, 'audio/ogg') !== false || strpos($type, 'video/ogg') !== false) $ext = 'ogg';

            $filename = uniqid('ptt_') . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (file_put_contents($filepath, $media_data)) {
                $media_urls[] = $my_planet_url . '/media/' . $filename;
            }
        }
        
        // Priority 2: Fallback if JS is disabled in browser (Raw Upload)
        if (empty($_POST['media_base64']) && isset($_FILES['media'])) {
            $files = $_FILES['media'];
            
            $file_names = is_array($files['name']) ? $files['name'] : [$files['name']];
            $file_tmp_names = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
            $file_errors = is_array($files['error']) ? $files['error'] : [$files['error']];

            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4'];
            
            for ($i = 0; $i < count($file_names); $i++) {
                if ($file_errors[$i] === UPLOAD_ERR_OK) {
                    $file_ext = strtolower(pathinfo($file_names[$i], PATHINFO_EXTENSION));
                    
                    if (in_array($file_ext, $allowed_ext)) {
                        $prefix = in_array($file_ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4']) ? 'ptt_' : 'sig_';
                        $filename = uniqid($prefix) . '.' . $file_ext;
                        $target_file = $upload_dir . $filename;
                        
                        if (move_uploaded_file($file_tmp_names[$i], $target_file)) {
                            $media_urls[] = $my_planet_url . '/media/' . $filename;
                        }
                    }
                }
            }
        }

        // 🚧 [ TACTICAL LIMIT ]: Maksimal 4 Media (Grid 2x2)
        if (count($media_urls) > 4) {
            $media_urls = array_slice($media_urls, 0, 4);
        }

        // 🗄️ [ SMART STORAGE FORMATTER ]
        if (count($media_urls) === 1) {
            $final_media_url = $media_urls[0];
        } elseif (count($media_urls) > 1) {
            $final_media_url = json_encode($media_urls, JSON_UNESCAPED_SLASHES);
        }
    }
    // ==========================================

    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';

    try {
        // 4. WRITE TO LOCAL CORE MEMORY 
        if (!in_array($visibility, $tactical_signals)) {
            $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, target_planet, is_remote, author_alias, expiry_date, media_url) VALUES (:content, :visibility, :target, 0, :author, :expiry, :media)");
            $stmt->execute([
                ':content' => $content_local, 
                ':visibility' => $visibility,
                ':target' => $target_planet,
                ':author' => $author_alias,
                ':expiry' => $expiry_date,
                ':media' => $final_media_url
            ]);
        }
        
        // 5. ASSEMBLE BASE JSON CAPSULE
        $base_payload = [
            "content" => $content, 
            "author_alias" => $author_alias,
            "from_planet" => $my_planet_url,
            "visibility" => $visibility,
            "expiry_date" => $expiry_date,
            "media_url" => $final_media_url
        ];

        // --- [ TRANSMISSION ROUTING ] ---

        if ($visibility === 'public' || $visibility === 'global_purge') {
            // [ SCATTER BEAM ] Broadcast to all allies in the Star Chart
            // [ V6.2 ] Fetch Handshake Tokens for each ally to bypass their Anti-Spoofing firewall
            $query = $db->query("SELECT planet_url, handshake_token FROM following");
            $allies = $query->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($allies) > 0) {
                // Machine Gun (Multi-cURL)
                $mh = curl_multi_init();
                $curl_array = [];
                
                foreach ($allies as $i => $ally) {
                    $target_url = rtrim($ally['planet_url'], '/') . '/api_inbox.php';
                    
                    // Inject Unique Token for this specific ally
                    $ally_payload = $base_payload;
                    $ally_payload['handshake_token'] = $ally['handshake_token'] ?? '';
                    $json_payload = json_encode($ally_payload);
                    
                    $curl_array[$i] = curl_init($target_url);
                    curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl_array[$i], CURLOPT_POST, true);
                    curl_setopt($curl_array[$i], CURLOPT_POSTFIELDS, $json_payload);
                    curl_setopt($curl_array[$i], CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($json_payload)
                    ]);
                    curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 5); 
                    curl_multi_add_handle($mh, $curl_array[$i]);
                }
                
                // Fire simultaneously
                $running = null;
                do { curl_multi_exec($mh, $running); } while ($running);
                
                // Clean up execution handles
                foreach ($allies as $i => $ally) { curl_multi_remove_handle($mh, $curl_array[$i]); }
                curl_multi_close($mh);
            }

        } elseif (in_array($visibility, ['direct', 'sonar_pulse', 'ack_receipt', 'scorched_earth'])) {
            // [ LASER LINK / TACTICAL PULSES ] Fire specific message/ping to single target
            if (!empty($target_planet)) {
                $target_clean = rtrim($target_planet, '/');
                if (strpos($target_clean, 'http') !== 0) {
                    $target_clean = 'https://' . $target_clean;
                }
                
                $target_url = $target_clean . '/api_inbox.php';
                
                // [ V6.2 ] Fetch token specifically for this target
                $stmt_tk = $db->prepare("SELECT handshake_token FROM following WHERE planet_url = :url");
                $stmt_tk->execute([':url' => $target_clean]);
                $hs_token = $stmt_tk->fetchColumn() ?: '';
                
                $direct_payload = $base_payload;
                $direct_payload['handshake_token'] = $hs_token;
                $json_payload = json_encode($direct_payload);
                
                // Single cURL execution
                $ch = curl_init($target_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_payload)
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Mission complete, return to appropriate Radar
        if (in_array($visibility, ['ack_receipt', 'scorched_earth', 'global_purge'])) {
            // Sinyal ini ditembak via AJAX (Latar Belakang), jangan lakukan redirect!
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => "[ TACTICAL SIGNAL $visibility FIRED ]"]);
            exit;
        } elseif ($visibility === 'direct') {
            header("Location: ../direct.php?status=transmission_successful");
        } else {
            // For both Public and Sonar Pulses
            header("Location: ../console.php?status=transmission_successful");
        }
        exit;

    } catch (PDOException $e) {
        if (in_array($visibility, ['ack_receipt', 'scorched_earth', 'global_purge'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error']);
            exit;
        }
        die("<h3 style='color:red;'>[ TRANSMISSION FAILED ] Core Memory Error: " . $e->getMessage() . "</h3>");
    }
} else {
    die("<h3 style='color:red;'>[ ERROR ] Invalid Protocol. Use main console.</h3>");
}