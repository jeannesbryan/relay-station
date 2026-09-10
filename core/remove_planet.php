<?php
require_once __DIR__ . '/security.php';
require_once 'ssl_shield.php';
// RELAY STATION: DISCONNECT PROTOCOL
// Manually remove a planet's coordinates from the radar (Unfollow)

relay_session_start();

// Only the Commander may reach this endpoint.
relay_require_auth(false);

// Destructive (deletes a federation link) and was reachable by GET, so a
// single <a href> on any page could unfollow a node for a logged-in Commander.
relay_require_post_and_csrf(false);

if (isset($_POST['id'])) {
    $id = (int) $_POST['id'];
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        $stmt = $db->prepare("DELETE FROM following WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header("Location: ../console.php?status=node_removed");
        exit;

    } catch (PDOException $e) {
        error_log('[RELAY] remove_planet failed: ' . $e->getMessage());
        die("<h3 style='color:red;'>[ SYSTEM ERROR ]</h3>");
    }
} else {
    header("Location: ../console.php");
}