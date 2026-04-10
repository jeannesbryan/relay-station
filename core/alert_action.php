<?php
require_once 'ssl_shield.php';
// RELAY STATION: ALERT HANDLER

session_start();
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) { die("UNAUTHORIZED"); }

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // 🚀 [ INJECT CORE MEMORY ENGINE (WAL MODE) ]
    require_once 'db_connect.php';
    
    try {
        $stmt = $db->prepare("DELETE FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Check if this is an AJAX request (Follow Back)
        if (isset($_GET['ajax'])) { echo "OK"; exit; }
        
        // Check if this is a redirect from 'Read DM' button
        if (isset($_GET['redirect']) && $_GET['redirect'] == 'direct') {
            header("Location: ../direct.php"); exit;
        }

    } catch (PDOException $e) {}
}
header("Location: ../console.php");