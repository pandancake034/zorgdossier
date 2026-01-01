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

// Variabelen voor charts
$chart_districts = [];
$chart_reports_dates = [];
$chart_reports_counts = [];

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
        // --- ZUSTER DASHBOARD DATA ---

        // 1. Route Vandaag
        $route_stmt = $pdo->prepare("SELECT r.route_id FROM roster r WHERE r.nurse_id = ? AND r.day_of_week = ?");
        $route_stmt->execute([$user_id, $today_nl]);
        $my_routes = $route_stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($my_routes)) {
            $route_ids_str = implode(',', $my_routes);

            // 2. Alerts (Pijn/Incidenten afgelopen 48u bij MIJN cliënten)
            $alert_sql = "SELECT r.*, c.first_name, c.last_name 
                          FROM client_reports r
                          JOIN clients c ON r.client_id = c.id
                          JOIN route_stops rs ON c.id = rs.client_id
                          WHERE rs.route_id IN ($route_ids_str)
                          AND r.created_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                          AND (r.mood = 'Pijn' OR r.report_type = 'Incident')
                          ORDER BY r.created_at DESC";
            $alerts = $pdo->query($alert_sql)->fetchAll();

            // 3. Bezoeken Tijdlijn
            $visit_sql = "SELECT rs.planned_time, c.id as client_id, c.first_name, c.last_name, c.address,
                                 (SELECT COUNT(*) FROM client_care_tasks t WHERE t.client_id = c.id AND t.is_active=1) as task_count
                          FROM route_stops rs
                          JOIN clients c ON rs.client_id = c.id
                          WHERE rs.route_id IN ($route_ids_str)
                          ORDER BY rs.planned_time ASC";
            $todays_visits = $pdo->query($visit_sql)->fetchAll();

            // 4. Overdracht (Rapportages van collega's)
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
        // --- MANAGEMENT DASHBOARD DATA (MET CHARTS) ---
        
        // 1. KPI's
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders']  = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        $stats['nurses']  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='zuster' AND is_active=1")->fetchColumn();

        // 2. Chart Data: Cliënten per District
        $dist_sql = "SELECT district, COUNT(*) as count FROM clients WHERE is_active=1 GROUP BY district";
        $dist_data = $pdo->query($dist_sql)->fetchAll();
        foreach($dist_data as $d) {
            $chart_districts[] = ['value' => $d['count'], 'name' => $d['district']];
        }

        // 3. Chart Data: Rapportages per dag (Laatste 7 dagen)
        $rep_sql = "SELECT DATE(created_at) as r_date, COUNT(*) as count 
                    FROM client_reports 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
                    GROUP BY DATE(created_at) 
                    ORDER BY r_date ASC";
        $rep_data = $pdo->query($rep_sql)->fetchAll();
        
        // Vul data aan voor dagen met 0 rapportages
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $found = false;
            $count = 0;
            foreach($rep_data as $r) {
                if($r['r_date'] == $date) {
                    $count = $r['count'];
                    $found = true;
                    break;
                }
            }
            $chart_reports_dates[] = date('d-m', strtotime($date)); // Format: 01-01
            $chart_reports_counts[] = $count;
        }

        // 4. Open Orders
        $pending_orders = $pdo->query("SELECT o.*, c.first_name, c.last_name, p.name as product 
                                       FROM orders o JOIN clients c ON o.client_id = c.id 
                                       JOIN order_items oi ON o.id = oi.order_id 
                                       JOIN products p ON oi.product_id = p.id 
                                       WHERE o.status = 'in_afwachting' LIMIT 5")->fetchAll();
    } elseif ($role === 'familie') {
        // --- FAMILIE DATA ---
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
    die("Data Error: " . $e->getMessage());
}
?>

<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<div class="w-full max-w-7xl mx-auto mb-12">

    <div class="bg-white border-b border-gray-200 p-6 mb-6 flex justify-between items-center shadow-sm rounded-t-lg">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">
                Welkom terug, <span class="text-teal-600"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                <?php echo date('l d F Y'); ?> | Rol: <span class="uppercase font-bold text-xs bg-slate-100 px-2 py-0.5 rounded"><?php echo $role; ?></span>
            </p>
        </div>
        <?php if($role === 'management'): ?>
            <a href="pages/users/create.php" class="bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold py-2 px-4 rounded shadow-sm transition">
                <i class="fa-solid fa-plus mr-2"></i> Nieuw
            </a>
        <?php endif; ?>
    </div>

    <?php if ($role === 'zuster'): ?>
        
        <?php if (count($alerts) > 0): ?>
            <div class="mb-8 bg-red-50 border border-red-200 rounded-lg p-4 animate-pulse-slow">
                <h2 class="text-sm font-bold text-red-800 uppercase mb-3 flex items-center">
                    <i class="fa-solid fa-bell mr-2"></i> Vereist Aandacht (Laatste 48u)
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach($alerts as $alert): ?>
                        <a href="pages/clients/detail.php?id=<?php echo $alert['client_id']; ?>#rapportages" class="bg-white p-3 rounded border-l-4 border-red-500 shadow-sm hover:shadow-md transition">
                            <div class="flex justify-between">
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars($alert['first_name'].' '.$alert['last_name']); ?></span>
                                <span class="text-[10px] font-bold text-red-600 uppercase bg-red-100 px-1 rounded"><?php echo $alert['report_type']; ?></span>
                            </div>
                            <p class="text-xs text-slate-600 mt-1 truncate">"<?php echo htmlspecialchars($alert['content']); ?>"</p>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-300 shadow-sm rounded-lg overflow-hidden">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-sm font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-route mr-2 text-teal-600"></i> Mijn Route
                        </h3>
                        <a href="pages/planning/view.php" class="text-xs font-bold text-blue-600 hover:underline">Start Modus &rarr;</a>
                    </div>
                    <?php if(count($todays_visits) > 0): ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach($todays_visits as $v): ?>
                                <div class="p-4 flex items-center hover:bg-slate-50 transition">
                                    <div class="w-16 font-bold text-slate-700 text-lg"><?php echo date('H:i', strtotime($v['planned_time'])); ?></div>
                                    <div class="flex-1">
                                        <div class="font-bold text-blue-700"><?php echo htmlspecialchars($v['first_name'].' '.$v['last_name']); ?></div>
                                        <div class="text-xs text-slate-500"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($v['address']); ?></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="bg-teal-50 text-teal-700 text-xs font-bold px-2 py-1 rounded border border-teal-100">
                                            <?php echo $v['task_count']; ?> taken
                                        </span>
                                    </div>
                                    <a href="pages/clients/detail.php?id=<?php echo $v['client_id']; ?>" class="ml-4 text-slate-300 hover:text-blue-600"><i class="fa-solid fa-chevron-right"></i></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center text-slate-400 italic">Geen route gepland voor vandaag.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm rounded-lg h-full">
                    <div class="bg-indigo-50 px-5 py-3 border-b border-indigo-100">
                        <h3 class="text-sm font-bold text-indigo-900 uppercase"><i class="fa-solid fa-comments mr-2"></i> Overdracht</h3>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <?php if(count($handover_reports) > 0): foreach($handover_reports as $h): ?>
                            <li class="p-4 hover:bg-indigo-50/30 transition">
                                <div class="flex justify-between text-[10px] text-slate-400 uppercase font-bold mb-1">
                                    <span><?php echo date('H:i', strtotime($h['created_at'])); ?> • <?php echo htmlspecialchars($h['author_name']); ?></span>
                                </div>
                                <div class="font-bold text-sm text-slate-800"><?php echo htmlspecialchars($h['c_first'].' '.$h['c_last']); ?></div>
                                <p class="text-xs text-slate-600 mt-1 line-clamp-2"><?php echo htmlspecialchars($h['content']); ?></p>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="p-6 text-center text-xs text-slate-400 italic">Geen recente updates van collega's.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

    <?php elseif ($role === 'management'): ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-5 rounded-lg shadow-sm border-l-4 border-teal-500 flex justify-between items-center">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Totaal Cliënten</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['clients']; ?></span>
                </div>
                <div class="h-12 w-12 bg-teal-50 text-teal-600 rounded-full flex items-center justify-center text-xl"><i class="fa-solid fa-hospital-user"></i></div>
            </div>
            
            <a href="pages/planning/manage_orders.php" class="bg-white p-5 rounded-lg shadow-sm border-l-4 border-orange-500 flex justify-between items-center hover:bg-orange-50 transition group cursor-pointer">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider group-hover:text-orange-600">Open Bestellingen</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['orders']; ?></span>
                </div>
                <div class="h-12 w-12 bg-orange-50 text-orange-600 rounded-full flex items-center justify-center text-xl group-hover:bg-white"><i class="fa-solid fa-boxes-packing"></i></div>
            </a>

            <div class="bg-white p-5 rounded-lg shadow-sm border-l-4 border-blue-500 flex justify-between items-center">
                <div>
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Zorgpersoneel</span>
                    <span class="block text-3xl font-bold text-slate-800"><?php echo $stats['nurses']; ?></span>
                </div>
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl"><i class="fa-solid fa-user-nurse"></i></div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-100 pb-2">
                    <i class="fa-solid fa-chart-pie mr-2 text-teal-600"></i> Cliënten per District
                </h3>
                <div id="districtChart" style="width: 100%; height: 300px;"></div>
            </div>

            <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-4">
                <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-100 pb-2">
                    <i class="fa-solid fa-chart-line mr-2 text-blue-600"></i> Rapportages (7 dagen)
                </h3>
                <div id="reportsChart" style="width: 100%; height: 300px;"></div>
            </div>
        </div>

        <div class="bg-white border border-gray-300 rounded-lg shadow-sm">
            <div class="bg-orange-50 px-5 py-3 border-b border-orange-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-orange-800 uppercase">
                    <i class="fa-solid fa-list-check mr-2"></i> Te Beoordelen Orders
                </h3>
                <a href="pages/planning/manage_orders.php" class="text-xs font-bold text-orange-700 hover:underline">Alles bekijken</a>
            </div>
            <?php if(count($pending_orders) > 0): ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach($pending_orders as $o): ?>
                        <div class="p-4 flex justify-between items-center hover:bg-orange-50/40 transition">
                            <div>
                                <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($o['product']); ?></div>
                                <div class="text-xs text-slate-500">Cliënt: <?php echo htmlspecialchars($o['first_name'].' '.$o['last_name']); ?></div>
                            </div>
                            <a href="pages/planning/order_detail.php?id=<?php echo $o['id']; ?>" class="bg-white border border-gray-300 text-slate-600 hover:text-blue-600 hover:border-blue-400 px-3 py-1 rounded text-xs font-bold uppercase transition">
                                Beoordelen
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-6 text-center text-slate-400 text-sm italic">Geen openstaande bestellingen.</div>
            <?php endif; ?>
        </div>

        <script>
            // Data uit PHP halen
            const distData = <?php echo json_encode($chart_districts); ?>;
            const repDates = <?php echo json_encode($chart_reports_dates); ?>;
            const repCounts = <?php echo json_encode($chart_reports_counts); ?>;

            // 1. District Chart (Donut)
            const distChart = echarts.init(document.getElementById('districtChart'));
            const distOption = {
                tooltip: { trigger: 'item' },
                legend: { bottom: '0%', left: 'center' },
                color: ['#0d9488', '#0f766e', '#115e59', '#134e4a', '#14b8a6', '#5eead4'], // Teal palet
                series: [{
                    name: 'District',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    avoidLabelOverlap: false,
                    itemStyle: { borderRadius: 5, borderColor: '#fff', borderWidth: 2 },
                    label: { show: false, position: 'center' },
                    emphasis: { label: { show: true, fontSize: 14, fontWeight: 'bold' } },
                    data: distData
                }]
            };
            distChart.setOption(distOption);

            // 2. Reports Chart (Line)
            const repChart = echarts.init(document.getElementById('reportsChart'));
            const repOption = {
                tooltip: { trigger: 'axis' },
                grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
                xAxis: { type: 'category', boundaryGap: false, data: repDates },
                yAxis: { type: 'value', minInterval: 1 },
                color: ['#2563eb'], // Blue-600
                series: [{
                    name: 'Rapportages',
                    type: 'line',
                    smooth: true,
                    areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{offset: 0, color: '#3b82f6'}, {offset: 1, color: '#eff6ff'}]) }, // Gradient Blue
                    data: repCounts
                }]
            };
            repChart.setOption(repOption);

            // Responsive maken
            window.addEventListener('resize', function() {
                distChart.resize();
                repChart.resize();
            });
        </script>

    <?php else: ?>
        
        <?php if(isset($my_client) && $my_client): ?>
            <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-8 text-center mb-6">
                <div class="h-20 w-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-4 border-4 border-white shadow">
                    <?php echo substr($my_client['first_name'],0,1).substr($my_client['last_name'],0,1); ?>
                </div>
                <h2 class="text-xl font-bold text-slate-800">Dossier van <?php echo htmlspecialchars($my_client['first_name']); ?></h2>
                <p class="text-slate-500 mb-6"><?php echo htmlspecialchars($my_client['address']); ?></p>
                <a href="pages/clients/detail.php?id=<?php echo $my_client['id']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded shadow transition">
                    Open Volledig Dossier
                </a>
            </div>

            <div class="bg-white border border-gray-300 rounded-lg shadow-sm p-6">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Laatste Berichten</h3>
                <?php if(count($recent_reports) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($recent_reports as $r): ?>
                            <div class="flex gap-4 items-start">
                                <div class="text-center min-w-[40px] pt-1">
                                    <span class="block text-xs font-bold text-slate-400"><?php echo date('d M', strtotime($r['created_at'])); ?></span>
                                </div>
                                <div class="bg-slate-50 p-3 rounded w-full border border-gray-100">
                                    <p class="text-slate-700 text-sm"><?php echo nl2br(htmlspecialchars($r['content'])); ?></p>
                                    <div class="mt-2 text-xs text-slate-400">Type: <?php echo $r['report_type']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 italic text-sm">Nog geen berichten beschikbaar.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="bg-yellow-50 text-yellow-800 p-6 rounded border border-yellow-200 text-center">
                <p class="font-bold">Geen cliënt gekoppeld.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>