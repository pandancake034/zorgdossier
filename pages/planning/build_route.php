<?php
// pages/planning/build_route.php
include '../../includes/header.php';
require '../../config/db.php';

// Alleen Management
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// Welke route bewerken we? (Standaard de eerste als er geen gekozen is)
$selected_route_id = $_GET['route_id'] ?? 1;

// 1. TOEVOEGEN STOP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_stop') {
    $client_id = $_POST['client_id'];
    $time = $_POST['planned_time'];
    
    // Voeg toe of update tijd als hij er al in zat
    $stmt = $pdo->prepare("INSERT INTO route_stops (route_id, client_id, planned_time) VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE planned_time = ?");
    $stmt->execute([$selected_route_id, $client_id, $time, $time]);
}

// 2. VERWIJDER STOP
if (isset($_GET['delete_stop'])) {
    $pdo->prepare("DELETE FROM route_stops WHERE id = ?")->execute([$_GET['delete_stop']]);
    header("Location: build_route.php?route_id=$selected_route_id");
    exit;
}

// DATA OPHALEN
// Alle routes (voor dropdown)
$routes = $pdo->query("SELECT * FROM routes ORDER BY name")->fetchAll();

// De haltes van DEZE route (Gesorteerd op tijd!)
$stops = $pdo->prepare("SELECT rs.*, c.first_name, c.last_name, c.address 
                        FROM route_stops rs 
                        JOIN clients c ON rs.client_id = c.id 
                        WHERE rs.route_id = ? 
                        ORDER BY rs.planned_time ASC");
$stops->execute([$selected_route_id]);
$current_stops = $stops->fetchAll();

// Alle cliënten (voor toevoegen)
$clients = $pdo->query("SELECT id, first_name, last_name, district FROM clients WHERE is_active=1 ORDER BY last_name")->fetchAll();
?>

<div class="max-w-5xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">📍 Route Indeling (Spoorboekje)</h2>
        <a href="manage.php" class="text-gray-500 font-bold hover:underline">Terug naar Rooster</a>
    </div>

    <div class="bg-white p-4 rounded shadow mb-6 flex items-center gap-4">
        <label class="font-bold text-gray-700">Bewerk Route:</label>
        <form method="GET" class="flex-1">
            <select name="route_id" onchange="this.form.submit()" class="w-full p-2 border rounded font-bold text-lg text-teal-700">
                <?php foreach($routes as $r): ?>
                    <option value="<?php echo $r['id']; ?>" <?php if($r['id'] == $selected_route_id) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($r['name']); ?> (<?php echo $r['district']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-teal-700 text-white p-3 font-bold flex justify-between">
                    <span>Tijd</span>
                    <span>Cliënt</span>
                    <span>Adres</span>
                    <span></span>
                </div>
                
                <?php if(count($current_stops) > 0): ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach($current_stops as $stop): ?>
                            <div class="p-3 flex items-center hover:bg-teal-50">
                                <div class="w-20 font-mono text-lg font-bold text-teal-800">
                                    <?php echo date('H:i', strtotime($stop['planned_time'])); ?>
                                </div>
                                <div class="flex-1 font-bold text-gray-700">
                                    <?php echo htmlspecialchars($stop['first_name'].' '.$stop['last_name']); ?>
                                </div>
                                <div class="flex-1 text-sm text-gray-500 truncate">
                                    <?php echo htmlspecialchars($stop['address']); ?>
                                </div>
                                <div>
                                    <a href="?route_id=<?php echo $selected_route_id; ?>&delete_stop=<?php echo $stop['id']; ?>" class="text-red-400 hover:text-red-600 font-bold">×</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-400 italic">Nog geen cliënten in deze route.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="md:col-span-1">
            <div class="bg-gray-100 p-5 rounded-lg border border-gray-200 sticky top-4">
                <h3 class="font-bold text-gray-700 mb-4">+ Cliënt Toevoegen</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add_stop">
                    
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Tijdstip</label>
                        <input type="time" name="planned_time" class="w-full p-2 border rounded font-bold" required>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase">Cliënt</label>
                        <select name="client_id" class="w-full p-2 border rounded" size="10">
                            <?php foreach($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>">
                                    <?php echo htmlspecialchars($c['last_name'] . ', ' . $c['first_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1">Tip: Dubbelklikken werkt niet, selecteer en klik op toevoegen.</p>
                    </div>

                    <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 rounded">
                        Toevoegen aan Route
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>
<?php include '../../includes/footer.php'; ?>