<?php
// pages/planning/manage_orders.php
include '../../includes/header.php';
require '../../config/db.php';

// BEVEILIGING: Alleen Management
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// DATA OPHALEN

// 1. Openstaande bestellingen (Wachtrij)
$sql_pending = "SELECT o.*, 
                       c.first_name as c_first, c.last_name as c_last,
                       u.username as nurse_name, np.first_name as n_first, np.last_name as n_last,
                       p.name as product_name, oi.quantity
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN clients c ON o.client_id = c.id
                JOIN users u ON o.nurse_id = u.id
                LEFT JOIN nurse_profiles np ON u.id = np.user_id
                WHERE o.status = 'in_afwachting'
                ORDER BY o.order_date ASC"; // Oudste eerst

$pending_orders = $pdo->query($sql_pending)->fetchAll();

// 2. Afgehandelde bestellingen (Laatste 50)
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
                LIMIT 50";

$history_orders = $pdo->query($sql_history)->fetchAll();
?>

<div class="max-w-7xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-3xl font-bold text-gray-800">📦 Bestellingen Overzicht</h2>
            <p class="text-gray-600">Klik op een bestelling om de details te zien en te keuren.</p>
        </div>
        
        <a href="../../dashboard.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded transition">
            Terug naar Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8 border-l-4 border-yellow-500">
        <div class="bg-yellow-50 p-4 border-b border-yellow-100 flex justify-between items-center">
            <h3 class="font-bold text-yellow-800 text-lg flex items-center">
                ⚠️ Wacht op goedkeuring <span class="ml-2 bg-yellow-200 text-yellow-900 text-xs px-2 py-1 rounded-full"><?php echo count($pending_orders); ?></span>
            </h3>
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
                            <tr class="hover:bg-yellow-50 transition cursor-pointer" onclick="window.location='order_detail.php?id=<?php echo $order['id']; ?>'">
                                <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                                    <?php echo date('d-m-Y', strtotime($order['order_date'])); ?>
                                    <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($order['order_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-800 block text-base"><?php echo htmlspecialchars($order['product_name']); ?></span>
                                    <span class="text-xs bg-gray-200 px-2 py-1 rounded font-bold text-gray-700">Aantal: <?php echo $order['quantity']; ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($order['c_first'] . ' ' . $order['c_last']); ?></span>
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <?php echo htmlspecialchars($order['n_first'] ? $order['n_first'] . ' ' . $order['n_last'] : $order['nurse_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded text-xs font-bold shadow transition">
                                        👁️ Details & Keuren
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-12 text-center">
                <div class="text-4xl mb-2">🎉</div>
                <h3 class="text-gray-900 font-bold">Alles is bijgewerkt!</h3>
                <p class="text-gray-500 italic">Er zijn geen openstaande bestellingen op dit moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-200">
        <div class="bg-gray-50 p-4 border-b border-gray-200">
            <h3 class="font-bold text-gray-700">📜 Recent Verwerkt (Geschiedenis)</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-gray-100 text-gray-500 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3">Datum</th>
                        <th class="px-6 py-3">Product</th>
                        <th class="px-6 py-3">Cliënt</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php foreach($history_orders as $hist): ?>
                        <tr class="hover:bg-gray-50 cursor-pointer transition" onclick="window.location='order_detail.php?id=<?php echo $hist['id']; ?>'">
                            <td class="px-6 py-3 text-gray-500 whitespace-nowrap">
                                <?php echo date('d-m-Y', strtotime($hist['order_date'])); ?>
                            </td>
                            <td class="px-6 py-3">
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($hist['product_name']); ?></span>
                                <span class="text-xs text-gray-400 ml-1">(x<?php echo $hist['quantity']; ?>)</span>
                            </td>
                            <td class="px-6 py-3 text-gray-500">
                                <?php echo htmlspecialchars($hist['c_first'] . ' ' . $hist['c_last']); ?>
                            </td>
                            <td class="px-6 py-3">
                                <?php if($hist['status'] == 'goedgekeurd'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        ✅ Goedgekeurd
                                    </span>
                                <?php elseif($hist['status'] == 'geweigerd'): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        ⛔ Geweigerd
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        📦 Geleverd
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>