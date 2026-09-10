<?php
// RELAY STATION: DROP-POD INSTALLER V7.2 (The Social Signal Update)
// This script will extract core files, build the database, and self-destruct.

session_start();

$zip_file = 'relay.zip'; 
$schema_file = 'schema.sql';
$data_dir = 'data';
$db_file = $data_dir . '/relay_core.sqlite';
$error_msg = null;
$success_msg = null;

// Disable execution time limit for slow hosting servers
set_time_limit(0);

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
            $extract_success = $zip->extractTo(__DIR__);
            $zip->close();

            if ($extract_success && file_exists(__DIR__ . '/console.php')) {
                
                // 2. DATA & MEDIA BUNKER CONSTRUCTION
                if (!is_dir($data_dir)) mkdir($data_dir, 0755, true);
                // Lock data folder from direct browser access
                file_put_contents($data_dir . '/.htaccess', "Deny from all\n");
                
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
                        
                        // Automatic Captain login
                        $_SESSION['relay_auth'] = true;

                        // Redirect to console after 3 seconds
                        header("refresh:3;url=console.php");
                    } else {
                        $error_msg = "[ ERROR ] File schema.sql not found!";
                    }
                } catch (PDOException $e) {
                    $error_msg = "[ DATABASE ERROR ] " . $e->getMessage();
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
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --t-green: #00ff41; --bg-base: #030303; --t-red: #ff003c; }
        body { background: var(--bg-base); color: var(--t-green); font-family: 'JetBrains Mono', monospace; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
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
                    <?php echo $error_msg; ?>
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