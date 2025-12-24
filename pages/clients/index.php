<?php
// pages/clients/index.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING
// Iedereen (Management & Zuster) mag dit zien, behalve familie.
if ($_SESSION['role'] === 'familie') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// 2. FILTERS OPHALEN
$search = $_GET['search'] ?? '';
$filter_district = $_GET['district'] ?? '';
$filter_wijk = $_GET['wijk'] ?? '';

// 3. QUERY BOUWEN
// We beginnen met een basis query en plakken er stukjes aan vast (WHERE ...)
$sql = "SELECT * FROM clients WHERE is_active = 1";
$params = [];

if ($search) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_district) {
    $sql .= " AND district = ?";
    $params[] = $filter_district;
}

if ($filter_wijk) {
    $sql .= " AND neighborhood = ?";
    $params[] = $filter_wijk;
}

$sql .= " ORDER BY last_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Fout: " . $e->getMessage());
}

// Helper functie voor leeftijd
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y;
}
?>

<div class="bg-white rounded-lg shadow-lg p-6 min-h-screen">

    <div class="flex flex-col md:flex-row justify-between items-center mb-6 border-b pb-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">📂 Cliënten Dossiers</h2>
            <p class="text-gray-600">Zoek en filter door het cliëntenbestand.</p>
        </div>
        
        <?php if($_SESSION['role'] === 'management'): ?>
        <a href="create.php" class="mt-4 md:mt-0 bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded shadow flex items-center">
            <span class="mr-2">➕</span> Nieuwe Cliënt (Intake)
        </a>
        <?php endif; ?>
    </div>

    <form method="GET" class="bg-gray-50 p-4 rounded border border-gray-200 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
        
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Zoek Naam</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="bv. Carmen..." class="w-full p-2 border rounded focus:ring-teal-500 focus:border-teal-500">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">District</label>
            <select name="district" class="w-full p-2 border rounded bg-white">
                <option value="">-- Alle Districten --</option>
                <?php 
                $districten = ['Paramaribo','Wanica','Commewijne','Para','Saramacca','Marowijne','Coronie','Nickerie','Brokopondo','Sipaliwini'];
                foreach($districten as $d) {
                    $selected = ($filter_district == $d) ? 'selected' : '';
                    echo "<option value='$d' $selected>$d</option>";
                }
                ?>
            </select>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Wijk</label>
            <select name="wijk" class="w-full p-2 border rounded bg-white">
                <option value="">-- Alle Wijken --</option>
                <?php 
                $wijken = ['Centrum','Beekhuizen','Blauwgrond','Flora','Latour','Livorno','Munder','Pontbuiten','Rainville','Tammenga','Weg naar See','Welgelegen','Overig'];
                foreach($wijken as $w) {
                    $selected = ($filter_wijk == $w) ? 'selected' : '';
                    echo "<option value='$w' $selected>$w</option>";
                }
                ?>
            </select>
        </div>

        <div class="flex items-end space-x-2">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 w-full">
                🔍 Zoek
            </button>
            <a href="index.php" class="bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded hover:bg-gray-400 text-center" title="Filters wissen">
                ✕
            </a>
        </div>
    </form>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                <tr>
                    <th class="py-3 px-6 text-left">Cliënt</th>
                    <th class="py-3 px-6 text-left">Leeftijd</th>
                    <th class="py-3 px-6 text-left">Adres & Wijk</th>
                    <th class="py-3 px-6 text-left">Type Woning</th>
                    <th class="py-3 px-6 text-center">Dossier</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                
                <?php if (count($clients) > 0): ?>
                    <?php foreach ($clients as $client): ?>
                        <tr class="border-b border-gray-200 hover:bg-teal-50 transition duration-150">
                            
                            <td class="py-4 px-6 text-left whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="mr-3 font-bold text-lg text-teal-700 bg-teal-100 h-10 w-10 flex items-center justify-center rounded-full">
                                        <?php echo substr($client['first_name'], 0, 1) . substr($client['last_name'], 0, 1); ?>
                                    </div>
                                    <div>
                                        <span class="font-bold text-gray-800 text-base">
                                            <?php echo htmlspecialchars($client['last_name'] . ', ' . $client['first_name']); ?>
                                        </span>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $client['gender'] == 'M' ? 'Man' : 'Vrouw'; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-left">
                                <span class="font-semibold text-gray-700 text-base">
                                    <?php echo calculateAge($client['dob']); ?> jaar
                                </span>
                                <div class="text-xs text-gray-400">
                                    <?php echo date('d-m-Y', strtotime($client['dob'])); ?>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-left">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($client['address']); ?></div>
                                <div class="flex items-center mt-1">
                                    <span class="bg-gray-200 text-gray-600 py-1 px-2 rounded text-xs mr-2">
                                        <?php echo htmlspecialchars($client['neighborhood']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($client['district']); ?></span>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-left">
                                <?php 
                                    $bg = 'bg-gray-100 text-gray-600';
                                    if($client['housing_type'] == 'Zorginstelling') $bg = 'bg-orange-100 text-orange-600';
                                    if($client['housing_type'] == 'Appartement') $bg = 'bg-blue-100 text-blue-600';
                                    if($client['housing_type'] == 'Woonhuis') $bg = 'bg-green-100 text-green-600';
                                ?>
                                <span class="<?php echo $bg; ?> py-1 px-3 rounded-full text-xs font-bold border border-opacity-20">
                                    <?php echo htmlspecialchars($client['housing_type']); ?>
                                </span>
                                <div class="text-xs text-gray-400 mt-1 pl-1">
                                    <?php echo htmlspecialchars($client['floor_level']); ?>
                                </div>
                            </td>

                            <td class="py-4 px-6 text-center">
                                <a href="detail.php?id=<?php echo $client['id']; ?>" class="bg-teal-600 text-white py-2 px-4 rounded hover:bg-teal-700 font-bold shadow transition transform hover:scale-105 flex items-center justify-center">
                                    Open Dossier ➝
                                </a>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="py-8 text-center text-gray-500">
                            <span class="text-4xl block mb-2">🔍</span>
                            Geen cliënten gevonden met deze filters.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
