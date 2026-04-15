<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
date_default_timezone_set('UTC'); 

// ==========================================
// 🚀 [ V6.2 THE ESCAPE POD: DATABASE EXPORT ]
// ==========================================
if (isset($_GET['escape_pod']) && isset($_SESSION['relay_auth']) && $_SESSION['relay_auth'] === true) {
    $db_file = 'data/relay_core.sqlite';
    if (file_exists($db_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="EscapePod_'.date('Ymd_His').'_relay_core.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($db_file));
        readfile($db_file);
        exit;
    } else {
        die("[ CRITICAL ERROR ] Escape Pod Failed: Core memory not found.");
    }
}

// ==========================================
// 🚀 [ V7.1 THE STATION ARCHIVE: FULL SOURCE & DATA EXPORT ]
// ==========================================
if (isset($_GET['export_station']) && isset($_SESSION['relay_auth']) && $_SESSION['relay_auth'] === true) {
    $zip_filename = 'RelayStation_Backup_' . date('Ymd_His') . '.zip';
    $zip_filepath = sys_get_temp_dir() . '/' . $zip_filename;
    
    $zip = new ZipArchive();
    if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $dir = __DIR__;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($dir) + 1);
                
                // Skip existing zip files to prevent infinite recursion/bloat
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($ext !== 'zip' && $ext !== 'log') {
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }
        $zip->close();
        
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_filepath));
        readfile($zip_filepath);
        @unlink($zip_filepath); // Clean up temp file
        exit;
    } else {
        die("[ CRITICAL ERROR ] Failed to create Station Archive. Ensure ZipArchive PHP extension is enabled.");
    }
}

// ==========================================
// ⚙️ [ AUTO-DETECT SYSTEM VERSION ]
// ==========================================
$station_version = 'UNKNOWN';
if (file_exists('version.json')) {
    $v_data = json_decode(file_get_contents('version.json'), true);
    if (isset($v_data['version'])) {
        $station_version = $v_data['version'];
    }
}

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) & THE ORACLE ]
require_once 'core/db_connect.php';
require_once 'core/telegram.php'; // [ V7.0 ] Inject Telegram Engine

// ==========================================
// 🛡️ ANTI-BRUTE FORCE LOCKOUT PROTOCOL
// ==========================================
$user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$is_locked = false;
$login_error = null;

try {
    $stmt_check_ip = $db->prepare("SELECT attempts, lockout_until FROM login_attempts WHERE ip_address = :ip");
    $stmt_check_ip->execute([':ip' => $user_ip]);
    $ip_status = $stmt_check_ip->fetch(PDO::FETCH_ASSOC);

    if ($ip_status && $ip_status['lockout_until']) {
        $lockout_end = strtotime($ip_status['lockout_until']);
        if (time() < $lockout_end) {
            $is_locked = true;
            $time_left = ceil(($lockout_end - time()) / 60);
            $login_error = "SYSTEM LOCKED. WAIT {$time_left} MINUTE(S).";
        } else {
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $user_ip]);
            $ip_status = false; 
        }
    }

    $stmt = $db->query("SELECT config_value FROM system_config WHERE config_key = 'captain_hash' ORDER BY rowid DESC LIMIT 1");
    $captain_hash = $stmt->fetchColumn();
    $stmt = null; 

} catch (PDOException $e) {
    die("[ CRITICAL ERROR ] Security Protocol Failed: " . $e->getMessage());
}

// ==========================================
// 🔑 [ THE QUANTUM GATE (AJAX AUTH) ]
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_login'])) {
    header('Content-Type: application/json');
    if ($is_locked) {
        echo json_encode(['status' => 'error', 'message' => $login_error]); 
        exit;
    }
    
    if (password_verify($_POST['passcode'], $captain_hash)) {
        if ($ip_status) {
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $user_ip]);
        }
        $_SESSION['relay_auth'] = true;
        
        // 👁️ [ V7.0 THE ORACLE: LOGIN ALERT ]
        sendTelegramAlert("✅ *COMMANDER LOGIN DETECTED*\nAccess granted to Control Room.\nIP Address: `" . $user_ip . "`");

        echo json_encode(['status' => 'success']); 
        exit;
    } else { 
        if ($ip_status) {
            $attempts = $ip_status['attempts'] + 1;
            if ($attempts >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt = $db->prepare("UPDATE login_attempts SET attempts = :attempts, lockout_until = :lock_time WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':lock_time' => $lock_time, ':ip' => $user_ip]);
                
                // 👁️ [ V7.0 THE ORACLE: BRUTE-FORCE ALERT ]
                sendTelegramAlert("🚨 *SECURITY BREACH ATTEMPT*\nRadar frozen for 15 minutes due to multiple failed logins.\nIP Address: `" . $user_ip . "`");

                echo json_encode(['status' => 'error', 'message' => "SYSTEM LOCKED. WAIT 15 MINUTE(S)."]); 
                exit;
            } else {
                $stmt = $db->prepare("UPDATE login_attempts SET attempts = :attempts WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':ip' => $user_ip]);
                echo json_encode(['status' => 'error', 'message' => "ACCESS DENIED. " . (5 - $attempts) . " ATTEMPTS LEFT."]); 
                exit;
            }
        } else {
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (:ip, 1)");
            $stmt->execute([':ip' => $user_ip]);
            echo json_encode(['status' => 'error', 'message' => "ACCESS DENIED. 4 ATTEMPTS LEFT."]); 
            exit;
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); exit;
}

// ==========================================
// 🛡️ [ RENDER LOGIN SHIELD IF UNAUTHENTICATED ]
// ==========================================
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    
    // Pre-fetch Vault for JS Gatekeeper
    $stmt_key = $db->query("SELECT config_value FROM system_config WHERE config_key = 'encrypted_privkey' ORDER BY rowid DESC LIMIT 1");
    $pre_enc_priv = $stmt_key ? $stmt_key->fetchColumn() : '';
    
    $stmt_pub = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key' ORDER BY rowid DESC LIMIT 1");
    $pre_pub_key = $stmt_pub ? $stmt_pub->fetchColumn() : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RESTRICTED - Relay</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
</head>
<body class="t-crt t-center-screen">
    <div class="t-center-box t-card danger mb-0">
        <h2 class="t-card-header t-flicker">> RESTRICTED AREA</h2>
        
        <div id="login-alert" class="t-alert danger text-left mb-3" style="display: <?php echo $login_error ? 'block' : 'none'; ?>;">
            <?php echo htmlspecialchars($login_error ?? ''); ?>
        </div>

        <form id="login-form" class="m-0">
            <div class="t-input-group mb-4">
                <?php if ($is_locked): ?>
                    <input type="password" disabled class="t-input text-center font-bold" placeholder="[ RADAR FROZEN ]" style="letter-spacing: 5px;">
                <?php else: ?>
                    <input type="password" id="loginPass" class="t-input text-center font-bold" placeholder="ENTER PASSCODE" autofocus style="letter-spacing: 5px;" required>
                    <button type="button" class="t-input-action-btn" onclick="Terminal.toggleInputAction('loginPass', this)">[ SHOW ]</button>
                <?php endif; ?>
            </div>
            
            <?php if ($is_locked): ?>
                <button type="button" disabled class="t-btn w-100 font-bold" style="border-color: var(--t-red); color: var(--t-red); opacity: 0.5; cursor: not-allowed;">[ SYSTEM_LOCKED ]</button>
            <?php else: ?>
                <button type="submit" id="login-btn" class="t-btn danger w-100 font-bold t-glow">[ OVERRIDE_SYSTEM ]</button>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        // ==========================================
        // 🔐 THE QUANTUM GATE ENGINE
        // ==========================================
        const serverEncPriv = "<?php echo addslashes($pre_enc_priv); ?>";
        const serverPubKey = "<?php echo addslashes($pre_pub_key); ?>";

        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('login-btn');
            const pass = document.getElementById('loginPass').value;
            const alertBox = document.getElementById('login-alert');
            
            if (!pass) return;
            
            btn.disabled = true;
            btn.innerText = '[ DECRYPTING_GATE... ]';
            if(alertBox) alertBox.style.display = 'none';

            // Attempt Vault Decryption
            if (serverEncPriv) {
                try {
                    const parts = serverEncPriv.split(':');
                    const salt = Uint8Array.from(atob(parts[0]), c => c.charCodeAt(0));
                    const iv = Uint8Array.from(atob(parts[1]), c => c.charCodeAt(0));
                    const cipher = Uint8Array.from(atob(parts[2]), c => c.charCodeAt(0));

                    const enc = new TextEncoder();
                    const keyMaterial = await window.crypto.subtle.importKey(
                        "raw", enc.encode(pass), {name: "PBKDF2"}, false, ["deriveKey"]
                    );
                    
                    const key = await window.crypto.subtle.deriveKey(
                        {name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256"},
                        keyMaterial, {name: "AES-GCM", length: 256}, false, ["decrypt"]
                    );

                    const decrypted = await window.crypto.subtle.decrypt({name: "AES-GCM", iv: iv}, key, cipher);
                    const privPem = new TextDecoder().decode(decrypted);

                    localStorage.setItem('relay_privkey', privPem);
                    localStorage.setItem('relay_pubkey', serverPubKey);

                } catch (err) {
                    btn.disabled = false;
                    btn.innerText = '[ OVERRIDE_SYSTEM ]';
                    alertBox.style.display = 'block';
                    alertBox.innerText = "ACCESS DENIED: INCORRECT PASSCODE";
                    return;
                }
            }

            // Fire AJAX Login
            btn.innerText = '[ AUTHENTICATING... ]';
            const formData = new FormData();
            formData.append('ajax_login', '1');
            formData.append('passcode', pass);

            try {
                const res = await fetch('console.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.status === 'success') {
                    btn.innerText = '[ ACCESS_GRANTED ]';
                    btn.classList.replace('danger', 'success');
                    setTimeout(() => location.reload(), 300);
                } else {
                    btn.disabled = false;
                    btn.innerText = '[ OVERRIDE_SYSTEM ]';
                    alertBox.style.display = 'block';
                    alertBox.innerText = data.message;
                }
            } catch (err) {
                btn.disabled = false;
                btn.innerText = '[ OVERRIDE_SYSTEM ]';
                alertBox.style.display = 'block';
                alertBox.innerText = "CONNECTION INTERRUPTED";
            }
        });
    </script>
</body>
</html>
<?php
    exit;
}

// ==========================================
// 🚀 [ MAIN DASHBOARD PROCESSOR ]
// ==========================================
try {
    // 🌐 [ V6.2 THE NOMADIC RE-SYNC DISPATCHER ]
    if (isset($_POST['action']) && $_POST['action'] === 'nomadic_resync') {
        // 🛡️ [ V7.1 ] Input Sanitization
        $new_url = filter_var(trim($_POST['new_url']), FILTER_SANITIZE_URL);
        $old_url = filter_var(trim($_POST['old_url']), FILTER_SANITIZE_URL);
        
        $db->prepare("DELETE FROM system_config WHERE config_key = 'local_planet_url'")->execute();
        $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('local_planet_url', :val)");
        $stmt->execute([':val' => $new_url]);

        $following = $db->query("SELECT planet_url, handshake_token FROM following WHERE handshake_token IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($following as $node) {
            $payload = json_encode([
                'action' => 'resync',
                'old_url' => $old_url,
                'new_url' => $new_url,
                'handshake_token' => $node['handshake_token']
            ]);
            $ch = curl_init(rtrim($node['planet_url'], '/') . '/api_inbox.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            @curl_exec($ch);
            @curl_close($ch);
        }
        echo "RESYNC_COMPLETE";
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_control_room') {
        // 🛡️ [ V7.1 ] Extreme Input Sanitization
        $name = strip_tags(trim($_POST['station_name'] ?? 'RELAY_STATION'));
        $bio = strip_tags(trim($_POST['station_bio'] ?? ''));
        $bunker = ($_POST['bunker_mode'] === '1') ? '1' : '0';
        $lighthouse = ($_POST['lighthouse_opt'] === '1') ? '1' : '0';
        $new_pass = trim($_POST['station_passcode'] ?? '');
        $enc_priv = strip_tags(trim($_POST['encrypted_privkey'] ?? '')); 
        
        // 👁️ [ V7.0 THE ORACLE: CONFIGS WITH SANITIZATION ]
        $tel_enabled = ($_POST['telegram_enabled'] === '1') ? '1' : '0';
        $tel_token = strip_tags(trim($_POST['telegram_bot_token'] ?? ''));
        $tel_chat = strip_tags(trim($_POST['telegram_chat_id'] ?? ''));

        $db->prepare("DELETE FROM system_config WHERE config_key IN ('station_name', 'station_bio', 'bunker_mode', 'lighthouse_opt', 'telegram_enabled', 'telegram_bot_token', 'telegram_chat_id')")->execute();
        
        $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?)");
        $stmt->execute(['station_name', $name]);
        $stmt->execute(['station_bio', $bio]);
        $stmt->execute(['bunker_mode', $bunker]);
        $stmt->execute(['lighthouse_opt', $lighthouse]);
        $stmt->execute(['telegram_enabled', $tel_enabled]);
        $stmt->execute(['telegram_bot_token', $tel_token]);
        $stmt->execute(['telegram_chat_id', $tel_chat]);

        if (!empty($new_pass)) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare("DELETE FROM system_config WHERE config_key = 'captain_hash'")->execute();
            $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('captain_hash', ?)")->execute([$hash]);
        }
        
        if (!empty($enc_priv)) {
            $db->prepare("DELETE FROM system_config WHERE config_key = 'encrypted_privkey'")->execute();
            $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('encrypted_privkey', ?)")->execute([$enc_priv]);
        }

        // 👁️ [ V7.0 THE ORACLE: TEST PING ]
        if ($tel_enabled === '1') {
            sendTelegramAlert("> ORACLE SYSTEM ONLINE. Radar is active.");
        }

        // 🗼 THE LIGHTHOUSE FIRE PROTOCOL (PHP Side)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $base_path = dirname($_SERVER['SCRIPT_NAME']);
        if ($base_path === '\\' || $base_path === '/') $base_path = '';
        $my_planet_url = rtrim($protocol . $host . $base_path, '/');
        
        $ping_data = '';
        if ($lighthouse === '1') {
            $ping_data = json_encode([
                'action' => 'ping',
                'planet_url' => $my_planet_url,
                'station_name' => $name,
                'station_bio' => $bio
            ]);
        } else {
            $ping_data = json_encode([
                'action' => 'kill',
                'planet_url' => $my_planet_url
            ]);
        }
        
        $ch = curl_init('https://relay.emptyhub.my.id/api_register.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $ping_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        @curl_exec($ch);
        @curl_close($ch);

        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_keys') {
        // 🛡️ [ V7.1 ] Input Sanitization
        $pubkey = strip_tags(trim($_POST['public_key'] ?? ''));
        $enc_priv = strip_tags(trim($_POST['encrypted_privkey'] ?? ''));
        
        if (!empty($pubkey)) {
            $db->prepare("DELETE FROM system_config WHERE config_key = 'public_key'")->execute();
            $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('public_key', :val)");
            $stmt->execute([':val' => $pubkey]);
        }
        
        if (!empty($enc_priv)) {
            $db->prepare("DELETE FROM system_config WHERE config_key = 'encrypted_privkey'")->execute();
            $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('encrypted_privkey', :val)");
            $stmt->execute([':val' => $enc_priv]);
        }
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete_local') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM transmissions WHERE id = ? AND is_remote = 0")->execute([$id]);
        exit;
    }
    
    $stmt_ghost = $db->query("SELECT media_url FROM transmissions WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_TIMESTAMP AND media_url IS NOT NULL");
    $expired_ghosts = $stmt_ghost->fetchAll(PDO::FETCH_COLUMN);
    foreach ($expired_ghosts as $ghost_img) {
        $img_path = 'media/' . basename($ghost_img);
        if (file_exists($img_path)) { @unlink($img_path); }
    }
    $db->exec("DELETE FROM transmissions WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_TIMESTAMP");
    
    $media_files = glob('media/*');
    if ($media_files) {
        $active_media = $db->query("SELECT media_url FROM transmissions WHERE media_url IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $active_filenames = array_map('basename', $active_media);
        $protected_files = ['.htaccess', 'index.php', 'index.html', 'robots.txt']; 
        
        $physical_files = [];
        foreach ($media_files as $file) { if (is_file($file)) { $physical_files[] = basename($file); } }
        $orphans = array_diff($physical_files, $active_filenames);
        foreach ($orphans as $orphan) { 
            if (!in_array($orphan, $protected_files)) { @unlink('media/' . $orphan); }
        }
    }
    $db->exec("DELETE FROM transmissions WHERE visibility = 'public' AND is_remote = 1 AND timestamp <= datetime('now', '-30 days')");

    if (isset($_GET['last_id'])) {
        $last_id = (int)$_GET['last_id'];
        $stmt = $db->prepare("SELECT * FROM transmissions WHERE visibility = 'public' AND id < :last_id ORDER BY id DESC LIMIT 15");
        $stmt->execute([':last_id' => $last_id]);
        $transmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transmissions as $msg) {
            $author = htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN');
            $ghost = !empty($msg['expiry_date']) ? '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>' : '';
            $src = $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:';
            $content = nl2br(htmlspecialchars($msg['content']));
            
            $img = '';
            if (!empty($msg['media_url'])) {
                $media_items = [];
                if (strpos($msg['media_url'], '[') === 0) {
                    $media_items = json_decode($msg['media_url'], true) ?? [];
                } else {
                    $media_items = [$msg['media_url']];
                }
                
                $m_count = count($media_items);
                if ($m_count > 0) {
                    $matrix_class = 'media-matrix-' . min($m_count, 4);
                    $img = '<div class="media-matrix ' . $matrix_class . '">';
                    foreach(array_slice($media_items, 0, 4) as $url) {
                        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                        $is_audio = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a']);
                        $is_video = in_array($ext, ['mp4']);
                        
                        if ($is_audio) {
                            $img .= '<div class="matrix-item audio-cell p-2"><button type="button" class="t-btn warning w-100 audio-play-btn" data-src="'.htmlspecialchars($url).'" style="font-size: 11px;">[ ▶️ PLAY AUDIO_LOG ]</button></div>';
                        } elseif ($is_video) {
                            $img .= '<div class="matrix-item"><video class="matrix-video" controls preload="metadata"><source src="'.htmlspecialchars($url).'" type="video/mp4"></video></div>';
                        } else {
                            $img .= '<div class="matrix-item"><img src="'.htmlspecialchars($url).'" class="matrix-img" alt="Secure Media"></div>';
                        }
                    }
                    $img .= '</div>';
                }
            }
            
            $purge_btn = ($msg['is_remote'] == 0) ? "<button type='button' onclick='globalPurge(this, {$msg['id']})' class='t-btn danger t-btn-sm font-bold' style='padding: 1px 5px; font-size: 9px; line-height: 1;' title='Wipe Local & Allies Timeline'>[ 🔥 PURGE ]</button>" : '';

            echo "<div class='t-card mb-3 p-3 transmission-card' data-id='{$msg['id']}' data-raw-content='".htmlspecialchars($msg['content'], ENT_QUOTES)."'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap gap-2'>
                        <span>[ {$msg['timestamp']} UTC ] $src <strong class='text-success'>$author</strong> $ghost</span>
                        <div class='d-flex gap-2'>
                            <button type='button' onclick=\"quoteTimeline(this, '{$author}')\" class='t-btn t-btn-sm' style='padding: 1px 5px; font-size: 9px; line-height: 1; border-color: var(--t-green-dim); color: var(--t-green-dim);'>[ 💬 QUOTE ]</button>
                            $purge_btn
                        </div>
                    </div>
                    <p class='m-0 timeline-msg' style='font-size: 14px;'>$content</p> $img
                  </div>";
        }
        exit;
    }
    
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' ORDER BY id DESC LIMIT 15");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

    $stmt_alerts = $db->query("SELECT * FROM alerts WHERE is_read = 0 ORDER BY id DESC");
    $active_alerts = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    // 🎛️ FETCH SYSTEM CONFIGURATIONS
    $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode' ORDER BY rowid DESC LIMIT 1");
    $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';

    $stmt_lh = $db->query("SELECT config_value FROM system_config WHERE config_key = 'lighthouse_opt' ORDER BY rowid DESC LIMIT 1");
    $lighthouse_opt = $stmt_lh ? $stmt_lh->fetchColumn() : '0';

    $stmt_name = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_name' ORDER BY rowid DESC LIMIT 1");
    $station_name = $stmt_name->fetchColumn() ?: 'RELAY_STATION';

    $stmt_bio = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_bio' ORDER BY rowid DESC LIMIT 1");
    $station_bio = $stmt_bio->fetchColumn() ?: '';

    // 🔑 FETCH IDENTITY KEYS FOR VAULT SYNC
    $stmt_key = $db->query("SELECT config_value FROM system_config WHERE config_key = 'encrypted_privkey' ORDER BY rowid DESC LIMIT 1");
    $encrypted_privkey = $stmt_key ? $stmt_key->fetchColumn() : null;

    $stmt_pub = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key' ORDER BY rowid DESC LIMIT 1");
    $server_pubkey = $stmt_pub ? $stmt_pub->fetchColumn() : null;

    // 👁️ [ V7.0 THE ORACLE: FETCH CONFIGS ]
    $stmt_tel_en = $db->query("SELECT config_value FROM system_config WHERE config_key = 'telegram_enabled' ORDER BY rowid DESC LIMIT 1");
    $telegram_enabled = $stmt_tel_en ? $stmt_tel_en->fetchColumn() : '0';

    $stmt_tel_tok = $db->query("SELECT config_value FROM system_config WHERE config_key = 'telegram_bot_token' ORDER BY rowid DESC LIMIT 1");
    $telegram_bot_token = $stmt_tel_tok ? $stmt_tel_tok->fetchColumn() : '';

    $stmt_tel_chat = $db->query("SELECT config_value FROM system_config WHERE config_key = 'telegram_chat_id' ORDER BY rowid DESC LIMIT 1");
    $telegram_chat_id = $stmt_tel_chat ? $stmt_tel_chat->fetchColumn() : '';

    // 🌐 [ V6.2 ] THE NOMADIC RE-SYNC RADAR (DETECT DOMAIN CHANGE)
    $stmt_local = $db->query("SELECT config_value FROM system_config WHERE config_key = 'local_planet_url' ORDER BY rowid DESC LIMIT 1");
    $stored_local_url = $stmt_local ? $stmt_local->fetchColumn() : '';
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($base_path === '\\' || $base_path === '/') $base_path = '';
    $current_local_url = rtrim($protocol . $host . $base_path, '/');

    $trigger_nomadic_resync = false;
    if (!empty($stored_local_url) && $stored_local_url !== $current_local_url) {
        $trigger_nomadic_resync = true;
        $old_planet_url = $stored_local_url;
    }

} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ CRITICAL ERROR ] Core Memory Data Fetch Failed.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Public Timeline</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
    <link rel="manifest" href="manifest.json">
    <style>
        #installAppBtn { display: none; }
        #ptt-btn { user-select: none; -webkit-user-select: none; touch-action: manipulation; }
        
        .media-matrix { display: grid; gap: 8px; margin-top: 10px; }
        .media-matrix-1 { grid-template-columns: 1fr; }
        .media-matrix-2 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 .matrix-item:first-child { grid-column: span 2; }
        .media-matrix-4 { grid-template-columns: 1fr 1fr; }
        
        .matrix-item { position: relative; overflow: hidden; border-radius: 4px; border: 1px dashed var(--t-green); background: var(--bg-base); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
        .matrix-item.audio-cell { aspect-ratio: auto; min-height: 50px; border-style: dotted; border-color: var(--t-yellow); }
        
        .matrix-img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s; }
        .matrix-img:hover { filter: none; }
        
        .matrix-video { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s; }
        .matrix-video:hover, .matrix-video:focus, .matrix-video:active { filter: none; outline: none; }
    </style>
</head>
<body class="t-crt">

    <div id="splash-overlay" class="t-splash">
        <div class="font-bold text-success" id="splash-text" style="font-size: 1.1rem; letter-spacing: 2px; text-shadow: 0 0 8px currentColor;">
            > MOUNTING_PUBLIC_TIMELINE<span class="t-loading-dots"></span>
        </div>
    </div>

    <?php if ($trigger_nomadic_resync): ?>
    <div id="nomadic-resync-modal" class="t-splash" style="z-index: 9999; background: rgba(0,0,0,0.95); flex-direction: column; justify-content: center; align-items: center; display: flex;">
        <div class="t-card warning text-center" style="width: 90%; max-width: 450px; border-color: var(--t-yellow);">
            <h2 class="text-warning font-bold t-blink">> THE NOMADIC PROTOCOL ACTIVATED</h2>
            <p class="text-muted fs-small mt-3">> Domain change detected.<br>Old: <strong><?php echo htmlspecialchars($old_planet_url); ?></strong><br>New: <strong class="text-success"><?php echo htmlspecialchars($current_local_url); ?></strong></p>
            <p class="text-muted fs-small">> Firing Re-Sync Pulse to all allied nodes to update their Star Charts automatically.</p>
            <div class="text-warning mt-3 mb-2">[ FIRING PULSE... ] <span class="t-loading-dots"></span></div>
        </div>
    </div>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const formData = new FormData();
            formData.append('action', 'nomadic_resync');
            formData.append('old_url', '<?php echo addslashes($old_planet_url); ?>');
            formData.append('new_url', '<?php echo addslashes($current_local_url); ?>');
            
            fetch('console.php', { method: 'POST', body: formData }).then(r => r.text()).then(res => {
                const splashText = document.querySelector('#nomadic-resync-modal .text-warning.mt-3');
                splashText.innerHTML = '[ ✓ RE-SYNC COMPLETE ]';
                splashText.classList.replace('text-warning', 'text-success');
                setTimeout(() => { document.getElementById('nomadic-resync-modal').style.display = 'none'; }, 2000);
            });
        });
    </script>
    <?php endif; ?>

    <div id="vault-setup-modal" class="t-splash" style="display:none; z-index: 2000; background: rgba(0,0,0,0.9); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card success" style="width: 90%; max-width: 400px; border-color: var(--t-green);">
            <div class="t-card-header text-success font-bold">> 🔐 IDENTITY VAULT SETUP</div>
            <div class="p-3 text-center">
                <p class="fs-small text-muted mb-3">> Your E2E keys are active but NOT backed up. To enable multi-device sync, enter your Master Passcode to lock and backup your keys to the server vault.</p>
                <input type="password" id="vault-setup-passcode" class="t-input text-center font-bold mb-3" placeholder="MASTER PASSCODE">
                <button onclick="triggerVaultSetup()" class="t-btn success w-100 font-bold t-glow">[ SECURE IDENTITY ]</button>
            </div>
        </div>
    </div>

    <div id="split-brain-modal" class="t-splash" style="display:none; z-index: 2000; background: rgba(0,0,0,0.9); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card danger" style="width: 90%; max-width: 400px; border-color: var(--t-red);">
            <div class="t-card-header text-danger font-bold">> 🚨 CRITICAL SYNC ERROR</div>
            <div class="p-3 text-center">
                <p class="fs-small text-muted mb-3">> Another device claims this node's identity but failed to backup the Vault. <br><br><strong>Action Required:</strong> Go to the device that created the keys and setup the vault. OR, permanently overwrite the server identity here.</p>
                <button onclick="forceRebuildIdentity()" class="t-btn danger w-100 font-bold t-glow">[ DESTROY & REBUILD KEYS ]</button>
            </div>
        </div>
    </div>

    <div class="t-container-fluid pt-0">
        <nav class="t-navbar mt-3 mb-4">
            <div class="t-nav-brand">
                <span class="t-led-dot t-led-green"></span> <?php echo htmlspecialchars($station_name); ?> 
                <span class="fs-small text-muted fw-normal ml-2">v<?php echo htmlspecialchars($station_version); ?></span>
                <?php if (count($active_alerts) > 0): ?>
                    <span id="nav-alert-counter" class="text-warning t-blink ml-2 fw-normal" style="font-size:12px;">[ 🔔 <?php echo count($active_alerts); ?> NEW ]</span>
                <?php endif; ?>
            </div>
            <div class="t-nav-menu">
                <button onclick="document.getElementById('control-room-modal').style.display='flex';" class="t-btn t-btn-sm" title="Configure Station">[ ⚙️ ]</button>
                <button id="installAppBtn" class="t-btn t-btn-sm" title="Install PWA">[ 📥 ]</button>
                <a href="core/updater.php" class="t-btn warning t-btn-sm" title="Check System Update">[ 🔄 ]</a>
                <a href="console.php?logout=true" class="t-btn danger t-btn-sm" title="Logout">[ ➜] ]</a>
            </div>
        </nav>

        <div class="t-grid-layout">
            
            <main class="t-main-panel">
                <h2 class="t-card-header">> 🌐 PUBLIC TIMELINE</h2>
                
                <div class="t-card">
                    <form action="core/transmitter.php" method="POST" enctype="multipart/form-data" class="m-0" id="broadcast-form">
                        <input type="hidden" name="visibility" value="public">
                        <input type="hidden" name="media_base64" id="media-base64">
                        <input type="hidden" name="audio_base64" id="audio-base64">
                        
                        <textarea name="content" rows="3" class="t-textarea" placeholder="> What's happening in your sector?"></textarea>

                        <div class="mb-3 mt-2 d-flex align-items-center gap-2 flex-wrap">
                            <input type="file" id="media-input" name="media[]" accept="image/*,video/mp4,audio/*" multiple style="display: none;">
                            <button type="button" class="t-btn t-btn-sm" onclick="document.getElementById('media-input').click();" style="white-space: nowrap;">[ ATTACH_FILE ]</button>
                            <button type="button" class="t-btn danger t-btn-sm font-bold" id="ptt-btn" style="white-space: nowrap;" title="Hold to record broadcast">[ 🎙️ HOLD_TO_TALK ]</button>
                            <span id="file-name-display" class="fs-small text-muted" style="flex-grow:1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">> NO_MEDIA</span>
                            <span id="compress-status" class="fs-small text-warning font-bold"></span>
                        </div>

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <label class="t-checkbox-label text-danger m-0">
                                <input type="checkbox" name="ghost_protocol" value="1"><span class="t-checkmark"></span> [!] GHOST PROTOCOL (24H)
                            </label>
                            <button type="submit" class="t-btn font-bold t-glow">[ BROADCAST ]</button>
                        </div>
                    </form>
                </div>

                <div id="signal-log">
                    <?php if (empty($transmissions)): ?>
                        <div class="text-center text-muted py-4 t-border border-dashed">[ TIMELINE IS EMPTY ]</div>
                    <?php else: ?>
                        <?php foreach ($transmissions as $msg): 
                            $is_me = ($msg['is_remote'] == 0);
                            $author_disp = htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN');
                        ?>
                            <div class="t-card mb-3 p-3 transmission-card" data-id="<?php echo $msg['id']; ?>" data-raw-content="<?php echo htmlspecialchars($msg['content'], ENT_QUOTES); ?>">
                                <div class="t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap gap-2">
                                    <span>
                                        [ <?php echo $msg['timestamp']; ?> UTC ] 
                                        <?php echo $is_me ? 'LOCAL_AUTHOR:' : 'INCOMING FROM:'; ?> 
                                        <strong class="text-success"><?php echo $author_disp; ?></strong>
                                        <?php if(!empty($msg['expiry_date'])) echo '<span class="t-badge danger t-flicker ml-2">[ 👻 GHOSTED ]</span>'; ?>
                                    </span>
                                    <div class="d-flex gap-2">
                                        <button type="button" onclick="quoteTimeline(this, '<?php echo $author_disp; ?>')" class="t-btn t-btn-sm" style="padding: 1px 5px; font-size: 9px; line-height: 1; border-color: var(--t-green-dim); color: var(--t-green-dim);">[ 💬 QUOTE ]</button>
                                        <?php if($is_me): ?>
                                            <button type="button" onclick="globalPurge(this, <?php echo $msg['id']; ?>)" class="t-btn danger t-btn-sm font-bold" style="padding: 1px 5px; font-size: 9px; line-height: 1;" title="Wipe Local & Allies Timeline">[ 🔥 PURGE ]</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p class="m-0 timeline-msg" style="font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </p>
                                
                                <?php if(!empty($msg['media_url'])): 
                                    $media_items = [];
                                    if (strpos($msg['media_url'], '[') === 0) {
                                        $media_items = json_decode($msg['media_url'], true) ?? [];
                                    } else {
                                        $media_items = [$msg['media_url']];
                                    }
                                    
                                    $m_count = count($media_items);
                                    if ($m_count > 0):
                                ?>
                                    <div class="media-matrix media-matrix-<?php echo min($m_count, 4); ?>">
                                        <?php foreach(array_slice($media_items, 0, 4) as $url): 
                                            $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                                            $is_audio = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a']);
                                            $is_video = in_array($ext, ['mp4']);
                                        ?>
                                            <?php if($is_audio): ?>
                                                <div class="matrix-item audio-cell p-2">
                                                    <button type="button" class="t-btn warning w-100 audio-play-btn" data-src="<?php echo htmlspecialchars($url); ?>" style="font-size: 11px;">
                                                        [ ▶️ PLAY AUDIO_LOG ]
                                                    </button>
                                                </div>
                                            <?php elseif($is_video): ?>
                                                <div class="matrix-item">
                                                    <video class="matrix-video" controls preload="metadata">
                                                        <source src="<?php echo htmlspecialchars($url); ?>" type="video/mp4">
                                                    </video>
                                                </div>
                                            <?php else: ?>
                                                <div class="matrix-item">
                                                    <img src="<?php echo htmlspecialchars($url); ?>" class="matrix-img" alt="Transmission Media">
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($transmissions)): ?>
                    <div id="load-more" class="text-center mt-3 text-muted" style="border-top:1px dashed var(--t-green); padding-top:15px; padding-bottom:30px;">
                        [ SCROLL DOWN TO SCAN DEEP SPACE ]
                    </div>
                <?php endif; ?>

            </main>

            <aside class="t-side-panel">
                <h2 class="t-card-header">> 🧭 NAVIGATION</h2>
                <div class="t-list-group mb-4">
                    <a href="console.php" class="t-list-item active">
                        <span class="t-list-item-title">> 🌐 TIMELINE</span>
                    </a>
                    <a href="direct.php" class="t-list-item">
                        <span class="t-list-item-title">> ✉️ DIRECT MESSAGES</span>
                    </a>
                    <a href="index.php" target="_blank" class="t-list-item">
                        <span class="t-list-item-title fw-normal">> 👁️ PUBLIC HOLOGRAM</span>
                    </a>
                </div>

                <?php if (!empty($active_alerts)): ?>
                    <h2 id="sidebar-alert-header" class="t-card-header text-warning t-blink">> 🔔 ALERTS (<span id="sidebar-alert-counter"><?php echo count($active_alerts); ?></span>)</h2>
                    <div id="sidebar-alert-list" class="t-list-group mb-4">
                        <?php foreach ($active_alerts as $alert): ?>
                            <div class="t-card p-2 mb-2" style="border-color: var(--t-yellow); background: rgba(255,255,0,0.05);">
                                <?php if ($alert['type'] == 'new_follower'): ?>
                                    <div class="fs-small text-warning mb-2">> PING DETECTED: <br><strong style="word-break: break-all;"><?php echo htmlspecialchars($alert['from_planet']); ?></strong></div>
                                    <div class="d-flex gap-2">
                                        <button onclick="acceptHandshake('<?php echo htmlspecialchars($alert['from_planet']); ?>', <?php echo $alert['id']; ?>)" class="t-btn warning w-100" style="padding:4px; font-size:11px;">[ FOLLOW BACK ]</button>
                                        <a href="core/alert_action.php?id=<?php echo $alert['id']; ?>" class="t-btn danger w-100 text-center" style="padding:4px; font-size:11px; text-decoration:none;">[ IGNORE ]</a>
                                    </div>
                                <?php elseif ($alert['type'] == 'new_dm'): ?>
                                    <div class="fs-small text-success mb-2">> ✉️ INCOMING LASER LINK: <br><strong style="word-break: break-all;"><?php echo htmlspecialchars($alert['from_planet']); ?></strong></div>
                                    <div class="d-flex gap-2">
                                        <a href="core/alert_action.php?id=<?php echo $alert['id']; ?>&redirect=direct" class="t-btn w-100 text-center" style="padding:4px; font-size:11px; text-decoration:none; border-color: var(--t-green); color: var(--t-green);">[ READ MESSAGE ]</a>
                                    </div>
                                <?php elseif ($alert['type'] == 'sonar_pulse'): ?>
                                    <div class="fs-small text-danger t-blink mb-2">> 📡 TACTICAL SONAR DETECTED: <br><strong style="word-break: break-all;"><?php echo htmlspecialchars($alert['from_planet']); ?></strong></div>
                                    <div class="d-flex flex-column gap-2 text-center">
                                        <div id="sonar-display-<?php echo $alert['id']; ?>" class="text-warning font-bold mb-1" style="letter-spacing: 5px; min-height: 18px; font-size: 14px;"></div>
                                        <button onclick="decodeSonar('<?php echo htmlspecialchars($alert['payload'] ?? 'PING'); ?>', <?php echo $alert['id']; ?>)" id="btn-sonar-<?php echo $alert['id']; ?>" class="t-btn danger w-100" style="padding:4px; font-size:11px;">[ 📻 DECODE SIGNAL ]</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="t-card-header d-flex justify-content-between align-items-center">
                    > 🗺️ STAR_CHART
                    <button onclick="runRadarSweep()" id="btn-sweep" class="t-btn warning t-btn-sm" title="Ping All Nodes">[ 📡 ]</button>
                </h2>
                <div class="t-card p-2 mb-3 mt-3">
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                        <div class="t-alert danger p-2 mb-2 fs-small">[!] SIGNAL LOST: Invalid Node.</div>
                    <?php endif; ?>
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'empty_url'): ?>
                        <div class="t-alert danger p-2 mb-2 fs-small">[!] ERROR: Empty Coordinates.</div>
                    <?php endif; ?>
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'self_node'): ?>
                        <div class="t-alert warning p-2 mb-2 fs-small">[!] RADAR REJECTED: Cannot target your own node.</div>
                    <?php endif; ?>
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                        <div class="t-alert p-2 mb-2 fs-small" style="border-color: var(--t-green);">[✓] NODE LOCKED.</div>
                    <?php endif; ?>
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'node_removed'): ?>
                        <div class="t-alert warning p-2 mb-2 fs-small">[!] NODE DISCONNECTED.</div>
                    <?php endif; ?>

                    <form action="core/add_planet.php" method="POST" class="m-0" id="follow-form">
                        <input type="hidden" name="handshake_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                        <input type="url" name="planet_url" id="target-planet-input" class="t-input mb-2" placeholder="https://domain.com" required>
                        <button type="submit" class="t-btn w-100 t-btn-sm">[ FOLLOW NODE ]</button>
                    </form>
                </div>

                <div class="t-list-group">
                    <?php if (empty($star_chart)): ?>
                        <div class="t-list-item"><span class="t-list-item-subtitle text-center">[ NO NODES FOLLOWED ]</span></div>
                    <?php else: ?>
                        <?php foreach ($star_chart as $star): 
                            $clean_alias = trim(preg_replace('/\[v\d+(\.\d+)*\]/i', '', $star['alias']));
                        ?>
                            <div class="t-list-item" style="cursor: default;">
                                <div style="overflow: hidden; margin-bottom: 8px;">
                                    <span class="t-list-item-title"><?php echo htmlspecialchars($clean_alias); ?></span>
                                    <span class="t-list-item-subtitle text-success" style="word-break: break-all;"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button onclick="openSonarModal('<?php echo htmlspecialchars($star['planet_url']); ?>', '<?php echo htmlspecialchars($clean_alias); ?>')" class="t-btn warning t-btn-sm flex-fill text-center" style="padding: 4px; font-size: 11px;">[ 📡 SONAR ]</button>
                                    <a href="core/remove_planet.php?id=<?php echo $star['id']; ?>" class="t-btn danger t-btn-sm flex-fill text-center" style="padding: 4px; font-size: 11px; text-decoration: none;" onclick="return confirm('> WARNING: Disconnect from this node?');">[ ❌ DISCONNECT ]</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>

    <div id="control-room-modal" class="t-splash" style="display:none; z-index: 1000; background: rgba(0,0,0,0.85); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card" style="width: 90%; max-width: 500px; border-color: var(--t-green);">
            <div class="t-card-header d-flex justify-content-between align-items-center">
                <span class="font-bold text-success">> 🎛️ THE_CONTROL_ROOM</span>
                <button type="button" onclick="document.getElementById('control-room-modal').style.display='none';" class="t-btn danger t-btn-sm" style="padding: 2px 8px;">[ X ]</button>
            </div>
            <div class="p-3" style="max-height: 80vh; overflow-y: auto;">
                <form id="control-room-form" class="m-0">
                    <div class="mb-3">
                        <label class="t-form-label">> STATION_NAME</label>
                        <input type="text" id="cr-name" class="t-input font-bold text-success" value="<?php echo htmlspecialchars($station_name); ?>" maxlength="30" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="t-form-label">> STATION_BIO / BROADCAST_MESSAGE</label>
                        <textarea id="cr-bio" class="t-textarea" rows="3" maxlength="160" placeholder="> Enter public station description..."><?php echo htmlspecialchars($station_bio); ?></textarea>
                    </div>
                    
                    <div class="mb-3 t-card p-2" style="border-color: var(--t-red); background: rgba(255,0,0,0.05);">
                        <span class="font-bold text-danger">> PRIVATE NODE / BUNKER MODE</span>
                        <div class="mt-2 fs-small text-muted">
                            > Seal Hologram from public. Manual follower approval. 
                            <input type="checkbox" id="cr-bunker" value="1" <?php echo ($bunker_mode == '1') ? 'checked' : ''; ?> style="vertical-align: middle; cursor: pointer; margin-left: 5px;">
                        </div>
                    </div>

                    <div class="mb-3 t-card p-2" style="border-color: var(--t-green); background: rgba(0,255,65,0.05);">
                        <span class="font-bold text-success">> THE LIGHTHOUSE PROTOCOL</span>
                        <div class="mt-2 fs-small text-muted">
                            > Transmit station signal to public directory (Opt-In). 
                            <input type="checkbox" id="cr-lighthouse" value="1" <?php echo ($lighthouse_opt == '1') ? 'checked' : ''; ?> style="vertical-align: middle; cursor: pointer; margin-left: 5px;">
                        </div>
                    </div>

                    <div class="mb-3 t-card p-2" style="border-color: var(--t-green); background: rgba(0,255,65,0.05);">
                        <span class="font-bold text-success">> THE ESCAPE POD (DATA PORTABILITY)</span>
                        <div class="mt-2 fs-small text-muted">
                            > Download your core memory before migrating to a new domain. Restore it later to activate the <strong>Token Re-Sync Protocol</strong>.
                            <a href="console.php?escape_pod=true" class="t-btn success t-btn-sm w-100 mt-2 text-center" style="text-decoration:none; display:block;">[ 📥 EXPORT CORE DATABASE ]</a>
                            <a href="console.php?export_station=true" class="t-btn warning t-btn-sm w-100 mt-2 text-center font-bold" style="text-decoration:none; display:block;">[ 📦 BACKUP WHOLE STATION (ZIP) ]</a>
                        </div>
                    </div>

                    <div class="mb-3 t-card p-2" style="border-color: var(--t-green); background: rgba(0,255,65,0.05);">
                        <span class="font-bold text-success">> THE ORACLE: TELEGRAM BOT</span>
                        <div class="mt-2 fs-small text-muted">
                            > Enable real-time radar alerts to your smartphone. Read `TELEGRAM.md` for the setup guide.
                            <input type="checkbox" id="cr-telegram-enabled" value="1" <?php echo ($telegram_enabled == '1') ? 'checked' : ''; ?> style="vertical-align: middle; cursor: pointer; margin-left: 5px;">
                        </div>
                        <div id="telegram-settings" style="display: <?php echo ($telegram_enabled == '1') ? 'block' : 'none'; ?>; margin-top: 15px;">
                            <label class="t-form-label fs-small">> BOT_TOKEN</label>
                            <input type="text" id="cr-telegram-token" class="t-input mb-3" style="font-size: 11px;" value="<?php echo htmlspecialchars($telegram_bot_token); ?>" placeholder="1234567890:ABCDefGhIjKlMnOpQrStUvWxYz">
                            
                            <label class="t-form-label fs-small">> CHAT_ID</label>
                            <input type="text" id="cr-telegram-chatid" class="t-input" style="font-size: 11px;" value="<?php echo htmlspecialchars($telegram_chat_id); ?>" placeholder="123456789">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="t-form-label">> CHANGE_MASTER_PASSCODE</label>
                        <input type="password" id="cr-passcode" class="t-input" placeholder="> Leave blank to keep current passcode...">
                    </div>

                    <button type="submit" class="t-btn warning w-100 font-bold t-glow">[ APPLY_CONFIGURATION ]</button>
                </form>
            </div>
        </div>
    </div>

    <div id="sonar-pulse-modal" class="t-splash" style="display:none; z-index: 1000; background: rgba(0,0,0,0.85); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card" style="width: 90%; max-width: 400px; border-color: var(--t-yellow);">
            <div class="t-card-header d-flex justify-content-between align-items-center text-warning">
                <span class="font-bold">> 📡 TACTICAL_SONAR_PULSE</span>
                <button type="button" onclick="document.getElementById('sonar-pulse-modal').style.display='none';" class="t-btn danger t-btn-sm" style="padding: 2px 8px;">[ X ]</button>
            </div>
            <div class="p-3 text-center">
                <div class="text-muted fs-small mb-3">> TARGET: <strong id="sonar-target-display" class="text-success"></strong></div>
                <form id="sonar-form" action="core/transmitter.php" method="POST" class="m-0">
                    <input type="hidden" name="visibility" value="sonar_pulse">
                    <input type="hidden" name="target_planet" id="sonar-target-input">
                    <div class="mb-3">
                        <input type="text" id="cr-sonar-code" name="content" class="t-input font-bold text-warning text-center" placeholder="ENTER SHORT CODE" maxlength="15" required style="letter-spacing: 3px; font-size: 1.2rem;">
                        <div class="text-muted fs-small mt-1">> ALPHANUMERIC ONLY. MAX 15 CHARACTERS.</div>
                    </div>
                    <button type="submit" class="t-btn warning w-100 font-bold t-glow" onclick="Terminal.splash.show('> TRANSMITTING SONAR...');">[ FIRE_PULSE ]</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        // ==========================================
        // 👁️ [ V7.0 THE ORACLE: UI TOGGLE ]
        // ==========================================
        const telCheckbox = document.getElementById('cr-telegram-enabled');
        const telSettings = document.getElementById('telegram-settings');
        if(telCheckbox && telSettings) {
            telCheckbox.addEventListener('change', function() {
                telSettings.style.display = this.checked ? 'block' : 'none';
            });
        }

        // ==========================================
        // 🔐 [ V5.6 THE ENCRYPTED KEY VAULT (ENGINE & DECRYPTOR) ]
        // ==========================================
        async function encryptVault(privPem, passcode) {
            const enc = new TextEncoder();
            const keyMaterial = await window.crypto.subtle.importKey(
                "raw", enc.encode(passcode), {name: "PBKDF2"}, false, ["deriveKey"]
            );
            const salt = window.crypto.getRandomValues(new Uint8Array(16));
            const key = await window.crypto.subtle.deriveKey(
                {name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256"},
                keyMaterial, {name: "AES-GCM", length: 256}, false, ["encrypt"]
            );
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const cipher = await window.crypto.subtle.encrypt({name: "AES-GCM", iv: iv}, key, new TextEncoder().encode(privPem));
            
            const saltB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(salt)));
            const ivB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(iv)));
            const cipherB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipher)));
            return `${saltB64}:${ivB64}:${cipherB64}`;
        }

        async function triggerVaultSetup() {
            const pc = document.getElementById('vault-setup-passcode').value;
            if (!pc) return;
            document.getElementById('vault-setup-passcode').disabled = true;

            const privPem = localStorage.getItem('relay_privkey');
            const pubPem = localStorage.getItem('relay_pubkey');
            if (!privPem) return;

            try {
                const encPriv = await encryptVault(privPem, pc);
                const formData = new FormData();
                formData.append('action', 'save_keys');
                formData.append('public_key', pubPem);
                formData.append('encrypted_privkey', encPriv);

                await fetch('console.php', { method: 'POST', body: formData });
                document.getElementById('vault-setup-modal').style.display = 'none';
                Terminal.toast('[✓] IDENTITY VAULT SECURED', 'success');
            } catch(e) {
                document.getElementById('vault-setup-passcode').disabled = false;
                Terminal.toast('[!] VAULT ENCRYPTION FAILED', 'danger');
            }
        }

        async function forceRebuildIdentity() {
            if (confirm("> WARNING: This will permanently destroy the current identity on the server and create a new one. All previous encrypted messages will become unreadable. Proceed?")) {
                document.getElementById('split-brain-modal').style.display = 'none';
                await forgeQuantumKeys();
                document.getElementById('vault-setup-modal').style.display = 'flex';
            }
        }

        async function forgeQuantumKeys() {
            Terminal.splash.show('> FORGING_QUANTUM_KEYS...');
            try {
                const keyPair = await window.crypto.subtle.generateKey(
                    { name: "RSA-OAEP", modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: "SHA-256" },
                    true, ["encrypt", "decrypt"]
                );

                const exportKey = async (key, type) => {
                    const exported = await window.crypto.subtle.exportKey(type, key);
                    return window.btoa(String.fromCharCode.apply(null, new Uint8Array(exported)));
                };

                const pubPem = await exportKey(keyPair.publicKey, "spki");
                const privPem = await exportKey(keyPair.privateKey, "pkcs8");

                localStorage.setItem('relay_pubkey', pubPem);
                localStorage.setItem('relay_privkey', privPem);

                const formData = new FormData();
                formData.append('action', 'save_keys');
                formData.append('public_key', pubPem);
                await fetch('console.php', { method: 'POST', body: formData });

                Terminal.splash.hide();
            } catch (err) {
                Terminal.splash.hide();
                Terminal.toast('[!] KEY GENERATION FAILED', 'danger');
            }
        }

        // ==========================================
        // 🧠 [ THE STATE MACHINE: SYNC ENFORCER ]
        // ==========================================
        window.addEventListener('DOMContentLoaded', async () => {
            const serverEncPriv = "<?php echo addslashes($encrypted_privkey ?? ''); ?>";
            const serverPubKey = "<?php echo addslashes($server_pubkey ?? ''); ?>";
            const localPriv = localStorage.getItem('relay_privkey');
            const localPub = localStorage.getItem('relay_pubkey');
            
            // 1. New Device (No Local Keys)
            if (!localPriv || !localPub) {
                if (serverEncPriv) {
                    return;
                } else if (serverPubKey) {
                    document.getElementById('split-brain-modal').style.display = 'flex';
                    return;
                } else {
                    await forgeQuantumKeys();
                    document.getElementById('vault-setup-modal').style.display = 'flex';
                    return;
                }
            }

            // 2. Existing Device (Has Local Keys)
            if (localPriv && localPub) {
                if (serverPubKey && localPub !== serverPubKey) {
                    if (serverEncPriv) {
                        Terminal.toast('[!] IDENTITY OUT OF SYNC. RELOGIN REQUIRED.', 'danger');
                        setTimeout(() => { window.location.href = 'console.php?logout=true'; }, 2000);
                        return;
                    } else {
                        document.getElementById('split-brain-modal').style.display = 'flex';
                        return;
                    }
                } else if (!serverEncPriv) {
                    document.getElementById('vault-setup-modal').style.display = 'flex';
                    return;
                }
            }

            // 🗼 THE LIGHTHOUSE HEARTBEAT (Pings directory if Opted-In)
            const lighthouseOpt = "<?php echo $lighthouse_opt; ?>";
            if (lighthouseOpt === '1') {
                const planetUrlStr = window.location.origin + window.location.pathname.replace('/console.php', '');
                const stationNameStr = "<?php echo addslashes($station_name); ?>";
                const stationBioStr = "<?php echo addslashes($station_bio); ?>";
                
                fetch('https://relay.emptyhub.my.id/api_register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'ping',
                        planet_url: planetUrlStr,
                        station_name: stationNameStr,
                        station_bio: stationBioStr
                    })
                }).catch(e => {}); // Silent execution in background
            }
        });

        // ==========================================
        // 💬 [ TACTICAL QUOTE & GLOBAL PURGE ]
        // ==========================================
        function quoteTimeline(btn, author) {
            const container = btn.closest('.transmission-card');
            const msgElement = container.querySelector('.timeline-msg');
            const hasMedia = container.querySelector('.media-matrix') || container.querySelector('.audio-play-btn') || container.querySelector('.matrix-img') ? true : false;
            
            let originalText = msgElement.innerText.trim();

            let quoteBlock = `> [ TRANSMISI DARI: ${author} ]\n`;
            if (originalText.length > 0) {
                const lines = originalText.split('\n').map(line => `> ${line}`);
                quoteBlock += lines.join('\n') + '\n';
            }
            if (hasMedia) {
                quoteBlock += `> [ 📎 MEDIA_ATTACHED ]\n`;
            }
            
            const input = document.querySelector('#broadcast-form textarea[name="content"]');
            input.value = quoteBlock + '\n' + input.value;
            input.focus();
        }

        async function globalPurge(btn, msgId) {
            if (confirm('> THE GLOBAL PURGE PROTOCOL\n\nWARNING: This will permanently delete this broadcast locally AND fire a silent missile to wipe it from all allied Star Chart nodes.\n\nExecute?')) {
                Terminal.splash.show('> INITIATING GLOBAL_PURGE...');
                
                const container = btn.closest('.transmission-card');
                const rawContent = container.getAttribute('data-raw-content');
                
                const fireData = new FormData();
                fireData.append('visibility', 'global_purge');
                fireData.append('content', rawContent); 
                try {
                    await fetch('core/transmitter.php', { method: 'POST', body: fireData });
                } catch(e) {}

                const delData = new FormData();
                delData.append('action', 'delete_local');
                delData.append('id', msgId);
                await fetch('console.php', { method: 'POST', body: delData });
                
                Terminal.toast('[✓] GLOBAL PURGE EXECUTED', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        }

        // ==========================================
        // 🎛️ [ THE CONTROL ROOM ENGINE ]
        // ==========================================
        const crForm = document.getElementById('control-room-form');
        if (crForm) {
            crForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const btn = this.querySelector('button[type="submit"]');
                btn.innerText = '[ UPDATING_CORE_MEMORY... ]';
                
                const newName = document.getElementById('cr-name').value;
                const newBio = document.getElementById('cr-bio').value;
                const isBunker = document.getElementById('cr-bunker').checked ? '1' : '0';
                const isLighthouse = document.getElementById('cr-lighthouse').checked ? '1' : '0';
                const newPass = document.getElementById('cr-passcode').value;
                
                // 👁️ [ V7.0 THE ORACLE: INPUT CAPTURE ]
                const isTelegram = document.getElementById('cr-telegram-enabled').checked ? '1' : '0';
                const telToken = document.getElementById('cr-telegram-token').value;
                const telChatId = document.getElementById('cr-telegram-chatid').value;
                
                const formData = new FormData();
                formData.append('action', 'save_control_room');
                formData.append('station_name', newName);
                formData.append('station_bio', newBio);
                formData.append('bunker_mode', isBunker);
                formData.append('lighthouse_opt', isLighthouse);
                formData.append('station_passcode', newPass);
                
                formData.append('telegram_enabled', isTelegram);
                formData.append('telegram_bot_token', telToken);
                formData.append('telegram_chat_id', telChatId);

                if (newPass) {
                    const privPem = localStorage.getItem('relay_privkey');
                    if (privPem) {
                        try {
                            const encPriv = await encryptVault(privPem, newPass);
                            formData.append('encrypted_privkey', encPriv);
                        } catch(err) { console.error('Failed to re-encrypt vault', err); }
                    }
                }
                
                try {
                    await fetch('console.php', { method: 'POST', body: formData });
                    Terminal.toast('[✓] STATION CONFIGURATION UPDATED', 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch (err) {
                    Terminal.toast('[!] CONFIGURATION UPDATE FAILED', 'danger');
                    btn.innerText = '[ APPLY_CONFIGURATION ]';
                }
            });
        }

        // ==========================================
        // 🎙️ [ TACTICAL SQUELCH GENERATOR (Web Audio API) ]
        // ==========================================
        let audioCtx;
        function getAudioCtx() {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            return audioCtx;
        }

        function playSquelch(type) {
            const ctx = getAudioCtx();
            const duration = 0.15; 
            
            const bufferSize = ctx.sampleRate * duration; 
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) { data[i] = Math.random() * 2 - 1; }
            
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            
            const noiseFilter = ctx.createBiquadFilter();
            noiseFilter.type = 'bandpass';
            noiseFilter.frequency.value = 1500;
            
            const noiseGain = ctx.createGain();
            noiseGain.gain.setValueAtTime(1, ctx.currentTime);
            noiseGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            noise.connect(noiseFilter);
            noiseFilter.connect(noiseGain);
            noiseGain.connect(ctx.destination);
            
            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.frequency.setValueAtTime(type === 'start' ? 2200 : 1200, ctx.currentTime);
            
            const oscGain = ctx.createGain();
            oscGain.gain.setValueAtTime(0.1, ctx.currentTime);
            oscGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            osc.connect(oscGain);
            oscGain.connect(ctx.destination);
            
            noise.start();
            osc.start();
            osc.stop(ctx.currentTime + duration);
        }

        // ==========================================
        // 🎙️ [ THE PUSH-TO-TALK ENGINE (MediaRecorder) ]
        // ==========================================
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        
        const pttBtn = document.getElementById('ptt-btn');
        const fileDisplay = document.getElementById('file-name-display');
        const audioBase64Input = document.getElementById('audio-base64');
        const mediaInput = document.getElementById('media-input');
        const mediaBase64Input = document.getElementById('media-base64');
        const compStatus = document.getElementById('compress-status');

        async function startRecording() {
            if (isRecording) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = () => {
                        audioBase64Input.value = reader.result;
                        fileDisplay.innerText = '> [ AUDIO_LOG_SAVED ]';
                        fileDisplay.className = 'fs-small text-warning font-bold t-blink';
                    };
                    stream.getTracks().forEach(track => track.stop()); 
                };
                
                mediaRecorder.start();
                isRecording = true;
                playSquelch('start');
                
                pttBtn.classList.add('warning', 't-blink');
                pttBtn.classList.remove('danger');
                pttBtn.innerText = '[ 🔴 RECORDING... ]';
                fileDisplay.innerText = '> LISTENING...';
                fileDisplay.className = 'fs-small text-danger t-blink';
            } catch (err) {
                console.error(err);
                Terminal.toast('[!] MIC ACCESS DENIED. CHECK BROWSER PERMISSIONS.', 'danger');
            }
        }

        function stopRecording() {
            if (!isRecording || !mediaRecorder) return;
            mediaRecorder.stop();
            isRecording = false;
            playSquelch('stop');
            
            pttBtn.classList.remove('warning', 't-blink');
            pttBtn.classList.add('danger');
            pttBtn.innerText = '[ 🎙️ HOLD_TO_TALK ]';
        }

        if (pttBtn) {
            pttBtn.addEventListener('mousedown', startRecording);
            pttBtn.addEventListener('touchstart', (e) => { e.preventDefault(); startRecording(); }, {passive: false});
            window.addEventListener('mouseup', stopRecording);
            pttBtn.addEventListener('touchend', stopRecording);
        }

        // ==========================================
        // 🗜️ [ MULTI-MEDIA COMPRESSOR MATRIX ]
        // ==========================================
        if (mediaInput) {
            mediaInput.addEventListener('change', async function(e) {
                const files = e.target.files;
                if(files.length === 0) {
                    fileDisplay.innerText = '> NO_MEDIA';
                    compStatus.innerText = '';
                    mediaBase64Input.value = '';
                    return;
                }
                
                if(files.length > 4) {
                    Terminal.toast('[!] MAX 4 FILES ALLOWED', 'warning');
                }
                
                const processCount = Math.min(files.length, 4);
                if (processCount === 1) {
                    fileDisplay.innerText = '> ' + files[0].name;
                } else {
                    fileDisplay.innerText = '> [ ' + processCount + ' MEDIA ATTACHED ]';
                }
                fileDisplay.className = 'fs-small text-success font-bold';
                compStatus.innerText = '[ PROCESSING... ]';
                
                let processedBase64Array = [];
                
                for(let i=0; i < processCount; i++) {
                    const file = files[i];
                    if(file.type.startsWith('video/') || file.type.startsWith('audio/')) { continue; }
                    if(file.type.startsWith('image/')) {
                        const b64 = await compressImage(file);
                        processedBase64Array.push(b64);
                    }
                }
                
                if (processedBase64Array.length > 0) {
                    mediaBase64Input.value = JSON.stringify(processedBase64Array);
                    compStatus.innerText = '[ WEBP COMPRESSED ]';
                } else {
                    mediaBase64Input.value = '';
                    compStatus.innerText = '[ RAW MEDIA QUEUED ]';
                }
            });
        }

        function compressImage(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width; let height = img.height;
                        if(width > 1080) { height = Math.round(height * 1080 / width); width = 1080; } 
                        canvas.width = width; canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/webp', 0.8));
                    }
                    img.src = event.target.result;
                }
                reader.readAsDataURL(file);
            });
        }

        // ==========================================
        // ▶️ [ DELEGATED RETRO AUDIO PLAYER ]
        // ==========================================
        let currentAudio = null;
        let currentBtn = null;

        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('audio-play-btn')) {
                const btn = e.target;
                const src = btn.getAttribute('data-src');
                
                if (currentAudio && currentBtn === btn) {
                    if (!currentAudio.paused) {
                        currentAudio.pause();
                        btn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                        btn.classList.remove('t-blink');
                        return;
                    } else {
                        currentAudio.play();
                        btn.innerText = '[ ⏸️ PLAYING... ]';
                        btn.classList.add('t-blink');
                        return;
                    }
                }
                
                if (currentAudio) {
                    currentAudio.pause();
                    if(currentBtn) {
                        currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                        currentBtn.classList.remove('t-blink');
                    }
                }
                
                currentAudio = new Audio(src);
                currentBtn = btn;
                currentBtn.innerText = '[ ⏸️ PLAYING... ]';
                currentBtn.classList.add('t-blink');
                
                currentAudio.play();
                
                currentAudio.onended = () => {
                    currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                    currentBtn.classList.remove('t-blink');
                };
            }
        });

        // ==========================================
        // 📡 [ SONAR PULSE: UI & VALIDATION ENGINE ]
        // ==========================================
        function openSonarModal(url, alias) {
            document.getElementById('sonar-target-display').innerText = alias;
            document.getElementById('sonar-target-input').value = url;
            document.getElementById('cr-sonar-code').value = '';
            document.getElementById('sonar-pulse-modal').style.display = 'flex';
            document.getElementById('cr-sonar-code').focus();
        }
        
        const sonarInput = document.getElementById('cr-sonar-code');
        if(sonarInput) {
            sonarInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        const MORSE_DICT = {
            'A': '.-', 'B': '-...', 'C': '-.-.', 'D': '-..', 'E': '.', 'F': '..-.',
            'G': '--.', 'H': '....', 'I': '..', 'J': '.---', 'K': '-.-', 'L': '.-..',
            'M': '--', 'N': '-.', 'O': '---', 'P': '.--.', 'Q': '--.-', 'R': '.-.',
            'S': '...', 'T': '-', 'U': '..-', 'V': '...-', 'W': '.--', 'X': '-..-',
            'Y': '-.--', 'Z': '--..', '0': '-----', '1': '.----', '2': '..---',
            '3': '...--', '4': '....-', '5': '.....', '6': '-....', '7': '--...',
            '8': '---..', '9': '----.'
        };

        async function decodeSonar(code, alertId) {
            code = code.toUpperCase().replace(/[^A-Z0-9]/g, '');
            const btn = document.getElementById('btn-sonar-' + alertId);
            const display = document.getElementById('sonar-display-' + alertId);
            
            btn.disabled = true;
            btn.innerText = '[ 📻 DECODING... ]';
            display.innerText = '';

            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();

            oscillator.type = 'sine';
            oscillator.frequency.value = 600; 
            
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            gainNode.gain.setValueAtTime(0, audioCtx.currentTime);
            oscillator.start();

            const dotTime = 0.1; 

            for (let i = 0; i < code.length; i++) {
                let char = code[i];
                display.innerText += char; 
                
                let morse = MORSE_DICT[char];
                if (morse) {
                    for (let j = 0; j < morse.length; j++) {
                        let symbol = morse[j];
                        let duration = symbol === '.' ? dotTime : dotTime * 3;
                        
                        gainNode.gain.setTargetAtTime(1, audioCtx.currentTime, 0.01);
                        await new Promise(r => setTimeout(r, duration * 1000));
                        
                        gainNode.gain.setTargetAtTime(0, audioCtx.currentTime, 0.01);
                        await new Promise(r => setTimeout(r, dotTime * 1000)); 
                    }
                }
                await new Promise(r => setTimeout(r, dotTime * 3 * 1000)); 
            }
            
            oscillator.stop();
            btn.innerText = '[ ✓ DECODED ]';
            
            fetch('core/alert_action.php?id=' + alertId + '&ajax=1').then(() => {
                setTimeout(() => {
                    const card = btn.closest('.t-card');
                    if(card) {
                        card.remove();
                        const sideCounter = document.getElementById('sidebar-alert-counter');
                        const navCounter = document.getElementById('nav-alert-counter');
                        if (sideCounter) {
                            let count = parseInt(sideCounter.innerText) - 1;
                            if (count <= 0) {
                                const sideHeader = document.getElementById('sidebar-alert-header');
                                const sideList = document.getElementById('sidebar-alert-list');
                                if(sideHeader) sideHeader.remove();
                                if(sideList) sideList.remove();
                                if(navCounter) navCounter.remove();
                            } else {
                                sideCounter.innerText = count;
                                if(navCounter) navCounter.innerHTML = `[ 🔔 ${count} NEW ]`;
                            }
                        }
                    }
                }, 2000);
            });
        }

        // ==========================================
        // 🚀 [ BROADCAST FORM & AJAX LOGIC ]
        // ==========================================
        const broadcastForm = document.getElementById('broadcast-form');
        if (broadcastForm) {
            broadcastForm.addEventListener('submit', (e) => { 
                const contentInput = broadcastForm.querySelector('textarea[name="content"]');
                const audioInput = document.getElementById('audio-base64');
                const mediaInp = document.getElementById('media-input');
                
                if (contentInput.value.trim() === '' && (audioInput.value !== '' || mediaInp.files.length > 0)) {
                    contentInput.value = '[ 🎙️ SECURE_MEDIA_TRANSMISSION ]';
                } else if (contentInput.value.trim() === '') {
                    e.preventDefault();
                    Terminal.toast('[!] TRANSMISSION CANNOT BE EMPTY', 'danger');
                    return;
                }
                
                Terminal.splash.show('> TRANSMITTING_SIGNAL...'); 
            });
        }

        document.getElementById('follow-form').addEventListener('submit', () => { Terminal.splash.show('> LOCKING_COORDINATES...'); });

        function acceptHandshake(url, alertId) {
            document.getElementById('target-planet-input').value = url;
            Terminal.splash.show('> PROCESSING_MUTUAL_LINK...');
            fetch('core/alert_action.php?id=' + alertId + '&ajax=1').then(() => {
                document.getElementById('follow-form').submit();
            });
        }

        let deferredPrompt; const installBtn = document.getElementById('installAppBtn');
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Reg Failed:', err)); }); }
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; installBtn.style.display = 'inline-block'; });
        installBtn.addEventListener('click', async () => { if (deferredPrompt !== null) { deferredPrompt.prompt(); const { outcome } = await deferredPrompt.userChoice; if (outcome === 'accepted') { installBtn.style.display = 'none'; } deferredPrompt = null; } });
        window.addEventListener('appinstalled', () => { installBtn.style.display = 'none'; });

        let isFetching = false;
        const loadMoreEl = document.getElementById('load-more');
        
        if (loadMoreEl) {
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting && !isFetching) {
                    isFetching = true;
                    loadMoreEl.innerText = '[ RECEIVING SIGNALS... ]';
                    
                    const cards = document.querySelectorAll('.transmission-card');
                    if (cards.length === 0) return;
                    const lastId = cards[cards.length - 1].getAttribute('data-id');

                    fetch('console.php?last_id=' + lastId)
                    .then(r => r.text())
                    .then(html => {
                        if(html.trim() !== '') {
                            document.getElementById('signal-log').insertAdjacentHTML('beforeend', html);
                            isFetching = false;
                            loadMoreEl.innerText = '[ SCROLL DOWN TO SCAN ]';
                        } else {
                            loadMoreEl.innerText = '[ END OF TRANSMISSIONS ]';
                            observer.disconnect();
                        }
                    });
                }
            });
            observer.observe(loadMoreEl);
        }

        function runRadarSweep() {
            const btn = document.getElementById('btn-sweep');
            btn.innerText = '[ PINGING... ]'; btn.disabled = true;
            fetch('core/radar_sweep.php').then(r => r.text()).then(res => {
                Terminal.toast(res, 'warning');
                setTimeout(() => location.reload(), 2000); 
            });
        }
    </script>
</body>
</html>