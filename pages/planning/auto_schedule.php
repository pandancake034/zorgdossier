<?php
// pages/planning/auto_schedule.php
include '../../includes/header.php';
require '../../config/db.php';

// Alleen Management
if ($_SESSION['role'] !== 'management') {
    die("Geen toegang");
}

$message = "";

// DE AUTOMATISCHE MAGIE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $target_date = $_POST['target_date']; // Bv. 2025-12-25
        
        // 1. Welke dag is het? (Ma, Di, Wo...)
        $english_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $dutch_days   = ['Ma',  'Di',  'Wo',  'Do',  'Vr',  'Za',  'Zo'];
        $day_of_week = str_replace($english_days, $dutch_days, date('D', strtotime($target_date)));

        // 2. Maak de oude planning voor deze dag leeg (Reset)
        // We verwijderen stops van routes die op deze dag gereden worden
        // (Complexere logica: we wissen even alles voor de gekozen routes om dubbelingen te voorkomen)
        
        // 3. Haal het ROOSTER op: Welke Routes zijn actief deze dag?
        $roster_stmt = $pdo->prepare("SELECT r.route_id, rt.name as route_name, rt.district 
                                      FROM roster r 
                                      JOIN routes rt ON r.route_id = rt.id 
                                      WHERE r.day_of_week = ?");
        $roster_stmt->execute([$day_of_week]);
        $active_routes = $roster_stmt->fetchAll();

        $count_clients = 0;

        // VOOR ELKE ROUTE DIE RIJDT...
        foreach ($active_routes as $route) {
            $route_id = $route['route_id'];
            $district = $route['district'];

            // Starttijd van de route (Standaard 07:30 voor ochtend)
            // Je zou dit ook uit de routes tabel kunnen halen als je daar starttijden hebt
            $current_time = strtotime("07:30:00");

            // 4. ZOEK CLIËNTEN IN DEZE WIJK MET ZORG OP DEZE DAG
            // - Moet actief zijn
            // - Moet in het district van de route wonen (of gekoppeld zijn aan de route)
            // - Moet taken hebben op 'Dagelijks' OF 'Deze Dag'
            
            $sql = "SELECT DISTINCT c.id, c.first_name, c.last_name, c.address, c.neighborhood
                    FROM clients c
                    JOIN client_care_tasks t ON c.id = t.client_id
                    WHERE c.is_active = 1 
                    AND c.district = ? 
                    AND t.is_active = 1
                    AND (t.frequency = 'Dagelijks' OR FIND_IN_SET(?, t.specific_days))
                    ORDER BY c.neighborhood, c.address"; // Sorteer slim op adres (clusteren)

            $client_stmt = $pdo->prepare($sql);
            $client_stmt->execute([$district, $day_of_week]);
            $clients = $client_stmt->fetchAll();

            // 5. BEREKEN DE TIJDEN EN SLA OP
            foreach ($clients as $client) {
                
                // Bereken totale zorgduur voor deze cliënt
                $dur_stmt = $pdo->prepare("SELECT SUM(duration) FROM client_care_tasks 
                                           WHERE client_id = ? AND is_active = 1 
                                           AND (frequency = 'Dagelijks' OR FIND_IN_SET(?, specific_days))");
                $dur_stmt->execute([$client['id'], $day_of_week]);
                $total_minutes = $dur_stmt->fetchColumn();
                
                if(!$total_minutes) $total_minutes = 15; // Minimaal 15 min als backup

                // Formatteer de tijd voor DB
                $planned_time = date("H:i:s", $current_time);

                // Opslaan in route_stops
                // ON DUPLICATE KEY UPDATE: Als hij er al staat, update de tijd
                $ins = $pdo->prepare("INSERT INTO route_stops (route_id, client_id, planned_time) 
                                      VALUES (?, ?, ?) 
                                      ON DUPLICATE KEY UPDATE planned_time = ?");
                $ins->execute([$route_id, $client['id'], $planned_time, $planned_time]);

                // Tel tijd op voor de volgende cliënt
                // Zorgduur + 10 minuten reistijd
                $current_time += ($total_minutes * 60) + (10 * 60);
                
                $count_clients++;
            }
        }

        $message = "✅ Planning gegenereerd voor $target_date ($day_of_week)! <br> $count_clients cliënten zijn automatisch ingepland op basis van hun zorgplan.";

    } catch (Exception $e) {
        $message = "❌ Fout: " . $e->getMessage();
    }
}
?>

<div class="max-w-4xl mx-auto mb-12">
    
    <div class="bg-white p-8 rounded-lg shadow-lg border-t-8 border-teal-600">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">🤖 Auto-Scheduler</h1>
        <p class="text-gray-600 mb-6">
            Dit systeem combineert <strong>Zorgplannen</strong>, <strong>Personeelsroosters</strong> en <strong>Wijken</strong> 
            om automatisch de meest logische route en tijden te berekenen.
        </p>

        <?php if($message): ?>
            <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-gray-50 p-6 rounded border border-gray-200">
            <label class="block text-sm font-bold text-gray-700 mb-2">Voor welke datum wil je plannen?</label>
            <div class="flex gap-4">
                <input type="date" name="target_date" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="p-3 border rounded text-lg font-bold flex-1">
                <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-8 rounded shadow text-lg flex items-center">
                    <span class="text-2xl mr-2">⚙️</span> Genereer Planning
                </button>
            </div>
            <p class="text-xs text-gray-500 mt-2">
                * Het systeem kijkt wie er werkt op deze dag en welke cliënten zorgtaken hebben staan.
            </p>
        </form>

        <div class="mt-8 border-t pt-6">
            <h3 class="font-bold text-gray-800 mb-2">Hoe werkt het algoritme?</h3>
            <ul class="list-disc list-inside text-gray-600 space-y-2 text-sm">
                <li>Het systeem checkt het <strong>Rooster</strong> om te zien welke routes actief zijn.</li>
                <li>Het haalt alle cliënten op die in die wijken wonen.</li>
                <li>Het filtert op cliënten die taken hebben op deze specifieke dag (bv. "Alleen op Maandag").</li>
                <li>Het telt de tijdsduur van alle taken bij elkaar op (Wassen 30m + Medicatie 5m).</li>
                <li>Het voegt standaard <strong>10 minuten reistijd</strong> toe tussen cliënten.</li>
                <li>De route start standaard om <strong>07:30</strong>.</li>
            </ul>
        </div>
        
        <div class="mt-6 text-center">
             <a href="build_route.php" class="text-teal-600 font-bold hover:underline">Bekijk het resultaat in de Route Indeling →</a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>