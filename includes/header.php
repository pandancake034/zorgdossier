<?php
// includes/header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// BEVEILIGING
if (!isset($_SESSION['user_id'])) {
    header("Location: /zorgdossier/login.php");
    exit;
}

$role = $_SESSION['role'];
$username = $_SESSION['username'];

// SLIMME PADEN
$script_name = $_SERVER['SCRIPT_NAME'];
$depth = substr_count($script_name, '/') - 2; 
$base_path = str_repeat('../', max(0, $depth)); 
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zorgdossier ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Enterprise Font Stack */
        body { font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        
        /* Navigatie Link Stijl */
        .nav-item {
            padding: 0.75rem 1rem;
            color: #cbd5e1; /* Slate-300 */
            text-decoration: none;
            font-size: 0.875rem; /* 14px */
            border-right: 1px solid #334155; /* Slate-700 separator */
            transition: background-color 0.2s, color 0.2s;
        }
        .nav-item:hover {
            background-color: #1e293b; /* Slate-800 */
            color: #ffffff;
        }
        .nav-item.active {
            background-color: #0f172a; /* Slate-900 */
            color: #ffffff;
            border-bottom: 2px solid #3b82f6; /* Blue-500 indicator */
        }
    </style>
</head>
<body class="bg-gray-100 text-slate-800 text-sm flex flex-col min-h-screen">

    <nav class="bg-slate-700 shadow-sm border-b border-slate-800">
        <div class="w-full flex flex-wrap items-center justify-between px-4">
            
            <a href="<?php echo $base_path; ?>dashboard.php" class="flex items-center no-underline mr-6 py-3">
                <span class="text-lg font-semibold text-white tracking-tight uppercase">Zorgdossier<span class="text-blue-400">ERP</span></span>
            </a>

            <div class="flex-grow flex items-center overflow-x-auto">
                <a class="nav-item" href="<?php echo $base_path; ?>dashboard.php">Dashboard</a>

                <?php if ($role === 'management'): ?>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/clients/index.php">Cliënten</a>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/users/index.php">HR & Personeel</a>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/planning/roster.php">Rooster & Routes</a>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/planning/manage_orders.php">Inkoop & Orders</a>
                <?php endif; ?>

                <?php if ($role === 'zuster'): ?>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/planning/view.php">Mijn route</a>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/clients/index.php">Cliëntenlijst</a>
                <?php endif; ?>

                <?php if ($role === 'familie'): ?>
                    <a class="nav-item" href="<?php echo $base_path; ?>pages/clients/index.php">Mijn familie</a>
                <?php endif; ?>
            </div>

            <div class="flex items-center pl-4 border-l border-slate-600 ml-auto">
                <div class="text-right mr-4 leading-tight">
                    <div class="text-xs text-slate-300">Ingelogd als</div>
                    <div class="text-xs font-bold text-white"><?php echo htmlspecialchars($username); ?></div>
                </div>
                <a href="<?php echo $base_path; ?>logout.php" class="bg-slate-600 hover:bg-red-700 text-white text-xs py-1 px-3 border border-slate-500 hover:border-red-800 transition uppercase font-semibold tracking-wider">
                    Uitloggen
                </a>
            </div>

        </div>
    </nav>
    
    <div class="w-full max-w-7xl mx-auto mt-6 px-4 flex-grow">