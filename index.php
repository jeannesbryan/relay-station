<?php
// RELAY STATION: PUBLIC HOLOGRAM
$db_file = 'data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 ORDER BY timestamp DESC LIMIT 50");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ SIGNAL LOST ] Stasiun sedang dalam perbaikan.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | npc.my.id</title>
    
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
            <h1 class="mb-1 text-success"><span class="t-blink">_</span>RELAY_STATION</h1>
            <p class="text-muted m-0 fs-small">COORDINATES: relay.npc.my.id | <span class="t-led-dot t-led-green"></span> COMMANDER: ONLINE</p>
        </header>

        <main>
            <h3 class="text-success mb-3">> PUBLIC_BROADCAST_LOG:</h3>
            
            <?php if (empty($transmissions)): ?>
                <div class="t-card text-center text-muted p-5" style="border-style: dashed;">
                    <p class="m-0">[ ARSIP KOSONG. KAPTEN BELUM MEMANCARKAN SINYAL. ]</p>
                </div>
            <?php else: ?>
                <?php foreach ($transmissions as $msg): ?>
                    <div class="t-card mb-3">
                        <span class="t-bubble-meta t-border-bottom pb-2 mb-2">
                            [ <?php echo $msg['timestamp']; ?> UTC ] <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'COMMANDER'); ?></strong>
                        </span>
                        <p class="m-0" style="font-size: 14px;">
                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <footer class="text-center mt-5 text-muted fs-small t-border-top pt-4">
            <p class="mb-2">POWERED BY <a href="https://github.com/jeannesbryan/relay-station" class="text-success font-bold" style="text-decoration: none;">RELAY PROTOCOL</a></p>
            <a href="console.php" class="text-muted" style="text-decoration: none;">[ SYSADMIN_LOGIN ]</a>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
</body>
</html>