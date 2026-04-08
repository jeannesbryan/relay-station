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

    // ==========================================
    // 🛠️ [ BUG FIX: AWARENESS FOLDER PATH ]
    // Mengelompokkan pesan berdasarkan domain & path rekanan
    // ==========================================
    $chat_threads = [];
    foreach ($transmissions as $msg) {
        $partner_id = '';
        if ($msg['is_remote'] == 1) {
            // author_alias format: NAMA@domain.com/folder
            $parts = explode('@', $msg['author_alias']);
            $partner_id = end($parts); // Menghasilkan: domain.com/folder
        } else {
            // target_planet format: https://domain.com/folder
            $parsed = parse_url($msg['target_planet']);
            $host = $parsed['host'] ?? '';
            $path = rtrim($parsed['path'] ?? '', '/');
            $partner_id = $host . $path; // Menghasilkan: domain.com/folder
        }
        
        if (!empty($partner_id)) {
            $chat_threads[$partner_id][] = $msg;
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
    <title>RELAY | Secure Direct Links</title>
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
        #chat-container::-webkit-scrollbar { width: 6px; }
        #chat-container::-webkit-scrollbar-track { background: var(--t-black); border-left: 1px dashed var(--t-green-dim); }
        #chat-container::-webkit-scrollbar-thumb { background: var(--t-green-dim); }
    </style>
</head>
<body class="t-crt">

    <div id="splash-overlay" class="t-splash">
        <div class="font-bold text-success" id="splash-text" style="font-size: 1.1rem; letter-spacing: 2px; text-shadow: 0 0 8px currentColor;">
            > INITIATING_QUANTUM_DECRYPTION<span class="t-loading-dots"></span>
        </div>
    </div>

    <div class="t-container-fluid mt-4">
        <nav class="t-navbar mb-4">
            <div class="t-nav-brand"><span class="t-led-dot t-led-green t-blink"></span></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">> SECURE_COMMS (E2E) v4.2</span></div>
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
                            <h3 class="text-warning mb-4 t-border-bottom pb-2">> LINK ESTABLISHED: <?php echo htmlspecialchars($domain); ?> <span class="fs-small t-blink">[ 🔐 E2E SECURED ]</span></h3>
                            
                            <?php foreach ($thread as $msg): 
                                $is_me = ($msg['is_remote'] == 0);
                            ?>
                                <div class="mb-3 d-flex flex-column <?php echo $is_me ? 'align-items-end' : 'align-items-start'; ?>">
                                    <div class="fs-small text-muted mb-1">
                                        <?php echo $is_me ? 'LOCAL_COMMAND' : htmlspecialchars($msg['author_alias']); ?> 
                                        [<?php echo date('H:i', strtotime($msg['timestamp'])); ?>] 🔐
                                        <?php if(!empty($msg['expiry_date'])) echo '<span class="text-danger t-flicker"> [👻]</span>'; ?>
                                    </div>
                                    <div class="p-2 px-3" style="
                                        max-width: 80%; 
                                        border: 1px solid var(--t-<?php echo $is_me ? 'green' : 'yellow'; ?>); 
                                        background: rgba(<?php echo $is_me ? '0,255,65,0.05' : '255,255,0,0.05'; ?>);
                                        border-radius: 4px;
                                    ">
                                        <p class="m-0 e2e-msg" data-cipher="<?php echo htmlspecialchars($msg['content']); ?>" style="font-size: 14px; word-break: break-word;">
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
                    
                    <input type="hidden" name="content_local" id="content-local-input">

                    <div class="mb-2">
                        <label class="t-form-label">> TARGET_COORDINATES (URL)</label>
                        <input type="url" name="target_planet" id="target-input" class="t-input m-0 text-warning font-bold" placeholder="https://domain.com" required>
                    </div>
                    
                    <textarea name="content" id="content-input" rows="2" maxlength="180" class="t-textarea mb-1" placeholder="> Enter secure transmission (Max 180 Chars)..." required></textarea>
                    <div class="text-right text-muted fs-small mb-2" id="char-counter">0 / 180 Bytes</div>

                    <div class="mb-3">
                        <input type="file" name="media" accept="image/*" class="t-input m-0" style="padding: 6px; font-size: 0.9rem;">
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <label class="t-checkbox-label text-danger m-0">
                            <input type="checkbox" name="ghost_protocol" value="1"><span class="t-checkmark"></span> [!] GHOST (24H)
                        </label>
                        <button type="submit" class="t-btn font-bold t-glow">[ SEND_ENCRYPTED ]</button>
                    </div>
                </form>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/gh/jeannesbryan/terminal/terminal.js"></script>
    <script>
        // Character Counter
        document.getElementById('content-input').addEventListener('input', function() {
            document.getElementById('char-counter').innerText = this.value.length + ' / 180 Bytes';
        });

        // ==========================================
        // 🔐 THE DECRYPTOR (Membaca Pesan E2E)
        // ==========================================
        window.addEventListener('DOMContentLoaded', async () => {
            const privPem = localStorage.getItem('relay_privkey');
            if(!privPem) return;

            try {
                // 1. Convert Base64 Private Key to ArrayBuffer
                const binStr = window.atob(privPem);
                const bytes = new Uint8Array(binStr.length);
                for (let i = 0; i < binStr.length; i++) { bytes[i] = binStr.charCodeAt(i); }
                
                // 2. Import Key to Web Crypto API
                const privateKey = await window.crypto.subtle.importKey(
                    "pkcs8", bytes.buffer, { name: "RSA-OAEP", hash: "SHA-256" }, true, ["decrypt"]
                );

                const dec = new TextDecoder();
                const msgs = document.querySelectorAll('.e2e-msg');
                
                // 3. Eksekusi Dekripsi Masal
                for(let msg of msgs) {
                    const cipherText = msg.getAttribute('data-cipher');
                    // Cek apakah teksnya cukup panjang untuk dianggap sebagai Base64 RSA
                    if (cipherText.length > 200 && /^[A-Za-z0-9+/=]+$/.test(cipherText)) {
                        try {
                            const cBinStr = window.atob(cipherText);
                            const cBytes = new Uint8Array(cBinStr.length);
                            for (let i = 0; i < cBinStr.length; i++) { cBytes[i] = cBinStr.charCodeAt(i); }
                            
                            const decrypted = await window.crypto.subtle.decrypt(
                                { name: "RSA-OAEP" }, privateKey, cBytes.buffer
                            );
                            
                            // Ganti teks acak dengan teks asli, beri warna hijau redup sebagai tanda berhasil
                            msg.innerHTML = dec.decode(decrypted).replace(/\n/g, '<br>');
                            msg.style.color = "var(--t-green-dim)";
                        } catch(e) {
                            // Jika gagal (mungkin kuncinya salah), biarkan sebagai teks acak
                            console.log('Decryption skip: ', e);
                        }
                    }
                }
            } catch(err) {
                console.error("Core Decryption Failed: ", err);
            }
        });

        // ==========================================
        // 🔐 THE ENCRYPTOR (Mencegat Form Pengiriman)
        // ==========================================
        const replyForm = document.getElementById('reply-form');
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            Terminal.splash.show('> ESTABLISHING QUANTUM LINK...');

            const targetUrl = document.getElementById('target-input').value.replace(/\/$/, '');
            const rawContent = document.getElementById('content-input').value;
            const localPem = localStorage.getItem('relay_pubkey');
            
            try {
                if(!localPem) throw new Error("Local Public Key is missing! Open Main Console to generate one.");

                // 1. Fetch Target Public Key (Handshake)
                Terminal.splash.show('> PINGING TARGET HANDSHAKE...');
                const pingRes = await fetch(targetUrl + '/api_ping.php');
                if(!pingRes.ok) throw new Error("Target node is unreachable or offline.");
                const pingData = await pingRes.json();
                
                if(!pingData.public_key) {
                    throw new Error("Target node does not support E2E Encryption (Missing Public Key).");
                }

                Terminal.splash.show('> ENCRYPTING PAYLOADS...');
                
                // Helper to import Public Key
                async function importPubKey(pem) {
                    const binStr = window.atob(pem);
                    const bytes = new Uint8Array(binStr.length);
                    for (let i = 0; i < binStr.length; i++) { bytes[i] = binStr.charCodeAt(i); }
                    return await window.crypto.subtle.importKey("spki", bytes.buffer, { name: "RSA-OAEP", hash: "SHA-256" }, true, ["encrypt"]);
                }

                const targetPubKey = await importPubKey(pingData.public_key);
                const localPubKey = await importPubKey(localPem);
                const encodedMsg = new TextEncoder().encode(rawContent);

                // 2. Encrypt for Target (Kapsul Luar)
                const cipherTarget = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, targetPubKey, encodedMsg);
                const base64Target = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipherTarget)));

                // 3. Encrypt for Local (Kapsul Dalam)
                const cipherLocal = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, localPubKey, encodedMsg);
                const base64Local = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipherLocal)));

                // 4. Inject Ciphertexts to Form
                document.getElementById('content-input').value = base64Target;
                document.getElementById('content-local-input').value = base64Local;
                
                // 5. Fire the Engine
                replyForm.submit();

            } catch(err) {
                console.error(err);
                Terminal.splash.hide();
                Terminal.toast(err.message, 'danger');
            }
        });

        // UI Logic
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
                    var container = document.getElementById('chat-container');
                    container.scrollTop = container.scrollHeight;
                }
            }
        }
    </script>
</body>
</html>