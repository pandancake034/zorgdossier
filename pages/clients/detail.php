<?php
// pages/clients/detail.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// 2. ACTIES VERWERKEN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. NIEUWE NOTITIE (CHAT)
    if (isset($_POST['action']) && $_POST['action'] === 'add_note') {
        $message = trim($_POST['message']);
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO client_notes (client_id, author_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$client_id, $user_id, $message]);
        }
        header("Location: detail.php?id=$client_id"); 
        exit;
    }

    // B. RAPPORTAGE TOEVOEGEN
    if (isset($_POST['action']) && $_POST['action'] === 'add_report') {
        $content = $_POST['content'];
        $mood = $_POST['mood'];
        $type = $_POST['report_type'];
        $visible = isset($_POST['visible_to_family']) ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO client_reports (client_id, author_id, content, mood, report_type, visible_to_family) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$client_id, $user_id, $content, $mood, $type, $visible]);
        header("Location: detail.php?id=$client_id#rapportages");
        exit;
    }

    // C. RAPPORTAGE VERWIJDEREN
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'];
        $chk = $pdo->prepare("SELECT author_id FROM client_reports WHERE id=?"); $chk->execute([$report_id]); $r=$chk->fetch();
        if ($r && ($r['author_id'] == $user_id || $user_role === 'management')) {
            $pdo->prepare("DELETE FROM client_reports WHERE id=?")->execute([$report_id]);
        }
        header("Location: detail.php?id=$client_id#rapportages");
        exit;
    }

    // D. RAPPORTAGE BEWERKEN
    if (isset($_POST['action']) && $_POST['action'] === 'edit_report') {
        $report_id = $_POST['report_id'];
        $content = $_POST['content'];
        $mood = $_POST['mood'];
        $type = $_POST['report_type'];
        
        $chk = $pdo->prepare("SELECT author_id FROM client_reports WHERE id=?"); $chk->execute([$report_id]); $r=$chk->fetch();
        if ($r && ($r['author_id'] == $user_id || $user_role === 'management')) {
            $pdo->prepare("UPDATE client_reports SET content=?, mood=?, report_type=? WHERE id=?")->execute([$content, $mood, $type, $report_id]);
        }
        header("Location: detail.php?id=$client_id#rapportages");
        exit;
    }
}

// Helper
function calculateAge($dob) { return (new DateTime($dob))->diff(new DateTime('today'))->y; }

try {
    // Client Stamgegevens
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if (!$client) die("Cliënt niet gevonden.");

    // Lijsten
    $allergies = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?"); $allergies->execute([$client_id]); $allergies = $allergies->fetchAll(PDO::FETCH_COLUMN);
    $aids = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?"); $aids->execute([$client_id]); $aids = $aids->fetchAll(PDO::FETCH_COLUMN);

    // 1. NOTITIES (CHAT)
    $note_sql = "SELECT n.*, u.username, np.first_name, np.last_name 
                 FROM client_notes n 
                 JOIN users u ON n.author_id = u.id 
                 LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                 WHERE n.client_id = ? 
                 ORDER BY n.created_at ASC"; 
    $stmt_notes = $pdo->prepare($note_sql);
    $stmt_notes->execute([$client_id]);
    $chat_notes = $stmt_notes->fetchAll();

    // 2. RAPPORTAGES
    $rep_sql = "SELECT r.*, u.username, np.first_name, np.last_name 
                FROM client_reports r 
                JOIN users u ON r.author_id = u.id 
                LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                WHERE r.client_id = ?";
    if ($user_role === 'familie') { $rep_sql .= " AND r.visible_to_family = 1"; }
    $rep_sql .= " ORDER BY r.created_at DESC";
    $stmt_rep = $pdo->prepare($rep_sql);
    $stmt_rep->execute([$client_id]);
    $reports = $stmt_rep->fetchAll();

    // 3. ZORGPLAN
    $task_sql = "SELECT * FROM client_care_tasks WHERE client_id = ? AND is_active = 1 
                 ORDER BY FIELD(time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht', 'Hele dag'), title";
    $stmt_task = $pdo->prepare($task_sql);
    $stmt_task->execute([$client_id]);
    $care_tasks = $stmt_task->fetchAll();

    // 4. MEDICATIE & BIBLIOTHEEK
    $med_library = $pdo->query("SELECT name, standard_dosage FROM medication_library ORDER BY name")->fetchAll();
    
    $med_stmt = $pdo->prepare("SELECT * FROM client_medications WHERE client_id = ? ORDER BY times");
    $med_stmt->execute([$client_id]);
    $medications = $med_stmt->fetchAll();

    // 5. ZORGTAKEN BIBLIOTHEEK (voor modal/edit opties later)
    $library_tasks = $pdo->query("SELECT * FROM task_library ORDER BY category, title")->fetchAll();

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
                        <div><span class="font-bold text-slate-800">ID:</span> <?php echo htmlspecialchars($client['id_number']); ?></div>
                        <div><span class="font-bold text-slate-800">Geb:</span> <?php echo date('d-m-Y', strtotime($client['dob'])); ?> (<?php echo calculateAge($client['dob']); ?>jr)</div>
                        <div><span class="font-bold text-slate-800">Wijk:</span> <?php echo htmlspecialchars($client['neighborhood']); ?></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php if($client['diabetes_type'] !== 'Geen'): ?><span class="px-2 py-0.5 bg-red-50 text-red-800 border border-red-200 text-xs font-bold uppercase">Diabetes <?php echo htmlspecialchars($client['diabetes_type']); ?></span><?php endif; ?>
                        <?php foreach($allergies as $a): ?><span class="px-2 py-0.5 bg-orange-50 text-orange-800 border border-orange-200 text-xs font-bold uppercase"><?php echo htmlspecialchars($a); ?></span><?php endforeach; ?>
                        <?php foreach($aids as $a): ?><span class="px-2 py-0.5 bg-blue-50 text-blue-800 border border-blue-200 text-xs font-bold uppercase"><?php echo htmlspecialchars($a); ?></span><?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php if($user_role === 'management'): ?>
                <a href="edit.php?id=<?php echo $client_id; ?>" class="bg-slate-50 border border-gray-300 text-slate-700 hover:text-blue-700 hover:bg-white px-4 py-2 text-sm font-bold uppercase transition-colors">Wijzigen</a>
            <?php endif; ?>
        </div>

        <div class="px-6 border-t border-gray-200 bg-slate-50 overflow-x-auto">
            <nav class="flex space-x-8 -mb-px min-w-max" aria-label="Tabs">
                <button onclick="switchTab('homepage', this)" class="tab-btn active border-blue-600 text-blue-600 font-bold py-4 px-1 border-b-2 text-sm uppercase tracking-wide">Stamkaart & Notities</button>
                <button onclick="switchTab('zorgplan', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm uppercase tracking-wide">Zorgplan</button>
                <button onclick="switchTab('rapportages', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm uppercase tracking-wide">Rapportages</button>
                <button onclick="switchTab('medisch', this)" class="tab-btn border-transparent text-slate-500 font-medium hover:text-slate-700 py-4 px-1 border-b-2 text-sm uppercase tracking-wide">Medisch</button>
            </nav>
        </div>
    </div>

    <div id="homepage" class="tab-content block">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <div class="space-y-6">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Adres & Woonsituatie</h3></div>
                    <div class="p-4 text-sm space-y-2">
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-slate-500">Adres</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['address']); ?></span></div>
                        <div class="flex justify-between border-b border-gray-100 pb-2"><span class="text-slate-500">District</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['neighborhood'] . ', ' . $client['district']); ?></span></div>
                        <div class="flex justify-between"><span class="text-slate-500">Woning</span><span class="font-medium text-slate-800"><?php echo htmlspecialchars($client['housing_type']); ?> (<?php echo htmlspecialchars($client['floor_level']); ?>)</span></div>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Contact</h3></div>
                    <div class="p-4">
                        <div class="bg-blue-50 border border-blue-100 p-3 mb-3">
                            <span class="text-[10px] font-bold text-blue-800 uppercase block mb-1">Eerste Contactpersoon</span>
                            <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($client['contact1_name']); ?></div>
                            <div class="text-slate-600 text-xs mt-1">Tel: <?php echo htmlspecialchars($client['contact1_phone']); ?></div>
                        </div>
                        <?php if($client['notes']): ?>
                            <div class="bg-yellow-50 border border-yellow-100 p-3 text-sm text-slate-700">
                                <span class="block text-[10px] font-bold text-yellow-800 uppercase mb-1">Bijzonderheden</span>
                                <span class="italic"><?php echo nl2br(htmlspecialchars($client['notes'])); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-300 shadow-sm flex flex-col h-[600px]">
                <div class="bg-slate-800 text-white px-4 py-3 border-b border-slate-900 flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-wide">Communicatie Logboek</h3>
                    <span class="bg-slate-700 text-[10px] px-2 py-0.5 border border-slate-600"><?php echo count($chat_notes); ?></span>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50" id="chatContainer">
                    <?php if(count($chat_notes) > 0): foreach($chat_notes as $note): 
                        $is_me = ($note['author_id'] == $user_id);
                        $align = $is_me ? 'items-end' : 'items-start';
                        $bubble_style = $is_me ? 'bg-blue-100 border-blue-200 text-slate-800' : 'bg-white border-gray-300 text-slate-700';
                        $author_name = $note['first_name'] ? $note['first_name'] : $note['username'];
                    ?>
                        <div class="flex flex-col <?php echo $align; ?>">
                            <div class="flex items-end gap-2 <?php echo $is_me ? 'flex-row-reverse' : 'flex-row'; ?> max-w-[85%]">
                                <div class="w-8 h-8 bg-slate-300 border border-slate-400 flex items-center justify-center text-xs font-bold text-slate-600 shrink-0">
                                    <?php echo substr($author_name,0,1); ?>
                                </div>
                                <div class="border p-3 shadow-sm text-sm relative <?php echo $bubble_style; ?>">
                                    <div class="flex justify-between items-center gap-4 border-b border-black/5 pb-1 mb-1">
                                        <span class="text-[10px] font-bold uppercase opacity-70"><?php echo htmlspecialchars($author_name); ?></span>
                                        <span class="text-[10px] opacity-50"><?php echo date('d-m H:i', strtotime($note['created_at'])); ?></span>
                                    </div>
                                    <p class="leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($note['message']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="text-center text-slate-400 italic mt-10 text-sm">Nog geen notities. Plaats een bericht hieronder.</div>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-white border-t border-gray-300">
                    <form action="detail.php?id=<?php echo $client_id; ?>" method="POST" class="flex gap-2">
                        <input type="hidden" name="action" value="add_note">
                        <input type="text" name="message" placeholder="Typ een notitie..." class="flex-1 p-2 border border-gray-300 text-sm focus:border-blue-600 focus:ring-0" required autocomplete="off">
                        <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white px-4 py-2 text-sm font-bold uppercase">Verstuur</button>
                    </form>
                </div>
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
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($care_tasks as $task): $taskJson = htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8'); ?>
                            <div onclick='openTaskModal(<?php echo $taskJson; ?>)' class="border border-gray-200 p-4 hover:bg-blue-50 hover:border-blue-300 cursor-pointer transition-all group relative bg-white">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-bold text-slate-800 text-sm group-hover:text-blue-800"><?php echo htmlspecialchars($task['title']); ?></h4>
                                    <span class="text-[10px] font-bold uppercase bg-slate-100 text-slate-600 px-2 py-0.5 border border-slate-200"><?php echo htmlspecialchars($task['time_of_day']); ?></span>
                                </div>
                                <p class="text-xs text-slate-500 truncate"><?php echo htmlspecialchars($task['description']); ?></p>
                                <div class="mt-3 pt-2 border-t border-gray-100 flex justify-between items-center">
                                    <span class="text-[10px] text-slate-400 font-bold uppercase"><?php echo htmlspecialchars($task['frequency']); ?></span>
                                    <span class="text-blue-600 text-xs font-bold group-hover:underline">Details &rarr;</span>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <?php if($user_role !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Nieuwe Rapportage</h3>
                    <form action="detail.php?id=<?php echo $client_id; ?>" method="POST">
                        <input type="hidden" name="action" value="add_report">
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                            <select name="report_type" class="w-full p-2 border border-gray-300 text-sm bg-slate-50 focus:bg-white"><option>Algemeen</option><option>Medisch</option><option>Incident</option></select>
                        </div>
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2 flex justify-between">Stemming <span id="new_mood_display" class="text-blue-700 font-bold">Rustig</span></label>
                            <input type="range" min="0" max="2" value="1" class="w-full h-2 bg-slate-200 rounded-none appearance-none cursor-pointer" oninput="updateMood(this.value, 'new_mood_display', 'new_mood_input')">
                            <input type="hidden" name="mood" id="new_mood_input" value="Rustig">
                            <div class="flex justify-between text-[9px] text-slate-400 uppercase font-bold mt-2"><span>Pijn</span><span>Rustig</span><span>Blij</span></div>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Inhoud</label>
                            <textarea name="content" rows="4" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="Typ rapportage..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="inline-flex items-center text-sm text-slate-600"><input type="checkbox" name="visible_to_family" checked class="text-blue-600 border-gray-300 focus:ring-0 mr-2">Zichtbaar voor familie</label>
                        </div>
                        <button type="submit" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-sm uppercase">Opslaan</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="<?php echo ($user_role === 'familie') ? 'lg:col-span-3' : 'lg:col-span-2'; ?>">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Geschiedenis</h3></div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b border-gray-200 text-slate-500 uppercase text-xs">
                                <tr><th class="px-4 py-3">Datum</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Auteur</th><th class="px-4 py-3 w-1/2">Inhoud</th><th class="px-4 py-3 text-right">Actie</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($reports as $r): $can_edit = ($r['author_id'] == $user_id || $user_role === 'management'); $rJson = htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap align-top"><?php echo date('d-m-Y H:i', strtotime($r['created_at'])); ?></td>
                                    <td class="px-4 py-3 align-top"><span class="text-[10px] font-bold bg-gray-100 border border-gray-200 px-1 py-0.5 uppercase text-slate-600"><?php echo htmlspecialchars($r['report_type']); ?></span></td>
                                    <td class="px-4 py-3 text-slate-600 align-top text-xs"><?php echo htmlspecialchars($r['first_name'] ?: $r['username']); ?></td>
                                    <td class="px-4 py-3 text-slate-700 align-top">
                                        <div class="mb-1 text-xs font-bold text-blue-700 uppercase">Stemming: <?php echo htmlspecialchars($r['mood']); ?></div>
                                        <?php echo nl2br(htmlspecialchars($r['content'])); ?>
                                    </td>
                                    <td class="px-4 py-3 text-right align-top">
                                        <?php if($can_edit): ?>
                                            <button onclick='openEditModal(<?php echo $rJson; ?>)' class="text-blue-600 hover:underline text-xs mr-2 font-bold uppercase">Bewerk</button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Verwijderen?');">
                                                <input type="hidden" name="action" value="delete_report">
                                                <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" class="text-red-600 hover:underline text-xs font-bold uppercase">Wis</button>
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
        </div>
    </div>

    <div id="medisch" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 bg-white border border-gray-300 shadow-sm">
                <div class="bg-slate-100 px-4 py-2 border-b border-gray-300"><h3 class="text-xs font-bold text-slate-700 uppercase">Medicatie Overzicht</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-500 border-b uppercase text-xs">
                            <tr><th class="p-3">Naam</th><th class="p-3">Dosis</th><th class="p-3">Tijden</th><th class="p-3">Nota</th><th class="p-3"></th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($medications as $m): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-3 font-bold text-slate-700"><?php echo htmlspecialchars($m['name']); ?></td>
                                <td class="p-3 text-slate-600"><?php echo htmlspecialchars($m['dosage']); ?></td>
                                <td class="p-3"><span class="bg-blue-50 text-blue-800 border border-blue-200 px-2 py-0.5 text-xs font-bold"><?php echo htmlspecialchars($m['times']); ?></span></td>
                                <td class="p-3 text-slate-500 italic"><?php echo htmlspecialchars($m['notes']); ?></td>
                                <td class="p-3 text-right">
                                    <?php if($user_role !== 'familie'): ?>
                                        <a href="save_medication.php?action=delete&delete_id=<?php echo $m['id']; ?>&client_id=<?php echo $client_id; ?>" class="text-red-400 hover:text-red-600 font-bold" onclick="return confirm('Verwijderen?');">✕</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if($user_role !== 'familie'): ?>
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm p-4 sticky top-4">
                    <h3 class="text-xs font-bold text-slate-700 uppercase mb-4 border-b border-gray-200 pb-2">Medicijn Toevoegen</h3>
                    <form action="save_medication.php" method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        
                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Medicijn</label>
                            <input type="text" list="medList" name="name" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-600 focus:ring-0" placeholder="Zoek of typ..." required>
                            <datalist id="medList">
                                <?php foreach($med_library as $ml): ?><option value="<?php echo htmlspecialchars($ml['name']); ?>"><?php endforeach; ?>
                            </datalist>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Sterkte</label>
                                <input type="text" name="dosage" class="w-full p-2 border border-gray-300 text-sm" placeholder="bv. 500mg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Frequentie</label>
                                <input type="text" name="frequency" class="w-full p-2 border border-gray-300 text-sm" placeholder="bv. 3x daags">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tijdstippen</label>
                            <input type="text" name="times" class="w-full p-2 border border-gray-300 text-sm" placeholder="bv. 08:00, 14:00, 20:00">
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Bijzonderheden</label>
                            <input type="text" name="notes" class="w-full p-2 border border-gray-300 text-sm" placeholder="bv. Met eten innemen">
                        </div>

                        <button type="submit" class="w-full bg-slate-700 hover:bg-slate-800 text-white font-bold py-2 text-sm uppercase">Toevoegen</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-md border border-slate-500 shadow-2xl">
        <div class="bg-slate-800 text-white px-4 py-3 flex justify-between items-center border-b border-slate-900">
            <h3 class="text-sm font-bold uppercase tracking-wide">Zorgtaak Details</h3>
            <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="text-white hover:text-red-300 font-bold">✕</button>
        </div>
        <div class="p-6 space-y-4">
            <div><h2 id="mTaskTitle" class="text-xl font-bold text-slate-800 mb-1"></h2><div class="h-1 w-10 bg-blue-600"></div></div>
            <div class="bg-slate-50 p-3 border border-slate-200 text-sm text-slate-700 leading-relaxed" id="mTaskDesc"></div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Dagdeel</span><span id="mTaskTime" class="font-bold text-slate-700"></span></div>
                <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Frequentie</span><span id="mTaskFreq" class="font-bold text-slate-700"></span></div>
            </div>
            <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Dagen</span><span id="mTaskDays" class="text-sm font-medium text-blue-700"></span></div>
        </div>
        <div class="bg-slate-100 px-4 py-3 border-t border-gray-200 text-right">
            <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="bg-slate-700 hover:bg-slate-800 text-white px-4 py-2 text-xs font-bold uppercase">Sluiten</button>
        </div>
    </div>
</div>

<div id="editReportModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50">
    <div class="bg-white w-full max-w-lg border border-slate-500 shadow-2xl">
        <div class="bg-slate-100 px-4 py-3 border-b border-gray-300 flex justify-between items-center">
            <h3 class="text-sm font-bold text-slate-700 uppercase">Rapportage Bewerken</h3>
            <button onclick="document.getElementById('editReportModal').classList.add('hidden')" class="text-slate-500 hover:text-red-600 font-bold">✕</button>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" value="edit_report">
            <input type="hidden" name="report_id" id="e_id">
            
            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type</label>
                <select name="report_type" id="e_type" class="w-full p-2 border border-gray-300 text-sm"><option>Algemeen</option><option>Medisch</option><option>Incident</option></select>
            </div>
            
            <div class="mb-6">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-2 flex justify-between">Stemming <span id="edit_mood_display" class="text-blue-700 font-bold">Rustig</span></label>
                <input type="range" min="0" max="2" value="1" id="edit_mood_range" class="w-full h-2 bg-slate-200 rounded-none appearance-none cursor-pointer" oninput="updateMood(this.value, 'edit_mood_display', 'edit_mood_input')">
                <input type="hidden" name="mood" id="edit_mood_input">
                <div class="flex justify-between text-[9px] text-slate-400 uppercase font-bold mt-2"><span>Pijn</span><span>Rustig</span><span>Blij</span></div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Inhoud</label>
                <textarea name="content" id="e_content" rows="5" class="w-full p-2 border border-gray-300 text-sm"></textarea>
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
        if(id === 'homepage') { const c = document.getElementById('chatContainer'); if(c) c.scrollTop = c.scrollHeight; }
    }

    function openTaskModal(task) {
        document.getElementById('taskModal').classList.remove('hidden');
        document.getElementById('mTaskTitle').innerText = task.title;
        document.getElementById('mTaskDesc').innerText = task.description || 'Geen beschrijving beschikbaar.';
        document.getElementById('mTaskTime').innerText = task.time_of_day;
        document.getElementById('mTaskFreq').innerText = task.frequency;
        document.getElementById('mTaskDays').innerText = task.specific_days || 'Dagelijks / Alle dagen';
    }

    const moodMap = ['Pijn', 'Rustig', 'Blij'];
    function updateMood(val, labelId, inputId) {
        const m = moodMap[val];
        document.getElementById(labelId).innerText = m;
        document.getElementById(inputId).value = m;
    }

    function openEditModal(report) {
        document.getElementById('editReportModal').classList.remove('hidden');
        document.getElementById('e_id').value = report.id;
        document.getElementById('e_content').value = report.content;
        document.getElementById('e_type').value = report.report_type;
        let val = 1; if(report.mood === 'Pijn') val = 0; if(report.mood === 'Blij') val = 2;
        document.getElementById('edit_mood_range').value = val;
        updateMood(val, 'edit_mood_display', 'edit_mood_input');
    }

    document.addEventListener("DOMContentLoaded", function() {
        if(window.location.hash) {
            const h = window.location.hash.substring(1);
            const b = document.querySelector(`button[onclick="switchTab('${h}', this)"]`);
            if(b) b.click();
        } else {
            const c = document.getElementById('chatContainer'); if(c) c.scrollTop = c.scrollHeight;
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>