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

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    echo '<!DOCTYPE html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="stylesheet" href="assets/style.css"></head>';
    echo '<body style="display:flex; justify-content:center; align-items:center; height:100vh; margin:0; background-color: var(--bg-color);">';
    echo '<div class="console-box" style="text-align:center; width: 350px; max-width: 90%; box-sizing: border-box;">';
    echo '<h2 class="blink" style="color:var(--alert);">[ RESTRICTED AREA ]</h2>';
    echo '<p style="color:var(--alert); font-size: 0.8em;">' . ($login_error ?? 'IDENTIFY YOURSELF, CAPTAIN.') . '</p>';
    echo '<form method="POST" style="margin:0; padding:0;">';
    echo '<div style="position: relative; margin-bottom: 15px;">';
    echo '<input type="password" id="loginPass" name="passcode" placeholder="ENTER PASSCODE" autofocus style="text-align:center; width: 100%; box-sizing: border-box; padding: 10px; padding-right: 60px; letter-spacing: 5px; background: transparent; border: 1px solid var(--text-main); color: var(--text-main); font-family: monospace;">';
    echo '<button type="button" onclick="toggleLoginPass()" id="toggleLoginBtn" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); width: auto; border: none; background: transparent; color: var(--text-main); cursor: pointer; font-size: 0.8em; padding: 5px; margin: 0; font-family: monospace;">[ SHOW ]</button>';
    echo '</div><button type="submit" style="width: 100%; box-sizing: border-box; padding: 10px; cursor: pointer; font-family: monospace; font-weight: bold;">[ OVERRIDE ]</button></form>';
    echo '<script>function toggleLoginPass() { var x = document.getElementById("loginPass"); var btn = document.getElementById("toggleLoginBtn"); if (x.type === "password") { x.type = "text"; btn.innerHTML = "[ HIDE ]"; btn.style.color = "var(--alert)"; } else { x.type = "password"; btn.innerHTML = "[ SHOW ]"; btn.style.color = "var(--text-main)"; } }</script>';
    echo '</div></body></html>'; exit;
}
// ==========================================

date_default_timezone_set('UTC'); 

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    // Ghost Protocol & Auto Purge
    $db->exec("DELETE FROM transmissions WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_TIMESTAMP");
    $db->exec("DELETE FROM transmissions WHERE visibility = 'public' AND is_remote = 1 AND timestamp <= datetime('now', '-30 days')");
    
    // Timeline Query (Public Only)
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' ORDER BY timestamp DESC LIMIT 50");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    // Star Chart Query
    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<h3 style='color:red;'>[ CRITICAL ERROR ] Core Memory Offline.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Public Timeline</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="manifest.json">
    <style>
        .status-dot { display: inline-block; width: 14px; height: 14px; background-color: #4af626; border-radius: 50%; margin-right: 10px; box-shadow: 0 0 10px #4af626; }
        .dashboard-layout { display: flex; gap: 20px; flex-wrap: wrap; }
        .main-panel { flex: 3; min-width: 300px; }
        .side-panel { flex: 1; min-width: 250px; }
        #installAppBtn { display: none; background: #4af626; color: #0a0a0a; border: none; padding: 5px 10px; font-weight: bold; cursor: pointer; font-family: monospace; }
        @media (max-width: 768px) { .dashboard-layout { flex-direction: column; } }
    </style>
</head>
<body>

    <header style="margin-bottom: 30px; border-bottom: 2px solid var(--text-main); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-bottom: 10px;">
        <div>
            <h1 style="margin-bottom: 5px; display: flex; align-items: center;"><span class="status-dot blink"></span>RELAY_STATION</h1>
            <p style="margin-top: 0; font-size: 0.9em;">STATUS: <span style="color: #4af626;">ONLINE</span> | ENCRYPTION: ACTIVE | VER: 1.0.0-dev</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button id="installAppBtn">[ INSTALL PWA ]</button>
            <a href="console.php?logout=true" style="color: var(--alert); text-decoration: none; border: 1px solid var(--alert); padding: 5px 10px;">> LOGOUT</a>
        </div>
    </header>

    <div class="dashboard-layout">
        
        <main class="main-panel">
            <h2>[ 🌐 PUBLIC TIMELINE ]</h2>
            
            <div class="console-box">
                <form action="core/transmitter.php" method="POST">
                    <input type="hidden" name="visibility" value="public">
                    <textarea name="content" rows="3" style="width: 100%; box-sizing: border-box; background: transparent; border: 1px solid var(--text-main); color: var(--text-main); padding: 10px; font-family: monospace;" placeholder="What's happening in your sector?" required></textarea><br>

                    <div style="margin-top: 10px; border: 1px dashed var(--text-dim); padding: 5px; display: flex; justify-content: space-between; align-items: center;">
                        <label style="color: var(--alert); font-size: 0.9em; cursor: pointer;">
                            <input type="checkbox" name="ghost_protocol" value="1"> 
                            [!] GHOST PROTOCOL (24H Destruct)
                        </label>
                        <button type="submit" style="padding: 5px 15px; cursor: pointer; font-weight: bold; font-family: monospace;">[ BROADCAST ]</button>
                    </div>
                </form>
            </div>

            <div id="signal-log">
                <?php if (empty($transmissions)): ?>
                    <p style="opacity: 0.5; margin-top: 20px;">[ TIMELINE IS EMPTY ]</p>
                <?php else: ?>
                    <?php foreach ($transmissions as $msg): ?>
                        <div class="console-box" style="margin-top: 10px; border-color: var(--text-dim);">
                            <small style="opacity: 0.7;">
                                [ <?php echo $msg['timestamp']; ?> UTC ] 
                                <?php echo $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:'; ?> 
                                <strong style="color: #fff;"><?php echo htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN'); ?></strong>
                                <?php if(!empty($msg['expiry_date'])) echo ' <span class="blink" style="color: var(--alert);">[ 👻 GHOSTED ]</span>'; ?>
                            </small>
                            <p style="margin: 10px 0 0 0; word-wrap: break-word;">
                                <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <aside class="side-panel">
            <h2>[ 🧭 NAVIGATION ]</h2>
            <div class="console-box" style="margin-bottom: 20px;">
                <ul style="list-style: none; padding: 0; margin: 0; font-weight: bold;">
                    <li style="margin-bottom: 15px;"><span style="color: #4af626;">> 🌐 TIMELINE</span></li>
                    <li style="margin-bottom: 15px;"><a href="direct.php" style="color: var(--text-main); text-decoration: none;">> ✉️ DIRECT MESSAGES</a></li>
                    <li><a href="index.php" target="_blank" style="color: var(--text-dim); text-decoration: none; font-weight: normal;">> 👁️ VIEW PUBLIC HOLOGRAM</a></li>
                </ul>
            </div>

            <h2>[ 🗺️ STAR_CHART ]</h2>
            <div class="console-box" style="margin-bottom: 10px;">
                <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                    <div style="color: var(--bg-color); background-color: var(--alert); padding: 5px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;">
                        [!] SIGNAL LOST: Target is not a valid RELAY node.
                    </div>
                <?php endif; ?>
                <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                    <div style="color: var(--bg-color); background-color: #4af626; padding: 5px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;">
                        [✓] NODE LOCKED.
                    </div>
                <?php endif; ?>

                <form action="core/add_planet.php" method="POST">
                    <input type="url" name="planet_url" placeholder="https://domain.com" required style="width: 100%; box-sizing: border-box; margin-bottom: 10px; padding: 10px; background: transparent; border: 1px solid var(--text-main); color: var(--text-main); font-family: monospace;">
                    <button type="submit" style="width: 100%; box-sizing: border-box; font-size: 0.9em; padding: 10px; cursor: pointer; font-weight: bold; font-family: monospace;">[ FOLLOW NODE ]</button>
                </form>
            </div>

            <div class="console-box" style="border-color: var(--text-dim);">
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9em; word-wrap: break-word;">
                    <?php if (empty($star_chart)): ?>
                        <li style="opacity: 0.5;">[ NO NODES FOLLOWED ]</li>
                    <?php else: ?>
                        <?php foreach ($star_chart as $star): ?>
                            <li style="margin-bottom: 5px; border-bottom: 1px dashed var(--text-dim); padding-bottom: 5px;">
                                <strong style="color: var(--text-main);"><?php echo htmlspecialchars($star['alias']); ?></strong><br>
                                <span style="font-size: 0.8em; opacity: 0.7;"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

    </div>

    <script>
        let deferredPrompt; const installBtn = document.getElementById('installAppBtn');
        if ('serviceWorker' in navigator) { window.addEventListener('load', () => { navigator.serviceWorker.register('sw.js').catch(err => console.log('SW Reg Failed:', err)); }); }
        window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferredPrompt = e; installBtn.style.display = 'inline-block'; });
        installBtn.addEventListener('click', async () => { if (deferredPrompt !== null) { deferredPrompt.prompt(); const { outcome } = await deferredPrompt.userChoice; if (outcome === 'accepted') { installBtn.style.display = 'none'; } deferredPrompt = null; } });
        window.addEventListener('appinstalled', () => { installBtn.style.display = 'none'; });
    </script>
</body>
</html>