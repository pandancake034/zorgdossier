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
$sql = "SELECT u.username, u.email, u.is_active, np.* FROM users u 
        LEFT JOIN nurse_profiles np ON u.id = np.user_id 
        WHERE u.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$nurse = $stmt->fetch();

if (!$nurse) {
    echo "<div class='p-4 bg-red-50 text-red-700 border-l-4 border-red-600'>Gebruiker niet gevonden.</div>";
    exit;
}

// 3. BEREKENINGEN
$monthly_cost = 0;
if ($nurse['hourly_wage'] && $nurse['contract_hours']) {
    $monthly_cost = $nurse['hourly_wage'] * $nurse['contract_hours'] * 4.33;
}
?>

<div class="max-w-5xl mx-auto mb-10">
    
    <div class="flex justify-between items-center mb-6 print:hidden">
        <a href="index.php" class="text-slate-500 hover:text-teal-700 font-bold text-sm uppercase flex items-center transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Terug naar overzicht
        </a>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-white border border-gray-300 text-slate-700 hover:bg-gray-50 font-bold py-2 px-4 text-sm shadow-sm flex items-center">
                <i class="fa-solid fa-print mr-2"></i> Printen
            </button>
            <a href="edit.php?id=<?php echo $user_id; ?>" class="bg-teal-700 hover:bg-teal-800 text-white font-bold py-2 px-4 text-sm shadow-sm flex items-center transition-colors">
                <i class="fa-solid fa-pen-to-square mr-2"></i> Bewerken
            </a>
        </div>
    </div>

    <div class="bg-white shadow-sm border border-gray-300">
        
        <div class="bg-teal-700 p-8 text-white flex flex-col md:flex-row justify-between items-start md:items-center">
            <div class="flex items-center gap-6">
                <div class="h-24 w-24 bg-teal-800 flex items-center justify-center text-4xl border-4 border-teal-600 shadow-sm text-teal-100">
                    <i class="fa-solid fa-user-nurse"></i>
                </div>
                
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        <?php echo htmlspecialchars($nurse['first_name'] . ' ' . $nurse['last_name']); ?>
                    </h1>
                    <p class="text-teal-200 text-sm uppercase tracking-wider font-bold mt-1 flex items-center">
                        <i class="fa-solid fa-id-badge mr-2"></i> <?php echo htmlspecialchars($nurse['job_title']); ?>
                    </p>
                    
                    <div class="mt-4 flex gap-3">
                        <span class="bg-teal-900 text-teal-100 px-3 py-1 text-xs border border-teal-600 font-bold uppercase">
                            <?php echo htmlspecialchars($nurse['contract_type']); ?>
                        </span>
                        <?php if($nurse['is_active']): ?>
                            <span class="bg-green-600 text-white px-3 py-1 text-xs font-bold uppercase border border-green-700">
                                <i class="fa-solid fa-check mr-1"></i> Actief
                            </span>
                        <?php else: ?>
                            <span class="bg-red-600 text-white px-3 py-1 text-xs font-bold uppercase border border-red-700">
                                <i class="fa-solid fa-ban mr-1"></i> Inactief
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="text-right hidden md:block opacity-80">
                <div class="text-2xl font-bold"><i class="fa-solid fa-folder-open"></i></div>
                <p class="text-xs uppercase tracking-widest mt-1">Personeelsdossier</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3">
            
            <div class="p-8 bg-slate-50 border-r border-gray-300 md:col-span-1">
                
                <h3 class="text-slate-400 uppercase tracking-widest text-xs font-bold mb-6 border-b border-gray-200 pb-2">
                    Persoonlijk
                </h3>
                
                <ul class="space-y-6 text-sm text-slate-700">
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-cake-candles text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Geboortedatum</span>
                            <span class="font-medium"><?php echo date('d-m-Y', strtotime($nurse['dob'])); ?></span>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-venus-mars text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Geslacht</span>
                            <span class="font-medium"><?php echo $nurse['gender'] == 'M' ? 'Man' : 'Vrouw'; ?></span>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-car text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Vervoer</span>
                            <span class="font-medium"><?php echo $nurse['has_car'] ? 'Eigen Auto' : 'Geen eigen vervoer'; ?></span>
                        </div>
                    </li>
                </ul>

                <h3 class="text-slate-400 uppercase tracking-widest text-xs font-bold mb-6 mt-10 border-b border-gray-200 pb-2">
                    Contact
                </h3>
                
                <ul class="space-y-6 text-sm text-slate-700">
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-phone text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Telefoon</span>
                            <span class="font-medium"><?php echo htmlspecialchars($nurse['phone']); ?></span>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-envelope text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Email</span>
                            <a href="mailto:<?php echo htmlspecialchars($nurse['email']); ?>" class="text-blue-600 hover:underline break-all font-medium">
                                <?php echo htmlspecialchars($nurse['email']); ?>
                            </a>
                        </div>
                    </li>
                    <li class="flex items-start">
                        <div class="w-8 text-slate-400 text-center mr-3"><i class="fa-solid fa-location-dot text-lg"></i></div>
                        <div>
                            <span class="block text-[10px] text-slate-400 uppercase font-bold">Adres</span>
                            <span class="font-medium block"><?php echo htmlspecialchars($nurse['address']); ?></span>
                            <span class="text-slate-500 block"><?php echo htmlspecialchars($nurse['neighborhood']); ?></span>
                            <span class="text-slate-500 block"><?php echo htmlspecialchars($nurse['district']); ?></span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="p-8 md:col-span-2">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                    
                    <div class="bg-white border border-gray-200 shadow-sm p-0">
                        <div class="bg-slate-50 px-4 py-3 border-b border-gray-200 flex items-center">
                            <i class="fa-solid fa-file-contract text-teal-600 mr-2"></i>
                            <h3 class="text-xs font-bold text-slate-700 uppercase">Contract Info</h3>
                        </div>
                        <div class="p-4 space-y-3">
                            <div>
                                <span class="block text-[10px] text-slate-400 uppercase font-bold">Datum in dienst</span>
                                <span class="font-bold text-slate-800"><?php echo date('d-m-Y', strtotime($nurse['date_employed'])); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-400 uppercase font-bold">Contracturen (p/w)</span>
                                <span class="font-bold text-slate-800"><?php echo number_format($nurse['contract_hours'], 1, ',', '.'); ?> uur</span>
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-400 uppercase font-bold">Dienstverband</span>
                                <span class="font-bold text-slate-800"><?php echo $nurse['contract_type']; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 shadow-sm p-0">
                        <div class="bg-blue-50 px-4 py-3 border-b border-blue-100 flex items-center">
                            <i class="fa-solid fa-money-bill-wave text-blue-600 mr-2"></i>
                            <h3 class="text-xs font-bold text-blue-900 uppercase">Financieel</h3>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between border-b border-gray-100 pb-2 mb-2">
                                <span class="text-xs text-slate-500 font-medium">Uurloon</span>
                                <span class="text-sm font-bold text-slate-800">SRD <?php echo number_format($nurse['hourly_wage'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 pb-2 mb-2">
                                <span class="text-xs text-slate-500 font-medium">Reiskosten</span>
                                <span class="text-sm font-bold text-slate-800">SRD <?php echo number_format($nurse['travel_allowance'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="flex justify-between pt-1">
                                <span class="text-xs text-slate-500 font-bold uppercase mt-1">Est. Maandlast</span>
                                <span class="text-lg font-bold text-blue-700">SRD <?php echo number_format($monthly_cost, 2, ',', '.'); ?></span>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1 text-right italic">*Indicatie op basis van 4.33 weken</p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-slate-700 font-bold text-sm mb-2 flex items-center uppercase tracking-wide">
                        <i class="fa-solid fa-note-sticky text-slate-400 mr-2"></i> Interne Notities
                    </h3>
                    <div class="bg-slate-50 p-5 border border-gray-200 text-sm text-slate-600 leading-relaxed font-mono">
                        <?php echo !empty($nurse['notes']) ? nl2br(htmlspecialchars($nurse['notes'])) : "<span class='text-slate-400 italic'>Geen notities aanwezig in dit dossier.</span>"; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>