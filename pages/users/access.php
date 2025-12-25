<?php
// pages/users/access.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

if (!isset($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
$user_id = $_GET['id'];

// 2. GEBRUIKER OPHALEN
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'familie') {
    die("Deze pagina is alleen voor familie-accounts.");
}

// 3. OPSLAAN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Eerst alle bestaande toegang verwijderen voor deze user
        $del = $pdo->prepare("DELETE FROM family_client_access WHERE user_id = ?");
        $del->execute([$user_id]);

        // Nu de aangevinkte cliënten toevoegen
        if (isset($_POST['clients']) && is_array($_POST['clients'])) {
            $ins = $pdo->prepare("INSERT INTO family_client_access (user_id, client_id) VALUES (?, ?)");
            foreach ($_POST['clients'] as $client_id) {
                $ins->execute([$user_id, $client_id]);
            }
        }

        $pdo->commit();
        $success = "Toegang succesvol bijgewerkt.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout: " . $e->getMessage();
    }
}

// 4. DATA OPHALEN (Cliënten + Huidige toegang)
// Alle cliënten
$clients = $pdo->query("SELECT * FROM clients WHERE is_active = 1 ORDER BY last_name")->fetchAll();

// Huidige toegang (array van client_ids)
$access_stmt = $pdo->prepare("SELECT client_id FROM family_client_access WHERE user_id = ?");
$access_stmt->execute([$user_id]);
$current_access = $access_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="w-full max-w-4xl mx-auto">

    <div class="bg-white border border-gray-300 p-4 mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Toegangsbeheer Familie</h1>
            <p class="text-xs text-slate-500">
                Koppel cliëntdossiers aan gebruiker: <strong class="text-slate-700"><?php echo htmlspecialchars($user['username']); ?></strong>
            </p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-slate-700 font-bold text-sm flex items-center">
            ← Terug
        </a>
    </div>

    <?php if(isset($success)): ?>
        <div class="bg-green-50 border-l-4 border-green-600 text-green-800 p-4 mb-4 text-sm font-bold">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white border border-gray-300 shadow-sm">
        <div class="bg-slate-100 px-5 py-3 border-b border-gray-300 flex justify-between items-center">
            <h3 class="text-xs font-bold text-slate-700 uppercase">Selecteer Dossiers</h3>
            <span class="text-xs text-slate-500">Vink aan om toegang te geven</span>
        </div>

        <div class="max-h-[500px] overflow-y-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 border-b border-gray-200 uppercase text-xs sticky top-0">
                    <tr>
                        <th class="px-4 py-3 w-16 text-center">Toegang</th>
                        <th class="px-4 py-3">Cliënt</th>
                        <th class="px-4 py-3">Locatie</th>
                        <th class="px-4 py-3">Geboortedatum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach($clients as $c): 
                        $has_access = in_array($c['id'], $current_access);
                        $bg_class = $has_access ? 'bg-blue-50' : 'hover:bg-slate-50';
                    ?>
                    <tr class="<?php echo $bg_class; ?> transition-colors cursor-pointer" onclick="document.getElementById('cb_<?php echo $c['id']; ?>').click()">
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" name="clients[]" value="<?php echo $c['id']; ?>" 
                                   id="cb_<?php echo $c['id']; ?>"
                                   class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                                   <?php if($has_access) echo 'checked'; ?>
                                   onclick="event.stopPropagation()">
                        </td>
                        <td class="px-4 py-3 font-bold text-slate-700">
                            <?php echo htmlspecialchars($c['last_name'] . ', ' . $c['first_name']); ?>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <?php echo htmlspecialchars($c['neighborhood']); ?>
                        </td>
                        <td class="px-4 py-3 text-slate-400 font-mono text-xs">
                            <?php echo date('d-m-Y', strtotime($c['dob'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 text-sm uppercase tracking-wide">
                Wijzigingen Opslaan
            </button>
        </div>
    </form>

</div>

<?php include '../../includes/footer.php'; ?>