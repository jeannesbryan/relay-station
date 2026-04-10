<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: PUBLIC HOLOGRAM
// The station's interface for public visitors (Read-Only)
// ==========================================

// ⚙️ [ AUTO-DETECT SYSTEM COORDINATES ]
$host = $_SERVER['HTTP_HOST'] ?? 'UNKNOWN_NODE';
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$station_coordinates = $host . $base_path;

// ⚙️ [ AUTO-DETECT SYSTEM VERSION ]
$station_version = 'UNKNOWN';
if (file_exists('version.json')) {
    $v_data = json_decode(file_get_contents('version.json'), true);
    if (isset($v_data['version'])) {
        $station_version = $v_data['version'];
    }
}

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

try {
    // ==========================================
    // 🚧 [ CHECK STATION MODE: BUNKER STATUS ]
    // [ BUG FIX ]: Selalu ambil baris data terbaru (terbawah) dari tabel
    // ==========================================
    $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode' ORDER BY rowid DESC LIMIT 1");
    $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';

    // 🔄 [ AJAX ENDPOINT: CURSOR-BASED PAGINATION ]
    if (isset($_GET['last_id'])) {
        // Block AJAX data access if Bunker mode is active
        if ($bunker_mode == '1') { exit; }

        $last_id = (int)$_GET['last_id'];
        // [ BUG FIX ]: Hapus filter is_remote = 1 agar pesan lokal juga terambil
        $stmt = $db->prepare("SELECT * FROM transmissions WHERE visibility = 'public' AND id < :last_id ORDER BY id DESC LIMIT 15");
        $stmt->execute([':last_id' => $last_id]);
        $transmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transmissions as $msg) {
            $author = htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN');
            $img = !empty($msg['media_url']) ? '<div class="mt-3 text-center"><img src="'.htmlspecialchars($msg['media_url']).'" class="t-hologram-img" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;"></div>' : '';
            $src_label = $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:';
            
            echo "<div class='t-card mb-3 p-3 transmission-card' data-id='{$msg['id']}'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2'>
                        <span>[ {$msg['timestamp']} UTC ] $src_label <strong class='text-success'>$author</strong></span>
                    </div>
                    <p class='m-0' style='font-size: 14px;'>".nl2br(htmlspecialchars($msg['content']))."</p> $img
                  </div>";
        }
        exit;
    }

    // If Bunker Mode is active, do not fetch message data into memory
    $transmissions = [];
    if ($bunker_mode == '0') {
        // [ BUG FIX ]: Hapus filter is_remote = 1
        $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' ORDER BY id DESC LIMIT 15");
        $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ CRITICAL ERROR ] Core Memory Offline.</h3>");
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
    <style>
        .t-hologram-img { filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); }
        .bunker-seal { min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px dashed var(--t-red); background: rgba(255,0,65,0.05); }
    </style>
</head>
<body class="t-crt">

    <div class="t-container mt-5">
        <header class="text-center mb-5">
            <h1 class="text-success font-bold mb-0" style="letter-spacing: 5px;">> RELAY_STATION</h1>
            <div class="text-muted fs-small mt-2">[ COORDINATES: <?php echo htmlspecialchars($station_coordinates); ?> ]</div>
        </header>

        <?php if ($bunker_mode == '1'): ?>
            <div class="bunker-seal t-card danger text-center p-5">
                <h2 class="t-flicker text-danger font-bold mb-3" style="font-size: 2rem;">[ 🚧 RESTRICTED AREA ]</h2>
                <p class="text-muted mb-4">> THIS NODE HAS ENTERED PRIVATE BUNKER MODE.<br>> ALL PUBLIC TRANSMISSIONS ARE CURRENTLY SEALED.</p>
                <div class="t-loading-dots text-danger" style="font-size: 24px;"></div>
                <div class="mt-5">
                    <a href="console.php" class="t-btn danger">[ COMMANDER_LOGIN ]</a>
                </div>
            </div>
        <?php else: ?>
            <div class="t-grid-layout" style="grid-template-columns: 1fr;">
                <main class="t-main-panel">
                    <h2 class="t-card-header">> 📡 INCOMING_TRANSMISSIONS_LOG</h2>
                    
                    <div id="signal-log">
                        <?php if (empty($transmissions)): ?>
                            <div class="text-center text-muted py-5 t-border border-dashed">[ NO PUBLIC SIGNALS DETECTED ]</div>
                        <?php else: ?>
                            <?php foreach ($transmissions as $msg): ?>
                                <div class="t-card mb-3 p-3 transmission-card" data-id="<?php echo $msg['id']; ?>">
                                    <div class="t-bubble-meta t-border-bottom pb-2 mb-2">
                                        <span>
                                            [ <?php echo $msg['timestamp']; ?> UTC ] 
                                            <?php echo $msg['is_remote'] ? 'INCOMING FROM:' : 'LOCAL_AUTHOR:'; ?> 
                                            <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN'); ?></strong>
                                        </span>
                                    </div>
                                    <p class="m-0" style="font-size: 14px;">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                    </p>
                                    <?php if(!empty($msg['media_url'])): ?>
                                        <div class="mt-3 text-center">
                                            <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" class="t-hologram-img" alt="Transmission" style="max-width: 100%; border: 1px dashed var(--t-green); border-radius: 4px;">
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
            </div>
        <?php endif; ?>

        <footer class="text-center text-muted t-border-top pt-4 pb-5 mt-5">
            <p class="mb-2">> SIGNAL STATUS: <?php echo ($bunker_mode == '1') ? '<span class="text-danger">[ LOCKED ]</span>' : '<span class="text-success">[ BROADCASTING ]</span>'; ?></p>
            <p class="fs-small m-0">STATION_OS v<?php echo htmlspecialchars($station_version); ?> | COORDINATES: <?php echo htmlspecialchars($station_coordinates); ?></p>
        </footer>
    </div>

    <script>
        // 🔄 [ BUG FIX: CURSOR-BASED INFINITE SCROLL ]
        let isFetching = false;
        const loadMoreEl = document.getElementById('load-more');
        const bunkerActive = <?php echo $bunker_mode; ?>;
        
        if (loadMoreEl && bunkerActive === 0) {
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting && !isFetching) {
                    isFetching = true;
                    loadMoreEl.innerText = '[ RECEIVING SIGNALS... ]';
                    
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