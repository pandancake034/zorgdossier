<?php
// pages/clients/detail.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include '../../includes/header.php';
require '../../config/db.php';

// ---------------------------------------------------------
// 1. LOGICA & DATA OPHALEN (Backend)
// ---------------------------------------------------------

if (!isset($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
$client_id = $_GET['id'];
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// --- POST AFHANDELING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. NIEUWE NOTITIE (CHAT / OVERDRACHT)
    if (isset($_POST['action']) && $_POST['action'] === 'add_note') {
        $message = trim($_POST['message']);
        if (!empty($message)) {
            $stmt = $pdo->prepare("INSERT INTO client_notes (client_id, author_id, message) VALUES (?, ?, ?)");
            $stmt->execute([$client_id, $user_id, $message]);
        }
        // Redirect om form resubmit te voorkomen
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

    // C. RAPPORTAGE VERWIJDEREN/BEWERKEN (Simpele implementatie behouden uit origineel)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_report') {
        $report_id = $_POST['report_id'];
        $chk = $pdo->prepare("SELECT author_id FROM client_reports WHERE id=?"); $chk->execute([$report_id]); $r=$chk->fetch();
        if ($r && ($r['author_id'] == $user_id || $user_role === 'management')) {
            $pdo->prepare("DELETE FROM client_reports WHERE id=?")->execute([$report_id]);
        }
        header("Location: detail.php?id=$client_id#rapportages");
        exit;
    }
}

// Helper Functies
function calculateAge($dob) { return (new DateTime($dob))->diff(new DateTime('today'))->y; }

try {
    // 1. Client Stamgegevens
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    if (!$client) die("Cliënt niet gevonden.");

    // 2. Kritieke Info (Allergieën & Hulpmiddelen)
    $allergies = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?"); 
    $allergies->execute([$client_id]); 
    $allergies_list = $allergies->fetchAll(PDO::FETCH_COLUMN);

    $aids = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?"); 
    $aids->execute([$client_id]); 
    $aids_list = $aids->fetchAll(PDO::FETCH_COLUMN);

    // 3. Notities (Communicatie Logboek)
    $note_sql = "SELECT n.*, u.username, np.first_name, np.last_name 
                 FROM client_notes n 
                 JOIN users u ON n.author_id = u.id 
                 LEFT JOIN nurse_profiles np ON u.id = np.user_id 
                 WHERE n.client_id = ? 
                 ORDER BY n.created_at ASC"; 
    $stmt_notes = $pdo->prepare($note_sql);
    $stmt_notes->execute([$client_id]);
    $chat_notes = $stmt_notes->fetchAll();

    // 4. Rapportages
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

    // 5. Zorgplan (Taken)
    $task_sql = "SELECT * FROM client_care_tasks WHERE client_id = ? AND is_active = 1 
                 ORDER BY FIELD(time_of_day, 'Ochtend', 'Middag', 'Avond', 'Nacht', 'Hele dag'), title";
    $stmt_task = $pdo->prepare($task_sql);
    $stmt_task->execute([$client_id]);
    $care_tasks = $stmt_task->fetchAll();

    // 6. Medicatie
    $med_stmt = $pdo->prepare("SELECT * FROM client_medications WHERE client_id = ? ORDER BY times");
    $med_stmt->execute([$client_id]);
    $medications = $med_stmt->fetchAll();

    // 7. Dag Progressie (Voor het dashboard)
    // Telt hoeveel taken vandaag gedaan zijn (Logica vereenvoudigd voor display)
    $total_tasks_count = count($care_tasks);
    $completed_tasks_count = 0; // Hier zou je normaal in `task_execution_log` kijken voor vandaag
    $progress_perc = ($total_tasks_count > 0) ? round(($completed_tasks_count / $total_tasks_count) * 100) : 0;

} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<div class="sticky top-0 z-40 bg-white border-b border-slate-300 shadow-md">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-col md:flex-row items-center justify-between">
        
        <div class="flex items-center w-full md:w-auto mb-2 md:mb-0">
            <div class="h-12 w-12 bg-slate-200 rounded-full flex items-center justify-center text-slate-600 font-bold text-lg border-2 border-white shadow-sm mr-4">
                <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-800 leading-tight">
                    <?php echo htmlspecialchars($client['last_name'] . ', ' . $client['first_name']); ?>
                </h1>
                <div class="text-xs text-slate-500 flex items-center gap-3">
                    <span><i class="fa-regular fa-id-card mr-1"></i> <?php echo date('d-m-Y', strtotime($client['dob'])); ?> (<?php echo calculateAge($client['dob']); ?>jr)</span>
                    <span class="hidden sm:inline">|</span>
                    <span class="truncate"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($client['address']); ?></span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 justify-center md:justify-start">
            <?php if($client['diabetes_type'] !== 'Geen'): ?>
                <span class="bg-red-100 text-red-800 border border-red-200 px-3 py-1 rounded-full text-xs font-bold uppercase flex items-center animate-pulse">
                    <i class="fa-solid fa-syringe mr-2"></i> Diabetes <?php echo htmlspecialchars($client['diabetes_type']); ?>
                </span>
            <?php endif; ?>
            
            <?php foreach($allergies_list as $a): ?>
                <span class="bg-amber-100 text-amber-800 border border-amber-200 px-3 py-1 rounded-full text-xs font-bold uppercase flex items-center">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo htmlspecialchars($a); ?>
                </span>
            <?php endforeach; ?>
        </div>

        <div class="flex items-center gap-2 mt-2 md:mt-0 w-full md:w-auto justify-end">
            <?php if($user_role !== 'familie'): ?>
                <button onclick="switchTab('rapportages', document.getElementById('tab-rep')); document.getElementById('report_content').focus();" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold py-2 px-3 rounded shadow-sm transition">
                    <i class="fa-solid fa-pen mr-1"></i> Rapporteren
                </button>
                <a href="save_observation.php?client_id=<?php echo $client_id; ?>" class="bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold py-2 px-3 rounded shadow-sm transition">
                    <i class="fa-solid fa-heart-pulse mr-1"></i> Meting
                </a>
            <?php endif; ?>
            <?php if($user_role === 'management'): ?>
                <a href="edit.php?id=<?php echo $client_id; ?>" class="text-slate-400 hover:text-slate-600 px-2" title="Stamkaart Wijzigen">
                    <i class="fa-solid fa-gear"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="w-full max-w-7xl mx-auto py-6 px-4">

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <div class="lg:col-span-8 space-y-6">
            
            <div class="bg-white border-b border-gray-200 sticky top-[72px] z-30 shadow-sm">
                <nav class="flex space-x-1" aria-label="Tabs">
                    <button id="tab-home" onclick="switchTab('overview', this)" class="tab-btn active border-b-2 border-blue-600 text-blue-600 font-bold px-4 py-3 text-sm">
                        Overzicht
                    </button>
                    <button id="tab-plan" onclick="switchTab('zorgplan', this)" class="tab-btn border-b-2 border-transparent text-slate-500 hover:text-slate-700 font-medium px-4 py-3 text-sm">
                        Zorgplan
                    </button>
                    <button id="tab-med" onclick="switchTab('medisch', this)" class="tab-btn border-b-2 border-transparent text-slate-500 hover:text-slate-700 font-medium px-4 py-3 text-sm">
                        Medisch
                    </button>
                    <button id="tab-rep" onclick="switchTab('rapportages', this)" class="tab-btn border-b-2 border-transparent text-slate-500 hover:text-slate-700 font-medium px-4 py-3 text-sm">
                        Rapportages
                    </button>
                </nav>
            </div>

            <div id="overview" class="tab-content block space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white border border-gray-200 rounded p-4 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-3"><i class="fa-solid fa-phone mr-1"></i> Eerste Contactpersoon</h3>
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars($client['contact1_name']); ?></div>
                                <div class="text-sm text-slate-500"><?php echo htmlspecialchars($client['contact1_phone']); ?></div>
                            </div>
                            <a href="tel:<?php echo htmlspecialchars($client['contact1_phone']); ?>" class="bg-green-100 text-green-700 h-8 w-8 rounded-full flex items-center justify-center hover:bg-green-200">
                                <i class="fa-solid fa-phone"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="bg-white border border-gray-200 rounded p-4 shadow-sm">
                        <h3 class="text-xs font-bold text-slate-400 uppercase mb-3"><i class="fa-solid fa-notes-medical mr-1"></i> Bijzonderheden</h3>
                        <p class="text-sm text-slate-700 italic">
                            <?php echo !empty($client['notes']) ? nl2br(htmlspecialchars($client['notes'])) : "Geen bijzonderheden."; ?>
                        </p>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Laatste Rapportage</h3>
                        <button onclick="switchTab('rapportages', document.getElementById('tab-rep'))" class="text-xs text-blue-600 font-bold hover:underline">Alle tonen &rarr;</button>
                    </div>
                    <div class="p-4">
                        <?php if(count($reports) > 0): $last_r = $reports[0]; ?>
                            <div class="flex gap-3">
                                <div class="text-center min-w-[50px]">
                                    <span class="block text-xs text-slate-400 uppercase font-bold"><?php echo date('M', strtotime($last_r['created_at'])); ?></span>
                                    <span class="block text-xl font-bold text-slate-700"><?php echo date('d', strtotime($last_r['created_at'])); ?></span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-bold bg-blue-50 text-blue-700 px-1.5 rounded uppercase"><?php echo htmlspecialchars($last_r['report_type']); ?></span>
                                        <span class="text-xs text-slate-400">door <?php echo htmlspecialchars($last_r['first_name']); ?></span>
                                    </div>
                                    <p class="text-sm text-slate-700"><?php echo nl2br(htmlspecialchars($last_r['content'])); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-sm text-slate-400 italic">Nog geen rapportages.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white border border-gray-200 rounded shadow-sm">
                    <div class="bg-slate-50 px-4 py-2 border-b border-gray-200">
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Zorgmomenten Vandaag</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php 
                        $preview_tasks = array_slice($care_tasks, 0, 3);
                        foreach($preview_tasks as $t): 
                        ?>
                            <div class="p-3 flex justify-between items-center hover:bg-slate-50">
                                <div>
                                    <div class="font-bold text-sm text-slate-700"><?php echo htmlspecialchars($t['title']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($t['time_of_day']); ?></div>
                                </div>
                                <span class="bg-slate-100 text-slate-500 text-[10px] px-2 py-1 rounded font-bold uppercase"><?php echo htmlspecialchars($t['frequency']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <button onclick="switchTab('zorgplan', document.getElementById('tab-plan'))" class="w-full py-2 text-center text-xs font-bold text-blue-600 hover:bg-blue-50">
                            Bekijk volledig zorgplan (<?php echo count($care_tasks); ?> taken)
                        </button>
                    </div>
                </div>
            </div>

            <div id="zorgplan" class="tab-content hidden space-y-4">
                <div class="flex justify-between items-end mb-2">
                    <h3 class="font-bold text-slate-700">Geplande Zorgtaken</h3>
                    <?php if($user_role !== 'familie'): ?>
                        <?php endif; ?>
                </div>

                <?php if(count($care_tasks) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach($care_tasks as $task): $taskJson = htmlspecialchars(json_encode($task), ENT_QUOTES, 'UTF-8'); ?>
                            <div onclick='openTaskModal(<?php echo $taskJson; ?>)' class="bg-white border border-gray-200 p-4 rounded shadow-sm hover:shadow-md hover:border-blue-300 cursor-pointer transition-all group">
                                <div class="flex justify-between items-start mb-2">
                                    <span class="text-[10px] font-bold uppercase bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                        <?php echo htmlspecialchars($task['time_of_day']); ?>
                                    </span>
                                    <i class="fa-solid fa-chevron-right text-slate-300 group-hover:text-blue-500"></i>
                                </div>
                                <h4 class="font-bold text-slate-800 text-sm mb-1"><?php echo htmlspecialchars($task['title']); ?></h4>
                                <p class="text-xs text-slate-500 line-clamp-2"><?php echo htmlspecialchars($task['description']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-slate-50 border border-dashed border-slate-300 p-8 text-center rounded">
                        <p class="text-slate-500 italic">Geen zorgtaken ingesteld voor deze cliënt.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="medisch" class="tab-content hidden space-y-6">
                <div class="bg-white border border-gray-200 rounded shadow-sm overflow-hidden">
                    <div class="bg-slate-50 px-4 py-3 border-b border-gray-200 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase"><i class="fa-solid fa-pills mr-2"></i> Actuele Medicatie</h3>
                    </div>
                    <table class="w-full text-sm text-left">
                        <thead class="bg-white text-slate-500 border-b border-gray-200 uppercase text-xs">
                            <tr><th class="px-4 py-3">Naam</th><th class="px-4 py-3">Dosis</th><th class="px-4 py-3">Tijden</th><th class="px-4 py-3">Info</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($medications as $m): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-bold text-slate-700"><?php echo htmlspecialchars($m['name']); ?></td>
                                <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($m['dosage']); ?></td>
                                <td class="px-4 py-3"><span class="bg-blue-50 text-blue-800 px-2 py-0.5 rounded text-xs font-bold"><?php echo htmlspecialchars($m['times']); ?></span></td>
                                <td class="px-4 py-3 text-slate-500 italic text-xs"><?php echo htmlspecialchars($m['notes']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if($user_role !== 'familie'): ?>
                        <div class="p-3 bg-slate-50 border-t border-gray-200">
                             <p class="text-xs text-center text-slate-400">Beheer medicatie via het Management portaal of 'Medicijn toevoegen' in oude weergave.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white border border-gray-200 rounded p-4">
                        <h3 class="text-xs font-bold text-slate-700 uppercase mb-3 border-b pb-1">Hulpmiddelen</h3>
                        <ul class="list-disc list-inside text-sm text-slate-600">
                            <?php foreach($aids_list as $aid): ?>
                                <li><?php echo htmlspecialchars($aid); ?></li>
                            <?php endforeach; ?>
                            <?php if(empty($aids_list)) echo "<li class='list-none italic text-slate-400'>Geen hulpmiddelen.</li>"; ?>
                        </ul>
                    </div>
                    <div class="bg-white border border-gray-200 rounded p-4">
                         <h3 class="text-xs font-bold text-slate-700 uppercase mb-3 border-b pb-1">Allergieën</h3>
                         <ul class="list-disc list-inside text-sm text-red-600">
                            <?php foreach($allergies_list as $al): ?>
                                <li><?php echo htmlspecialchars($al); ?></li>
                            <?php endforeach; ?>
                            <?php if(empty($allergies_list)) echo "<li class='list-none italic text-slate-400'>Geen bekende allergieën.</li>"; ?>
                         </ul>
                    </div>
                </div>
            </div>

            <div id="rapportages" class="tab-content hidden space-y-6">
                
                <?php if($user_role !== 'familie'): ?>
                <div class="bg-blue-50 border border-blue-200 rounded p-4 shadow-sm">
                    <h3 class="text-xs font-bold text-blue-800 uppercase mb-3"><i class="fa-solid fa-pen-to-square mr-2"></i> Nieuwe Rapportage Schrijven</h3>
                    <form action="detail.php?id=<?php echo $client_id; ?>" method="POST">
                        <input type="hidden" name="action" value="add_report">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Type</label>
                                <select name="report_type" class="w-full p-2 border border-gray-300 rounded text-sm bg-white"><option>Algemeen</option><option>Medisch</option><option>Incident</option></select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Stemming Cliënt</label>
                                <select name="mood" class="w-full p-2 border border-gray-300 rounded text-sm bg-white"><option value="Rustig">Rustig</option><option value="Blij">Blij</option><option value="Pijn">Pijn / Oncomfortabel</option><option value="Verward">Verward</option></select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                             <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Inhoud</label>
                             <textarea id="report_content" name="content" rows="3" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0" placeholder="Beschrijf de observatie of handeling..."></textarea>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <label class="inline-flex items-center text-xs text-slate-600"><input type="checkbox" name="visible_to_family" checked class="text-blue-600 rounded mr-2">Zichtbaar voor familie</label>
                            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded text-xs uppercase shadow-sm">Opslaan</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="space-y-4">
                    <?php foreach($reports as $r): 
                        $can_edit = ($r['author_id'] == $user_id || $user_role === 'management');
                        $mood_color = 'bg-gray-100 text-gray-600';
                        if($r['mood'] == 'Blij') $mood_color = 'bg-green-100 text-green-700';
                        if($r['mood'] == 'Pijn') $mood_color = 'bg-red-100 text-red-700';
                    ?>
                        <div class="bg-white border border-gray-200 rounded p-4 hover:shadow-sm transition-shadow">
                            <div class="flex justify-between items-start mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 uppercase"><?php echo date('d-m-Y H:i', strtotime($r['created_at'])); ?></span>
                                    <span class="text-xs font-bold px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-slate-600 uppercase"><?php echo htmlspecialchars($r['report_type']); ?></span>
                                </div>
                                <?php if($can_edit): ?>
                                    <form method="POST" onsubmit="return confirm('Rapportage verwijderen?');">
                                        <input type="hidden" name="action" value="delete_report">
                                        <input type="hidden" name="report_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="text-slate-300 hover:text-red-500 text-xs"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            
                            <p class="text-sm text-slate-800 mb-3 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($r['content']); ?></p>
                            
                            <div class="flex items-center justify-between border-t border-gray-100 pt-2">
                                <div class="flex items-center gap-2 text-xs">
                                    <div class="h-5 w-5 bg-slate-200 rounded-full flex items-center justify-center text-[10px] font-bold text-slate-600">
                                        <?php echo substr(($r['first_name'] ?: $r['username']), 0, 1); ?>
                                    </div>
                                    <span class="text-slate-500 font-medium"><?php echo htmlspecialchars($r['first_name'] ?: $r['username']); ?></span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?php echo $mood_color; ?>">
                                    Stemming: <?php echo htmlspecialchars($r['mood']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <div class="lg:col-span-4">
            
            <div class="bg-white border border-gray-300 shadow-sm rounded-lg flex flex-col h-[600px] sticky top-[72px]">
                <div class="bg-slate-800 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
                    <h3 class="text-xs font-bold uppercase tracking-wide flex items-center">
                        <i class="fa-regular fa-comments mr-2"></i> Overdracht / Logboek
                    </h3>
                    <span class="bg-slate-700 text-[10px] px-2 py-0.5 rounded-full border border-slate-600"><?php echo count($chat_notes); ?></span>
                </div>
                
                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50" id="chatContainer">
                    <?php if(count($chat_notes) > 0): foreach($chat_notes as $note): 
                        $is_me = ($note['author_id'] == $user_id);
                        $align = $is_me ? 'items-end' : 'items-start';
                        $bubble_style = $is_me ? 'bg-blue-100 border-blue-200 text-slate-800 rounded-tr-none' : 'bg-white border-gray-300 text-slate-700 rounded-tl-none';
                        $author_name = $note['first_name'] ? $note['first_name'] : $note['username'];
                    ?>
                        <div class="flex flex-col <?php echo $align; ?>">
                            <div class="max-w-[85%]">
                                <div class="flex items-center gap-2 mb-1 <?php echo $is_me ? 'justify-end' : 'justify-start'; ?>">
                                    <span class="text-[10px] font-bold text-slate-500 uppercase"><?php echo htmlspecialchars($author_name); ?></span>
                                    <span class="text-[9px] text-slate-400"><?php echo date('d-m H:i', strtotime($note['created_at'])); ?></span>
                                </div>
                                <div class="border p-3 shadow-sm text-sm rounded-lg relative <?php echo $bubble_style; ?>">
                                    <p class="leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($note['message']); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="flex flex-col items-center justify-center h-full text-slate-400 italic text-sm opacity-50">
                            <i class="fa-regular fa-comment-dots text-3xl mb-2"></i>
                            <p>Nog geen notities.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-3 bg-white border-t border-gray-300 rounded-b-lg">
                    <form action="detail.php?id=<?php echo $client_id; ?>" method="POST" class="flex gap-2">
                        <input type="hidden" name="action" value="add_note">
                        <input type="text" name="message" placeholder="Schrijf een snelle notitie..." class="flex-1 p-2 border border-gray-300 rounded text-sm focus:border-blue-600 focus:ring-0" required autocomplete="off">
                        <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white px-3 py-2 rounded text-sm font-bold shadow-sm">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="mt-4 bg-amber-50 border border-amber-200 rounded p-4 text-xs text-amber-800">
                <p><strong><i class="fa-solid fa-circle-info mr-1"></i> Tip:</strong> Gebruik het logboek voor informele overdracht (bijv. "Bril ligt op nachtkastje"). Gebruik <em>Rapporteren</em> voor medische feiten.</p>
            </div>

        </div>

    </div>
</div>

<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 backdrop-blur-sm">
    <div class="bg-white w-full max-w-md rounded-lg shadow-2xl overflow-hidden transform transition-all scale-100">
        <div class="bg-slate-800 text-white px-4 py-3 flex justify-between items-center">
            <h3 class="text-sm font-bold uppercase tracking-wide">Taakdetails</h3>
            <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="text-white hover:text-red-300 font-bold text-lg">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <span id="mTaskTime" class="inline-block bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded font-bold uppercase mb-2"></span>
                <h2 id="mTaskTitle" class="text-xl font-bold text-slate-800"></h2>
            </div>
            <div class="bg-slate-50 p-4 rounded border border-slate-200 text-sm text-slate-700 leading-relaxed" id="mTaskDesc"></div>
            <div class="grid grid-cols-2 gap-4 text-sm border-t border-gray-100 pt-4">
                <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Frequentie</span><span id="mTaskFreq" class="font-bold text-slate-700"></span></div>
                <div><span class="block text-[10px] font-bold text-slate-400 uppercase">Specifieke Dagen</span><span id="mTaskDays" class="font-medium text-slate-600"></span></div>
            </div>
        </div>
        <div class="bg-slate-50 px-4 py-3 border-t border-gray-200 text-right">
            <button onclick="document.getElementById('taskModal').classList.add('hidden')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 px-4 py-2 rounded text-xs font-bold uppercase">Sluiten</button>
        </div>
    </div>
</div>

<script>
    function switchTab(id, btn) {
        // Content verbergen
        document.querySelectorAll('.tab-content').forEach(el => { 
            el.classList.add('hidden'); 
            el.classList.remove('block'); 
        });
        
        // Buttons resetten
        document.querySelectorAll('.tab-btn').forEach(el => { 
            el.classList.remove('active', 'border-blue-600', 'text-blue-600'); 
            el.classList.add('border-transparent', 'text-slate-500'); 
        });
        
        // Actieve tonen
        document.getElementById(id).classList.remove('hidden'); 
        document.getElementById(id).classList.add('block');
        
        // Button actief maken
        btn.classList.remove('border-transparent', 'text-slate-500'); 
        btn.classList.add('active', 'border-blue-600', 'text-blue-600');
        
        // Scroll chat naar beneden als chat zichtbaar is (altijd zichtbaar in zijbalk nu, maar voor zekerheid)
        const c = document.getElementById('chatContainer'); 
        if(c) c.scrollTop = c.scrollHeight;
    }

    function openTaskModal(task) {
        document.getElementById('taskModal').classList.remove('hidden');
        document.getElementById('mTaskTitle').innerText = task.title;
        document.getElementById('mTaskDesc').innerText = task.description || 'Geen beschrijving beschikbaar.';
        document.getElementById('mTaskTime').innerText = task.time_of_day;
        document.getElementById('mTaskFreq').innerText = task.frequency;
        document.getElementById('mTaskDays').innerText = task.specific_days || 'Dagelijks / Alle dagen';
    }

    // Scroll chat on load
    document.addEventListener("DOMContentLoaded", function() {
        const c = document.getElementById('chatContainer'); 
        if(c) c.scrollTop = c.scrollHeight;
        
        // Hash navigation support (e.g. detail.php#rapportages)
        if(window.location.hash) {
            const h = window.location.hash.substring(1);
            // Mapping hash to tab ID logic if needed, simplificatie:
            if(h === 'rapportages') switchTab('rapportages', document.getElementById('tab-rep'));
            if(h === 'zorgplan') switchTab('zorgplan', document.getElementById('tab-plan'));
            if(h === 'medisch') switchTab('medisch', document.getElementById('tab-med'));
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>