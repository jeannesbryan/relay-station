<?php
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
$db_file = 'data/relay_core.sqlite';

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    header("Location: console.php"); exit;
}

date_default_timezone_set('UTC'); 

$terminal_patch = '
<style>
    .t-textarea { width: 100%; background: transparent; color: var(--t-green); border: 1px solid var(--t-green-dim); padding: 10px; font-family: var(--t-font); font-size: 14px; outline: none; margin-bottom: 15px; resize: vertical; transition: 0.2s; }
    .t-textarea:focus { border-color: var(--t-green); box-shadow: 0 0 8px rgba(0, 255, 65, 0.2); }
    .t-blink { animation: terminal-blink 1s linear infinite; }
    @keyframes terminal-blink { 50% { opacity: 0; } }
    
    /* Modifikasi Chat Thread logic Relay */
    .chat-thread { display: none; }
    .chat-thread.active { display: flex; flex-direction: column; }
    .empty-chat-state { text-align: center; opacity: 0.5; margin-top: 50px; }
</style>
';

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'direct' ORDER BY timestamp ASC");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

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
    <title>RELAY | Secure Comms</title>
    <link rel="icon" href="assets/icon.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.css">
    <?php echo $terminal_patch; ?>
</head>
<body>

    <nav class="t-navbar">
        <div class="t-nav-brand"><span class="t-led-dot t-led-green"></span> RELAY_STATION <span style="font-size:10px; opacity:0.5;">v1.0.0</span></div>
        <div class="t-nav-menu">
            <a href="console.php?logout=true" class="t-btn danger" style="padding: 5px 10px;">> LOGOUT</a>
        </div>
    </nav>

    <div class="t-grid-layout">
        
        <aside class="t-side-panel" style="max-height: 80vh; overflow-y: auto;">
            <h2 class="t-card-header">> ✉️ INBOX</h2>
            <div class="t-list-group" style="margin-bottom: 20px;">
                <?php if (empty($chat_threads)): ?>
                    <div class="t-list-item"><span class="t-list-item-subtitle">[ NO ACTIVE COMMS ]</span></div>
                <?php else: ?>
                    <?php foreach (array_keys($chat_threads) as $domain): ?>
                        <a href="javascript:void(0)" class="t-list-item contact-item" onclick="openChat('<?php echo htmlspecialchars($domain); ?>', this)">
                            <span class="t-list-item-title">> <?php echo htmlspecialchars($domain); ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <a href="javascript:void(0)" class="t-list-item contact-item" style="border-top: 1px dashed var(--t-green);" onclick="openChat('NEW', this)">
                    <span class="t-list-item-title">+ [ NEW LASER LINK ]</span>
                </a>
            </div>
            
            <h2 class="t-card-header">> 🧭 NAVIGATION</h2>
            <div class="t-list-group">
                <a href="console.php" class="t-list-item">
                    <span class="t-list-item-title">> 🌐 TIMELINE</span>
                </a>
                <a href="direct.php" class="t-list-item active" style="border-left-color: var(--t-red); color: var(--t-red);">
                    <span class="t-list-item-title">> ✉️ DIRECT MESSAGES</span>
                </a>
            </div>
        </aside>

        <main class="t-main-panel">
            <div class="t-card" style="border-color: var(--t-red); min-height: 400px; display: flex; flex-direction: column; justify-content: space-between;">
                
                <div id="empty-state" class="empty-chat-state">
                    <h3 style="color: var(--t-red);">> ENCRYPTED CHANNEL</h3>
                    <p style="font-size: 12px;">Select a node from the Inbox to open communication channel.</p>
                </div>

                <div id="chat-container" class="t-chat-container" style="flex: 1; overflow-y: auto; padding-right: 10px;">
                    <?php foreach ($chat_threads as $domain => $messages): ?>
                        <div id="thread-<?php echo htmlspecialchars($domain); ?>" class="chat-thread">
                            <div class="t-alert danger" style="padding: 5px; text-align: center; margin-bottom: 15px; font-weight: bold;">
                                LINK ESTABLISHED: <?php echo htmlspecialchars($domain); ?>
                            </div>
                            
                            <?php foreach ($messages as $msg): ?>
                                <?php if ($msg['is_remote'] == 0): ?>
                                    <div class="t-bubble t-bubble-me">
                                        <span class="t-bubble-meta">[ <?php echo $msg['timestamp']; ?> UTC ] <?php if($msg['expiry_date']) echo '👻'; ?> : YOU</span>
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="t-bubble t-bubble-them">
                                        <span class="t-bubble-meta">SENDER: <?php echo htmlspecialchars($msg['author_alias']); ?> [ <?php echo $msg['timestamp']; ?> UTC ] <?php if($msg['expiry_date']) echo '👻'; ?></span>
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form action="core/transmitter.php" method="POST" id="reply-form" style="display: none; margin-top: 20px; border-top: 1px dashed var(--t-red); padding-top: 15px;">
                    <input type="hidden" name="visibility" value="direct">
                    <input type="url" id="target-input" class="t-input" name="target_planet" placeholder="Target URL (e.g., https://node.com)" required style="border-color: var(--t-red); color: var(--t-red);">
                    <textarea name="content" rows="2" class="t-textarea" placeholder="Transmit classified message..." required style="border-color: var(--t-red); color: var(--t-red);"></textarea>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <label class="t-checkbox-label" style="margin:0; color: var(--t-red);">
                            <input type="checkbox" name="ghost_protocol" value="1"><span class="t-checkmark"></span> [ 👻 24H PURGE ]
                        </label>
                        <button type="submit" class="t-btn danger">[ FIRE LASER ]</button>
                    </div>
                </form>

            </div>
        </main>
        
        <aside class="t-side-panel">
            <h2 class="t-card-header">> 🗺️ STAR_CHART</h2>
            <div class="t-card" style="padding: 10px;">
                <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                    <div class="t-alert danger" style="padding: 5px; margin-bottom: 10px; font-size: 11px;">[!] SIGNAL LOST: Invalid Node.</div>
                <?php endif; ?>
                <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                    <div class="t-alert" style="padding: 5px; margin-bottom: 10px; font-size: 11px; border-color: var(--t-green);">[✓] NODE LOCKED.</div>
                <?php endif; ?>

                <form action="core/add_planet.php" method="POST">
                    <input type="url" name="planet_url" class="t-input" placeholder="https://domain.com" required>
                    <button type="submit" class="t-btn" style="width: 100%;">[ FOLLOW ]</button>
                </form>
            </div>
            
            <div class="t-list-group">
                <?php if (empty($star_chart)): ?>
                    <div class="t-list-item"><span class="t-list-item-subtitle">[ NO NODES ]</span></div>
                <?php else: ?>
                    <?php foreach ($star_chart as $star): ?>
                        <div class="t-list-item" style="cursor: default; padding: 10px;">
                            <span class="t-list-item-title" style="font-size: 12px;"><?php echo htmlspecialchars($star['alias']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        function openChat(domain, element) {
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('reply-form').style.display = 'block';
            
            // Atur class active di list
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
            } else {
                targetInput.value = 'https://' + domain;
                var activeThread = document.getElementById('thread-' + domain);
                if(activeThread) {
                    activeThread.classList.add('active');
                    // Scroll ke paling bawah
                    var container = document.getElementById('chat-container');
                    container.scrollTop = container.scrollHeight;
                }
            }
        }
    </script>
</body>
</html>