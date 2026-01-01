<?php
// dashboard.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
require 'config/db.php';

// 1. INITIALISATIE
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
$today_nl     = str_replace($english_days, $dutch_days, date('D'));

// Variabelen voor charts & lijsten
$chart_districts = [];
$chart_reports_dates = [];
$chart_reports_counts = [];
$latest_clients = [];
$all_recent_reports = [];

// ------------------------------------------------------------------
// 2. DATA OPHALEN
// ------------------------------------------------------------------

$alerts = [];
$todays_visits = [];
$handover_reports = [];
$stats = [];
$pending_orders = [];

try {
    if ($role === 'zuster') {
        // --- ZUSTER DASHBOARD ---

        // 1. Route Vandaag
        $route_stmt = $pdo->prepare("SELECT r.route_id FROM roster r WHERE r.nurse_id = ? AND r.day_of_week = ?");
        $route_stmt->execute([$user_id, $today_nl]);
        $my_routes = $route_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($my_routes)) {
            $route_ids_str = implode(',', $my_routes);

            // 2. Alerts (Pijn/Incidenten)
            $alert_sql = "SELECT r.*, c.first_name, c.last_name 
                          FROM client_reports r
                          JOIN clients c ON r.client_id = c.id
                          JOIN route_stops rs ON c.id = rs.client_id
                          WHERE rs.route_id IN ($route_ids_str)
                          AND r.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                          AND (r.mood = 'Pijn' OR r.report_type = 'Incident')
                          ORDER BY r.created_at DESC";
            $alerts = $pdo->query($alert_sql)->fetchAll();

            // 3. Bezoeken
            $visit_sql = "SELECT rs.planned_time, c.id as client_id, c.first_name, c.last_name, c.address,
                                 (SELECT COUNT(*) FROM client_care_tasks t WHERE t.client_id = c.id AND t.is_active=1) as task_count
                          FROM route_stops rs
                          JOIN clients c ON rs.client_id = c.id
                          WHERE rs.route_id IN ($route_ids_str)
                          ORDER BY rs.planned_time ASC";
            $todays_visits = $pdo->query($visit_sql)->fetchAll();

            // 4. Overdracht
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
        // --- MANAGEMENT DASHBOARD ---
        
        // 1. KPI's
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders']  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        $stats['nurses']  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='zuster' AND is_active=1")->fetchColumn();

        // 2. Chart: Cliënten per District
        $dist_sql = "SELECT district, COUNT(*) as count FROM clients WHERE is_active=1 GROUP BY district";
        $dist_data = $pdo->query($dist_sql)->fetchAll();
        foreach($dist_data as $d) {
            $chart_districts[] = ['value' => $d['count'], 'name' => $d['district']];
        }

        // 3. Chart: Rapportages historie
        $rep_sql = "SELECT DATE(created_at) as r_date, COUNT(*) as count 
                    FROM client_reports 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                    GROUP BY DATE(created_at) 
                    ORDER BY r_date ASC";
        $rep_data = $pdo->query($rep_sql)->fetchAll();
        
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $found = false; $count = 0;
            foreach($rep_data as $r) {
                if($r['r_date'] == $date) { $count = $r['count']; $found = true; break; }
            }
            $chart_reports_dates[] = date('d-m', strtotime($date));
            $chart_reports_counts[] = $count;
        }

        // 4. Open Orders
        $pending_orders = $pdo->query("SELECT o.*, c.first_name, c.last_name, p.name as product 
                                       FROM orders o JOIN clients c ON o.client_id = c.id 
                                       JOIN order_items oi ON o.id = oi.order_id 
                                       JOIN products p ON oi.product_id = p.id 
                                       WHERE o.status = 'in_afwachting' LIMIT 5")->fetchAll();

        // 5. Laatste Cliënten (Nieuw)
        $latest_clients = $pdo->query("SELECT * FROM clients WHERE is_active=1 ORDER BY id DESC LIMIT 5")->fetchAll();

        // 6. Alle Recente Rapportages (Nieuw)
        $all_reports_sql = "SELECT r.*, c.first_name, c.last_name, u.username 
                            FROM client_reports r 
                            JOIN clients c ON r.client_id = c.id 
                            JOIN users u ON r.author_id = u.id 
                            ORDER BY r.created_at DESC LIMIT 5";
        $all_recent_reports = $pdo->query($all_reports_sql)->fetchAll();

    } elseif ($role === 'familie') {
        // --- FAMILIE ---
        $stmt = $pdo->prepare("SELECT c.* FROM clients c JOIN family_client_access a ON c.id = a.client_id WHERE a.user_id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $my_client = $stmt->fetch();
        
        if($my_client) {
            $stmt = $pdo->prepare("SELECT * FROM client_reports WHERE client_id = ? AND visible_to_family = 1 ORDER BY created_at DESC LIMIT 5");
            $stmt->execute([$my_client['id']]);
            $recent_reports = $stmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<div class="w-full max-w-7xl mx-auto mb-12">

    <div class="bg-white border-b border-gray-300 p-6 mb-6 flex justify-between items-center shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                Welkom, <span class="text-teal-700"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                <?php echo date('l d F Y'); ?> | <span class="uppercase font-bold text-xs bg-slate-100 text-slate-600 px-2 py-0.5 border border-slate-200"><?php echo $role; ?></span>
            </p>
        </div>
        <?php if($role === 'management'): ?>
            <a href="pages/users/create.php" class="bg-teal-700 hover:bg-teal-800 text-white text-sm font-bold py-2 px-4 shadow-sm transition-colors border border-teal-800">
                <i class="fa-solid fa-plus mr-2"></i> Nieuw
            </a>
        <?php endif; ?>
    </div>

    <?php if ($role === 'zuster'): ?>
        
        <?php if (count($alerts) > 0): ?>
            <div class="mb-8 bg-red-50 border border-red-300 p-4">
                <h2 class="text-sm font-bold text-red-800 uppercase mb-3 flex items-center">
                    <i class="fa-solid fa-bell mr-2"></i> Vereist Aandacht (48u)
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach($alerts as $alert): ?>
                        <a href="pages/clients/detail.php?id=<?php echo $alert['client_id']; ?>#rapportages" class="bg-white p-3 border-l-4 border-red-600 shadow-sm hover:shadow-md transition">
                            <div class="flex justify-between">
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($alert['first_name'].' '.$alert['last_name']); ?></span>
                                <span class="text-[10px] font-bold text-red-700 uppercase bg-red-100 px-1 border border-red-200"><?php echo $alert['report_type']; ?></span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1 truncate">"<?php echo htmlspecialchars($alert['content']); ?>"</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-route mr-2 text-teal-700"></i> Mijn Route
                        </h3>
                        <a href="pages/planning/view.php" class="text-xs font-bold text-blue-700 hover:underline">Start Modus &rarr;</a>
                    </div>
                    <?php if(count($todays_visits) > 0): ?>
                        <div class="divide-y divide-gray-200">
                            <?php foreach($todays_visits as $v): ?>
                                <div class="p-4 flex items-center hover:bg-slate-50 transition">
                                    <div class="w-16 font-bold text-slate-800 text-lg"><?php echo date('H:i', strtotime($v['planned_time'])); ?></div>
                                    <div class="flex-1">
                                        <div class="font-bold text-blue-800"><?php echo htmlspecialchars($v['first_name'].' '.$v['last_name']); ?></div>
                                        <div class="text-xs text-slate-500"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($v['address']); ?></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="bg-teal-50 text-teal-800 text-xs font-bold px-2 py-1 border border-teal-200">
                                            <?php echo $v['task_count']; ?> taken
                                        </span>
                                    </div>
                                    <a href="pages/clients/detail.php?id=<?php echo $v['client_id']; ?>" class="ml-4 text-slate-300 hover:text-blue-700"><i class="fa-solid fa-chevron-right"></i></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-400 italic">Geen route gepland.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm h-full">
                    <div class="bg-blue-50 px-5 py-3 border-b border-blue-200">
                        <h3 class="text-sm font-bold text-blue-900 uppercase"><i class="fa-solid fa-comments mr-2"></i> Overdracht</h3>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        <?php if(count($handover_reports) > 0): foreach($handover_reports as $h): ?>
                            <li class="p-4 hover:bg-blue-50/20 transition">
                                <div class="flex justify-between text-[10px] text-slate-400 uppercase font-bold mb-1">
                                    <span><?php echo date('H:i', strtotime($h['created_at'])); ?> • <?php echo htmlspecialchars($h['author_name']); ?></span>
                                </div>
                                <div class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($h['c_first'].' '.$h['c_last']); ?></div>
                                <p class="text-xs text-slate-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($h['content']); ?></p>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="p-6 text-center text-xs text-slate-400 italic">Geen recente updates.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

    <?php elseif ($role === 'management'): ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-5 shadow-sm border border-gray-300 border-l-8 border-l-teal-600 flex justify-between items-center">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Totaal Cliënten</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['clients']; ?></span>
                </div>
                <div class="h-12 w-12 bg-teal-50 text-teal-700 flex items-center justify-center text-xl border border-teal-100"><i class="fa-solid fa-hospital-user"></i></div>
            </div>
            
            <a href="pages/planning/manage_orders.php" class="bg-white p-5 shadow-sm border border-gray-300 border-l-8 border-l-orange-500 flex justify-between items-center hover:bg-orange-50 transition group cursor-pointer">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider group-hover:text-orange-700">Open Bestellingen</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['orders']; ?></span>
                </div>
                <div class="h-12 w-12 bg-orange-50 text-orange-600 flex items-center justify-center text-xl border border-orange-100 group-hover:bg-white"><i class="fa-solid fa-boxes-packing"></i></div>
            </a>

            <div class="bg-white p-5 shadow-sm border border-gray-300 border-l-8 border-l-blue-600 flex justify-between items-center">
                <div>
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Zorgpersoneel</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['nurses']; ?></span>
                </div>
                <div class="h-12 w-12 bg-blue-50 text-blue-700 flex items-center justify-center text-xl border border-blue-100"><i class="fa-solid fa-user-nurse"></i></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <div class="bg-white border border-gray-300 shadow-sm p-0">
                <div class="bg-slate-50 p-3 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-chart-pie mr-2 text-teal-700"></i> Cliënten per District
                    </h3>
                </div>
                <div class="p-4">
                    <div id="districtChart" style="width: 100%; height: 300px;"></div>
                </div>
            </div>

            <div class="bg-white border border-gray-300 shadow-sm p-0">
                <div class="bg-slate-50 p-3 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-chart-line mr-2 text-blue-700"></i> Rapportages (7 dagen)
                    </h3>
                </div>
                <div class="p-4">
                    <div id="reportsChart" style="width: 100%; height: 300px;"></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-blue-50 px-5 py-3 border-b border-blue-200 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-blue-900 uppercase">
                        <i class="fa-solid fa-pen-to-square mr-2"></i> Recente Rapportages
                    </h3>
                </div>
                <?php if(count($all_recent_reports) > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach($all_recent_reports as $rr): ?>
                            <div class="p-4 hover:bg-blue-50/20 transition group">
                                <div class="flex justify-between items-start mb-1">
                                    <div>
                                        <span class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($rr['first_name'].' '.$rr['last_name']); ?></span>
                                        <span class="text-xs text-slate-500 ml-1">(<?php echo htmlspecialchars($rr['username']); ?>)</span>
                                    </div>
                                    <span class="text-[10px] text-slate-400 font-mono"><?php echo date('d-m H:i', strtotime($rr['created_at'])); ?></span>
                                </div>
                                <p class="text-xs text-slate-600 line-clamp-1 mb-2">"<?php echo htmlspecialchars($rr['content']); ?>"</p>
                                <a href="pages/clients/detail.php?id=<?php echo $rr['client_id']; ?>#rapportages" class="text-[10px] font-bold text-blue-700 hover:underline flex items-center">
                                    LEES VOLLEDIGE RAPPORTAGE <i class="fa-solid fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-slate-400 italic text-sm">Geen recente data.</div>
                <?php endif; ?>
            </div>

            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-teal-50 px-5 py-3 border-b border-teal-200 flex justify-between items-center">
                    <h3 class="text-sm font-bold text-teal-900 uppercase">
                        <i class="fa-solid fa-user-plus mr-2"></i> Laatst toegevoegde cliënten
                    </h3>
                    <a href="pages/clients/index.php" class="text-xs font-bold text-teal-700 hover:underline">Alle Cliënten</a>
                </div>
                <?php if(count($latest_clients) > 0): ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach($latest_clients as $lc): ?>
                            <div class="p-4 flex justify-between items-center hover:bg-teal-50/20 transition">
                                <div>
                                    <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($lc['first_name'].' '.$lc['last_name']); ?></div>
                                    <div class="text-xs text-slate-500 flex items-center mt-0.5">
                                        <i class="fa-solid fa-location-dot mr-1 text-slate-400"></i> 
                                        <?php echo htmlspecialchars($lc['district']); ?>
                                    </div>
                                </div>
                                <a href="pages/clients/detail.php?id=<?php echo $lc['id']; ?>" class="bg-white border border-gray-300 text-slate-600 hover:border-teal-600 hover:text-teal-700 px-3 py-1 text-xs font-bold uppercase transition">
                                    Dossier
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-slate-400 italic text-sm">Nog geen cliënten.</div>
                <?php endif; ?>
            </div>

        </div>

        <script>
            const distData = <?php echo json_encode($chart_districts); ?>;
            const repDates = <?php echo json_encode($chart_reports_dates); ?>;
            const repCounts = <?php echo json_encode($chart_reports_counts); ?>;

            // District Chart
            const distChart = echarts.init(document.getElementById('districtChart'));
            distChart.setOption({
                tooltip: { trigger: 'item' },
                legend: { top: '5%', left: 'center' },
                color: ['#0f766e', '#115e59', '#134e4a', '#0d9488', '#14b8a6'], // Teal shades
                series: [{
                    name: 'District',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    avoidLabelOverlap: false,
                    itemStyle: { borderRadius: 0, borderColor: '#fff', borderWidth: 2 }, // No rounded borders
                    label: { show: false, position: 'center' },
                    emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold' } },
                    data: distData
                }]
            });

            // Reports Chart
            const repChart = echarts.init(document.getElementById('reportsChart'));
            repChart.setOption({
                tooltip: { trigger: 'axis' },
                grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                xAxis: { type: 'category', boundaryGap: false, data: repDates },
                yAxis: { type: 'value', minInterval: 1 },
                color: ['#1d4ed8'], // Blue-700
                series: [{
                    name: 'Rapportages',
                    type: 'line',
                    smooth: true,
                    areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{offset: 0, color: '#bfdbfe'}, {offset: 1, color: '#fff'}]) },
                    data: repCounts
                }]
            });

            window.addEventListener('resize', () => { distChart.resize(); repChart.resize(); });
        </script>

    <?php else: ?>
        
        <?php if(isset($my_client) && $my_client): ?>
            <div class="bg-white border border-gray-300 shadow-sm p-8 text-center mb-6">
                <div class="h-20 w-20 bg-blue-50 text-blue-700 flex items-center justify-center text-3xl font-bold mx-auto mb-4 border border-blue-100">
                    <?php echo substr($my_client['first_name'],0,1).substr($my_client['last_name'],0,1); ?>
                </div>
                <h2 class="text-xl font-bold text-slate-800">Dossier: <?php echo htmlspecialchars($my_client['first_name'].' '.$my_client['last_name']); ?></h2>
                <p class="text-slate-500 mb-6"><?php echo htmlspecialchars($my_client['address']); ?></p>
                <a href="pages/clients/detail.php?id=<?php echo $my_client['id']; ?>" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-8 shadow-sm border border-blue-900 transition">
                    Open Volledig Dossier
                </a>
            </div>
            
            <div class="bg-white border border-gray-300 shadow-sm p-6">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 border-b border-gray-200 pb-2">Recente Updates</h3>
                <?php if(count($recent_reports) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($recent_reports as $r): ?>
                            <div class="flex gap-4 items-start border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                <div class="text-center min-w-[50px] pt-1">
                                    <span class="block text-xs font-bold text-slate-400 uppercase"><?php echo date('M', strtotime($r['created_at'])); ?></span>
                                    <span class="block text-xl font-bold text-slate-700"><?php echo date('d', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div>
                                    <p class="text-slate-700 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($r['content'])); ?></p>
                                    <div class="mt-1 flex items-center text-xs text-slate-400">
                                        <span class="bg-slate-100 px-2 border border-slate-200 mr-2 text-slate-600 font-bold uppercase text-[10px]"><?php echo htmlspecialchars($r['report_type']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 italic text-sm">Geen updates beschikbaar.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 text-yellow-800 p-6 border border-yellow-200 text-center">
                <p class="font-bold">Geen cliënt gekoppeld.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>