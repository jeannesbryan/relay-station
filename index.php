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
    
    <style>
        .t-blink { animation: terminal-blink 1s linear infinite; }
        @keyframes terminal-blink { 50% { opacity: 0; } }
    </style>
</head>
<body>

    <div class="t-container">
        <header style="text-align: center; margin-bottom: 40px; border-bottom: 2px dashed var(--t-green-dim); padding-bottom: 20px;">
            <h1 style="border: none; margin-bottom: 5px;"><span class="t-blink">_</span>RELAY_STATION</h1>
            <p style="color: var(--t-green-dim); margin-top: 0; font-size: 12px;">COORDINATES: relay.npc.my.id | <span class="t-led-dot t-led-green"></span> COMMANDER: ONLINE</p>
        </header>

        <main>
            <h3 style="color: var(--t-green-dim); border: none; font-size: 14px; margin-bottom: 15px;">> PUBLIC_BROADCAST_LOG:</h3>
            
            <?php if (empty($transmissions)): ?>
                <div class="t-card" style="text-align: center; opacity: 0.5;">
                    <p>[ ARSIP KOSONG. KAPTEN BELUM MEMANCARKAN SINYAL. ]</p>
                </div>
            <?php else: ?>
                <?php foreach ($transmissions as $msg): ?>
                    <div class="t-card" style="margin-bottom: 15px;">
                        <span class="t-bubble-meta">
                            [ <?php echo $msg['timestamp']; ?> UTC ] <strong style="color: #fff;"><?php echo htmlspecialchars($msg['author_alias'] ?? 'COMMANDER'); ?></strong>
                        </span>
                        <p style="margin-top: 10px; font-size: 14px;">
                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </main>

        <footer style="text-align: center; margin-top: 50px; opacity: 0.5; font-size: 12px;">
            <p>POWERED BY <a href="https://github.com/jeannesbryan/relay-station" style="color: var(--t-green);">RELAY PROTOCOL</a></p>
            <a href="console.php" style="color: var(--bg-base);">[ SYSADMIN ]</a>
        </footer>
    </div>

</body>
</html>