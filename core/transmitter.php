<?php
require_once 'ssl_shield.php';
// ==========================================================
// 🚀 RELAY STATION: TRANSMITTER ENGINE (V7.3)
// Handles Public, Direct, Ghost Protocol, Media, Sonar Pulse, ACKs, 
// Scorched Earth, Global Purge, SIGNAL RESONANCE, and THE RELAY PROTOCOL.
// Equipped with Anti-Loop Shield, Chain Purge support, and WAF Bypass.
// ==========================================================

date_default_timezone_set('UTC'); // Enforce UTC to prevent Ghost Protocol timing issues

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. [ V7.1 ] Capture & Sanitize Console Input (Local Defense)
    $content = strip_tags(trim($_POST['content'] ?? ''));
    $content_local = strip_tags(trim($_POST['content_local'] ?? $content)); 
    $visibility = strip_tags(trim($_POST['visibility'] ?? 'public'));
    $target_planet = filter_var(trim($_POST['target_planet'] ?? ''), FILTER_SANITIZE_URL);
    
    // [ V7.2 & V7.3 ] Special tactical parameters
    $post_id = (int)($_POST['post_id'] ?? 0); 
    $origin_id = strip_tags(trim($_POST['origin_id'] ?? '')); // [ V7.3 ] DNA Sinyal
    
    // 2. Detect Ghost Protocol (Self-destruct timer)
    $is_ghost = isset($_POST['ghost_protocol']) ? true : false;
    $expiry_date = null;
    if ($is_ghost) {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
    }

    // 3. Identify Local Commander Coordinates
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    
    $script_path = dirname($_SERVER['SCRIPT_NAME']); 
    $base_path = dirname($script_path); 
    if ($base_path === '\\' || $base_path === '/') {
        $base_path = '';
    }

    $my_planet_url = rtrim($protocol . $host . $base_path, '/');
    $author_alias = 'LOCAL_COMMAND'; 
    
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
        $content = strtoupper($content); 
    }

    // 🚀 [ TACTICAL SIGNAL CLASSIFICATION ]
    // Signals that bypass media processing and standard insertion
    $tactical_signals = ['sonar_pulse', 'ack_receipt', 'scorched_earth', 'global_purge', 'resonance'];

    // ==========================================
    // 🖼️ & 🎙️ [ ADVANCED MEDIA MATRIX ]
    // ==========================================
    $final_media_url = null;
    $media_urls = []; 

    if (!in_array($visibility, $tactical_signals)) {
        $upload_dir = '../media/';
        if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

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
        
        if (!empty($_POST['audio_base64'])) {
            $audio_base64 = $_POST['audio_base64'];
            list($type, $audio_base64) = explode(';', $audio_base64);
            list(, $audio_base64)      = explode(',', $audio_base64);
            $media_data = base64_decode($audio_base64);
            
            $ext = 'webm'; 
            if (strpos($type, 'audio/mp4') !== false || strpos($type, 'video/mp4') !== false) $ext = 'm4a';
            elseif (strpos($type, 'audio/ogg') !== false || strpos($type, 'video/ogg') !== false) $ext = 'ogg';

            $filename = uniqid('ptt_') . '.' . $ext;
            $filepath = $upload_dir . $filename;
            
            if (file_put_contents($filepath, $media_data)) {
                $media_urls[] = $my_planet_url . '/media/' . $filename;
            }
        }
        
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

        if (count($media_urls) > 4) {
            $media_urls = array_slice($media_urls, 0, 4);
        }

        if (count($media_urls) === 1) {
            $final_media_url = $media_urls[0];
        } elseif (count($media_urls) > 1) {
            $final_media_url = json_encode($media_urls, JSON_UNESCAPED_SLASHES);
        }
    }

    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';

    try {
        // 4. WRITE TO LOCAL CORE MEMORY 
        if (!in_array($visibility, $tactical_signals)) {
            // [ V7.3 ] Inject is_relay and origin_id into local database
            $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, target_planet, is_remote, author_alias, expiry_date, media_url, is_relay, origin_id) VALUES (:content, :visibility, :target, 0, :author, :expiry, :media, 0, :origin_id)");
            $stmt->execute([
                ':content' => $content_local, 
                ':visibility' => $visibility,
                ':target' => $target_planet,
                ':author' => $author_alias,
                ':expiry' => $expiry_date,
                ':media' => $final_media_url,
                ':origin_id' => !empty($origin_id) ? $origin_id : null
            ]);
        }

        // ==========================================
        // ⚡ [ V7.2 ] SIGNAL RESONANCE (ROGER THAT)
        // ==========================================
        if ($visibility === 'resonance') {
            if ($post_id <= 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => '[ CORRUPTED SIGNAL ] Missing target ID.']);
                exit;
            }

            // Anti-Spam Check
            $stmt_check = $db->prepare("SELECT COUNT(*) FROM signal_resonance WHERE post_id = :pid AND reactor_url = :my_url");
            $stmt_check->execute([':pid' => $post_id, ':my_url' => $my_planet_url]);
            if ($stmt_check->fetchColumn() > 0) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => '[ ANTI-SPAM ] Signal already acknowledged.']);
                exit;
            }

            $stmt_res = $db->prepare("INSERT INTO signal_resonance (post_id, reactor_url, reactor_alias, resonance_type) VALUES (:pid, :my_url, :alias, :type)");
            $stmt_res->execute([
                ':pid' => $post_id,
                ':my_url' => $my_planet_url,
                ':alias' => $author_alias,
                ':type' => $content 
            ]);
        }
        
        // ==========================================
        // 💥 [ V7.3 ] CHAIN-PURGE (FETCH ORIGIN DNA)
        // ==========================================
        if ($visibility === 'global_purge') {
            // Lacak DNA (origin_id) dari pesan yang dihapus Kapten agar rudalnya akurat
            $stmt_orig = $db->prepare("SELECT origin_id FROM transmissions WHERE content = :content AND is_remote = 0 LIMIT 1");
            $stmt_orig->execute([':content' => $content_local]);
            $fetched_origin = $stmt_orig->fetchColumn();
            if ($fetched_origin) {
                $origin_id = $fetched_origin;
            }
        }

        // 5. ASSEMBLE BASE JSON CAPSULE
        if ($visibility === 'resonance') {
            $base_payload = [
                "action" => "resonance",
                "post_id" => $post_id,
                "reactor" => $author_alias,
                "type" => $content,
                "from_planet" => $my_planet_url
            ];
        } else {
            $base_payload = [
                "content" => $content, 
                "author_alias" => $author_alias,
                "from_planet" => $my_planet_url,
                "visibility" => $visibility,
                "expiry_date" => $expiry_date,
                "media_url" => $final_media_url,
                "is_relay" => 0,          // [ V7.3 ] Original broadcast is never a relay from the sender's perspective
                "origin_id" => $origin_id // [ V7.3 ] Embed DNA into the transmission payload
            ];
        }

        // --- [ TRANSMISSION ROUTING ] ---

        if ($visibility === 'public' || $visibility === 'global_purge') {
            // [ SCATTER BEAM ] Broadcast to all allies
            $query = $db->query("SELECT planet_url, handshake_token FROM following");
            $allies = $query->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($allies) > 0) {
                $mh = curl_multi_init();
                $curl_array = [];
                
                foreach ($allies as $i => $ally) {
                    $target_url = rtrim($ally['planet_url'], '/') . '/api_inbox.php';
                    
                    $ally_payload = $base_payload;
                    $ally_payload['handshake_token'] = $ally['handshake_token'] ?? '';
                    $json_payload = json_encode($ally_payload);
                    
                    $curl_array[$i] = curl_init($target_url);
                    curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl_array[$i], CURLOPT_POST, true);
                    curl_setopt($curl_array[$i], CURLOPT_POSTFIELDS, $json_payload);
                    curl_setopt($curl_array[$i], CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($json_payload),
                        'User-Agent: RelayStation-Transmitter/7.3' // [ V7.3 ] WAF Bypass Upgrade
                    ]);
                    curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 5); 
                    curl_multi_add_handle($mh, $curl_array[$i]);
                }
                
                $running = null;
                do { curl_multi_exec($mh, $running); } while ($running);
                
                foreach ($allies as $i => $ally) { curl_multi_remove_handle($mh, $curl_array[$i]); }
                curl_multi_close($mh);
            }

        } elseif (in_array($visibility, ['direct', 'sonar_pulse', 'ack_receipt', 'scorched_earth', 'resonance'])) {
            // [ LASER LINK / TACTICAL Pulses ] Fire specific message to single target
            if (!empty($target_planet)) {
                $target_clean = rtrim($target_planet, '/');
                if (strpos($target_clean, 'http') !== 0) {
                    $target_clean = 'https://' . $target_clean;
                }
                
                $target_url = $target_clean . '/api_inbox.php';
                
                $stmt_tk = $db->prepare("SELECT handshake_token FROM following WHERE planet_url = :url");
                $stmt_tk->execute([':url' => $target_clean]);
                $hs_token = $stmt_tk->fetchColumn() ?: '';
                
                $direct_payload = $base_payload;
                $direct_payload['handshake_token'] = $hs_token;
                $json_payload = json_encode($direct_payload);
                
                $ch = curl_init($target_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($json_payload),
                    'User-Agent: RelayStation-Transmitter/7.3' // [ V7.3 ] WAF Bypass Upgrade
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Mission complete, return to appropriate Radar
        if (in_array($visibility, ['ack_receipt', 'scorched_earth', 'global_purge', 'resonance'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => "[ TACTICAL SIGNAL $visibility FIRED ]"]);
            exit;
        } elseif ($visibility === 'direct') {
            header("Location: ../direct.php?status=transmission_successful");
        } else {
            header("Location: ../console.php?status=transmission_successful");
        }
        exit;

    } catch (PDOException $e) {
        if (in_array($visibility, ['ack_receipt', 'scorched_earth', 'global_purge', 'resonance'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error']);
            exit;
        }
        die("<h3 style='color:red;'>[ TRANSMISSION FAILED ] Core Memory Error: " . $e->getMessage() . "</h3>");
    }
} else {
    die("<h3 style='color:red;'>[ ERROR ] Invalid Protocol. Use main console.</h3>");
}