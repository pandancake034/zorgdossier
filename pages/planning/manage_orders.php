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
    
    <div class="bg-white border border-gray-300 p-4 mb-6 flex flex-col md:flex-row justify-between items-center shadow-sm">
        <div class="mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight flex items-center">
                <i class="fa-solid fa-boxes-packing mr-3 text-slate-400"></i> Bestellingen & Inkoop
            </h1>
            <p class="text-xs text-slate-500 mt-1">Beheer aanvragen voor medicatie en hulpmiddelen.</p>
        </div>
        
        <a href="../../dashboard.php" class="text-slate-500 hover:text-slate-700 font-bold text-sm flex items-center transition-colors">
            <i class="fa-solid fa-arrow-left mr-2"></i> Terug naar Dashboard
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8 border-t-4 border-amber-500">
        <div class="bg-amber-50 p-4 border-b border-amber-100 flex justify-between items-center">
            <h3 class="font-bold text-amber-800 text-sm uppercase tracking-wide flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2"></i> Te Beoordelen
            </h3>
            <?php if(count($pending_orders) > 0): ?>
                <span class="bg-amber-200 text-amber-900 text-xs px-2 py-1 rounded-full font-bold">
                    <?php echo count($pending_orders); ?> openstaand
                </span>
            <?php endif; ?>
        </div>

        <?php if(count($pending_orders) > 0): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-xs border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-3">Datum</th>
                            <th class="px-6 py-3">Product</th>
                            <th class="px-6 py-3">Cliënt</th>
                            <th class="px-6 py-3">Aangevraagd door</th>
                            <th class="px-6 py-3 text-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($pending_orders as $order): ?>
                            <tr class="hover:bg-amber-50/50 transition cursor-pointer group" onclick="window.location='order_detail.php?id=<?php echo $order['id']; ?>'">
                                <td class="px-6 py-4 whitespace-nowrap text-slate-700">
                                    <div class="font-bold"><?php echo date('d-m-Y', strtotime($order['order_date'])); ?></div>
                                    <div class="text-xs text-slate-400 font-mono"><?php echo date('H:i', strtotime($order['order_date'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-800 block text-base group-hover:text-blue-700 transition-colors">
                                        <?php echo htmlspecialchars($order['product_name']); ?>
                                    </span>
                                    <span class="text-xs text-slate-500">
                                        Aantal: <span class="font-mono font-bold"><?php echo $order['quantity']; ?></span>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center text-xs font-bold text-slate-500 mr-3 border border-slate-200">
                                            <?php echo substr($order['c_first'],0,1).substr($order['c_last'],0,1); ?>
                                        </div>
                                        <span class="font-medium text-slate-700"><?php echo htmlspecialchars($order['c_first'] . ' ' . $order['c_last']); ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500 text-xs uppercase font-bold">
                                    <?php echo htmlspecialchars($order['n_first'] ? $order['n_first'] . ' ' . $order['n_last'] : $order['nurse_name']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="inline-flex items-center bg-white border border-slate-300 hover:border-blue-500 hover:text-blue-600 text-slate-600 px-3 py-1.5 rounded text-xs font-bold shadow-sm transition-all">
                                        <i class="fa-solid fa-eye mr-2"></i> Beoordelen
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="p-12 text-center bg-white">
                <div class="text-slate-200 text-5xl mb-4"><i class="fa-solid fa-clipboard-check"></i></div>
                <h3 class="text-slate-800 font-bold text-lg">Alles is bijgewerkt!</h3>
                <p class="text-slate-500 text-sm mt-1">Er zijn momenteel geen openstaande bestellingen die actie vereisen.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-300">
        <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex items-center">
            <i class="fa-solid fa-clock-rotate-left mr-2 text-slate-400"></i>
            <h3 class="text-xs font-bold text-slate-700 uppercase">Bestelgeschiedenis</h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-left">
                <thead class="bg-white text-slate-400 border-b border-gray-200 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-3 font-medium">Datum</th>
                        <th class="px-6 py-3 font-medium">Product</th>
                        <th class="px-6 py-3 font-medium">Cliënt</th>
                        <th class="px-6 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <?php foreach($history_orders as $hist): ?>
                        <tr class="hover:bg-slate-50 cursor-pointer transition-colors" onclick="window.location='order_detail.php?id=<?php echo $hist['id']; ?>'">
                            <td class="px-6 py-3 text-slate-500 whitespace-nowrap font-mono text-xs">
                                <?php echo date('d-m-Y', strtotime($hist['order_date'])); ?>
                            </td>
                            <td class="px-6 py-3">
                                <span class="font-bold text-slate-700"><?php echo htmlspecialchars($hist['product_name']); ?></span>
                                <span class="text-xs text-slate-400 ml-1">(x<?php echo $hist['quantity']; ?>)</span>
                            </td>
                            <td class="px-6 py-3 text-slate-600">
                                <?php echo htmlspecialchars($hist['c_first'] . ' ' . $hist['c_last']); ?>
                            </td>
                            <td class="px-6 py-3">
                                <?php if($hist['status'] == 'goedgekeurd'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-50 text-green-700 border border-green-200 uppercase">
                                        <i class="fa-solid fa-check mr-1.5"></i> Goedgekeurd
                                    </span>
                                <?php elseif($hist['status'] == 'geweigerd'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-50 text-red-700 border border-red-200 uppercase">
                                        <i class="fa-solid fa-ban mr-1.5"></i> Geweigerd
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 uppercase">
                                        <i class="fa-solid fa-truck-fast mr-1.5"></i> <?php echo htmlspecialchars($hist['status']); ?>
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