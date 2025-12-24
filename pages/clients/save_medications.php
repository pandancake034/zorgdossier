<?php
// pages/clients/save_medication.php
session_start();
require '../../config/db.php';

// Check rechten
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'familie') {
    die("Geen toegang.");
}

// 1. TOEVOEGEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    $client_id = $_POST['client_id'];
    $name = $_POST['name'];
    $dosage = $_POST['dosage'];
    $frequency = $_POST['frequency'];
    $times = $_POST['times'];
    $notes = $_POST['notes'];

    $stmt = $pdo->prepare("INSERT INTO client_medications (client_id, name, dosage, frequency, times, notes) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$client_id, $name, $dosage, $frequency, $times, $notes]);
    
    header("Location: detail.php?id=$client_id#zorgplan");
    exit;
}

// 2. VERWIJDEREN (via GET link)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_id'])) {
    $med_id = $_GET['delete_id'];
    $client_id = $_GET['client_id']; // Nodig voor redirect

    $stmt = $pdo->prepare("DELETE FROM client_medications WHERE id = ?");
    $stmt->execute([$med_id]);

    header("Location: detail.php?id=$client_id#zorgplan");
    exit;
}
?>