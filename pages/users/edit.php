<?php
// pages/users/edit.php
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

$edit_user_id = $_GET['id'];
$success = "";
$error = "";

// 2. DATA OPSLAAN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // A. BASIS GEGEVENS UPDATE
        $email = trim($_POST['email']);
        $role  = $_POST['role'];
        $is_active = $_POST['is_active'];
        
        // Wachtwoord alleen updaten indien ingevuld
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET email=?, role=?, is_active=?, password=? WHERE id=?");
            $stmt->execute([$email, $role, $is_active, $password, $edit_user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email=?, role=?, is_active=? WHERE id=?");
            $stmt->execute([$email, $role, $is_active, $edit_user_id]);
        }

        // B. ZUSTER PROFIEL UPDATE
        if ($role === 'zuster') {
            $has_car = isset($_POST['has_car']) ? 1 : 0;
            
            // Check of profiel al bestaat
            $check = $pdo->prepare("SELECT id FROM nurse_profiles WHERE user_id = ?");
            $check->execute([$edit_user_id]);
            
            if ($check->rowCount() > 0) {
                // Update
                $sql_prof = "UPDATE nurse_profiles SET 
                    first_name=?, last_name=?, gender=?, dob=?, address=?, city=?, district=?, neighborhood=?, 
                    phone=?, job_title=?, contract_type=?, contract_hours=?, hourly_wage=?, travel_allowance=?, 
                    has_car=?, notes=? 
                    WHERE user_id=?";
            } else {
                // Insert (als rol gewijzigd is naar zuster)
                $sql_prof = "INSERT INTO nurse_profiles 
                    (first_name, last_name, gender, dob, address, city, district, neighborhood, 
                    phone, job_title, contract_type, contract_hours, hourly_wage, travel_allowance, 
                    has_car, notes, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt_prof = $pdo->prepare($sql_prof);
            $stmt_prof->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'],
                $_POST['address'], $_POST['city'], $_POST['district'], $_POST['neighborhood'],
                $_POST['phone'], $_POST['job_title'], $_POST['contract_type'], 
                $_POST['contract_hours'], $_POST['hourly_wage'], $_POST['travel_allowance'], 
                $has_car, $_POST['notes'], $edit_user_id
            ]);
        }

        // C. FAMILIE KOPPELINGEN
        if ($role === 'familie') {
            // Eerst alles wissen
            $del = $pdo->prepare("DELETE FROM family_client_access WHERE user_id = ?");
            $del->execute([$edit_user_id]);

            // Nieuwe toevoegen
            if (isset($_POST['linked_clients']) && is_array($_POST['linked_clients'])) {
                $ins = $pdo->prepare("INSERT INTO family_client_access (user_id, client_id) VALUES (?, ?)");
                foreach ($_POST['linked_clients'] as $cid) {
                    $ins->execute([$edit_user_id, $cid]);
                }
            }
        }

        $pdo->commit();
        $success = "Gebruiker succesvol bijgewerkt.";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout: " . $e->getMessage();
    }
}

// 3. DATA OPHALEN VOOR FORMULIER
// User & Profiel
$stmt = $pdo->prepare("SELECT u.*, np.*, u.id as uid FROM users u LEFT JOIN nurse_profiles np ON u.id = np.user_id WHERE u.id = ?");
$stmt->execute([$edit_user_id]);
$user = $stmt->fetch();

if (!$user) { echo "Gebruiker niet gevonden."; exit; }

// Lijsten voor dropdowns
$districten = ['Paramaribo','Wanica','Commewijne','Para','Saramacca','Marowijne','Coronie','Nickerie','Brokopondo','Sipaliwini'];
$wijken = ['Centrum','Beekhuizen','Blauwgrond','Flora','Latour','Livorno','Munder','Pontbuiten','Rainville','Tammenga','Weg naar See','Welgelegen','Overig'];

// Cliënten (voor familie)
$all_clients = $pdo->query("SELECT id, first_name, last_name, district FROM clients WHERE is_active = 1 ORDER BY last_name")->fetchAll();
// Huidige toegang
$access_stmt = $pdo->prepare("SELECT client_id FROM family_client_access WHERE user_id = ?");
$access_stmt->execute([$edit_user_id]);
$current_access = $access_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="w-full max-w-5xl mx-auto mb-12">
    
    <div class="bg-white border border-gray-300 p-4 mb-6 flex justify-between items-center shadow-sm">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight flex items-center">
                <i class="fa-solid fa-user-pen mr-3 text-slate-400"></i> Gebruiker Bewerken
            </h1>
            <p class="text-xs text-slate-500">
                Aanpassen account: <strong class="text-slate-700"><?php echo htmlspecialchars($user['username']); ?></strong>
            </p>
        </div>
        <a href="index.php" class="text-slate-500 hover:text-red-700 font-bold text-sm uppercase flex items-center transition">
            <i class="fa-solid fa-xmark mr-2"></i> Annuleren
        </a>
    </div>

    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-600 text-red-800 p-4 text-sm font-medium mb-4 flex items-center">
            <i class="fa-solid fa-triangle-exclamation mr-3 text-lg"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="bg-green-50 border-l-4 border-green-600 text-green-800 p-4 text-sm font-medium mb-4 flex items-center">
            <i class="fa-solid fa-check mr-3 text-lg"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white border border-gray-300 shadow-sm">
        
        <div class="bg-slate-50 px-5 py-3 border-b border-gray-300">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                <i class="fa-solid fa-lock mr-2"></i> 1. Account & Toegang
            </h3>
        </div>
        
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gebruikersnaam</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-user"></i></span>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="w-full pl-10 p-2 border border-gray-300 bg-gray-100 text-sm cursor-not-allowed">
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Gebruikersnaam kan niet gewijzigd worden.</p>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status</label>
                <select name="is_active" class="w-full p-2 border border-gray-300 text-sm font-bold focus:border-blue-500 focus:ring-0">
                    <option value="1" <?php if($user['is_active'] == 1) echo 'selected'; ?>>Actief</option>
                    <option value="0" <?php if($user['is_active'] == 0) echo 'selected'; ?>>Inactief / Geblokkeerd</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nieuw Wachtwoord</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-key"></i></span>
                    <input type="password" name="password" class="w-full pl-10 p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="Laat leeg om te behouden">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rol / Type Account</label>
                <select name="role" id="roleSelect" class="w-full p-2 border border-gray-300 text-sm bg-blue-50 font-bold focus:border-blue-500 focus:ring-0" onchange="toggleFields()">
                    <option value="management" <?php if($user['role'] == 'management') echo 'selected'; ?>>Management</option>
                    <option value="zuster" <?php if($user['role'] == 'zuster') echo 'selected'; ?>>Zuster (Zorgpersoneel)</option>
                    <option value="familie" <?php if($user['role'] == 'familie') echo 'selected'; ?>>Familie</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Adres</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-envelope"></i></span>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full pl-10 p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                </div>
            </div>
        </div>

        <div id="nurseFields" class="border-t border-gray-200" style="display:none;">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                    <i class="fa-solid fa-briefcase mr-2"></i> 2. HR Profiel (Zorgpersoneel)
                </h3>
            </div>
            <div class="p-6">
                
                <h4 class="text-sm font-bold text-blue-700 mb-3 uppercase border-b border-gray-100 pb-1">Persoonsgegevens</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Voornaam</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Achternaam</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Geslacht</label>
                        <select name="gender" class="w-full p-2 border border-gray-300 text-sm bg-white">
                            <option value="V" <?php if(($user['gender'] ?? '') == 'V') echo 'selected'; ?>>Vrouw</option>
                            <option value="M" <?php if(($user['gender'] ?? '') == 'M') echo 'selected'; ?>>Man</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Geboortedatum</label>
                        <input type="date" name="dob" value="<?php echo $user['dob'] ?? ''; ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                </div>

                <h4 class="text-sm font-bold text-blue-700 mb-3 uppercase border-b border-gray-100 pb-1">Adres & Contact</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 mb-1">Straat + Huisnummer</label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Wijk / Buurt</label>
                        <select name="neighborhood" class="w-full p-2 border border-gray-300 text-sm bg-white">
                            <?php foreach($wijken as $w): ?>
                                <option value="<?php echo $w; ?>" <?php if(($user['neighborhood'] ?? '') == $w) echo 'selected'; ?>><?php echo $w; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">District</label>
                        <select name="district" class="w-full p-2 border border-gray-300 text-sm bg-white">
                            <?php foreach($districten as $d): ?>
                                <option value="<?php echo $d; ?>" <?php if(($user['district'] ?? '') == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Stad</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Telefoonnummer</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center space-x-2 p-2 border border-gray-200 w-full bg-gray-50 cursor-pointer hover:bg-white border-l-4 border-l-blue-500">
                            <input type="checkbox" name="has_car" value="1" <?php if(!empty($user['has_car'])) echo 'checked'; ?> class="text-blue-600 focus:ring-0">
                            <span class="text-sm text-slate-700 font-medium">Heeft eigen vervoer (Auto)</span>
                        </label>
                    </div>
                </div>

                <h4 class="text-sm font-bold text-blue-700 mb-3 uppercase border-b border-gray-100 pb-1">Contract & Functie</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Functie Titel</label>
                        <select name="job_title" class="w-full p-2 border border-gray-300 text-sm bg-white">
                            <option <?php if(($user['job_title'] ?? '') == 'Verzorgende-IG') echo 'selected'; ?>>Verzorgende-IG</option>
                            <option <?php if(($user['job_title'] ?? '') == 'Zuster') echo 'selected'; ?>>Zuster</option>
                            <option <?php if(($user['job_title'] ?? '') == 'Stagiair') echo 'selected'; ?>>Stagiair</option>
                            <option <?php if(($user['job_title'] ?? '') == 'Vrijwilliger') echo 'selected'; ?>>Vrijwilliger</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Dienstverband</label>
                        <select name="contract_type" class="w-full p-2 border border-gray-300 text-sm bg-white">
                            <option value="Vast" <?php if(($user['contract_type'] ?? '') == 'Vast') echo 'selected'; ?>>Vast Contract</option>
                            <option value="Tijdelijk" <?php if(($user['contract_type'] ?? '') == 'Tijdelijk') echo 'selected'; ?>>Tijdelijk Contract</option>
                            <option value="ZZP" <?php if(($user['contract_type'] ?? '') == 'ZZP') echo 'selected'; ?>>ZZP / Freelance</option>
                            <option value="Oproep" <?php if(($user['contract_type'] ?? '') == 'Oproep') echo 'selected'; ?>>Oproepkracht</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Contracturen (p/w)</label>
                        <input type="number" step="0.5" name="contract_hours" value="<?php echo $user['contract_hours'] ?? '0.0'; ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Uurloon (SRD)</label>
                        <input type="number" step="0.01" name="hourly_wage" value="<?php echo $user['hourly_wage'] ?? '0.00'; ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Reiskosten (SRD)</label>
                        <input type="number" step="0.01" name="travel_allowance" value="<?php echo $user['travel_allowance'] ?? '0.00'; ?>" class="w-full p-2 border border-gray-300 text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Interne Notities</label>
                    <textarea name="notes" rows="3" class="w-full p-2 border border-gray-300 text-sm"><?php echo htmlspecialchars($user['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <div id="familyFields" class="border-t border-gray-200" style="display:none;">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">
                    <i class="fa-solid fa-users mr-2"></i> 2. Koppel Cliënt(en)
                </h3>
            </div>
            
            <div class="p-6">
                <p class="text-sm text-slate-600 mb-4">Vink aan welke cliënt(en) dit familielid mag inzien:</p>
                
                <div class="h-64 overflow-y-auto border border-gray-300 bg-gray-50 p-2">
                    <?php if(count($all_clients) > 0): foreach($all_clients as $c): 
                        $checked = in_array($c['id'], $current_access) ? 'checked' : '';
                    ?>
                        <label class="flex items-center p-2 hover:bg-white border-b border-gray-200 cursor-pointer last:border-0">
                            <input type="checkbox" name="linked_clients[]" value="<?php echo $c['id']; ?>" <?php echo $checked; ?> class="w-5 h-5 text-blue-600 border-gray-300 focus:ring-0 mr-3">
                            <div>
                                <span class="font-bold text-slate-700 text-sm"><?php echo htmlspecialchars($c['last_name'] . ', ' . $c['first_name']); ?></span>
                                <span class="text-xs text-slate-400 ml-2">(<?php echo htmlspecialchars($c['district']); ?>)</span>
                            </div>
                        </label>
                    <?php endforeach; else: ?>
                        <div class="p-4 text-center text-slate-400 italic">Geen cliënten gevonden.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 text-sm uppercase tracking-wide transition-colors shadow-sm flex items-center">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Wijzigingen Opslaan
            </button>
        </div>

    </form>
</div>

<script>
    function toggleFields() {
        var role = document.getElementById('roleSelect').value;
        var nurseFields = document.getElementById('nurseFields');
        var familyFields = document.getElementById('familyFields');
        
        // Reset display
        nurseFields.style.display = 'none';
        familyFields.style.display = 'none';

        if (role === 'zuster') {
            nurseFields.style.display = 'block';
        } else if (role === 'familie') {
            familyFields.style.display = 'block';
        }
    }
    // Direct uitvoeren bij laden om juiste velden te tonen
    document.addEventListener("DOMContentLoaded", function() {
        toggleFields();
    });
</script>

<?php include '../../includes/footer.php'; ?>