<?php
// Smoke test: load every entry point in a sandboxed subprocess and confirm it
// does not fatal.
//
// WHY THIS EXISTS: `php -l` only checks syntax. It cannot see an undefined
// function call, a wrong include path, or a function referenced before it is
// defined. During the 8.0 hardening a regex patch produced
// `relay_relay_session_start()` in five files - syntactically perfect, and a
// fatal error the moment anything requested those endpoints. Linting was green.
// This test actually loads each file so that class of mistake cannot survive.
//
// Run: php tests/smoke_test.php

$ROOT = dirname(__DIR__);

// name => superglobals to simulate
$cases = [
    'index.php'               => ['GET' => []],
    'console.php'             => ['GET' => []],
    'bookmarks.php'           => ['GET' => []],
    'direct.php'              => ['GET' => []],
    'api_ping.php'            => ['GET' => []],
    'api_handshake.php'       => ['POST' => []],
    'api_inbox.php'           => ['POST' => []],
    'core/add_planet.php'     => ['POST' => []],
    'core/alert_action.php'   => ['POST' => []],
    'core/radar_sweep.php'    => ['POST' => []],
    'core/remove_planet.php'  => ['POST' => []],
    'core/transmitter.php'    => ['POST' => []],
    'core/updater.php'        => ['GET' => []],
];

// Fatal-looking strings we must not see in the output.
$fatal_markers = [
    'Fatal error',
    'Uncaught Error',
    'Uncaught TypeError',
    'Undefined function',
    'Undefined constant',
    'Call to undefined',
    'require_once(): Failed opening',
    'require(): Failed opening',
    'include(): Failed opening',
];

$pass = 0; $fail = 0;

// Build the child-process PHP command.
//
// PHP_BINARY is the raw interpreter, which does not inherit any `-d extension=`
// flags this process was started with. On a normal PHP install the extensions
// come from php.ini and are picked up automatically; in a hand-assembled
// runtime (extracted .deb packages) they are not. Re-declaring the extensions
// that are already loaded here keeps the test self-contained either way, so a
// failure means the application is broken rather than the environment.
$php_cmd = escapeshellarg(PHP_BINARY);
$ext_dir = ini_get('extension_dir');
if ($ext_dir && is_dir($ext_dir)) {
    $php_cmd .= ' -d extension_dir=' . escapeshellarg($ext_dir);
    $builtin = ['Core','standard','date','libxml','openssl','pcre','zlib','filter',
                'json','hash','session','spl','random','Reflection','Phar','SPL',
                'PDO','curl','mbstring','sqlite3','pdo_sqlite','ctype','tokenizer',
                'fileinfo','iconv'];
    foreach (get_loaded_extensions() as $ext) {
        $so = strtolower($ext) . '.so';
        if (file_exists($ext_dir . '/' . $so) && !in_array($ext, ['Core','standard'], true)) {
            $php_cmd .= ' -d extension=' . escapeshellarg($so);
        }
    }
}

foreach ($cases as $rel => $sim) {
    $boot = '$root = ' . var_export($ROOT, true) . ';';
    $boot .= 'ini_set("session.save_path", "/tmp/relay_smoke_sess");';
    $boot .= '$_SERVER["REQUEST_METHOD"] = ' . var_export(
        isset($sim['POST']) ? 'POST' : 'GET', true) . ';';
    $boot .= '$_SERVER["HTTPS"]="on"; $_SERVER["SERVER_PORT"]=443;';
    $boot .= '$_SERVER["SCRIPT_NAME"] = ' . var_export('/' . $rel, true) . ';';
    $boot .= '$_SERVER["SCRIPT_FILENAME"] = $root . ' . var_export('/' . $rel, true) . ';';
    $boot .= '$_SERVER["REQUEST_URI"] = ' . var_export('/' . $rel, true) . ';';
    $boot .= '$_SERVER["HTTP_HOST"] = "relay.example";';
    $boot .= '$_SERVER["REMOTE_ADDR"] = "203.0.113.200";';
    $boot .= '$_GET = ' . var_export($sim['GET'] ?? [], true) . ';';
    $boot .= '$_POST = ' . var_export($sim['POST'] ?? [], true) . ';';
    $boot .= '$_SESSION = [];';
    $boot .= 'chdir($root . "/" . ' . var_export(dirname($rel), true) . ');';
    $boot .= 'ob_start(); register_shutdown_function(function(){ $e = error_get_last();'
           . ' if ($e && in_array($e["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {'
           . ' fwrite(STDERR, "\\nSMOKE_FATAL: " . $e["message"]); } });';
    $boot .= 'require $root . ' . var_export('/' . $rel, true) . ';';

    $cmd = $php_cmd . ' -d display_errors=1 -d error_reporting=E_ALL -r '
         . escapeshellarg($boot) . ' 2>&1';
    $out = (string) shell_exec($cmd);

    $hit = null;
    foreach ($fatal_markers as $m) {
        if (stripos($out, $m) !== false) { $hit = $m; break; }
    }
    if ($hit === null && stripos($out, 'SMOKE_FATAL') !== false) { $hit = 'shutdown-time fatal'; }

    // Notices about missing tables are acceptable: the schema is created by the
    // installer, which is not run here. A fatal is not.
    if ($hit === null) {
        $pass++;
        printf("  PASS  %-26s loads clean\n", $rel);
    } else {
        $fail++;
        printf("  FAIL  %-26s %s\n", $rel, $hit);
        $lines = explode("\n", $out);
        foreach ($lines as $l) {
            if (stripos($l, $hit) !== false) { echo "          " . trim($l) . "\n"; break; }
        }
    }
}

echo "\n==================================================================\n";
printf(" SMOKE: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
