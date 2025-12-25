<?php
// pages/zorgplan/index.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. DATA OPHALEN: Hoofdoverzicht (Bestaande query, geoptimaliseerd)
// Telt taken per dagdeel per cliënt
$sql = "SELECT c.id, c.first_name, c.last_name, c.neighborhood, c.district, c.address,
               SUM(CASE WHEN t.time_of_day = 'Ochtend' AND t.is_active = 1 THEN 1 ELSE 0 END) as count_ochtend,
               SUM(CASE WHEN t.time_of_day = 'Middag' AND t.is_active = 1 THEN 1 ELSE 0 END) as count_middag,
               SUM(CASE WHEN t.time_of_day = 'Avond' AND t.is_active = 1 THEN 1 ELSE 0 END) as count_avond,
               SUM(CASE WHEN t.time_of_day = 'Nacht' AND t.is_active = 1 THEN 1 ELSE 0 END) as count_nacht,
               COUNT(t.id) as total_tasks
        FROM clients c
        LEFT JOIN client_care_tasks t ON c.id = t.client_id AND t.is_active = 1
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.neighborhood, c.last_name";

// 2. DATA OPHALEN: Laatst gewijzigde zorgplannen (Nieuw)
// We kijken naar het hoogste (nieuwste) taak ID per cliënt om te zien wie recent is bijgewerkt
$sql_recent = "SELECT c.id, c.first_name, c.last_name, c.neighborhood, MAX(t.id) as last_task_id, COUNT(t.id) as task_count
               FROM clients c
               JOIN client_care_tasks t ON c.id = t.client_id
               WHERE c.is_active = 1 AND t.is_active = 1
               GROUP BY c.id
               ORDER BY last_task_id DESC
               LIMIT 5";

try {
    $stmt = $pdo->query($sql);
    $clients = $stmt->fetchAll();

    $stmt_recent = $pdo->query($sql_recent);
    $recent_updates = $stmt_recent->fetchAll();
} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto mb-12">

    <div class="bg-white border border-gray-300 p-4 mb-6 flex flex-col md:flex-row justify-between items-center shadow-sm">
        <div class="mb-4 md:mb-0">
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight flex items-center">
                <i class="fa-solid fa-clipboard-list mr-3 text-slate-400"></i> Centraal Zorgplan
            </h1>
            <p class="text-xs text-slate-500 mt-1">Beheer en inzage in de zorgtaken per cliënt.</p>
        </div>
        
        <div class="relative w-full md:w-1/3">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
            </div>
            <input type="text" id="liveSearch" placeholder="Zoek op naam, wijk..." 
                   class="w-full p-2 pl-10 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0 rounded bg-slate-50 focus:bg-white transition-colors"
                   onkeyup="filterClients()">
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1">
            <div class="bg-white border border-gray-300 shadow-sm sticky top-4">
                <div class="bg-slate-50 px-5 py-3 border-b border-gray-300 flex items-center">
                    <i class="fa-solid fa-clock-rotate-left mr-2 text-slate-400"></i>
                    <h3 class="text-xs font-bold text-slate-700 uppercase">Laatst Gewijzigd</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="bg-white text-slate-400 border-b border-gray-100">
                                <th class="px-4 py-2 font-medium">Cliënt</th>
                                <th class="px-4 py-2 font-medium text-right">Taken</th>
                                <th class="px-4 py-2 font-medium text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if(count($recent_updates) > 0): foreach($recent_updates as $ru): ?>
                                <tr class="hover:bg-slate-50 transition-colors cursor-pointer" onclick="window.location='../clients/detail.php?id=<?php echo $ru['id']; ?>#zorgplan'">
                                    <td class="px-4 py-3">
                                        <span class="block font-bold text-slate-700"><?php echo htmlspecialchars($ru['last_name']); ?></span>
                                        <span class="text-[10px] text-slate-400 uppercase"><?php echo htmlspecialchars($ru['neighborhood']); ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <span class="bg-blue-100 text-blue-800 py-0.5 px-2 rounded-full font-bold text-[10px]">
                                            <?php echo $ru['task_count']; ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-400">
                                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                                    </td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="3" class="p-4 text-center text-slate-400 italic">Nog geen zorgplannen actief.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bg-slate-50 p-3 border-t border-gray-200 text-center">
                    <p class="text-[10px] text-slate-400">Gebaseerd op laatst toegevoegde taken</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2">
            <div id="clientGrid" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <?php foreach($clients as $c): ?>
                    <div class="client-card bg-white border border-gray-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all group overflow-hidden"
                         data-search="<?php echo strtolower($c['first_name'] . ' ' . $c['last_name'] . ' ' . $c['neighborhood'] . ' ' . $c['address']); ?>">
                        
                        <div class="p-4 flex justify-between items-start border-b border-gray-100 bg-gray-50/50">
                            <div>
                                <h3 class="font-bold text-slate-800 text-lg group-hover:text-blue-700 transition-colors">
                                    <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                                </h3>
                                <div class="flex items-center text-xs text-slate-500 mt-1">
                                    <i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i>
                                    <?php echo htmlspecialchars($c['neighborhood']); ?>
                                </div>
                            </div>
                            <div class="h-10 w-10 bg-white border border-gray-200 text-slate-500 rounded flex items-center justify-center font-bold text-sm shadow-sm group-hover:bg-blue-600 group-hover:text-white group-hover:border-blue-600 transition-colors">
                                <?php echo substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1); ?>
                            </div>
                        </div>

                        <div class="p-4">
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div class="bg-orange-50 border border-orange-100 rounded p-2 flex flex-col items-center justify-center">
                                    <span class="text-xs text-orange-400 mb-1"><i class="fa-regular fa-sun"></i></span>
                                    <span class="font-bold text-orange-800 text-lg leading-none"><?php echo $c['count_ochtend']; ?></span>
                                </div>
                                <div class="bg-yellow-50 border border-yellow-100 rounded p-2 flex flex-col items-center justify-center">
                                    <span class="text-xs text-yellow-500 mb-1"><i class="fa-solid fa-cloud-sun"></i></span>
                                    <span class="font-bold text-yellow-800 text-lg leading-none"><?php echo $c['count_middag']; ?></span>
                                </div>
                                <div class="bg-indigo-50 border border-indigo-100 rounded p-2 flex flex-col items-center justify-center">
                                    <span class="text-xs text-indigo-400 mb-1"><i class="fa-solid fa-moon"></i></span>
                                    <span class="font-bold text-indigo-800 text-lg leading-none"><?php echo $c['count_avond']; ?></span>
                                </div>
                                <div class="bg-slate-50 border border-slate-200 rounded p-2 flex flex-col items-center justify-center">
                                    <span class="text-xs text-slate-400 mb-1"><i class="fa-solid fa-bed"></i></span>
                                    <span class="font-bold text-slate-700 text-lg leading-none"><?php echo $c['count_nacht']; ?></span>
                                </div>
                            </div>
                        </div>

                        <a href="../clients/detail.php?id=<?php echo $c['id']; ?>#zorgplan" 
                           class="block bg-slate-50 hover:bg-blue-600 hover:text-white border-t border-gray-100 py-3 text-center text-xs font-bold text-slate-600 uppercase tracking-wide transition-colors">
                           Bekijk Plan <i class="fa-solid fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="noResults" class="hidden text-center py-12 bg-white border border-gray-200 border-dashed rounded">
                <div class="text-slate-300 text-4xl mb-3"><i class="fa-solid fa-magnifying-glass"></i></div>
                <p class="text-slate-500 font-medium">Geen cliënten gevonden met deze zoekterm.</p>
            </div>
        </div>

    </div>

</div>

<script>
    function filterClients() {
        // 1. Haal de zoekterm op
        let input = document.getElementById('liveSearch').value.toLowerCase();
        
        // 2. Haal alle kaarten op
        let cards = document.getElementsByClassName('client-card');
        let visibleCount = 0;

        // 3. Loop door alle kaarten
        for (let i = 0; i < cards.length; i++) {
            let searchableText = cards[i].getAttribute('data-search');
            
            // Check of de tekst overeenkomt
            if (searchableText.includes(input)) {
                cards[i].parentElement.style.display = ""; // Grid item tonen (fix voor grid layout flow)
                cards[i].style.display = ""; 
                visibleCount++;
            } else {
                cards[i].style.display = "none"; // Verberg card
                // Optioneel: verberg parent wrapper als je die gebruikt, hier niet nodig door grid structuur
            }
        }

        // 4. Toon melding als er niks is
        document.getElementById('noResults').style.display = (visibleCount === 0) ? "block" : "none";
        document.getElementById('clientGrid').style.display = (visibleCount === 0) ? "none" : "grid";
    }
</script>

<?php include '../../includes/footer.php'; ?>