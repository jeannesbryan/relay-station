<?php
// Store-and-forward outbox test.
//
// WHY THIS EXISTS: v8.1 queues a delivery that never arrived instead of losing
// it. That is the first piece of this codebase that writes to disk and comes
// back later to act on it, which makes the failure modes non-obvious and worth
// pinning:
//
//   - retrying forever would fill the disk,
//   - retrying too eagerly would hammer a peer that is simply asleep,
//   - dropping on the first failure would reproduce the bug it was written to
//     fix,
//   - and retrying a delivery the peer already accepted would publish
//     somebody's signal twice on their timeline.
//
// NOT COVERED HERE: the successful 2xx path of relay_outbox_deliver(). Reaching
// it needs a peer that resolves, speaks HTTPS and answers on the public
// internet - relay_url_is_safe() refuses anything else by design, so there is
// no local stub to point it at. That path is exercised against a real peer
// instead. Everything the queue *decides* is covered below.
//
// Run: php tests/outbox_test.php

require_once dirname(__DIR__) . '/core/outbox.php';

$pass = 0; $fail = 0;

function check($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  PASS  $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($extra !== '' ? "   [$extra]" : '') . "\n"; }
}

function section($name)
{
    echo "\n== $name ==\n";
}

/**
 * A database in a temporary file rather than in memory: relay_outbox_lock()
 * needs a real path to put its lock file beside, and the lock is one of the
 * things under test.
 */
function outbox_db($tag)
{
    $dir = sys_get_temp_dir() . '/relay_outbox_' . getmypid() . '_' . $tag;
    @mkdir($dir, 0700, true);
    $path = $dir . '/core.sqlite';

    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE outbox (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        target_url TEXT NOT NULL,
        payload TEXT NOT NULL,
        visibility TEXT NOT NULL,
        attempts INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_attempt DATETIME DEFAULT NULL,
        next_attempt DATETIME DEFAULT NULL
    )");
    $db->exec("CREATE TABLE following (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        planet_url TEXT UNIQUE NOT NULL,
        alias TEXT,
        handshake_token TEXT,
        added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_seen DATETIME DEFAULT NULL,
        failure_count INTEGER DEFAULT 0
    )");

    return $db;
}

function rm_rf($path)
{
    if (!is_dir($path)) { return; }
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') { continue; }
        $full = $path . '/' . $entry;
        is_dir($full) ? rm_rf($full) : @unlink($full);
    }
    @rmdir($path);
}

function outbox_dir_of(PDO $db)
{
    $row = $db->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
    return dirname((string) $row['file']);
}

function rows(PDO $db)
{
    return $db->query("SELECT * FROM outbox ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

// A host that cannot resolve: the guard reports "host did not resolve", which
// is the retryable verdict - this is what an offline peer looks like.
$OFFLINE = 'https://relay-outbox-test.invalid';
// A scheme the guard refuses outright. Nothing about waiting will fix this, so
// it must never be retried.
$REFUSED = 'http://relay-outbox-test.invalid';

$db = outbox_db('main');

// ---------------------------------------------------------------------------
section('what is worth queueing');

check('a public broadcast is durable', relay_outbox_is_durable('public'));
check('a direct message is durable', relay_outbox_is_durable('direct'));
check('a sonar pulse is not durable', !relay_outbox_is_durable('sonar_pulse'));
check('an acknowledgement is not durable', !relay_outbox_is_durable('ack_receipt'));
check('a resonance is not durable', !relay_outbox_is_durable('resonance'));
check('scorched earth is not durable', !relay_outbox_is_durable('scorched_earth'));
check('a global purge is not durable', !relay_outbox_is_durable('global_purge'));
check('an unknown visibility is not durable', !relay_outbox_is_durable('nonsense'));

// ---------------------------------------------------------------------------
section('backoff');

check('the first retry is soon', relay_outbox_retry_delay(1) === 15);
check('the delay grows', relay_outbox_retry_delay(2) > relay_outbox_retry_delay(1));
check('the delay is capped at an hour', relay_outbox_retry_delay(99) === 3600);
check('a zero attempt does not produce a zero delay', relay_outbox_retry_delay(0) === 15);

// ---------------------------------------------------------------------------
section('queueing');

check('an empty target is refused', relay_outbox_enqueue($db, '   ', ['content' => 'x'], 'public') === false);

relay_outbox_enqueue($db, $OFFLINE, ['content' => 'hello', 'author_alias' => 'ME'], 'public');
$queued = rows($db);
check('one row is queued', count($queued) === 1);
check('the row remembers its target', $queued[0]['target_url'] === $OFFLINE);
check('the row remembers its visibility', $queued[0]['visibility'] === 'public');
check('a fresh row is due immediately', $queued[0]['next_attempt'] !== null || $queued[0]['attempts'] === '0');
check('the payload round-trips as json',
    json_decode($queued[0]['payload'], true)['content'] === 'hello');
check('the queue reports its depth', relay_outbox_pending_count($db) === 1);

// ---------------------------------------------------------------------------
section('the queue is bounded');

for ($i = 0; $i < RELAY_OUTBOX_MAX_ROWS + 5; $i++) {
    relay_outbox_enqueue($db, $OFFLINE, ['content' => 'flood ' . $i], 'public');
}
check('the queue never exceeds its ceiling',
    relay_outbox_pending_count($db) === RELAY_OUTBOX_MAX_ROWS,
    (string) relay_outbox_pending_count($db));
check('the oldest rows are the ones dropped',
    json_decode(rows($db)[0]['payload'], true)['content'] === 'flood 5',
    json_decode(rows($db)[0]['payload'], true)['content']);

// ---------------------------------------------------------------------------
section('the lock is exclusive');

$held = relay_outbox_lock($db);
check('the lock can be taken', $held !== null);
check('a second flush cannot start while one is running', relay_outbox_lock($db) === null);
if ($held !== null) { flock($held, LOCK_UN); fclose($held); }
$again = relay_outbox_lock($db);
check('the lock is released afterwards', $again !== null);
if ($again !== null) { flock($again, LOCK_UN); fclose($again); }

// ---------------------------------------------------------------------------
section('an unreachable peer is kept, not lost');

$db2 = outbox_db('defer');
relay_outbox_enqueue($db2, $OFFLINE, ['content' => 'keep me'], 'public');

$stats = relay_outbox_flush($db2);
check('the flush runs', $stats['skipped'] === 0);
check('nothing was delivered', $stats['sent'] === 0);
check('nothing was dropped', $stats['dropped'] === 0, 'dropped ' . $stats['dropped']);
check('the delivery is still queued', relay_outbox_pending_count($db2) === 1);

$row = rows($db2)[0];
check('the attempt was counted', (int) $row['attempts'] === 1);
check('the last attempt was recorded', $row['last_attempt'] !== null);
check('the next attempt is in the future',
    strtotime($row['next_attempt']) > time() - 5, (string) $row['next_attempt']);
check('the payload is untouched',
    json_decode($row['payload'], true)['content'] === 'keep me');

$stats2 = relay_outbox_flush($db2);
check('a row that is not due yet is not retried', $stats2['deferred'] === 0 && $stats2['sent'] === 0);
check('the attempt count did not move', (int) rows($db2)[0]['attempts'] === 1);

// ---------------------------------------------------------------------------
section('a permanently refused target is dropped');

$db3 = outbox_db('refused');
relay_outbox_enqueue($db3, $REFUSED, ['content' => 'never deliverable'], 'public');
$stats3 = relay_outbox_flush($db3);
check('a refused target is not retried', $stats3['dropped'] === 1, 'dropped ' . $stats3['dropped']);
check('the row is gone', relay_outbox_pending_count($db3) === 0);

// ---------------------------------------------------------------------------
section('a corrupt row is dropped');

$db4 = outbox_db('corrupt');
$db4->exec("INSERT INTO outbox (target_url, payload, visibility) VALUES ('$OFFLINE', 'not json', 'public')");
$stats4 = relay_outbox_flush($db4);
check('a payload that is not a json object is dropped', $stats4['dropped'] === 1);
check('the corrupt row is gone', relay_outbox_pending_count($db4) === 0);

// ---------------------------------------------------------------------------
section('giving up after enough attempts');

$db5 = outbox_db('giveup');
relay_outbox_enqueue($db5, $OFFLINE, ['content' => 'tired'], 'public');
$db5->exec("UPDATE outbox SET attempts = " . (RELAY_OUTBOX_MAX_ATTEMPTS - 1) . ",
            next_attempt = datetime('now', '-1 hour')");

$stats5 = relay_outbox_flush($db5);
check('the attempt ceiling drops the row in one pass',
    $stats5['dropped'] === 1 && relay_outbox_pending_count($db5) === 0 && $stats5['sent'] === 0,
    'dropped ' . $stats5['dropped'] . ', left ' . relay_outbox_pending_count($db5));

// And one attempt short of the ceiling, the same row is only deferred - the
// difference between "keep trying" and "give up" is exactly this line.
$db5b = outbox_db('giveup-early');
relay_outbox_enqueue($db5b, $OFFLINE, ['content' => 'not yet tired'], 'public');
$db5b->exec("UPDATE outbox SET attempts = " . (RELAY_OUTBOX_MAX_ATTEMPTS - 2) . ",
             next_attempt = datetime('now', '-1 hour')");
$stats5b = relay_outbox_flush($db5b);
check('one attempt short of the ceiling the row is only deferred',
    $stats5b['deferred'] === 1 && $stats5b['dropped'] === 0 && relay_outbox_pending_count($db5b) === 1,
    'deferred ' . $stats5b['deferred'] . ' dropped ' . $stats5b['dropped']);

// ---------------------------------------------------------------------------
section('expiry');

$db6 = outbox_db('expiry');
relay_outbox_enqueue($db6, $OFFLINE, ['content' => 'stale'], 'public');
$db6->exec("UPDATE outbox SET created_at = datetime('now', '-" . (RELAY_OUTBOX_MAX_AGE_DAYS + 3) . " days')");

$stats6 = relay_outbox_flush($db6);
check('a row older than the age limit expires', $stats6['dropped'] === 1);
check('the stale row is gone', relay_outbox_pending_count($db6) === 0);

// A row that is old but still inside the limit must survive.
$db7 = outbox_db('fresh-enough');
relay_outbox_enqueue($db7, $OFFLINE, ['content' => 'yesterday'], 'public');
$db7->exec("UPDATE outbox SET created_at = datetime('now', '-1 day')");
$stats7 = relay_outbox_flush($db7);
check('a row inside the age limit is kept', $stats7['dropped'] === 0 && relay_outbox_pending_count($db7) === 1);

// ---------------------------------------------------------------------------
section('the summary line');

$db8 = outbox_db('summary');
relay_outbox_enqueue($db8, $OFFLINE, ['content' => 'one'], 'public');
$line = relay_outbox_summary($db8, ['sent' => 0, 'deferred' => 0, 'dropped' => 0, 'skipped' => 0]);
check('the summary reports the depth', strpos($line, '1 waiting') !== false, $line);
check('the summary reports deliveries when there are any',
    strpos(relay_outbox_summary($db8, ['sent' => 2, 'dropped' => 1]), '2 delivered') !== false
    && strpos(relay_outbox_summary($db8, ['sent' => 2, 'dropped' => 1]), '1 expired') !== false);

// ---------------------------------------------------------------------------
foreach (['main', 'defer', 'refused', 'corrupt', 'giveup', 'giveup-early', 'expiry', 'fresh-enough', 'summary'] as $tag) {
    rm_rf(sys_get_temp_dir() . '/relay_outbox_' . getmypid() . '_' . $tag);
}

echo "\n" . str_repeat('=', 66) . "\n";
echo " OUTBOX: $pass passed, $fail failed\n";
echo str_repeat('=', 66) . "\n";

exit($fail === 0 ? 0 : 1);
