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

        // ---------------------------------------------------------
        // 1. BASIS USER AANMAKEN (Tabel: users)
        // ---------------------------------------------------------
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role     = $_POST['role'];
        
        // Zorg dat email niet leeg is (voorkomt error 1364)
        $email = !empty($_POST['email']) ? $_POST['email'] : $username . '@zorgdossier.sr';

        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $password, $email, $role]);
        $user_id = $pdo->lastInsertId();

        // ---------------------------------------------------------
        // 2. ZUSTER PROFIEL AANMAKEN (Tabel: nurse_profiles)
        // ---------------------------------------------------------
        if ($role === 'zuster') {
            
            // Decimalen en Datums veilig maken (voorkomt "Incorrect decimal value")
            $contract_hours   = !empty($_POST['contract_hours']) ? $_POST['contract_hours'] : 0.00;
            $hourly_wage      = !empty($_POST['hourly_wage']) ? $_POST['hourly_wage'] : 0.00;
            $travel_allowance = !empty($_POST['travel_allowance']) ? $_POST['travel_allowance'] : 0.00;
            $date_employed    = !empty($_POST['date_employed']) ? $_POST['date_employed'] : date('Y-m-d');
            $has_car          = isset($_POST['has_car']) ? 1 : 0; 

            // De query die EXACT matcht met jouw 'DESCRIBE nurse_profiles' output
            $sql_profile = "INSERT INTO nurse_profiles 
            (
                user_id, first_name, last_name, gender, dob, 
                address, city, district, neighborhood, phone, 
                job_title, contract_type, date_employed, contract_hours, 
                hourly_wage, travel_allowance, has_car, notes
            ) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt_profile = $pdo->prepare($sql_profile);
            
            $stmt_profile->execute([
                $user_id,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['gender'],
                $_POST['dob'],
                $_POST['address'],
                $_POST['city'],
                $_POST['district'],
                $_POST['neighborhood'],
                $_POST['phone'],
                $_POST['job_title'],
                $_POST['contract_type'],
                $date_employed,
                $contract_hours,
                $hourly_wage,      // Matcht nu met DB kolom
                $travel_allowance, // Matcht nu met DB kolom
                $has_car,
                $_POST['notes']
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

<div class="max-w-4xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">👤 Nieuwe Gebruiker Aanmaken</h2>
        <a href="index.php" class="text-gray-500 hover:text-gray-700 font-bold">Annuleren ✕</a>
    </div>

    <?php if(isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow p-6">
        
        <h3 class="font-bold text-gray-700 border-b pb-2 mb-4">1. Login Gegevens</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Gebruikersnaam *</label>
                <input type="text" name="username" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Wachtwoord *</label>
                <input type="password" name="password" class="w-full p-2 border rounded" required>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Email Adres *</label>
                <input type="email" name="email" class="w-full p-2 border rounded" required placeholder="naam@zorginstelling.sr">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 uppercase">Rol *</label>
                <select name="role" id="roleSelect" class="w-full p-2 border rounded bg-blue-50" onchange="toggleNurseFields()">
                    <option value="management">Management</option>
                    <option value="zuster" selected>Zuster (Verpleging)</option>
                    <option value="familie">Familie</option>
                </select>
            </div>
        </div>

        <div id="nurseFields" class="border-t pt-4">
            <h3 class="font-bold text-teal-700 border-b pb-2 mb-4">2. HR Profiel (Zuster)</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500">Voornaam *</label>
                    <input type="text" name="first_name" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Achternaam *</label>
                    <input type="text" name="last_name" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Geslacht *</label>
                    <select name="gender" class="w-full p-2 border rounded">
                        <option value="V">Vrouw</option>
                        <option value="M">Man</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Geboortedatum *</label>
                    <input type="date" name="dob" class="w-full p-2 border rounded">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-500">Adres *</label>
                    <input type="text" name="address" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Stad *</label>
                    <input type="text" name="city" value="Paramaribo" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Telefoon *</label>
                    <input type="text" name="phone" class="w-full p-2 border rounded">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500">District *</label>
                    <select name="district" class="w-full p-2 border rounded">
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
                    <label class="block text-xs font-bold text-gray-500">Buurt/Wijk *</label>
                    <select name="neighborhood" class="w-full p-2 border rounded">
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
                <div class="flex items-center mt-6">
                    <input type="checkbox" name="has_car" id="has_car" class="w-5 h-5 text-teal-600 rounded">
                    <label for="has_car" class="ml-2 text-sm font-bold text-gray-700">Heeft Auto 🚗</label>
                </div>
            </div>

            <h4 class="font-bold text-gray-600 mt-6 mb-2 text-sm uppercase">Contract Gegevens</h4>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 bg-gray-50 p-4 rounded border">
                <div>
                    <label class="block text-xs font-bold text-gray-500">Functie *</label>
                    <select name="job_title" class="w-full p-2 border rounded">
                        <option value="Verzorgende-IG">Verzorgende-IG</option>
                        <option value="Verpleegkundige">Verpleegkundige</option>
                        <option value="Helpende+">Helpende+</option>
                        <option value="Leerling">Leerling</option>
                        <option value="Zuster">Zuster</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Contract Type *</label>
                    <select name="contract_type" class="w-full p-2 border rounded">
                        <option value="Vast">Vast</option>
                        <option value="Tijdelijk">Tijdelijk</option>
                        <option value="Uitzend">Uitzend</option>
                        <option value="ZZP">ZZP</option>
                        <option value="Freelance">Freelance</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Datum Indienst *</label>
                    <input type="date" name="date_employed" class="w-full p-2 border rounded" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Contract Uren</label>
                    <input type="number" step="0.01" name="contract_hours" class="w-full p-2 border rounded" placeholder="bv. 8.00">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500">Uurloon (Hourly Wage) € *</label>
                    <input type="number" step="0.01" name="hourly_wage" class="w-full p-2 border rounded" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500">Reiskostenvergoeding €</label>
                    <input type="number" step="0.01" name="travel_allowance" class="w-full p-2 border rounded" placeholder="0.00">
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500">Notities</label>
                <textarea name="notes" rows="2" class="w-full p-2 border rounded"></textarea>
            </div>
        </div>

        <button type="submit" class="w-full bg-teal-700 hover:bg-teal-800 text-white font-bold py-3 rounded mt-4 shadow text-lg">
            💾 Gebruiker Opslaan
        </button>
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