<?php
require_once 'ssl_shield.php';
// RELAY STATION: DEEP SPACE RADAR SWEEP
// Pings all nodes in the Star Chart. Dead/Error nodes will be purged.

session_start();
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) {
    die("UNAUTHORIZED");
}

// 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
require_once 'db_connect.php';

try {
    $stmt = $db->query("SELECT id, planet_url, alias FROM following");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($nodes) === 0) {
        die("[ RADAR EMPTY ] No coordinates to scan.");
    }

    // Machine Gun (Multi-cURL) for simultaneous pinging
    $mh = curl_multi_init();
    $curl_array = [];
    foreach ($nodes as $i => $node) {
        $ping_url = rtrim($node['planet_url'], '/') . '/api_ping.php';
        $curl_array[$i] = curl_init($ping_url);
        curl_setopt($curl_array[$i], CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl_array[$i], CURLOPT_TIMEOUT, 5); // 5 seconds max
        curl_setopt($curl_array[$i], CURLOPT_SSL_VERIFYPEER, false);
        curl_multi_add_handle($mh, $curl_array[$i]);
    }

    // Fire the radar
    $running = null;
    do { curl_multi_exec($mh, $running); } while ($running);

    $dead_nodes = 0;
    $active_nodes = 0;

    foreach ($nodes as $i => $node) {
        $http_code = curl_getinfo($curl_array[$i], CURLINFO_HTTP_CODE);
        $response = curl_multi_getcontent($curl_array[$i]);
        curl_multi_remove_handle($mh, $curl_array[$i]);
        
        $is_alive = false;
        if ($http_code == 200 && $response) {
            $data = json_decode($response, true);
            if (isset($data['software']) && $data['software'] === 'relay_station') {
                $is_alive = true;
            }
        }

        if ($is_alive) {
            $active_nodes++;
        } else {
            // Node is dead, purge from radar database
            $del_stmt = $db->prepare("DELETE FROM following WHERE id = :id");
            $del_stmt->execute([':id' => $node['id']]);
            $dead_nodes++;
        }
    }
    curl_multi_close($mh);

    echo "[ SWEEP COMPLETE ] Active Nodes: $active_nodes | Dead Nodes Purged: $dead_nodes";

} catch (PDOException $e) {
    die("[ SYSTEM ERROR ] Radar malfunction.");
}