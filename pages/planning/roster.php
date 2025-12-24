<?php
// pages/planning/roster.php
include '../../includes/header.php';
require '../../config/db.php';

if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$error = "";
$success = "";
$selected_date = $_GET['date'] ?? date('Y-m-d');
$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
$day_en = date('D', strtotime($selected_date));
$day_nl = str_replace($english_days, $dutch_days, $day_en);

// DAGOVERZICHT QUERY
$day_roster_stmt = $pdo->prepare("SELECT r.id, rt.name as route_name, rt.district, np.first_name, np.last_name 
                                  FROM roster r 
                                  JOIN routes rt ON r.route_id = rt.id 
                                  JOIN users u ON r.nurse_id = u.id 
                                  LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                                  WHERE r.day_of_week = ? ORDER BY rt.name");
$day_roster_stmt->execute([$day_nl]);
$day_shifts = $day_roster_stmt->fetchAll();

// ACTIES (Toevoegen/Verwijderen)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_shift') {
    $nurse_id = $_POST['nurse_id'];
    $route_id = $_POST['route_id'];
    $day = $_POST['day'];
    $check = $pdo->prepare("SELECT * FROM roster WHERE nurse_id = ? AND day_of_week = ?");
    $check->execute([$nurse_id, $day]);
    if ($check->rowCount() > 0) {
        $error = "Fout: Medewerker staat al ingeroosterd op $day.";
    } else {
        $pdo->prepare("INSERT INTO roster (nurse_id, route_id, day_of_week) VALUES (?, ?, ?)")->execute([$nurse_id, $route_id, $day]);
        echo "<script>window.location.href='roster.php?date=$selected_date';</script>"; exit;
    }
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM roster WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: roster.php?date=$selected_date"); exit;
}

// MATRIX DATA
$nurses = $pdo->query("SELECT u.id, np.first_name, np.last_name FROM users u JOIN nurse_profiles np ON u.id = np.user_id WHERE u.role='zuster' ORDER BY np.first_name")->fetchAll();
$routes = $pdo->query("SELECT * FROM routes ORDER BY name")->fetchAll();
$roster_data = $pdo->query("SELECT r.id, r.route_id, r.day_of_week, np.first_name, np.last_name FROM roster r JOIN users u ON r.nurse_id = u.id JOIN nurse_profiles np ON u.id = np.user_id")->fetchAll();

$matrix = [];
foreach($roster_data as $row) {
    $matrix[$row['route_id']][$row['day_of_week']] = ['id' => $row['id'], 'name' => $row['first_name'] . ' ' . substr($row['last_name'], 0, 1) . '.'];
}
$days_list = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
?>

<div class="w-full max-w-7xl mx-auto">

    <div class="bg-white border border-gray-300 p-4 mb-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Personeelsplanning</h1>
            <p class="text-xs text-slate-500">Dag- en weekroosters beheren</p>
        </div>
        <a href="auto_schedule.php" class="text-blue-700 hover:text-blue-900 text-sm font-bold flex items-center">
            Naar Auto-Scheduler <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
        </a>
    </div>

    <?php if($error): ?><div class="bg-red-50 border-l-4 border-red-600 text-red-800 p-3 text-sm mb-4 font-medium"><?php echo $error; ?></div><?php endif; ?>

    <div class="bg-slate-50 border border-gray-300 p-5 mb-8">
        <div class="flex flex-col md:flex-row justify-between items-end mb-4 border-b border-gray-300 pb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide flex items-center">
                    <svg class="w-5 h-5 mr-2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Dagoverzicht: <span class="text-slate-900 ml-1"><?php echo $day_nl . ' ' . date('d-m-Y', strtotime($selected_date)); ?></span>
                </h3>
            </div>
            <form method="GET" class="flex items-center gap-2 mt-4 md:mt-0">
                <input type="date" name="date" value="<?php echo $selected_date; ?>" 
                       class="p-1.5 border border-gray-300 text-sm font-medium text-slate-700 focus:border-blue-500 focus:ring-0" 
                       onchange="this.form.submit()">
            </form>
        </div>

        <?php if(count($day_shifts) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach($day_shifts as $shift): ?>
                    <div class="bg-white p-3 border border-gray-200 border-l-4 border-l-blue-600 shadow-sm flex justify-between items-center">
                        <div>
                            <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($shift['route_name']); ?></div>
                            <div class="text-xs text-slate-500 uppercase"><?php echo htmlspecialchars($shift['district']); ?></div>
                        </div>
                        <div class="text-right">
                            <span class="bg-slate-100 text-slate-700 px-2 py-0.5 text-xs font-bold border border-gray-200">
                                <?php echo htmlspecialchars($shift['first_name'] . ' ' . substr($shift['last_name'],0,1)); ?>.
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-4 text-sm text-slate-500 italic bg-white border border-gray-200 border-dashed">
                Geen diensten gepland voor deze dag.
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white border border-gray-300">
        <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">Weekrooster Matrix</h3>
        </div>

        <div class="p-4 bg-white border-b border-gray-200">
            <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="action" value="add_shift">
                
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Medewerker</label>
                    <select name="nurse_id" class="w-full p-2 border border-gray-300 text-sm bg-gray-50 focus:bg-white transition-colors" required>
                        <option value="">-- Selecteer --</option>
                        <?php foreach($nurses as $n): echo "<option value='{$n['id']}'>".htmlspecialchars($n['first_name'].' '.$n['last_name'])."</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Route</label>
                    <select name="route_id" class="w-full p-2 border border-gray-300 text-sm bg-gray-50 focus:bg-white" required>
                        <option value="">-- Selecteer --</option>
                        <?php foreach($routes as $r): echo "<option value='{$r['id']}'>".htmlspecialchars($r['name'])." ({$r['district']})</option>"; endforeach; ?>
                    </select>
                </div>
                <div class="w-32">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Dag</label>
                    <select name="day" class="w-full p-2 border border-gray-300 text-sm bg-gray-50 focus:bg-white" required>
                        <?php foreach($days_list as $d): echo "<option value='$d'>$d</option>"; endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 px-6 text-sm h-[38px]">
                    Toevoegen
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase text-xs border-b border-gray-300">
                        <th class="p-3 text-left w-48 border-r border-gray-200">Route</th>
                        <?php foreach($days_list as $d): ?>
                            <th class="p-3 text-center border-l border-gray-200"><?php echo $d; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach($routes as $r): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-3 font-bold text-slate-800 border-r border-gray-200 bg-gray-50/30">
                                <?php echo htmlspecialchars($r['name']); ?>
                                <div class="text-[10px] text-slate-400 font-normal uppercase"><?php echo $r['district']; ?></div>
                            </td>
                            <?php foreach($days_list as $d): ?>
                                <td class="p-2 border-l border-gray-200 text-center relative group h-14">
                                    <?php if (isset($matrix[$r['id']][$d])): $shift = $matrix[$r['id']][$d]; ?>
                                        <div class="inline-flex items-center justify-between w-full bg-blue-50 text-blue-800 px-2 py-1 text-xs font-bold border border-blue-200">
                                            <span><?php echo htmlspecialchars($shift['name']); ?></span>
                                            <a href="?delete=<?php echo $shift['id']; ?>&date=<?php echo $selected_date; ?>" 
                                               class="text-blue-400 hover:text-red-600 ml-2" onclick="return confirm('Verwijderen?');">✕</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>