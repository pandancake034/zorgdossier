<?php
// pages/planning/roster.php
include '../../includes/header.php';
require '../../config/db.php';

// Alleen Management
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$error = "";
$success = "";

// -------------------------------------------------------
// DEEL 1: LOGICA VOOR NIEUWE DAGPLANNING (BOVENAAN)
// -------------------------------------------------------
$selected_date = $_GET['date'] ?? date('Y-m-d'); // Standaard vandaag
$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];

// Bereken de Nederlandse dag afkorting (Ma, Di, etc.) op basis van de gekozen datum
$day_en = date('D', strtotime($selected_date));
$day_nl = str_replace($english_days, $dutch_days, $day_en);

// Haal het rooster op specifiek voor deze dag
$day_roster_stmt = $pdo->prepare("
    SELECT r.id, rt.name as route_name, rt.district, np.first_name, np.last_name, u.username
    FROM roster r
    JOIN routes rt ON r.route_id = rt.id
    JOIN users u ON r.nurse_id = u.id
    LEFT JOIN nurse_profiles np ON u.id = np.user_id
    WHERE r.day_of_week = ?
    ORDER BY rt.name
");
$day_roster_stmt->execute([$day_nl]);
$day_shifts = $day_roster_stmt->fetchAll();


// -------------------------------------------------------
// DEEL 2: LOGICA VOOR HET BESTAANDE BEHEER (ONDERIN)
// -------------------------------------------------------

// ACTIE: DIENST TOEVOEGEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_shift') {
    $nurse_id = $_POST['nurse_id'];
    $route_id = $_POST['route_id'];
    $day = $_POST['day'];

    // Check: Werkt deze zuster al ergens anders op deze dag?
    $check = $pdo->prepare("SELECT * FROM roster WHERE nurse_id = ? AND day_of_week = ?");
    $check->execute([$nurse_id, $day]);
    if ($check->rowCount() > 0) {
        $error = "⚠️ Let op: Deze zuster staat al ingeroosterd op " . $day . " bij een andere route!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO roster (nurse_id, route_id, day_of_week) VALUES (?, ?, ?)");
            $stmt->execute([$nurse_id, $route_id, $day]);
            $success = "Dienst succesvol toegevoegd!";
            // Refresh om de nieuwe data te tonen in beide overzichten
            echo "<script>window.location.href='roster.php?date=$selected_date';</script>"; 
            exit;
        } catch (PDOException $e) {
            $error = "Fout: " . $e->getMessage();
        }
    }
}

// ACTIE: DIENST VERWIJDEREN
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM roster WHERE id = ?")->execute([$id]);
    // Redirect behoudt de geselecteerde datum
    $redir_date = $_GET['date'] ?? date('Y-m-d');
    header("Location: roster.php?date=$redir_date"); 
    exit;
}

// DATA OPHALEN VOOR MATRIX
$nurses = $pdo->query("SELECT u.id, np.first_name, np.last_name FROM users u JOIN nurse_profiles np ON u.id = np.user_id WHERE u.role='zuster' ORDER BY np.first_name")->fetchAll();
$routes = $pdo->query("SELECT * FROM routes ORDER BY name")->fetchAll();

// ROOSTER MATRIX BOUWEN
$roster_data = $pdo->query("
    SELECT r.id, r.route_id, r.day_of_week, np.first_name, np.last_name 
    FROM roster r
    JOIN users u ON r.nurse_id = u.id
    JOIN nurse_profiles np ON u.id = np.user_id
")->fetchAll();

$matrix = [];
foreach($roster_data as $row) {
    $matrix[$row['route_id']][$row['day_of_week']] = [
        'id' => $row['id'],
        'name' => $row['first_name'] . ' ' . substr($row['last_name'], 0, 1) . '.'
    ];
}

$days_list = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
?>

<div class="max-w-7xl mx-auto mb-12">

    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">📅 Personeelsrooster</h2>
            <p class="text-gray-600">Beheer de dagplanning en het weekoverzicht.</p>
        </div>
        <a href="auto_schedule.php" class="text-teal-600 font-bold hover:underline text-sm">
            Naar Auto-Scheduler →
        </a>
    </div>

    <?php if($error): ?><div class="bg-red-100 text-red-700 p-3 rounded mb-4 border border-red-300"><?php echo $error; ?></div><?php endif; ?>
    <?php if($success): ?><div class="bg-green-100 text-green-700 p-3 rounded mb-4 border border-green-300"><?php echo $success; ?></div><?php endif; ?>

    <div class="bg-purple-50 rounded-lg shadow-md border border-purple-200 p-6 mb-10">
        <div class="flex flex-col md:flex-row justify-between items-end mb-4 border-b border-purple-200 pb-4">
            <div>
                <h3 class="text-xl font-bold text-purple-800 flex items-center">
                    🔎 Dagoverzicht: <?php echo $day_nl; ?> <?php echo date('d-m-Y', strtotime($selected_date)); ?>
                </h3>
                <p class="text-sm text-purple-600">Zie direct wie er op deze specifieke datum rijdt.</p>
            </div>
            <form method="GET" class="flex items-center gap-2 mt-4 md:mt-0">
                <label class="font-bold text-sm text-purple-700">Kies datum:</label>
                <input type="date" name="date" value="<?php echo $selected_date; ?>" 
                       class="p-2 border rounded font-bold text-purple-900" 
                       onchange="this.form.submit()">
            </form>
        </div>

        <?php if(count($day_shifts) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach($day_shifts as $shift): ?>
                    <div class="bg-white p-4 rounded shadow-sm border-l-4 border-purple-500 flex justify-between items-center">
                        <div>
                            <div class="font-bold text-gray-800 text-lg"><?php echo htmlspecialchars($shift['route_name']); ?></div>
                            <div class="text-xs text-gray-500 uppercase"><?php echo htmlspecialchars($shift['district']); ?></div>
                        </div>
                        <div class="text-right">
                            <div class="bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm font-bold">
                                👩‍⚕️ <?php echo htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-gray-500 italic bg-white rounded border border-dashed border-gray-300">
                Nog geen diensten gepland voor deze <?php echo $day_nl; ?>. <br>
                Gebruik het formulier hieronder om iemand in te roosteren.
            </div>
        <?php endif; ?>
    </div>

    <div class="border-t-4 border-gray-200 pt-8">
        
        <h3 class="text-lg font-bold text-gray-700 mb-4">✏️ Weekrooster Bewerken</h3>

        <div class="bg-white p-4 rounded-lg shadow-lg mb-8 border-t-4 border-blue-600">
            <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
                <input type="hidden" name="action" value="add_shift">
                
                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">1. Kies Zuster</label>
                    <select name="nurse_id" class="w-full p-2 border rounded font-bold" required>
                        <option value="">-- Selecteer --</option>
                        <?php foreach($nurses as $n): ?>
                            <option value="<?php echo $n['id']; ?>"><?php echo htmlspecialchars($n['first_name'] . ' ' . $n['last_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">2. Kies Route</label>
                    <select name="route_id" class="w-full p-2 border rounded" required>
                        <option value="">-- Selecteer --</option>
                        <?php foreach($routes as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?> (<?php echo $r['district']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex-1 w-full">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">3. Kies Dag</label>
                    <select name="day" class="w-full p-2 border rounded" required>
                        <?php foreach($days_list as $d): ?>
                            <option value="<?php echo $d; ?>"><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded shadow w-full md:w-auto">
                    + Inroosteren
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-800 text-white text-sm uppercase">
                        <th class="p-4 text-left border-r border-gray-700 w-48">Route</th>
                        <?php foreach($days_list as $d): ?>
                            <th class="p-4 text-center border-l border-gray-700"><?php echo $d; ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach($routes as $r): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-4 font-bold text-gray-800 border-r bg-gray-50">
                                <?php echo htmlspecialchars($r['name']); ?>
                                <div class="text-xs text-gray-400 font-normal"><?php echo $r['district']; ?></div>
                            </td>

                            <?php foreach($days_list as $d): ?>
                                <td class="p-2 border-l text-center h-16 relative group">
                                    <?php if (isset($matrix[$r['id']][$d])): 
                                        $shift = $matrix[$r['id']][$d];
                                    ?>
                                        <div class="inline-block bg-teal-100 text-teal-800 px-3 py-1 rounded-full text-sm font-bold shadow-sm relative pr-6">
                                            <?php echo htmlspecialchars($shift['name']); ?>
                                            
                                            <a href="?delete=<?php echo $shift['id']; ?>&date=<?php echo $selected_date; ?>" 
                                               class="absolute right-1 top-1/2 transform -translate-y-1/2 text-teal-400 hover:text-red-500 font-bold px-1"
                                               onclick="return confirm('Dienst verwijderen?');">
                                                ×
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-200 text-2xl font-bold select-none opacity-20 group-hover:opacity-100 transition cursor-default">.</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 text-gray-500 text-sm">
        <p>💡 Tip: Gebruik het formulier om gaten in het rooster te vullen. Dubbele diensten worden geweigerd.</p>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>