<?php
// pages/planning/view.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// 1. BEPAAL DE DAG (Vertaal Engelse dag naar NL: Mon -> Ma)
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

    // Haal alleen de ID's op
    $my_route_ids = array_column($routes, 'route_id');
    $my_route_names = array_column($routes, 'name');

    // 3. STAP B: HAAL TAKEN OP VAN CLIËNTEN IN DEZE ROUTES
    if (!empty($my_route_ids)) {
        $placeholders = implode(',', array_fill(0, count($my_route_ids), '?'));

        $sql = "SELECT t.*, 
                       c.first_name, c.last_name, c.address, c.neighborhood, c.district, 
                       rt.name as route_name
                FROM client_care_tasks t
                JOIN clients c ON t.client_id = c.id
                JOIN routes rt ON c.route_id = rt.id
                
                WHERE c.route_id IN ($placeholders)
                AND t.is_active = 1
                AND (t.frequency = 'Dagelijks' OR FIND_IN_SET(?, t.specific_days))
                
                ORDER BY rt.name, c.neighborhood, c.address, FIELD(t.time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht')";

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
    
    <div class="bg-teal-700 text-white p-6 rounded-t-lg shadow-lg flex flex-col md:flex-row justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold">🚑 Mijn Route Vandaag</h2>
            <div class="text-teal-100 mt-1">
                <span class="font-bold text-white"><?php echo $today_nl . ' ' . $current_date; ?></span>
                
                <?php if(!empty($my_route_names)): ?>
                    <span class="mx-2">•</span> 
                    Route: <span class="bg-teal-800 px-2 py-1 rounded text-xs uppercase tracking-wide"><?php echo implode(', ', $my_route_names); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php 
            $total = count($todays_tasks);
            $done = count($completed_tasks);
            $perc = ($total > 0) ? round(($done / $total) * 100) : 0;
        ?>
        <div class="mt-4 md:mt-0 text-center">
            <div class="text-3xl font-bold"><?php echo $perc; ?>%</div>
            <div class="text-xs text-teal-200">Klaar</div>
        </div>
    </div>

    <div class="bg-gray-100 min-h-screen p-4 md:p-6">
        
        <?php if(count($todays_tasks) > 0): ?>
            
            <?php 
            $current_client_id = 0;
            foreach($todays_tasks as $task): 
                $is_done = in_array($task['id'], $completed_tasks);
                
                // CLIENT KAART (Groepering)
                if($task['client_id'] != $current_client_id):
                    $current_client_id = $task['client_id'];
            ?>
                <div class="mt-8 mb-4 flex items-center justify-between border-b-2 border-gray-300 pb-2">
                    <div class="flex items-center">
                        <div class="bg-teal-600 text-white h-10 w-10 rounded-full flex items-center justify-center font-bold mr-3">
                             <?php echo substr($task['first_name'], 0, 1) . substr($task['last_name'], 0, 1); ?>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-gray-800">
                                <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>" class="hover:underline">
                                    <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                </a>
                            </h3>
                            <p class="text-sm text-gray-600">
                                📍 <?php echo htmlspecialchars($task['address']); ?>, <?php echo htmlspecialchars($task['neighborhood']); ?>
                            </p>
                        </div>
                    </div>
                    <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>" class="text-teal-600 text-sm hover:underline font-semibold">
                        Dossier openen ➝
                    </a>
                </div>
            <?php endif; ?>

            <div class="bg-white border-l-4 <?php echo $is_done ? 'border-green-500 opacity-60' : 'border-teal-500'; ?> rounded shadow-sm p-4 mb-3 flex justify-between items-center hover:shadow-md transition">
                <div class="flex-1">
                    <div class="flex items-center mb-1">
                        <?php 
                            $badge_color = 'bg-gray-100 text-gray-600';
                            if($task['time_of_day'] == 'Ochtend') $badge_color = 'bg-orange-100 text-orange-700';
                            if($task['time_of_day'] == 'Middag') $badge_color = 'bg-yellow-100 text-yellow-700';
                        ?>
                        <span class="text-xs font-bold px-2 py-0.5 rounded mr-2 <?php echo $badge_color; ?>">
                            <?php echo $task['time_of_day']; ?>
                        </span>
                        <span class="font-bold text-gray-800 <?php echo $is_done ? 'line-through text-gray-500' : ''; ?>">
                            <?php echo htmlspecialchars($task['title']); ?>
                        </span>
                    </div>
                    <?php if($task['description']): ?>
                        <p class="text-sm text-gray-600 ml-1 <?php echo $is_done ? 'hidden' : ''; ?>">
                            <?php echo htmlspecialchars($task['description']); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if(!$is_done): ?>
                        <form action="toggle_task.php" method="POST">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <input type="hidden" name="status" value="Uitgevoerd">
                            <button type="submit" class="bg-white border border-gray-300 hover:bg-green-50 hover:border-green-500 text-gray-400 hover:text-green-600 h-10 w-10 rounded-full flex items-center justify-center transition" title="Afvinken">⬜</button>
                        </form>
                    <?php else: ?>
                        <div class="text-green-500 text-2xl">✅</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endforeach; ?>

        <?php else: ?>
            <div class="bg-white rounded-lg shadow p-8 text-center mt-8">
                <div class="text-6xl mb-4">☕</div>
                <h3 class="text-2xl font-bold text-gray-700">Geen taken</h3>
                <p class="text-gray-500 mt-2">U staat voor vandaag (<?php echo $today_nl; ?>) niet ingeroosterd, of de route heeft geen taken.</p>
                <div class="mt-6"><a href="../../dashboard.php" class="text-teal-600 hover:underline">Terug naar Dashboard</a></div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
