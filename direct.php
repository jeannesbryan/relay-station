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
        
        /* PTT Button specific styling to prevent text selection while holding */
        #ptt-btn { user-select: none; -webkit-user-select: none; touch-action: manipulation; }
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
            <div class="t-nav-brand"><span class="t-led-dot t-led-green t-blink"></span></span> RELAY_STATION <span class="fs-small text-muted fw-normal ml-2">> SECURE_COMMS (E2E) v5.4</span></div>
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
                                <button onclick="purgeRoom('<?php echo htmlspecialchars($domain); ?>')" class="t-btn danger t-btn-sm" style="padding: 2px 8px; font-size: 11px;" title="Purge Local Records">[ 🗑️ PURGE_LINK ]</button>
                            </h3>
                            
                            <?php foreach ($thread as $msg): 
                                $is_me = ($msg['is_remote'] == 0);
                                $status = $msg['status'] ?? 'sent';
                            ?>
                                <div class="mb-3 d-flex flex-column <?php echo $is_me ? 'align-items-end' : 'align-items-start'; ?>">
                                    <div class="fs-small text-muted mb-1">
                                        <?php echo $is_me ? 'LOCAL_COMMAND' : htmlspecialchars($msg['author_alias']); ?> 
                                        [<?php echo date('H:i', strtotime($msg['timestamp'])); ?>] 🔐
                                        <?php if(!empty($msg['expiry_date'])) echo '<span class="text-danger t-flicker"> [👻]</span>'; ?>
                                        
                                        <?php if ($is_me): ?>
                                            <?php if ($status === 'read'): ?>
                                                <span class="ml-1 font-bold" style="color: #00ffff; text-shadow: 0 0 5px rgba(0, 255, 255, 0.5);">[ READ ]</span>
                                            <?php else: ?>
                                                <span class="text-warning ml-1">[ SENT ]</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
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
                                        
                                        <?php if(!empty($msg['media_url'])): 
                                            $ext = strtolower(pathinfo($msg['media_url'], PATHINFO_EXTENSION));
                                            $is_audio = in_array($ext, ['webm', 'ogg', 'mp3', 'wav', 'm4a', 'mp4']);
                                        ?>
                                            <div class="mt-2 text-<?php echo $is_me ? 'right' : 'left'; ?>">
                                                <?php if($is_audio): ?>
                                                    <button type="button" class="t-btn <?php echo $is_me ? 'warning' : 'danger'; ?> t-btn-sm audio-play-btn" data-src="<?php echo htmlspecialchars($msg['media_url']); ?>" style="font-size: 11px;">
                                                        [ ▶️ PLAY AUDIO_LOG ]
                                                    </button>
                                                <?php else: ?>
                                                    <img src="<?php echo htmlspecialchars($msg['media_url']); ?>" alt="Secure Media" style="max-width: 100%; border: 1px dashed var(--t-<?php echo $is_me ? 'green' : 'yellow'; ?>); border-radius: 4px;">
                                                <?php endif; ?>
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
                    <input type="hidden" name="audio_base64" id="audio-base64"> <div class="mb-2">
                        <label class="t-form-label">> TARGET_COORDINATES (URL)</label>
                        <input type="url" name="target_planet" id="target-input" class="t-input m-0 text-warning font-bold" placeholder="https://domain.com" required>
                    </div>
                    
                    <textarea name="content" id="content-input" rows="2" maxlength="180" class="t-textarea mb-1" placeholder="> Enter secure transmission (Max 180 Chars)..."></textarea>
                    <div class="text-right text-muted fs-small mb-2" id="char-counter">0 / 180 Bytes</div>

                    <div class="mb-3 mt-2 d-flex align-items-center gap-2 flex-wrap">
                        <input type="file" name="media" accept="image/*,audio/*" class="t-input m-0" id="media-input" style="display: none;">
                        <button type="button" class="t-btn t-btn-sm" onclick="document.getElementById('media-input').click();" style="white-space: nowrap;">[ ATTACH_FILE ]</button>
                        <button type="button" class="t-btn danger t-btn-sm font-bold" id="ptt-btn" style="white-space: nowrap;" title="Hold to record transmission">[ 🎙️ HOLD_TO_TALK ]</button>
                        <span id="file-name-display" class="fs-small text-muted" style="flex-grow:1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">> NO_MEDIA</span>
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
        // 🎙️ [ TACTICAL SQUELCH GENERATOR (Web Audio API) ]
        // ==========================================
        let audioCtx;
        function getAudioCtx() {
            if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (audioCtx.state === 'suspended') audioCtx.resume();
            return audioCtx;
        }

        function playSquelch(type) {
            const ctx = getAudioCtx();
            const duration = 0.15; // 150ms of static
            
            // 1. White Noise Generator
            const bufferSize = ctx.sampleRate * duration; 
            const buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) { data[i] = Math.random() * 2 - 1; }
            
            const noise = ctx.createBufferSource();
            noise.buffer = buffer;
            
            // Filter noise to sound like a radio
            const noiseFilter = ctx.createBiquadFilter();
            noiseFilter.type = 'bandpass';
            noiseFilter.frequency.value = 1500;
            
            const noiseGain = ctx.createGain();
            noiseGain.gain.setValueAtTime(1, ctx.currentTime);
            noiseGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            noise.connect(noiseFilter);
            noiseFilter.connect(noiseGain);
            noiseGain.connect(ctx.destination);
            
            // 2. Tactical Beep (Start = High, Stop = Low)
            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.frequency.setValueAtTime(type === 'start' ? 2200 : 1200, ctx.currentTime);
            
            const oscGain = ctx.createGain();
            oscGain.gain.setValueAtTime(0.1, ctx.currentTime);
            oscGain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + duration);
            
            osc.connect(oscGain);
            oscGain.connect(ctx.destination);
            
            // Fire!
            noise.start();
            osc.start();
            osc.stop(ctx.currentTime + duration);
        }

        // ==========================================
        // 🎙️ [ THE PUSH-TO-TALK ENGINE (MediaRecorder) ]
        // ==========================================
        let mediaRecorder;
        let audioChunks = [];
        let isRecording = false;
        
        const pttBtn = document.getElementById('ptt-btn');
        const fileDisplay = document.getElementById('file-name-display');
        const audioBase64Input = document.getElementById('audio-base64');
        const mediaInput = document.getElementById('media-input');

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
                        mediaInput.value = ''; // Clear native file input
                        fileDisplay.innerText = '> [ PTT_AUDIO_READY ]';
                        fileDisplay.className = 'fs-small text-warning font-bold t-blink';
                    };
                    stream.getTracks().forEach(track => track.stop()); // Release Mic
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

        // Listeners for Mouse and Touch (Mobile friendly)
        pttBtn.addEventListener('mousedown', startRecording);
        pttBtn.addEventListener('touchstart', (e) => { e.preventDefault(); startRecording(); }, {passive: false});
        
        window.addEventListener('mouseup', stopRecording);
        pttBtn.addEventListener('touchend', stopRecording);

        // Fallback file input UI update
        mediaInput.addEventListener('change', function(e) {
            if(e.target.files.length > 0) {
                audioBase64Input.value = ''; // Clear PTT if a file is manually selected
                fileDisplay.innerText = '> ' + e.target.files[0].name;
                fileDisplay.className = 'fs-small text-muted';
            }
        });

        // ==========================================
        // ▶️ [ RETRO AUDIO PLAYER ]
        // ==========================================
        let currentAudio = null;
        let currentBtn = null;

        document.querySelectorAll('.audio-play-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const src = this.getAttribute('data-src');
                
                if (currentAudio && currentBtn === this) {
                    if (!currentAudio.paused) {
                        currentAudio.pause();
                        this.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                        this.classList.remove('t-blink');
                        return;
                    } else {
                        currentAudio.play();
                        this.innerText = '[ ⏸️ PLAYING... ]';
                        this.classList.add('t-blink');
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
                currentBtn = this;
                currentBtn.innerText = '[ ⏸️ PLAYING... ]';
                currentBtn.classList.add('t-blink');
                
                currentAudio.play();
                
                currentAudio.onended = () => {
                    currentBtn.innerText = '[ ▶️ PLAY AUDIO_LOG ]';
                    currentBtn.classList.remove('t-blink');
                };
            });
        });

        // ==========================================
        // 🗑️ [ ROOM PURGE PROTOCOL ]
        // ==========================================
        async function purgeRoom(domain) {
            if (confirm('> WARNING: This will permanently delete all local records of this communication link. Continue?')) {
                Terminal.splash.show('> PURGING_LOCAL_RECORDS...');
                const formData = new FormData();
                formData.append('action', 'purge_room');
                formData.append('target_domain', domain);
                
                try {
                    await fetch('direct.php', { method: 'POST', body: formData });
                    Terminal.toast('[✓] LINK PURGED SUCCESSFULLY', 'success');
                    setTimeout(() => location.reload(), 1000);
                } catch(err) {
                    Terminal.splash.hide();
                    Terminal.toast('[!] PURGE FAILED', 'danger');
                }
            }
        }

        // ==========================================
        // 🔐 THE DECRYPTOR (Read E2E Messages)
        // ==========================================
        window.addEventListener('DOMContentLoaded', async () => {
            const privPem = localStorage.getItem('relay_privkey');
            if(!privPem) return;

            try {
                const binStr = window.atob(privPem);
                const bytes = new Uint8Array(binStr.length);
                for (let i = 0; i < binStr.length; i++) { bytes[i] = binStr.charCodeAt(i); }
                
                const privateKey = await window.crypto.subtle.importKey(
                    "pkcs8", bytes.buffer, { name: "RSA-OAEP", hash: "SHA-256" }, true, ["decrypt"]
                );

                const dec = new TextDecoder();
                const msgs = document.querySelectorAll('.e2e-msg');
                
                for(let msg of msgs) {
                    const cipherText = msg.getAttribute('data-cipher');
                    if (cipherText.length > 200 && /^[A-Za-z0-9+/=]+$/.test(cipherText)) {
                        try {
                            const cBinStr = window.atob(cipherText);
                            const cBytes = new Uint8Array(cBinStr.length);
                            for (let i = 0; i < cBinStr.length; i++) { cBytes[i] = cBinStr.charCodeAt(i); }
                            
                            const decrypted = await window.crypto.subtle.decrypt(
                                { name: "RSA-OAEP" }, privateKey, cBytes.buffer
                            );
                            
                            msg.innerHTML = dec.decode(decrypted).replace(/\n/g, '<br>');
                            msg.style.color = "var(--t-green-dim)";
                        } catch(e) {
                            console.log('Decryption skip: ', e);
                        }
                    }
                }
            } catch(err) {
                console.error("Core Decryption Failed: ", err);
            }
        });

        // ==========================================
        // 🔐 THE ENCRYPTOR (Intercept Submission Form)
        // ==========================================
        const replyForm = document.getElementById('reply-form');
        replyForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            Terminal.splash.show('> ESTABLISHING QUANTUM LINK...');

            const targetUrl = document.getElementById('target-input').value.replace(/\/$/, '');
            let rawContent = document.getElementById('content-input').value;
            const localPem = localStorage.getItem('relay_pubkey');
            
            // Failsafe: Prevent empty payload error if sending Audio/Media only
            if (rawContent.trim() === '' && (document.getElementById('audio-base64').value !== '' || document.getElementById('media-input').files.length > 0)) {
                rawContent = '[ 🎙️ SECURE_MEDIA_TRANSMISSION ]';
            } else if (rawContent.trim() === '') {
                Terminal.splash.hide();
                Terminal.toast('[!] TRANSMISSION CANNOT BE EMPTY', 'danger');
                return;
            }

            try {
                if(!localPem) throw new Error("Local Public Key is missing! Open Main Console to generate one.");

                Terminal.splash.show('> PINGING TARGET HANDSHAKE...');
                const pingRes = await fetch(targetUrl + '/api_ping.php');
                if(!pingRes.ok) throw new Error("Target node is unreachable or offline.");
                const pingData = await pingRes.json();
                
                if(!pingData.public_key) {
                    throw new Error("Target node does not support E2E Encryption (Missing Public Key).");
                }

                Terminal.splash.show('> ENCRYPTING PAYLOADS...');
                
                async function importPubKey(pem) {
                    const binStr = window.atob(pem);
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
                Terminal.splash.hide();
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