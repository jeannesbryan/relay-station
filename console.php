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

// --- LAYAR LOGIN ---
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    echo '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>RESTRICTED - Relay</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">';
    echo '</head><body class="t-crt t-center-screen">';
    echo '<div class="t-center-box t-card danger mb-0">';
    echo '<h2 class="t-card-header t-flicker">> RESTRICTED AREA</h2>';
    if(isset($login_error)) echo '<div class="t-alert danger text-left mb-3">' . $login_error . '</div>';
    echo '<form method="POST" class="m-0">';
    echo '<div class="t-input-group mb-4">';
    echo '<input type="password" id="loginPass" name="passcode" class="t-input text-center font-bold" placeholder="ENTER PASSCODE" autofocus style="letter-spacing: 5px;">';
    echo '<button type="button" class="t-input-action-btn" onclick="Terminal.toggleInputAction(\'loginPass\', this)">[ SHOW ]</button>';
    echo '</div>';
    echo '<button type="submit" class="t-btn danger w-100 font-bold t-glow">[ OVERRIDE_SYSTEM ]</button>';
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
    <style>
        #installAppBtn { display: none; }
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
            <div class="t-nav-brand"><span class="t-led-dot t-led-green"></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">v1.0.0</span></div>
            <div class="t-nav-menu">
                <button id="installAppBtn" class="t-btn t-btn-sm">[ INSTALL PWA ]</button>
                <a href="console.php?logout=true" class="t-btn danger t-btn-sm">> LOGOUT</a>
            </div>
        </nav>

        <div class="t-grid-layout">
            
            <main class="t-main-panel">
                <h2 class="t-card-header">> 🌐 PUBLIC TIMELINE</h2>
                
                <div class="t-card">
                    <form action="core/transmitter.php" method="POST" class="m-0" id="broadcast-form">
                        <input type="hidden" name="visibility" value="public">
                        <textarea name="content" rows="3" class="t-textarea" placeholder="> What's happening in your sector?" required></textarea>

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
                            <div class="t-card mb-3 p-3">
                                <div class="t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap">
                                    <span>
                                        [ <?php echo $msg['timestamp']; ?> UTC ] 
                                        <?php echo $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:'; ?> 
                                        <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN'); ?></strong>
                                    </span>
                                    <?php if(!empty($msg['expiry_date'])) echo '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>'; ?>
                                </div>
                                <p class="m-0" style="font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
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

                <h2 class="t-card-header">> 🗺️ STAR_CHART</h2>
                <div class="t-card p-2 mb-3">
                    <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                        <div class="t-alert danger p-2 mb-2 fs-small">[!] SIGNAL LOST: Invalid Node.</div>
                    <?php endif; ?>
                    <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                        <div class="t-alert p-2 mb-2 fs-small" style="border-color: var(--t-green);">[✓] NODE LOCKED.</div>
                    <?php endif; ?>

                    <form action="core/add_planet.php" method="POST" class="m-0" id="follow-form">
                        <input type="url" name="planet_url" class="t-input mb-2" placeholder="https://domain.com" required>
                        <button type="submit" class="t-btn w-100 t-btn-sm">[ FOLLOW NODE ]</button>
                    </form>
                </div>

                <div class="t-list-group">
                    <?php if (empty($star_chart)): ?>
                        <div class="t-list-item"><span class="t-list-item-subtitle text-center">[ NO NODES FOLLOWED ]</span></div>
                    <?php else: ?>
                        <?php foreach ($star_chart as $star): ?>
                            <div class="t-list-item" style="cursor: default;">
                                <span class="t-list-item-title"><?php echo htmlspecialchars($star['alias']); ?></span>
                                <span class="t-list-item-subtitle text-success"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        document.getElementById('broadcast-form').addEventListener('submit', () => { Terminal.splash.show('> TRANSMITTING_SIGNAL...'); });
        document.getElementById('follow-form').addEventListener('submit', () => { Terminal.splash.show('> LOCKING_COORDINATES...'); });

        let deferredPrompt; const installBtn = document.getElementById('installAppBtn');
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Reg Failed:', err)); }); }
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; installBtn.style.display = 'inline-block'; });
        installBtn.addEventListener('click', async () => { if (deferredPrompt !== null) { deferredPrompt.prompt(); const { outcome } = await deferredPrompt.userChoice; if (outcome === 'accepted') { installBtn.style.display = 'none'; } deferredPrompt = null; } });
        window.addEventListener('appinstalled', () => { installBtn.style.display = 'none'; });
    </script>
</body>
</html>