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

    // D. ZORGPLAN & BIBLIOTHEKEN
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

    // E. METINGEN
    $obs_stmt = $pdo->prepare("SELECT do.*, u.username, np.first_name 
                               FROM daily_observations do
                               JOIN users u ON do.nurse_id = u.id
                               LEFT JOIN nurse_profiles np ON u.id = np.user_id
                               WHERE do.client_id = ? 
                               ORDER BY do.observation_time DESC LIMIT 50");
    $obs_stmt->execute([$client_id]);
    $observations = $obs_stmt->fetchAll();

    // F. BESTELLINGEN
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

<div class="w-full max-w-7xl mx-auto mb-20">

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

        <div class="px-6 border-t border-gray-200 bg-slate-50">
            <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                <button onclick="switchTab('overzicht', this)" class="tab-btn active border-blue-600 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-bold text-sm">
                    Stamkaart
                </button>
                <button onclick="switchTab('rapportages', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Rapportages
                </button>
                <button onclick="switchTab('zorgplan', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Zorgplan
                </button>
                <button onclick="switchTab('metingen', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Metingen
                </button>
                <button onclick="switchTab('bestellingen', this)" class="tab-btn border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Materiaal
                </button>
            </nav>
        </div>
    </div>

    <div id="overzicht" class="tab-content block">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-4 py-2 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase">Woonsituatie & Adres</h3>
                </div>
                <div class="p-4 space-y-3 text-sm">
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2">
                        <span class="text-slate-500">Adres</span>
                        <span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['address']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2">
                        <span class="text-slate-500">Wijk/District</span>
                        <span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['neighborhood'] . ', ' . $client['district']); ?></span>
                    </div>
                    <div class="grid grid-cols-3 border-b border-gray-100 pb-2">
                        <span class="text-slate-500">Woning</span>
                        <span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['housing_type']); ?> (<?php echo htmlspecialchars($client['floor_level']); ?>)</span>
                    </div>
                    <div class="grid grid-cols-3">
                        <span class="text-slate-500">Parkeren</span>
                        <span class="col-span-2 font-medium text-slate-800"><?php echo htmlspecialchars($client['parking_info']); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-4 py-2 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase">Contactpersonen</h3>
                </div>
                <div class="p-4 space-y-4">
                    <div class="bg-blue-50 border border-blue-100 p-3">
                        <span class="text-[10px] font-bold text-blue-700 uppercase block mb-1">Eerste Contact</span>
                        <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($client['contact1_name']); ?></div>
                        <div class="text-slate-600 text-sm flex items-center mt-1">
                            📞 <?php echo htmlspecialchars($client['contact1_phone']); ?>
                        </div>
                    </div>
                    
                    <?php if(!empty($client['notes'])): ?>
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase mb-1 block">Bijzonderheden</span>
                        <div class="text-sm bg-yellow-50 text-slate-700 p-3 border border-yellow-200">
                            <?php echo nl2br(htmlspecialchars($client['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
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
                    ?>
                    <div class="bg-white border border-gray-300 p-4 <?php echo $border; ?> shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-bold text-slate-800 text-sm block"><?php echo $report['report_type']; ?></span>
                                <span class="text-xs text-slate-400"><?php echo date('d-m-Y H:i', strtotime($report['created_at'])); ?> • <?php echo htmlspecialchars($report['first_name'] ?: $report['username']); ?></span>
                            </div>
                            <span class="text-xs font-bold uppercase bg-slate-100 px-2 py-1 text-slate-600 border border-gray-200"><?php echo $report['mood']; ?></span>
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
            <div class="bg-slate-100 px-4 py-2 border-b border-gray-300 flex justify-between items-center">
                <h3 class="text-xs font-bold text-slate-700 uppercase">Medicatie (Toedienlijst)</h3>
            </div>
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
                            <label><input type="checkbox" name="days[]" value="Ma"> Ma</label>
                            <label><input type="checkbox" name="days[]" value="Di"> Di</label>
                            <label><input type="checkbox" name="days[]" value="Wo"> Wo</label>
                            <label><input type="checkbox" name="days[]" value="Do"> Do</label>
                            <label><input type="checkbox" name="days[]" value="Vr"> Vr</label>
                            <label><input type="checkbox" name="days[]" value="Za"> Za</label>
                            <label><input type="checkbox" name="days[]" value="Zo"> Zo</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase">Opslaan</button>
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
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300">
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Actueel Zorgplan</h3>
                    </div>
                    <div class="p-4">
                        <?php if(count($care_plan) > 0): 
                            $curr = ''; 
                            foreach($care_plan as $t): 
                                if($t['time_of_day'] != $curr): $curr = $t['time_of_day']; 
                                    echo "<h4 class='mt-4 mb-2 font-bold text-slate-400 text-xs uppercase border-b border-gray-100'>$curr</h4>"; 
                                endif;
                        ?>
                            <div class="bg-white border border-gray-200 p-3 mb-2 hover:bg-slate-50 transition-colors">
                                <div class="flex justify-between items-start">
                                    <h5 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($t['title']); ?></h5>
                                    <span class="text-[10px] bg-blue-50 text-blue-800 px-1.5 py-0.5 border border-blue-200 uppercase font-bold"><?php echo htmlspecialchars($t['frequency']); ?></span>
                                </div>
                                <p class="text-xs text-slate-600 mt-1"><?php echo htmlspecialchars($t['description']); ?></p>
                            </div>
                        <?php endforeach; else: echo "<p class='text-slate-500 italic text-sm'>Geen taken ingepland.</p>"; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="metingen" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Dagstaat Invoeren</h3>
                    <form action="save_observation.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bloeddruk</label>
                        <div class="flex gap-2 mb-3">
                            <input type="number" name="bp_systolic" class="w-1/2 p-2 border border-gray-300 text-sm" placeholder="Sys">
                            <input type="number" name="bp_diastolic" class="w-1/2 p-2 border border-gray-300 text-sm" placeholder="Dia">
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <select name="eating" class="p-2 border border-gray-300 text-sm"><option value="Goed">Eten: Goed</option><option value="Slecht">Eten: Slecht</option></select>
                            <select name="drinking" class="p-2 border border-gray-300 text-sm"><option value="Goed">Drink: Goed</option><option value="Slecht">Drink: Slecht</option></select>
                        </div>
                        <div class="mb-3"><select name="defecation" class="w-full p-2 border border-gray-300 text-sm"><option value="Geen">Ontlasting: Geen</option><option value="Normaal">Normaal</option><option value="Obstipatie">Obstipatie</option></select></div>
                        <textarea name="general_impression" rows="2" class="w-full p-2 border border-gray-300 text-sm mb-3" placeholder="Algemene indruk"></textarea>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase">Opslaan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300">
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Geschiedenis Metingen</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-left">
                            <thead class="bg-slate-50 text-slate-500 border-b border-gray-200 uppercase text-xs">
                                <tr><th class="px-4 py-2">Tijd</th><th class="px-4 py-2">RR (Bloeddruk)</th><th class="px-4 py-2">Eten</th><th class="px-4 py-2">Indruk</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($observations as $obs): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2 text-slate-600 whitespace-nowrap"><?php echo date('d-m H:i', strtotime($obs['observation_time'])); ?></td>
                                    <td class="px-4 py-2 font-mono text-blue-800 font-bold"><?php echo $obs['bp_systolic'] ? $obs['bp_systolic'].'/'.$obs['bp_diastolic'] : '-'; ?></td>
                                    <td class="px-4 py-2 text-slate-700"><?php echo htmlspecialchars($obs['eating']); ?></td>
                                    <td class="px-4 py-2 text-slate-600 truncate max-w-xs"><?php echo htmlspecialchars($obs['general_impression']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="bestellingen" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <?php if($_SESSION['role'] !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Materiaal Aanvragen</h3>
                    <form action="save_order.php" method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Product</label>
                            <select name="product_id" class="w-full p-2 border border-gray-300 text-sm bg-slate-50" required>
                                <option value="">-- Kies product --</option>
                                <?php foreach($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>"><?php echo htmlspecialchars($prod['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Aantal</label>
                            <input type="number" name="quantity" value="1" min="1" class="w-full p-2 border border-gray-300 text-sm" required>
                        </div>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase">Aanvragen</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="<?php echo ($_SESSION['role'] === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300">
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Bestelgeschiedenis</h3>
                    </div>
                    <div class="p-4 space-y-3">
                        <?php if(count($orders) > 0): foreach($orders as $ord): 
                             $status_style = 'bg-yellow-50 text-yellow-800 border-yellow-200';
                             if($ord['status'] == 'goedgekeurd') $status_style = 'bg-green-50 text-green-800 border-green-200';
                             if($ord['status'] == 'geweigerd') $status_style = 'bg-red-50 text-red-800 border-red-200';
                        ?>
                        <div class="bg-white border border-gray-200 p-3 flex justify-between items-center hover:bg-slate-50">
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($ord['product_name']); ?> <span class="text-slate-500 font-normal">x<?php echo $ord['quantity']; ?></span></h4>
                                <p class="text-xs text-slate-400">Aangevraagd: <?php echo date('d-m-Y', strtotime($ord['order_date'])); ?> door <?php echo htmlspecialchars($ord['first_name']); ?></p>
                            </div>
                            <span class="px-2 py-1 text-[10px] font-bold uppercase border <?php echo $status_style; ?>"><?php echo $ord['status']; ?></span>
                        </div>
                        <?php endforeach; else: ?>
                            <p class="text-slate-500 italic text-sm">Nog geen bestellingen.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    function switchTab(tabId, btnElement) {
        // 1. Verberg alle content
        document.querySelectorAll('.tab-content').forEach(function(el) {
            el.classList.add('hidden');
            el.classList.remove('block');
        });

        // 2. Maak alle knoppen inactief (Grijze tekst, transparante border)
        document.querySelectorAll('.tab-btn').forEach(function(el) {
            el.classList.remove('active', 'border-blue-600', 'text-blue-600', 'font-bold');
            el.classList.add('border-transparent', 'text-slate-500', 'font-medium');
        });

        // 3. Toon gekozen content
        const activeContent = document.getElementById(tabId);
        if(activeContent) {
            activeContent.classList.remove('hidden');
            activeContent.classList.add('block');
        }

        // 4. Maak gekozen knop actief (Blauwe tekst, blauwe border)
        btnElement.classList.remove('border-transparent', 'text-slate-500', 'font-medium');
        btnElement.classList.add('active', 'border-blue-600', 'text-blue-600', 'font-bold');
    }

    // Bij laden pagina: Check of er een hash is (bv #zorgplan) en open die tab
    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            const hash = window.location.hash.substring(1); // haal # weg
            const targetBtn = document.querySelector(`button[onclick="switchTab('${hash}', this)"]`);
            if(targetBtn) {
                targetBtn.click();
            }
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>