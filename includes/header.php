<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// BEVEILIGING: Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    // Als we niet inloggen zijn, stuur naar login.
    // We gokken het pad, of gebruiken een harde redirect als het bestand in root staat.
    header("Location: /zorgdossier/login.php");
    exit;
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// SLIMME PADEN TRUC
// We kijken hoe diep we zitten (tellen het aantal slashes in het pad)
// Zo weten we of we 'pages/...' moeten gebruiken of '../'
$script_name = $_SERVER['SCRIPT_NAME'];
$depth = substr_count($script_name, '/') - 2; // -2 omdat we localhost/zorgdossier niet meetellen
$base_path = str_repeat('../', max(0, $depth)); 
// $base_path is nu leeg "" op dashboard, of "../../" als je diep zit.

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zorgdossier Suriname</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Extra animatie voor menu items */
        .nav-link:hover { text-decoration: underline; text-underline-offset: 4px; }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal flex flex-col min-h-screen">

    <nav class="bg-teal-700 p-4 shadow-md text-white">
        <div class="container mx-auto flex flex-wrap items-center justify-between">
            
            <a href="<?php echo $base_path; ?>dashboard.php" class="flex items-center text-white no-underline hover:text-teal-200 transition">
                <span class="text-2xl pl-2 font-bold">🏥 Zorgdossier</span>
            </a>

            <div class="w-full block flex-grow lg:flex lg:items-center lg:w-auto hidden md:block pt-6 lg:pt-0">
                <ul class="list-reset lg:flex justify-end flex-1 items-center space-x-6 text-sm font-bold">
                    
                    <li>
                        <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>dashboard.php">Dashboard</a>
                    </li>

                    <?php if ($role === 'management'): ?>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/clients/index.php">Cliënten</a>
                        </li>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/users/index.php">HR & Personeel</a>
                        </li>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/planning/manage.php">Rooster & Routes</a>
                        </li>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/planning/manage_orders.php">Bestellingen</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'zuster'): ?>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/planning/view.php">🚑 Mijn Route</a>
                        </li>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/clients/index.php">Cliëntenlijst</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'familie'): ?>
                        <li>
                            <a class="nav-link text-teal-100 hover:text-white" href="<?php echo $base_path; ?>pages/clients/index.php">Mijn Familie</a>
                        </li>
                    <?php endif; ?>

                </ul>
            </div>

            <div class="ml-6 pl-6 border-l border-teal-500 flex items-center space-x-4">
                <div class="text-right leading-tight">
                    <span class="block text-xs text-teal-300">Ingelogd als</span>
                    <span class="font-bold"><?php echo htmlspecialchars($username); ?></span>
                </div>
                <a href="<?php echo $base_path; ?>logout.php" class="bg-teal-800 hover:bg-teal-900 text-white text-xs py-2 px-3 rounded shadow">
                    Uitloggen
                </a>
            </div>

        </div>
    </nav>
    
    <div class="container mx-auto mt-8 px-4 flex-grow">
