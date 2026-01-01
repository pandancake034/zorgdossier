<?php
// dashboard.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
require 'config/db.php';

// 1. INITIALISATIE & DATUM
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
$today_nl     = str_replace($english_days, $dutch_days, date('D')); // Bv. 'Ma'

// ------------------------------------------------------------------
// 2. DATA OPHALEN PER ROL
// ------------------------------------------------------------------

$alerts = [];
$todays_visits = [];
$handover_reports = [];
$stats = [];
$pending_orders = [];

try {
    if ($role === 'zuster') {
        // --- ZUSTER LOGICA ---

        // A. Welke route rijd ik vandaag?
        $route_stmt = $pdo->prepare("SELECT r.route_id FROM roster r WHERE r.nurse_id = ? AND r.day_of_week = ?");
        $route_stmt->execute([$user_id, $today_nl]);
        $my_routes = $route_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($my_routes)) {
            $route_ids_str = implode(',', $my_routes);

            // B. Alerts: Incidenten of Pijn meldingen bij MIJN cliënten (afgelopen 48u)
            // Dit is cruciale triage info!
            $alert_sql = "SELECT r.*, c.first_name, c.last_name, c.address 
                          FROM client_reports r
                          JOIN clients c ON r.client_id = c.id
                          JOIN route_stops rs ON c.id = rs.client_id
                          WHERE rs.route_id IN ($route_ids_str)
                          AND r.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                          AND (r.mood = 'Pijn' OR r.report_type = 'Incident')
                          ORDER BY r.created_at DESC";
            $alerts = $pdo->query($alert_sql)->fetchAll();

            // C. Mijn Taken Vandaag (Tijdlijn)
            $visit_sql = "SELECT rs.planned_time, c.id as client_id, c.first_name, c.last_name, c.address, c.neighborhood,
                                 (SELECT COUNT(*) FROM client_care_tasks t WHERE t.client_id = c.id AND t.is_active=1) as task_count
                          FROM route_stops rs
                          JOIN clients c ON rs.client_id = c.id
                          WHERE rs.route_id IN ($route_ids_str)
                          ORDER BY rs.planned_time ASC";
            $todays_visits = $pdo->query($visit_sql)->fetchAll();

            // D. Overdracht: Recente rapportages van COLLEGA'S over mijn cliënten (laatste 24u)
            // Dus NIET wat ik zelf schreef, maar wat ik moet weten.
            $handover_sql = "SELECT r.*, c.first_name as c_first, c.last_name as c_last, u.username as author_name
                             FROM client_reports r
                             JOIN clients c ON r.client_id = c.id
                             JOIN route_stops rs ON c.id = rs.client_id
                             JOIN users u ON r.author_id = u.id
                             WHERE rs.route_id IN ($route_ids_str)
                             AND r.author_id != ? 
                             AND r.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                             ORDER BY r.created_at DESC LIMIT 5";
            $stmt_ho = $pdo->prepare($handover_sql);
            $stmt_ho->execute([$user_id]);
            $handover_reports = $stmt_ho->fetchAll();
        }

    } elseif ($role === 'management') {
        // --- MANAGEMENT LOGICA ---
        
        // A. KPI's / Tellers
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders']  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        
        // B. Recente Incidenten (System wide alerts)
        $alert_sql = "SELECT r.*, c.first_name, c.last_name, u.username 
                      FROM client_reports r
                      JOIN clients c ON r.client_id = c.id
                      JOIN users u ON r.author_id = u.id
                      WHERE r.report_type = 'Incident' OR r.mood = 'Pijn'
                      ORDER BY r.created_at DESC LIMIT 5";
        $alerts = $pdo->query($alert_sql)->fetchAll();

        // C. Openstaande Orders (Actie vereist)
        $order_sql = "SELECT o.*, c.first_name, c.last_name, p.name as product 
                      FROM orders o 
                      JOIN clients c ON o.client_id = c.id
                      JOIN order_items oi ON o.id = oi.order_id
                      JOIN products p ON oi.product_id = p.id
                      WHERE o.status = 'in_afwachting' LIMIT 5";
        $pending_orders = $pdo->query($order_sql)->fetchAll();

    } elseif ($role === 'familie') {
        // --- FAMILIE LOGICA (Behouden zoals het was, maar in nieuwe stijl) ---
        // Haal gekoppelde cliënt op
        $client_stmt = $pdo->prepare("SELECT c.* FROM clients c JOIN family_client_access a ON c.id = a.client_id WHERE a.user_id = ? LIMIT 1");
        $client_stmt->execute([$user_id]);
        $my_client = $client_stmt->fetch();

        if ($my_client) {
            $report_stmt = $pdo->prepare("SELECT * FROM client_reports WHERE client_id = ? AND visible_to_family = 1 ORDER BY created_at DESC LIMIT 5");
            $report_stmt->execute([$my_client['id']]);
            $recent_reports = $report_stmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}

// ------------------------------------------------------------------
// 3. UI WEERGAVE
// ------------------------------------------------------------------
?>

<div class="w-full max-w-7xl mx-auto mb-12">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 bg-white p-6 border-b border-gray-200 shadow-sm rounded-lg">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">
                Goedemorgen, <?php echo htmlspecialchars($_SESSION['username']); ?> 👋
            </h1>
            <p class="text-slate-500 text-sm mt-1">
                <?php 
                    if($role === 'zuster') echo "Hier is de update voor je dienst van vandaag.";
                    elseif($role === 'management') echo "Hier is het operationele overzicht.";
                    else echo "Welkom in uw zorgomgeving.";
                ?>
            </p>
        </div>
        <div class="mt-4 md:mt-0 text-right">
            <div class="text-sm font-bold text-slate-700 bg-slate-100 px-3 py-1 rounded inline-block">
                📅 <?php echo date('d-m-Y'); ?>
            </div>
        </div>
    </div>

    <?php if ($role === 'zuster'): ?>
        
        <?php if (count($alerts) > 0): ?>
            <div class="mb-8">
                <h2 class="text-sm font-bold text-red-700 uppercase mb-3 flex items-center">
                    <i class="fa-solid fa-bell mr-2 animate-pulse"></i> Aandachtspunten & Risico's
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach($alerts as $alert): 
                        $is_incident = ($alert['report_type'] === 'Incident');
                        $card_color = $is_incident ? 'bg-red-50 border-red-200' : 'bg-orange-50 border-orange-200';
                        $text_color = $is_incident ? 'text-red-800' : 'text-orange-800';
                        $icon = $is_incident ? 'fa-triangle-exclamation' : 'fa-face-frown';
                    ?>
                    <a href="pages/clients/detail.php?id=<?php echo $alert['client_id']; ?>#rapportages" class="block border-l-4 <?php echo $card_color; ?> p-4 rounded shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold <?php echo $text_color; ?>"><?php echo htmlspecialchars($alert['first_name'].' '.$alert['last_name']); ?></h3>
                            <span class="text-xs font-bold <?php echo $text_color; ?> uppercase"><?php echo $alert['report_type']; ?></span>
                        </div>
                        <p class="text-xs text-slate-600 mt-1 line-clamp-2 italic">"<?php echo htmlspecialchars($alert['content']); ?>"</p>
                        <div class="mt-2 text-[10px] text-slate-400 flex items-center">
                            <i class="fa-solid <?php echo $icon; ?> mr-1"></i> 
                            <?php echo date('d-m H:i', strtotime($alert['created_at'])); ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded mb-8 flex items-center">
                <i class="fa-solid fa-check-circle text-xl mr-3"></i>
                <div>
                    <span class="font-bold">Alles rustig.</span>
                    <span class="text-sm">Geen incidenten of pijnmeldingen in de laatste 48u bij jouw cliënten.</span>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="bg-slate-50 px-5 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-route mr-2 text-slate-400"></i> Mijn Route Vandaag
                        </h3>
                        <a href="pages/planning/view.php" class="text-xs font-bold text-blue-600 hover:underline">Start Modus &rarr;</a>
                    </div>
                    
                    <?php if(count($todays_visits) > 0): ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach($todays_visits as $visit): ?>
                                <div class="p-4 flex items-center hover:bg-slate-50 transition group">
                                    <div class="w-16 text-center mr-4">
                                        <div class="text-lg font-bold text-slate-800"><?php echo date('H:i', strtotime($visit['planned_time'])); ?></div>
                                    </div>
                                    <div class="flex-1">
                                        <a href="pages/clients/detail.php?id=<?php echo $visit['client_id']; ?>" class="font-bold text-blue-700 text-base group-hover:underline">
                                            <?php echo htmlspecialchars($visit['first_name'].' '.$visit['last_name']); ?>
                                        </a>
                                        <div class="text-xs text-slate-500 flex items-center mt-1">
                                            <i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i> <?php echo htmlspecialchars($visit['address']); ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="inline-flex items-center bg-blue-50 text-blue-700 text-xs font-bold px-2 py-1 rounded">
                                            <?php echo $visit['task_count']; ?> taken
                                        </span>
                                    </div>
                                    <div class="ml-4">
                                        <a href="pages/clients/detail.php?id=<?php echo $visit['client_id']; ?>#zorgplan" class="text-slate-300 hover:text-blue-600 text-lg">
                                            <i class="fa-solid fa-chevron-right"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-400 italic">
                            <i class="fa-solid fa-mug-hot text-3xl mb-2 block"></i>
                            Je staat vandaag niet ingeroosterd.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm h-full">
                    <div class="bg-indigo-50 px-5 py-4 border-b border-indigo-100">
                        <h3 class="text-sm font-bold text-indigo-900 uppercase flex items-center">
                            <i class="fa-solid fa-clipboard-check mr-2"></i> Overdracht
                        </h3>
                    </div>
                    <div class="p-0">
                        <?php if(count($handover_reports) > 0): ?>
                            <ul class="divide-y divide-gray-100">
                                <?php foreach($handover_reports as $h): ?>
                                    <li class="p-4 hover:bg-slate-50">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-[10px] font-bold uppercase text-slate-500"><?php echo date('H:i', strtotime($h['created_at'])); ?> • <?php echo htmlspecialchars($h['author_name']); ?></span>
                                            <span class="text-[10px] bg-slate-100 px-1 rounded border"><?php echo $h['report_type']; ?></span>
                                        </div>
                                        <div class="font-bold text-sm text-slate-800 mb-1">
                                            <?php echo htmlspecialchars($h['c_first'].' '.$h['c_last']); ?>
                                        </div>
                                        <p class="text-xs text-slate-600 line-clamp-3">
                                            <?php echo htmlspecialchars($h['content']); ?>
                                        </p>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="p-6 text-sm text-slate-400 italic text-center">
                                Geen nieuwe rapportages van collega's in de laatste 24u.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

    <?php elseif ($role === 'management'): ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex items-center justify-between">
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Actieve Cliënten</span>
                    <span class="block text-3xl font-bold text-slate-800 mt-1"><?php echo $stats['clients']; ?></span>
                </div>
                <div class="h-10 w-10 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
            </div>
            
            <a href="pages/planning/manage_orders.php" class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 flex items-center justify-between hover:border-orange-400 transition cursor-pointer relative overflow-hidden">
                <?php if($stats['orders'] > 0): ?><div class="absolute top-0 left-0 w-1 h-full bg-orange-500"></div><?php endif; ?>
                <div>
                    <span class="block text-xs font-bold text-slate-500 uppercase">Open Bestellingen</span>
                    <span class="block text-3xl font-bold <?php echo $stats['orders'] > 0 ? 'text-orange-600' : 'text-slate-800'; ?> mt-1"><?php echo $stats['orders']; ?></span>
                </div>
                <div class="h-10 w-10 bg-orange-50 text-orange-600 rounded-full flex items-center justify-center"><i class="fa-solid fa-box-open"></i></div>
            </a>
            
            <a href="pages/planning/auto_schedule.php" class="bg-teal-600 p-6 rounded-lg shadow-sm text-white flex items-center justify-between hover:bg-teal-700 transition cursor-pointer md:col-span-2">
                <div>
                    <span class="block text-lg font-bold">Auto-Scheduler</span>
                    <span class="block text-sm text-teal-100 mt-1">Genereer planning voor morgen &rarr;</span>
                </div>
                <i class="fa-solid fa-wand-magic-sparkles text-3xl text-teal-200"></i>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="bg-orange-50 px-5 py-4 border-b border-orange-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-orange-800 uppercase flex items-center">
                        <i class="fa-solid fa-list-check mr-2"></i> Te Beoordelen Orders
                    </h3>
                </div>
                <?php if(count($pending_orders) > 0): ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach($pending_orders as $o): ?>
                            <div class="p-4 flex justify-between items-center hover:bg-orange-50/50 transition">
                                <div>
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars($o['product']); ?></div>
                                    <div class="text-xs text-slate-500">Voor: <?php echo htmlspecialchars($o['first_name'].' '.$o['last_name']); ?></div>
                                </div>
                                <a href="pages/planning/order_detail.php?id=<?php echo $o['id']; ?>" class="text-xs bg-white border border-gray-300 px-3 py-1 rounded font-bold text-slate-600 hover:text-blue-600">Beoordeel</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-slate-400 italic">Geen openstaande orders.</div>
                <?php endif; ?>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="bg-red-50 px-5 py-4 border-b border-red-100 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-red-800 uppercase flex items-center">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Recente Incidenten
                    </h3>
                </div>
                <?php if(count($alerts) > 0): ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach($alerts as $a): ?>
                            <div class="p-4 hover:bg-red-50/30 transition">
                                <div class="flex justify-between items-start mb-1">
                                    <span class="text-xs font-bold text-red-600 uppercase"><?php echo $a['report_type']; ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo date('d-m H:i', strtotime($a['created_at'])); ?></span>
                                </div>
                                <div class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($a['first_name'].' '.$a['last_name']); ?></div>
                                <p class="text-xs text-slate-600 truncate"><?php echo htmlspecialchars($a['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-slate-400 italic">Geen recente incidenten.</div>
                <?php endif; ?>
            </div>

        </div>

    <?php else: ?>
        
        <?php if($my_client): ?>
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden mb-6">
                <div class="p-6 md:flex justify-between items-center bg-blue-50">
                    <div class="flex items-center">
                        <div class="h-16 w-16 bg-white rounded-full flex items-center justify-center text-2xl font-bold text-blue-600 border-2 border-blue-100 mr-4">
                            <?php echo substr($my_client['first_name'],0,1).substr($my_client['last_name'],0,1); ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-slate-800">Dossier: <?php echo htmlspecialchars($my_client['first_name'].' '.$my_client['last_name']); ?></h2>
                            <p class="text-slate-600 text-sm"><i class="fa-solid fa-house-medical mr-1"></i> <?php echo htmlspecialchars($my_client['address']); ?></p>
                        </div>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <a href="pages/clients/detail.php?id=<?php echo $my_client['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow transition">
                            Open Volledig Dossier
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                <h3 class="text-sm font-bold text-slate-700 uppercase mb-4 pb-2 border-b border-gray-100">Laatste Updates</h3>
                <?php if(count($recent_reports) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($recent_reports as $r): ?>
                            <div class="flex gap-4">
                                <div class="text-center min-w-[50px]">
                                    <span class="block text-xs font-bold text-slate-400 uppercase"><?php echo date('M', strtotime($r['created_at'])); ?></span>
                                    <span class="block text-xl font-bold text-slate-700"><?php echo date('d', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div>
                                    <p class="text-slate-700 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($r['content'])); ?></p>
                                    <div class="mt-1 flex items-center text-xs text-slate-400">
                                        <span class="bg-gray-100 px-2 rounded mr-2"><?php echo htmlspecialchars($r['report_type']); ?></span>
                                        <?php if($r['mood']): ?><span>Stemming: <?php echo htmlspecialchars($r['mood']); ?></span><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 italic">Er zijn nog geen rapportages zichtbaar.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-8 rounded text-center">
                <i class="fa-solid fa-triangle-exclamation text-3xl mb-2"></i>
                <h3 class="font-bold">Geen cliënt gekoppeld</h3>
                <p>Er is nog geen dossier gekoppeld aan uw account. Neem contact op met de zorginstelling.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>