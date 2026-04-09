<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
$db_file = 'data/relay_core.sqlite';
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

try {
    $db_auth = new PDO("sqlite:" . $db_file);
    $db_auth->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_auth->setAttribute(PDO::ATTR_TIMEOUT, 5); 
    
    // ==========================================
    // 🛡️ ANTI-BRUTE FORCE LOCKOUT PROTOCOL
    // ==========================================
    $user_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $is_locked = false;
    $login_error = null;

    $stmt_check_ip = $db_auth->prepare("SELECT attempts, lockout_until FROM login_attempts WHERE ip_address = :ip");
    $stmt_check_ip->execute([':ip' => $user_ip]);
    $ip_status = $stmt_check_ip->fetch(PDO::FETCH_ASSOC);

    if ($ip_status && $ip_status['lockout_until']) {
        $lockout_end = strtotime($ip_status['lockout_until']);
        if (time() < $lockout_end) {
            $is_locked = true;
            $time_left = ceil(($lockout_end - time()) / 60);
            $login_error = "SYSTEM LOCKED. WAIT {$time_left} MINUTE(S).";
        } else {
            $db_auth->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $user_ip]);
            $ip_status = false; 
        }
    }

    $stmt = $db_auth->query("SELECT config_value FROM system_config WHERE config_key = 'captain_hash'");
    $captain_hash = $stmt->fetchColumn();
    $stmt = null; 

} catch (PDOException $e) {
    die("[ CRITICAL ERROR ] Cannot read station encryption: " . $e->getMessage());
}

if (isset($_POST['passcode']) && !$is_locked) {
    if (password_verify($_POST['passcode'], $captain_hash)) {
        if ($ip_status) {
            $db_auth->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")->execute([':ip' => $user_ip]);
        }
        $_SESSION['relay_auth'] = true;
        header("Location: console.php"); exit;
    } else { 
        if ($ip_status) {
            $attempts = $ip_status['attempts'] + 1;
            if ($attempts >= 5) {
                $lock_time = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $stmt = $db_auth->prepare("UPDATE login_attempts SET attempts = :attempts, lockout_until = :lock_time WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':lock_time' => $lock_time, ':ip' => $user_ip]);
                $is_locked = true;
                $login_error = "SYSTEM LOCKED. WAIT 15 MINUTE(S).";
            } else {
                $stmt = $db_auth->prepare("UPDATE login_attempts SET attempts = :attempts WHERE ip_address = :ip");
                $stmt->execute([':attempts' => $attempts, ':ip' => $user_ip]);
                $login_error = "ACCESS DENIED. " . (5 - $attempts) . " ATTEMPTS LEFT.";
            }
        } else {
            $stmt = $db_auth->prepare("INSERT INTO login_attempts (ip_address, attempts) VALUES (:ip, 1)");
            $stmt->execute([':ip' => $user_ip]);
            $login_error = "ACCESS DENIED. 4 ATTEMPTS LEFT.";
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); exit;
}

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

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    // ==========================================
    // 🔐 [ AJAX ENDPOINT: SAVE PUBLIC KEY ]
    // ==========================================
    if (isset($_POST['action']) && $_POST['action'] === 'save_pubkey') {
        $pubkey = trim($_POST['public_key'] ?? '');
        if (!empty($pubkey)) {
            $stmt = $db->prepare("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES ('public_key', :val)");
            $stmt->execute([':val' => $pubkey]);
        }
        exit;
    }

    // ==========================================
    // 🚧 [ AJAX ENDPOINT: TOGGLE BUNKER MODE ]
    // ==========================================
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_bunker') {
        $new_state = ($_POST['state'] === '1') ? '1' : '0';
        $stmt = $db->prepare("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES ('bunker_mode', :val)");
        $stmt->execute([':val' => $new_state]);
        exit;
    }
    
    // ==========================================
    // 🧹 [ ADVANCED GARBAGE COLLECTOR V3.0.2 ]
    // ==========================================
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

    // ==========================================
    // 🔄 [ AJAX ENDPOINT: CURSOR-BASED PAGINATION ]
    // ==========================================
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
            $img = !empty($msg['media_url']) ? '<div class="mt-3 text-center"><img src="'.htmlspecialchars($msg['media_url']).'" class="t-hologram-img" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;"></div>' : '';
            
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

    // [ NEW ] Mengambil Status Bunker Mode
    $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode'");
    $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';

} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ CRITICAL ERROR ] Core Memory Offline.</h3>");
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
        .t-hologram-img { filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s ease-in-out; cursor: crosshair; }
        .t-hologram-img:hover { filter: grayscale(0%) sepia(0%) hue-rotate(0deg) brightness(1) contrast(1); }
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
                <span class="t-led-dot t-led-green"></span> RELAY_STATION 
                <span class="fs-small text-muted fw-normal ml-2">v<?php echo htmlspecialchars($station_version); ?></span>
                <?php if (count($active_alerts) > 0): ?>
                    <span class="text-warning t-blink ml-2 fw-normal" style="font-size:12px;">[ 🔔 <?php echo count($active_alerts); ?> NEW ]</span>
                <?php endif; ?>
            </div>
            <div class="t-nav-menu">
                <button id="installAppBtn" class="t-btn t-btn-sm">[ INSTALL PWA ]</button>
                <a href="core/updater.php" class="t-btn warning t-btn-sm" title="Check System Update">[ SYS_UPDATE ]</a>
                <a href="console.php?logout=true" class="t-btn danger t-btn-sm">> LOGOUT</a>
            </div>
        </nav>

        <div class="t-grid-layout">
            
            <main class="t-main-panel">
                <h2 class="t-card-header">> 🌐 PUBLIC TIMELINE</h2>
                
                <div class="t-card">
                    <form action="core/transmitter.php" method="POST" enctype="multipart/form-data" class="m-0" id="broadcast-form">
                        <input type="hidden" name="visibility" value="public">
                        <input type="hidden" name="media_base64" id="media-base64">
                        <textarea name="content" rows="3" class="t-textarea" placeholder="> What's happening in your sector?" required></textarea>

                        <div class="mb-3 mt-2 d-flex align-items-center gap-2">
                            <input type="file" id="media-input" name="media" accept="image/*" style="display: none;">
                            <button type="button" class="t-btn t-btn-sm" onclick="document.getElementById('media-input').click();" style="white-space: nowrap;">[ ATTACH_MEDIA ]</button>
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
                                
                                <?php if(!empty($msg['media_url'])): ?>
                                    <div class="mt-3 text-center">
                                        <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" class="t-hologram-img" alt="Transmission Media" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;">
                                    </div>
                                <?php endif; ?>
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

                <h2 class="t-card-header">> 🚧 STATION MODE</h2>
                <div class="t-card p-2 mb-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-color: var(--t-<?php echo ($bunker_mode == '1') ? 'red' : 'green'; ?>); background: rgba(<?php echo ($bunker_mode == '1') ? '255,0,65' : '0,255,65'; ?>,0.05);" id="bunker-card">
                    <div>
                        <span class="font-bold text-<?php echo ($bunker_mode == '1') ? 'danger t-blink' : 'success'; ?>" id="bunker-label">
                            <?php echo ($bunker_mode == '1') ? '[ PRIVATE NODE ]' : '[ PUBLIC NODE ]'; ?>
                        </span>
                        <div class="fs-small text-muted mt-1" id="bunker-desc">
                            <?php echo ($bunker_mode == '1') ? '> Hologram sealed. Alerts active.' : '> Hologram online. Open to public.'; ?>
                        </div>
                    </div>
                    <label class="t-checkbox-label m-0">
                        <input type="checkbox" id="bunker-toggle" <?php echo ($bunker_mode == '1') ? 'checked' : ''; ?>><span class="t-checkmark"></span>
                    </label>
                </div>

                <?php if (!empty($active_alerts)): ?>
                    <h2 class="t-card-header text-warning t-blink">> 🔔 ALERTS (<?php echo count($active_alerts); ?>)</h2>
                    <div class="t-list-group mb-4">
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
                            <div class="t-list-item d-flex justify-content-between align-items-center" style="cursor: default;">
                                <div style="overflow: hidden;">
                                    <span class="t-list-item-title"><?php echo htmlspecialchars($clean_alias); ?></span>
                                    <span class="t-list-item-subtitle text-success"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                                </div>
                                <a href="core/remove_planet.php?id=<?php echo $star['id']; ?>" class="t-btn danger t-btn-sm ml-2" style="padding: 2px 6px; border-radius: 0; min-width: auto;" title="Disconnect" onclick="return confirm('> WARNING: Disconnect from this node?');">[ ❌ ]</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        // ==========================================
        // 🚧 [ BUNKER MODE: THE TOGGLE ENGINE ]
        // ==========================================
        const bunkerToggle = document.getElementById('bunker-toggle');
        if (bunkerToggle) {
            bunkerToggle.addEventListener('change', async function() {
                const newState = this.checked ? '1' : '0';
                const label = document.getElementById('bunker-label');
                const desc = document.getElementById('bunker-desc');
                const card = document.getElementById('bunker-card');
                
                if(newState === '1') {
                    label.className = 'font-bold text-danger t-blink';
                    label.innerText = '[ PRIVATE NODE ]';
                    desc.innerText = '> Hologram sealed. Alerts active.';
                    card.style.borderColor = 'var(--t-red)';
                    card.style.background = 'rgba(255,0,65,0.05)';
                } else {
                    label.className = 'font-bold text-success';
                    label.innerText = '[ PUBLIC NODE ]';
                    desc.innerText = '> Hologram online. Open to public.';
                    card.style.borderColor = 'var(--t-green)';
                    card.style.background = 'rgba(0,255,65,0.05)';
                }

                const formData = new FormData();
                formData.append('action', 'toggle_bunker');
                formData.append('state', newState);
                await fetch('console.php', { method: 'POST', body: formData });
                Terminal.toast('[✓] STATION MODE UPDATED', newState === '1' ? 'danger' : 'success');
            });
        }

        // ==========================================
        // 🔐 [ E2E ENCRYPTION: KEYPAIR RADAR ]
        // ==========================================
        async function initCryptoRadar() {
            const privKey = localStorage.getItem('relay_privkey');
            const pubKey = localStorage.getItem('relay_pubkey');

            if (!privKey || !pubKey) {
                console.log('> GENERATING QUANTUM ENCRYPTION KEYS...');
                try {
                    const keyPair = await window.crypto.subtle.generateKey(
                        {
                            name: "RSA-OAEP",
                            modulusLength: 2048,
                            publicExponent: new Uint8Array([1, 0, 1]),
                            hash: "SHA-256"
                        },
                        true,
                        ["encrypt", "decrypt"]
                    );

                    const exportedPriv = await window.crypto.subtle.exportKey("pkcs8", keyPair.privateKey);
                    const exportedPub = await window.crypto.subtle.exportKey("spki", keyPair.publicKey);

                    const privB64 = btoa(String.fromCharCode.apply(null, new Uint8Array(exportedPriv)));
                    const pubB64 = btoa(String.fromCharCode.apply(null, new Uint8Array(exportedPub)));

                    localStorage.setItem('relay_privkey', privB64);
                    localStorage.setItem('relay_pubkey', pubB64);

                    // Send Public Key to Core Memory (SQLite)
                    const formData = new FormData();
                    formData.append('action', 'save_pubkey');
                    formData.append('public_key', pubB64);

                    await fetch('console.php', { method: 'POST', body: formData });
                    Terminal.toast('[✓] E2E KEYS GENERATED', 'success');
                } catch (err) {
                    console.error('[!] ENCRYPTION MODULE FAILED:', err);
                    Terminal.toast('[!] E2E KEY GENERATION FAILED', 'danger');
                }
            }
        }
        window.addEventListener('DOMContentLoaded', initCryptoRadar);


        document.getElementById('broadcast-form').addEventListener('submit', () => { Terminal.splash.show('> TRANSMITTING_SIGNAL...'); });
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

        const mediaInput = document.getElementById('media-input');
        const fileDisplay = document.getElementById('file-name-display');
        
        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                const file = e.target.files[0]; 
                if(!file) {
                    fileDisplay.innerText = '> NO_MEDIA';
                    document.getElementById('compress-status').innerText = '';
                    return;
                }
                
                fileDisplay.innerText = '> ' + file.name;
                document.getElementById('compress-status').innerText = 'COMPRESSING...';
                
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
                        
                        document.getElementById('media-base64').value = canvas.toDataURL('image/webp', 0.8); 
                        document.getElementById('compress-status').innerText = '[ COMPRESSED WEBP ]';
                    }
                    img.src = event.target.result;
                }
                reader.readAsDataURL(file);
            });
        }

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