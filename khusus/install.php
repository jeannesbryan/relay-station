<?php
// RELAY STATION: DROP-POD INSTALLER V7.2 (The Social Signal Update)
// This script will extract core files, build the database, and self-destruct.

// ---------------------------------------------------------------------------
// [ V8.0 ] SESSION HARDENING
// ---------------------------------------------------------------------------
// The installer authenticates nobody, but it does set the master passcode and
// then logs itself in. A pre-created session id could therefore be fixated
// across installation, so the cookie is locked down and the id is regenerated
// at the moment of privilege change (below).
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    $installer_secure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
                     || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    if ($installer_secure) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

$zip_file = 'relay.zip'; 
$schema_file = 'schema.sql';
$data_dir = 'data';
$db_file = $data_dir . '/relay_core.sqlite';
$error_msg = null;
$success_msg = null;

// Disable execution time limit for slow hosting servers
set_time_limit(0);

// ---------------------------------------------------------------------------
// [ V8.0 ] RUN-ONCE GUARD
// ---------------------------------------------------------------------------
// This script is remotely reachable and, on a successful run, sets the master
// passcode from an unauthenticated POST and logs the caller in as captain.
// Whoever reached it first therefore owned the node. There is also nothing to
// install once it has run, so it now refuses as soon as a database exists.
if (file_exists($db_file)) {
    http_response_code(409);
    die("<h2 style='color:#ff003c; background:#0a0a0a; padding:20px; font-family:monospace; text-align:center;'>"
      . "[ STOP ] This station is already installed. Delete khusus/install.php from the server.</h2>");
}

if (!class_exists('ZipArchive')) {
    die("<h2 style='color:#ff003c; background:#0a0a0a; padding:20px; font-family:monospace; text-align:center;'>[ CRITICAL ERROR ] Your hosting server does not support PHP ZipArchive. Installation aborted.</h2>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['passcode'])) {
    $raw_passcode = $_POST['passcode'];
    $hashed_passcode = password_hash($raw_passcode, PASSWORD_DEFAULT);

    // 1. CORE FILE EXTRACTION (relay.zip)
    if (file_exists($zip_file)) {
        $zip = new ZipArchive;
        if ($zip->open($zip_file) === TRUE) {

            // [ V8.0 ] ZIP-SLIP GUARD
            // extractTo(__DIR__) with no entry validation: an archive entry
            // named ../../x.php, an absolute path, or a Windows drive path
            // escapes the destination entirely and can overwrite anything the
            // web user can write - which is remote code execution. Every entry
            // is now confirmed to land inside the destination before anything
            // is written, and the whole archive is rejected if any entry fails
            // so a hostile file cannot be partially applied.
            $bad_entry = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) { $bad_entry = '(unreadable entry)'; break; }

                $norm = str_replace('\\', '/', $entry);

                $is_bad = ($norm === '')
                    || ($norm[0] === '/')                        // absolute
                    || (bool) preg_match('~^[A-Za-z]:/~', $norm)  // windows drive
                    || (strpos($norm, "\0") !== false)            // null byte
                    || (strpos('/' . $norm . '/', '/../') !== false); // traversal

                // Resolve the path segment by segment. This is a lexical check,
                // deliberately NOT realpath(): the archive has not been
                // extracted yet, so directories it is about to create do not
                // exist, and realpath() would return false for every legitimate
                // nested entry - blocking a valid installation.
                if (!$is_bad) {
                    $depth = 0;
                    foreach (explode('/', $norm) as $seg) {
                        if ($seg === '' || $seg === '.') { continue; }
                        if ($seg === '..') {
                            $depth--;
                            if ($depth < 0) { $is_bad = true; break; }
                            continue;
                        }
                        $depth++;
                    }
                }

                if ($is_bad) { $bad_entry = $entry; break; }
            }

            if ($bad_entry !== null) {
                $zip->close();
                error_log('[RELAY][SECURITY] installer rejected archive entry: ' . $bad_entry);
                die("<h2 style='color:#ff003c; background:#0a0a0a; padding:20px; font-family:monospace; text-align:center;'>"
                  . "[ SECURITY STOP ] The archive contains an unsafe path and was not extracted.</h2>");
            }

            $extract_success = $zip->extractTo(__DIR__);
            $zip->close();

            if ($extract_success && file_exists(__DIR__ . '/console.php')) {
                
                // 2. DATA & MEDIA BUNKER CONSTRUCTION
                if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
                // Lock data folder from direct browser access
                // Deny for both Apache 2.2 and 2.4. The old single-line
                // "Deny from all" is Apache 2.2 syntax and is ignored by 2.4
                // without mod_access_compat, which would leave the database
                // unprotected on a modern host.
                file_put_contents($data_dir . '/.htaccess',
                    "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
                  . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
                
                $media_dir = 'media';
                if (!is_dir($media_dir)) mkdir($media_dir, 0755, true);

                // 3. CORE MEMORY (DATABASE) CONSTRUCTION
                try {
                    $db = new PDO("sqlite:" . $db_file);
                    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    if (file_exists($schema_file)) {
                        $sql = file_get_contents($schema_file);
                        $db->exec($sql);
                        
                        // ==========================================
                        // [ INJECT CORE SYSTEM VARIABLES ]
                        // ==========================================
                        
                        // Deteksi URL peladen saat ini secara otomatis
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $host = $_SERVER['HTTP_HOST'];
                        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                        $current_planet_url = $protocol . $host . $path;
                        
                        $stmt = $db->prepare("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES (:key, :val)");
                        $stmt->execute([':key' => 'captain_hash', ':val' => $hashed_passcode]);
                        
                        // Suntikkan Local Planet URL untuk pemicu fitur Nomadic
                        $stmt = $db->prepare("INSERT OR REPLACE INTO system_config (config_key, config_value) VALUES (:key, :val)");
                        $stmt->execute([':key' => 'local_planet_url', ':val' => $current_planet_url]);
                        // ==========================================

                        $success_msg = "[ SYSTEM ONLINE ] Station successfully built. Activating self-destruct protocol...";
                        
                        // 4. SELF-DESTRUCT PROTOCOL (Extreme Security)
                        // Erase installation traces to prevent hijacking
                        @unlink($zip_file);    // Delete relay.zip (Inner ZIP)
                        @unlink($schema_file); // Delete schema.sql
                        
                        // Delete outer wrapper (Outer ZIP) if the user forgets to delete it
                        $installer_zips = glob('Relay-Installer*.zip');
                        if ($installer_zips) {
                            foreach ($installer_zips as $iz) { @unlink($iz); }
                        }
                        
                        @unlink(__FILE__);     // Delete install.php (This script itself!)
                        
                        // Automatic Captain login. Regenerate first: the id
                        // in use during installation must not become the id of
                        // the authenticated session.
                        session_regenerate_id(true);
                        $_SESSION['relay_auth'] = true;

                        // Redirect to console after 3 seconds
                        header("refresh:3;url=console.php");
                    } else {
                        $error_msg = "[ ERROR ] File schema.sql not found!";
                    }
                } catch (PDOException $e) {
                    // The driver message contains the database path.
                    error_log('[RELAY] installer database error: ' . $e->getMessage());
                    $error_msg = "[ DATABASE ERROR ] Installation failed. Check the server log.";
                }

            } else {
                $error_msg = "[ ERROR ] Extraction failed or core files are incomplete.";
            }
        } else {
            $error_msg = "[ ERROR ] Failed to open relay.zip. File is corrupted.";
        }
    } else {
        $error_msg = "[ ERROR ] File relay.zip not found in this directory.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RELAY | Genesis Deployment</title>
    <style>
        :root { --t-green: #00ff41; --bg-base: #030303; --t-red: #ff003c; }
        /* No third-party font. The installer is the first page a node ever
           serves; loading a typeface from Google would beacon the node's very
           first request to a third party. */
        body { background: var(--bg-base); color: var(--t-green); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .t-card { background: #0a0a0a; border: 1px solid var(--t-green); padding: 30px; width: 100%; max-width: 450px; text-align: center; box-shadow: 0 0 15px rgba(0,255,65,0.1); box-sizing: border-box; }
        
        /* Modifikasi Input Group untuk Show/Hide */
        .input-group { position: relative; margin-bottom: 20px; width: 100%; }
        .t-input { width: 100%; background: #000; border: 1px dashed var(--t-green); color: var(--t-green); padding: 12px; padding-right: 80px; box-sizing: border-box; font-family: inherit; text-align: center; letter-spacing: 2px; outline: none; transition: 0.2s; }
        .t-input:focus { border-style: solid; box-shadow: 0 0 10px rgba(0,255,65,0.2); }
        .toggle-btn { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--t-green); cursor: pointer; font-family: inherit; font-weight: bold; font-size: 12px; opacity: 0.7; transition: 0.2s; }
        .toggle-btn:hover { opacity: 1; color: #fff; }
        
        .t-btn { background: rgba(0,255,65,0.1); border: 1px solid var(--t-green); color: var(--t-green); padding: 12px; width: 100%; cursor: pointer; font-family: inherit; font-weight: bold; transition: 0.2s; }
        .t-btn:hover { background: var(--t-green); color: #000; }
        .t-blink { animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: 0; } }
    </style>
</head>
<body>

    <div class="t-card">
        <?php if($success_msg): ?>
            <h2 class="t-blink" style="margin-bottom: 20px;">> DEPLOYMENT_SUCCESS</h2>
            <p style="font-size: 14px; line-height: 1.6;"><?php echo $success_msg; ?></p>
            <p style="font-size: 12px; margin-top: 20px; opacity: 0.7;">Redirecting to Main Console...</p>
        <?php else: ?>
            <h2 style="margin-bottom: 5px;">> RELAY GENESIS <span class="t-blink">_</span></h2>
            <p style="font-size: 12px; opacity: 0.7; margin-bottom: 25px;">System detected <strong>relay.zip</strong>. Initialize deployment sequence V7.2</p>
            
            <?php if($error_msg): ?>
                <div style="background: rgba(255,0,60,0.1); color: var(--t-red); border: 1px dashed var(--t-red); padding: 10px; font-size: 12px; margin-bottom: 20px; text-align: left;">
                    <?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <input type="password" id="master-passcode" name="passcode" class="t-input" placeholder="CREATE MASTER PASSCODE" required autofocus>
                    <button type="button" class="toggle-btn" onclick="togglePass()">[ SHOW ]</button>
                </div>
                <button type="submit" class="t-btn">[ INITIATE_DEPLOYMENT ]</button>
            </form>
            
            <p style="font-size: 10px; margin-top: 20px; opacity: 0.5;">* The installer will destroy itself and the outer wrapper files after execution.</p>
        <?php endif; ?>
    </div>

    <script>
        // Logika Show/Hide Password
        function togglePass() {
            const input = document.getElementById('master-passcode');
            const btn = document.querySelector('.toggle-btn');
            
            if (input.type === 'password') {
                input.type = 'text';
                btn.innerText = '[ HIDE ]';
                btn.style.color = 'var(--t-red)'; // Biar keliatan kalau lagi mode bahaya (terlihat)
            } else {
                input.type = 'password';
                btn.innerText = '[ SHOW ]';
                btn.style.color = 'var(--t-green)';
            }
        }
    </script>
</body>
</html>