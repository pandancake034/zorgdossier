<?php
// pages/planning/manage_orders.php
include '../../includes/header.php';
require '../../config/db.php';

// BEVEILIGING: Alleen Management
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// ACTIE VERWERKEN (Goedkeuren / Weigeren)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'];
    $action = $_POST['action']; // 'approve' of 'deny'
    
    $new_status = ($action === 'approve') ? 'goedgekeurd' : 'geweigerd';
    
    // Update de status in de database
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Toon succesmelding (optioneel via GET parameter)
    header("Location: manage_orders.php?success=1");
    exit;
}

// DATA OPHALEN

// 1. Openstaande bestellingen (Wachtrij)
$sql_pending = "SELECT o.*, 
                       c.first_name as c_first, c.last_name as c_last,
                       u.username as nurse_name,
                       p.name as product_name, oi.quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN clients c ON o.client_id = c.id
                JOIN users u ON o.nurse_id = u.id
                WHERE o.status = 'in_afwachting'
                ORDER BY o.order_date ASC"; // Oudste eerst

$pending_orders = $pdo->query($sql_pending)->fetchAll();

// 2. Afgehandelde bestellingen (Laatste 20)
$sql_history = "SELECT o.*, 
                       c.first_name as c_first, c.last_name as c_last,
                       u.username as nurse_name,
                       p.name as product_name, oi.quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN clients c ON o.client_id = c.id
                JOIN users u ON o.nurse_id = u.id
                WHERE o.status != 'in_afwachting'
                ORDER BY o.order_date DESC
                LIMIT 20";

$history_orders = $pdo->query($sql_history)->fetchAll();
?>

<div class="max-w-6xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">📦 Bestellingen Beheer</h2>
            <p class="text-gray-600">Keur aanvragen van materiaal en medicatie goed.</p>
        </div>
        
        <a href="../../dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded">
            Terug naar Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8 border-l-4 border-yellow-500">
        <div class="bg-yellow-50 p-4 border-b border-yellow-100 flex justify-between items-center">
            <h3 class="font-bold text-yellow-800 text-lg">⚠️ Wacht op goedkeuring (<?php echo count($pending_orders); ?>)</h3>
        </div>

        <?php if(count($pending_orders) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-600 uppercase font-bold text-xs">
                        <tr>
                            <th class="px-6 py-3">Datum</th>
                            <th class="px-6 py-3">Product</th>
                            <th class="px-6 py-3">Cliënt</th>
                            <th class="px-6 py-3">Aangevraagd door</th>
                            <th class="px-6 py-3 text-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach($pending_orders as $order): ?>
                            <tr class="hover:bg-yellow-50 transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php echo date('d-m-Y', strtotime($order['order_date'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-800"><?php echo htmlspecialchars($order['product_name']); ?></span>
                                    <span class="text-xs bg-gray-200 px-2 py-1 rounded ml-2">x<?php echo $order['quantity']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($order['c_first'] . ' ' . $order['c_last']); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo htmlspecialchars($order['nurse_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <form method="POST" onsubmit="return confirm('Zeker weten weigeren?');">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="action" value="deny">
                                            <button class="bg-red-100 hover:bg-red-200 text-red-600 px-3 py-1 rounded font-bold text-xs border border-red-200">
                                                ✕ Weigeren
                                            </button>
                                        </form>
                                        
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-1 rounded font-bold text-xs shadow">
                                                ✓ Goedkeuren
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-8 text-center text-gray-500 italic">
                Geen openstaande bestellingen. Alles is bijgewerkt! 🎉
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-gray-50 p-4 border-b border-gray-200">
            <h3 class="font-bold text-gray-700">📜 Recent Verwerkt</h3>
        </div>
        
        <table class="min-w-full text-sm text-left">
            <thead class="bg-gray-100 text-gray-500">
                <tr>
                    <th class="px-6 py-3">Datum</th>
                    <th class="px-6 py-3">Product</th>
                    <th class="px-6 py-3">Cliënt</th>
                    <th class="px-6 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach($history_orders as $hist): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-gray-500">
                            <?php echo date('d-m-Y', strtotime($hist['order_date'])); ?>
                        </td>
                        <td class="px-6 py-3">
                            <?php echo htmlspecialchars($hist['product_name']); ?> (<?php echo $hist['quantity']; ?>)
                        </td>
                        <td class="px-6 py-3 text-gray-500">
                            <?php echo htmlspecialchars($hist['c_first'] . ' ' . $hist['c_last']); ?>
                        </td>
                        <td class="px-6 py-3">
                            <?php if($hist['status'] == 'goedgekeurd'): ?>
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-bold">Goedgekeurd</span>
                            <?php else: ?>
                                <span class="bg-red-100 text-red-800 px-2 py-1 rounded-full text-xs font-bold">Geweigerd</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
