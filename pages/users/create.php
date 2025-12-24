<?php
// pages/users/create.php
include '../../includes/header.php';
require '../../config/db.php';

// BEVEILIGING
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$error = '';
$success = '';

// AFHANDELING FORMULIER
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Haal basisgegevens op
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role']; // zuster, management, familie

        // Check of gebruiker al bestaat
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            throw new Exception("Gebruikersnaam of e-mailadres bestaat al!");
        }

        // 2. Start Transactie (Alles of Niets)
        $pdo->beginTransaction();

        // 3. Maak User aan
        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
        $sqlUser = "INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, ?, 1)";
        $stmtUser = $pdo->prepare($sqlUser);
        $stmtUser->execute([$username, $hashed_pass, $email, $role]);
        
        $new_user_id = $pdo->lastInsertId();

        // 4. Als het een Zuster is -> Vul Nurse Profile
        if ($role === 'zuster') {
            $sqlProfile = "INSERT INTO nurse_profiles (
                user_id, first_name, last_name, gender, dob, 
                address, city, district, neighborhood, phone, 
                job_title, contract_type, date_employed, 
                contract_hours, hourly_wage, travel_allowance, has_car, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtProfile = $pdo->prepare($sqlProfile);
            $stmtProfile->execute([
                $new_user_id,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['gender'],
                $_POST['dob'],
                $_POST['address'],
                'Paramaribo', // Stad standaard (of voeg veld toe)
                $_POST['district'],
                $_POST['neighborhood'],
                $_POST['phone'],
                $_POST['job_title'],
                $_POST['contract_type'],
                $_POST['date_employed'],
                $_POST['contract_hours'],
                $_POST['hourly_wage'],
                $_POST['travel_allowance'],
                isset($_POST['has_car']) ? 1 : 0,
                $_POST['notes']
            ]);
        }

        // 5. Commit de transactie
        $pdo->commit();
        
        // Terug naar overzicht
        echo "<script>window.location.href='index.php';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack(); // Draai alles terug bij fout
        $error = "Fout: " . $e->getMessage();
    }
}
?>

<div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
    <div class="bg-teal-600 p-4 text-white flex justify-between items-center">
        <h2 class="text-2xl font-bold">Nieuwe Medewerker Toevoegen</h2>
        <a href="index.php" class="text-teal-100 hover:text-white text-sm">Terug naar overzicht</a>
    </div>

    <form method="POST" class="p-6 space-y-6">
        
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="bg-gray-50 p-4 rounded border border-gray-200">
            <h3 class="font-bold text-gray-700 mb-4 border-b pb-2">🔐 Inloggegevens</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Gebruikersnaam</label>
                    <input type="text" name="username" required class="w-full p-2 border rounded focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">E-mailadres</label>
                    <input type="email" name="email" required class="w-full p-2 border rounded focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Wachtwoord</label>
                    <input type="password" name="password" required class="w-full p-2 border rounded focus:ring-teal-500 focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600">Rol</label>
                    <select name="role" id="roleSelect" class="w-full p-2 border rounded bg-white">
                        <option value="zuster">Zuster (Zorgpersoneel)</option>
                        <option value="management">Management</option>
                        <option value="familie">Familie (Alleen lezen)</option>
                    </select>
                </div>
            </div>
        </div>

        <div id="nurseFields" class="border-t pt-4">
            
            <div class="mb-6">
                <h3 class="font-bold text-teal-700 mb-4 text-lg">👤 Persoonsgegevens</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Voornaam</label>
                        <input type="text" name="first_name" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Achternaam</label>
                        <input type="text" name="last_name" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Geslacht</label>
                        <select name="gender" class="w-full p-2 border rounded">
                            <option value="V">Vrouw</option>
                            <option value="M">Man</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Geboortedatum</label>
                        <input type="date" name="dob" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Telefoon</label>
                        <input type="text" name="phone" class="w-full p-2 border rounded">
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="font-bold text-teal-700 mb-4 text-lg">📍 Locatie</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs uppercase text-gray-500">Adres</label>
                        <input type="text" name="address" class="w-full p-2 border rounded" placeholder="Straatnaam en nummer">
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">District</label>
                        <select name="district" class="w-full p-2 border rounded">
                            <option value="Paramaribo">Paramaribo</option>
                            <option value="Wanica">Wanica</option>
                            <option value="Commewijne">Commewijne</option>
                            <option value="Para">Para</option>
                            <option value="Saramacca">Saramacca</option>
                            </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Wijk</label>
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
                </div>
            </div>

            <div class="bg-yellow-50 p-4 rounded border border-yellow-200 mb-6">
                <h3 class="font-bold text-yellow-800 mb-4 text-lg">💼 Contract & Salaris</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    
                    <div>
                        <label class="block text-xs uppercase text-gray-500">Functie</label>
                        <select name="job_title" class="w-full p-2 border rounded">
                            <option value="Helpende+">Helpende+</option>
                            <option value="Leerling">Leerling</option>
                            <option value="Verzorgende-IG">Verzorgende-IG</option>
                            <option value="Verpleegkundige">Verpleegkundige</option>
                            <option value="Zuster">Zuster</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-gray-500">Soort Contract</label>
                        <select name="contract_type" class="w-full p-2 border rounded">
                            <option value="Vast">Vast</option>
                            <option value="Uitzend">Uitzend</option>
                            <option value="ZZP">ZZP</option>
                            <option value="Freelance">Freelance</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-gray-500">Datum in dienst</label>
                        <input type="date" name="date_employed" class="w-full p-2 border rounded">
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-gray-500">Uurloon (SRD)</label>
                        <input type="number" step="0.01" name="hourly_wage" class="w-full p-2 border rounded" placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-gray-500">Reiskosten (SRD)</label>
                        <input type="number" step="0.01" name="travel_allowance" class="w-full p-2 border rounded" placeholder="0.00">
                    </div>

                    <div>
                        <label class="block text-xs uppercase text-gray-500">Contracturen (p.w.)</label>
                        <input type="number" step="0.01" name="contract_hours" class="w-full p-2 border rounded" placeholder="0.00">
                    </div>
                    
                    <div class="flex items-center mt-6">
                         <input type="checkbox" name="has_car" id="has_car" class="w-5 h-5 text-teal-600 rounded">
                         <label for="has_car" class="ml-2 text-gray-700 font-bold">Heeft eigen auto?</label>
                    </div>

                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-gray-600">Bijzonderheden</label>
                <textarea name="notes" rows="3" class="w-full p-2 border rounded"></textarea>
            </div>

        </div> <div class="pt-4 border-t">
            <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded shadow text-lg transition duration-200">
                💾 Medewerker Opslaan
            </button>
        </div>

    </form>
</div>

<script>
    const roleSelect = document.getElementById('roleSelect');
    const nurseFields = document.getElementById('nurseFields');
    const nurseInputs = nurseFields.querySelectorAll('input, select, textarea');

    function toggleFields() {
        if (roleSelect.value === 'zuster') {
            nurseFields.style.display = 'block';
            // Maak velden required als ze zichtbaar zijn (optioneel, voor betere validatie)
            // nurseInputs.forEach(input => input.required = true);
        } else {
            nurseFields.style.display = 'none';
            // nurseInputs.forEach(input => input.required = false);
        }
    }

    // Luister naar veranderingen
    roleSelect.addEventListener('change', toggleFields);
    
    // Voer 1x uit bij laden pagina
    toggleFields();
</script>

<?php include '../../includes/footer.php'; ?>
