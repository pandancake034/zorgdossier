<?php
// pages/clients/detail.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. CHECK ID
if (!isset($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
$client_id = $_GET['id'];

function calculateAge($dob) {
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

try {
    // A. BASIS DATA
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if (!$client) die("Cliënt niet gevonden.");

    // B. LABELS
    $stmt = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $allergies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $aids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // C. RAPPORTAGES
    $report_sql = "SELECT r.*, u.username, u.role as author_role, np.first_name, np.last_name 
                   FROM client_reports r 
                   JOIN users u ON r.author_id = u.id 
                   LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                   WHERE r.client_id = ?";
    if ($_SESSION['role'] === 'familie') {
        $report_sql .= " AND r.visible_to_family = 1";
    }
    $report_sql .= " ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($report_sql);
    $stmt->execute([$client_id]);
    $reports = $stmt->fetchAll();

    // D. ZORGPLAN
    $lib_stmt = $pdo->query("SELECT * FROM task_library ORDER BY category, title");
    $library_tasks = $lib_stmt->fetchAll();

    $plan_stmt = $pdo->prepare("SELECT * FROM client_care_tasks 
                                WHERE client_id = ? AND is_active = 1 
                                ORDER BY FIELD(time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht', 'Hele dag'), title");
    $plan_stmt->execute([$client_id]);
    $care_plan = $plan_stmt->fetchAll();

    // E. METINGEN
    $obs_stmt = $pdo->prepare("SELECT do.*, u.username, np.first_name 
                               FROM daily_observations do
                               JOIN users u ON do.nurse_id = u.id
                               LEFT JOIN nurse_profiles np ON u.id = np.user_id
                               WHERE do.client_id = ? 
                               ORDER BY do.observation_time DESC LIMIT 50");
    $obs_stmt->execute([$client_id]);
    $observations = $obs_stmt->fetchAll();

    // F. BESTELLINGEN (NIEUW!)
    // Producten voor dropdown
    $prod_stmt = $pdo->query("SELECT * FROM products ORDER BY category, name");
    $products = $prod_stmt->fetchAll();

    // Bestelgeschiedenis
    $order_stmt = $pdo->prepare("SELECT o.*, p.name as product_name, oi.quantity, u.username, np.first_name 
                                 FROM orders o
                                 JOIN order_items oi ON o.id = oi.order_id
                                 JOIN products p ON oi.product_id = p.id
                                 JOIN users u ON o.nurse_id = u.id
                                 LEFT JOIN nurse_profiles np ON u.id = np.user_id
                                 WHERE o.client_id = ?
                                 ORDER BY o.order_date DESC");
    $order_stmt->execute([$client_id]);
    $orders = $order_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<style>
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }
    .tab-btn.active { border-bottom: 3px solid #0d9488; color: #0f766e; font-weight: bold; background-color: #f0fdfa; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="max-w-7xl mx-auto mb-12">

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-teal-700 p-6 md:p-8 text-white flex flex-col md:flex-row items-center md:items-start space-y-4 md:space-y-0 md:space-x-6">
            <div class="h-24 w-24 md:h-32 md:w-32 bg-white rounded-full flex items-center justify-center text-teal-700 text-4xl font-bold shadow-lg border-4 border-teal-200">
                <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
            </div>
            <div class="flex-1 text-center md:text-left">
                <h1 class="text-3xl font-bold"><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></h1>
                <p class="text-teal-200 text-lg"><?php echo calculateAge($client['dob']); ?> jaar • <?php echo date('d-m-Y', strtotime($client['dob'])); ?></p>
                <div class="mt-4 flex flex-wrap justify-center md:justify-start gap-2">
                    <?php if($client['diabetes_type'] !== 'Geen'): ?>
                        <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-bold shadow-sm">🩸 Diabetes <?php echo htmlspecialchars($client['diabetes_type']); ?></span>
                    <?php endif; ?>
                    <?php foreach($allergies as $allergy): ?>
                        <span class="bg-orange-500 text-white px-3 py-1 rounded-full text-sm font-bold shadow-sm">⚠️ <?php echo htmlspecialchars($allergy); ?></span>
                    <?php endforeach; ?>
                    <?php foreach($aids as $aid): ?>
                        <span class="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-bold shadow-sm">♿ <?php echo htmlspecialchars($aid); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if($_SESSION['role'] === 'management'): ?>
                <div><a href="edit.php?id=<?php echo $client_id; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded shadow font-bold text-sm flex items-center">✏️ Wijzig</a></div>
            <?php endif; ?>
        </div>

        <div class="flex border-b border-gray-200 bg-gray-50 text-gray-600 overflow-x-auto">
            <button onclick="openTab('overzicht', this)" class="tab-btn active py-4 px-6 focus:outline-none hover:text-teal-600 whitespace-nowrap">📄 Stamkaart</button>
            <button onclick="openTab('rapportages', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-600 whitespace-nowrap">📝 Rapportages</button>
            <button onclick="openTab('zorgplan', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-600 whitespace-nowrap">📅 Zorgplan</button>
            <button onclick="openTab('metingen', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-600 whitespace-nowrap">🩺 Metingen</button>
            <button onclick="openTab('bestellingen', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-600 whitespace-nowrap">📦 Bestellingen</button>
        </div>
    </div>

    <div id="overzicht" class="tab-content active bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6 border-b pb-2">Persoonlijke Stamkaart</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded border border-gray-200">
                    <h4 class="font-bold text-teal-700 mb-3">🏠 Woonsituatie</h4>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex justify-between border-b pb-1"><span class="text-gray-500">Adres:</span><span class="font-medium text-right"><?php echo htmlspecialchars($client['address']); ?></span></li>
                        <li class="flex justify-between border-b pb-1"><span class="text-gray-500">Wijk:</span><span class="font-medium text-right"><?php echo htmlspecialchars($client['neighborhood']); ?></span></li>
                        <li class="flex justify-between border-b pb-1"><span class="text-gray-500">Woning:</span><span class="font-medium text-right"><?php echo htmlspecialchars($client['housing_type']); ?> (<?php echo htmlspecialchars($client['floor_level']); ?>)</span></li>
                    </ul>
                </div>
            </div>
            <div class="space-y-6">
                <div class="border border-gray-200 rounded p-4 relative">
                    <span class="absolute top-0 right-0 bg-teal-100 text-teal-800 text-xs font-bold px-2 py-1 rounded-bl">1e Contact</span>
                    <h4 class="font-bold text-gray-800 mb-2">👤 <?php echo htmlspecialchars($client['contact1_name']); ?></h4>
                    <p class="text-gray-600 text-sm">📞 <?php echo htmlspecialchars($client['contact1_phone']); ?></p>
                </div>
                <div class="bg-yellow-50 p-3 rounded border border-yellow-200 text-sm italic">
                    <?php echo !empty($client['notes']) ? nl2br(htmlspecialchars($client['notes'])) : 'Geen bijzonderheden.'; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="rapportages" class="tab-content bg-gray-50 rounded-lg shadow p-6 min-h-[500px]">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow border border-teal-100 sticky top-4">
                    <h3 class="font-bold text-teal-700 mb-4">✏️ Nieuwe Rapportage</h3>
                    <form action="save_report.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                            <select name="report_type" class="w-full p-2 border rounded text-sm"><option value="Algemeen">Algemeen</option><option value="Medisch">Medisch</option><option value="Incident">Incident</option></select>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Stemming</label>
                            <div class="flex justify-between gap-1">
                                <label class="cursor-pointer"><input type="radio" name="mood" value="Blij" class="peer sr-only" checked><span class="block text-2xl p-1 rounded hover:bg-green-100 peer-checked:bg-green-200">🙂</span></label>
                                <label class="cursor-pointer"><input type="radio" name="mood" value="Rustig" class="peer sr-only"><span class="block text-2xl p-1 rounded hover:bg-blue-100 peer-checked:bg-blue-200">😌</span></label>
                                <label class="cursor-pointer"><input type="radio" name="mood" value="Pijn" class="peer sr-only"><span class="block text-2xl p-1 rounded hover:bg-red-100 peer-checked:bg-red-200">🤕</span></label>
                            </div>
                        </div>
                        <textarea name="content" rows="4" class="w-full p-3 border rounded text-sm mb-3" required></textarea>
                        <div class="mb-4 flex items-center">
                            <input type="checkbox" name="visible_to_family" id="vis_fam" value="1" checked class="w-4 h-4 text-teal-600 rounded">
                            <label for="vis_fam" class="ml-2 text-sm text-gray-700">Zichtbaar voor familie?</label>
                        </div>
                        <button type="submit" class="w-full bg-teal-600 text-white font-bold py-2 px-4 rounded hover:bg-teal-700">Versturen</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <h3 class="font-bold text-gray-700 mb-6">📅 Tijdlijn</h3>
                <?php if(count($reports) > 0): ?>
                    <div class="relative border-l-2 border-gray-300 ml-4 space-y-8">
                        <?php foreach($reports as $report): 
                             $color = ($report['report_type'] == 'Incident') ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200';
                             $mood_emoji = match($report['mood']) { 'Blij'=>'🙂', 'Rustig'=>'😌', 'Pijn'=>'🤕', default=>'😐' };
                        ?>
                        <div class="relative pl-8">
                            <div class="absolute -left-2.5 top-0 bg-teal-500 h-5 w-5 rounded-full border-4 border-white"></div>
                            <div class="rounded-lg shadow-sm p-4 border <?php echo $color; ?>">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="font-bold text-sm text-gray-800"><?php echo $report['report_type']; ?> <span class="text-gray-400 font-normal">• <?php echo date('d-m H:i', strtotime($report['created_at'])); ?></span></span>
                                    <span class="text-2xl"><?php echo $mood_emoji; ?></span>
                                </div>
                                <p class="text-gray-700 text-sm"><?php echo nl2br(htmlspecialchars($report['content'])); ?></p>
                                <div class="mt-2 text-xs text-gray-400">Door: <?php echo htmlspecialchars($report['first_name'] ? $report['first_name'] : $report['username']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">Geen rapportages.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="zorgplan" class="tab-content bg-gray-50 rounded-lg shadow p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow border border-purple-100 sticky top-4">
                    <h3 class="font-bold text-purple-700 mb-4">📅 Taak Inplannen</h3>
                    <form action="save_task.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Bibliotheek</label>
                            <select id="libSelect" class="w-full p-2 border rounded text-sm" onchange="fillTaskInfo()">
                                <option value="">-- Kies taak --</option>
                                <?php foreach($library_tasks as $lt): ?>
                                    <option value="<?php echo htmlspecialchars($lt['title']); ?>" data-desc="<?php echo htmlspecialchars($lt['default_description']); ?>"><?php echo htmlspecialchars($lt['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="text" name="title" id="taskTitle" class="w-full p-2 border rounded mb-3" placeholder="Titel" required>
                        <textarea name="description" id="taskDesc" rows="2" class="w-full p-2 border rounded mb-3" placeholder="Instructie"></textarea>
                        <select name="time_of_day" class="w-full p-2 border rounded mb-3"><option value="Ochtend">🌅 Ochtend</option><option value="Middag">☀️ Middag</option><option value="Avond">🌙 Avond</option></select>
                        <select name="frequency" class="w-full p-2 border rounded mb-3"><option value="Dagelijks">Dagelijks</option><option value="Wekelijks">Wekelijks</option></select>
                        <button type="submit" class="w-full bg-purple-600 text-white font-bold py-2 px-4 rounded hover:bg-purple-700">Opslaan</button>
                    </form>
                    <script>
                        function fillTaskInfo() {
                            var s = document.getElementById("libSelect");
                            if(s.value !== "") {
                                document.getElementById("taskTitle").value = s.value;
                                document.getElementById("taskDesc").value = s.options[s.selectedIndex].getAttribute("data-desc");
                            }
                        }
                    </script>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <h3 class="font-bold text-gray-700 mb-6">📋 Actueel Zorgplan</h3>
                <?php if(count($care_plan) > 0): 
                    $curr = ''; 
                    foreach($care_plan as $t): 
                        if($t['time_of_day'] != $curr): $curr = $t['time_of_day']; echo "<h4 class='mt-6 mb-2 font-bold text-gray-600 border-b'>$curr</h4>"; endif;
                ?>
                    <div class="bg-white border border-gray-200 p-3 rounded shadow-sm mb-2">
                        <h5 class="font-bold text-gray-800"><?php echo htmlspecialchars($t['title']); ?></h5>
                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($t['description']); ?></p>
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded mt-2 inline-block"><?php echo htmlspecialchars($t['frequency']); ?></span>
                    </div>
                <?php endforeach; else: echo "<p class='text-gray-500'>Geen taken.</p>"; endif; ?>
            </div>
        </div>
    </div>

    <div id="metingen" class="tab-content bg-gray-50 rounded-lg shadow p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow border border-blue-100 sticky top-4">
                    <h3 class="font-bold text-blue-700 mb-4">🩺 Dagstaat</h3>
                    <form action="save_observation.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="flex gap-2 mb-3">
                            <input type="number" name="bp_systolic" class="w-1/2 p-2 border rounded text-sm" placeholder="Sys">
                            <input type="number" name="bp_diastolic" class="w-1/2 p-2 border rounded text-sm" placeholder="Dia">
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <select name="eating" class="p-2 border rounded text-sm"><option value="Goed">Eten: Goed</option><option value="Slecht">Eten: Slecht</option></select>
                            <select name="drinking" class="p-2 border rounded text-sm"><option value="Goed">Drink: Goed</option><option value="Slecht">Drink: Slecht</option></select>
                        </div>
                        <div class="mb-3"><select name="defecation" class="w-full p-2 border rounded text-sm"><option value="Geen">Ontl: Geen</option><option value="Normaal">Ontl: Normaal</option><option value="Obstipatie">Obstipatie</option></select></div>
                        <textarea name="general_impression" rows="2" class="w-full p-2 border rounded text-sm mb-3" placeholder="Algemene indruk"></textarea>
                        <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">Opslaan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <h3 class="font-bold text-gray-700 mb-6">📊 Geschiedenis</h3>
                <table class="min-w-full text-sm text-left bg-white rounded shadow">
                    <thead class="bg-gray-100 font-bold"><tr><th class="px-4 py-2">Datum</th><th class="px-4 py-2">RR</th><th class="px-4 py-2">Eten</th><th class="px-4 py-2">Indruk</th></tr></thead>
                    <tbody>
                        <?php foreach($observations as $obs): ?>
                        <tr class="border-b">
                            <td class="px-4 py-2"><?php echo date('d-m H:i', strtotime($obs['observation_time'])); ?></td>
                            <td class="px-4 py-2 font-mono text-blue-800"><?php echo $obs['bp_systolic'] ? $obs['bp_systolic'].'/'.$obs['bp_diastolic'] : '-'; ?></td>
                            <td class="px-4 py-2"><?php echo $obs['eating']; ?></td>
                            <td class="px-4 py-2 truncate max-w-xs"><?php echo htmlspecialchars($obs['general_impression']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="bestellingen" class="tab-content bg-gray-50 rounded-lg shadow p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white p-5 rounded-lg shadow border border-teal-100 sticky top-4">
                    <h3 class="font-bold text-teal-700 mb-4">📦 Materiaal Aanvragen</h3>
                    <form action="save_order.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Product</label>
                            <select name="product_id" class="w-full p-2 border rounded text-sm" required>
                                <option value="">-- Kies product --</option>
                                <?php foreach($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Aantal</label>
                            <input type="number" name="quantity" value="1" min="1" class="w-full p-2 border rounded" required>
                        </div>
                        <button type="submit" class="w-full bg-teal-600 text-white font-bold py-2 px-4 rounded hover:bg-teal-700">Bestelling Plaatsen</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <h3 class="font-bold text-gray-700 mb-6">📦 Bestelgeschiedenis</h3>
                <?php if(count($orders) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($orders as $ord): 
                            $status_color = 'bg-yellow-100 text-yellow-800'; // Default afwachting
                            if($ord['status'] == 'goedgekeurd') $status_color = 'bg-green-100 text-green-800';
                            if($ord['status'] == 'geweigerd') $status_color = 'bg-red-100 text-red-800';
                            if($ord['status'] == 'geleverd') $status_color = 'bg-blue-100 text-blue-800';
                        ?>
                        <div class="bg-white p-4 rounded shadow-sm border border-gray-200 flex justify-between items-center">
                            <div>
                                <h4 class="font-bold text-gray-800"><?php echo htmlspecialchars($ord['product_name']); ?> <span class="text-sm font-normal text-gray-500">x<?php echo $ord['quantity']; ?></span></h4>
                                <p class="text-xs text-gray-500">Aangevraagd: <?php echo date('d-m-Y', strtotime($ord['order_date'])); ?> door <?php echo htmlspecialchars($ord['first_name']); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?php echo $status_color; ?>"><?php echo $ord['status']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 italic">Nog geen bestellingen.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
    function openTab(id, btn) {
        document.querySelectorAll('.tab-content').forEach(e => e.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(e => e.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        btn.classList.add('active');
    }
    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            const h = window.location.hash.substring(1);
            const b = document.querySelector(`button[onclick="openTab('${h}', this)"]`);
            if(b) b.click();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>
