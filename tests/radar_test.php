<?php
// Radar sweep test: does a sweep survive unreachable peers, and does it give a
// silent peer a grace period instead of deleting it on the first miss?
//
// WHY THIS EXISTS: until v8.0.3 a single unanswered ping deleted a followed node
// outright. Worse, the sweep did not even get that far: the outbound guard
// rejects any host that does not resolve - which is exactly what an offline peer
// looks like - and the resulting null handle was passed to curl_getinfo(),
// producing an uncaught TypeError. The whole sweep died with an empty 500 body,
// leaving every node untouched and the operator with no idea why. Nothing about
// that is visible without a real run against a table that contains a dead node,
// which is why this test builds one.
//
// Runs against a throwaway database (via RELAY_DB_FILE), never the live one.
//
// Run: php tests/radar_test.php

$ROOT = dirname(__DIR__);

$pass = 0; $fail = 0;
function check($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra !== '' ? "   [$extra]" : '') . "\n"; }
}

// ---------------------------------------------------------------------------
// Sandbox
// ---------------------------------------------------------------------------
$sandbox = sys_get_temp_dir() . '/relay_radar_' . getmypid();
$dbPath  = $sandbox . '/data/relay_core.sqlite';
$sessDir = $sandbox . '/sessions';

foreach ([$sandbox . '/data', $sandbox . '/media', $sessDir] as $d) {
    if (!is_dir($d)) { mkdir($d, 0777, true); }
}
foreach ([$dbPath, $dbPath . '-wal', $dbPath . '-shm'] as $f) { @unlink($f); }

$schema = file_get_contents($ROOT . '/installer/schema.sql');
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec($schema);

// Two peers that can never answer, plus one that is refused outright by the
// guard for being in a private range.
$ins = $db->prepare("INSERT INTO following (planet_url, alias, handshake_token) VALUES (?,?,?)");
$ins->execute(['https://peer-offline-9f3a2b.invalid/relay', 'OFFLINE_PEER', 't1']);
$ins->execute(['http://10.0.0.5/relay', 'PRIVATE_RANGE_PEER', 't2']);

check('sandbox database built from schema.sql',
      (int) $db->query("SELECT COUNT(*) FROM following")->fetchColumn() === 2);

// Confirm the sandbox schema really has the v8.0.3 columns; if the migration or
// schema.sql drifted, the sweep below would fail for the wrong reason.
$cols = [];
foreach ($db->query("PRAGMA table_info(following)") as $c) { $cols[] = $c['name']; }
check('schema declares last_seen and failure_count',
      in_array('last_seen', $cols, true) && in_array('failure_count', $cols, true),
      implode(',', $cols));
$db = null;

// ---------------------------------------------------------------------------
// Run the sweep, in a subprocess, as an authenticated commander.
// ---------------------------------------------------------------------------
$php_cmd = escapeshellarg(PHP_BINARY);
$ext_dir = ini_get('extension_dir');
if ($ext_dir && is_dir($ext_dir)) {
    $php_cmd .= ' -d extension_dir=' . escapeshellarg($ext_dir);
    foreach (get_loaded_extensions() as $ext) {
        if (in_array($ext, ['Core', 'standard'], true)) { continue; }
        $so = strtolower($ext) . '.so';
        if (file_exists($ext_dir . '/' . $so)) {
            $php_cmd .= ' -d extension=' . escapeshellarg($so);
        }
    }
}

$fatal_markers = [
    'Fatal error', 'Uncaught Error', 'Uncaught TypeError',
    'must be of type CurlHandle', 'must be of type CurlMultiHandle',
    'Call to undefined', 'require_once(): Failed opening',
];

function runSweep($ROOT, $dbPath, $sessDir, $php_cmd, array $fatal_markers)
{
    $token = 'testtoken' . bin2hex(random_bytes(4));

    $boot  = '$root = ' . var_export($ROOT, true) . ';';
    $boot .= 'ini_set("session.save_path", ' . var_export($sessDir, true) . ');';
    $boot .= 'session_start();';
    // relay_session_start() returns early when a session is already active, so
    // seeding it here is enough to satisfy relay_require_auth().
    $boot .= '$_SESSION["relay_auth"] = true;';
    $boot .= '$_SESSION["relay_csrf"] = ' . var_export($token, true) . ';';
    $boot .= '$_SERVER["REQUEST_METHOD"] = "POST";';
    $boot .= '$_SERVER["HTTPS"] = "on"; $_SERVER["SERVER_PORT"] = 443;';
    $boot .= '$_SERVER["SCRIPT_NAME"] = "/core/radar_sweep.php";';
    $boot .= '$_SERVER["REQUEST_URI"] = "/core/radar_sweep.php";';
    $boot .= '$_SERVER["HTTP_HOST"] = "relay.example";';
    $boot .= '$_SERVER["REMOTE_ADDR"] = "203.0.113.200";';
    $boot .= '$_GET = [];';
    $boot .= '$_POST = ["relay_csrf" => ' . var_export($token, true) . '];';
    $boot .= 'chdir($root . "/core");';
    $boot .= 'ob_start();';
    $boot .= 'register_shutdown_function(function(){ $e = error_get_last();'
           . ' if ($e && in_array($e["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {'
           . ' fwrite(STDERR, "\\nSWEEP_FATAL: " . $e["message"]); } });';
    $boot .= 'require $root . "/core/radar_sweep.php";';

    $cmd = 'RELAY_DB_FILE=' . escapeshellarg($dbPath) . ' '
         . $php_cmd . ' -d display_errors=1 -d error_reporting=E_ALL -r '
         . escapeshellarg($boot) . ' 2>&1';

    $out = (string) shell_exec($cmd);

    $hit = null;
    foreach ($fatal_markers as $m) {
        if (stripos($out, $m) !== false) { $hit = $m; break; }
    }
    if ($hit === null && stripos($out, 'SWEEP_FATAL') !== false) { $hit = 'shutdown-time fatal'; }

    return [$out, $hit];
}

echo "\n== sweep survives unreachable peers ==\n";

list($out1, $fatal1) = runSweep($ROOT, $dbPath, $sessDir, $php_cmd, $fatal_markers);
check('sweep #1 does not fatal', $fatal1 === null, (string) $fatal1 . ' | ' . trim(substr($out1, 0, 160)));
check('sweep #1 reports completion', strpos($out1, '[ SWEEP COMPLETE ]') !== false,
      trim(substr($out1, 0, 120)));

// ---------------------------------------------------------------------------
// Grace period: a silent peer is kept, and only purged after the limit.
// ---------------------------------------------------------------------------
echo "\n== grace period ==\n";

$grace = 3; // RELAY_SWEEP_GRACE_LIMIT in core/radar_sweep.php

function persistState($dbPath)
{
    $d = new PDO('sqlite:' . $dbPath);
    $rows = [];
    foreach ($d->query("SELECT alias, failure_count FROM following ORDER BY id") as $r) {
        $rows[$r['alias']] = (int) $r['failure_count'];
    }
    return $rows;
}

$state1 = persistState($dbPath);
check('after sweep #1 both peers survive', count($state1) === 2, json_encode($state1));
check('after sweep #1 failure_count is 1',
      !empty($state1) && max($state1) === 1, json_encode($state1));

list($out2, $fatal2) = runSweep($ROOT, $dbPath, $sessDir, $php_cmd, $fatal_markers);
$state2 = persistState($dbPath);
check('sweep #2 does not fatal', $fatal2 === null, (string) $fatal2);
check('after sweep #2 both peers survive', count($state2) === 2, json_encode($state2));
check('after sweep #2 failure_count is 2',
      !empty($state2) && max($state2) === 2, json_encode($state2));

list($out3, $fatal3) = runSweep($ROOT, $dbPath, $sessDir, $php_cmd, $fatal_markers);
$state3 = persistState($dbPath);
check('sweep #3 does not fatal', $fatal3 === null, (string) $fatal3);
check("after $grace consecutive misses the peers are purged",
      count($state3) === 0, json_encode($state3));
check('sweep #3 says it purged', strpos($out3, 'Purged: 2') !== false,
      trim(substr($out3, 0, 120)));

// ---------------------------------------------------------------------------
// Cleanup
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

echo "\n==================================================================\n";
printf(" RADAR: %d passed, %d failed\n", $pass, $fail);
echo "==================================================================\n";
exit($fail === 0 ? 0 : 1);
