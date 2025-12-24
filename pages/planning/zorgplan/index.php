<?php
// pages/zorgplan/index.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. DATA OPHALEN
// We halen alle actieve cliënten op.
// We gebruiken een SLIMME QUERY om meteen te tellen hoeveel taken ze hebben per dagdeel.
// Dit voorkomt dat we 100x naar de database moeten vragen.

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

try {
    $stmt = $pdo->query($sql);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database fout: " . $e->getMessage());
}
?>

<div class="max-w-7xl mx-auto mb-12">

    <div class="flex flex-col md:flex-row justify-between items-center mb-8 bg-purple-700 p-6 rounded-lg shadow-lg text-white">
        <div>
            <h2 class="text-3xl font-bold">📋 Centraal Zorgplan Overzicht</h2>
            <p class="text-purple-200">Zoek en bekijk de zorgtaken per cliënt.</p>
        </div>
        
        <div class="mt-4 md:mt-0 w-full md:w-1/3 relative">
            <input type="text" id="liveSearch" placeholder="🔍 Zoek op naam, wijk of adres..." 
                   class="w-full p-3 pl-10 rounded text-gray-800 font-bold focus:ring-4 focus:ring-purple-300 outline-none"
                   onkeyup="filterClients()">
        </div>
    </div>

    <div id="clientGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <?php foreach($clients as $c): ?>
            <div class="client-card bg-white rounded-lg shadow-md hover:shadow-xl transition transform hover:-translate-y-1 border-t-4 border-purple-500 overflow-hidden"
                 data-search="<?php echo strtolower($c['first_name'] . ' ' . $c['last_name'] . ' ' . $c['neighborhood'] . ' ' . $c['address']); ?>">
                
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold text-xl text-gray-800">
                                <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                            </h3>
                            <p class="text-sm text-gray-500 font-semibold">
                                📍 <?php echo htmlspecialchars($c['neighborhood']); ?>
                            </p>
                            <p class="text-xs text-gray-400">
                                <?php echo htmlspecialchars($c['address']); ?>
                            </p>
                        </div>
                        
                        <div class="bg-purple-100 text-purple-700 h-10 w-10 rounded-full flex items-center justify-center font-bold text-lg">
                            <?php echo substr($c['first_name'], 0, 1) . substr($c['last_name'], 0, 1); ?>
                        </div>
                    </div>

                    <hr class="my-4 border-gray-100">

                    <div class="grid grid-cols-4 gap-2 text-center text-xs">
                        <div class="bg-orange-50 p-2 rounded text-orange-700">
                            <span class="block text-lg font-bold"><?php echo $c['count_ochtend']; ?></span>
                            🌅 Ochtend
                        </div>
                        <div class="bg-yellow-50 p-2 rounded text-yellow-700">
                            <span class="block text-lg font-bold"><?php echo $c['count_middag']; ?></span>
                            ☀️ Middag
                        </div>
                        <div class="bg-indigo-50 p-2 rounded text-indigo-700">
                            <span class="block text-lg font-bold"><?php echo $c['count_avond']; ?></span>
                            🌙 Avond
                        </div>
                        <div class="bg-gray-100 p-2 rounded text-gray-600">
                            <span class="block text-lg font-bold"><?php echo $c['count_nacht']; ?></span>
                            🌑 Nacht
                        </div>
                    </div>
                </div>

                <a href="../clients/detail.php?id=<?php echo $c['id']; ?>#zorgplan" 
                   class="block bg-gray-50 hover:bg-purple-600 hover:text-white text-center py-3 text-sm font-bold text-gray-600 transition border-t">
                   Bekijk volledig Zorgplan ➝
                </a>
            </div>
            <?php endforeach; ?>

    </div>
    
    <div id="noResults" class="hidden text-center py-12 text-gray-400">
        <p class="text-xl">🔍 Geen cliënten gevonden met deze zoekterm.</p>
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
                cards[i].style.display = ""; // Toon
                visibleCount++;
            } else {
                cards[i].style.display = "none"; // Verberg
            }
        }

        // 4. Toon melding als er niks is
        document.getElementById('noResults').style.display = (visibleCount === 0) ? "block" : "none";
    }
</script>

<?php include '../../includes/footer.php'; ?>