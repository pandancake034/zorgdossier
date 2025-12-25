<?php
// pages/profile/my_hr.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of gebruiker is ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Alleen Zuster en Management hebben hier iets te zoeken (Familie niet)
if ($_SESSION['role'] === 'familie') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$nurse_id = $_SESSION['user_id'];
$current_month_name = date('F Y'); 

// 1. ROOSTER OPHALEN (Komende 7 dagen)
$english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];

$upcoming_schedule = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day_en = date('D', strtotime($date));
    $day_nl = str_replace($english_days, $dutch_days, $day_en);
    
    // Kijk of er een route is voor deze dag
    $stmt = $pdo->prepare("SELECT r.*, rt.name as route_name, rt.time_slot 
                           FROM roster r 
                           JOIN routes rt ON r.route_id = rt.id 
                           WHERE r.nurse_id = ? AND r.day_of_week = ?");
    $stmt->execute([$nurse_id, $day_nl]);
    $shift = $stmt->fetch();

    $upcoming_schedule[] = [
        'date' => $date,
        'day_nl' => $day_nl,
        'has_shift' => $shift ? true : false,
        'route' => $shift ? $shift['route_name'] : '-',
        'time' => $shift ? $shift['time_slot'] : '-'
    ];
}

// 2. GEWERKTE UREN OPHALEN
$hours_stmt = $pdo->prepare("SELECT * FROM worked_hours WHERE nurse_id = ? ORDER BY date DESC LIMIT 20");
$hours_stmt->execute([$nurse_id]);
$worked_hours = $hours_stmt->fetchAll();

// Totaal deze maand berekenen
$total_hours = 0;
foreach($worked_hours as $wh) {
    if(strpos($wh['date'], date('Y-m')) === 0) {
        $total_hours += $wh['hours_worked'];
    }
}

// 3. SALARISSTROKEN OPHALEN
$pay_stmt = $pdo->prepare("SELECT * FROM payslips WHERE nurse_id = ? ORDER BY payment_date DESC");
$pay_stmt->execute([$nurse_id]);
$payslips = $pay_stmt->fetchAll();
?>

<div class="max-w-6xl mx-auto mb-12">

    <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden mb-6">
        <div class="bg-teal-700 p-6 text-white flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center mb-4 md:mb-0">
                <div class="h-16 w-16 bg-teal-600 rounded-full flex items-center justify-center text-3xl mr-4 border-2 border-teal-500 shadow-sm">
                    <i class="fa-solid fa-briefcase"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Mijn HR Portaal</h1>
                    <p class="text-teal-200 text-sm">Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?>.</p>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <div class="text-right hidden md:block border-r border-teal-600 pr-6">
                    <div class="text-3xl font-bold"><?php echo $total_hours; ?>u</div>
                    <div class="text-xs text-teal-300 uppercase font-bold">Gewerkt in <?php echo date('M'); ?></div>
                </div>
                <a href="my_profile.php" class="bg-teal-900 hover:bg-teal-800 text-white font-bold py-2 px-4 rounded text-sm transition shadow border border-teal-600 flex items-center">
                    <i class="fa-solid fa-user-pen mr-2"></i> Wijzig Gegevens
                </a>
            </div>
        </div>
        
        <div class="flex border-b border-gray-200 bg-gray-50 text-gray-600 overflow-x-auto">
            <button onclick="openTab('rooster', this)" class="tab-btn active py-4 px-6 focus:outline-none hover:text-teal-700 transition font-bold text-sm border-b-2 border-teal-600 text-teal-800 bg-white">
                <i class="fa-solid fa-calendar-days mr-2"></i> Mijn Rooster
            </button>
            <button onclick="openTab('uren', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-700 transition font-medium text-sm border-b-2 border-transparent">
                <i class="fa-solid fa-clock mr-2"></i> Urenregistratie
            </button>
            <button onclick="openTab('salaris', this)" class="tab-btn py-4 px-6 focus:outline-none hover:text-teal-700 transition font-medium text-sm border-b-2 border-transparent">
                <i class="fa-solid fa-file-invoice-dollar mr-2"></i> Salaris & Stroken
            </button>
        </div>
    </div>

    <div id="rooster" class="tab-content block">
        <div class="bg-white rounded-lg shadow-sm border border-gray-300 p-6">
            <div class="flex items-center justify-between mb-4 border-b border-gray-100 pb-2">
                <h3 class="text-sm font-bold text-slate-700 uppercase">
                    <i class="fa-solid fa-calendar-week mr-2 text-slate-400"></i> Komende 7 Dagen
                </h3>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                <?php foreach($upcoming_schedule as $day): 
                    $is_today = ($day['date'] == date('Y-m-d'));
                    $bg_color = $day['has_shift'] ? 'bg-teal-50 border-teal-200' : 'bg-gray-50 border-gray-200';
                    if($is_today) $bg_color = 'bg-blue-50 border-blue-300 ring-2 ring-blue-100';
                ?>
                    <div class="border rounded-lg p-3 text-center flex flex-col h-full <?php echo $bg_color; ?>">
                        <div class="text-[10px] font-bold text-slate-400 uppercase mb-1"><?php echo $day['day_nl']; ?></div>
                        <div class="text-lg font-bold text-slate-700 mb-2"><?php echo date('d M', strtotime($day['date'])); ?></div>
                        
                        <?php if($day['has_shift']): ?>
                            <div class="mt-auto">
                                <div class="bg-teal-600 text-white text-[10px] font-bold py-1 px-2 rounded mb-1 shadow-sm">
                                    <?php echo $day['time']; ?>
                                </div>
                                <div class="text-[10px] text-teal-800 font-bold truncate" title="<?php echo $day['route']; ?>">
                                    <?php echo $day['route']; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mt-auto text-gray-400 text-sm py-2">
                                <i class="fa-regular fa-face-smile"></i> Vrij
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6 bg-blue-50 p-4 rounded border-l-4 border-blue-500 text-blue-900 text-sm flex items-start">
                <i class="fa-solid fa-circle-info mt-0.5 mr-3 text-lg"></i>
                <div>
                    <strong>Let op:</strong> Wijzigingen in het rooster moeten altijd via de planning worden aangevraagd. Ruilen is alleen toegestaan met goedkeuring.
                </div>
            </div>
        </div>
    </div>

    <div id="uren" class="tab-content hidden">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="md:col-span-2 bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-gray-300">
                    <h3 class="text-xs font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-list-check mr-2 text-slate-400"></i> Recent gelogd
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-white text-slate-500 border-b border-gray-200 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-3">Datum</th>
                                <th class="px-6 py-3">Route/Dienst</th>
                                <th class="px-6 py-3">Uren</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($worked_hours as $h): 
                                $status_badge = 'bg-gray-100 text-gray-600 border-gray-200';
                                $icon = 'fa-hourglass-start';
                                
                                if($h['status'] == 'Goedgekeurd') { 
                                    $status_badge = 'bg-green-50 text-green-700 border-green-200'; 
                                    $icon = 'fa-check';
                                }
                                if($h['status'] == 'Uitbetaald') { 
                                    $status_badge = 'bg-blue-50 text-blue-700 border-blue-200'; 
                                    $icon = 'fa-money-bill-wave';
                                }
                                if($h['status'] == 'Ingediend') { 
                                    $status_badge = 'bg-yellow-50 text-yellow-700 border-yellow-200';
                                    $icon = 'fa-paper-plane';
                                }
                            ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-6 py-3 font-medium text-slate-700"><?php echo date('d-m-Y', strtotime($h['date'])); ?></td>
                                <td class="px-6 py-3 text-slate-600"><?php echo htmlspecialchars($h['route_name']); ?></td>
                                <td class="px-6 py-3 font-bold"><?php echo $h['hours_worked']; ?>u</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $status_badge; ?>">
                                        <i class="fa-solid <?php echo $icon; ?> mr-1.5"></i> <?php echo $h['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-300 p-6 h-fit">
                <h3 class="font-bold text-teal-700 mb-4 flex items-center">
                    <i class="fa-solid fa-pen-to-square mr-2"></i> Uren Indienen
                </h3>
                <p class="text-xs text-gray-500 mb-4">Vergeten te loggen? Dien hier handmatig in.</p>
                
                <form>
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Datum</label>
                        <input type="date" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-teal-500 focus:ring-0">
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Dienst</label>
                        <select class="w-full p-2 border border-gray-300 rounded text-sm bg-white focus:border-teal-500 focus:ring-0">
                            <option>Ochtendroute</option>
                            <option>Middagroute</option>
                            <option>Avondroute</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Aantal Uur</label>
                        <input type="number" step="0.5" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-teal-500 focus:ring-0" placeholder="bv. 8.0">
                    </div>
                    <button type="button" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 rounded shadow-sm opacity-50 cursor-not-allowed text-sm" title="Demo modus">
                        <i class="fa-solid fa-paper-plane mr-2"></i> Indienen
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="salaris" class="tab-content hidden">
        <div class="bg-white rounded-lg shadow-sm border border-gray-300 p-6">
            <h3 class="text-sm font-bold text-slate-700 uppercase mb-6 border-b border-gray-100 pb-2">
                <i class="fa-solid fa-file-invoice mr-2 text-slate-400"></i> Beschikbare Loonstroken
            </h3>
            
            <?php if(count($payslips) > 0): ?>
                <div class="space-y-4">
                    <?php foreach($payslips as $p): ?>
                        <div class="border border-gray-200 rounded-lg p-4 flex flex-col md:flex-row justify-between items-center hover:bg-slate-50 transition-colors group">
                            <div class="flex items-center mb-4 md:mb-0 w-full md:w-auto">
                                <div class="bg-green-100 text-green-600 h-12 w-12 rounded flex items-center justify-center text-xl mr-4 group-hover:bg-green-200 transition-colors">
                                    <i class="fa-solid fa-euro-sign"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-lg text-slate-800">Salaris <?php echo $p['month']; ?></h4>
                                    <p class="text-xs text-slate-500">Uitbetaald op: <?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between w-full md:w-auto space-x-6">
                                <div class="text-right">
                                    <div class="text-[10px] text-slate-400 uppercase font-bold">Netto</div>
                                    <div class="text-lg font-bold text-green-700">€ <?php echo number_format($p['net_salary'], 2, ',', '.'); ?></div>
                                </div>
                                <button class="bg-slate-800 hover:bg-slate-900 text-white px-4 py-2 rounded text-xs font-bold shadow flex items-center">
                                    <i class="fa-solid fa-download mr-2"></i> PDF
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 bg-slate-50 rounded border border-dashed border-gray-300 text-slate-400">
                    <i class="fa-regular fa-folder-open text-4xl mb-2"></i>
                    <p class="text-sm">Nog geen salarisstroken beschikbaar in dit systeem.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    function openTab(id, btn) {
        // Hide content
        document.querySelectorAll('.tab-content').forEach(e => {
            e.classList.add('hidden');
            e.classList.remove('block');
        });
        // Reset buttons
        document.querySelectorAll('.tab-btn').forEach(e => {
            e.classList.remove('active', 'border-teal-600', 'text-teal-800', 'bg-white', 'font-bold');
            e.classList.add('border-transparent', 'font-medium');
        });
        
        // Show active content
        document.getElementById(id).classList.remove('hidden');
        document.getElementById(id).classList.add('block');
        
        // Style active button
        btn.classList.remove('border-transparent', 'font-medium');
        btn.classList.add('active', 'border-teal-600', 'text-teal-800', 'bg-white', 'font-bold');
    }
</script>

<?php include '../../includes/footer.php'; ?>