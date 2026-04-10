<?php
require_once 'ssl_shield.php';
// ==========================================================
// 🚀 RELAY STATION: TRANSMITTER ENGINE (E2E ENABLED)
// Handles Public, Direct, Ghost Protocol messages, and Media
// ==========================================================

date_default_timezone_set('UTC'); // Enforce UTC to prevent Ghost Protocol timing issues

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Capture Console Input
    $content = trim($_POST['content'] ?? '');
    
    // [ E2E NEW ] Capture specific ciphertext for local database
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

    $my_planet_url = $protocol . $host . $base_path;
    $author_alias = 'LOCAL_COMMAND'; 
    
    // Prevent empty payload transmission
    if ($content === '') {
        $redirect = ($visibility === 'direct') ? '../direct.php' : '../console.php';
        header("Location: $redirect?error=empty_payload");
        exit;
    }

    // ==========================================
    // 🖼️ [ MEDIA PROCESSING ] (With Fallback)
    // ==========================================
    $media_url = null;
    $upload_dir = '../media/';
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

    // Priority 1: Capture Base64 from JS Compressor (WebP)
    if (!empty($_POST['media_base64'])) {
        $media_base64 = $_POST['media_base64'];
        list($type, $media_base64) = explode(';', $media_base64);
        list(, $media_base64)      = explode(',', $media_base64);
        $media_data = base64_decode($media_base64);
        
        $filename = uniqid('sig_') . '.webp';
        $filepath = $upload_dir . $filename;
        
        if (file_put_contents($filepath, $media_data)) {
            $media_url = rtrim($my_planet_url, '/') . '/media/' . $filename;
        }
    } 
    // Priority 2: Fallback if JS is disabled in browser (Raw Upload)
    elseif (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_ext, $allowed_ext)) {
            $filename = uniqid('sig_') . '.' . $file_ext;
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['media']['tmp_name'], $target_file)) {
                $media_url = rtrim($my_planet_url, '/') . '/media/' . $filename;
            }
        }
    }
    // ==========================================

    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';

    try {
        // 4. WRITE TO LOCAL CORE MEMORY
        $stmt = $db->prepare("INSERT INTO transmissions (content, visibility, target_planet, is_remote, author_alias, expiry_date, media_url) VALUES (:content, :visibility, :target, 0, :author, :expiry, :media)");
        $stmt->execute([
            // [ E2E NEW ] Save local ciphertext (locked with own Public Key)
            ':content' => $content_local, 
            ':visibility' => $visibility,
            ':target' => $target_planet,
            ':author' => $author_alias,
            ':expiry' => $expiry_date,
            ':media' => $media_url
        ]);
        
        // 5. ASSEMBLE JSON CAPSULE
        $payload = json_encode([
            // [ E2E NEW ] Send outward (locked with target's Public Key)
            "content" => $content, 
            "author_alias" => $author_alias,
            "from_planet" => $my_planet_url,
            "visibility" => $visibility,
            "expiry_date" => $expiry_date,
            "media_url" => $media_url
        ]);

        // --- [ TRANSMISSION ROUTING ] ---

        if ($visibility === 'public') {
            // [ SCATTER BEAM ] Broadcast to all allies in the Star Chart
            // [ BUG FIX ] Using the correct "following" table
            $query = $db->query("SELECT planet_url FROM following");
            $allies = $query->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($allies) > 0) {
                // Machine Gun (Multi-cURL)
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

        } elseif ($visibility === 'direct') {
            // [ LASER LINK ] Fire specific Direct Message
            if (!empty($target_planet)) {
                $target_url = rtrim($target_planet, '/') . '/api_inbox.php';
                
                // Single cURL execution
                $ch = curl_init($target_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5); 
                curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Mission complete, return to appropriate Radar
        if ($visibility === 'direct') {
            header("Location: ../direct.php?status=transmission_successful");
        } else {
            header("Location: ../console.php?status=transmission_successful");
        }
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ TRANSMISSION FAILED ] Core Memory Error: " . $e->getMessage() . "</h3>");
    }
} else {
    die("<h3 style='color:red;'>[ ERROR ] Invalid Protocol. Use main console.</h3>");
}