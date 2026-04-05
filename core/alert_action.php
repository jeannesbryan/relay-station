<?php
// RELAY STATION: ALERT HANDLER (V3.0.6)

session_start();
if (!isset($_SESSION['relay_auth']) || $_SESSION['relay_auth'] !== true) { die("UNAUTHORIZED"); }

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db_file = '../data/relay_core.sqlite';
    try {
        $db = new PDO("sqlite:" . $db_file);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $db->prepare("DELETE FROM alerts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        // Cek jika ini via AJAX (Follow Back)
        if (isset($_GET['ajax'])) { echo "OK"; exit; }
        
        // Cek jika ini dari tombol Baca DM
        if (isset($_GET['redirect']) && $_GET['redirect'] == 'direct') {
            header("Location: ../direct.php"); exit;
        }

    } catch (PDOException $e) {}
}
header("Location: ../console.php");