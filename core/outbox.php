<?php
// ==========================================================
// 📮 RELAY STATION: STORE-AND-FORWARD OUTBOX (V8.1)
// ==========================================================
// WHY THIS EXISTS
//
// Before v8.1 a signal that could not be delivered was simply lost. The
// transmitter opened one connection with a five second timeout, and if the
// ally's host did not answer, the signal was gone: it sat in the operator's
// own timeline and nowhere else, with nothing on screen to say it had not
// travelled. The only trace was a line in the server log, which is to say
// nobody was told.
//
// That is the wrong failure mode for a network whose entire premise is that
// you own the sending. A peer rebooting, a laptop lid closing, a DNS entry
// momentarily gone - each of those silently cost the operator a delivery they
// believed had happened.
//
// So a delivery that fails for a reason time can fix is now queued here and
// retried on the next Radar Sweep, which already knows which peers are up.
//
// WHAT IS QUEUED, AND WHAT IS NOT
//
// Only the two signal types a human actually authored: `public` broadcasts and
// `direct` messages. The tactical pulses (sonar_pulse, ack_receipt, resonance,
// scorched_earth, global_purge) are deliberately NOT queued - they are
// machine chatter whose meaning expires with the moment. A sonar ping or an
// acknowledgement that arrives six hours late is not a late delivery, it is a
// false statement about the present.
//
// DELIVERY SEMANTICS
//
// At-least-once. A row is removed only after a 2xx response, so a crash
// between sending and deleting causes one repeated delivery rather than a
// lost one. Receivers de-duplicate where it matters (signal_resonance carries
// a UNIQUE(post_id, reactor_url) guard).

require_once __DIR__ . '/security.php';

// Signals a human wrote, which stay meaningful until they arrive.
const RELAY_OUTBOX_DURABLE_VISIBILITIES = ['public', 'direct'];

// Give up after this many attempts (~8 sweeps with the backoff curve below).
const RELAY_OUTBOX_MAX_ATTEMPTS = 8;

// ...and never hold anything longer than this, however it got here.
const RELAY_OUTBOX_MAX_AGE_DAYS = 7;

// Ceiling on the queue itself, so a long outage cannot grow it without bound.
const RELAY_OUTBOX_MAX_ROWS = 500;

// Outbound URLs refused for these reasons are worth retrying later, because
// the cause is time-dependent. Anything else is a policy refusal - a non-https
// scheme, a private address - and retrying it would never succeed while
// re-resolving a host the guard has already rejected.
const RELAY_OUTBOX_RETRYABLE_REFUSALS = ['host did not resolve'];

/**
 * Would this visibility survive a delay?
 */
function relay_outbox_is_durable($visibility)
{
    return in_array($visibility, RELAY_OUTBOX_DURABLE_VISIBILITIES, true);
}

/**
 * Queue one delivery.
 *
 * $payload is the JSON capsule WITHOUT handshake_token: the token is looked up
 * from `following` at send time, so a rotated token does not strand everything
 * that was queued before the rotation.
 */
function relay_outbox_enqueue(PDO $db, $target_url, array $payload, $visibility)
{
    $target_url = trim((string) $target_url);
    if ($target_url === '') {
        return false;
    }

    $stmt = $db->prepare(
        "INSERT INTO outbox (target_url, payload, visibility, next_attempt)
         VALUES (:url, :payload, :vis, CURRENT_TIMESTAMP)"
    );

    $ok = $stmt->execute([
        ':url'     => $target_url,
        ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES),
        ':vis'     => (string) $visibility,
    ]);

    // Keep the queue bounded: drop the oldest rows beyond the ceiling.
    $db->exec(
        "DELETE FROM outbox WHERE id NOT IN (
            SELECT id FROM outbox ORDER BY id DESC LIMIT " . RELAY_OUTBOX_MAX_ROWS . ")"
    );

    return $ok;
}

/**
 * How many deliveries are waiting.
 */
function relay_outbox_pending_count(PDO $db)
{
    try {
        return (int) $db->query("SELECT COUNT(*) FROM outbox")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Seconds to wait before the next attempt. Quadratic, capped at an hour, so a
 * peer that is down for a minute is retried quickly and one that is down for a
 * day is not hammered.
 */
function relay_outbox_retry_delay($attempts)
{
    $attempts = max(1, (int) $attempts);

    return min(3600, 15 * $attempts * $attempts);
}

/**
 * Exclusive lock so two console tabs (or a sweep racing a page load) cannot
 * deliver the same queued row twice. Returns a handle to release, or null if
 * another flush is already running.
 */
function relay_outbox_lock(PDO $db)
{
    $path = '';

    try {
        $row = $db->query("PRAGMA database_list")->fetch(PDO::FETCH_ASSOC);
        $path = (string) ($row['file'] ?? '');
    } catch (PDOException $e) {
        $path = '';
    }

    if ($path === '') {
        return null; // In-memory database: no shared file to lock on.
    }

    $handle = @fopen($path . '.outbox.lock', 'c');
    if ($handle === false) {
        return null;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        return null;
    }

    return $handle;
}

/**
 * Try to deliver everything that is due.
 *
 * Called from core/radar_sweep.php, which already pings every peer: the sweep
 * is the moment the node knows who is reachable, so it is the moment to spend
 * the queue.
 *
 * @return array{sent:int,deferred:int,dropped:int,skipped:int}
 */
function relay_outbox_flush(PDO $db, $limit = 20)
{
    $stats = ['sent' => 0, 'deferred' => 0, 'dropped' => 0, 'skipped' => 0];

    $lock = relay_outbox_lock($db);
    if ($lock === null) {
        $stats['skipped'] = 1;
        return $stats;
    }

    try {
        // Expire anything that has been waiting too long, regardless of state.
        $expired = $db->prepare(
            "DELETE FROM outbox WHERE created_at <= datetime('now', :age)"
        );
        $expired->execute([':age' => '-' . RELAY_OUTBOX_MAX_AGE_DAYS . ' days']);
        $stats['dropped'] += $expired->rowCount();

        $due = $db->prepare(
            "SELECT * FROM outbox
             WHERE next_attempt IS NULL OR next_attempt <= CURRENT_TIMESTAMP
             ORDER BY id ASC LIMIT :lim"
        );
        $due->bindValue(':lim', (int) $limit, PDO::PARAM_INT);
        $due->execute();
        $rows = $due->fetchAll(PDO::FETCH_ASSOC);

        $claim = $db->prepare(
            "UPDATE outbox SET attempts = attempts + 1,
                    last_attempt = CURRENT_TIMESTAMP,
                    next_attempt  = datetime('now', :delay)
             WHERE id = :id"
        );
        $drop = $db->prepare("DELETE FROM outbox WHERE id = :id");
        $token_lookup = $db->prepare("SELECT handshake_token FROM following WHERE planet_url = :url");

        foreach ($rows as $row) {
            $attempts = ((int) $row['attempts']) + 1;

            // Claim before attempting, so a crash mid-send still counts.
            $claim->execute([
                ':delay' => '+' . relay_outbox_retry_delay($attempts) . ' seconds',
                ':id'    => $row['id'],
            ]);

            // Judge the row itself before judging the network. A payload that is
            // not a JSON object can never be delivered, so it is dropped
            // regardless of whether the target happens to be reachable - which
            // also keeps this branch reachable without a live connection.
            $payload = json_decode($row['payload'], true);
            if (!is_array($payload)) {
                error_log('[RELAY][OUTBOX] dropped a queued delivery whose payload is not a JSON object: '
                    . $row['target_url']);
                $drop->execute([':id' => $row['id']]);
                $stats['dropped']++;
                continue;
            }

            $verdict = relay_url_is_safe($row['target_url']);
            if ($verdict !== true && !in_array($verdict, RELAY_OUTBOX_RETRYABLE_REFUSALS, true)) {
                error_log('[RELAY][OUTBOX] dropped a queued delivery, the target is refused outright: '
                    . $row['target_url'] . ' (' . $verdict . ')');
                $drop->execute([':id' => $row['id']]);
                $stats['dropped']++;
                continue;
            }

            $delivered = false;

            if ($verdict === true) {
                // Refresh the token: it may have been rotated since the queue.
                $token_lookup->execute([':url' => $row['target_url']]);
                $payload['handshake_token'] = (string) ($token_lookup->fetchColumn() ?: '');
                $token_lookup->closeCursor();

                $delivered = relay_outbox_deliver($row['target_url'], $payload);
            }

            if ($delivered) {
                $drop->execute([':id' => $row['id']]);
                $stats['sent']++;
                continue;
            }

            if ($attempts >= RELAY_OUTBOX_MAX_ATTEMPTS) {
                error_log('[RELAY][OUTBOX] giving up after ' . $attempts . ' attempts: ' . $row['target_url']);
                $drop->execute([':id' => $row['id']]);
                $stats['dropped']++;
            } else {
                $stats['deferred']++;
            }
        }
    } catch (PDOException $e) {
        error_log('[RELAY][OUTBOX] flush failed: ' . $e->getMessage());
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    return $stats;
}

/**
 * One-line queue state for the Radar Sweep's response string, so a queued
 * delivery is visible to the operator instead of living only in the log.
 */
function relay_outbox_summary(PDO $db, array $stats)
{
    $line = '| Outbox: ' . relay_outbox_pending_count($db) . ' waiting';

    if (!empty($stats['sent'])) {
        $line .= ', ' . (int) $stats['sent'] . ' delivered';
    }
    if (!empty($stats['dropped'])) {
        $line .= ', ' . (int) $stats['dropped'] . ' expired';
    }

    return $line;
}

/**
 * One delivery attempt. Returns true only on a 2xx response.
 */
function relay_outbox_deliver($target_url, array $payload)
{
    $url = rtrim($target_url, '/') . '/api_inbox.php';
    $ch  = relay_node_curl($url);

    if (!$ch) {
        return false;
    }

    $json = json_encode($payload);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json),
        'User-Agent: RelayStation/8.1',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}
