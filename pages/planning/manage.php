<?php
// pages/planning/manage.php
include '../../includes/header.php';
require '../../config/db.php';

if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// 1. ZUSTER KOPPELEN AAN ROUTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'plan_nurse') {
    $nurse_id = $_POST['nurse_id'];
    $route_id = $_POST['route_id'];
    $day = $_POST['day'];

    $stmt = $pdo->prepare("INSERT IGNORE INTO roster (nurse_id, route_id, day_of_week) VALUES (?, ?, ?)");
    $stmt->execute([$nurse_id, $route_id, $day]);
}

// 2. CLIENT KOPPELEN AAN ROUTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'link_client') {
    $client_id = $_POST['client_id'];
    $route_id = $_POST['route_id'];

    $stmt = $pdo->prepare("UPDATE clients SET route_id = ? WHERE id = ?");
    $stmt->execute([$route_id, $client_id]);
}

// Data ophalen
$routes = $pdo->query("SELECT * FROM routes ORDER BY name")->fetchAll();
$nurses = $pdo->query("SELECT u.id, np.first_name, np.last_name FROM users u JOIN nurse_profiles np ON u.id = np.user_id WHERE u.role='zuster'")->fetchAll();
$clients = $pdo->query("SELECT id, first_name, last_name, route_id FROM clients WHERE is_active=1 ORDER BY last_name")->fetchAll();

// Rooster ophalen voor weergave
$roster = $pdo->query("SELECT r.*, np.first_name, rt.name as route_name 
                       FROM roster r 
                       JOIN nurse_profiles np ON r.nurse_id = np.user_id 
                       JOIN routes rt ON r.route_id = rt.id 
                       ORDER BY FIELD(day_of_week, 'Ma','Di','Wo','Do','Vr','Za','Zo'), rt.name")->fetchAll();
?>

<div class="max-w-6xl mx-auto p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
    
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-teal-700 mb-4">📅 1. Rooster (Wie werkt waar?)</h2>
        
        <form method="POST" class="mb-6 bg-teal-50 p-4 rounded border border-teal-100">
            <input type="hidden" name="action" value="plan_nurse">
            <div class="grid grid-cols-3 gap-2 mb-2">
                <select name="day" class="border p-2 rounded"><option>Ma</option><option>Di</option><option>Wo</option><option>Do</option><option>Vr</option><option>Za</option><option>Zo</option></select>
                <select name="nurse_id" class="border p-2 rounded">
                    <?php foreach($nurses as $n): echo "<option value='{$n['id']}'>{$n['first_name']}</option>"; endforeach; ?>
                </select>
                <select name="route_id" class="border p-2 rounded">
                    <?php foreach($routes as $r): echo "<option value='{$r['id']}'>{$r['name']}</option>"; endforeach; ?>
                </select>
            </div>
            <button class="w-full bg-teal-600 text-white font-bold py-2 rounded">+ Inroosteren</button>
        </form>

        <table class="w-full text-sm">
            <thead class="bg-gray-100 font-bold"><tr><td>Dag</td><td>Zuster</td><td>Route</td><td></td></tr></thead>
            <tbody>
                <?php foreach($roster as $row): ?>
                    <tr class="border-b">
                        <td class="py-2"><?php echo $row['day_of_week']; ?></td>
                        <td><?php echo $row['first_name']; ?></td>
                        <td><?php echo $row['route_name']; ?></td>
                        <td class="text-red-500 cursor-pointer">🗑️</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-purple-700 mb-4">📍 2. Cliënt Indeling (Welke route?)</h2>
        
        <div class="h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 font-bold"><tr><td>Cliënt</td><td>Huidige Route</td><td>Wijzig</td></tr></thead>
                <tbody>
                    <?php foreach($clients as $c): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 font-medium"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name']); ?></td>
                            <td class="text-gray-500">
                                <?php 
                                    // Zoek route naam (beetje quick & dirty voor weergave)
                                    $r_name = '- Geen -';
                                    foreach($routes as $r) { if($r['id'] == $c['route_id']) $r_name = $r['name']; }
                                    echo $r_name;
                                ?>
                            </td>
                            <td>
                                <form method="POST" class="flex">
                                    <input type="hidden" name="action" value="link_client">
                                    <input type="hidden" name="client_id" value="<?php echo $c['id']; ?>">
                                    <select name="route_id" class="text-xs border p-1 rounded w-24 mr-1">
                                        <?php foreach($routes as $r): $sel = ($c['route_id'] == $r['id']) ? 'selected' : ''; echo "<option value='{$r['id']}' $sel>{$r['name']}</option>"; endforeach; ?>
                                    </select>
                                    <button class="text-xs bg-purple-600 text-white px-2 rounded">💾</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
