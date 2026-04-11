<?php
require_once 'core/ssl_shield.php';
// ==========================================
// 🔒 [ SECURITY OVERRIDE: ENCRYPTED SESSION ]
// ==========================================
session_start();

if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    header("Location: console.php"); exit;
}

date_default_timezone_set('UTC'); 

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'core/db_connect.php';

// 🗑️ [ AJAX ENDPOINT: PURGE DM ROOM ]
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purge_room') {
    $domain = trim($_POST['target_domain'] ?? '');
    if (!empty($domain)) {
        try {
            $like_domain = '%' . $domain . '%';
            $stmt = $db->prepare("DELETE FROM transmissions WHERE visibility = 'direct' AND (target_planet LIKE :d1 OR author_alias LIKE :d2)");
            $stmt->execute([':d1' => $like_domain, ':d2' => $like_domain]);
        } catch (PDOException $e) {}
    }
    exit;
}

try {
    $query = $db->query("SELECT * FROM transmissions WHERE visibility = 'direct' ORDER BY timestamp ASC");
    $transmissions = $query->fetchAll(PDO::FETCH_ASSOC);

    $query_stars = $db->query("SELECT * FROM following ORDER BY added_at DESC");
    $star_chart = $query_stars->fetchAll(PDO::FETCH_ASSOC);

    // 🔑 [ V5.6 KEY VAULT PRE-FETCH ]
    $stmt_key = $db->query("SELECT config_value FROM system_config WHERE config_key = 'encrypted_privkey' ORDER BY rowid DESC LIMIT 1");
    $encrypted_privkey = $stmt_key ? $stmt_key->fetchColumn() : null;

    $stmt_pub = $db->query("SELECT config_value FROM system_config WHERE config_key = 'public_key' ORDER BY rowid DESC LIMIT 1");
    $server_pubkey = $stmt_pub ? $stmt_pub->fetchColumn() : null;

    // ==========================================
    // 🛠️ [ AWARENESS FOLDER PATH GROUPING ]
    // ==========================================
    $chat_threads = [];
    foreach ($transmissions as $msg) {
        $partner_id = '';
        if ($msg['is_remote'] == 1) {
            $parts = explode('@', $msg['author_alias']);
            $partner_id = end($parts); 
        } else {
            $parsed = parse_url($msg['target_planet']);
            $host = $parsed['host'] ?? '';
            $path = rtrim($parsed['path'] ?? '', '/');
            $partner_id = $host . $path; 
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
        
        /* PTT Button specific styling */
        #ptt-btn { user-select: none; -webkit-user-select: none; touch-action: manipulation; }

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

    <div id="splash-overlay" class="t-splash">
        <div class="font-bold text-success" id="splash-text" style="font-size: 1.1rem; letter-spacing: 2px; text-shadow: 0 0 8px currentColor;">
            > INITIATING_QUANTUM_DECRYPTION<span class="t-loading-dots"></span>
        </div>
    </div>

    <div id="vault-modal" class="t-splash" style="display:none; z-index: 2000; background: rgba(0,0,0,0.9); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card warning" style="width: 90%; max-width: 400px; border-color: var(--t-yellow);">
            <div class="t-card-header text-warning font-bold">> 🔐 IDENTITY VAULT DETECTED</div>
            <div class="p-3 text-center">
                <p class="fs-small text-muted mb-3">> Your local identity is missing or out of sync. Enter your Master Passcode to securely decrypt and restore your identity from the server.</p>
                <input type="password" id="vault-passcode" class="t-input text-center font-bold mb-3" placeholder="MASTER PASSCODE">
                <button onclick="triggerVaultUnlock()" class="t-btn warning w-100 font-bold t-glow">[ RESTORE IDENTITY ]</button>
            </div>
        </div>
    </div>

    <div id="vault-setup-modal" class="t-splash" style="display:none; z-index: 2000; background: rgba(0,0,0,0.9); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card success" style="width: 90%; max-width: 400px; border-color: var(--t-green);">
            <div class="t-card-header text-success font-bold">> 🔐 IDENTITY VAULT SETUP</div>
            <div class="p-3 text-center">
                <p class="fs-small text-muted mb-3">> Your E2E keys are active but NOT backed up. To enable multi-device sync, enter your Master Passcode to lock and backup your keys to the server vault.</p>
                <input type="password" id="vault-setup-passcode" class="t-input text-center font-bold mb-3" placeholder="MASTER PASSCODE">
                <button onclick="triggerVaultSetup()" class="t-btn success w-100 font-bold t-glow">[ SECURE IDENTITY ]</button>
            </div>
        </div>
    </div>

    <div id="split-brain-modal" class="t-splash" style="display:none; z-index: 2000; background: rgba(0,0,0,0.9); flex-direction: column; justify-content: center; align-items: center;">
        <div class="t-card danger" style="width: 90%; max-width: 400px; border-color: var(--t-red);">
            <div class="t-card-header text-danger font-bold">> 🚨 CRITICAL SYNC ERROR</div>
            <div class="p-3 text-center">
                <p class="fs-small text-muted mb-3">> Another device claims this node's identity but failed to backup the Vault. <br><br><strong>Action Required:</strong> Go to the device that created the keys and setup the vault. OR, permanently overwrite the server identity here.</p>
                <button onclick="forceRebuildIdentity()" class="t-btn danger w-100 font-bold t-glow">[ DESTROY & REBUILD KEYS ]</button>
            </div>
        </div>
    </div>

    <div class="t-container-fluid mt-4">
        <nav class="t-navbar mb-4">
            <div class="t-nav-brand"><span class="t-led-dot t-led-green t-blink"></span></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">> SECURE_COMMS (E2E) v5.6</span></div>
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
                            
                            <h3 class="text-warning mb-4 t-border-bottom pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span>> LINK ESTABLISHED: <?php echo htmlspecialchars($domain); ?> <span class="fs-small t-blink">[ 🔐 E2E SECURED ]</span></span>
                                <button onclick="purgeRoom('<?php echo htmlspecialchars($domain); ?>')" class="t-btn danger t-btn-sm font-bold" style="padding: 2px 8px; font-size: 11px;" title="Wipe Local & Remote Logs">[ 🔥 SCORCHED_EARTH ]</button>
                            </h3>
                            
                            <?php foreach ($thread as $msg): 
                                $is_me = ($msg['is_remote'] == 0);
                                $status = $msg['status'] ?? 'sent';
                                $alias_display = $is_me ? 'LOCAL_COMMAND' : htmlspecialchars($msg['author_alias']);
                            ?>
                                <div class="mb-3 d-flex flex-column <?php echo $is_me ? 'align-items-end' : 'align-items-start'; ?>">
                                    <div class="fs-small text-muted mb-1 d-flex align-items-center gap-2">
                                        <span>
                                            <?php echo $alias_display; ?> [<?php echo date('H:i', strtotime($msg['timestamp'])); ?>] 🔐
                                            <?php if(!empty($msg['expiry_date'])) echo '<span class="text-danger t-flicker"> [👻]</span>'; ?>
                                            
                                            <?php if ($is_me): ?>
                                                <?php if ($status === 'read'): ?>
                                                    <span class="ml-1 font-bold" style="color: #00ffff; text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);">[ READ ]</span>
                                                <?php else: ?>
                                                    <span class="text-warning ml-1">[ SENT ]</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </span>
                                        <button type="button" onclick="quoteMessage(this, '<?php echo $alias_display; ?>')" class="t-btn t-btn-sm" style="padding: 1px 5px; font-size: 9px; line-height: 1; border-color: var(--t-green-dim); color: var(--t-green-dim);">[ 💬 QUOTE ]</button>
                                    </div>
                                    <div class="p-2 px-3" style="
                                        max-width: 80%; width: 100%;
                                        border: 1px solid var(--t-<?php echo $is_me ? 'green' : 'yellow'; ?>); 
                                        background: rgba(<?php echo $is_me ? '0,255,65,0.05' : '255,255,0,0.05'; ?>);
                                        border-radius: 4px;
                                    ">
                                        <p class="m-0 e2e-msg" data-cipher="<?php echo htmlspecialchars($msg['content']); ?>" style="font-size: 14px; word-break: break-word;">
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
                                                            <button type="button" class="t-btn <?php echo $is_me ? 'warning' : 'danger'; ?> w-100 audio-play-btn" data-src="<?php echo htmlspecialchars($url); ?>" style="font-size: 11px;">
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
                                                            <img src="<?php echo htmlspecialchars($url); ?>" class="matrix-img" alt="Secure Media">
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form action="core/transmitter.php" method="POST" enctype="multipart/form-data" id="reply-form" style="display:none;" class="m-0 mt-3 t-card p-3">
                    <input type="hidden" name="visibility" value="direct">
                    <input type="hidden" name="content_local" id="content-local-input">
                    
                    <input type="hidden" name="media_base64" id="media-base64"> <input type="hidden" name="audio_base64" id="audio-base64"> 

                    <div class="mb-2">
                        <label class="t-form-label">> TARGET_COORDINATES (URL)</label>
                        <input type="url" name="target_planet" id="target-input" class="t-input m-0 text-warning font-bold" placeholder="https://domain.com" required>
                    </div>
                    
                    <textarea name="content" id="content-input" rows="2" class="t-textarea mb-1" placeholder="> Enter secure transmission (Max 180 Chars)..."></textarea>
                    <div class="text-right text-muted fs-small mb-2" id="char-counter">0 / 180 Bytes</div>

                    <div class="mb-3 mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <input type="file" name="media[]" accept="image/*,video/mp4,audio/*" multiple class="t-input m-0" id="media-input" style="display: none;">
                        <button type="button" class="t-btn t-btn-sm" onclick="document.getElementById('media-input').click();" style="white-space: nowrap;">[ ATTACH_FILE ]</button>
                        <button type="button" class="t-btn danger t-btn-sm font-bold" id="ptt-btn" style="white-space: nowrap;" title="Hold to record transmission">[ 🎙️ HOLD_TO_TALK ]</button>
                        <span id="file-name-display" class="fs-small text-muted" style="flex-grow:1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">> NO_MEDIA</span>
                        <span id="compress-status" class="fs-small text-warning font-bold"></span>
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
        // ==========================================
        // 🔐 [ V5.6 THE ENCRYPTED KEY VAULT (ENGINE & DECRYPTOR) ]
        // ==========================================
        async function encryptVault(privPem, passcode) {
            const enc = new TextEncoder();
            const keyMaterial = await window.crypto.subtle.importKey(
                "raw", enc.encode(passcode), {name: "PBKDF2"}, false, ["deriveKey"]
            );
            const salt = window.crypto.getRandomValues(new Uint8Array(16));
            const key = await window.crypto.subtle.deriveKey(
                {name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256"},
                keyMaterial, {name: "AES-GCM", length: 256}, false, ["encrypt"]
            );
            const iv = window.crypto.getRandomValues(new Uint8Array(12));
            const cipher = await window.crypto.subtle.encrypt({name: "AES-GCM", iv: iv}, key, new TextEncoder().encode(privPem));
            
            const saltB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(salt)));
            const ivB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(iv)));
            const cipherB64 = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipher)));
            return `${saltB64}:${ivB64}:${cipherB64}`;
        }

        async function triggerVaultUnlock() {
            const passcode = document.getElementById('vault-passcode').value;
            if(!passcode) return;
            
            const vaultData = "<?php echo $encrypted_privkey ?? ''; ?>";
            const pubKey = "<?php echo $server_pubkey ?? ''; ?>";
            if (!vaultData) return;

            document.getElementById('vault-passcode').disabled = true;
            document.getElementById('vault-passcode').placeholder = "DECRYPTING VAULT...";

            try {
                const parts = vaultData.split(':');
                const salt = Uint8Array.from(atob(parts[0]), c => c.charCodeAt(0));
                const iv = Uint8Array.from(atob(parts[1]), c => c.charCodeAt(0));
                const cipher = Uint8Array.from(atob(parts[2]), c => c.charCodeAt(0));

                const enc = new TextEncoder();
                const keyMaterial = await window.crypto.subtle.importKey(
                    "raw", enc.encode(passcode), {name: "PBKDF2"}, false, ["deriveKey"]
                );
                
                const key = await window.crypto.subtle.deriveKey(
                    {name: "PBKDF2", salt: salt, iterations: 100000, hash: "SHA-256"},
                    keyMaterial, {name: "AES-GCM", length: 256}, false, ["decrypt"]
                );

                const decrypted = await window.crypto.subtle.decrypt({name: "AES-GCM", iv: iv}, key, cipher);
                const privPem = new TextDecoder().decode(decrypted);

                localStorage.setItem('relay_privkey', privPem);
                localStorage.setItem('relay_pubkey', pubKey);

                document.getElementById('vault-modal').style.display='none';
                Terminal.toast('[✓] IDENTITY RESTORED SUCCESSFULLY', 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                document.getElementById('vault-passcode').disabled = false;
                document.getElementById('vault-passcode').value = '';
                document.getElementById('vault-passcode').placeholder = "MASTER PASSCODE";
                Terminal.toast('[!] INVALID PASSCODE OR CORRUPTED VAULT', 'danger');
            }
        }

        async function triggerVaultSetup() {
            const pc = document.getElementById('vault-setup-passcode').value;
            if (!pc) return;
            document.getElementById('vault-setup-passcode').disabled = true;

            const privPem = localStorage.getItem('relay_privkey');
            const pubPem = localStorage.getItem('relay_pubkey');
            if (!privPem) return;

            try {
                const encPriv = await encryptVault(privPem, pc);
                const formData = new FormData();
                formData.append('action', 'save_keys');
                formData.append('public_key', pubPem);
                formData.append('encrypted_privkey', encPriv);

                await fetch('console.php', { method: 'POST', body: formData });
                document.getElementById('vault-setup-modal').style.display = 'none';
                Terminal.toast('[✓] IDENTITY VAULT SECURED', 'success');
            } catch(e) {
                document.getElementById('vault-setup-passcode').disabled = false;
                Terminal.toast('[!] VAULT ENCRYPTION FAILED', 'danger');
            }
        }

        async function forceRebuildIdentity() {
            if (confirm("> WARNING: This will permanently destroy the current identity on the server and create a new one. All previous encrypted messages will become unreadable. Proceed?")) {
                document.getElementById('split-brain-modal').style.display = 'none';
                await forgeQuantumKeys();
                document.getElementById('vault-setup-modal').style.display = 'flex';
            }
        }

        async function forgeQuantumKeys() {
            Terminal.splash.show('> FORGING_QUANTUM_KEYS...');
            try {
                const keyPair = await window.crypto.subtle.generateKey(
                    { name: "RSA-OAEP", modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: "SHA-256" },
                    true, ["encrypt", "decrypt"]
                );

                const exportKey = async (key, type) => {
                    const exported = await window.crypto.subtle.exportKey(type, key);
                    return window.btoa(String.fromCharCode.apply(null, new Uint8Array(exported)));
                };

                const pubPem = await exportKey(keyPair.publicKey, "spki");
                const privPem = await exportKey(keyPair.privateKey, "pkcs8");

                localStorage.setItem('relay_pubkey', pubPem);
                localStorage.setItem('relay_privkey', privPem);

                const formData = new FormData();
                formData.append('action', 'save_keys');
                formData.append('public_key', pubPem);
                await fetch('console.php', { method: 'POST', body: formData });

                Terminal.splash.hide();
            } catch (err) {
                Terminal.splash.hide();
                Terminal.toast('[!] KEY GENERATION FAILED', 'danger');
            }
        }

        // ==========================================
        // 🧠 [ THE STATE MACHINE: SYNC ENFORCER ]
        // ==========================================
        window.addEventListener('DOMContentLoaded', async () => {
            const serverEncPriv = "<?php echo $encrypted_privkey ?? ''; ?>";
            const serverPubKey = "<?php echo $server_pubkey ?? ''; ?>";
            const localPriv = localStorage.getItem('relay_privkey');
            const localPub = localStorage.getItem('relay_pubkey');
            
            // 1. New Device (No Local Keys)
            if (!localPriv || !localPub) {
                if (serverEncPriv) {
                    // Server has a valid vault. Force user to unlock it.
                    document.getElementById('vault-modal').style.display = 'flex';
                    return;
                } else if (serverPubKey) {
                    // Split Brain: Server has keys, but no vault backup!
                    document.getElementById('split-brain-modal').style.display = 'flex';
                    return;
                } else {
                    // Brand new station. Forge keys and force vault setup.
                    await forgeQuantumKeys();
                    document.getElementById('vault-setup-modal').style.display = 'flex';
                    return;
                }
            }

            // 2. Existing Device (Has Local Keys)
            if (localPriv && localPub) {
                if (serverPubKey && localPub !== serverPubKey) {
                    // Keys out of sync!
                    if (serverEncPriv) {
                        document.getElementById('vault-modal').style.display = 'flex';
                        return;
                    } else {
                        document.getElementById('split-brain-modal').style.display = 'flex';
                        return;
                    }
                } else if (!serverEncPriv) {
                    // Keys are in sync, but NOT backed up to the vault!
                    document.getElementById('vault-setup-modal').style.display = 'flex';
                    return;
                }
            }

            // 3. Perfect Sync: Execute Decrypter safely!
            executeDecryption(localPriv);
        });

        async function executeDecryption(privPem) {
            try {
                const cleanPrivPem = privPem.replace(/[\r\n\s]/g, '');
                const binStr = window.atob(cleanPrivPem);
                const bytes = new Uint8Array(binStr.length);
                for (let i = 0; i < binStr.length; i++) { bytes[i] = binStr.charCodeAt(i); }
                
                const privateKey = await window.crypto.subtle.importKey(
                    "pkcs8", bytes.buffer, { name: "RSA-OAEP", hash: "SHA-256" }, true, ["decrypt"]
                );

                const dec = new TextDecoder();
                const msgs = document.querySelectorAll('.e2e-msg');
                
                for(let msg of msgs) {
                    const cipherText = msg.getAttribute('data-cipher');
                    if (cipherText.length > 200 && /^[A-Za-z0-9+/=\s]+$/.test(cipherText)) {
                        try {
                            const cleanCipher = cipherText.replace(/[\r\n\s]/g, '');
                            const cBinStr = window.atob(cleanCipher);
                            const cBytes = new Uint8Array(cBinStr.length);
                            for (let i = 0; i < cBinStr.length; i++) { cBytes[i] = cBinStr.charCodeAt(i); }
                            
                            const decrypted = await window.crypto.subtle.decrypt(
                                { name: "RSA-OAEP" }, privateKey, cBytes.buffer
                            );
                            
                            msg.innerHTML = dec.decode(decrypted).replace(/\n/g, '<br>');
                            msg.style.color = "var(--t-green-dim)";
                        } catch(e) {}
                    }
                }
            } catch(err) {
                console.error("Core Decryption Failed: ", err);
            }
        }

        // ==========================================
        // 💬 [ V5.6 TACTICAL QUOTE ENGINE ]
        // ==========================================
        function quoteMessage(btn, author) {
            const container = btn.closest('.d-flex.flex-column');
            const msgElement = container.querySelector('.e2e-msg');
            const hasMedia = container.querySelector('.media-matrix') || container.querySelector('.audio-play-btn') || container.querySelector('.matrix-img') ? true : false;
            
            let originalText = msgElement.innerText.trim();
            if (originalText.includes('DECRYPTING') || msgElement.getAttribute('data-cipher') === originalText) {
                Terminal.toast('[!] CANNOT QUOTE ENCRYPTED TEXT', 'warning');
                return;
            }

            let quoteBlock = `> [ TRANSMISI DARI: ${author} ]\n`;
            if (originalText.length > 0) {
                const lines = originalText.split('\n').map(line => `> ${line}`);
                quoteBlock += lines.join('\n') + '\n';
            }
            if (hasMedia) {
                quoteBlock += `> [ 📎 MEDIA_ATTACHED ]\n`;
            }
            
            const input = document.getElementById('content-input');
            input.value = quoteBlock + '\n' + input.value;
            input.focus();
            
            if (input.value.length > 180) {
                input.value = input.value.substring(0, 180); 
            }
            document.getElementById('char-counter').innerText = input.value.length + ' / 180 Bytes';
            document.getElementById('reply-form').style.display = 'block';
        }

        // ==========================================
        // 🛡️ [ BUG FIX: JS-LEVEL MAXLENGTH ENFORCER ]
        // ==========================================
        document.getElementById('content-input').addEventListener('input', function() {
            if (this.value.length > 180) {
                this.value = this.value.substring(0, 180); 
            }
            document.getElementById('char-counter').innerText = this.value.length + ' / 180 Bytes';
        });

        // ==========================================
        // 🎙️ [ TACTICAL SQUELCH & PTT ENGINE ]
        // ==========================================
        let audioCtx;
        function getAudioCtx() {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            return audioCtx;
        }

        function playSquelch(type) {
            const ctx = getAudioCtx();
            const duration = 0.15; 
            
            const bufferSize = ctx.sampleRate * duration; 
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) { data[i] = Math.random() * 2 - 1; }
            
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            
            const noiseFilter = ctx.createBiquadFilter();
            noiseFilter.type = 'bandpass';
            noiseFilter.frequency.value = 1500;
            
            const noiseGain = ctx.createGain();
            noiseGain.gain.setValueAtTime(1, ctx.currentTime);
            noiseGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            noise.connect(noiseFilter);
            noiseFilter.connect(noiseGain);
            noiseGain.connect(ctx.destination);
            
            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.frequency.setValueAtTime(type === 'start' ? 2200 : 1200, ctx.currentTime);
            
            const oscGain = ctx.createGain();
            oscGain.gain.setValueAtTime(0.1, ctx.currentTime);
            oscGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            osc.connect(oscGain);
            oscGain.connect(ctx.destination);
            
            noise.start();
            osc.start();
            osc.stop(ctx.currentTime + duration);
        }

        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        
        const pttBtn = document.getElementById('ptt-btn');
        const fileDisplay = document.getElementById('file-name-display');
        const audioBase64Input = document.getElementById('audio-base64');
        const mediaInput = document.getElementById('media-input');
        const mediaBase64Input = document.getElementById('media-base64');
        const compStatus = document.getElementById('compress-status');

        async function startRecording() {
            if (isRecording) return;
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];
                
                mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
                
                mediaRecorder.onstop = () => {
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = () => {
                        audioBase64Input.value = reader.result;
                        fileDisplay.innerText = '> [ AUDIO_LOG_SAVED ]';
                        fileDisplay.className = 'fs-small text-warning font-bold t-blink';
                    };
                    stream.getTracks().forEach(track => track.stop()); 
                };
                
                mediaRecorder.start();
                isRecording = true;
                playSquelch('start');
                
                pttBtn.classList.add('warning', 't-blink');
                pttBtn.classList.remove('danger');
                pttBtn.innerText = '[ 🔴 RECORDING... ]';
                fileDisplay.innerText = '> LISTENING...';
                fileDisplay.className = 'fs-small text-danger t-blink';
            } catch (err) {
                console.error(err);
                Terminal.toast('[!] MIC ACCESS DENIED. CHECK BROWSER PERMISSIONS.', 'danger');
            }
        }

        function stopRecording() {
            if (!isRecording || !mediaRecorder) return;
            mediaRecorder.stop();
            isRecording = false;
            playSquelch('stop');
            
            pttBtn.classList.remove('warning', 't-blink');
            pttBtn.classList.add('danger');
            pttBtn.innerText = '[ 🎙️ HOLD_TO_TALK ]';
        }

        if (pttBtn) {
            pttBtn.addEventListener('mousedown', startRecording);
            pttBtn.addEventListener('touchstart', (e) => { e.preventDefault(); startRecording(); }, {passive: false});
            window.addEventListener('mouseup', stopRecording);
            pttBtn.addEventListener('touchend', stopRecording);
        }

        // ==========================================
        // 🗜️ [ MULTI-MEDIA COMPRESSOR MATRIX ]
        // ==========================================
        mediaInput.addEventListener('change', async function(e) {
            const files = e.target.files;
            if(files.length === 0) {
                fileDisplay.innerText = '> NO_MEDIA';
                compStatus.innerText = '';
                mediaBase64Input.value = '';
                return;
            }
            
            if(files.length > 4) {
                Terminal.toast('[!] MAX 4 FILES ALLOWED', 'warning');
            }
            
            const processCount = Math.min(files.length, 4);
            if (processCount === 1) {
                fileDisplay.innerText = '> ' + files[0].name;
            } else {
                fileDisplay.innerText = '> [ ' + processCount + ' MEDIA ATTACHED ]';
            }
            fileDisplay.className = 'fs-small text-success font-bold';
            compStatus.innerText = '[ PROCESSING... ]';
            
            let processedBase64Array = [];
            
            for(let i=0; i < processCount; i++) {
                const file = files[i];
                if(file.type.startsWith('video/') || file.type.startsWith('audio/')) { continue; }
                if(file.type.startsWith('image/')) {
                    const b64 = await compressImage(file);
                    processedBase64Array.push(b64);
                }
            }
            
            if (processedBase64Array.length > 0) {
                mediaBase64Input.value = JSON.stringify(processedBase64Array);
                compStatus.innerText = '[ WEBP COMPRESSED ]';
            } else {
                mediaBase64Input.value = '';
                compStatus.innerText = '[ RAW MEDIA QUEUED ]';
            }
        });

        function compressImage(file) {
            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const img = new Image();
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        let width = img.width; let height = img.height;
                        if(width > 1080) { height = Math.round(height * 1080 / width); width = 1080; } 
                        canvas.width = width; canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        resolve(canvas.toDataURL('image/webp', 0.8));
                    }
                    img.src = event.target.result;
                }
                reader.readAsDataURL(file);
            });
        }

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

        // ==========================================
        // 🔥 [ V5.6 THE SCORCHED EARTH PROTOCOL ]
        // ==========================================
        async function purgeRoom(domain) {
            if (confirm('> THE SCORCHED EARTH PROTOCOL\n\nWARNING: This will permanently delete all local records of this link AND send a destructive payload to wipe the target node\'s records.\n\nExecute?')) {
                
                document.getElementById('splash-overlay').style.display = 'flex';
                document.getElementById('splash-text').innerText = '> INITIATING SCORCHED_EARTH...';

                // 1. Fire Scorched Earth to target node
                const fireData = new FormData();
                fireData.append('visibility', 'scorched_earth');
                fireData.append('target_planet', 'https://' + domain);
                fireData.append('content', 'PURGE'); 
                
                try {
                    await fetch('core/transmitter.php', { method: 'POST', body: fireData });
                } catch(e) {}

                // 2. Purge Local Memory
                const formData = new FormData();
                formData.append('action', 'purge_room');
                formData.append('target_domain', domain);
                
                try {
                    await fetch('direct.php', { method: 'POST', body: formData });
                    Terminal.toast('[✓] SCORCHED EARTH EXECUTED', 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch(err) {
                    document.getElementById('splash-overlay').style.display = 'none';
                    Terminal.toast('[!] LOCAL PURGE FAILED', 'danger');
                }
            }
        }

        // ==========================================
        // 🔐 THE ENCRYPTOR (Intercept Submission Form)
        // ==========================================
        const replyForm = document.getElementById('reply-form');
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const splashEl = document.getElementById('splash-overlay');
            const splashText = document.getElementById('splash-text');
            splashEl.style.display = 'flex';
            splashText.innerHTML = '> ESTABLISHING QUANTUM LINK...<span class="t-loading-dots"></span>';

            const targetUrl = document.getElementById('target-input').value.replace(/\/$/, '');
            let rawContent = document.getElementById('content-input').value;
            const localPem = localStorage.getItem('relay_pubkey');
            
            if (rawContent.trim() === '' && (document.getElementById('audio-base64').value !== '' || document.getElementById('media-input').files.length > 0)) {
                rawContent = '[ 🎙️ SECURE_MEDIA_TRANSMISSION ]';
            } else if (rawContent.trim() === '') {
                splashEl.style.display = 'none';
                Terminal.toast('[!] TRANSMISSION CANNOT BE EMPTY', 'danger');
                return;
            }
            
            if (rawContent.length > 180) {
                splashEl.style.display = 'none';
                Terminal.toast('[!] PAYLOAD EXCEEDS 180 BYTES', 'danger');
                return;
            }

            try {
                if(!localPem) throw new Error("Local Public Key is missing! Sync your Identity Vault first.");

                splashText.innerHTML = '> PINGING TARGET HANDSHAKE...<span class="t-loading-dots"></span>';
                
                const pingRes = await fetch(targetUrl + '/api_ping.php');
                if(!pingRes.ok) throw new Error("Target node API ditolak / CORS Error (HTTP " + pingRes.status + ")");
                const pingData = await pingRes.json();
                
                if(!pingData.public_key) {
                    throw new Error("Target node does not support E2E Encryption (Missing Public Key).");
                }

                splashText.innerHTML = '> ENCRYPTING PAYLOADS...<span class="t-loading-dots"></span>';
                
                async function importPubKey(pem) {
                    const cleanPem = pem.replace(/[\r\n\s]/g, '');
                    const binStr = window.atob(cleanPem);
                    const bytes = new Uint8Array(binStr.length);
                    for (let i = 0; i < binStr.length; i++) { bytes[i] = binStr.charCodeAt(i); }
                    return await window.crypto.subtle.importKey("spki", bytes.buffer, { name: "RSA-OAEP", hash: "SHA-256" }, true, ["encrypt"]);
                }

                const targetPubKey = await importPubKey(pingData.public_key);
                const localPubKey = await importPubKey(localPem);
                const encodedMsg = new TextEncoder().encode(rawContent);

                const cipherTarget = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, targetPubKey, encodedMsg);
                const base64Target = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipherTarget)));

                const cipherLocal = await window.crypto.subtle.encrypt({ name: "RSA-OAEP" }, localPubKey, encodedMsg);
                const base64Local = window.btoa(String.fromCharCode.apply(null, new Uint8Array(cipherLocal)));

                document.getElementById('content-input').value = base64Target;
                document.getElementById('content-local-input').value = base64Local;
                
                replyForm.submit();

            } catch(err) {
                console.error(err);
                splashEl.style.display = 'none';
                Terminal.toast(err.message, 'danger');
            }
        });

        // ==========================================
        // 🎛️ UI LOGIC & ACK TRIGGER
        // ==========================================
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
                    
                    // 🔫 FIRE ACK PROTOCOL SILENTLY
                    const formData = new FormData();
                    formData.append('visibility', 'ack_receipt');
                    formData.append('target_planet', 'https://' + domain);
                    formData.append('content', 'ACK'); 
                    
                    fetch('core/transmitter.php', { method: 'POST', body: formData }).catch(e => console.log('> ACK Dispatch Failed'));
                }
            }
        }
    </script>
</body>
</html>