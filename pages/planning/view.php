<?php
// pages/planning/view.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// 1. BEPAAL DE DAG
$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
$today_en = date('D'); 
$today_nl = str_replace($english_days, $dutch_days, $today_en);
$current_date = date('d-m-Y');

$nurse_id = $_SESSION['user_id'];
$todays_tasks = [];
$my_route_names = [];

try {
    // 2. STAP A: WELKE ROUTES RIJD IK VANDAAG?
    $routes_stmt = $pdo->prepare("SELECT r.route_id, rt.name 
                                  FROM roster r 
                                  JOIN routes rt ON r.route_id = rt.id 
                                  WHERE r.nurse_id = ? AND r.day_of_week = ?");
    $routes_stmt->execute([$nurse_id, $today_nl]);
    $routes = $routes_stmt->fetchAll();

    $my_route_ids = array_column($routes, 'route_id');
    $my_route_names = array_column($routes, 'name');

    // 3. STAP B: HAAL TAKEN OP (GEKOPPELD AAN TIJDEN)
    if (!empty($my_route_ids)) {
        $placeholders = implode(',', array_fill(0, count($my_route_ids), '?'));

        $sql = "SELECT t.*, 
                       c.first_name, c.last_name, c.address, c.neighborhood, c.district, 
                       rt.name as route_name,
                       rs.planned_time as route_time
                FROM client_care_tasks t
                JOIN clients c ON t.client_id = c.id
                JOIN route_stops rs ON c.id = rs.client_id
                JOIN routes rt ON rs.route_id = rt.id
                WHERE rs.route_id IN ($placeholders)
                AND t.is_active = 1
                AND (t.frequency = 'Dagelijks' OR FIND_IN_SET(?, t.specific_days))
                ORDER BY rs.planned_time ASC, c.address";

        $params = array_merge($my_route_ids, [$today_nl]);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $todays_tasks = $stmt->fetchAll();
    }

    // 4. CHECK REEDS UITGEVOERDE TAKEN
    $log_sql = "SELECT client_care_task_id FROM task_execution_log 
                WHERE nurse_id = ? AND DATE(executed_at) = CURDATE() AND status = 'Uitgevoerd'";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([$nurse_id]);
    $completed_tasks = $log_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<div class="w-full max-w-5xl mx-auto mb-12">
    
    <div class="bg-white border border-gray-300 p-4 mb-6 flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight flex items-center">
                <svg class="w-6 h-6 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Mijn Dagplanning
            </h1>
            <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                <span class="font-bold text-slate-700"><?php echo $today_nl . ' ' . $current_date; ?></span>
                <?php if(!empty($my_route_names)): ?>
                    <span class="text-slate-300">|</span>
                    <span>Route: <?php echo implode(', ', $my_route_names); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <?php 
            $total = count($todays_tasks);
            $done = count($completed_tasks);
            $perc = ($total > 0) ? round(($done / $total) * 100) : 0;
        ?>
        <div class="w-full md:w-64">
            <div class="flex justify-between text-xs font-bold text-slate-600 mb-1 uppercase">
                <span>Voortgang</span>
                <span><?php echo $done; ?> / <?php echo $total; ?> taken</span>
            </div>
            <div class="w-full bg-gray-200 h-2">
                <div class="bg-blue-600 h-2" style="width: <?php echo $perc; ?>%"></div>
            </div>
        </div>
    </div>

    <?php if(count($todays_tasks) > 0): ?>
        
        <div class="bg-white border border-gray-300 shadow-sm">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Route Uitvoering</h3>
            </div>

            <div class="divide-y divide-gray-200">
                <?php 
                $current_client_id = 0;
                foreach($todays_tasks as $task): 
                    $is_done = in_array($task['id'], $completed_tasks);
                    
                    // NIEUWE CLIËNT HEADER (GROEPERING)
                    if($task['client_id'] != $current_client_id):
                        $current_client_id = $task['client_id'];
                        $time_display = date('H:i', strtotime($task['route_time']));
                ?>
                    <div class="bg-slate-50 p-4 border-t-4 border-t-slate-200 flex flex-col md:flex-row md:items-center justify-between mt-2">
                        <div class="flex items-center">
                            <div class="bg-slate-800 text-white font-bold px-3 py-1 text-sm mr-4 min-w-[60px] text-center">
                                <?php echo $time_display; ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base">
                                    <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>" class="hover:text-blue-700 hover:underline">
                                        <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                    </a>
                                </h3>
                                <div class="text-xs text-slate-500 flex items-center">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <?php echo htmlspecialchars($task['address']); ?>
                                </div>
                            </div>
                        </div>
                        <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>#zorgplan" class="mt-2 md:mt-0 text-xs font-bold text-blue-600 uppercase hover:text-blue-800 flex items-center">
                            Dossier Openen <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </a>
                    </div>
                <?php endif; ?>

                <div class="pl-4 md:pl-20 pr-4 py-3 flex items-center justify-between group hover:bg-blue-50/30 transition-colors <?php echo $is_done ? 'bg-gray-50' : ''; ?>">
                    
                    <div class="flex-1 pr-4 <?php echo $is_done ? 'opacity-50 grayscale' : ''; ?>">
                        <div class="flex items-center gap-2 mb-1">
                            <?php 
                                $badge_class = 'bg-gray-100 text-slate-600 border-gray-200';
                                if($task['time_of_day'] == 'Ochtend') $badge_class = 'bg-orange-50 text-orange-800 border-orange-200';
                                if($task['time_of_day'] == 'Middag') $badge_class = 'bg-yellow-50 text-yellow-800 border-yellow-200';
                                if($task['time_of_day'] == 'Avond') $badge_class = 'bg-indigo-50 text-indigo-800 border-indigo-200';
                            ?>
                            <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 border <?php echo $badge_class; ?>">
                                <?php echo $task['time_of_day']; ?>
                            </span>
                            <span class="font-semibold text-sm text-slate-700">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </span>
                        </div>
                        <?php if($task['description']): ?>
                            <p class="text-xs text-slate-500 pl-1 border-l-2 border-gray-200 ml-1">
                                <?php echo htmlspecialchars($task['description']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="w-10 flex justify-center">
                        <?php if(!$is_done): ?>
                            <form action="toggle_task.php" method="POST">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status" value="Uitgevoerd">
                                <button type="submit" class="w-6 h-6 border-2 border-slate-300 text-white hover:border-blue-600 hover:bg-blue-600 flex items-center justify-center transition-all shadow-sm" title="Markeer als voltooid">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-green-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
            
            <div class="bg-gray-50 p-4 text-center border-t border-gray-300">
                <span class="text-xs font-bold text-slate-400 uppercase">Einde van de route</span>
            </div>
        </div>

    <?php else: ?>
        
        <div class="bg-white border border-gray-300 p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4 text-slate-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Geen route gepland</h3>
            <p class="text-slate-500 text-sm mt-2 mb-6">
                U staat voor vandaag (<?php echo $today_nl; ?>) niet ingeroosterd, <br>of de planning heeft nog geen route vrijgegeven.
            </p>
            <a href="../../dashboard.php" class="inline-block bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 px-6 text-sm uppercase">
                Terug naar Dashboard
            </a>
        </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>