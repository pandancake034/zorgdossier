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

        // 1. Basis User Aanmaken
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];

        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
        $user_id = $pdo->lastInsertId();

        // 2. Als het een Zuster is, profiel aanmaken
        if ($role === 'zuster') {
            
            // --- DE REPARATIE ---
            // We kijken of het veld leeg is via 'empty()'. 
            // Zo ja? Dan maken we er 0 van. Zo nee? Dan gebruiken we de waarde.
            $hourly_rate = empty($_POST['hourly_rate']) ? 0 : $_POST['hourly_rate'];
            $travel_allowance = empty($_POST['travel_allowance']) ? 0 : $_POST['travel_allowance'];
            // --------------------

            $stmt_profile = $pdo->prepare("INSERT INTO nurse_profiles 
                (user_id, first_name, last_name, phone, address, contract_type, hourly_rate, travel_allowance) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt_profile->execute([
                $user_id,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['phone'],
                $_POST['address'],
                $_POST['contract_type'],
                $hourly_rate,      // Nu veilig (0 of een getal)
                $travel_allowance  // Nu veilig (0 of een getal)
            ]);
        }

        $pdo->commit();
        
        // Terug naar de lijst
        echo "<script>window.location.href='index.php?success=1';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}
?>

<div class="max-w-2xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">👤 Nieuwe Gebruiker Aanmaken</h2>
        <a href="index.php" class="text-gray-500 hover:text-gray-700 font-bold">Annuleren ✕</a>
    </div>

    <?php if(isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST">
            
            <h3 class="font-bold text-gray-700 border-b pb-2 mb-4">1. Login Gegevens</h3>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Gebruikersnaam</label>
                <input type="text" name="username" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-bold text-gray-700 mb-1">Wachtwoord</label>
                <input type="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-bold text-gray-700 mb-1">Rol</label>
                <select name="role" id="roleSelect" class="w-full p-2 border rounded bg-gray-50" onchange="toggleNurseFields()">
                    <option value="management">Management</option>
                    <option value="zuster">Zuster (Verpleging)</option>
                    <option value="familie">Familie</option>
                </select>
            </div>

            <div id="nurseFields" class="hidden border-t pt-4">
                <h3 class="font-bold text-teal-700 border-b pb-2 mb-4">2. Zuster Profiel (HR)</h3>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500">Voornaam</label>
                        <input type="text" name="first_name" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500">Achternaam</label>
                        <input type="text" name="last_name" class="w-full p-2 border rounded">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs uppercase font-bold text-gray-500">Telefoon</label>
                    <input type="text" name="phone" class="w-full p-2 border rounded">
                </div>
                
                <div class="mb-4">
                    <label class="block text-xs uppercase font-bold text-gray-500">Adres</label>
                    <input type="text" name="address" class="w-full p-2 border rounded">
                </div>

                <div class="mb-4">
                    <label class="block text-xs uppercase font-bold text-gray-500">Contract Type</label>
                    <select name="contract_type" class="w-full p-2 border rounded">
                        <option value="Fulltime">Fulltime</option>
                        <option value="Parttime">Parttime</option>
                        <option value="ZZP">ZZP / Oproep</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500">Uurloon (€)</label>
                        <input type="number" step="0.01" name="hourly_rate" class="w-full p-2 border rounded" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-gray-500">Reiskosten (€)</label>
                        <input type="number" step="0.01" name="travel_allowance" class="w-full p-2 border rounded" placeholder="0.00">
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded mt-4 shadow">
                Gebruiker Aanmaken
            </button>

        </form>
    </div>
</div>

<script>
    function toggleNurseFields() {
        var role = document.getElementById('roleSelect').value;
        var fields = document.getElementById('nurseFields');
        
        if (role === 'zuster') {
            fields.classList.remove('hidden');
            // Animatie effectje
            fields.style.display = 'block';
        } else {
            fields.classList.add('hidden');
            fields.style.display = 'none';
        }
    }
    // Voer 1x uit bij laden (voor als browser velden onthoudt)
    toggleNurseFields();
</script>

<?php include '../../includes/footer.php'; ?>