<?php
// dashboard.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/header.php';
require 'config/db.php';

// 1. DATA OPHALEN
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Initialiseer variabelen
$stats = ['clients' => 0, 'orders' => 0, 'nurses' => 0];
$recent_reports = [];
$my_client = null;
$past_visits = [];
$upcoming_visits = [];

// Helper voor Nederlandse datumweergave
$dutch_days = ['Mon'=>'Maandag', 'Tue'=>'Dinsdag', 'Wed'=>'Woensdag', 'Thu'=>'Donderdag', 'Fri'=>'Vrijdag', 'Sat'=>'Zaterdag', 'Sun'=>'Zondag'];
$dutch_months = ['Jan'=>'Januari', 'Feb'=>'Februari', 'Mar'=>'Maart', 'Apr'=>'April', 'May'=>'Mei', 'Jun'=>'Juni', 'Jul'=>'Juli', 'Aug'=>'Augustus', 'Sep'=>'September', 'Oct'=>'Oktober', 'Nov'=>'November', 'Dec'=>'December'];
$today_display = $dutch_days[date('D')] . ' ' . date('d') . ' ' . $dutch_months[date('M')] . ' ' . date('Y');

try {
    if ($role === 'management') {
        // --- MANAGEMENT LOGICA ---
        $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_active=1")->fetchColumn();
        $stats['orders'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='in_afwachting'")->fetchColumn();
        $stats['nurses'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='zuster'")->fetchColumn();
        
        $stmt = $pdo->query("SELECT r.*, c.first_name, c.last_name, u.username, c.id as client_id
                             FROM client_reports r 
                             JOIN clients c ON r.client_id = c.id 
                             JOIN users u ON r.author_id = u.id 
                             ORDER BY r.created_at DESC LIMIT 10");
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'zuster') {
        // --- ZUSTER LOGICA ---
        $stmt = $pdo->prepare("SELECT r.*, c.first_name, c.last_name, u.username, c.id as client_id
                               FROM client_reports r 
                               JOIN clients c ON r.client_id = c.id 
                               JOIN users u ON r.author_id = u.id 
                               WHERE r.author_id = ?
                               ORDER BY r.created_at DESC LIMIT 10");
        $stmt->execute([$user_id]);
        $recent_reports = $stmt->fetchAll();

    } elseif ($role === 'familie') {
        // --- FAMILIE LOGICA ---
        $client_stmt = $pdo->prepare("
            SELECT c.* FROM clients c 
            JOIN family_client_access a ON c.id = a.client_id 
            WHERE a.user_id = ? 
            LIMIT 1
        ");
        $client_stmt->execute([$user_id]);
        $my_client = $client_stmt->fetch();

        if ($my_client) {
            $client_id = $my_client['id'];

            $report_stmt = $pdo->prepare("
                SELECT r.*, u.username, np.first_name, np.last_name 
                FROM client_reports r 
                JOIN users u ON r.author_id = u.id 
                LEFT JOIN nurse_profiles np ON u.id = np.user_id
                WHERE r.client_id = ? AND r.visible_to_family = 1
                ORDER BY r.created_at DESC LIMIT 5
            ");
            $report_stmt->execute([$client_id]);
            $recent_reports = $report_stmt->fetchAll();

            $past_stmt = $pdo->prepare("
                SELECT DATE(tel.executed_at) as visit_date, MAX(tel.executed_at) as last_time,
                       u.username, np.first_name, np.last_name
                FROM task_execution_log tel
                JOIN client_care_tasks cct ON tel.client_care_task_id = cct.id
                JOIN users u ON tel.nurse_id = u.id
                LEFT JOIN nurse_profiles np ON u.id = np.user_id
                WHERE cct.client_id = ? AND tel.status = 'Uitgevoerd'
                GROUP BY DATE(tel.executed_at), tel.nurse_id
                ORDER BY visit_date DESC
                LIMIT 5
            ");
            $past_stmt->execute([$client_id]);
            $past_visits = $past_stmt->fetchAll();

            $roster_stmt = $pdo->prepare("
                SELECT r.day_of_week, rs.planned_time, u.username, np.first_name, np.last_name, rt.name as route_name
                FROM route_stops rs
                JOIN roster r ON rs.route_id = r.route_id
                JOIN routes rt ON rs.route_id = rt.id
                JOIN users u ON r.nurse_id = u.id
                LEFT JOIN nurse_profiles np ON u.id = np.user_id
                WHERE rs.client_id = ?
            ");
            $roster_stmt->execute([$client_id]);
            $roster_rules = $roster_stmt->fetchAll();

            if($roster_rules) {
                $days_map = ['Ma' => 'Mon', 'Di' => 'Tue', 'Wo' => 'Wed', 'Do' => 'Thu', 'Vr' => 'Fri', 'Za' => 'Sat', 'Zo' => 'Sun'];
                
                for($i = 0; $i <= 14; $i++) {
                    $ts = strtotime("+$i days");
                    $date_str = date('Y-m-d', $ts);
                    $day_en = date('D', $ts);
                    
                    $day_nl_found = false;
                    foreach($days_map as $nl => $en) {
                        if($en === $day_en) { $day_nl_found = $nl; break; }
                    }

                    foreach($roster_rules as $rule) {
                        if($rule['day_of_week'] === $day_nl_found) {
                            $upcoming_visits[] = [
                                'date' => $date_str,
                                'time' => $rule['planned_time'],
                                'nurse' => $rule['first_name'] ? $rule['first_name'] . ' ' . $rule['last_name'] : $rule['username'],
                                'route' => $rule['route_name']
                            ];
                        }
                    }
                    if(count($upcoming_visits) >= 5) break;
                }
            }
        }
    }

} catch (PDOException $e) {
    echo "<div class='bg-red-50 text-red-700 p-4 border-l-4 border-red-600'>Systeemmelding: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<div class="w-full mb-12">

    <div class="flex justify-between items-center mb-8 border-b border-gray-300 pb-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 uppercase tracking-tight">
                <?php echo ($role === 'familie') ? 'Mijn Zorgomgeving' : (($role === 'management') ? 'Management Dashboard' : 'Zorgportaal'); ?>
            </h1>
            <p class="text-xs text-slate-500 mt-1">
                <?php if($role === 'familie'): ?>
                    Informatie en updates over uw naaste.
                <?php else: ?>
                    Enterprise Resource Planning & Zorgadministratie.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-right">
            <div class="text-sm font-bold text-slate-700"><i class="fa-regular fa-calendar mr-1"></i> <?php echo $today_display; ?></div>
            <div class="text-xs text-slate-400">Ingelogd als <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>
    </div>

    <?php if ($role === 'familie'): ?>
        
        <?php if($my_client): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                
                <div class="lg:col-span-1 bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-user-injured mr-2 text-slate-400"></i> Cliëntdossier
                        </h3>
                        <span class="text-[10px] bg-green-100 text-green-800 px-2 py-0.5 border border-green-200 uppercase font-bold">Actief</span>
                    </div>
                    <div class="p-6 flex flex-col items-center text-center">
                        <div class="w-20 h-20 bg-slate-200 rounded-full flex items-center justify-center text-2xl font-bold text-slate-500 mb-4 border-4 border-white shadow-sm">
                            <?php echo substr($my_client['first_name'],0,1).substr($my_client['last_name'],0,1); ?>
                        </div>
                        <h2 class="text-lg font-bold text-slate-800 mb-1">
                            <?php echo htmlspecialchars($my_client['first_name'] . ' ' . $my_client['last_name']); ?>
                        </h2>
                        <p class="text-xs text-slate-500 mb-4 uppercase tracking-wide">
                            <i class="fa-solid fa-location-dot mr-1"></i> <?php echo htmlspecialchars($my_client['district']); ?>
                        </p>
                        
                        <div class="w-full border-t border-gray-100 pt-4 text-left text-sm space-y-2 mb-6">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Adres:</span>
                                <span class="font-medium text-slate-700"><?php echo htmlspecialchars($my_client['address']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Wijk:</span>
                                <span class="font-medium text-slate-700"><?php echo htmlspecialchars($my_client['neighborhood']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Geboortedatum:</span>
                                <span class="font-medium text-slate-700"><?php echo date('d-m-Y', strtotime($my_client['dob'])); ?></span>
                            </div>
                        </div>

                        <a href="pages/clients/detail.php?id=<?php echo $my_client['id']; ?>" class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 text-xs uppercase tracking-wide transition-colors text-center flex items-center justify-center gap-2">
                            <i class="fa-solid fa-folder-open"></i> Dossier Openen
                        </a>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-white border border-gray-300 shadow-sm flex flex-col">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300">
                        <h3 class="text-xs font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-clipboard-list mr-2 text-slate-400"></i> Laatste Rapportages
                        </h3>
                    </div>
                    <div class="flex-1 overflow-auto">
                        <?php if(count($recent_reports) > 0): ?>
                            <div class="divide-y divide-gray-100">
                                <?php foreach($recent_reports as $r): ?>
                                    <div class="p-4 hover:bg-slate-50 transition-colors">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="text-xs font-bold text-blue-700 uppercase flex items-center gap-1">
                                                <i class="fa-solid fa-circle-info"></i> <?php echo htmlspecialchars($r['report_type']); ?>
                                            </span>
                                            <span class="text-xs text-slate-400">
                                                <i class="fa-regular fa-clock mr-1"></i> <?php echo date('d-m-Y H:i', strtotime($r['created_at'])); ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-700 leading-relaxed mb-2">
                                            <?php echo nl2br(htmlspecialchars($r['content'])); ?>
                                        </p>
                                        <div class="flex items-center text-[10px] text-slate-400 uppercase">
                                            <span class="mr-2">Verzorgende: <?php echo htmlspecialchars($r['first_name'] ? $r['first_name'] : $r['username']); ?></span>
                                            <?php if($r['mood']): ?>
                                                <span class="px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded-sm text-slate-600 flex items-center gap-1">
                                                    Stemming: <?php echo htmlspecialchars($r['mood']); ?>
                                                    <?php 
                                                    if($r['mood'] === 'Blij') echo '<i class="fa-solid fa-face-smile text-green-500"></i>';
                                                    elseif($r['mood'] === 'Pijn') echo '<i class="fa-solid fa-face-frown text-red-500"></i>';
                                                    else echo '<i class="fa-solid fa-face-meh text-gray-400"></i>';
                                                    ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-slate-400 italic text-sm">
                                Nog geen rapportages beschikbaar.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-solid fa-check-circle mr-2 text-slate-400"></i> Afgeronde Bezoeken
                        </h3>
                        <span class="text-[10px] text-slate-400 uppercase">Laatste 5</span>
                    </div>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-gray-100">
                                <th class="px-4 py-2 font-medium">Datum</th>
                                <th class="px-4 py-2 font-medium">Verzorgende</th>
                                <th class="px-4 py-2 font-medium text-right">Tijdstip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($past_visits) > 0): foreach($past_visits as $vis): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700 font-bold"><?php echo date('d-m-Y', strtotime($vis['visit_date'])); ?></td>
                                    <td class="px-4 py-3 text-slate-600"><?php echo htmlspecialchars($vis['first_name'] ? $vis['first_name'].' '.$vis['last_name'] : $vis['username']); ?></td>
                                    <td class="px-4 py-3 text-right text-slate-400"><?php echo date('H:i', strtotime($vis['last_time'])); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400 italic">Geen bezoeken geregistreerd.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
                        <h3 class="text-xs font-bold text-slate-700 uppercase flex items-center">
                            <i class="fa-regular fa-clock mr-2 text-slate-400"></i> Verwachte Bezoeken
                        </h3>
                        <span class="text-[10px] text-slate-400 uppercase">Planning</span>
                    </div>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-slate-400 border-b border-gray-100">
                                <th class="px-4 py-2 font-medium">Datum</th>
                                <th class="px-4 py-2 font-medium">Route / Tijd</th>
                                <th class="px-4 py-2 font-medium text-right">Verzorgende</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($upcoming_visits) > 0): foreach($upcoming_visits as $up): ?>
                                <tr>
                                    <td class="px-4 py-3 text-slate-700 font-bold">
                                        <?php 
                                            $d = strtotime($up['date']);
                                            echo date('d-m', $d); 
                                            // Vertaal dagnaam kort
                                            $day_en_short = date('D', $d);
                                            $day_nl_short = '';
                                            foreach($dutch_days as $eng => $nl) { if(strpos($eng, $day_en_short) === 0) $day_nl_short = substr($nl, 0, 2); }
                                            echo " <span class='text-slate-400 font-normal'>(".$day_nl_short.")</span>";
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <span class="block text-slate-800 font-bold"><?php echo date('H:i', strtotime($up['time'])); ?></span>
                                        <span class="text-[10px] uppercase"><?php echo htmlspecialchars($up['route']); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-blue-700 font-medium"><?php echo htmlspecialchars($up['nurse']); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400 italic">Geen toekomstige bezoeken gepland in het rooster.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

        <?php else: ?>
            <div class="bg-white border border-yellow-300 bg-yellow-50 p-8 text-center shadow-sm">
                <i class="fa-solid fa-triangle-exclamation text-yellow-600 text-4xl mb-3"></i>
                <h3 class="text-lg font-bold text-yellow-800 mb-2">Geen cliënt gekoppeld</h3>
                <p class="text-sm text-yellow-700">Er is helaas geen cliëntdossier gekoppeld aan uw account. Neem contact op met de administratie.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        
        <?php if ($role === 'management'): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <a href="pages/clients/index.php" class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm group hover:border-blue-400 transition-colors cursor-pointer">
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Actieve Cliënten</div>
                        <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['clients']; ?></div>
                    </div>
                    <div class="text-slate-200 group-hover:text-blue-200 transition-colors">
                        <i class="fa-solid fa-users text-4xl"></i>
                    </div>
                </a>

                <a href="pages/planning/manage_orders.php" class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm relative overflow-hidden group hover:border-orange-400 transition-colors cursor-pointer">
                    <?php if($stats['orders'] > 0): ?><div class="absolute top-0 right-0 w-3 h-3 bg-orange-500 rounded-bl-lg"></div><?php endif; ?>
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Open Orders</div>
                        <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['orders']; ?></div>
                    </div>
                    <div class="text-slate-200 group-hover:text-orange-200 transition-colors">
                        <i class="fa-solid fa-box-open text-4xl"></i>
                    </div>
                </a>

                <a href="pages/users/index.php" class="bg-white border border-gray-300 p-5 flex items-center justify-between shadow-sm group hover:border-teal-400 transition-colors cursor-pointer">
                    <div>
                        <div class="text-xs text-slate-500 uppercase font-bold tracking-wider">Zorgpersoneel</div>
                        <div class="text-3xl font-bold text-slate-800 mt-2"><?php echo $stats['nurses']; ?></div>
                    </div>
                    <div class="text-slate-200 group-hover:text-teal-200 transition-colors">
                        <i class="fa-solid fa-user-nurse text-4xl"></i>
                    </div>
                </a>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-1">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex items-center">
                        <i class="fa-solid fa-bolt text-slate-400 mr-2"></i>
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Snelmenu</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php if ($role === 'management'): ?>
                            <a href="pages/clients/index.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-folder-open w-6 text-slate-400 group-hover:text-blue-600 transition-colors"></i>
                                Cliëntendossiers
                            </a>
                            <a href="pages/users/index.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-users-gear w-6 text-slate-400 group-hover:text-blue-600 transition-colors"></i>
                                HR & Personeel
                            </a>
                            <a href="pages/planning/roster.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-calendar-days w-6 text-slate-400 group-hover:text-blue-600 transition-colors"></i>
                                Rooster & Routes
                            </a>
                            <a href="pages/planning/manage_orders.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-boxes-packing w-6 text-slate-400 group-hover:text-blue-600 transition-colors"></i>
                                Orders & Inkoop
                            </a>
                        <?php else: ?>
                            <a href="pages/planning/view.php" class="group block p-4 hover:bg-blue-50 text-sm font-bold text-blue-700 border-l-4 border-blue-600 flex items-center">
                                <i class="fa-solid fa-route w-6"></i>
                                Start Mijn Route
                            </a>
                            <a href="pages/clients/index.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-address-book w-6 text-slate-400 group-hover:text-blue-600"></i>
                                Cliëntenlijst
                            </a>
                            <a href="pages/profile/my_hr.php" class="group block p-4 hover:bg-slate-50 text-sm font-bold text-slate-700 flex items-center">
                                <i class="fa-solid fa-briefcase w-6 text-slate-400 group-hover:text-blue-600"></i>
                                Mijn HR
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white border border-gray-300 shadow-sm">
                    <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex items-center">
                        <i class="fa-solid fa-file-medical text-slate-400 mr-2"></i>
                        <h3 class="text-xs font-bold text-slate-700 uppercase">Recente Rapportages</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($recent_reports as $r): ?>
                                <tr class="hover:bg-slate-50 cursor-pointer transition-colors" onclick="window.location='pages/clients/detail.php?id=<?php echo $r['client_id']; ?>#rapportages'">
                                    <td class="px-4 py-3 font-medium text-slate-600 whitespace-nowrap">
                                        <?php echo date('d-m H:i', strtotime($r['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-slate-700"><?php echo htmlspecialchars($r['last_name']); ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 border text-[10px] font-bold uppercase bg-gray-100 text-slate-600">
                                            <?php echo htmlspecialchars($r['report_type']); ?>
                                        </span>
                                        <?php 
                                            if($r['mood'] == 'Blij') echo '<i class="fa-solid fa-face-smile text-green-500 ml-2 text-lg" title="Blij"></i>';
                                            elseif($r['mood'] == 'Pijn') echo '<i class="fa-solid fa-face-frown text-red-500 ml-2 text-lg" title="Pijn"></i>';
                                            else echo '<i class="fa-solid fa-face-meh text-gray-400 ml-2 text-lg" title="Rustig"></i>';
                                        ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 truncate max-w-xs"><?php echo htmlspecialchars($r['content']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>