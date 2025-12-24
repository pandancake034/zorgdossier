<?php
// pages/planning/toggle_task.php
session_start();
require '../../config/db.php';

if (!isset($_SESSION['user_id'])) exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'];
    $nurse_id = $_SESSION['user_id'];
    $status = $_POST['status']; // 'Uitgevoerd' of 'Niet gelukt'

    // We voegen een regel toe aan het logboek
    $stmt = $pdo->prepare("INSERT INTO task_execution_log (client_care_task_id, nurse_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$task_id, $nurse_id, $status]);

    // Terug naar de planning
    header("Location: view.php?success=1");
    exit;
}
?>
