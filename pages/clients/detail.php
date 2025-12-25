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

// 2. ACTIES VERWERKEN (Rapportages Bewerken/Verwijderen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RAPPORTAGE OPSLAAN (Nieuwe Chat/Notitie)
    if (isset($_POST['action']) && $_POST['action'] === 'add_report') {
        $content = $_POST['content'];
        $mood = $_POST['mood'];
        $type = $_POST['report_type'];
        $visible = isset($_POST['visible_to_family']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO client_reports (client_id, author_id, content, mood, report_type, visible_to_family) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $user_id, $content, $mood, $type, $visible]);
        header("Location: detail.php?id=$client_id"); // Blijf op homepage voor chat
        exit;
    }

    // RAPPORTAGE VERWIJDEREN
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'];
        $check = $pdo->prepare("SELECT author_id FROM client_reports WHERE id = ?");
        $check->execute([$report_id]);
        $r = $check->fetch();
        if ($r && ($r['author_id'] == $user_id || $user_role === 'management')) {
            $pdo->prepare("DELETE FROM client_reports WHERE id = ?")->execute([$report_id]);
            header("Location: detail.php?id=$client_id#rapportages");
            exit;
        }
    }

    // RAPPORTAGE BEWERKEN
    if (isset($_POST['action']) && $_POST['action'] === 'edit_report') {
        $report_id = $_POST['report_id'];
        $content = $_POST['content'];
        $mood = $_POST['mood'];
        $type = $_POST['report_type'];
        
        $check = $pdo->prepare("SELECT author_id FROM client_reports WHERE id = ?");
        $check->execute([$report_id]);
        $r = $check->fetch();
        if ($r && ($r['author_id'] == $user_id || $user_role === 'management')) {
            $pdo->prepare("UPDATE client_reports SET content = ?, mood = ?, report_type = ? WHERE id = ?")->execute([$content, $mood, $type, $report_id]);
            header("Location: detail.php?id=$client_id#rapportages");
            exit;
        }
    }
}

// 3. DATA OPHALEN
function calculateAge($dob) { return (new DateTime($dob))->diff(new DateTime('today'))->y; }

try {
    // Client Info
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if (!$client) die("Cliënt niet gevonden.");

    // Lijsten
    $allergies = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?");
    $allergies->execute([$client_id]);
    $allergies = $allergies->fetchAll(PDO::FETCH_COLUMN);

    $aids = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?");
    $aids->execute([$client_id]);
    $aids = $aids->fetchAll(PDO::FETCH_COLUMN);

    // Rapportages (Chat & Lijst)
    $rep_sql = "SELECT r.*, u.username, np.first_name, np.last_name 
                FROM client_reports r 
                JOIN users u ON r.author_id = u.id 
                LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                WHERE r.client_id = ?";
    if ($_SESSION['role'] === 'familie') { $rep_sql .= " AND r.visible_to_family = 1"; }
    $rep_sql .= " ORDER BY r.created_at DESC";
    $reports_stmt = $pdo->prepare($rep_sql);
    $reports_stmt->execute([$client_id]);
    $reports = $reports_stmt->fetchAll();

    // Zorgplan (Taken)
    $tasks_stmt = $pdo->prepare("SELECT * FROM client_care_tasks WHERE client_id = ? AND is_active = 1 ORDER BY FIELD(time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht'), title");
    $tasks_stmt->execute([$client_id]);
    $care_tasks = $tasks_stmt->fetchAll();

    // Medicatie
    $meds_stmt = $pdo->prepare("SELECT * FROM client_medications WHERE client_id = ? ORDER BY times");
    $meds_stmt->execute([$client_id]);
    $medications = $meds_stmt->fetchAll();

    // Overige (Metingen/Orders - kort gehouden voor deze view)
    // ... (code voor metingen/orders kan hier, maar focus ligt op de vraag)

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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
                        <div><span class="font-bold text-slate-800">ID:</span> <?php echo htmlspecialchars($client['id_number']); ?></div>
                        <div><span class="font-bold text-slate-800">Geb:</span> <?php echo date('d-m-Y', strtotime($client['dob'])); ?> (<?php echo calculateAge($client['dob']); ?>)</div>
                        <div><span class="font-bold text-slate-800">Locatie:</span> <?php echo htmlspecialchars($client['neighborhood']); ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($allergies as $a): ?><span class="px-2 py-0.5 bg-orange-50 text-orange-800 border border-orange-200 text-xs font-bold uppercase"><?php echo htmlspecialchars($a); ?></span><?php endforeach; ?>
                        <?php foreach($aids as $a): ?><span class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-xs font-bold uppercase"><?php echo htmlspecialchars($a); ?></span><?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php if($_SESSION['role'] === 'management'): ?>
                <a href="edit.php?id=<?php echo $client_id; ?>" class="bg-slate-50 border border-gray-300 text-slate-700 hover:text-blue-700 hover:bg-white px-4 py-2 text-sm font-bold uppercase transition-colors">Wijzigen</a>
            <?php endif; ?>
        </div>

        <div class="px-6 border-t border-gray-200 bg-slate-50 overflow-x-auto">
            <nav class="flex space-x-8 -mb-px" aria-label="Tabs">
                <button onclick="switchTab('homepage', this)" class="tab-btn active border-blue-600 text-blue-600 font-bold py-4 px-1 border-b-2 text-sm whitespace-nowrap">Homepage (Notities)</button>
                <button onclick="switchTab('zorgplan', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm whitespace-nowrap">Zorgplan</button>
                <button onclick="switchTab('rapportages', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm whitespace-nowrap">Rapportages (Beheer)</button>
                <button onclick="switchTab('medisch', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm whitespace-nowrap">Medisch & Metingen</button>
            </nav>
        </div>
    </div>

    <div id="homepage" class="tab-content block">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="space-y-6">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Woonsituatie</h3></div>
                    <div class="p-4 text-sm space-y-2">
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-slate-500">Adres</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['address']); ?></span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-slate-500">District</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['neighborhood'] . ', ' . $client['district']); ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Woning</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['housing_type']); ?> (<?php echo htmlspecialchars($client['floor_level']); ?>)</span></div>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Contactpersonen</h3></div>
                    <div class="p-4">
                        <div class="bg-blue-50 border border-blue-100 p-3 mb-3">
                            <span class="text-[10px] font-bold text-blue-800 uppercase block mb-1">Eerste Contact</span>
                            <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($client['contact1_name']); ?></div>
                            <div class="text-slate-600 text-xs mt-1"><?php echo htmlspecialchars($client['contact1_phone']); ?></div>
                        </div>
                        <?php if($client['notes']): ?>
                            <div class="bg-yellow-50 border border-yellow-100 p-3 text-sm text-slate-700 italic">
                                <span class="block text-[10px] font-bold text-yellow-800 not-italic uppercase mb-1">Bijzonderheden</span>
                                <?php echo nl2br(htmlspecialchars($client['notes'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-300 shadow-sm flex flex-col h-[600px]">
                <div class="bg-slate-800 text-white px-4 py-3 border-b border-slate-900 flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-wide">Notities & Communicatie</h3>
                    <span class="bg-slate-600 text-[10px] px-2 py-0.5 rounded text-white"><?php echo count($reports); ?> berichten</span>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50" id="chatContainer">
                    <?php if(count($reports) > 0): foreach($reports as $r): 
                        $is_me = ($r['author_id'] == $user_id);
                        $align = $is_me ? 'items-end' : 'items-start';
                        $bubble_bg = $is_me ? 'bg-blue-100 border-blue-200' : 'bg-white border-gray-300';
                        $author_name = $r['first_name'] ? $r['first_name'].' '.$r['last_name'] : $r['username'];
                    ?>
                        <div class="flex flex-col <?php echo $align; ?>">
                            <div class="flex items-end gap-2 <?php echo $is_me ? 'flex-row-reverse' : 'flex-row'; ?> max-w-[90%]">
                                <div class="w-8 h-8 bg-slate-300 border border-slate-400 flex items-center justify-center text-xs font-bold text-slate-700 shrink-0">
                                    <?php echo substr($author_name,0,1); ?>
                                </div>
                                <div class="<?php echo $bubble_bg; ?> border p-3 shadow-sm text-sm text-slate-800 relative">
                                    <div class="flex justify-between items-center gap-4 border-b border-black/5 pb-1 mb-1">
                                        <span class="text-[10px] font-bold uppercase text-slate-500"><?php echo htmlspecialchars($author_name); ?></span>
                                        <span class="text-[10px] text-slate-400"><?php echo date('d-m H:i', strtotime($r['created_at'])); ?></span>
                                    </div>
                                    <p class="leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($r['content']); ?></p>
                                    <div class="mt-1 flex gap-2">
                                        <span class="text-[9px] uppercase font-bold bg-black/5 px-1 text-slate-500"><?php echo $r['report_type']; ?></span>
                                        <span class="text-[9px] uppercase font-bold bg-black/5 px-1 text-slate-500"><?php echo $r['mood']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="text-center text-slate-400 italic mt-10">Nog geen notities. Begin het gesprek hieronder.</div>
                    <?php endif; ?>
                </div>

                <?php if($_SESSION['role'] !== 'familie'): // Pas aan als familie ook mag typen ?>
                <div class="p-3 bg-white border-t border-gray-300">
                    <form action="detail.php?id=<?php echo $client_id; ?>" method="POST">
                        <input type="hidden" name="action" value="add_report">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        <div class="flex gap-2 mb-2">
                            <select name="report_type" class="text-xs p-1 border border-gray-300 bg-slate-50"><option>Algemeen</option><option>Medisch</option><option>Incident</option></select>
                            <select name="mood" class="text-xs p-1 border border-gray-300 bg-slate-50"><option>Rustig</option><option>Blij</option><option>Pijn</option></select>
                            <label class="flex items-center text-xs text-slate-500 ml-auto"><input type="checkbox" name="visible_to_family" checked class="mr-1"> Fam?</label>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" name="content" placeholder="Schrijf een notitie..." class="flex-1 p-2 border border-gray-300 text-sm focus:border-blue-600 focus:ring-0" required autocomplete="off">
                            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 text-sm font-bold uppercase">Verstuur</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="zorgplan" class="tab-content hidden">
        <div class="bg-white border border-gray-300 shadow-sm">
            <div class="bg-slate-100 px-4 py-2 border-b border-gray-300 flex justify-between items-center">
                <h3 class="text-xs font-bold text-slate-700 uppercase">Geplande Zorgtaken</h3>
            </div>
            <div class="p-6">
                <?php if(count($care_tasks) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($care_tasks as $task): 
                            $jsonTask = htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div onclick='openTaskModal(<?php echo $jsonTask; ?>)' class="border border-gray-200 p-4 hover:bg-blue-50 hover:border-blue-300 cursor-pointer transition-colors group relative">
                                <div class="flex justify-between items-start">
                                    <h4 class="font-bold text-slate-800 text-sm group-hover:text-blue-800"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="text-[10px] font-bold uppercase bg-slate-100 text-slate-600 px-2 py-0.5 border border-slate-200"><?php echo htmlspecialchars($task['time_of_day']); ?></span>
                                </div>
                                <p class="text-xs text-slate-500 mt-2 truncate"><?php echo htmlspecialchars($task['description']); ?></p>
                                <div class="mt-3 flex items-center gap-2">
                                    <span class="text-[10px] text-slate-400 uppercase font-medium">Frequentie: <?php echo htmlspecialchars($task['frequency']); ?></span>
                                    <span class="text-blue-600 text-xs ml-auto group-hover:underline">Details &rarr;</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 italic text-center py-8">Geen zorgtaken ingesteld.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="rapportages" class="tab-content hidden">
        <div class="bg-white border border-gray-300 shadow-sm">
            <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Rapportage Beheer</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-gray-200 text-slate-500 uppercase text-xs">
                        <tr>
                            <th class="px-4 py-3">Datum</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Auteur</th>
                            <th class="px-4 py-3 w-1/2">Inhoud</th>
                            <th class="px-4 py-3 text-right">Actie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach($reports as $r): 
                            $can_edit = ($r['author_id'] == $user_id || $_SESSION['role'] === 'management');
                        ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600 whitespace-nowrap align-top"><?php echo date('d-m-Y H:i', strtotime($r['created_at'])); ?></td>
                            <td class="px-4 py-3 align-top"><span class="text-[10px] font-bold bg-gray-100 border border-gray-200 px-1 py-0.5 uppercase text-slate-600"><?php echo htmlspecialchars($r['report_type']); ?></span></td>
                            <td class="px-4 py-3 text-slate-600 align-top"><?php echo htmlspecialchars($r['first_name'] ?: $r['username']); ?></td>
                            <td class="px-4 py-3 text-slate-700 align-top"><?php echo htmlspecialchars($r['content']); ?></td>
                            <td class="px-4 py-3 text-right align-top">
                                <?php if($can_edit): ?>
                                    <button onclick='openEditModal(<?php echo json_encode($r); ?>)' class="text-blue-600 hover:underline text-xs mr-2">Wijzig</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Verwijderen?');">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:underline text-xs">Wis</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="medisch" class="tab-content hidden">
        <div class="bg-white border border-gray-300 p-6 shadow-sm mb-6">
            <h3 class="text-xs font-bold text-slate-700 uppercase border-b border-gray-200 pb-2 mb-4">Medicatie</h3>
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 border-b uppercase text-xs"><tr><th class="p-2">Naam</th><th class="p-2">Dosis</th><th class="p-2">Tijden</th><th class="p-2">Nota</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($medications as $m): ?>
                    <tr>
                        <td class="p-2 font-bold text-slate-700"><?php echo htmlspecialchars($m['name']); ?></td>
                        <td class="p-2 text-slate-600"><?php echo htmlspecialchars($m['dosage']); ?></td>
                        <td class="p-2"><span class="bg-blue-50 text-blue-800 border border-blue-200 px-1 py-0.5 text-xs font-bold"><?php echo htmlspecialchars($m['times']); ?></span></td>
                        <td class="p-2 text-slate-500 italic"><?php echo htmlspecialchars($m['notes']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-md border border-gray-400 shadow-2xl">
        <div class="bg-blue-700 text-white px-4 py-3 flex justify-between items-center">
            <h3 class="text-sm font-bold uppercase">Zorgtaak Details</h3>
            <button onclick="closeTaskModal()" class="text-white hover:text-red-200 font-bold">✕</button>
        </div>
        <div class="p-6">
            <h2 id="modalTaskTitle" class="text-xl font-bold text-slate-800 mb-2"></h2>
            <div class="space-y-3 text-sm text-slate-600">
                <div><span class="font-bold text-slate-500 block text-xs uppercase">Beschrijving</span><p id="modalTaskDesc" class="bg-slate-50 p-2 border border-slate-200 text-slate-800"></p></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><span class="font-bold text-slate-500 block text-xs uppercase">Dagdeel</span><span id="modalTaskTime" class="font-medium text-blue-700"></span></div>
                    <div><span class="font-bold text-slate-500 block text-xs uppercase">Frequentie</span><span id="modalTaskFreq" class="font-medium"></span></div>
                </div>
                <div><span class="font-bold text-slate-500 block text-xs uppercase">Dagen</span><span id="modalTaskDays" class="font-medium"></span></div>
            </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 border-t border-gray-200 text-right">
            <button onclick="closeTaskModal()" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 text-xs font-bold uppercase">Sluiten</button>
        </div>
    </div>
</div>

<div id="editReportModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-lg border border-gray-400 shadow-2xl">
        <div class="bg-slate-100 px-4 py-3 border-b border-gray-300 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-700 uppercase">Rapportage Bewerken</h3>
            <button onclick="document.getElementById('editReportModal').classList.add('hidden')" class="text-slate-500 hover:text-red-600 font-bold">✕</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit_report">
            <input type="hidden" name="report_id" id="edit_report_id">
            <div class="mb-3">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                <select name="report_type" id="edit_report_type" class="w-full p-2 border border-gray-300 text-sm"><option>Algemeen</option><option>Medisch</option><option>Incident</option></select>
            </div>
            <div class="mb-3">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Stemming</label>
                <select name="mood" id="edit_report_mood" class="w-full p-2 border border-gray-300 text-sm"><option>Blij</option><option>Rustig</option><option>Pijn</option></select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Inhoud</label>
                <textarea name="content" id="edit_report_content" rows="4" class="w-full p-2 border border-gray-300 text-sm"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('editReportModal').classList.add('hidden')" class="bg-gray-200 text-slate-700 px-4 py-2 text-xs font-bold uppercase">Annuleren</button>
                <button type="submit" class="bg-blue-700 text-white px-4 py-2 text-xs font-bold uppercase hover:bg-blue-800">Opslaan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(id, btn) {
        document.querySelectorAll('.tab-content').forEach(el => { el.classList.add('hidden'); el.classList.remove('block'); });
        document.querySelectorAll('.tab-btn').forEach(el => { el.classList.remove('active', 'border-blue-600', 'text-blue-600', 'font-bold'); el.classList.add('border-transparent', 'text-slate-500', 'font-medium'); });
        document.getElementById(id).classList.remove('hidden'); document.getElementById(id).classList.add('block');
        btn.classList.remove('border-transparent', 'text-slate-500', 'font-medium'); btn.classList.add('active', 'border-blue-600', 'text-blue-600', 'font-bold');
    }

    function openTaskModal(task) {
        document.getElementById('taskModal').classList.remove('hidden');
        document.getElementById('modalTaskTitle').innerText = task.title;
        document.getElementById('modalTaskDesc').innerText = task.description || 'Geen beschrijving';
        document.getElementById('modalTaskTime').innerText = task.time_of_day;
        document.getElementById('modalTaskFreq').innerText = task.frequency;
        document.getElementById('modalTaskDays').innerText = task.specific_days || 'Alle dagen';
    }
    function closeTaskModal() { document.getElementById('taskModal').classList.add('hidden'); }

    function openEditModal(report) {
        document.getElementById('editReportModal').classList.remove('hidden');
        document.getElementById('edit_report_id').value = report.id;
        document.getElementById('edit_report_content').value = report.content;
        document.getElementById('edit_report_type').value = report.report_type;
        document.getElementById('edit_report_mood').value = report.mood;
    }

    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            const h = window.location.hash.substring(1);
            const b = document.querySelector(`button[onclick="switchTab('${h}', this)"]`);
            if(b) b.click();
        }
        // Auto scroll chat to bottom
        const chat = document.getElementById('chatContainer');
        if(chat) chat.scrollTop = chat.scrollHeight;
    });
</script>

<?php include '../../includes/footer.php'; ?>