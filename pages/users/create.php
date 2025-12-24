<?php
// pages/users/create.php
include '../../includes/header.php';
require '../../config/db.php';

// Check of Management is ingelogd
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// VERWERKEN VAN HET FORMULIER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. BASIS USER
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role     = $_POST['role'];
        $email    = !empty($_POST['email']) ? $_POST['email'] : $username . '@zorgdossier.sr';

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $email, $role]);
        $user_id = $pdo->lastInsertId();

        // 2. ZUSTER PROFIEL
        if ($role === 'zuster') {
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
            <p class="text-xs text-slate-500">Account aanmaken en HR gegevens invullen</p>
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

    <form method="POST" class="bg-white border border-gray-300">
        
        <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">1. Login Gegevens</h3>
        </div>
        
        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Gebruikersnaam *</label>
                <input type="text" name="username" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Wachtwoord *</label>
                <input type="password" name="password" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Email Adres *</label>
                <input type="email" name="email" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" required placeholder="naam@zorginstelling.sr">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Rol *</label>
                <select name="role" id="roleSelect" class="w-full p-2 border border-gray-300 text-sm bg-blue-50 focus:bg-white focus:border-blue-500 focus:ring-0" onchange="toggleNurseFields()">
                    <option value="management">Management</option>
                    <option value="zuster" selected>Zuster (Verpleging)</option>
                    <option value="familie">Familie</option>
                </select>
            </div>
        </div>

        <div id="nurseFields" class="border-t border-gray-200">
            <div class="bg-slate-100 px-5 py-3 border-b border-gray-300">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide">2. HR Profiel (Zorgpersoneel)</h3>
            </div>
            
            <div class="p-6">
                <h4 class="text-xs font-bold text-slate-400 uppercase mb-3 border-b border-gray-100 pb-1">Persoonsgegevens</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Voornaam *</label>
                        <input type="text" name="first_name" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Achternaam *</label>
                        <input type="text" name="last_name" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Geslacht *</label>
                        <select name="gender" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                            <option value="V">Vrouw</option>
                            <option value="M">Man</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Geboortedatum *</label>
                        <input type="date" name="dob" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                </div>

                <h4 class="text-xs font-bold text-slate-400 uppercase mb-3 border-b border-gray-100 pb-1">Adres & Contact</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 mb-1">Adres *</label>
                        <input type="text" name="address" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Stad *</label>
                        <input type="text" name="city" value="Paramaribo" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Telefoon *</label>
                        <input type="text" name="phone" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">District *</label>
                        <select name="district" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                            <option value="Paramaribo">Paramaribo</option>
                            <option value="Wanica">Wanica</option>
                            <option value="Commewijne">Commewijne</option>
                            <option value="Para">Para</option>
                            <option value="Saramacca">Saramacca</option>
                            <option value="Marowijne">Marowijne</option>
                            <option value="Coronie">Coronie</option>
                            <option value="Nickerie">Nickerie</option>
                            <option value="Brokopondo">Brokopondo</option>
                            <option value="Sipaliwini">Sipaliwini</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Buurt/Wijk *</label>
                        <select name="neighborhood" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                            <option value="Centrum">Centrum</option>
                            <option value="Beekhuizen">Beekhuizen</option>
                            <option value="Blauwgrond">Blauwgrond</option>
                            <option value="Flora">Flora</option>
                            <option value="Latour">Latour</option>
                            <option value="Livorno">Livorno</option>
                            <option value="Munder">Munder</option>
                            <option value="Pontbuiten">Pontbuiten</option>
                            <option value="Rainville">Rainville</option>
                            <option value="Tammenga">Tammenga</option>
                            <option value="Weg naar See">Weg naar See</option>
                            <option value="Welgelegen">Welgelegen</option>
                            <option value="Overig">Overig</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 flex items-end pb-2">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="has_car" id="has_car" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-0 rounded-none">
                            <span class="ml-2 text-sm font-bold text-slate-700 uppercase">Beschikt over auto</span>
                        </label>
                    </div>
                </div>

                <h4 class="text-xs font-bold text-slate-400 uppercase mb-3 border-b border-gray-100 pb-1">Contract & Salaris</h4>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 bg-slate-50 p-4 border border-gray-200">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Functie *</label>
                        <select name="job_title" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                            <option value="Verzorgende-IG">Verzorgende-IG</option>
                            <option value="Verpleegkundige">Verpleegkundige</option>
                            <option value="Helpende+">Helpende+</option>
                            <option value="Leerling">Leerling</option>
                            <option value="Zuster">Zuster</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Contract Type *</label>
                        <select name="contract_type" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0">
                            <option value="Vast">Vast</option>
                            <option value="Tijdelijk">Tijdelijk</option>
                            <option value="Uitzend">Uitzend</option>
                            <option value="ZZP">ZZP</option>
                            <option value="Freelance">Freelance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Datum Indienst *</label>
                        <input type="date" name="date_employed" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Contract Uren</label>
                        <input type="number" step="0.01" name="contract_hours" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Uurloon (€) *</label>
                        <input type="number" step="0.01" name="hourly_wage" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">Reiskosten (€)</label>
                        <input type="number" step="0.01" name="travel_allowance" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0" placeholder="0.00">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="block text-xs font-bold text-slate-500 mb-1">HR Notities</label>
                    <textarea name="notes" rows="2" class="w-full p-2 border border-gray-300 text-sm focus:border-blue-500 focus:ring-0"></textarea>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
            <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 text-sm uppercase tracking-wide transition-colors">
                Gebruiker Opslaan
            </button>
        </div>

    </form>
</div>

<script>
    function toggleNurseFields() {
        var role = document.getElementById('roleSelect').value;
        var fields = document.getElementById('nurseFields');
        
        if (role === 'zuster') {
            fields.style.display = 'block';
        } else {
            fields.style.display = 'none';
        }
    }
    // Direct uitvoeren bij laden pagina
    document.addEventListener("DOMContentLoaded", function() {
        toggleNurseFields();
    });
</script>

<?php include '../../includes/footer.php'; ?>