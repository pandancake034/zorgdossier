<?php
// pages/clients/detail.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING & ID CHECK
if (!isset($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
$client_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 2. ACTIES VERWERKEN (Verwijderen & Bewerken)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. VERWIJDEREN
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'];
        
        // Check eigenaarschap of management rol
        $check = $pdo->prepare("SELECT author_id FROM client_reports WHERE id = ?");
        $check->execute([$report_id]);
        $report = $check->fetch();

        if ($report && ($report['author_id'] == $user_id || $user_role === 'management')) {
            $del = $pdo->prepare("DELETE FROM client_reports WHERE id = ?");
            $del->execute([$report_id]);
            // Refresh om form resubmission te voorkomen
            header("Location: detail.php?id=$client_id#rapportages");
            exit;
        }
    }

    // B. BEWERKEN
    if (isset($_POST['action']) && $_POST['action'] === 'edit_report') {
        $report_id = $_POST['report_id'];
        $content = $_POST['content'];
        $mood = $_POST['mood'];
        $type = $_POST['report_type'];

        // Check eigenaarschap of management rol
        $check = $pdo->prepare("SELECT author_id FROM client_reports WHERE id = ?");
        $check->execute([$report_id]);
        $report = $check->fetch();

        if ($report && ($report['author_id'] == $user_id || $user_role === 'management')) {
            $upd = $pdo->prepare("UPDATE client_reports SET content = ?, mood = ?, report_type = ? WHERE id = ?");
            $upd->execute([$content, $mood, $type, $report_id]);
            header("Location: detail.php?id=$client_id#rapportages");
            exit;
        }
    }
}

// Helper functie voor leeftijd
function calculateAge($dob) {
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}

try {
    // A. BASISGEGEVENS CLIËNT
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();

    if (!$client) die("Cliënt niet gevonden.");

    // B. LIJSTEN OPHALEN
    $stmt = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $allergies = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $aids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // C. RAPPORTAGES OPHALEN
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

    // D. OVERIGE DATA (Zorgplan, Metingen, etc.)
    $library_tasks = $pdo->query("SELECT * FROM task_library ORDER BY category, title")->fetchAll();
    $med_library = $pdo->query("SELECT name, standard_dosage FROM medication_library ORDER BY name")->fetchAll();
    
    $plan_stmt = $pdo->prepare("SELECT * FROM client_care_tasks 
                                WHERE client_id = ? AND is_active = 1 
                                ORDER BY FIELD(time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht', 'Hele dag'), title");
    $plan_stmt->execute([$client_id]);
    $care_plan = $plan_stmt->fetchAll();

    $med_stmt = $pdo->prepare("SELECT * FROM client_medications WHERE client_id = ? ORDER BY times");
    $med_stmt->execute([$client_id]);
    $medications = $med_stmt->fetchAll();

    $obs_stmt = $pdo->prepare("SELECT do.*, u.username, np.first_name 
                               FROM daily_observations do
                               JOIN users u ON do.nurse_id = u.id
                               LEFT JOIN nurse_profiles np ON u.id = np.user_id
                               WHERE do.client_id = ? 
                               ORDER BY do.observation_time DESC LIMIT 50");
    $obs_stmt->execute([$client_id]);
    $observations = $obs_stmt->fetchAll();

    $products = $pdo->query("SELECT * FROM products ORDER BY category, name")->fetchAll();
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

<div class="w-full max-w-7xl mx-auto mb-20 relative">

    <div class="bg-white border border-gray-300 shadow-sm mb-6">
        <div class="p-6 flex flex-col md:flex-row items-start justify-between">
            <div class="flex items-start">
                <div class="h-20 w-20 bg-slate-100 border border-slate-300 flex items-center justify-center text-slate-500 text-2xl font-bold mr-6 shrink-0">
                    <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
                </div>
                
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 uppercase tracking-tight mb-1">
                        <?php echo htmlspecialchars($client['last_name'] . ', ' . $client['first_name']); ?>
                    </h1>
                    
                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-slate-600 mb-3">
                        <div class="flex items-center">
                            <span class="font-bold text-slate-800 mr-2">ID:</span> <?php echo htmlspecialchars($client['id_number']); ?>
                        </div>
                        <div class="flex items-center">
                            <span class="font-bold text-slate-800 mr-2">Geb:</span> <?php echo date('d-m-Y', strtotime($client['dob'])); ?> (<?php echo calculateAge($client['dob']); ?>)
                        </div>
                        <div class="flex items-center">
                            <span class="font-bold text-slate-800 mr-2">Locatie:</span> <?php echo htmlspecialchars($client['neighborhood']); ?>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <?php if($client['diabetes_type'] !== 'Geen'): ?>
                            <span class="px-2 py-0.5 bg-red-50 text-red-700 border border-red-200 text-xs font-bold uppercase">
                                Diabetes <?php echo htmlspecialchars($client['diabetes_type']); ?>
                            </span>
                        <?php endif; ?>
                        <?php foreach($allergies as $allergy): ?>
                            <span class="px-2 py-0.5 bg-orange-50 text-orange-700 border border-orange-200 text-xs font-bold uppercase">
                                <?php echo htmlspecialchars($allergy); ?>
                            </span>
                        <?php endforeach; ?>
                        <?php foreach($aids as $aid): ?>
                            <span class="px-2 py-0.5 bg-blue-50 text-blue-700 border border-blue-200 text-xs font-bold uppercase">
                                <?php echo htmlspecialchars($aid); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if($_SESSION['role'] === 'management'): ?>
                <a href="edit.php?id=<?php echo $client_id; ?>" class="mt-4 md:mt-0 bg-white border border-gray-300 text-slate-700 hover:bg-slate-50 hover:text-blue-700 px-4 py-2 text-sm font-bold uppercase transition-colors">
                    Dossier Wijzigen
                </a>
            <?php endif; ?>
        </div>

        <div class="px-6 border-t border-gray-200 bg-slate-50 overflow-x-auto">
            <nav class="flex space-x-8 -mb-px min-w-max" aria-label="Tabs">
                <button onclick="switchTab('overzicht', this)" class="tab-btn active border-blue-600 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm">Stamkaart</button>
                <button onclick="switchTab('notities', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Communicatie (Chat)</button>
                <button onclick="switchTab('rapportages', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Rapportages</button>
                <button onclick="switchTab('zorgplan', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Zorgplan</button>
                <button onclick="switchTab('metingen', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Metingen</button>
                <button onclick="switchTab('bestellingen', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">Materiaal</button>
            </nav>
        </div>
    </div>

    <div id="overzicht" class="tab-content block">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Woonsituatie & Adres</h3></div>
                <div class="p-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2"><span class="text-slate-500">Adres</span><span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['address']); ?></span></div>
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2"><span class="text-slate-500">Wijk/District</span><span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['neighborhood'] . ', ' . $client['district']); ?></span></div>
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2"><span class="text-slate-500">Woning</span><span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['housing_type']); ?> (<?php echo htmlspecialchars($client['floor_level']); ?>)</span></div>
                    <div class="grid grid-cols-3"><span class="text-slate-500">Parkeren</span><span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['parking_info']); ?></span></div>
                </div>
            </div>
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Contactpersonen</h3></div>
                <div class="p-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-100 p-3">
                        <span class="text-[10px] font-bold text-blue-700 uppercase block mb-1">Eerste Contact</span>
                        <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($client['contact1_name']); ?></div>
                        <div class="text-slate-600 text-sm flex items-center mt-1">📞 <?php echo htmlspecialchars($client['contact1_phone']); ?></div>
                    </div>
                    <?php if(!empty($client['notes'])): ?>
                    <div><span class="text-xs font-bold text-slate-500 uppercase mb-1 block">Bijzonderheden</span><div class="text-sm bg-yellow-50 text-slate-700 p-3 border border-yellow-200"><?php echo nl2br(htmlspecialchars($client['notes'])); ?></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="notities" class="tab-content hidden">
        <div class="bg-slate-50 border border-gray-300 h-[600px] flex flex-col">
            <div class="bg-slate-100 px-4 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase">Communicatie & Notities Tijdlijn</h3>
            </div>
            
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <?php if(count($reports) > 0): foreach($reports as $chat): 
                    $is_me = ($chat['author_id'] == $user_id);
                    $align = $is_me ? 'items-end' : 'items-start';
                    $bg = $is_me ? 'bg-blue-100 border-blue-200' : 'bg-white border-gray-300';
                    $author_name = $chat['first_name'] ? $chat['first_name'] . ' ' . $chat['last_name'] : $chat['username'];
                ?>
                    <div class="flex flex-col <?php echo $align; ?>">
                        <div class="flex items-end gap-2 <?php echo $is_me ? 'flex-row-reverse' : 'flex-row'; ?>">
                            <div class="w-8 h-8 bg-slate-300 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0 border border-slate-400">
                                <?php echo substr($author_name, 0, 1); ?>
                            </div>
                            
                            <div class="max-w-xl <?php echo $bg; ?> border p-3 shadow-sm text-sm text-slate-800">
                                <div class="flex justify-between items-center mb-1 gap-4 border-b border-black/10 pb-1">
                                    <span class="font-bold text-xs uppercase text-slate-600"><?php echo htmlspecialchars($author_name); ?></span>
                                    <span class="text-[10px] text-slate-400"><?php echo date('d-m-Y H:i', strtotime($chat['created_at'])); ?></span>
                                </div>
                                <p class="leading-relaxed"><?php echo nl2br(htmlspecialchars($chat['content'])); ?></p>
                                <div class="mt-2 text-[10px] uppercase font-bold text-slate-400">Type: <?php echo $chat['report_type']; ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="text-center text-slate-400 italic mt-10">Start het gesprek of voeg een notitie toe bij 'Rapportages'.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="rapportages" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Nieuwe Rapportage</h3>
                    <form action="save_report.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                            <select name="report_type" class="w-full p-2 border border-gray-300 text-sm bg-slate-50 focus:bg-white"><option value="Algemeen">Algemeen</option><option value="Medisch">Medisch</option><option value="Incident">Incident</option></select>
                        </div>
                        <div class="mb-3">
                             <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Stemming</label>
                             <div class="flex gap-2">
                                 <label class="border border-gray-300 p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Blij" checked> Blij</label>
                                 <label class="border border-gray-300 p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Rustig"> Rustig</label>
                                 <label class="border border-gray-300 p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Pijn"> Pijn</label>
                             </div>
                        </div>
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Omschrijving</label>
                            <textarea name="content" rows="4" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="Typ hier..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="inline-flex items-center text-sm text-slate-600">
                                <input type="checkbox" name="visible_to_family" value="1" checked class="text-blue-600 border-gray-300 focus:ring-0">
                                <span class="ml-2">Zichtbaar voor familie</span>
                            </label>
                        </div>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase tracking-wide">Opslaan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <div class="space-y-4">
                    <?php if(count($reports) > 0): foreach($reports as $report): 
                        $border = ($report['report_type'] == 'Incident') ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-blue-500';
                        $can_edit = ($report['author_id'] == $user_id || $user_role === 'management');
                    ?>
                    <div class="bg-white border border-gray-300 p-4 <?php echo $border; ?> shadow-sm relative group">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-bold text-slate-800 text-sm block"><?php echo $report['report_type']; ?></span>
                                <span class="text-xs text-slate-400"><?php echo date('d-m-Y H:i', strtotime($report['created_at'])); ?> • <?php echo htmlspecialchars($report['first_name'] ?: $report['username']); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase bg-slate-100 px-2 py-1 text-slate-600 border border-gray-200"><?php echo $report['mood']; ?></span>
                                
                                <?php if($can_edit): ?>
                                    <button onclick='openEditModal(<?php echo json_encode($report); ?>)' class="text-slate-400 hover:text-blue-600 p-1" title="Bewerken">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Weet u zeker dat u deze rapportage wilt verwijderen?');">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <button type="submit" class="text-slate-400 hover:text-red-600 p-1" title="Verwijderen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="text-sm text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($report['content'])); ?></p>
                    </div>
                    <?php endforeach; else: ?>
                        <div class="bg-slate-50 border border-dashed border-gray-300 p-8 text-center text-slate-500 italic text-sm">Nog geen rapportages aanwezig.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="zorgplan" class="tab-content hidden">
        <div class="bg-white border border-gray-300 shadow-sm mb-8">
            <div class="bg-slate-100 px-4 py-2 border-b border-gray-300 flex justify-between items-center"><h3 class="text-xs font-bold text-slate-700 uppercase">Medicatie</h3></div>
            <div class="p-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 overflow-x-auto">
                     <?php if(count($medications) > 0): ?>
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-slate-500 border-b border-gray-200 uppercase text-xs">
                                <tr><th class="py-2 px-2">Medicijn</th><th class="py-2 px-2">Sterkte</th><th class="py-2 px-2">Tijden</th><th class="py-2 px-2">Nota</th><th></th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($medications as $med): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-2 px-2 font-bold text-slate-700"><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td class="py-2 px-2 text-slate-600"><?php echo htmlspecialchars($med['dosage']); ?></td>
                                        <td class="py-2 px-2"><span class="bg-blue-50 text-blue-700 border border-blue-200 px-1.5 py-0.5 text-xs font-bold"><?php echo htmlspecialchars($med['times']); ?></span></td>
                                        <td class="py-2 px-2 text-slate-500 italic text-xs"><?php echo htmlspecialchars($med['notes']); ?></td>
                                        <td class="py-2 px-2 text-right">
                                            <?php if($_SESSION['role'] !== 'familie'): ?>
                                                <a href="save_medication.php?action=delete&delete_id=<?php echo $med['id']; ?>&client_id=<?php echo $client_id; ?>" class="text-red-400 hover:text-red-600 font-bold" onclick="return confirm('Verwijderen?');">✕</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-slate-500 italic text-sm">Geen medicatie geregistreerd.</p>
                    <?php endif; ?>
                </div>
                <?php if($_SESSION['role'] !== 'familie'): ?>
                <div class="bg-slate-50 border border-gray-200 p-4">
                    <h4 class="font-bold text-slate-700 mb-3 text-xs uppercase border-b border-gray-200 pb-1">Toevoegen</h4>
                    <form action="save_medication.php" method="POST">
                        <input type="hidden" name="action" value="add"><input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <input type="text" list="medList" name="name" placeholder="Medicijn" class="p-1.5 border border-gray-300 text-sm w-full" required>
                            <datalist id="medList"><?php foreach($med_library as $ml): ?><option value="<?php echo htmlspecialchars($ml['name']); ?>"><?php endforeach; ?></datalist>
                            <input type="text" name="dosage" placeholder="Sterkte" class="p-1.5 border border-gray-300 text-sm w-full">
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <input type="text" name="frequency" placeholder="Freq" class="p-1.5 border border-gray-300 text-sm w-full">
                            <input type="text" name="times" placeholder="Tijd (08:00)" class="p-1.5 border border-gray-300 text-sm w-full">
                        </div>
                        <input type="text" name="notes" placeholder="Bijzonderheden" class="p-1.5 border border-gray-300 text-sm w-full mb-3">
                        <button type="submit" class="w-full bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold py-2 uppercase">Toevoegen</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Taak Inplannen</h3>
                    <form action="save_task.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bibliotheek</label>
                            <select id="libSelect" class="w-full p-2 border border-gray-300 text-sm bg-slate-50" onchange="fillTaskInfo()">
                                <option value="">-- Kies taak --</option>
                                <?php foreach($library_tasks as $lt): ?>
                                    <option value="<?php echo htmlspecialchars($lt['title']); ?>" data-desc="<?php echo htmlspecialchars($lt['default_description']); ?>"><?php echo htmlspecialchars($lt['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="text" name="title" id="taskTitle" class="w-full p-2 border border-gray-300 text-sm mb-3" placeholder="Titel" required>
                        <textarea name="description" id="taskDesc" rows="2" class="w-full p-2 border border-gray-300 text-sm mb-3" placeholder="Instructie"></textarea>
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <select name="time_of_day" class="w-full p-2 border border-gray-300 text-sm"><option value="Ochtend">Ochtend</option><option value="Middag">Middag</option><option value="Avond">Avond</option><option value="Nacht">Nacht</option></select>
                            <select name="frequency" class="w-full p-2 border border-gray-300 text-sm"><option value="Dagelijks">Dagelijks</option><option value="Wekelijks">Wekelijks</option></select>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-xs mb-4 text-slate-600">
                            <label><input type="checkbox" name="days[]" value="Ma"> Ma</label><label><input type="checkbox" name="days[]" value="Di"> Di</label><label><input type="checkbox" name="days[]" value="Wo"> Wo</label><label><input type="checkbox" name="days[]" value="Do"> Do</label><label><input type="checkbox" name="days[]" value="Vr"> Vr</label><label><input type="checkbox" name="days[]" value="Za"> Za</label><label><input type="checkbox" name="days[]" value="Zo"> Zo</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase">Opslaan</button>
                    </form>
                    <script>function fillTaskInfo() {var s = document.getElementById("libSelect");if(s.value !== "") {document.getElementById("taskTitle").value = s.value;document.getElementById("taskDesc").value = s.options[s.selectedIndex].getAttribute("data-desc");}}</script>
                </div>
            </div>
            <?php endif; ?>
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Actueel Zorgplan</h3></div>
                    <div class="p-4">
                        <?php if(count($care_plan) > 0): $curr = ''; foreach($care_plan as $t): 
                                if($t['time_of_day'] != $curr): $curr = $t['time_of_day']; echo "<h4 class='mt-4 mb-2 font-bold text-slate-400 text-xs uppercase border-b border-gray-100'>$curr</h4>"; endif; ?>
                            <div class="bg-white border border-gray-200 p-3 mb-2 hover:bg-slate-50 transition-colors">
                                <div class="flex justify-between items-start"><h5 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($t['title']); ?></h5><span class="text-[10px] bg-blue-50 text-blue-800 px-1.5 py-0.5 border border-blue-200 uppercase font-bold"><?php echo htmlspecialchars($t['frequency']); ?></span></div>
                                <p class="text-xs text-slate-600 mt-1"><?php echo htmlspecialchars($t['description']); ?></p>
                            </div>
                        <?php endforeach; else: echo "<p class='text-slate-500 italic text-sm'>Geen taken ingepland.</p>"; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="metingen" class="tab-content hidden"><div class="bg-white border border-gray-300 p-6"><p class="text-slate-500">Metingen module geladen...</p></div></div>
    <div id="bestellingen" class="tab-content hidden"><div class="bg-white border border-gray-300 p-6"><p class="text-slate-500">Bestellingen module geladen...</p></div></div>

</div>

<div id="editReportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-lg border border-gray-400 shadow-2xl p-0">
        <div class="bg-slate-100 px-4 py-3 border-b border-gray-300 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-700 uppercase">Rapportage Bewerken</h3>
            <button onclick="closeEditModal()" class="text-slate-500 hover:text-red-600 font-bold">✕</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit_report">
            <input type="hidden" name="report_id" id="modal_report_id">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                <select name="report_type" id="modal_type" class="w-full p-2 border border-gray-300 text-sm"><option value="Algemeen">Algemeen</option><option value="Medisch">Medisch</option><option value="Incident">Incident</option></select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Stemming</label>
                <div class="flex gap-2">
                    <label class="border p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Blij" id="modal_mood_blij"> Blij</label>
                    <label class="border p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Rustig" id="modal_mood_rustig"> Rustig</label>
                    <label class="border p-2 flex-1 text-center cursor-pointer hover:bg-slate-50 text-sm"><input type="radio" name="mood" value="Pijn" id="modal_mood_pijn"> Pijn</label>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Inhoud</label>
                <textarea name="content" id="modal_content" rows="5" class="w-full p-2 border border-gray-300 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="bg-gray-200 text-slate-700 px-4 py-2 text-sm font-bold uppercase">Annuleren</button>
                <button type="submit" class="bg-blue-700 text-white px-4 py-2 text-sm font-bold uppercase hover:bg-blue-800">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-content').forEach(e => { e.classList.add('hidden'); e.classList.remove('block'); });
        document.querySelectorAll('.tab-btn').forEach(e => { e.classList.remove('active', 'border-blue-600', 'text-blue-600', 'font-bold'); e.classList.add('border-transparent', 'text-slate-500', 'font-medium'); });
        document.getElementById(tabId).classList.remove('hidden'); document.getElementById(tabId).classList.add('block');
        btn.classList.remove('border-transparent', 'text-slate-500', 'font-medium'); btn.classList.add('active', 'border-blue-600', 'text-blue-600', 'font-bold');
    }
    
    function openEditModal(report) {
        document.getElementById('editReportModal').classList.remove('hidden');
        document.getElementById('modal_report_id').value = report.id;
        document.getElementById('modal_content').value = report.content;
        document.getElementById('modal_type').value = report.report_type;
        // Radio buttons checken
        if(report.mood === 'Blij') document.getElementById('modal_mood_blij').checked = true;
        if(report.mood === 'Rustig') document.getElementById('modal_mood_rustig').checked = true;
        if(report.mood === 'Pijn') document.getElementById('modal_mood_pijn').checked = true;
    }

    function closeEditModal() {
        document.getElementById('editReportModal').classList.add('hidden');
    }

    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            const h = window.location.hash.substring(1);
            const b = document.querySelector(`button[onclick="switchTab('${h}', this)"]`);
            if(b) b.click();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>