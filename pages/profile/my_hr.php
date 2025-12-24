<?php
// pages/profile/my_hr.php
include '../../includes/header.php';
require '../../config/db.php';

// Check Zuster Rol
if ($_SESSION['role'] !== 'zuster' && $_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$nurse_id = $_SESSION['user_id'];
$current_month_name = date('F Y'); // Bijv. December 2025

// 1. ROOSTER OPHALEN (Komende 7 dagen)
// We combineren de vaste planning (roster) met echte datums
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

<style>
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s; }
    .tab-btn.active { border-bottom: 3px solid #0d9488; color: #0f766e; font-weight: bold; background-color: #f0fdfa; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="max-w-6xl mx-auto mb-12">

    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
        <div class="bg-teal-800 p-6 text-white flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold">💼 Mijn HR Portaal</h1>
                <p class="text-teal-200">Welkom, <?php echo htmlspecialchars($_SESSION['username']); ?>. Hier regelt u uw personeelszaken.</p>
            </div>
            <div class="text-right hidden md:block">
                <div class="text-3xl font-bold"><?php echo $total_hours; ?>u</div>
                <div class="text-sm text-teal-300">Gewerkt in <?php echo date('M'); ?></div>
            </div>
        </div>
        
        <div class="flex border-b border-gray-200 bg-gray-50 text-gray-600">
            <button onclick="openTab('rooster', this)" class="tab-btn active py-4 px-8 focus:outline-none hover:text-teal-600 transition">📅 Mijn Rooster</button>
            <button onclick="openTab('uren', this)" class="tab-btn py-4 px-8 focus:outline-none hover:text-teal-600 transition">⏱️ Urenregistratie</button>
            <button onclick="openTab('salaris', this)" class="tab-btn py-4 px-8 focus:outline-none hover:text-teal-600 transition">💰 Salaris & Stroken</button>
        </div>
    </div>

    <div id="rooster" class="tab-content active">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">📅 Komende 7 Dagen</h3>
            <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
                <?php foreach($upcoming_schedule as $day): 
                    $is_today = ($day['date'] == date('Y-m-d'));
                    $bg_color = $day['has_shift'] ? 'bg-teal-50 border-teal-200' : 'bg-gray-50 border-gray-100';
                    if($is_today) $bg_color = 'bg-blue-50 border-blue-300 ring-2 ring-blue-100';
                ?>
                    <div class="border rounded p-4 text-center <?php echo $bg_color; ?>">
                        <div class="text-xs font-bold text-gray-500 uppercase mb-1"><?php echo $day['day_nl']; ?></div>
                        <div class="text-lg font-bold text-gray-800 mb-2"><?php echo date('d M', strtotime($day['date'])); ?></div>
                        
                        <?php if($day['has_shift']): ?>
                            <div class="bg-teal-600 text-white text-xs py-1 px-2 rounded mb-1">
                                <?php echo $day['time']; ?>
                            </div>
                            <div class="text-xs text-gray-600 truncate" title="<?php echo $day['route']; ?>">
                                <?php echo $day['route']; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-400 text-sm py-2">Vrij</div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6 bg-blue-50 p-4 rounded border border-blue-200 text-blue-800 text-sm flex items-center">
                <span class="text-2xl mr-3">💡</span>
                Let op: Wijzigingen in het rooster moeten altijd via de planning (Avinash) worden aangevraagd.
            </div>
        </div>
    </div>

    <div id="uren" class="tab-content">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="md:col-span-2 bg-white rounded-lg shadow p-6">
                <h3 class="text-xl font-bold text-gray-800 mb-4">⏱️ Urenoverzicht</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-100 text-gray-600">
                            <tr>
                                <th class="px-4 py-2">Datum</th>
                                <th class="px-4 py-2">Route/Dienst</th>
                                <th class="px-4 py-2">Uren</th>
                                <th class="px-4 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($worked_hours as $h): 
                                $status_badge = 'bg-gray-100 text-gray-600';
                                if($h['status'] == 'Goedgekeurd') $status_badge = 'bg-green-100 text-green-700';
                                if($h['status'] == 'Uitbetaald') $status_badge = 'bg-blue-100 text-blue-700';
                                if($h['status'] == 'Ingediend') $status_badge = 'bg-yellow-100 text-yellow-700';
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?php echo date('d-m-Y', strtotime($h['date'])); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($h['route_name']); ?></td>
                                <td class="px-4 py-3 font-bold"><?php echo $h['hours_worked']; ?>u</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-bold <?php echo $status_badge; ?>">
                                        <?php echo $h['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 h-fit">
                <h3 class="font-bold text-teal-700 mb-4">📝 Uren Indienen</h3>
                <p class="text-sm text-gray-500 mb-4">Bent u vergeten uw uren te loggen? Dien ze hier handmatig in.</p>
                
                <form>
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Datum</label>
                        <input type="date" class="w-full p-2 border rounded">
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dienst</label>
                        <select class="w-full p-2 border rounded">
                            <option>Ochtendroute</option>
                            <option>Middagroute</option>
                            <option>Avondroute</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Aantal Uur</label>
                        <input type="number" step="0.5" class="w-full p-2 border rounded" placeholder="bv. 8.0">
                    </div>
                    <button type="button" class="w-full bg-teal-600 text-white font-bold py-2 rounded opacity-50 cursor-not-allowed" title="Demo modus">Indienen</button>
                </form>
            </div>
        </div>
    </div>

    <div id="salaris" class="tab-content">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-6">💰 Salarisstroken</h3>
            
            <?php if(count($payslips) > 0): ?>
                <div class="space-y-4">
                    <?php foreach($payslips as $p): ?>
                        <div class="border border-gray-200 rounded p-4 flex flex-col md:flex-row justify-between items-center hover:shadow-md transition">
                            <div class="flex items-center mb-4 md:mb-0">
                                <div class="bg-green-100 text-green-700 h-12 w-12 rounded flex items-center justify-center text-2xl mr-4">
                                    💶
                                </div>
                                <div>
                                    <h4 class="font-bold text-lg text-gray-800">Salaris <?php echo $p['month']; ?></h4>
                                    <p class="text-sm text-gray-500">Uitbetaald op: <?php echo date('d-m-Y', strtotime($p['payment_date'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="flex items-center space-x-6">
                                <div class="text-right">
                                    <div class="text-xs text-gray-500 uppercase font-bold">Netto Uitbetaling</div>
                                    <div class="text-xl font-bold text-green-700">€ <?php echo number_format($p['net_salary'], 2, ',', '.'); ?></div>
                                </div>
                                <button class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded text-sm font-bold shadow">
                                    ⬇ PDF
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 italic">Nog geen salarisstroken beschikbaar.</p>
            <?php endif; ?>
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
</script>

<?php include '../../includes/footer.php'; ?>