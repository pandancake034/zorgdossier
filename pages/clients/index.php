<?php
// pages/clients/index.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING & ROL CHECK
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// 2. FILTERS
$search = $_GET['search'] ?? '';
$filter_district = $_GET['district'] ?? '';
$filter_wijk = $_GET['wijk'] ?? '';

// 3. QUERY OPBOUWEN
$sql = "SELECT c.* FROM clients c ";
$params = [];

// -> SPECIFIEKE LOGICA VOOR FAMILIE (NIEUW)
if ($role === 'familie') {
    // Alleen clients tonen die in de access tabel staan voor deze user_id
    $sql .= " JOIN family_client_access a ON c.id = a.client_id WHERE c.is_active = 1 AND a.user_id = ? ";
    $params[] = $user_id;
    
} else {
    // Management/Zuster filters
    $sql .= " WHERE c.is_active = 1 ";
    
    if ($search) {
        $sql .= " AND (c.first_name LIKE ? OR c.last_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($filter_district) {
        $sql .= " AND c.district = ?";
        $params[] = $filter_district;
    }
    if ($filter_wijk) {
        $sql .= " AND c.neighborhood = ?";
        $params[] = $filter_wijk;
    }
}

$sql .= " ORDER BY c.last_name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
    
    // UX Verbetering: Als familie maar 1 resultaat heeft (en geen filters), stuur direct door!
    if ($role === 'familie' && count($clients) === 1 && empty($_GET)) {
        $cid = $clients[0]['id'];
        echo "<script>window.location.href='detail.php?id=$cid';</script>";
        exit;
    }

} catch (PDOException $e) {
    die("Fout: " . $e->getMessage());
}

function calculateAge($dob) {
    return (new DateTime($dob))->diff(new DateTime('today'))->y;
}
?>

<div class="w-full max-w-7xl mx-auto">

    <div class="bg-white border border-gray-300 p-4 mb-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">
                <?php echo ($role === 'familie') ? 'Mijn Familie' : 'Cliënten Dossiers'; ?>
            </h1>
            <p class="text-xs text-slate-500">
                <?php echo ($role === 'familie') ? 'Selecteer een dossier om te bekijken' : 'Overzicht en beheer van cliënten'; ?>
            </p>
        </div>
        <?php if($role === 'management'): ?>
        <a href="create.php" class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium py-2 px-4 flex items-center transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Nieuwe Cliënt
        </a>
        <?php endif; ?>
    </div>

    <?php if($role !== 'familie'): ?>
    <form method="GET" class="bg-slate-100 border border-gray-300 p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Zoeken</label>
            <div class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Naam..." class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">District</label>
            <select name="district" class="w-full p-2 border border-gray-300 text-sm bg-white focus:border-blue-500 focus:ring-0">
                <option value="">Alle Districten</option>
                <?php 
                $districten = ['Paramaribo','Wanica','Commewijne','Para','Saramacca','Marowijne','Coronie','Nickerie','Brokopondo','Sipaliwini'];
                foreach($districten as $d) echo "<option value='$d' ".($filter_district == $d ? 'selected' : '').">$d</option>";
                ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Wijk</label>
            <select name="wijk" class="w-full p-2 border border-gray-300 text-sm bg-white focus:border-blue-500 focus:ring-0">
                <option value="">Alle Wijken</option>
                <?php 
                $wijken = ['Centrum','Beekhuizen','Blauwgrond','Flora','Latour','Livorno','Munder','Pontbuiten','Rainville','Tammenga','Weg naar See','Welgelegen','Overig'];
                foreach($wijken as $w) echo "<option value='$w' ".($filter_wijk == $w ? 'selected' : '').">$w</option>";
                ?>
            </select>
        </div>
        <div class="flex space-x-2">
            <button type="submit" class="bg-slate-700 hover:bg-slate-800 text-white font-medium py-2 px-6 text-sm flex-1">Filteren</button>
            <a href="index.php" class="bg-white border border-gray-300 text-slate-700 hover:bg-gray-50 font-medium py-2 px-4 text-sm flex items-center justify-center">Reset</a>
        </div>
    </form>
    <?php endif; ?>

    <div class="bg-white border border-gray-300 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 border-b border-gray-300 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 font-bold w-12">#</th>
                    <th class="px-4 py-3 font-bold">Cliëntnaam</th>
                    <th class="px-4 py-3 font-bold">Leeftijd</th>
                    <th class="px-4 py-3 font-bold">Locatie</th>
                    <th class="px-4 py-3 font-bold text-right">Actie</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if (count($clients) > 0): ?>
                    <?php foreach ($clients as $client): ?>
                        <tr class="hover:bg-blue-50/50 transition-colors group">
                            <td class="px-4 py-3 text-slate-400 font-mono text-xs"><?php echo $client['id']; ?></td>
                            
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-800 text-base">
                                    <?php echo htmlspecialchars($client['last_name'] . ', ' . $client['first_name']); ?>
                                </div>
                            </td>

                            <td class="px-4 py-3">
                                <span class="text-slate-700"><?php echo calculateAge($client['dob']); ?> jaar</span>
                                <div class="text-xs text-slate-400"><?php echo date('d-m-Y', strtotime($client['dob'])); ?></div>
                            </td>

                            <td class="px-4 py-3">
                                <div class="text-slate-800 font-medium"><?php echo htmlspecialchars($client['address']); ?></div>
                                <div class="text-xs text-slate-500">
                                    <?php echo htmlspecialchars($client['neighborhood']); ?>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a href="detail.php?id=<?php echo $client['id']; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-bold text-xs uppercase tracking-wide border border-transparent hover:border-blue-200 px-3 py-1 transition-all">
                                    Bekijk Dossier
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center">
                            <?php if($role === 'familie'): ?>
                                <div class="text-slate-400 mb-2">
                                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                </div>
                                <h3 class="text-lg font-bold text-slate-700">Geen cliënt gekoppeld</h3>
                                <p class="text-slate-500 text-sm mt-2">
                                    We konden geen dossier vinden dat gekoppeld is aan uw account (ID: <?php echo $user_id; ?>). <br>
                                    Neem contact op met de administratie.
                                </p>
                            <?php else: ?>
                                <span class="text-slate-500 italic">Geen resultaten gevonden.</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>