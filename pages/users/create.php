<?php
// pages/users/create.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of Management is ingelogd
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// Haal cliënten op voor de familie-koppeling
$clients = $pdo->query("SELECT id, first_name, last_name, district FROM clients WHERE is_active = 1 ORDER BY last_name")->fetchAll();

// VERWERKEN VAN HET FORMULIER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. BASIS USER AANMAKEN
        $username = trim($_POST['username']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Wachtwoord hashen
        $role     = $_POST['role'];
        $email    = !empty($_POST['email']) ? $_POST['email'] : $username . '@zorgdossier.sr';

        // Check of gebruikersnaam al bestaat
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if($check->rowCount() > 0) {
            throw new Exception("Gebruikersnaam '$username' bestaat al.");
        }

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$username, $password, $email, $role]);
        $user_id = $pdo->lastInsertId();

        // 2. ROL SPECIFIEKE ACTIES
        if ($role === 'zuster') {
            // ... (Bestaande logica voor zusters) ...
            $contract_hours   = !empty($_POST['contract_hours']) ? $_POST['contract_hours'] : 0.00;
            $hourly_wage      = !empty($_POST['hourly_wage']) ? $_POST['hourly_wage'] : 0.00;
            $travel_allowance = !empty($_POST['travel_allowance']) ? $_POST['travel_allowance'] : 0.00;
            $date_employed    = !empty($_POST['date_employed']) ? $_POST['date_employed'] : date('Y-m-d');
            $has_car          = isset($_POST['has_car']) ? 1 : 0; 

            $sql_profile = "INSERT INTO nurse_profiles 
            (user_id, first_name, last_name, gender, dob, address, city, district, neighborhood, phone, job_title, contract_type, date_employed, contract_hours, hourly_wage, travel_allowance, has_car, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_profile = $pdo->prepare($sql_profile);
            $stmt_profile->execute([
                $user_id, $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'],
                $_POST['address'], $_POST['city'], $_POST['district'], $_POST['neighborhood'], $_POST['phone'],
                $_POST['job_title'], $_POST['contract_type'], $date_employed, $contract_hours,
                $hourly_wage, $travel_allowance, $has_car, $_POST['notes']
            ]);

        } elseif ($role === 'familie') {
            // NIEUW: Direct koppelen aan geselecteerde cliënten
            if (isset($_POST['linked_clients']) && is_array($_POST['linked_clients'])) {
                $link_stmt = $pdo->prepare("INSERT INTO family_client_access (user_id, client_id) VALUES (?, ?)");
                foreach ($_POST['linked_clients'] as $client_id) {
                    $link_stmt->execute([$user_id, $client_id]);
                }
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='index.php?success=1';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}
?>

<div class="w-full max-w-5xl mx-auto mb-12">
    
    <div class="bg-white border border-gray-300 p-4 mb-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Nieuwe Gebruiker</h1>
            <p class="text-xs text-slate-500">Maak een account aan voor personeel of familie</p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-red-700 font-bold text-sm uppercase flex items-center">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            Annuleren
        </a>
    </div>

    <?php if(isset($error)): ?>
        <div class="bg-red-50 border-l-4 border-red-600 text-red-800 p-4 text-sm font-medium mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white border border-gray-300 shadow-sm">
        
        <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">1. Login & Rol</h3>
        </div>
        
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gebruikersnaam *</label>
                <input type="text" name="username" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" required placeholder="bv. jansen01">
                <p class="text-[10px] text-slate-400 mt-1">Dit gebruikt de persoon om in te loggen.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Wachtwoord *</label>
                <input type="password" name="password" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" required placeholder="********">
                <p class="text-[10px] text-slate-400 mt-1">Geef dit wachtwoord door aan de gebruiker.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Adres</label>
                <input type="email" name="email" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="naam@email.com">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rol / Type Account *</label>
                <select name="role" id="roleSelect" class="w-full p-2 border border-gray-300 text-sm bg-blue-50 font-bold focus:border-blue-500 focus:ring-0" onchange="toggleFields()">
                    <option value="management">Management</option>
                    <option value="zuster" selected>Zuster (Zorgpersoneel)</option>
                    <option value="familie">Familie (Toegang tot dossier)</option>
                </select>
            </div>
        </div>

        <div id="nurseFields" class="border-t border-gray-200 block">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">2. HR Profiel (Alleen voor Zorgpersoneel)</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Voornaam</label><input type="text" name="first_name" class="w-full p-2 border border-gray-300 text-sm"></div>
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Achternaam</label><input type="text" name="last_name" class="w-full p-2 border border-gray-300 text-sm"></div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Functie</label><select name="job_title" class="w-full p-2 border border-gray-300 text-sm"><option>Verzorgende-IG</option><option>Zuster</option></select></div>
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Uurloon</label><input type="number" name="hourly_wage" class="w-full p-2 border border-gray-300 text-sm" placeholder="0.00"></div>
                    <div><label class="block text-xs font-bold text-slate-500 mb-1">Telefoon</label><input type="text" name="phone" class="w-full p-2 border border-gray-300 text-sm"></div>
                </div>
                <input type="hidden" name="gender" value="V"><input type="hidden" name="contract_type" value="Vast">
            </div>
        </div>

        <div id="familyFields" class="border-t border-gray-200 hidden">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">2. Koppel Cliënt(en)</h3>
            </div>
            
            <div class="p-6">
                <p class="text-sm text-slate-600 mb-4">Vink aan welke cliënt(en) dit familielid mag inzien:</p>
                
                <div class="h-64 overflow-y-auto border border-gray-300 bg-gray-50 p-2">
                    <?php foreach($clients as $c): ?>
                        <label class="flex items-center p-2 hover:bg-white border-b border-gray-100 cursor-pointer">
                            <input type="checkbox" name="linked_clients[]" value="<?php echo $c['id']; ?>" class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-0 mr-3">
                            <div>
                                <span class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($c['last_name'] . ', ' . $c['first_name']); ?></span>
                                <span class="text-xs text-slate-400 ml-2">(<?php echo htmlspecialchars($c['district']); ?>)</span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 text-sm uppercase tracking-wide transition-colors shadow-sm">
                Gebruiker Opslaan
            </button>
        </div>

    </form>
</div>

<script>
    function toggleFields() {
        var role = document.getElementById('roleSelect').value;
        var nurseFields = document.getElementById('nurseFields');
        var familyFields = document.getElementById('familyFields');
        
        if (role === 'zuster') {
            nurseFields.style.display = 'block';
            familyFields.style.display = 'none';
        } else if (role === 'familie') {
            nurseFields.style.display = 'none';
            familyFields.style.display = 'block';
        } else {
            // Management
            nurseFields.style.display = 'none';
            familyFields.style.display = 'none';
        }
    }
    // Direct uitvoeren bij laden
    document.addEventListener("DOMContentLoaded", function() {
        toggleFields();
    });
</script>

<?php include '../../includes/footer.php'; ?>