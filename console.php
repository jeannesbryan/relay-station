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

date_default_timezone_set('UTC'); 

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    // 🧹 [ FAST GARBAGE COLLECTOR O(N) ]
    $media_files = glob('media/*');
    if ($media_files) {
        $active_media = $db->query("SELECT media_url FROM transmissions WHERE media_url IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
        $active_filenames = array_map('basename', $active_media);
        
        $physical_files = [];
        foreach ($media_files as $file) {
            if (is_file($file)) { $physical_files[] = basename($file); }
        }
        
        $orphans = array_diff($physical_files, $active_filenames);
        foreach ($orphans as $orphan) { @unlink('media/' . $orphan); }
    }
    
    $db->exec("DELETE FROM transmissions WHERE expiry_date IS NOT NULL AND expiry_date <= CURRENT_TIMESTAMP");
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
            // [ BUG FIX 1 ]: Bebas dari double escaping
            $content = nl2br($msg['content']);
            $img = !empty($msg['media_url']) ? '<div class="mt-3 text-center"><img src="'.htmlspecialchars($msg['media_url']).'" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;"></div>' : '';
            
            // [ BUG FIX 3 ]: Tambahkan class 'transmission-card' dan atribut 'data-id'
            echo "<div class='t-card mb-3 p-3 transmission-card' data-id='{$msg['id']}'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap'>
                        <span>[ {$msg['timestamp']} UTC ] $src <strong class='text-success'>$author</strong></span> $ghost
                    </div>
                    <p class='m-0' style='font-size: 14px;'>$content</p> $img
                  </div>";
        }
        exit;
    }
    // ==========================================
    
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' ORDER BY id DESC LIMIT 15");
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
            <div class="t-nav-brand"><span class="t-led-dot t-led-green"></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">v3.0 FEDIVERSE</span></div>
            <div class="t-nav-menu">
                <button id="installAppBtn" class="t-btn t-btn-sm">[ INSTALL PWA ]</button>
                <a href="core/updater.php" class="t-btn warning t-btn-sm" title="Periksa Pembaruan Sistem">[ SYS_UPDATE ]</a>
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
                            <input type="file" id="media-input" name="media" accept="image/*" class="t-input m-0" style="padding: 6px; flex-grow:1;">
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
                                        <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Transmission Media" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;">
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

                <h2 class="t-card-header d-flex justify-content-between align-items-center">
                    > 🗺️ STAR_CHART
                    <button onclick="runRadarSweep()" id="btn-sweep" class="t-btn warning t-btn-sm" title="Ping All Nodes">[ PING ALL ]</button>
                </h2>
                <div class="t-card p-2 mb-3 mt-3">
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

        // 🖼️ 1. CLIENT-SIDE IMAGE COMPRESSION (CANVAS)
        const mediaInput = document.getElementById('media-input');
        if (mediaInput) {
            mediaInput.addEventListener('change', function(e) {
                const file = e.target.files[0]; if(!file) return;
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

        // 🔄 [ BUG FIX 3: CURSOR-BASED INFINITE SCROLL ]
        let isFetching = false;
        const loadMoreEl = document.getElementById('load-more');
        
        if (loadMoreEl) {
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting && !isFetching) {
                    isFetching = true;
                    loadMoreEl.innerText = '[ RECEIVING SIGNALS... ]';
                    
                    // Cari ID dari pesan terakhir di layar
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

        // 📡 RADAR SWEEP (PING ALL)
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