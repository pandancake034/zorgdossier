<?php
// pages/clients/save_report.php
session_start();
require '../../config/db.php';

// 1. BEVEILIGING: Familie mag niks opslaan
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'familie') {
    die("Geen toegang.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id = $_POST['client_id'];
        $author_id = $_SESSION['user_id'];
        $content = trim($_POST['content']);
        $mood = $_POST['mood'];
        $report_type = $_POST['report_type'];
        
        // Checkbox: als aangevinkt = 1, anders = 0
        $visible = isset($_POST['visible_to_family']) ? 1 : 0;

        if (empty($content)) {
            throw new Exception("Bericht mag niet leeg zijn.");
        }

        $sql = "INSERT INTO client_reports (client_id, author_id, content, mood, report_type, visible_to_family) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id, $author_id, $content, $mood, $report_type, $visible]);

        // Terug naar het dossier (en open direct tabblad rapportages via de hashtag)
        header("Location: detail.php?id=$client_id#rapportages");
        exit;

    } catch (Exception $e) {
        die("Fout bij opslaan: " . $e->getMessage());
    }
}
?>
