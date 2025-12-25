<?php
// pages/planning/view.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// 1. BEPAAL DE DAG & DATUM
$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
// Mapping voor volledige dagnaam (voor weergave)
$dutch_days_full = ['Mon'=>'Maandag', 'Tue'=>'Dinsdag', 'Wed'=>'Woensdag', 'Thu'=>'Donderdag', 'Fri'=>'Vrijdag', 'Sat'=>'Zaterdag', 'Sun'=>'Zondag'];
$dutch_months = ['Jan'=>'Januari', 'Feb'=>'Februari', 'Mar'=>'Maart', 'Apr'=>'April', 'May'=>'Mei', 'Jun'=>'Juni', 'Jul'=>'Juli', 'Aug'=>'Augustus', 'Sep'=>'September', 'Oct'=>'Oktober', 'Nov'=>'November', 'Dec'=>'December'];

$today_en = date('D'); 
$today_nl = str_replace($english_days, $dutch_days, $today_en);
$date_display = $dutch_days_full[$today_en] . ' ' . date('d') . ' ' . $dutch_months[date('M')];

$nurse_id = $_SESSION['user_id'];
$todays_tasks = [];
$my_route_names = [];
$upcoming_shifts = [];

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

    // 3. STAP B: HAAL TAKEN OP (INDIEN VANDAAG)
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

    // 4. CHECK REEDS UITGEVOERDE TAKEN (VOOR PROGRESS BAR)
    $log_sql = "SELECT client_care_task_id FROM task_execution_log 
                WHERE nurse_id = ? AND DATE(executed_at) = CURDATE() AND status = 'Uitgevoerd'";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([$nurse_id]);
    $completed_tasks = $log_stmt->fetchAll(PDO::FETCH_COLUMN);


    // 5. NIEUW: HAAL TOEKOMSTIGE DIENSTEN OP (ALS VANDAAG LEEG IS OF VOOR OVERZICHT)
    // We halen het vaste weekrooster op
    $roster_stmt = $pdo->prepare("SELECT r.day_of_week, rt.name as route_name, rt.district, rt.time_slot 
                                  FROM roster r 
                                  JOIN routes rt ON r.route_id = rt.id 
                                  WHERE r.nurse_id = ?");
    $roster_stmt->execute([$nurse_id]);
    $my_roster = $roster_stmt->fetchAll();

    // We projecteren dit rooster op de komende 14 dagen
    if(count($my_roster) > 0) {
        for($i = 1; $i <= 14; $i++) {
            $ts = strtotime("+$i days");
            $loop_date = date('Y-m-d', $ts);
            $loop_day_en = date('D', $ts);
            $loop_day_nl = str_replace($english_days, $dutch_days, $loop_day_en); // Ma, Di, etc.

            // Check of deze dag in het rooster zit
            foreach($my_roster as $r_item) {
                if($r_item['day_of_week'] === $loop_day_nl) {
                    $upcoming_shifts[] = [
                        'date' => $loop_date,
                        'day_name' => $dutch_days_full[$loop_day_en],
                        'route' => $r_item['route_name'],
                        'district' => $r_item['district'],
                        'time_slot' => $r_item['time_slot']
                    ];
                }
            }
        }
    }

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<div class="w-full max-w-5xl mx-auto mb-12">
    
    <div class="bg-white border border-gray-300 p-4 mb-6 flex flex-col md:flex-row justify-between items-center shadow-sm">
        <div class="mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight flex items-center">
                <i class="fa-solid fa-route mr-3 text-slate-400"></i> Mijn Dagplanning
            </h1>
            <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                <span class="font-bold text-slate-700"><?php echo $date_display; ?></span>
                <?php if(!empty($my_route_names)): ?>
                    <span class="text-slate-300">|</span>
                    <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-[10px] font-bold uppercase">
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
        <div class="w-full md:w-64">
            <div class="flex justify-between text-xs font-bold text-slate-600 mb-1 uppercase">
                <span>Voortgang</span>
                <span><?php echo $done; ?> / <?php echo $total; ?> taken</span>
            </div>
            <div class="w-full bg-gray-200 h-2 rounded-full overflow-hidden">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: <?php echo $perc; ?>%"></div>
            </div>
        </div>
    </div>

    <?php if(count($todays_tasks) > 0): ?>
        
        <div class="bg-white border border-gray-300 shadow-sm">
            <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center">
                    <i class="fa-solid fa-list-check mr-2"></i> Route Uitvoering
                </h3>
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
                    <div class="bg-slate-50 p-4 border-t-4 border-t-slate-200 flex flex-col md:flex-row md:items-center justify-between mt-2 first:mt-0">
                        <div class="flex items-center">
                            <div class="bg-slate-800 text-white font-bold px-3 py-1 text-sm mr-4 min-w-[60px] text-center rounded">
                                <?php echo $time_display; ?>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 text-base flex items-center">
                                    <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>" class="hover:text-blue-700 hover:underline">
                                        <?php echo htmlspecialchars($task['first_name'] . ' ' . $task['last_name']); ?>
                                    </a>
                                </h3>
                                <div class="text-xs text-slate-500 flex items-center">
                                    <i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>
                                    <?php echo htmlspecialchars($task['address']); ?>
                                </div>
                            </div>
                        </div>
                        <a href="../clients/detail.php?id=<?php echo $task['client_id']; ?>#zorgplan" class="mt-2 md:mt-0 text-xs font-bold text-blue-600 uppercase hover:text-blue-800 flex items-center bg-blue-50 px-3 py-1.5 rounded border border-blue-100 hover:bg-blue-100 transition">
                            <i class="fa-solid fa-folder-open mr-1.5"></i> Dossier Openen
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
                            <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 border rounded-sm <?php echo $badge_class; ?>">
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
                                <button type="submit" class="w-8 h-8 rounded-full border-2 border-slate-300 text-white hover:border-green-500 hover:bg-green-500 flex items-center justify-center transition-all shadow-sm text-lg" title="Markeer als voltooid">
                                    <i class="fa-solid fa-check opacity-0 hover:opacity-100"></i>
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="text-green-600 text-xl">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
            
            <div class="bg-gray-50 p-4 text-center border-t border-gray-300">
                <span class="text-xs font-bold text-slate-400 uppercase"><i class="fa-solid fa-flag-checkered mr-1"></i> Einde van de route</span>
            </div>
        </div>

    <?php else: ?>
        
        <div class="space-y-6">
            <div class="bg-white border border-gray-300 p-8 text-center rounded-lg shadow-sm">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-100 mb-4 text-slate-400">
                    <i class="fa-solid fa-mug-hot text-3xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Geen diensten vandaag</h3>
                <p class="text-slate-500 text-sm mt-2 mb-4">
                    U staat voor vandaag (<?php echo $today_nl; ?>) niet ingeroosterd. <br>Geniet van uw vrije dag!
                </p>
                <a href="../../dashboard.php" class="inline-block bg-white border border-gray-300 hover:bg-gray-50 text-slate-700 font-bold py-2 px-6 text-sm rounded shadow-sm transition">
                    Terug naar Dashboard
                </a>
            </div>

            <div class="bg-white border border-gray-300 rounded-lg shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex items-center">
                    <i class="fa-solid fa-calendar-days text-slate-400 mr-2"></i>
                    <h3 class="text-xs font-bold text-slate-700 uppercase">Uw Eerstvolgende Diensten</h3>
                </div>
                
                <?php if(count($upcoming_shifts) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-white border-b border-gray-200 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Datum</th>
                                    <th class="px-5 py-3 font-medium">Route</th>
                                    <th class="px-5 py-3 font-medium">Wijk</th>
                                    <th class="px-5 py-3 font-medium text-right">Tijdslot</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($upcoming_shifts as $shift): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-5 py-3 font-bold text-slate-700">
                                            <?php echo date('d-m', strtotime($shift['date'])); ?>
                                            <span class="font-normal text-slate-500 ml-1">(<?php echo $shift['day_name']; ?>)</span>
                                        </td>
                                        <td class="px-5 py-3 text-slate-700">
                                            <?php echo htmlspecialchars($shift['route']); ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 text-xs uppercase font-bold">
                                            <?php echo htmlspecialchars($shift['district']); ?>
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2 py-0.5 rounded text-xs font-bold">
                                                <?php echo htmlspecialchars($shift['time_slot']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-slate-400 italic">
                        Geen diensten gevonden in de komende 14 dagen.
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>