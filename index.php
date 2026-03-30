<?php
// RELAY STATION: PUBLIC HOLOGRAM
// Menampilkan arsip pemikiran (Broadcast) milik Kapten kepada publik.

$db_file = 'data/relay_core.sqlite';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // HANYA ambil pesan Publik yang ditulis oleh Kapten sendiri (is_remote = 0)
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 ORDER BY timestamp DESC LIMIT 50");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("<h3 style='color:red;'>[ SIGNAL LOST ] Stasiun sedang dalam perbaikan.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | npc.my.id</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body style="max-width: 800px; margin: 0 auto; padding-top: 50px;">

    <header style="text-align: center; margin-bottom: 40px; border-bottom: 2px dashed var(--text-dim); padding-bottom: 20px;">
        <h1 style="border: none; margin-bottom: 5px;"><span class="blink">_</span>RELAY_STATION</h1>
        <p style="color: var(--text-dim); margin-top: 0;">COORDINATES: relay.npc.my.id | COMMANDER: ONLINE</p>
    </header>

    <main>
        <h3 style="color: var(--text-dim);">> PUBLIC_BROADCAST_LOG:</h3>
        
        <?php if (empty($transmissions)): ?>
            <div class="console-box" style="text-align: center; opacity: 0.5;">
                <p>[ ARSIP KOSONG. KAPTEN BELUM MEMANCARKAN SINYAL. ]</p>
            </div>
        <?php else: ?>
            <?php foreach ($transmissions as $msg): ?>
                <div class="console-box" style="margin-bottom: 20px;">
                    <small style="opacity: 0.7;">
                        [ <?php echo $msg['timestamp']; ?> UTC ] 
                        <strong style="color: #fff;"><?php echo htmlspecialchars($msg['author_alias'] ?? 'COMMANDER'); ?></strong>
                    </small>
                    <p style="margin: 10px 0 0 0; font-size: 1.1em;">
                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <footer style="text-align: center; margin-top: 50px; opacity: 0.5; font-size: 0.8em;">
        <p>POWERED BY <a href="https://github.com/USERNAME_ANDA/relay-station" style="color: var(--text-main);">RELAY PROTOCOL</a></p>
        <a href="console.php" style="color: var(--bg-color);">[ SYSADMIN ]</a> </footer>

</body>
</html>