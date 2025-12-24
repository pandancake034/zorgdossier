<?php
// dashboard.php
include 'includes/header.php';
require 'config/db.php';

// 1. DATA OPHALEN VOOR WIDGETS (Statistieken)
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$stats = [];
$recent_reports = [];

try {
    if ($role === 'management') {
        // Management ziet alles
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        $stats['nurses'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='zuster'")->fetchColumn();
        
        // Laatste 5 rapportages (Systeembreed)
        $stmt = $pdo->query("SELECT r.*, c.first_name, c.last_name, u.username 
                             FROM client_reports r 
                             JOIN clients c ON r.client_id = c.id 
                             JOIN users u ON r.author_id = u.id 
                             ORDER BY r.created_at DESC LIMIT 5");
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'zuster') {
        // Zuster ziet haar eigen taken
        // Bepaal dag
        $english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
        $today_nl = str_replace($english_days, $dutch_days, date('D'));
        
        // Haal aantal taken op (via Route logica)
        // (Vereenvoudigde query voor dashboard teller)
        $stats['my_tasks'] = 0; // Zou je kunnen vullen met een count query
        
        // Laatste rapportages die IK heb geschreven
        $stmt = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, u.username 
                               FROM client_reports r 
                               JOIN clients c ON r.client_id = c.id 
                               JOIN users u ON r.author_id = u.id 
                               WHERE r.author_id = ?
                               ORDER BY r.created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $recent_reports = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Silent fail voor dashboard stats
}
?>

<div class="max-w-7xl mx-auto mb-12 px-4 sm:px-6 lg:px-8">

    <div class="flex flex-col md:flex-row justify-between items-end mb-8 border-b border-gray-200 pb-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">
                Dashboard
            </h1>
            <p class="text-gray-500 mt-1">
                Welkom terug, <span class="text-teal-700 font-semibold"><?php echo ucfirst($_SESSION['username']); ?></span>.
                Het is vandaag <?php echo date('d-m-Y'); ?>.
            </p>
        </div>
        <div class="mt-4 md:mt-0">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-teal-100 text-teal-800">
                Rol: <?php echo ucfirst($role); ?>
            </span>
        </div>
    </div>

    <?php if ($role === 'management'): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-blue-500">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                        <span class="text-2xl">👥</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Actieve Cliënten</dt>
                            <dd class="text-3xl font-bold text-gray-900"><?php echo $stats['clients']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-yellow-500">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                        <span class="text-2xl">📦</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Wachtende Bestellingen</dt>
                            <dd class="text-3xl font-bold text-gray-900"><?php echo $stats['orders']; ?></dd>
                        </dl>
                    </div>
                    <?php if($stats['orders'] > 0): ?>
                        <div class="ml-auto">
                            <span class="flex h-3 w-3 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if($stats['orders'] > 0): ?>
                <div class="bg-gray-50 px-5 py-2">
                    <a href="pages/planning/manage_orders.php" class="text-sm text-yellow-700 hover:text-yellow-900 font-medium">Bekijk orders &rarr;</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg border-l-4 border-green-500">
            <div class="p-5">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <span class="text-2xl">👩‍⚕️</span>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Zorgpersoneel</dt>
                            <dd class="text-3xl font-bold text-gray-900"><?php echo $stats['nurses']; ?></dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="text-lg leading-6 font-medium text-gray-900 mb-4">Snelmenu</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">

        <?php if ($role === 'management'): ?>
            
            <a href="pages/clients/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-teal-600 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-teal-700">Cliënten Dossiers</h3>
                        <span class="bg-teal-50 text-teal-700 p-2 rounded text-xl">📂</span>
                    </div>
                    <p class="text-gray-500 text-sm">Beheer intakes, medische gegevens en bekijk rapportages.</p>
                </div>
            </a>

            <a href="pages/users/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-blue-600 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-blue-700">HR & Personeel</h3>
                        <span class="bg-blue-50 text-blue-700 p-2 rounded text-xl">📇</span>
                    </div>
                    <p class="text-gray-500 text-sm">Beheer zusters, accounts en wachtwoorden.</p>
                </div>
            </a>

            <a href="pages/planning/manage.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-purple-600 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-purple-700">Routes & Planning</h3>
                        <span class="bg-purple-50 text-purple-700 p-2 rounded text-xl">🗺️</span>
                    </div>
                    <p class="text-gray-500 text-sm">Maak wijkroutes en koppel personeel aan cliënten.</p>
                </div>
            </a>

            <a href="pages/zorgplan/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-indigo-600 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-indigo-700">Zorgplannen</h3>
                        <span class="bg-indigo-50 text-indigo-700 p-2 rounded text-xl">📋</span>
                    </div>
                    <p class="text-gray-500 text-sm">Zoek cliënten en bekijk geplande zorgtaken.</p>
                </div>
            </a>

            <a href="pages/planning/manage_orders.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-yellow-500 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 group-hover:text-yellow-700">Bestellingen</h3>
                        <span class="bg-yellow-50 text-yellow-700 p-2 rounded text-xl">📦</span>
                    </div>
                    <p class="text-gray-500 text-sm">Keur aanvragen voor materiaal en medicatie goed.</p>
                </div>
            </a>

        <?php elseif ($role === 'zuster'): ?>

            <a href="pages/planning/view.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-l-8 border-teal-600 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xl font-bold text-gray-900">🚑 Mijn Route Vandaag</h3>
                        <span class="text-3xl">➡️</span>
                    </div>
                    <p class="text-gray-600">Start hier uw dienst. Bekijk cliënten en vink zorgtaken af.</p>
                </div>
            </a>

            <a href="pages/clients/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-gray-400 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Alle Cliënten</h3>
                        <span class="bg-gray-100 text-gray-600 p-2 rounded text-xl">📂</span>
                    </div>
                    <p class="text-gray-500 text-sm">Zoek een dossier buiten uw route om.</p>
                </div>
            </a>
            
            <a href="pages/zorgplan/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-indigo-500 flex flex-col h-full">
                <div class="p-6 flex-grow">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Zorgplannen</h3>
                        <span class="bg-indigo-50 text-indigo-600 p-2 rounded text-xl">📋</span>
                    </div>
                    <p class="text-gray-500 text-sm">Bekijk taken per cliënt.</p>
                </div>
            </a>

        <?php elseif ($role === 'familie'): ?>
            
            <a href="pages/clients/index.php" class="group bg-white overflow-hidden shadow hover:shadow-lg transition-shadow rounded-sm border-t-4 border-pink-500 flex flex-col h-full">
                <div class="p-6 flex-grow text-center">
                    <div class="mb-4 text-pink-500 text-6xl">❤️</div>
                    <h3 class="text-xl font-bold text-gray-900">Mijn Familielid</h3>
                    <p class="text-gray-500 text-sm mt-2">Lees rapportages en bekijk de status.</p>
                </div>
            </a>

        <?php endif; ?>

    </div>

    <?php if ($role === 'management' || $role === 'zuster'): ?>
    <div class="bg-white shadow rounded-lg overflow-hidden border border-gray-200">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                📜 Recente Rapportages
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tijd</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cliënt</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Auteur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inhoud</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(count($recent_reports) > 0): ?>
                        <?php foreach($recent_reports as $r): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo date('d-m H:i', strtotime($r['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="pages/clients/detail.php?id=<?php echo $r['client_id']; ?>" class="hover:text-teal-600">
                                    <?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php 
                                    if($r['report_type'] == 'Incident') echo 'bg-red-100 text-red-800';
                                    elseif($r['report_type'] == 'Medisch') echo 'bg-blue-100 text-blue-800';
                                    else echo 'bg-gray-100 text-gray-800'; 
                                    ?>">
                                    <?php echo htmlspecialchars($r['report_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($r['username']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate max-w-xs">
                                <?php echo htmlspecialchars($r['content']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Nog geen recente activiteit.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>