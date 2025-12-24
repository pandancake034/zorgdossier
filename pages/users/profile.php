<?php
// pages/users/profile.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING & VALIDATIE
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

if (!isset($_GET['id'])) {
    echo "Geen ID opgegeven.";
    exit;
}

$user_id = $_GET['id'];

// 2. DATA OPHALEN
// We halen alles op uit users én nurse_profiles
$sql = "SELECT u.username, u.email, u.is_active, np.* FROM users u 
        LEFT JOIN nurse_profiles np ON u.id = np.user_id 
        WHERE u.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$nurse = $stmt->fetch();

if (!$nurse) {
    echo "<div class='p-4 bg-red-100 text-red-700'>Gebruiker niet gevonden.</div>";
    exit;
}

// 3. BEREKENINGEN (Handig voor management)
// Geschatte maandkosten = Uurloon * Uren per week * 4.33 (gemiddelde weken per maand)
$monthly_cost = 0;
if ($nurse['hourly_wage'] && $nurse['contract_hours']) {
    $monthly_cost = $nurse['hourly_wage'] * $nurse['contract_hours'] * 4.33;
}
?>

<div class="max-w-5xl mx-auto mb-10">
    
    <div class="flex justify-between items-center mb-6 print:hidden">
        <a href="index.php" class="text-teal-600 hover:underline flex items-center">
            ← Terug naar overzicht
        </a>
        <div class="space-x-2">
            <button onclick="window.print()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded inline-flex items-center">
                🖨️ Print Dossier
            </button>
            <a href="edit.php?id=<?php echo $user_id; ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded">
                ✏️ Bewerken
            </a>
        </div>
    </div>

    <div class="bg-white shadow-xl rounded-lg overflow-hidden border border-gray-200">
        
        <div class="bg-teal-700 p-8 text-white flex justify-between items-start">
            <div class="flex items-center space-x-6">
                <div class="h-24 w-24 bg-teal-500 rounded-full flex items-center justify-center text-4xl border-4 border-white shadow-lg">
                    <?php echo $nurse['gender'] == 'M' ? '👨🏾‍⚕️' : '👩🏾‍⚕️'; ?>
                </div>
                <div>
                    <h1 class="text-3xl font-bold">
                        <?php echo htmlspecialchars($nurse['first_name'] . ' ' . $nurse['last_name']); ?>
                    </h1>
                    <p class="text-teal-200 text-lg uppercase tracking-wide font-semibold mt-1">
                        <?php echo htmlspecialchars($nurse['job_title']); ?>
                    </p>
                    <div class="mt-3 flex space-x-3">
                        <span class="bg-teal-800 px-3 py-1 rounded text-xs border border-teal-600">
                            <?php echo htmlspecialchars($nurse['contract_type']); ?>
                        </span>
                        <?php if($nurse['is_active']): ?>
                            <span class="bg-green-500 text-white px-3 py-1 rounded text-xs font-bold">ACTIEF</span>
                        <?php else: ?>
                            <span class="bg-red-500 text-white px-3 py-1 rounded text-xs font-bold">INACTIEF</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="text-right opacity-75 hidden md:block">
                <h3 class="font-bold text-xl">Zorgdossier Suriname</h3>
                <p class="text-sm">Personeelsdossier</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3">
            
            <div class="p-8 bg-gray-50 border-r border-gray-200 md:col-span-1">
                <h3 class="text-gray-500 uppercase tracking-widest text-xs font-bold mb-4 border-b pb-2">Persoonlijke Gegevens</h3>
                
                <ul class="space-y-4 text-sm text-gray-700">
                    <li class="flex items-start">
                        <span class="w-8 text-xl">🎂</span>
                        <div>
                            <span class="block text-xs text-gray-500">Geboortedatum</span>
                            <?php echo date('d-m-Y', strtotime($nurse['dob'])); ?>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="w-8 text-xl">🚻</span>
                        <div>
                            <span class="block text-xs text-gray-500">Geslacht</span>
                            <?php echo $nurse['gender'] == 'M' ? 'Man' : 'Vrouw'; ?>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="w-8 text-xl">🚘</span>
                        <div>
                            <span class="block text-xs text-gray-500">Vervoer</span>
                            <?php echo $nurse['has_car'] ? 'Eigen Auto' : 'Geen auto'; ?>
                        </div>
                    </li>
                </ul>

                <h3 class="text-gray-500 uppercase tracking-widest text-xs font-bold mb-4 mt-8 border-b pb-2">Contact & Adres</h3>
                
                <ul class="space-y-4 text-sm text-gray-700">
                    <li class="flex items-start">
                        <span class="w-8 text-xl">📞</span>
                        <div>
                            <span class="block text-xs text-gray-500">Telefoon</span>
                            <?php echo htmlspecialchars($nurse['phone']); ?>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="w-8 text-xl">📧</span>
                        <div>
                            <span class="block text-xs text-gray-500">Email (Inlog)</span>
                            <?php echo htmlspecialchars($nurse['email']); ?>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <span class="w-8 text-xl">📍</span>
                        <div>
                            <span class="block text-xs text-gray-500">Woonadres</span>
                            <?php echo htmlspecialchars($nurse['address']); ?><br>
                            <?php echo htmlspecialchars($nurse['neighborhood']); ?><br>
                            <?php echo htmlspecialchars($nurse['district']); ?>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="p-8 md:col-span-2">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    <div>
                        <h3 class="text-teal-700 font-bold text-lg mb-4 flex items-center">
                            <span class="bg-teal-100 p-2 rounded-full mr-2">💼</span> Contract Details
                        </h3>
                        <div class="bg-white border rounded p-4 shadow-sm">
                            <div class="mb-3">
                                <span class="block text-xs text-gray-500 uppercase">Datum in dienst</span>
                                <span class="font-semibold text-gray-800"><?php echo date('d-m-Y', strtotime($nurse['date_employed'])); ?></span>
                            </div>
                            <div class="mb-3">
                                <span class="block text-xs text-gray-500 uppercase">Contracturen (p.w.)</span>
                                <span class="font-semibold text-gray-800"><?php echo number_format($nurse['contract_hours'], 2, ',', '.'); ?> uur</span>
                            </div>
                            <div>
                                <span class="block text-xs text-gray-500 uppercase">Type Dienstverband</span>
                                <span class="font-semibold text-gray-800"><?php echo $nurse['contract_type']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-teal-700 font-bold text-lg mb-4 flex items-center">
                            <span class="bg-yellow-100 p-2 rounded-full mr-2">💰</span> Salaris & Kosten
                        </h3>
                        <div class="bg-yellow-50 border border-yellow-200 rounded p-4">
                            <div class="flex justify-between mb-2 border-b border-yellow-200 pb-2">
                                <span class="text-sm text-gray-600">Uurloon</span>
                                <span class="font-bold text-gray-800">SRD <?php echo number_format($nurse['hourly_wage'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between mb-2 border-b border-yellow-200 pb-2">
                                <span class="text-sm text-gray-600">Reiskosten</span>
                                <span class="font-bold text-gray-800">SRD <?php echo number_format($nurse['travel_allowance'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between mt-3 pt-2">
                                <span class="text-sm font-bold text-gray-700">Est. Maandlasten</span>
                                <span class="font-bold text-teal-700 text-lg">SRD <?php echo number_format($monthly_cost, 2, ',', '.'); ?>*</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-2 italic">*Exclusief reiskosten, gebaseerd op 4.33 weken.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-teal-700 font-bold text-lg mb-2">📝 Bijzonderheden / Notities</h3>
                    <div class="bg-gray-50 p-4 rounded border border-gray-200 min-h-[100px] text-gray-700 italic">
                        <?php echo !empty($nurse['notes']) ? nl2br(htmlspecialchars($nurse['notes'])) : "Geen bijzonderheden genoteerd."; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
