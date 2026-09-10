<?php
require_once __DIR__ . '/security.php';
require_once 'ssl_shield.php';
// RELAY STATION: DISCONNECT PROTOCOL
// Manually remove a planet's coordinates from the radar (Unfollow)

relay_relay_session_start();

// Only the Commander may reach this endpoint.
relay_require_auth(false);

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        $stmt = $db->prepare("DELETE FROM following WHERE id = :id");
        $stmt->execute([':id' => $id]);

        header("Location: ../console.php?status=node_removed");
        exit;

    } catch (PDOException $e) {
        die("<h3 style='color:red;'>[ SYSTEM ERROR ] " . $e->getMessage() . "</h3>");
    }
} else {
    header("Location: ../console.php");
}