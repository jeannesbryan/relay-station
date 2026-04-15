<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📡 RELAY STATION: PUBLIC HOLOGRAM (SOVEREIGN PROFILE)
// The station's interface for public visitors. 
// Now strictly displays only local transmissions.
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
    // 🎛️ [ FETCH SYSTEM CONFIGURATIONS ]
    $stmt_bunker = $db->query("SELECT config_value FROM system_config WHERE config_key = 'bunker_mode' ORDER BY rowid DESC LIMIT 1");
    $bunker_mode = $stmt_bunker->fetchColumn() ?: '0';

    $stmt_name = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_name' ORDER BY rowid DESC LIMIT 1");
    $station_name = $stmt_name->fetchColumn() ?: 'RELAY_STATION';

    $stmt_bio = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_bio' ORDER BY rowid DESC LIMIT 1");
    $station_bio = $stmt_bio->fetchColumn() ?: '';

    // 🔄 [ AJAX ENDPOINT: CURSOR-BASED PAGINATION ]
    if (isset($_GET['last_id'])) {
        // Block AJAX data access if Bunker mode is active
        if ($bunker_mode == '1') { exit; }

        $last_id = (int)$_GET['last_id'];
        
        // [ SOVEREIGN LOCK ]: Only fetch is_remote = 0 (Local Transmissions)
        $stmt = $db->prepare("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 AND id < :last_id ORDER BY id DESC LIMIT 15");
        $stmt->execute([':last_id' => $last_id]);
        $transmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($transmissions as $msg) {
            $author = htmlspecialchars($msg['author_alias'] ?? 'LOCAL_COMMAND');
            $img = '';
            
            // 🗄️ V5.5 ADVANCED MEDIA MATRIX RENDERER (AJAX)
            if (!empty($msg['media_url'])) {
                $media_items = [];
                if (strpos($msg['media_url'], '[') === 0) {
                    $media_items = json_decode($msg['media_url'], true) ?? [];
                } else {
                    $media_items = [$msg['media_url']];
                }
                
                $m_count = count($media_items);
                if ($m_count > 0) {
                    $matrix_class = 'media-matrix-' . min($m_count, 4);
                    $img = '<div class="media-matrix ' . $matrix_class . '">';
                    foreach(array_slice($media_items, 0, 4) as $url) {
                        $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                        $is_audio = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a']);
                        $is_video = in_array($ext, ['mp4']);
                        
                        if ($is_audio) {
                            $img .= '<div class="matrix-item audio-cell p-2"><button type="button" class="t-btn warning w-100 audio-play-btn" data-src="'.htmlspecialchars($url).'" style="font-size: 11px;">[ ▶️ PLAY AUDIO_LOG ]</button></div>';
                        } elseif ($is_video) {
                            $img .= '<div class="matrix-item"><video class="matrix-video" controls preload="metadata"><source src="'.htmlspecialchars($url).'" type="video/mp4"></video></div>';
                        } else {
                            $img .= '<div class="matrix-item"><img src="'.htmlspecialchars($url).'" class="matrix-img" alt="Secure Media"></div>';
                        }
                    }
                    $img .= '</div>';
                }
            }
            
            echo "<div class='t-card mb-3 p-3 transmission-card' data-id='{$msg['id']}'>
                    <div class='t-bubble-meta t-border-bottom pb-2 mb-2'>
                        <span>[ {$msg['timestamp']} UTC ] LOCAL_TRANSMISSION: <strong class='text-success'>$author</strong></span>
                    </div>
                    <p class='m-0' style='font-size: 14px;'>".nl2br(htmlspecialchars($msg['content']))."</p> $img
                  </div>";
        }
        exit;
    }

    // If Bunker Mode is active, do not fetch message data into memory
    $transmissions = [];
    if ($bunker_mode == '0') {
        // [ SOVEREIGN LOCK ]: Only fetch is_remote = 0
        $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'public' AND is_remote = 0 ORDER BY id DESC LIMIT 15");
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
    <title><?php echo htmlspecialchars($station_name); ?> | Public Hologram</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
    <style>
        .bunker-seal { min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px dashed var(--t-red); background: rgba(255,0,65,0.05); }
        
        /* V5.5 THE MATRIX GRID */
        .media-matrix { display: grid; gap: 8px; margin-top: 10px; }
        .media-matrix-1 { grid-template-columns: 1fr; }
        .media-matrix-2 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 .matrix-item:first-child { grid-column: span 2; }
        .media-matrix-4 { grid-template-columns: 1fr 1fr; }
        
        .matrix-item { position: relative; overflow: hidden; border-radius: 4px; border: 1px dashed var(--t-green); background: var(--bg-base); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
        .matrix-item.audio-cell { aspect-ratio: auto; min-height: 50px; border-style: dotted; border-color: var(--t-yellow); }
        
        .matrix-img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s; }
        .matrix-img:hover { filter: none; }
        
        .matrix-video { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s; }
        .matrix-video:hover, .matrix-video:focus, .matrix-video:active { filter: none; outline: none; }
    </style>
</head>
<body class="t-crt">

    <div class="t-container mt-5">
        <header class="text-center mb-5">
            <h1 class="text-success font-bold mb-0" style="letter-spacing: 5px;">> <?php echo htmlspecialchars($station_name); ?></h1>
            
            <?php if (!empty($station_bio)): ?>
                <div class="text-muted mt-3 mb-2" style="max-width: 600px; margin: 0 auto; font-size: 0.95rem; line-height: 1.5;">
                    <?php echo nl2br(htmlspecialchars($station_bio)); ?>
                </div>
            <?php endif; ?>
            
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
                    <h2 class="t-card-header">> 📡 STATION_BROADCAST_LOG</h2>
                    
                    <div id="signal-log">
                        <?php if (empty($transmissions)): ?>
                            <div class="text-center text-muted py-5 t-border border-dashed">[ NO LOCAL BROADCASTS DETECTED ]</div>
                        <?php else: ?>
                            <?php foreach ($transmissions as $msg): ?>
                                <div class="t-card mb-3 p-3 transmission-card" data-id="<?php echo $msg['id']; ?>">
                                    <div class="t-bubble-meta t-border-bottom pb-2 mb-2">
                                        <span>
                                            [ <?php echo $msg['timestamp']; ?> UTC ] LOCAL_TRANSMISSION:  
                                            <strong class="text-success"><?php echo htmlspecialchars($msg['author_alias'] ?? 'LOCAL_COMMAND'); ?></strong>
                                        </span>
                                    </div>
                                    <p class="m-0" style="font-size: 14px;">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                    </p>
                                    
                                    <?php if(!empty($msg['media_url'])): 
                                        $media_items = [];
                                        if (strpos($msg['media_url'], '[') === 0) {
                                            $media_items = json_decode($msg['media_url'], true) ?? [];
                                        } else {
                                            $media_items = [$msg['media_url']];
                                        }
                                        
                                        $m_count = count($media_items);
                                        if ($m_count > 0):
                                    ?>
                                        <div class="media-matrix media-matrix-<?php echo min($m_count, 4); ?>">
                                            <?php foreach(array_slice($media_items, 0, 4) as $url): 
                                                $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                                                $is_audio = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a']);
                                                $is_video = in_array($ext, ['mp4']);
                                            ?>
                                                <?php if($is_audio): ?>
                                                    <div class="matrix-item audio-cell p-2">
                                                        <button type="button" class="t-btn warning w-100 audio-play-btn" data-src="<?php echo htmlspecialchars($url); ?>" style="font-size: 11px;">
                                                            [ ▶️ PLAY AUDIO_LOG ]
                                                        </button>
                                                    </div>
                                                <?php elseif($is_video): ?>
                                                    <div class="matrix-item">
                                                        <video class="matrix-video" controls preload="metadata">
                                                            <source src="<?php echo htmlspecialchars($url); ?>" type="video/mp4">
                                                        </video>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="matrix-item">
                                                        <img src="<?php echo htmlspecialchars($url); ?>" class="matrix-img" alt="Transmission Media">
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($transmissions)): ?>
                        <div id="load-more" class="text-center mt-3 text-muted" style="border-top:1px dashed var(--t-green); padding-top:15px; padding-bottom:30px;">
                            [ SCROLL DOWN TO SCAN ARCHIVES ]
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        <?php endif; ?>

        <footer class="text-center text-muted t-border-top pt-4 pb-5 mt-5">
            <p class="mb-2">> SIGNAL STATUS: <?php echo ($bunker_mode == '1') ? '<span class="text-danger">[ LOCKED ]</span>' : '<span class="text-success">[ BROADCASTING ]</span>'; ?></p>
            <p class="fs-small m-0"><a href="https://github.com/jeannesbryan/relay-station" target="_blank" class="text-muted" style="text-decoration: underline;">RELAY_STATION v<?php echo htmlspecialchars($station_version); ?></a></p>
        </footer>
    </div>

    <script>
        // ==========================================
        // ▶️ [ DELEGATED RETRO AUDIO PLAYER ]
        // ==========================================
        let currentAudio = null;
        let currentBtn = null;

        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('audio-play-btn')) {
                const btn = e.target;
                const src = btn.getAttribute('data-src');
                
                if (currentAudio && currentBtn === btn) {
                    if (!currentAudio.paused) {
                        currentAudio.pause();
                        btn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                        btn.classList.remove('t-blink');
                        return;
                    } else {
                        currentAudio.play();
                        btn.innerText = '[ ⏸️ PLAYING... ]';
                        btn.classList.add('t-blink');
                        return;
                    }
                }
                
                if (currentAudio) {
                    currentAudio.pause();
                    if(currentBtn) {
                        currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                        currentBtn.classList.remove('t-blink');
                    }
                }
                
                currentAudio = new Audio(src);
                currentBtn = btn;
                currentBtn.innerText = '[ ⏸️ PLAYING... ]';
                currentBtn.classList.add('t-blink');
                
                currentAudio.play();
                
                currentAudio.onended = () => {
                    currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                    currentBtn.classList.remove('t-blink');
                };
            }
        });

        // 🔄 [ CURSOR-BASED INFINITE SCROLL ]
        let isFetching = false;
        const loadMoreEl = document.getElementById('load-more');
        const bunkerActive = <?php echo $bunker_mode; ?>;
        
        if (loadMoreEl && bunkerActive === 0) {
            const observer = new IntersectionObserver((entries) => {
                if(entries[0].isIntersecting && !isFetching) {
                    isFetching = true;
                    loadMoreEl.innerText = '[ RETRIEVING ARCHIVES... ]';
                    
                    const cards = document.querySelectorAll('.transmission-card');
                    if (cards.length === 0) return;
                    const lastId = cards[cards.length - 1].getAttribute('data-id');

                    fetch('index.php?last_id=' + lastId)
                    .then(r => r.text())
                    .then(html => {
                        if(html.trim() !== '') {
                            document.getElementById('signal-log').insertAdjacentHTML('beforeend', html);
                            isFetching = false;
                            loadMoreEl.innerText = '[ SCROLL DOWN TO SCAN ARCHIVES ]';
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