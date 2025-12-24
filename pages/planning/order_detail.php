<?php
// pages/planning/order_detail.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. CHECK ID & ROL
if (!isset($_GET['id'])) {
    header("Location: manage_orders.php");
    exit;
}
$order_id = $_GET['id'];

// Alleen management mag acties uitvoeren, zuster mag alleen kijken (eigen orders)
$can_manage = ($_SESSION['role'] === 'management');

// 2. ACTIE VERWERKEN (Vanuit deze detail pagina)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $action = $_POST['action'];
    $new_status = ($action === 'approve') ? 'goedgekeurd' : 'geweigerd';
    
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    // Herlaad pagina om nieuwe status te zien
    header("Location: order_detail.php?id=$order_id&success=1");
    exit;
}

// 3. DATA OPHALEN (Met alle joins voor namen en producten)
$sql = "SELECT o.*, 
               c.first_name as c_first, c.last_name as c_last, c.address, c.neighborhood,
               u.username, np.first_name as n_first, np.last_name as n_last,
               p.name as product_name, p.category, p.description as product_desc,
               oi.quantity
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN clients c ON o.client_id = c.id
        JOIN users u ON o.nurse_id = u.id
        LEFT JOIN nurse_profiles np ON u.id = np.user_id
        WHERE o.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) die("Bestelling niet gevonden.");

// Kleurtje bepalen voor status
$status_color = 'bg-yellow-100 text-yellow-800 border-yellow-200';
$status_icon = '⏳';
if ($order['status'] == 'goedgekeurd') { $status_color = 'bg-green-100 text-green-800 border-green-200'; $status_icon = '✅'; }
if ($order['status'] == 'geweigerd') { $status_color = 'bg-red-100 text-red-800 border-red-200'; $status_icon = '⛔'; }
?>

<div class="max-w-4xl mx-auto mb-12">

    <a href="manage_orders.php" class="inline-flex items-center text-gray-600 hover:text-teal-600 mb-6 font-bold transition">
        ← Terug naar overzicht
    </a>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6 border-t-4 border-teal-600">
        <div class="p-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Bestelling #<?php echo $order['id']; ?></h1>
                <p class="text-gray-500">Aangemaakt op <?php echo date('d-m-Y \o\m H:i', strtotime($order['order_date'])); ?></p>
            </div>
            <div class="mt-4 md:mt-0 px-4 py-2 rounded-lg border <?php echo $status_color; ?> font-bold text-lg uppercase tracking-wide flex items-center">
                <span class="mr-2 text-2xl"><?php echo $status_icon; ?></span>
                <?php echo $order['status']; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-700 border-b pb-2 mb-4">📦 Product Informatie</h3>
            
            <div class="flex items-center mb-4">
                <div class="h-16 w-16 bg-blue-50 text-blue-600 rounded flex items-center justify-center text-3xl mr-4">
                    💊
                </div>
                <div>
                    <h4 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($order['product_name']); ?></h4>
                    <span class="text-sm bg-gray-100 text-gray-600 px-2 py-1 rounded"><?php echo htmlspecialchars($order['category']); ?></span>
                </div>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between border-b border-gray-100 pb-2">
                    <span class="text-gray-500">Aantal stuks:</span>
                    <span class="font-bold text-lg"><?php echo $order['quantity']; ?></span>
                </div>
                <div>
                    <span class="text-gray-500 block mb-1">Omschrijving product:</span>
                    <p class="text-sm text-gray-700 italic bg-gray-50 p-2 rounded">
                        "<?php echo htmlspecialchars($order['product_desc']); ?>"
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-700 border-b pb-2 mb-4">👤 Voor wie is dit?</h3>
            
            <div class="mb-6">
                <h4 class="font-bold text-teal-700">Cliënt</h4>
                <p class="text-lg font-semibold"><?php echo htmlspecialchars($order['c_first'] . ' ' . $order['c_last']); ?></p>
                <p class="text-gray-500 text-sm">📍 <?php echo htmlspecialchars($order['address']); ?>, <?php echo htmlspecialchars($order['neighborhood']); ?></p>
                <a href="../clients/detail.php?id=<?php echo $order['client_id']; ?>" class="text-xs text-teal-600 hover:underline">Bekijk dossier →</a>
            </div>

            <div>
                <h4 class="font-bold text-blue-700">Aangevraagd door</h4>
                <p class="font-semibold">Zuster <?php echo htmlspecialchars($order['n_first'] ? $order['n_first'] . ' ' . $order['n_last'] : $order['username']); ?></p>
            </div>
        </div>

    </div>

    <?php if($can_manage && $order['status'] == 'in_afwachting'): ?>
        <div class="mt-8 bg-white p-6 rounded-lg shadow-lg border border-gray-200">
            <h3 class="text-lg font-bold text-gray-800 mb-4">⚡ Actie ondernemen</h3>
            <p class="text-gray-600 mb-4">Wilt u deze aanvraag goedkeuren voor inkoop?</p>
            
            <div class="flex space-x-4">
                <form method="POST" class="flex-1">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded shadow transition transform hover:scale-105 flex items-center justify-center">
                        <span class="text-xl mr-2">✓</span> Goedkeuren
                    </button>
                </form>

                <form method="POST" class="flex-1" onsubmit="return confirm('Weet u zeker dat u dit wilt weigeren?');">
                    <input type="hidden" name="action" value="deny">
                    <button type="submit" class="w-full bg-red-100 hover:bg-red-200 text-red-700 border border-red-300 font-bold py-3 px-4 rounded shadow transition flex items-center justify-center">
                        <span class="text-xl mr-2">✕</span> Weigeren
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include '../../includes/footer.php'; ?>