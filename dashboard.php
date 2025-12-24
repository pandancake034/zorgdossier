<?php
// dashboard.php
include 'includes/header.php';
require 'config/db.php';

// 1. DATA OPHALEN OP BASIS VAN ROL
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$stats = [];
$recent_reports = [];

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
        // Zuster ziet alleen eigen data (geen algemene stats voor nu)
        
        // Laatste rapportages van DEZE zuster
        $stmt = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, u.username 
                               FROM client_reports r 
                               JOIN clients c ON r.client_id = c.id 
                               JOIN users u ON r.author_id = u.id 
                               WHERE r.author_id = ?
                               ORDER BY r.created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $recent_reports = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Silent fail
}
?>

<div class="w-full mb-12">

    <div class="flex justify-between items-center mb-6 border-b border-gray-300 pb-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800 uppercase tracking-tight">
                <?php echo ($role === 'management') ? 'Management Dashboard' : 'Zorgportaal'; ?>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                <?php if($role === 'management'): ?>
                    Overzicht van de zorginstelling en administratie.
                <?php else: ?>
                    Welkom, bekijk uw route en rapportages.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right">
            <span class="text-xs font-bold text-slate-600 bg-gray-100 px-3 py-1 border border-gray-300">
                Vandaag: <?php echo date('d-m-Y'); ?>
            </span>
        </div>
    </div>

    <?php if ($role === 'management'): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Actieve Cliënten</div>
                <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['clients']; ?></div>
            </div>
            <div class="text-slate-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
        </div>

        <div class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm relative overflow-hidden">
            <?php if($stats['orders'] > 0): ?>
                <div class="absolute top-0 right-0 w-4 h-4 bg-orange-500"></div>
            <?php endif; ?>
            <div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Openstaande Orders</div>
                <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['orders']; ?></div>
            </div>
            <div class="text-slate-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
        </div>

        <div class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Zorgpersoneel</div>
                <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['nurses']; ?></div>
            </div>
            <div class="text-slate-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="1.5" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0h4m-4 0a1 1 0 00-1 1v3a1 1 0 001 1h2a1 1 0 001-1V7a1 1 0 00-1-1h-2z"></path></svg>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Snelmenu</h3>
                </div>
                
                <div class="flex flex-col divide-y divide-gray-100">
                    <?php if ($role === 'management'): ?>
                        <a href="pages/clients/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Cliëntendossiers</div>
                                <div class="text-xs text-slate-500">Beheer intakes en gegevens</div>
                            </div>
                        </a>
                        <a href="pages/users/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">HR & Personeel</div>
                                <div class="text-xs text-slate-500">Accounts en contracten</div>
                            </div>
                        </a>
                        <a href="pages/planning/roster.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Rooster & Routes</div>
                                <div class="text-xs text-slate-500">Planning beheren</div>
                            </div>
                        </a>
                        <a href="pages/planning/manage_orders.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Orders & Inkoop</div>
                                <div class="text-xs text-slate-500">Materiaal goedkeuringen</div>
                            </div>
                        </a>

                    <?php elseif ($role === 'zuster'): ?>
                        <a href="pages/planning/view.php" class="p-4 hover:bg-blue-50 flex items-center group transition-colors border-l-4 border-blue-600 bg-blue-50/50">
                            <span class="w-5 h-5 mr-4 text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-blue-800">Start mijn route</div>
                                <div class="text-xs text-blue-600">Bekijk cliënten en taken</div>
                            </div>
                        </a>
                        <a href="pages/clients/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Zoek Cliënt</div>
                                <div class="text-xs text-slate-500">Volledige cliëntenlijst</div>
                            </div>
                        </a>
                        <a href="pages/profile/my_hr.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            </span>
                            <div>
                                <div class="text-sm font-bold text-slate-800">Mijn HR & Rooster</div>
                                <div class="text-xs text-slate-500">Urenregistratie en loonstroken</div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                        <?php echo ($role === 'management') ? 'Recente Mutaties / Rapportages' : 'Mijn Recente Rapportages'; ?>
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3 font-bold w-28">Datum</th>
                                <th class="px-4 py-3 font-bold">Cliënt</th>
                                <th class="px-4 py-3 font-bold">Type</th>
                                <?php if($role === 'management'): ?>
                                    <th class="px-4 py-3 font-bold">Auteur</th>
                                <?php endif; ?>
                                <th class="px-4 py-3 font-bold">Omschrijving</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($recent_reports) > 0): ?>
                                <?php foreach($recent_reports as $r): ?>
                                <tr class="hover:bg-yellow-50/50 transition-colors">
                                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap align-top font-medium">
                                        <?php echo date('d-m H:i', strtotime($r['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-700 align-top">
                                        <a href="pages/clients/detail.php?id=<?php echo $r['client_id']; ?>" class="text-blue-700 hover:underline decoration-blue-300 underline-offset-2">
                                            <?php echo htmlspecialchars($r['last_name']); ?>, <?php echo substr($r['first_name'],0,1); ?>.
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <?php 
                                        $badge_class = 'bg-gray-100 text-gray-700 border-gray-200';
                                        if($r['report_type'] == 'Incident') $badge_class = 'bg-red-50 text-red-700 border-red-200';
                                        if($r['report_type'] == 'Medisch') $badge_class = 'bg-blue-50 text-blue-700 border-blue-200';
                                        ?>
                                        <span class="px-2 py-0.5 border text-[10px] font-bold uppercase <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($r['report_type']); ?>
                                        </span>
                                    </td>
                                    <?php if($role === 'management'): ?>
                                        <td class="px-4 py-3 text-slate-500 align-top">
                                            <?php echo htmlspecialchars($r['username']); ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="px-4 py-3 text-slate-600 truncate max-w-xs align-top">
                                        <?php echo htmlspecialchars($r['content']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-slate-400 italic">Geen recente activiteiten gevonden.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>