<?php
// pages/clients/save_task.php
session_start();
require '../../config/db.php';

// Beveiliging
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'familie') {
    die("Geen toegang.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id = $_POST['client_id'];
        
        // Data ophalen
        $title = $_POST['title'];
        $description = $_POST['description'];
        $frequency = $_POST['frequency']; // Dagelijks, Wekelijks...
        $time_of_day = $_POST['time_of_day']; // Ochtend, Middag...
        
        // De dagen zijn een array (checkboxes), die maken we plat naar een string "Ma,Wo,Vr"
        $specific_days = isset($_POST['days']) ? implode(',', $_POST['days']) : null;

        // Als "Dagelijks" is gekozen, boeit de specifieke dag niet, maar voor de zekerheid slaan we het netjes op.
        
        $sql = "INSERT INTO client_care_tasks 
                (client_id, title, description, frequency, specific_days, time_of_day) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id, $title, $description, $frequency, $specific_days, $time_of_day]);

        // Terug naar tabblad zorgplan
        header("Location: detail.php?id=$client_id#zorgplan");
        exit;

    } catch (Exception $e) {
        die("Fout bij opslaan taak: " . $e->getMessage());
    }
}
?>
