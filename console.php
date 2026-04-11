<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
date_default_timezone_set('UTC'); 

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

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

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
// 🔑 [ AUTHENTICATION PROCESS ]
// ==========================================
if (isset($_POST['passcode']) && !$is_locked) {
    if (password_verify($_POST['passcode'], $captain_hash)) {
        if ($ip_status) {
            $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $user_ip]);
        }
        $_SESSION['relay_auth'] = true;
        header("Location: console.php"); exit;
    } else { 
        if ($ip_status) {
            $attempts = $ip_status['attempts'] + 1;
            if ($attempts >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt = $db->prepare("UPDATE login_attempts SET attempts = :attempts, lockout_until = :lock_time WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':lock_time' => $lock_time, ':ip' => $user_ip]);
                $is_locked = true;
                $login_error = "SYSTEM LOCKED. WAIT 15 MINUTE(S).";
            } else {
                $stmt = $db->prepare("UPDATE login_attempts SET attempts = :attempts WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':ip' => $user_ip]);
                $login_error = "ACCESS DENIED. " . (5 - $attempts) . " ATTEMPTS LEFT.";
            }
        } else {
            $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (:ip, 1)");
            $stmt->execute([':ip' => $user_ip]);
            $login_error = "ACCESS DENIED. 4 ATTEMPTS LEFT.";
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
    echo '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>RESTRICTED - Relay</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">';
    echo '</head><body class="t-crt t-center-screen">';
    echo '<div class="t-center-box t-card danger mb-0">';
    echo '<h2 class="t-card-header t-flicker">> RESTRICTED AREA</h2>';
    if($login_error) echo '<div class="t-alert danger text-left mb-3">' . $login_error . '</div>';
    echo '<form method="POST" class="m-0">';
    echo '<div class="t-input-group mb-4">';
    if ($is_locked) {
        echo '<input type="password" disabled class="t-input text-center font-bold" placeholder="[ RADAR FROZEN ]" style="letter-spacing: 5px;">';
    } else {
        echo '<input type="password" id="loginPass" name="passcode" class="t-input text-center font-bold" placeholder="ENTER PASSCODE" autofocus style="letter-spacing: 5px;">';
        echo '<button type="button" class="t-input-action-btn" onclick="Terminal.toggleInputAction(\'loginPass\', this)">[ SHOW ]</button>';
    }
    echo '</div>';
    if ($is_locked) {
        echo '<button type="button" disabled class="t-btn w-100 font-bold" style="border-color: var(--t-red); color: var(--t-red); opacity: 0.5; cursor: not-allowed;">[ SYSTEM_LOCKED ]</button>';
    } else {
        echo '<button type="submit" class="t-btn danger w-100 font-bold t-glow">[ OVERRIDE_SYSTEM ]</button>';
    }
    echo '</form></div>';
    echo '<script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>';
    echo '</body></html>'; exit;
}

// ==========================================
// 🚀 [ MAIN DASHBOARD PROCESSOR ]
// ==========================================
try {
    // 🎛️ [ AJAX ENDPOINT: SAVE CONTROL ROOM ]
    if (isset($_POST['action']) && $_POST['action'] === 'save_control_room') {
        $name = trim($_POST['station_name'] ?? 'RELAY_STATION');
        $bio = trim($_POST['station_bio'] ?? '');
        $bunker = ($_POST['bunker_mode'] === '1') ? '1' : '0';
        $new_pass = trim($_POST['station_passcode'] ?? '');
        
        $db->prepare("DELETE FROM system_config WHERE config_key IN ('station_name', 'station_bio', 'bunker_mode')")->execute();
        
        $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?)");
        $stmt->execute(['station_name', $name]);
        $stmt->execute(['station_bio', $bio]);
        $stmt->execute(['bunker_mode', $bunker]);

        // Jika passcode baru diisi, timpa hash yang lama
        if (!empty($new_pass)) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $db->prepare("DELETE FROM system_config WHERE config_key = 'captain_hash'")->execute();
            $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('captain_hash', ?)")->execute([$hash]);
        }
        exit;
    }

    // 🔐 [ AJAX ENDPOINT: SAVE PUBLIC KEY ]
    if (isset($_POST['action']) && $_POST['action'] === 'save_pubkey') {
        $pubkey = trim($_POST['public_key'] ?? '');
        if (!empty($pubkey)) {
            $db->prepare("DELETE FROM system_config WHERE config_key = 'public_key'")->execute();
            $stmt = $db->prepare("INSERT INTO system_config (config_key, config_value) VALUES ('public_key', :val)");
            $stmt->execute([':val' => $pubkey]);
        }
        exit;
    }
    
    // 🧹 [ ADVANCED GARBAGE COLLECTOR V3.0.2 ]
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

    // 🔄 [ AJAX ENDPOINT: CURSOR-BASED PAGINATION ]
    if (isset($_GET['last_id'])) {
        $last_id = (int)$_GET['last_id'];
        $stmt = $db->prepare("SELECT * FROM transmissions WHERE visibility = 'public' AND id < :last_id ORDER BY id DESC LIMIT 15");
        $stmt->execute([':last_id' => $last_id]);
        $transmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transmissions as $msg) {
            $author = htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN');
            $ghost = !empty($msg['expiry_date']) ? '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>' : '';
            $src = $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:';
            $content = nl2br($msg['content']);
            
            // 🗄️ V5.5 ADVANCED MEDIA MATRIX RENDERER (AJAX)
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
            
            echo "<div class='t-card mb-3 p-3 transmission-card' data-id='{$msg['id']}'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap'>
                        <span>[ {$msg['timestamp']} UTC ] $src <strong class='text-success'>$author</strong></span> $ghost
                    </div>
                    <p class='m-0' style='font-size: 14px;'>$content</p> $img
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

    $stmt_name = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_name' ORDER BY rowid DESC LIMIT 1");
    $station_name = $stmt_name->fetchColumn() ?: 'RELAY_STATION';

    $stmt_bio = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_bio' ORDER BY rowid DESC LIMIT 1");
    $station_bio = $stmt_bio->fetchColumn() ?: '';

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
        /* PTT Button specific styling */
        #ptt-btn { user-select: none; -webkit-user-select: none; touch-action: manipulation; }
        
        /* V5.5 THE MATRIX GRID */
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
                <button id="installAppBtn" class="t-btn t-btn-sm">[ 📥 ]</button>
                <a href="core/updater.php" class="t-btn warning t-btn-sm" title="Check System Update">[ 🔄 ]</a>
                <a href="console.php?logout=true" class="t-btn danger t-btn-sm">[ ➜] ]</a>
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
                        <?php foreach ($transmissions as $msg): ?>
                            <div class="t-card mb-3 p-3 transmission-card" data-id="<?php echo $msg['id']; ?>">
                                <div class="t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap">
                                    <span>
                                        [ <?php echo $msg['timestamp']; ?> UTC ] 
                                        <?php echo $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:'; ?> 
                                        <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN'); ?></strong>
                                    </span>
                                    <?php if(!empty($msg['expiry_date'])) echo '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>'; ?>
                                </div>
                                <p class="m-0" style="font-size: 14px;">
                                    <?php echo nl2br($msg['content']); ?>
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
                    
                    <div class="mb-3 t-card p-2" style="border-color: var(--t-green); background: rgba(0,255,65,0.05);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="font-bold text-warning">[ PRIVATE NODE / BUNKER MODE ]</span>
                                <div class="fs-small text-muted mt-1">> Seal Hologram from public. Manual follower approval.</div>
                            </div>
                            <label class="t-checkbox-label m-0">
                                <input type="checkbox" id="cr-bunker" <?php echo ($bunker_mode == '1') ? 'checked' : ''; ?>><span class="t-checkmark"></span>
                            </label>
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
        // 🗜️ [ V5.5 MULTI-MEDIA COMPRESSOR MATRIX ]
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
                    
                    if(file.type.startsWith('video/') || file.type.startsWith('audio/')) {
                        continue; 
                    }
                    
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
                const newPass = document.getElementById('cr-passcode').value;
                
                const formData = new FormData();
                formData.append('action', 'save_control_room');
                formData.append('station_name', newName);
                formData.append('station_bio', newBio);
                formData.append('bunker_mode', isBunker);
                formData.append('station_passcode', newPass);
                
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