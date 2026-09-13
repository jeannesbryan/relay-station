<?php
require_once __DIR__ . '/security.php';
require_once 'ssl_shield.php';
// RELAY STATION: DEEP SPACE RADAR SWEEP
// Pings every node in the Star Chart. Nodes that fail repeatedly are purged.

relay_session_start();

// Only the Commander may reach this endpoint.
relay_require_auth(false);
// A sweep deletes nodes it judges dead, so it is a destructive action and
// takes the same POST + CSRF treatment as the rest.
relay_require_post_and_csrf(false);

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'db_connect.php';

// ==========================================================
// ⏳ [ V8.0.3 ] THE GRACE PERIOD
// ==========================================================
// A node used to be deleted the first time a single 5-second ping failed.
// That is a hostile definition of dead: a node rebooting, a laptop lid
// closing, a host asleep, or a brief upstream outage all cost the operator
// the relationship permanently, and the Star Chart quietly forgot a peer
// that was never gone.
//
// A node is now given this many consecutive sweeps to answer before it is
// purged. A single successful ping resets the counter, so only sustained
// silence removes a peer.
const RELAY_SWEEP_GRACE_LIMIT = 3;

try {
    $stmt = $db->query("SELECT id, planet_url, alias, failure_count FROM following");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($nodes) === 0) {
        die("[ RADAR EMPTY ] No coordinates to scan.");
    }

    // Machine Gun (Multi-CURL) for simultaneous pinging
    $mh = curl_multi_init();
    $curl_array = [];

    foreach ($nodes as $i => $node) {
        $ping_url = rtrim($node['planet_url'], '/') . '/api_ping.php';
        $ch = relay_node_curl($ping_url);

        // [ V8.0.3 ] The outbound guard returns null when it refuses a URL -
        // and it refuses any host that does not resolve, which is exactly what
        // an offline node looks like. That null used to be fed straight into
        // curl_getinfo() below, killing the whole sweep with a TypeError and
        // leaving every node in place. A refused node is now simply counted as
        // silent, so the sweep always runs to completion.
        if (!$ch) {
            $curl_array[$i] = null;
            continue;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds max
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_multi_add_handle($mh, $ch);
        $curl_array[$i] = $ch;
    }

    // Fire the radar
    $running = null;
    do { curl_multi_exec($mh, $running); } while ($running);

    $active_nodes = 0;
    $silent_nodes = 0;
    $purged_nodes = 0;

    $mark_alive = $db->prepare("UPDATE following SET last_seen = CURRENT_TIMESTAMP, failure_count = 0 WHERE id = :id");
    $mark_fail  = $db->prepare("UPDATE following SET failure_count = :n WHERE id = :id");
    $del_stmt   = $db->prepare("DELETE FROM following WHERE id = :id");

    foreach ($nodes as $i => $node) {
        $ch = $curl_array[$i];
        $is_alive = false;

        if ($ch) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $response = curl_multi_getcontent($ch);
            curl_multi_remove_handle($mh, $ch);

            if ($http_code == 200 && $response) {
                $data = json_decode($response, true);
                if (isset($data['software']) && $data['software'] === 'relay_station') {
                    $is_alive = true;
                }
            }
        }

        if ($is_alive) {
            $mark_alive->execute([':id' => $node['id']]);
            $active_nodes++;
            continue;
        }

        // Silent this round. Charge one failure and only purge once the node
        // has missed RELAY_SWEEP_GRACE_LIMIT sweeps in a row.
        $failures = (int) $node['failure_count'] + 1;

        if ($failures >= RELAY_SWEEP_GRACE_LIMIT) {
            $del_stmt->execute([':id' => $node['id']]);
            $purged_nodes++;
        } else {
            $mark_fail->execute([':n' => $failures, ':id' => $node['id']]);
            $silent_nodes++;
        }
    }
    curl_multi_close($mh);

    echo "[ SWEEP COMPLETE ] Active: $active_nodes | Silent (kept, grace " . RELAY_SWEEP_GRACE_LIMIT . "): $silent_nodes | Purged: $purged_nodes";

} catch (PDOException $e) {
    error_log('[RELAY] radar sweep failed: ' . $e->getMessage());
    die("[ SYSTEM ERROR ] Radar malfunction.");
}
