<?php
// pages/clients/save_order.php
session_start();
require '../../config/db.php';

// Familie mag niet bestellen
if (!isset($_SESSION['role']) || $_SESSION['role'] === 'familie') {
    die("Geen toegang.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client_id = $_POST['client_id'];
        $nurse_id = $_SESSION['user_id'];
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];

        $pdo->beginTransaction();

        // 1. Maak de Hoofd Order aan
        $stmt = $pdo->prepare("INSERT INTO orders (client_id, nurse_id, status) VALUES (?, ?, 'in_afwachting')");
        $stmt->execute([$client_id, $nurse_id]);
        $order_id = $pdo->lastInsertId();

        // 2. Koppel het product eraan (Regel)
        $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmtItem->execute([$order_id, $product_id, $quantity]);

        $pdo->commit();

        header("Location: detail.php?id=$client_id#bestellingen");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Fout bij bestellen: " . $e->getMessage());
    }
}
?>
