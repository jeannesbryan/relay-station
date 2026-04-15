<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 📌 RELAY STATION: THE MEMORY VAULT (BOOKMARKS)
// V7.2 - Displays saved transmissions using INNER JOIN.
// Automatically purges if the original transmission is deleted.
// ==========================================

session_start();
date_default_timezone_set('UTC'); 

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    header("Location: console.php");
    exit;
}

require_once 'core/db_connect.php';

// ⚙️ [ AUTO-DETECT SYSTEM VERSION ]
$station_version = 'UNKNOWN';
if (file_exists('version.json')) {
    $v_data = json_decode(file_get_contents('version.json'), true);
    if (isset($v_data['version'])) { $station_version = $v_data['version']; }
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname($_SERVER['SCRIPT_NAME']);
if ($base_path === '\\' || $base_path === '/') $base_path = '';
$current_local_url = rtrim($protocol . $host . $base_path, '/');

// 📌 [ V7.2 ] ACTION PROCESSOR: TOGGLE BOOKMARK (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'toggle_bookmark') {
    $tid = (int)$_POST['id'];
    $stmt_check = $db->prepare("SELECT id FROM bookmarks WHERE transmission_id = ?");
    $stmt_check->execute([$tid]);
    if ($stmt_check->fetchColumn()) {
        $db->prepare("DELETE FROM bookmarks WHERE transmission_id = ?")->execute([$tid]);
        echo "REMOVED";
    } else {
        $db->prepare("INSERT INTO bookmarks (transmission_id) VALUES (?)")->execute([$tid]);
        echo "SAVED";
    }
    exit;
}

try {
    // 🗄️ [ THE INNER JOIN ENGINE ]
    // Only fetch transmissions that exist in both 'transmissions' and 'bookmarks' tables.
    // If a post is deleted by Scorched Earth or Ghost Protocol, it vanishes here too.
    $query = $db->query("
        SELECT t.*, b.bookmarked_at 
        FROM transmissions t 
        INNER JOIN bookmarks b ON t.id = b.transmission_id 
        ORDER BY b.bookmarked_at DESC
    ");
    $bookmarked_transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $stmt_alerts = $db->query("SELECT * FROM alerts WHERE is_read = 0 ORDER BY id DESC");
    $active_alerts = $stmt_alerts->fetchAll(PDO::FETCH_ASSOC);

    $stmt_name = $db->query("SELECT config_value FROM system_config WHERE config_key = 'station_name' ORDER BY rowid DESC LIMIT 1");
    $station_name = $stmt_name->fetchColumn() ?: 'RELAY_STATION';

} catch (PDOException $e) {
    die("<h3 class='t-alert danger'>[ CRITICAL ERROR ] Core Memory Data Fetch Failed.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Bookmarks Vault</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
    <style>
        .media-matrix { display: grid; gap: 8px; margin-top: 10px; }
        .media-matrix-1 { grid-template-columns: 1fr; }
        .media-matrix-2 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 { grid-template-columns: 1fr 1fr; }
        .media-matrix-3 .matrix-item:first-child { grid-column: span 2; }
        .media-matrix-4 { grid-template-columns: 1fr 1fr; }
        
        .matrix-item { position: relative; overflow: hidden; border-radius: 4px; border: 1px dashed var(--t-green); background: var(--bg-base); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
        .matrix-item.audio-cell { aspect-ratio: auto; min-height: 50px; border-style: dotted; border-color: var(--t-yellow); }
        .matrix-img, .matrix-video { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%) sepia(100%) hue-rotate(80deg) brightness(0.7) contrast(1.2); transition: 0.3s; }
        .matrix-img:hover, .matrix-video:hover { filter: none; }
    </style>
</head>
<body class="t-crt">

    <div class="t-container-fluid pt-0">
        <nav class="t-navbar mt-3 mb-4">
            <div class="t-nav-brand">
                <span class="t-led-dot t-led-yellow"></span> <?php echo htmlspecialchars($station_name); ?> 
                <span class="fs-small text-muted fw-normal ml-2">v<?php echo htmlspecialchars($station_version); ?> | VAULT</span>
            </div>
            <div class="t-nav-menu">
                <a href="console.php" class="t-btn warning t-btn-sm" title="Return to Radar">[ 🔙 BACK ]</a>
            </div>
        </nav>

        <div class="t-grid-layout">
            <main class="t-main-panel">
                <h2 class="t-card-header text-warning">> 📌 THE MEMORY VAULT (BOOKMARKS)</h2>
                
                <div id="signal-log">
                    <?php if (empty($bookmarked_transmissions)): ?>
                        <div class="text-center text-muted py-5 t-border border-dashed">
                            [ VAULT IS EMPTY ]<br><br>
                            <span class="fs-small">> Pin signals from the Timeline to store them here safely.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($bookmarked_transmissions as $msg): 
                            $is_me = ($msg['is_remote'] == 0);
                            $author_disp = htmlspecialchars($msg['author_alias'] ?? 'UNKNOWN');

                            $stmt_res_count = $db->prepare("SELECT COUNT(*) FROM signal_resonance WHERE post_id = ?");
                            $stmt_res_count->execute([$msg['id']]);
                            $res_count = $stmt_res_count->fetchColumn();

                            $stmt_my_res = $db->prepare("SELECT COUNT(*) FROM signal_resonance WHERE post_id = ? AND reactor_url = ?");
                            $stmt_my_res->execute([$msg['id'], $current_local_url]);
                            $has_roger = $stmt_my_res->fetchColumn() > 0;

                            $target_planet_url = '';
                            if ($msg['is_remote'] == 1) {
                                $parts = explode('@', $msg['author_alias']);
                                if (count($parts) > 1) { $target_planet_url = 'https://' . end($parts); }
                            }

                            $roger_btn_text = $has_roger ? '[ ✓ ACKNOWLEDGED ]' : '[ 📻 ROGER THAT ]';
                            $roger_btn_class = $has_roger ? 'success' : '';
                        ?>
                            <div class="t-card mb-3 p-3 transmission-card" id="bookmark-card-<?php echo $msg['id']; ?>">
                                <div class="t-bubble-meta t-border-bottom pb-2 mb-2 d-flex justify-content-between flex-wrap gap-2">
                                    <span>
                                        [ <?php echo date('Y-m-d H:i', strtotime($msg['bookmarked_at'])); ?> SAVED ] 
                                        <?php echo $is_me ? 'LOCAL_AUTHOR:' : 'INCOMING FROM:'; ?> 
                                        <strong class="text-success"><?php echo $author_disp; ?></strong>
                                        <?php if(!empty($msg['expiry_date'])) echo '<span class="t-badge danger t-flicker ml-2">[ 👻 GHOSTED ]</span>'; ?>
                                    </span>
                                    <div class="d-flex gap-2">
                                        <button type="button" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($msg['content'], ENT_QUOTES); ?>', '<?php echo $author_disp; ?>')" class="t-btn t-btn-sm" style="padding: 1px 5px; font-size: 9px; line-height: 1; border-color: var(--t-yellow-dim); color: var(--t-yellow-dim);">[ 📋 COPY ]</button>
                                    </div>
                                </div>
                                <p class="m-0 timeline-msg" style="font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                </p>
                                
                                <?php if(!empty($msg['media_url'])): 
                                    $media_items = [];
                                    if (strpos($msg['media_url'], '[') === 0) { $media_items = json_decode($msg['media_url'], true) ?? []; } 
                                    else { $media_items = [$msg['media_url']]; }
                                    
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
                                                    <button type="button" class="t-btn warning w-100 audio-play-btn" data-src="<?php echo htmlspecialchars($url); ?>" style="font-size: 11px;">[ ▶️ PLAY AUDIO_LOG ]</button>
                                                </div>
                                            <?php elseif($is_video): ?>
                                                <div class="matrix-item"><video class="matrix-video" controls preload="metadata"><source src="<?php echo htmlspecialchars($url); ?>" type="video/mp4"></video></div>
                                            <?php else: ?>
                                                <div class="matrix-item"><img src="<?php echo htmlspecialchars($url); ?>" class="matrix-img" alt="Saved Media"></div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; endif; ?>

                                <div class='mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2' style='border-top: 1px dashed rgba(255,255,0,0.2);'>
                                    <div class='d-flex gap-2'>
                                        <button type='button' onclick="toggleRogerThat(this, <?php echo $msg['id']; ?>, '<?php echo htmlspecialchars($target_planet_url); ?>')" class='t-btn t-btn-sm <?php echo $roger_btn_class; ?>' style='padding: 2px 6px; font-size: 10px;'><?php echo $roger_btn_text; ?></button>
                                        <button type='button' onclick="removeBookmark(<?php echo $msg['id']; ?>)" class='t-btn t-btn-sm warning' style='padding: 2px 6px; font-size: 10px;'>[ 📌 SAVED (CLICK TO REMOVE) ]</button>
                                    </div>
                                    <span class='fs-small text-muted' style='font-size: 11px;'>ROGER_COUNT: <strong class='text-success'><?php echo $res_count; ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3 text-muted" style="border-top:1px dashed var(--t-yellow); padding-top:15px; padding-bottom:30px;">
                            [ END OF VAULT RECORDS ]
                        </div>
                    <?php endif; ?>
                </div>
            </main>

            <aside class="t-side-panel">
                <h2 class="t-card-header">> 🧭 NAVIGATION</h2>
                <div class="t-list-group mb-4">
                    <a href="console.php" class="t-list-item">
                        <span class="t-list-item-title">> 🌐 TIMELINE</span>
                    </a>
                    <a href="bookmarks.php" class="t-list-item active" style="border-color: var(--t-yellow);">
                        <span class="t-list-item-title text-warning">> 📌 BOOKMARKS</span>
                    </a>
                    <a href="direct.php" class="t-list-item">
                        <span class="t-list-item-title">> ✉️ DIRECT MESSAGES</span>
                    </a>
                </div>

                <?php if (!empty($active_alerts)): ?>
                    <h2 class="t-card-header text-warning t-blink">> 🔔 ALERTS (<?php echo count($active_alerts); ?>)</h2>
                    <div class="t-list-group mb-4">
                        <div class="t-card p-2 mb-2 text-center fs-small text-muted" style="border-color: var(--t-yellow); border-style: dashed;">
                            > Unread alerts detected.<br>> Return to <a href="console.php" class="text-warning">Timeline</a> to manage.
                        </div>
                    </div>
                <?php endif; ?>

                <div class="t-card p-3" style="border-color: var(--t-yellow); background: rgba(255,255,0,0.05);">
                    <span class="font-bold text-warning">> ℹ️ VAULT INTEL</span>
                    <p class="fs-small text-muted mt-2 mb-0" style="line-height: 1.4;">
                        > Signals pinned here are anchored to the local database via INNER JOIN protocol.<br><br>
                        > If the origin node executes a Global Purge or Ghost Protocol self-destruct, the signal will be wiped from this vault automatically to maintain data parity.
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        async function toggleRogerThat(btn, id, target) {
            if (btn.classList.contains('success')) return; 
            btn.innerText = '[ TRANSMITTING... ]'; btn.disabled = true;
            const fd = new FormData(); fd.append('visibility', 'resonance'); fd.append('post_id', id); fd.append('target_planet', target); fd.append('content', 'roger');
            try {
                const res = await fetch('core/transmitter.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.status === 'success') { btn.innerText = '[ ✓ ACKNOWLEDGED ]'; btn.classList.add('success'); } 
                else { btn.innerText = '[ 📻 ROGER THAT ]'; btn.disabled = false; Terminal.toast(data.message, 'danger'); }
            } catch(e) { btn.innerText = '[ 📻 ROGER THAT ]'; btn.disabled = false; }
        }

        async function removeBookmark(id) {
            const card = document.getElementById('bookmark-card-' + id);
            card.style.opacity = '0.5';
            
            const fd = new FormData();
            fd.append('action', 'toggle_bookmark');
            fd.append('id', id);
            
            try {
                const res = await fetch('bookmarks.php', { method: 'POST', body: fd });
                const status = await res.text();
                
                if (status.trim() === 'REMOVED') {
                    Terminal.toast('[✓] SIGNAL PURGED FROM VAULT', 'warning');
                    card.style.transition = '0.3s';
                    card.style.transform = 'scale(0.95)';
                    setTimeout(() => card.remove(), 300);
                }
            } catch(e) { card.style.opacity = '1'; }
        }

        function copyToClipboard(btn, text, author) {
            const formatted = `> [ TRANSMISI DARI: ${author} ]\n> ${text}`;
            navigator.clipboard.writeText(formatted).then(() => {
                const originalText = btn.innerText;
                btn.innerText = '[ ✓ COPIED ]';
                btn.classList.add('warning');
                Terminal.toast('[✓] SIGNAL SECURED TO CLIPBOARD', 'success');
                setTimeout(() => { btn.innerText = originalText; btn.classList.remove('warning'); }, 2000);
            });
        }
        
        // Audio Player Engine (Delegated)
        let currentAudio = null; let currentBtn = null;
        document.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('audio-play-btn')) {
                const btn = e.target; const src = btn.getAttribute('data-src');
                if (currentAudio && currentBtn === btn) {
                    if (!currentAudio.paused) { currentAudio.pause(); btn.innerText = '[ ▶️ PLAY AUDIO_LOG ]'; btn.classList.remove('t-blink'); return; } 
                    else { currentAudio.play(); btn.innerText = '[ ⏸️ PLAYING... ]'; btn.classList.add('t-blink'); return; }
                }
                if (currentAudio) { currentAudio.pause(); if(currentBtn) { currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]'; currentBtn.classList.remove('t-blink'); } }
                currentAudio = new Audio(src); currentBtn = btn; currentBtn.innerText = '[ ⏸️ PLAYING... ]'; currentBtn.classList.add('t-blink');
                currentAudio.play();
                currentAudio.onended = () => { currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]'; currentBtn.classList.remove('t-blink'); };
            }
        });
    </script>
</body>
</html>