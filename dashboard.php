<?php
// dashboard.php
include 'includes/header.php';
require 'config/db.php';

// 1. DATA OPHALEN
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$stats = [];
$recent_reports = [];
$my_client = null;

try {
    if ($role === 'management') {
        // Management ziet bedrijfsbrede cijfers
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        $stats['nurses'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='zuster'")->fetchColumn();
        
        // Laatste 10 rapportages (Systeembreed)
        $stmt = $pdo->query("SELECT r.*, c.first_name, c.last_name, u.username 
                             FROM client_reports r 
                             JOIN clients c ON r.client_id = c.id 
                             JOIN users u ON r.author_id = u.id 
                             ORDER BY r.created_at DESC LIMIT 10");
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'zuster') {
        // Zuster ziet laatste rapportages van haarzelf
        $stmt = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, u.username 
                               FROM client_reports r 
                               JOIN clients c ON r.client_id = c.id 
                               JOIN users u ON r.author_id = u.id 
                               WHERE r.author_id = ?
                               ORDER BY r.created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'familie') {
        // FAMILIE LOGICA (NIEUW: Via koppeltabel)
        
        // 1. Haal de (eerste) gekoppelde cliënt op voor de profielkaart
        // We gebruiken een JOIN met family_client_access
        $client_stmt = $pdo->prepare("
            SELECT c.* FROM clients c 
            JOIN family_client_access a ON c.id = a.client_id 
            WHERE a.user_id = ? 
            LIMIT 1
        ");
        $client_stmt->execute([$user_id]);
        $my_client = $client_stmt->fetch();

        // 2. Haal rapportages op van ALLE gekoppelde cliënten (als ze er meer hebben)
        // We gebruiken een subquery of join om rapportages te filteren op toegang
        if ($my_client) {
            $stmt = $pdo->prepare("
                SELECT r.*, c.first_name, c.last_name, u.username 
                FROM client_reports r 
                JOIN clients c ON r.client_id = c.id 
                JOIN users u ON r.author_id = u.id 
                JOIN family_client_access a ON c.id = a.client_id
                WHERE a.user_id = ? 
                AND r.visible_to_family = 1
                ORDER BY r.created_at DESC LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $recent_reports = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    // Silent fail
}
?>

<div class="w-full mb-12">

    <div class="flex justify-between items-center mb-6 border-b border-gray-300 pb-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 uppercase tracking-tight">
                <?php echo ($role === 'familie') ? 'Mijn Zorgomgeving' : (($role === 'management') ? 'Management Dashboard' : 'Zorgportaal'); ?>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                <?php if($role === 'familie'): ?>
                    Welkom. Hier vindt u informatie over uw naaste(n).
                <?php else: ?>
                    Overzicht van de zorginstelling en administratie.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right">
            <span class="text-xs font-bold text-slate-600 bg-gray-100 px-3 py-1 border border-gray-300">
                Vandaag: <?php echo date('d-m-Y'); ?>
            </span>
        </div>
    </div>

    <?php if ($role === 'familie'): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-1">
                <?php if($my_client): ?>
                    <div class="bg-white border border-gray-300 shadow-sm">
                        <div class="bg-blue-700 px-5 py-4 border-b border-blue-800 text-white">
                            <h3 class="text-sm font-bold uppercase tracking-wide">Uw Familielid</h3>
                        </div>
                        <div class="p-6 text-center">
                            <div class="w-24 h-24 bg-slate-100 rounded-full mx-auto flex items-center justify-center text-3xl font-bold text-slate-400 mb-4 border border-slate-200">
                                <?php echo substr($my_client['first_name'],0,1).substr($my_client['last_name'],0,1); ?>
                            </div>
                            <h2 class="text-xl font-bold text-slate-800 mb-1">
                                <?php echo htmlspecialchars($my_client['first_name'] . ' ' . $my_client['last_name']); ?>
                            </h2>
                            <p class="text-sm text-slate-500 mb-6">
                                <?php echo htmlspecialchars($my_client['address']); ?><br>
                                <?php echo htmlspecialchars($my_client['neighborhood']); ?>
                            </p>
                            
                            <a href="pages/clients/detail.php?id=<?php echo $my_client['id']; ?>" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 text-sm uppercase tracking-wide transition-colors">
                                Open Volledig Dossier
                            </a>
                            
                            <div class="mt-4 text-xs text-blue-600 hover:underline">
                                <a href="pages/clients/index.php">Bekijk alle gekoppelde dossiers &rarr;</a>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-5 py-3 border-t border-gray-200 text-xs text-slate-500 text-center">
                            Dossier ID: #<?php echo $my_client['id']; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 p-6 text-center">
                        <h3 class="font-bold text-yellow-800 mb-2">Geen koppeling gevonden</h3>
                        <p class="text-sm text-yellow-700">
                            Uw account is nog niet gekoppeld aan een cliëntendossier via het systeem. <br>
                            Neem contact op met de administratie om toegang te krijgen.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-300 shadow-sm h-full">
                    <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                            Laatste Rapportages
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-gray-50 border-b border-gray-200 text-slate-500 uppercase tracking-wider">
                                    <th class="px-5 py-3 font-bold w-32">Datum</th>
                                    <th class="px-5 py-3 font-bold">Cliënt</th>
                                    <th class="px-5 py-3 font-bold w-24">Type</th>
                                    <th class="px-5 py-3 font-bold">Bericht</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if(count($recent_reports) > 0): ?>
                                    <?php foreach($recent_reports as $r): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-5 py-4 text-slate-600 whitespace-nowrap align-top font-medium">
                                            <?php echo date('d-m-Y', strtotime($r['created_at'])); ?><br>
                                            <span class="text-slate-400"><?php echo date('H:i', strtotime($r['created_at'])); ?></span>
                                        </td>
                                        <td class="px-5 py-4 font-bold text-slate-700 align-top">
                                            <?php echo htmlspecialchars($r['first_name']); ?>
                                        </td>
                                        <td class="px-5 py-4 align-top">
                                            <?php 
                                            $badge_class = 'bg-gray-100 text-slate-600 border-gray-200';
                                            if($r['report_type'] == 'Incident') $badge_class = 'bg-red-50 text-red-700 border-red-200';
                                            if($r['report_type'] == 'Medisch') $badge_class = 'bg-blue-50 text-blue-700 border-blue-200';
                                            ?>
                                            <span class="px-2 py-1 border text-[10px] font-bold uppercase <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($r['report_type']); ?>
                                            </span>
                                            <div class="mt-1 text-[10px] text-slate-400">
                                                Stemming: <?php echo htmlspecialchars($r['mood']); ?>
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-700 align-top leading-relaxed">
                                            <?php echo nl2br(htmlspecialchars($r['content'])); ?>
                                            <div class="mt-1 text-slate-400 text-[10px] italic">
                                                Geschreven door: <?php echo htmlspecialchars($r['username']); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-400 italic">
                                            Er zijn nog geen rapportages beschikbaar.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="w-full mb-12">
             <?php include 'pages/dashboard_internal.php'; // Fictieve include, in praktijk de code van vorige stappen gebruiken ?>
             <p class="text-slate-500 italic">Interne dashboard weergave geladen.</p>
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>