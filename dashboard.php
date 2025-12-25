<?php
// dashboard.php
// Zet foutmeldingen aan voor debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
require 'config/db.php';

// 1. DATA OPHALEN
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Initialiseer variabelen om 'undefined variable' fouten te voorkomen
$stats = [
    'clients' => 0,
    'orders' => 0,
    'nurses' => 0
];
$recent_reports = [];
$my_client = null;

try {
    if ($role === 'management') {
        // --- MANAGEMENT LOGICA ---
        // Tellers
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
        // --- ZUSTER LOGICA ---
        // Laatste rapportages van DEZE zuster
        $stmt = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, u.username 
                               FROM client_reports r 
                               JOIN clients c ON r.client_id = c.id 
                               JOIN users u ON r.author_id = u.id 
                               WHERE r.author_id = ?
                               ORDER BY r.created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'familie') {
        // --- FAMILIE LOGICA ---
        // 1. Haal de gekoppelde cliënt op via de nieuwe koppeltabel
        $client_stmt = $pdo->prepare("
            SELECT c.* FROM clients c 
            JOIN family_client_access a ON c.id = a.client_id 
            WHERE a.user_id = ? 
            LIMIT 1
        ");
        $client_stmt->execute([$user_id]);
        $my_client = $client_stmt->fetch();

        // 2. Haal rapportages op van gekoppelde cliënten
        // We filteren op visible_to_family = 1
        $report_stmt = $pdo->prepare("
            SELECT r.*, c.first_name, c.last_name, u.username 
            FROM client_reports r 
            JOIN clients c ON r.client_id = c.id 
            JOIN users u ON r.author_id = u.id 
            JOIN family_client_access a ON c.id = a.client_id
            WHERE a.user_id = ? 
            AND r.visible_to_family = 1
            ORDER BY r.created_at DESC LIMIT 5
        ");
        $report_stmt->execute([$user_id]);
        $recent_reports = $report_stmt->fetchAll();
    }

} catch (PDOException $e) {
    // Toon de foutmelding als de database faalt
    echo "<div class='bg-red-100 text-red-700 p-4'>Database Fout: " . htmlspecialchars($e->getMessage()) . "</div>";
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
                <?php if($stats['orders'] > 0): ?><div class="absolute top-0 right-0 w-4 h-4 bg-orange-500"></div><?php endif; ?>
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

    <?php elseif ($role === 'familie'): ?>
        
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
                    </div>
                <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 p-6 text-center">
                        <h3 class="font-bold text-yellow-800 mb-2">Geen koppeling gevonden</h3>
                        <p class="text-sm text-yellow-700">
                            Uw account is nog niet gekoppeld aan een cliëntendossier. <br>
                            Neem contact op met de administratie.
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-2">
                </div>
        </div>

    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">

        <?php if ($role !== 'familie'): ?>
        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Snelmenu</h3>
                </div>
                <div class="flex flex-col divide-y divide-gray-100">
                    <?php if ($role === 'management'): ?>
                        <a href="pages/clients/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">📂</span>
                            <div><div class="text-sm font-bold text-slate-800">Cliëntendossiers</div><div class="text-xs text-slate-500">Beheer intakes en gegevens</div></div>
                        </a>
                        <a href="pages/users/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">👥</span>
                            <div><div class="text-sm font-bold text-slate-800">HR & Personeel</div><div class="text-xs text-slate-500">Accounts en contracten</div></div>
                        </a>
                        <a href="pages/planning/roster.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">📅</span>
                            <div><div class="text-sm font-bold text-slate-800">Rooster & Routes</div><div class="text-xs text-slate-500">Planning beheren</div></div>
                        </a>
                        <a href="pages/planning/manage_orders.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">📦</span>
                            <div><div class="text-sm font-bold text-slate-800">Orders & Inkoop</div><div class="text-xs text-slate-500">Materiaal goedkeuringen</div></div>
                        </a>
                    <?php elseif ($role === 'zuster'): ?>
                        <a href="pages/planning/view.php" class="p-4 hover:bg-blue-50 flex items-center group transition-colors border-l-4 border-blue-600 bg-blue-50/50">
                            <span class="w-5 h-5 mr-4 text-blue-600">🚑</span>
                            <div><div class="text-sm font-bold text-blue-800">Start mijn route</div><div class="text-xs text-blue-600">Bekijk dagplanning</div></div>
                        </a>
                        <a href="pages/clients/index.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">📂</span>
                            <div><div class="text-sm font-bold text-slate-800">Cliëntenlijst</div><div class="text-xs text-slate-500">Zoek in database</div></div>
                        </a>
                        <a href="pages/profile/my_hr.php" class="p-4 hover:bg-slate-50 flex items-center group transition-colors">
                            <span class="w-5 h-5 mr-4 text-slate-400 group-hover:text-blue-600">💼</span>
                            <div><div class="text-sm font-bold text-slate-800">Mijn HR</div><div class="text-xs text-slate-500">Uren en loonstroken</div></div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?php echo ($role === 'familie') ? 'lg:col-span-2 lg:col-start-2 -mt-8' : 'lg:col-span-2'; ?>">
            <div class="bg-white border border-gray-300 shadow-sm h-full">
                <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                        <?php echo ($role === 'management') ? 'Recente Mutaties / Rapportages' : 'Recente Rapportages'; ?>
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-slate-500 uppercase tracking-wider">
                                <th class="px-4 py-3 font-bold w-28">Datum</th>
                                <th class="px-4 py-3 font-bold">Cliënt</th>
                                <th class="px-4 py-3 font-bold">Type</th>
                                <th class="px-4 py-3 font-bold">Omschrijving</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($recent_reports) > 0): ?>
                                <?php foreach($recent_reports as $r): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap align-top font-medium">
                                        <?php echo date('d-m H:i', strtotime($r['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-700 align-top">
                                        <a href="pages/clients/detail.php?id=<?php echo $r['client_id']; ?>" class="text-blue-700 hover:underline">
                                            <?php echo htmlspecialchars($r['last_name']); ?>, <?php echo substr($r['first_name'],0,1); ?>.
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <?php 
                                        $badge_class = 'bg-gray-100 text-slate-600 border-gray-200';
                                        if($r['report_type'] == 'Incident') $badge_class = 'bg-red-50 text-red-700 border-red-200';
                                        if($r['report_type'] == 'Medisch') $badge_class = 'bg-blue-50 text-blue-700 border-blue-200';
                                        ?>
                                        <span class="px-2 py-0.5 border text-[10px] font-bold uppercase <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($r['report_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 truncate max-w-xs align-top">
                                        <?php echo htmlspecialchars($r['content']); ?>
                                        <?php if($role === 'management' || $role === 'familie'): ?>
                                            <div class="text-[10px] text-slate-400 mt-1 italic">Door: <?php echo htmlspecialchars($r['username']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-400 italic">Geen recente activiteiten gevonden.</td>
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