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
                
                -- CRUCIAAL: JOIN MET ROUTE_STOPS VOOR DE TIJD
                JOIN route_stops rs ON c.id = rs.client_id
                JOIN routes rt ON rs.route_id = rt.id
                
                WHERE rs.route_id IN ($placeholders)
                AND t.is_active = 1
                AND (t.frequency = 'Dagelijks' OR FIND_IN_SET(?, t.specific_days))
                
                -- SORTEER OP TIJDSTIP
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

<div class="max-w-4xl mx-auto mb-12">
    
    <div class="bg-teal-700 text-white p-6 rounded-t-lg shadow-lg flex flex-col md:flex-row justify-between items-center sticky top-0 z-50">
        <div>
            <h2 class="text-2xl font-bold">🚑 Mijn Route Vandaag</h2>
            <div class="text-teal-100 mt-1 flex items-center">
                <span class="font-bold text-white mr-3"><?php echo $today_nl . ' ' . $current_date; ?></span>
                <?php if(!empty($my_route_names)): ?>
                    <span class="bg-teal-800 px-2 py-1 rounded text-xs uppercase tracking-wide border border-teal-600">
                        <?php echo implode(', ', $my_route_names); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php 
            $total = count($todays_tasks);
            $done = count($completed_tasks);
            $perc = ($total > 0) ? round(($done / $total) * 100) : 0;
        ?>
        <div class="mt-4 md:mt-0 text-center bg-teal-800 p-2 rounded-lg min-w-[80px]">
            <div class="text-2xl font-bold"><?php echo $perc; ?>%</div>
            <div class="text-xs text-teal-200">Voltooid</div>
        </div>
    </div>

    <div class="bg-gray-100 min-h-screen p-4 md:p-6 pb-20">
        
        <?php if(count($todays_tasks) > 0): ?>
            
            <?php 
            $current_client_id = 0;
            foreach($todays_tasks as $task): 
                $is_done = in_array($task['id'], $completed_tasks);
                
                // CLIENT SECTIE START
                if($task['client_id'] != $current_client_id):
                    $current_client_id = $task['client_id'];
                    $time_display = date('H:i', strtotime($task['route_time']));
            ?>
                <div class="mt-6 mb-2 flex items-center bg-white p-3 rounded-lg shadow-sm border-l-8 border-teal-600 relative overflow-hidden">
                    
                    <div class="flex flex-col items-center justify-center pr-4 mr-4 border-r border-gray-100 min-w-[70px]">
                        <span class="text-2xl font-bold text-gray-800"><?php echo $time_display; ?></span>
                        <span class="text-xs text-gray-400 uppercase">Uur</span>
                    </div>

                    <div class="flex-1">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 leading-none mb-1">
                                    <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>" class="hover:text-teal-600 transition">
                                        <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-500 flex items-center">
                                    📍 <?php echo htmlspecialchars($task['address']); ?>
                                </p>
                            </div>
                            <div class="bg-teal-100 text-teal-800 h-8 w-8 rounded-full flex items-center justify-center font-bold text-xs">
                                <?php echo substr($task['first_name'], 0, 1) . substr($task['last_name'], 0, 1); ?>
                            </div>
                        </div>
                    </div>
                    
                    <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>#zorgplan" class="absolute right-0 top-0 bottom-0 w-8 bg-gray-50 hover:bg-teal-50 flex items-center justify-center border-l border-gray-100 text-gray-400 hover:text-teal-600 transition" title="Open Dossier">
                        ➝
                    </a>
                </div>
            <?php endif; ?>

            <div class="ml-4 pl-4 border-l-2 border-gray-300 mb-2 relative">
                <div class="absolute -left-[9px] top-4 h-4 w-4 rounded-full border-2 border-white <?php echo $is_done ? 'bg-green-500' : 'bg-gray-300'; ?>"></div>

                <div class="bg-white rounded p-3 shadow-sm flex justify-between items-center <?php echo $is_done ? 'opacity-60 bg-gray-50' : ''; ?>">
                    <div class="flex-1 pr-4">
                        <div class="flex items-center gap-2">
                             <?php 
                                $badge_color = 'bg-gray-100 text-gray-600';
                                if($task['time_of_day'] == 'Ochtend') $badge_color = 'bg-orange-100 text-orange-700';
                                if($task['time_of_day'] == 'Middag') $badge_color = 'bg-yellow-100 text-yellow-700';
                                if($task['time_of_day'] == 'Avond') $badge_color = 'bg-indigo-100 text-indigo-700';
                            ?>
                            <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded <?php echo $badge_color; ?>">
                                <?php echo $task['time_of_day']; ?>
                            </span>
                            <span class="font-medium text-gray-800 <?php echo $is_done ? 'line-through decoration-gray-400' : ''; ?>">
                                <?php echo htmlspecialchars($task['title']); ?>
                            </span>
                        </div>
                        <?php if($task['description']): ?>
                            <p class="text-xs text-gray-500 mt-1 ml-1"><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if(!$is_done): ?>
                        <form action="toggle_task.php" method="POST">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <input type="hidden" name="status" value="Uitgevoerd">
                            <button type="submit" class="h-10 w-10 rounded-full border-2 border-gray-300 hover:border-green-500 hover:text-green-600 text-gray-300 flex items-center justify-center transition bg-white shadow-sm">
                                ✔
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-green-500 font-bold text-xl">✅</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endforeach; ?>

            <div class="text-center mt-12 text-gray-400 pb-8">
                <p>Einde van de route</p>
                <div class="text-2xl mt-2">🏁</div>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-8 text-center mt-8 border-t-4 border-teal-500">
                <div class="text-6xl mb-4">☕</div>
                <h3 class="text-2xl font-bold text-gray-700">Geen geplande route</h3>
                <p class="text-gray-500 mt-2">
                    U staat voor vandaag (<?php echo $today_nl; ?>) niet ingeroosterd, <br>
                    of de route is nog niet ingedeeld door de planning.
                </p>
                <div class="mt-6"><a href="../../dashboard.php" class="text-teal-600 hover:underline">Terug naar Dashboard</a></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>