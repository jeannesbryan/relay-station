<?php
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();
$db_file = 'data/relay_core.sqlite';

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    header("Location: console.php"); 
    exit;
}

date_default_timezone_set('UTC'); 

try {
    $db = new PDO("sqlite:" . $db_file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_TIMEOUT, 5);
    
    // Ambil pesan Direct
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'direct' ORDER BY timestamp ASC");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    // Ambil Star Chart
    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

    // [ MESIN THREADING / CHAT BUBBLES ]
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
    die("<h3 style='color:red;'>[ CRITICAL ERROR ] Core Memory Offline.</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Secure Comms</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .status-dot { display: inline-block; width: 14px; height: 14px; background-color: #4af626; border-radius: 50%; margin-right: 10px; box-shadow: 0 0 10px #4af626; }
        .dashboard-layout { display: flex; gap: 20px; align-items: flex-start; }
        
        .contact-list { flex: 1; min-width: 250px; max-height: 70vh; overflow-y: auto; }
        .chat-area { flex: 2; min-width: 300px; display: flex; flex-direction: column; gap: 15px; }
        
        .contact-item { padding: 10px; border: 1px dashed var(--text-dim); cursor: pointer; margin-bottom: 10px; transition: 0.2s; }
        .contact-item:hover, .contact-item.active { border-color: var(--alert); background: rgba(255, 42, 42, 0.1); color: var(--alert); }
        
        .chat-thread { display: none; flex-direction: column; gap: 10px; max-height: 50vh; overflow-y: auto; padding-right: 10px; }
        .chat-thread.active { display: flex; }
        
        .bubble { max-width: 80%; padding: 10px 15px; border-radius: 8px; font-family: monospace; word-wrap: break-word; }
        .bubble-me { align-self: flex-end; background: rgba(74, 246, 38, 0.1); border: 1px solid #4af626; color: #4af626; border-bottom-right-radius: 0; }
        .bubble-them { align-self: flex-start; background: rgba(255, 255, 255, 0.05); border: 1px solid var(--text-dim); color: var(--text-main); border-bottom-left-radius: 0; }
        
        .chat-time { font-size: 0.7em; opacity: 0.5; margin-top: 5px; display: block; }
        .empty-chat-state { text-align: center; opacity: 0.5; margin-top: 50px; }

        @media (max-width: 768px) { .dashboard-layout { flex-direction: column; } .chat-area { width: 100%; } }
    </style>
</head>
<body>

    <header style="margin-bottom: 30px; border-bottom: 2px solid var(--text-main); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; padding-bottom: 10px;">
        <div>
            <h1 style="margin-bottom: 5px; display: flex; align-items: center;"><span class="status-dot blink"></span>RELAY_STATION</h1>
            <p style="margin-top: 0; font-size: 0.9em;">STATUS: <span style="color: #4af626;">ONLINE</span> | ENCRYPTION: ACTIVE | VER: 1.0.0-dev</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="console.php?logout=true" style="color: var(--alert); text-decoration: none; border: 1px solid var(--alert); padding: 5px 10px;">> LOGOUT</a>
        </div>
    </header>

    <div class="dashboard-layout">
        
        <aside class="contact-list">
            <h2 style="color: var(--alert);">[ ✉️ INBOX ]</h2>
            <div class="console-box" style="border-color: var(--text-dim);">
                <?php if (empty($chat_threads)): ?>
                    <p style="opacity: 0.5; font-size: 0.9em;">[ NO ACTIVE COMMS ]</p>
                <?php else: ?>
                    <?php foreach (array_keys($chat_threads) as $domain): ?>
                        <div class="contact-item" onclick="openChat('<?php echo htmlspecialchars($domain); ?>')">
                            <strong>> <?php echo htmlspecialchars($domain); ?></strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="contact-item" style="border-color: var(--text-main); margin-top: 20px;" onclick="openChat('NEW')">
                    <strong>+ [ NEW LASER LINK ]</strong>
                </div>
            </div>
            
            <h2 style="margin-top: 30px;">[ 🧭 NAVIGATION ]</h2>
            <div class="console-box" style="margin-bottom: 20px;">
                <ul style="list-style: none; padding: 0; margin: 0; font-weight: bold;">
                    <li style="margin-bottom: 15px;"><a href="console.php" style="color: var(--text-main); text-decoration: none;">> 🌐 TIMELINE</a></li>
                    <li style="margin-bottom: 15px;"><span style="color: var(--alert);">> ✉️ DIRECT MESSAGES</span></li>
                </ul>
            </div>
        </aside>

        <main class="chat-area">
            <div class="console-box" style="border-color: var(--alert); min-height: 400px; display: flex; flex-direction: column; justify-content: space-between;">
                
                <div id="empty-state" class="empty-chat-state">
                    <h3>[ ENCRYPTED CHANNEL ]</h3>
                    <p>Select a node from the Inbox to open communication channel.</p>
                </div>

                <div id="chat-container">
                    <?php foreach ($chat_threads as $domain => $messages): ?>
                        <div id="thread-<?php echo htmlspecialchars($domain); ?>" class="chat-thread">
                            <h4 style="border-bottom: 1px dashed var(--text-dim); padding-bottom: 10px; margin-top: 0; color: var(--alert);">
                                LINK ESTABLISHED: <?php echo htmlspecialchars($domain); ?>
                            </h4>
                            
                            <?php foreach ($messages as $msg): ?>
                                <?php if ($msg['is_remote'] == 0): ?>
                                    <div class="bubble bubble-me">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        <span class="chat-time"><?php echo $msg['timestamp']; ?> UTC <?php if($msg['expiry_date']) echo '👻'; ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="bubble bubble-them">
                                        <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        <span class="chat-time"><?php echo $msg['timestamp']; ?> UTC <?php if($msg['expiry_date']) echo '👻'; ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form action="core/transmitter.php" method="POST" id="reply-form" style="display: none; margin-top: 20px; border-top: 1px dashed var(--alert); padding-top: 15px;">
                    <input type="hidden" name="visibility" value="direct">
                    <input type="url" id="target-input" name="target_planet" placeholder="Target URL (e.g., https://node.com)" required style="width: 100%; box-sizing: border-box; margin-bottom: 10px; padding: 10px; background: transparent; border: 1px solid var(--alert); color: var(--text-main); font-family: monospace;">
                    <textarea name="content" rows="2" style="width: 100%; box-sizing: border-box; background: transparent; border: 1px solid var(--alert); color: var(--text-main); padding: 10px; font-family: monospace;" placeholder="Transmit classified message..." required></textarea>

                    <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <label style="color: var(--alert); font-size: 0.9em; cursor: pointer;">
                            <input type="checkbox" name="ghost_protocol" value="1"> 
                            [ 👻 24H PURGE ]
                        </label>
                        <button type="submit" style="padding: 5px 15px; cursor: pointer; font-weight: bold; font-family: monospace; color: var(--alert); border-color: var(--alert);">[ FIRE LASER ]</button>
                    </div>
                </form>

            </div>
        </main>
        
        <aside class="contact-list" style="flex: 1; min-width: 250px;">
             <h2>[ 🗺️ STAR_CHART ]</h2>
            <div class="console-box" style="margin-bottom: 10px;">
                <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_node'): ?>
                    <div style="color: var(--bg-color); background-color: var(--alert); padding: 5px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;">
                        [!] SIGNAL LOST: Target is not a valid node.
                    </div>
                <?php endif; ?>
                <?php if(isset($_GET['status']) && $_GET['status'] == 'node_locked'): ?>
                    <div style="color: var(--bg-color); background-color: #4af626; padding: 5px; margin-bottom: 10px; font-size: 0.8em; text-align: center; font-weight: bold;">
                        [✓] NODE LOCKED.
                    </div>
                <?php endif; ?>

                <form action="core/add_planet.php" method="POST">
                    <input type="url" name="planet_url" placeholder="https://domain.com" required style="width: 100%; box-sizing: border-box; margin-bottom: 10px; padding: 10px; background: transparent; border: 1px solid var(--text-main); color: var(--text-main); font-family: monospace;">
                    <button type="submit" style="width: 100%; box-sizing: border-box; font-size: 0.9em; padding: 10px; cursor: pointer; font-weight: bold; font-family: monospace;">[ FOLLOW NODE ]</button>
                </form>
            </div>
            
            <div class="console-box" style="border-color: var(--text-dim);">
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 0.9em; word-wrap: break-word;">
                    <?php if (empty($star_chart)): ?>
                        <li style="opacity: 0.5;">[ NO NODES FOLLOWED ]</li>
                    <?php else: ?>
                        <?php foreach ($star_chart as $star): ?>
                            <li style="margin-bottom: 5px; border-bottom: 1px dashed var(--text-dim); padding-bottom: 5px;">
                                <strong style="color: var(--text-main);"><?php echo htmlspecialchars($star['alias']); ?></strong><br>
                                <span style="font-size: 0.8em; opacity: 0.7;"><?php echo htmlspecialchars($star['planet_url']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </aside>

    </div>

    <script>
        function openChat(domain) {
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('reply-form').style.display = 'block';
            
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
                    activeThread.scrollTop = activeThread.scrollHeight;
                }
            }
        }
    </script>
</body>
</html>