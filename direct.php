<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
$db_file = 'data/relay_core.sqlite';

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    header("Location: console.php"); exit;
}

date_default_timezone_set('UTC'); 

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'direct' ORDER BY timestamp ASC");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

    // Mengelompokkan pesan berdasarkan domain rekanan
    $chat_threads = [];
    foreach ($transmissions as $msg) {
        $partner_domain = '';
        if ($msg['is_remote'] == 1) {
            $parts = explode('@', $msg['author_alias']);
            $partner_domain = end($parts);
        } else {
            $partner_domain = parse_url($msg['target_planet'], PHP_URL_HOST);
        }
        if (!empty($partner_domain)) {
            $chat_threads[$partner_domain][] = $msg;
        }
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
    <title>RELAY | Direct Messages</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
    <style>
        .chat-thread { display: none; }
        .chat-thread.active { display: block; }
        .contact-item { cursor: pointer; transition: 0.2s; }
        .contact-item:hover { border-color: var(--t-green) !important; background: rgba(0,255,65,0.05); }
        .contact-item.active { border-color: var(--t-green) !important; background: rgba(0,255,65,0.1); }
        #chat-container { max-height: 60vh; overflow-y: auto; padding-right: 10px; }
        /* Custom Scrollbar */
        #chat-container::-webkit-scrollbar { width: 6px; }
        #chat-container::-webkit-scrollbar-track { background: var(--t-black); border-left: 1px dashed var(--t-green-dim); }
        #chat-container::-webkit-scrollbar-thumb { background: var(--t-green-dim); }
    </style>
</head>
<body class="t-crt">

    <div class="t-container-fluid mt-4">
        <nav class="t-navbar mb-4">
            <div class="t-nav-brand"><span class="t-led-dot t-led-yellow"></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">> SECURE_COMMS v2.0</span></div>
            <div class="t-nav-menu">
                <a href="console.php" class="t-btn t-btn-sm">[ RETURN_TO_TIMELINE ]</a>
            </div>
        </nav>

        <div class="t-grid-layout" style="grid-template-columns: 300px 1fr;">
            
            <aside class="t-side-panel">
                <h2 class="t-card-header">> 📡 ENCRYPTED CHANNELS</h2>
                
                <div class="t-card mb-3 p-2 text-center contact-item" onclick="openChat('NEW', this)" style="border-style: dashed;">
                    <span class="text-warning font-bold">[ + INITIATE NEW LINK ]</span>
                </div>

                <div class="t-list-group">
                    <?php if (empty($chat_threads)): ?>
                        <div class="t-list-item text-center text-muted">[ NO ACTIVE CHANNELS ]</div>
                    <?php else: ?>
                        <?php foreach (array_keys($chat_threads) as $domain): ?>
                            <div class="t-list-item contact-item" onclick="openChat('<?php echo htmlspecialchars($domain); ?>', this)">
                                <span class="t-list-item-title">> <?php echo htmlspecialchars($domain); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </aside>

            <main class="t-main-panel d-flex flex-column" style="height: calc(100vh - 100px);">
                
                <div id="empty-state" class="t-card text-center text-muted p-5 m-auto w-100" style="border-style: dashed;">
                    > SELECT A CHANNEL TO BEGIN SECURE TRANSMISSION...
                </div>

                <div id="chat-container" class="flex-grow-1">
                    <?php foreach ($chat_threads as $domain => $thread): ?>
                        <div id="thread-<?php echo htmlspecialchars($domain); ?>" class="chat-thread pb-3">
                            <h3 class="text-warning mb-4 t-border-bottom pb-2">> LINK ESTABLISHED: <?php echo htmlspecialchars($domain); ?></h3>
                            
                            <?php foreach ($thread as $msg): 
                                $is_me = ($msg['is_remote'] == 0);
                            ?>
                                <div class="mb-3 d-flex flex-column <?php echo $is_me ? 'align-items-end' : 'align-items-start'; ?>">
                                    <div class="fs-small text-muted mb-1">
                                        <?php echo $is_me ? 'LOCAL_COMMAND' : htmlspecialchars($msg['author_alias']); ?> 
                                        [<?php echo date('H:i', strtotime($msg['timestamp'])); ?>]
                                        <?php if(!empty($msg['expiry_date'])) echo '<span class="text-danger t-flicker"> [👻]</span>'; ?>
                                    </div>
                                    <div class="p-2 px-3" style="
                                        max-width: 80%; 
                                        border: 1px solid var(--t-<?php echo $is_me ? 'green' : 'yellow'; ?>); 
                                        background: rgba(<?php echo $is_me ? '0,255,65,0.05' : '255,255,0,0.05'; ?>);
                                        border-radius: 4px;
                                    ">
                                        <p class="m-0" style="font-size: 14px; word-break: break-word;">
                                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        </p>
                                        
                                        <?php if(!empty($msg['media_url'])): ?>
                                            <div class="mt-2 text-<?php echo $is_me ? 'right' : 'left'; ?>">
                                                <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Secure Media" style="max-width: 100%; border: 1px dashed var(--t-<?php echo $is_me ? 'green' : 'yellow'; ?>); border-radius: 4px;">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form action="core/transmitter.php" method="POST" enctype="multipart/form-data" id="reply-form" style="display:none;" class="m-0 mt-3 t-card p-3">
                    <input type="hidden" name="visibility" value="direct">
                    <div class="mb-2">
                        <label class="t-form-label">> TARGET_COORDINATES (URL)</label>
                        <input type="url" name="target_planet" id="target-input" class="t-input m-0 text-warning font-bold" placeholder="https://domain.com" required>
                    </div>
                    <textarea name="content" rows="2" class="t-textarea mb-2" placeholder="> Enter secure transmission..." required></textarea>

                    <div class="mb-3">
                        <input type="file" name="media" accept="image/*" class="t-input m-0" style="padding: 6px; font-size: 0.9rem;">
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <label class="t-checkbox-label text-danger m-0">
                            <input type="checkbox" name="ghost_protocol" value="1"><span class="t-checkmark"></span> [!] GHOST (24H)
                        </label>
                        <button type="submit" class="t-btn font-bold t-glow">[ SEND_DIRECT ]</button>
                    </div>
                </form>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        document.getElementById('reply-form').addEventListener('submit', () => { Terminal.splash.show('> ENCRYPTING_TRANSMISSION...'); });

        function openChat(domain, element) {
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('reply-form').style.display = 'block';
            
            let items = document.querySelectorAll('.contact-item');
            items.forEach(i => i.classList.remove('active'));
            element.classList.add('active');
            
            var threads = document.getElementsByClassName('chat-thread');
            for (var i = 0; i < threads.length; i++) {
                threads[i].classList.remove('active');
            }
            
            var targetInput = document.getElementById('target-input');
            if (domain === 'NEW') {
                targetInput.value = '';
                targetInput.focus();
                targetInput.removeAttribute('readonly');
            } else {
                targetInput.value = 'https://' + domain;
                targetInput.setAttribute('readonly', true);
                var activeThread = document.getElementById('thread-' + domain);
                if(activeThread) {
                    activeThread.classList.add('active');
                    // Scroll to bottom
                    var container = document.getElementById('chat-container');
                    container.scrollTop = container.scrollHeight;
                }
            }
        }
    </script>
</body>
</html>