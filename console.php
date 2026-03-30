<?php
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
$db_file = 'data/relay_core.sqlite';

try {
    $db_auth = new PDO("sqlite:" . $db_file);
    $db_auth->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db_auth->setAttribute(PDO::ATTR_TIMEOUT, 5); 
    $stmt = $db_auth->query("SELECT config_value FROM system_config WHERE config_key = 'captain_hash'");
    $captain_hash = $stmt->fetchColumn();
    $stmt = null; $db_auth = null; 
} catch (PDOException $e) {
    die("[ CRITICAL ERROR ] Cannot read station encryption: " . $e->getMessage());
}

if (isset($_POST['passcode'])) {
    if (password_verify($_POST['passcode'], $captain_hash)) {
        $_SESSION['relay_auth'] = true;
        header("Location: console.php"); exit;
    } else { $login_error = "ACCESS DENIED. INTRUDER LOGGED."; }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); exit;
}

// ==========================================
// CSS PATCH UNTUK TERMINAL UI (YANG BELUM TER-COVER)
// ==========================================
$terminal_patch = '
<style>
    .t-textarea { width: 100%; background: transparent; color: var(--t-green); border: 1px solid var(--t-green-dim); padding: 10px; font-family: var(--t-font); font-size: 14px; outline: none; margin-bottom: 15px; resize: vertical; transition: 0.2s; }
    .t-textarea:focus { border-color: var(--t-green); box-shadow: 0 0 8px rgba(0, 255, 65, 0.2); }
    .t-center-screen { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .t-center-box { width: 100%; max-width: 400px; text-align: center; }
    .t-blink { animation: terminal-blink 1s linear infinite; }
    @keyframes terminal-blink { 50% { opacity: 0; } }
    #installAppBtn { display: none; margin-right: 15px; }
</style>
';

// --- LAYAR LOGIN ---
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    echo '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">';
    echo $terminal_patch . '</head>';
    echo '<body class="t-center-screen">';
    echo '<div class="t-center-box t-card" style="border-color: var(--t-red);">';
    echo '<h2 class="t-card-header t-blink" style="color:var(--t-red); border-color:var(--t-red);">> RESTRICTED AREA</h2>';
    if(isset($login_error)) echo '<div class="t-alert danger" style="text-align:left;">' . $login_error . '</div>';
    
    echo '<form method="POST">';
    echo '<div class="t-input-group">';
    echo '<input type="password" id="loginPass" name="passcode" class="t-input" placeholder="ENTER PASSCODE" autofocus style="text-align:center; letter-spacing: 5px;">';
    echo '<button type="button" class="t-input-action-btn" onclick="Terminal.toggleInputAction(\'loginPass\', this)">[ SHOW ]</button>';
    echo '</div>';
    echo '<button type="submit" class="t-btn" style="width: 100%; font-weight: bold; margin-top: 15px;">[ OVERRIDE ]</button>';
    echo '</form></div>';
    echo '<script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>';
    echo '</body></html>'; exit;
}
// ==========================================

date_default_timezone_set('UTC'); 

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    $db->exec("DELETE FROM transmissions WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_TIMESTAMP");
    $db->exec("DELETE FROM transmissions WHERE visibility = 'public' AND is_remote = 1 AND timestamp <= datetime('now', '-30 days')");
    
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' ORDER BY timestamp DESC LIMIT 50");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);
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
    <?php echo $terminal_patch; ?>
</head>
<body>

    <nav class="t-navbar">
        <div class="t-nav-brand"><span class="t-led-dot t-led-green"></span> RELAY_STATION <span style="font-size:10px; opacity:0.5;">v1.0.0</span></div>
        <div class="t-nav-menu">
            <button id="installAppBtn" class="t-btn">[ INSTALL PWA ]</button>
            <a href="console.php?logout=true" class="t-btn danger" style="padding: 5px 10px;">> LOGOUT</a>
        </div>
    </nav>

    <div class="t-grid-layout">
        
        <main class="t-main-panel">
            <h2 class="t-card-header">> 🌐 PUBLIC TIMELINE</h2>
            
            <div class="t-card">
                <form action="core/transmitter.php" method="POST">
                    <input type="hidden" name="visibility" value="public">
                    <textarea name="content" rows="3" class="t-textarea" placeholder="What's happening in your sector?" required></textarea>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <label class="t-checkbox-label" style="margin:0; color: var(--t-red);">
                            <input type="checkbox" name="ghost_protocol" value="1"><span class="t-checkmark"></span> [!] GHOST PROTOCOL (24H)
                        </label>
                        <button type="submit" class="t-btn">[ BROADCAST ]</button>
                    </div>
                </form>
            </div>

            <div id="signal-log">
                <?php if (empty($transmissions)): ?>
                    <p style="opacity: 0.5; margin-top: 20px; text-align: center;">[ TIMELINE IS EMPTY ]</p>
                <?php else: ?>
                    <?php foreach ($transmissions as $msg): ?>
                        <div class="t-card" style="margin-bottom: 10px; padding: 15px;">
                            <span class="t-bubble-meta">
                                [ <?php echo $msg['timestamp']; ?> UTC ] 
                                <?php echo $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:'; ?> 
                                <strong style="color: #fff;"><?php echo htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN'); ?></strong>
                                <?php if(!empty($msg['expiry_date'])) echo ' <span class="t-blink" style="color: var(--t-red);">[ 👻 GHOSTED ]</span>'; ?>
                            </span>
                            <p style="margin-top: 5px;">
                                <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <aside class="t-side-panel">
            <h2 class="t-card-header">> 🧭 NAVIGATION</h2>
            <div class="t-list-group" style="margin-bottom: 20px;">
                <a href="console.php" class="t-list-item active" style="border-left-color: var(--t-green); color: var(--t-green);">
                    <span class="t-list-item-title">> 🌐 TIMELINE</span>
                </a>
                <a href="direct.php" class="t-list-item">
                    <span class="t-list-item-title">> ✉️ DIRECT MESSAGES</span>
                </a>
                <a href="index.php" target="_blank" class="t-list-item">
                    <span class="t-list-item-title" style="font-weight: normal;">> 👁️ PUBLIC HOLOGRAM</span>
                </a>
            </div>

            <h2 class="t-card-header">> 🗺️ STAR_CHART</h2>
            <div class="t-card" style="padding: 10px;">
                <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                    <div class="t-alert danger" style="padding: 5px; margin-bottom: 10px; font-size: 11px;">[!] SIGNAL LOST: Invalid Node.</div>
                <?php endif; ?>
                <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                    <div class="t-alert" style="padding: 5px; margin-bottom: 10px; font-size: 11px; border-color: var(--t-green);">[✓] NODE LOCKED.</div>
                <?php endif; ?>

                <form action="core/add_planet.php" method="POST">
                    <input type="url" name="planet_url" class="t-input" placeholder="https://domain.com" required>
                    <button type="submit" class="t-btn" style="width: 100%;">[ FOLLOW NODE ]</button>
                </form>
            </div>

            <div class="t-list-group">
                <?php if (empty($star_chart)): ?>
                    <div class="t-list-item"><span class="t-list-item-subtitle">[ NO NODES FOLLOWED ]</span></div>
                <?php else: ?>
                    <?php foreach ($star_chart as $star): ?>
                        <div class="t-list-item" style="cursor: default;">
                            <span class="t-list-item-title"><?php echo htmlspecialchars($star['alias']); ?></span>
                            <span class="t-list-item-subtitle"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        let deferredPrompt; const installBtn = document.getElementById('installAppBtn');
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Reg Failed:', err)); }); }
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; installBtn.style.display = 'inline-block'; });
        installBtn.addEventListener('click', async () => { if (deferredPrompt !== null) { deferredPrompt.prompt(); const { outcome } = await deferredPrompt.userChoice; if (outcome === 'accepted') { installBtn.style.display = 'none'; } deferredPrompt = null; } });
        window.addEventListener('appinstalled', () => { installBtn.style.display = 'none'; });
    </script>
</body>
</html>