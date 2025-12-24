<?php
// pages/clients/save_observation.php
session_start();
require '../../config/db.php';

// Beveiliging: Familie mag niks invullen
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'familie') {
    die("Geen toegang.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id = $_POST['client_id'];
        $nurse_id = $_SESSION['user_id'];
        
        // Data ophalen uit formulier
        $gen_imp = $_POST['general_impression'];
        $sys = !empty($_POST['bp_systolic']) ? $_POST['bp_systolic'] : null;
        $dia = !empty($_POST['bp_diastolic']) ? $_POST['bp_diastolic'] : null;
        
        $walking = $_POST['walking'];
        $eating = $_POST['eating'];
        $drinking = $_POST['drinking'];
        $speech = $_POST['speech'];
        $sleeping = $_POST['sleeping'];
        
        $defecation = $_POST['defecation'];
        $urine = $_POST['urine'];
        $coughing = $_POST['coughing'];

        $sql = "INSERT INTO daily_observations 
                (client_id, nurse_id, general_impression, bp_systolic, bp_diastolic, 
                 walking, eating, drinking, speech, sleeping, defecation, urine, coughing) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $client_id, $nurse_id, $gen_imp, $sys, $dia, 
            $walking, $eating, $drinking, $speech, $sleeping, 
            $defecation, $urine, $coughing
        ]);

        // Terug naar tabblad metingen
        header("Location: detail.php?id=$client_id#metingen");
        exit;

    } catch (Exception $e) {
        die("Fout bij opslaan meting: " . $e->getMessage());
    }
}
?>
