<?php
// RELAY STATION: PUBLIC HOLOGRAM (FEDIVERSE EDITION V3.0)
// The station's interface for public visitors (Read-Only)

$db_file = 'data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ==========================================
    // 🔄 [ AJAX ENDPOINT: CURSOR-BASED PAGINATION ]
    // ==========================================
    if (isset($_GET['last_id'])) {
        $last_id = (int)$_GET['last_id'];
        // Fetch messages with an ID smaller (older) than the last message on the screen
        $stmt = $db->prepare("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 AND id < :last_id ORDER BY id DESC LIMIT 15");
        $stmt->execute([':last_id' => $last_id]);
        $transmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transmissions as $msg) {
            // [ BUG FIX 1 ]: Free from double escaping
            $content = nl2br($msg['content']);
            $img = !empty($msg['media_url']) ? '<div class="mt-3 text-center"><img src="'.htmlspecialchars($msg['media_url']).'" alt="Broadcast Media" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;"></div>' : '';
            $ghost = !empty($msg['expiry_date']) ? '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>' : '';
            
            // [ BUG FIX 3 ]: Add 'transmission-card' class and 'data-id' attribute
            echo "<div class='t-card mb-3 transmission-card' data-id='{$msg['id']}'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap'>
                        <span>[ {$msg['timestamp']} UTC ] <strong class='text-success'>" . htmlspecialchars($msg['author_alias'] ?? 'COMMANDER') . "</strong></span>
                        $ghost
                    </div>
                    <p class='m-0' style='font-size: 14px;'>$content</p>
                    $img
                  </div>";
        }
        exit;
    }
    // ==========================================

    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 ORDER BY id DESC LIMIT 15");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ SIGNAL LOST ] Station is currently under maintenance.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Public Hologram</title>
    
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
</head>
<body class="t-crt">

    <div id="splash-overlay" class="t-splash">
        <div class="font-bold text-success" id="splash-text" style="font-size: 1.1rem; letter-spacing: 2px; text-shadow: 0 0 8px currentColor;">
            > INTERCEPTING_PUBLIC_HOLOGRAM<span class="t-loading-dots"></span>
        </div>
    </div>

    <div class="t-container mt-4">
        <header class="text-center mb-5 t-border-bottom pb-4">
            <h1 class="mb-1 text-success"><span class="t-led-dot t-led-green t-blink" style="margin-right: 8px; transform: translateY(-3px);"></span>RELAY_STATION</h1>
            <p class="text-muted m-0 fs-small">COORDINATES: relay.npc.my.id | COMMANDER: ONLINE</p>
        </header>

        <main id="signal-log">
            <h3 class="text-success mb-3">> PUBLIC_BROADCAST_LOG:</h3>
            
            <?php if (empty($transmissions)): ?>
                <div class="t-card text-center text-muted p-5" style="border-style: dashed;">
                    <p class="m-0">[ ARCHIVE EMPTY. COMMANDER HAS NOT TRANSMITTED ANY SIGNALS. ]</p>
                </div>
            <?php else: ?>
                <?php foreach ($transmissions as $msg): ?>
                    <div class="t-card mb-3 transmission-card" data-id="<?php echo $msg['id']; ?>">
                        <div class="t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap">
                            <span>
                                [ <?php echo $msg['timestamp']; ?> UTC ] <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'COMMANDER'); ?></strong>
                            </span>
                            <?php if(!empty($msg['expiry_date'])) echo '<span class="t-badge danger t-flicker">[ 👻 GHOSTED ]</span>'; ?>
                        </div>
                        <p class="m-0" style="font-size: 14px;">
                            <?php echo nl2br($msg['content']); ?>
                        </p>

                        <?php if(!empty($msg['media_url'])): ?>
                            <div class="mt-3 text-center">
                                <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Broadcast Media" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <?php if (!empty($transmissions)): ?>
            <div id="load-more" class="text-center mt-3 text-muted" style="border-top:1px dashed var(--t-green); padding-top:15px; padding-bottom:30px;">
                [ SCROLL DOWN TO SCAN DEEP SPACE ]
            </div>
        <?php endif; ?>

        <footer class="text-center mt-5 text-muted fs-small t-border-top pt-4 mb-4">
            <p class="mb-2">POWERED BY <a href="https://github.com/jeannesbryan/relay-station" class="text-success font-bold" style="text-decoration: none;">RELAY PROTOCOL</a> v3.0.6</p>
            <a href="console.php" class="text-muted" style="text-decoration: none;">[ SYSADMIN_LOGIN ] <span class="t-blink text-success">_</span></a>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        // 🔄 [ BUG FIX 3: CURSOR-BASED INFINITE SCROLL ]
        let isFetching = false;
        const loadMoreEl = document.getElementById('load-more');
        
        if (loadMoreEl) {
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting && !isFetching) {
                    isFetching = true;
                    loadMoreEl.innerText = '[ RECEIVING SIGNALS... ]';
                    
                    // Find the ID of the last message on the screen
                    const cards = document.querySelectorAll('.transmission-card');
                    if (cards.length === 0) return;
                    const lastId = cards[cards.length - 1].getAttribute('data-id');

                    fetch('index.php?last_id=' + lastId)
                    .then(r => r.text())
                    .then(html => {
                        if(html.trim() !== '') {
                            document.getElementById('signal-log').insertAdjacentHTML('beforeend', html);
                            isFetching = false;
                            loadMoreEl.innerText = '[ SCROLL DOWN TO SCAN DEEP SPACE ]';
                        } else {
                            loadMoreEl.innerText = '[ END OF TRANSMISSIONS ]';
                            observer.disconnect();
                        }
                    });
                }
            });
            observer.observe(loadMoreEl);
        }
    </script>
</body>
</html>