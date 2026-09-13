<?php
// Smoke test: load every entry point in a sandboxed subprocess and confirm it
// does not fatal AND does not read anything undefined.
//
// The second half was added in v8.1.1. Checked for fatals only, this suite
// passed on a build whose public page logged a warning on every view; see the
// note on $undefined_markers below.
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

// ---------------------------------------------------------------------------
// Sandbox. Several entry points require core/db_connect.php, which creates the
// SQLite file on first use. Without an override that file lands in the
// repository's own data/ directory - which is where it went until CI caught it,
// on this workflow's very first run, via the "no database left behind" step.
// RELAY_DB_FILE redirects it here instead. The directory is reused across cases
// so the schema is created once, and removed at the end.
// ---------------------------------------------------------------------------
$sandbox = sys_get_temp_dir() . '/relay_smoke_' . getmypid();
$sandboxDb = $sandbox . '/data/relay_core.sqlite';
$sandboxSess = $sandbox . '/sessions';

foreach ([$sandbox . '/data', $sandbox . '/media', $sandboxSess] as $d) {
    if (!is_dir($d)) { mkdir($d, 0777, true); }
}
foreach ([$sandboxDb, $sandboxDb . '-wal', $sandboxDb . '-shm'] as $f) { @unlink($f); }

/**
 * Bring the sandbox up to a state where the pages actually do something.
 *
 * This is the difference between loading a file and exercising it. Without a
 * schema every entry point queried a table that did not exist, got nothing
 * back, and skipped the loop that renders a transmission - which is precisely
 * where v8.1.0's undefined variable lived. The suite reported 13 clean loads
 * and knew nothing about the code path that was broken.
 *
 * installer/schema.sql is the authority on the shape, so it is read rather than
 * duplicated here; a schema change can then never drift away from this test.
 */
function smoke_seed(PDO $db, $schemaFile)
{
    if (!is_file($schemaFile)) { return false; }

    // Strip comment lines, then split on ';'. Enough for this file, which is
    // only CREATE TABLE / CREATE INDEX.
    $sql = '';
    foreach (explode("\n", (string) file_get_contents($schemaFile)) as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0) { continue; }
        $sql .= $line . "\n";
    }
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $db->exec($statement);
    }

    // One local and one incoming signal, so both halves of every render branch
    // are reachable: the label, the buttons and the media matrix all differ.
    $ins = $db->prepare("INSERT INTO transmissions (content, visibility, is_remote, is_relay, origin_id, author_alias)
                         VALUES (:c, 'public', :r, :y, :o, :a)");
    $ins->execute([':c' => 'smoke local', ':r' => 0, ':y' => 0, ':o' => null, ':a' => 'LOCAL_COMMAND']);
    $ins->execute([':c' => 'smoke incoming', ':r' => 1, ':y' => 0, ':o' => 'dna-smoke', ':a' => 'THEM@peer.example']);

    return true;
}

$seeded = false;
try {
    $seedDb = new PDO('sqlite:' . $sandboxDb);
    $seedDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $seeded = smoke_seed($seedDb, $ROOT . '/installer/schema.sql');
    $seedDb = null;
} catch (Throwable $e) {
    echo "  NOTE  the sandbox could not be seeded ({$e->getMessage()}); pages will not be exercised\n";
}
if (!$seeded) {
    echo "  NOTE  installer/schema.sql was not found, so this run only proves the files load\n";
}

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

// Markers that mean the code read something that does not exist.
//
// These are warnings rather than fatals, and that is exactly why they used to
// pass: a green run only ever proved that nothing fatal happened. v8.1.0
// shipped an index.php that named a variable it never defined - every single
// view of the public page wrote a line to the production error log, every test
// in this suite passed, and the only thing that noticed was the server. PHP
// hands back null for an undefined variable, so the page still rendered and the
// count was still right, which is what made it invisible.
//
// Kept separate from the fatal list because the two mean different things: a
// fatal is "this endpoint is broken", an undefined read is "this code is lying
// about what it knows".
$undefined_markers = [
    'Undefined variable',
    'Undefined array key',
    'Undefined property',
    'Trying to access array offset on value of type null',
    'Trying to access array offset on null',
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
    $boot .= 'ini_set("session.save_path", ' . var_export($sandboxSess, true) . ');';
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

    $cmd = 'RELAY_DB_FILE=' . escapeshellarg($sandboxDb)
         . ' ' . $php_cmd . ' -d display_errors=1 -d error_reporting=E_ALL -r '
         . escapeshellarg($boot) . ' 2>&1';
    $out = (string) shell_exec($cmd);

    $hit = null;
    foreach ($fatal_markers as $m) {
        if (stripos($out, $m) !== false) { $hit = $m; break; }
    }
    if ($hit === null) {
        foreach ($undefined_markers as $m) {
            if (stripos($out, $m) !== false) { $hit = $m; break; }
        }
    }
    if ($hit === null && stripos($out, 'SMOKE_FATAL') !== false) { $hit = 'shutdown-time fatal'; }

    // Notices about the schema are acceptable: tables are created by the
    // installer, which is not run here, so "no such table" is an artefact of
    // this sandbox and not a defect. A fatal, or a read of something undefined,
    // is neither.
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

// ---------------------------------------------------------------------------
// Tear the sandbox down, and prove it: the point of RELAY_DB_FILE is that this
// suite cannot write into the repository, so if a database is still sitting in
// data/ afterwards, something ignored the override.
// ---------------------------------------------------------------------------
$rm = function ($path) use (&$rm) {
    if (is_dir($path)) {
        foreach (scandir($path) as $e) {
            if ($e === '.' || $e === '..') { continue; }
            $rm($path . '/' . $e);
        }
        @rmdir($path);
    } else {
        @unlink($path);
    }
};
$rm($sandbox);

$strayDb = $ROOT . '/data/relay_core.sqlite';
if (file_exists($strayDb)) {
    echo "  FAIL  smoke test wrote into the repository's data/ directory\n";
    $fail++;
}

echo "\n==================================================================\n";
printf(" SMOKE: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
