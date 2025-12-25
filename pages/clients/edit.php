<?php
// pages/clients/edit.php
include '../../includes/header.php';
require '../../config/db.php';

// 1. BEVEILIGING
// Alleen Management mag stamkaarten wijzigen
if ($_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}

if (!isset($_GET['id'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit;
}
$client_id = $_GET['id'];
$success_msg = "";
$error_msg = "";

// 2. ACTIES VERWERKEN

// A. BESTAAND FAMILIELID KOPPELEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'link_family') {
    $user_id_to_link = $_POST['family_user_id'];
    try {
        // Check of al gekoppeld is om errors te voorkomen
        $check = $pdo->prepare("SELECT * FROM family_client_access WHERE user_id = ? AND client_id = ?");
        $check->execute([$user_id_to_link, $client_id]);
        if($check->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO family_client_access (user_id, client_id) VALUES (?, ?)");
            $stmt->execute([$user_id_to_link, $client_id]);
            $success_msg = "Familielid succesvol gekoppeld.";
        } else {
            $error_msg = "Deze gebruiker heeft al toegang.";
        }
    } catch (Exception $e) { $error_msg = "Fout: " . $e->getMessage(); }
}

// B. NIEUW ACCOUNT MAKEN & KOPPELEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_link_family') {
    try {
        $username = trim($_POST['new_username']);
        $password = trim($_POST['new_password']);
        $email = trim($_POST['new_email']);

        // Validatie
        if(empty($username) || empty($password)) throw new Exception("Gebruikersnaam en wachtwoord verplicht.");

        $pdo->beginTransaction();

        // 1. User maken
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, is_active) VALUES (?, ?, ?, 'familie', 1)");
        $stmt->execute([$username, $hash, $email]);
        $new_user_id = $pdo->lastInsertId();

        // 2. Direct koppelen
        $stmt = $pdo->prepare("INSERT INTO family_client_access (user_id, client_id) VALUES (?, ?)");
        $stmt->execute([$new_user_id, $client_id]);

        $pdo->commit();
        $success_msg = "Nieuw account '$username' aangemaakt en gekoppeld!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Kon account niet maken: " . $e->getMessage(); // Waarschijnlijk duplicate username
    }
}

// C. ONTKOPPELEN
if (isset($_GET['unlink_user'])) {
    $uid = $_GET['unlink_user'];
    $pdo->prepare("DELETE FROM family_client_access WHERE user_id = ? AND client_id = ?")->execute([$uid, $client_id]);
    header("Location: edit.php?id=$client_id"); // Redirect om refresh te voorkomen
    exit;
}

// D. BASIS GEGEVENS OPSLAAN (Het grote formulier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    try {
        $pdo->beginTransaction();

        // Update Basisgegevens
        $sql = "UPDATE clients SET 
                first_name=?, last_name=?, gender=?, dob=?, id_number=?, nationality=?, language=?,
                address=?, neighborhood=?, district=?, housing_type=?, floor_level=?, parking_info=?,
                contact1_name=?, contact1_phone=?, contact1_email=?,
                contact2_name=?, contact2_phone=?, contact2_email=?,
                diabetes_type=?, notes=?, is_active=?
                WHERE id=?";
        
        $params = [
            $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['dob'], $_POST['id_number'], $_POST['nationality'], $_POST['language'],
            $_POST['address'], $_POST['neighborhood'], $_POST['district'], $_POST['housing_type'], $_POST['floor_level'], $_POST['parking_info'],
            $_POST['contact1_name'], $_POST['contact1_phone'], $_POST['contact1_email'],
            $_POST['contact2_name'], $_POST['contact2_phone'], $_POST['contact2_email'],
            $_POST['diabetes_type'], $_POST['notes'], $_POST['is_active'],
            $client_id
        ];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Update Allergieën
        $pdo->prepare("DELETE FROM client_allergies WHERE client_id = ?")->execute([$client_id]);
        if (isset($_POST['allergies'])) {
            $stmt_all = $pdo->prepare("INSERT INTO client_allergies (client_id, allergy_type) VALUES (?, ?)");
            foreach ($_POST['allergies'] as $a) $stmt_all->execute([$client_id, $a]);
        }

        // Update Hulpmiddelen
        $pdo->prepare("DELETE FROM client_aids WHERE client_id = ?")->execute([$client_id]);
        if (isset($_POST['aids'])) {
            $stmt_aid = $pdo->prepare("INSERT INTO client_aids (client_id, aid_name) VALUES (?, ?)");
            foreach ($_POST['aids'] as $aid) $stmt_aid->execute([$client_id, $aid]);
        }

        $pdo->commit();
        echo "<script>window.location.href='detail.php?id=$client_id';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error_msg = "Fout bij opslaan cliënt: " . $e->getMessage();
    }
}

// 3. OPHALEN DATA
// Basis Data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();
if (!$client) die("Cliënt niet gevonden.");

// Lijstjes
$stmt = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?");
$stmt->execute([$client_id]);
$current_allergies = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?");
$stmt->execute([$client_id]);
$current_aids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Familie Data
// 1. Wie is al gekoppeld?
$linked_users = $pdo->prepare("SELECT u.* FROM users u JOIN family_client_access a ON u.id = a.user_id WHERE a.client_id = ?");
$linked_users->execute([$client_id]);
$family_linked = $linked_users->fetchAll();

// 2. Wie kan ik nog koppelen? (Alle familie die nog NIET gekoppeld is aan deze client)
// Let op: dit is een 'NOT IN' query
$all_family = $pdo->prepare("SELECT * FROM users WHERE role = 'familie' AND is_active = 1 AND id NOT IN (SELECT user_id FROM family_client_access WHERE client_id = ?) ORDER BY username");
$all_family->execute([$client_id]);
$available_family = $all_family->fetchAll();

// Opties arrays
$districten = ['Paramaribo','Wanica','Commewijne','Para','Saramacca','Marowijne','Coronie','Nickerie','Brokopondo','Sipaliwini'];
$wijken = ['Centrum','Beekhuizen','Blauwgrond','Flora','Latour','Livorno','Munder','Pontbuiten','Rainville','Tammenga','Weg naar See','Welgelegen','Overig'];
$opties_allergie = ['Antibiotica', 'Pinda', 'Lactose', 'Gluten', 'Latex', 'Insectenbeten', 'Huisstofmijt'];
$opties_hulpmiddelen = ['Bril', 'Gehoorapparaat', 'Kunstgebit', 'Rollator', 'Rolstoel', 'Wandelstok', 'Steunkousen', 'Incontinentiemateriaal'];
?>

<div class="max-w-5xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-slate-800 flex items-center">
                <i class="fa-solid fa-pen-to-square mr-3 text-slate-400"></i> Stamkaart Wijzigen
            </h2>
            <p class="text-sm text-slate-500">Bewerk gegevens van <?php echo htmlspecialchars($client['first_name'].' '.$client['last_name']); ?></p>
        </div>
        <a href="detail.php?id=<?php echo $client_id; ?>" class="text-slate-500 hover:text-red-700 font-bold flex items-center transition">
            <i class="fa-solid fa-xmark mr-2"></i> Annuleren
        </a>
    </div>

    <?php if($error_msg): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation mr-2"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <?php if($success_msg): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow-sm">
            <i class="fa-solid fa-check-circle mr-2"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden mb-8">
        <input type="hidden" name="action" value="update_client">
        
        <div class="bg-slate-50 px-6 py-4 border-b border-gray-300">
            <h3 class="font-bold text-slate-700 uppercase text-xs tracking-wide">
                <i class="fa-regular fa-id-card mr-2"></i> Persoonlijke Gegevens
            </h3>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Voornaam</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($client['first_name']); ?>" class="w-full p-2 border rounded text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Achternaam</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($client['last_name']); ?>" class="w-full p-2 border rounded text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Geslacht</label>
                    <select name="gender" class="w-full p-2 border rounded text-sm bg-white">
                        <option value="M" <?php if($client['gender'] == 'M') echo 'selected'; ?>>Man</option>
                        <option value="V" <?php if($client['gender'] == 'V') echo 'selected'; ?>>Vrouw</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Geboortedatum</label>
                    <input type="date" name="dob" value="<?php echo $client['dob']; ?>" class="w-full p-2 border rounded text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">ID Nummer</label>
                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($client['id_number']); ?>" class="w-full p-2 border rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status Dossier</label>
                    <select name="is_active" class="w-full p-2 border rounded font-bold text-sm <?php echo $client['is_active'] ? 'text-green-700 bg-green-50' : 'text-red-700 bg-red-50'; ?>">
                        <option value="1" <?php if($client['is_active'] == 1) echo 'selected'; ?>>🟢 Actief</option>
                        <option value="0" <?php if($client['is_active'] == 0) echo 'selected'; ?>>🔴 Gearchiveerd</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nationaliteit</label>
                    <input type="text" name="nationality" value="<?php echo htmlspecialchars($client['nationality']); ?>" class="w-full p-2 border rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Spreektaal</label>
                    <input type="text" name="language" value="<?php echo htmlspecialchars($client['language']); ?>" class="w-full p-2 border rounded text-sm">
                </div>
            </div>

            <h3 class="text-sm font-bold text-teal-700 border-b border-gray-200 pb-2 mb-4 mt-8 uppercase">Adres & Woonsituatie</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Straat + Huisnummer</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($client['address']); ?>" class="w-full p-2 border rounded text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Wijk</label>
                    <select name="neighborhood" class="w-full p-2 border rounded text-sm bg-white">
                        <?php foreach($wijken as $w): ?>
                            <option value="<?php echo $w; ?>" <?php if($client['neighborhood'] == $w) echo 'selected'; ?>><?php echo $w; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">District</label>
                    <select name="district" class="w-full p-2 border rounded text-sm bg-white">
                        <?php foreach($districten as $d): ?>
                            <option value="<?php echo $d; ?>" <?php if($client['district'] == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Type Woning</label>
                    <select name="housing_type" class="w-full p-2 border rounded text-sm bg-white">
                        <option value="Woonhuis" <?php if($client['housing_type'] == 'Woonhuis') echo 'selected'; ?>>Woonhuis</option>
                        <option value="Appartement" <?php if($client['housing_type'] == 'Appartement') echo 'selected'; ?>>Appartement</option>
                        <option value="Aanleunwoning" <?php if($client['housing_type'] == 'Aanleunwoning') echo 'selected'; ?>>Aanleunwoning</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Etage</label>
                    <input type="text" name="floor_level" value="<?php echo htmlspecialchars($client['floor_level']); ?>" class="w-full p-2 border rounded text-sm">
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Parkeerinformatie</label>
                    <input type="text" name="parking_info" value="<?php echo htmlspecialchars($client['parking_info']); ?>" class="w-full p-2 border rounded text-sm">
                </div>
            </div>

            <h3 class="text-sm font-bold text-teal-700 border-b border-gray-200 pb-2 mb-4 mt-8 uppercase">Contactpersonen</h3>
            <div class="bg-slate-50 p-4 rounded border border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="md:col-span-3 text-xs font-bold text-slate-600 uppercase">Eerste Contactpersoon</div>
                <input type="text" name="contact1_name" value="<?php echo htmlspecialchars($client['contact1_name']); ?>" placeholder="Naam" class="w-full p-2 border rounded text-sm">
                <input type="text" name="contact1_phone" value="<?php echo htmlspecialchars($client['contact1_phone']); ?>" placeholder="Telefoon" class="w-full p-2 border rounded text-sm">
                <input type="email" name="contact1_email" value="<?php echo htmlspecialchars($client['contact1_email']); ?>" placeholder="Email" class="w-full p-2 border rounded text-sm">
                
                <div class="md:col-span-3 text-xs font-bold text-slate-600 uppercase mt-2">Tweede Contactpersoon (Optioneel)</div>
                <input type="text" name="contact2_name" value="<?php echo htmlspecialchars($client['contact2_name']); ?>" placeholder="Naam" class="w-full p-2 border rounded text-sm">
                <input type="text" name="contact2_phone" value="<?php echo htmlspecialchars($client['contact2_phone']); ?>" placeholder="Telefoon" class="w-full p-2 border rounded text-sm">
                <input type="email" name="contact2_email" value="<?php echo htmlspecialchars($client['contact2_email']); ?>" placeholder="Email" class="w-full p-2 border rounded text-sm">
            </div>

            <h3 class="text-sm font-bold text-teal-700 border-b border-gray-200 pb-2 mb-4 mt-8 uppercase">Medisch & Welzijn</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Diabetes</label>
                    <select name="diabetes_type" class="w-full p-2 border rounded text-sm bg-white">
                        <option value="Geen" <?php if($client['diabetes_type'] == 'Geen') echo 'selected'; ?>>Geen</option>
                        <option value="Type 1" <?php if($client['diabetes_type'] == 'Type 1') echo 'selected'; ?>>Type 1 (Insuline)</option>
                        <option value="Type 2" <?php if($client['diabetes_type'] == 'Type 2') echo 'selected'; ?>>Type 2 (Tabletten/Dieet)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Allergieën</label>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <?php foreach($opties_allergie as $a): ?>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="allergies[]" value="<?php echo $a; ?>" 
                                    class="text-teal-600 rounded focus:ring-0" 
                                    <?php if(in_array($a, $current_allergies)) echo 'checked'; ?>>
                                <span class="text-slate-700"><?php echo $a; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Hulpmiddelen</label>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <?php foreach($opties_hulpmiddelen as $h): ?>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="aids[]" value="<?php echo $h; ?>" 
                                    class="text-blue-600 rounded focus:ring-0"
                                    <?php if(in_array($h, $current_aids)) echo 'checked'; ?>>
                                <span class="text-slate-700"><?php echo $h; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Algemene Opmerkingen</label>
                    <textarea name="notes" rows="4" class="w-full p-2 border rounded text-sm"><?php echo htmlspecialchars($client['notes']); ?></textarea>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded shadow-sm text-sm uppercase tracking-wide transition-transform transform hover:scale-105">
                <i class="fa-solid fa-floppy-disk mr-2"></i> Wijzigingen Opslaan
            </button>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden mb-8">
        <div class="bg-purple-50 px-6 py-4 border-b border-purple-100 flex justify-between items-center">
            <h3 class="font-bold text-purple-800 uppercase text-xs tracking-wide">
                <i class="fa-solid fa-users mr-2"></i> Familie & Toegang
            </h3>
        </div>
        
        <div class="p-6">
            <div class="mb-8">
                <h4 class="text-sm font-bold text-slate-700 mb-2">Gekoppelde Accounts</h4>
                <?php if(count($family_linked) > 0): ?>
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-slate-500 uppercase text-xs">
                                <tr>
                                    <th class="p-2">Gebruikersnaam</th>
                                    <th class="p-2">Email</th>
                                    <th class="p-2 text-right">Actie</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach($family_linked as $fu): ?>
                                    <tr>
                                        <td class="p-2 font-bold text-slate-700"><?php echo htmlspecialchars($fu['username']); ?></td>
                                        <td class="p-2 text-slate-500"><?php echo htmlspecialchars($fu['email']); ?></td>
                                        <td class="p-2 text-right">
                                            <a href="?id=<?php echo $client_id; ?>&unlink_user=<?php echo $fu['id']; ?>" class="text-red-500 hover:text-red-700 text-xs font-bold uppercase" onclick="return confirm('Toegang intrekken?')">
                                                <i class="fa-solid fa-trash-can mr-1"></i> Ontkoppel
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-slate-400 italic">Er zijn nog geen familieleden gekoppeld.</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4 border-t border-gray-100">
                
                <div>
                    <h4 class="text-sm font-bold text-slate-700 mb-2">Bestaand account toevoegen</h4>
                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="action" value="link_family">
                        <?php if(count($available_family) > 0): ?>
                            <select name="family_user_id" class="flex-1 p-2 border border-gray-300 rounded text-sm bg-white">
                                <option value="">-- Selecteer Familie --</option>
                                <?php foreach($available_family as $af): ?>
                                    <option value="<?php echo $af['id']; ?>"><?php echo htmlspecialchars($af['username']); ?> (<?php echo htmlspecialchars($af['email']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-bold">
                                Koppel
                            </button>
                        <?php else: ?>
                            <p class="text-xs text-slate-400 italic py-2">Alle familie-accounts zijn al gekoppeld of er zijn geen actieve accounts.</p>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="bg-gray-50 p-4 rounded border border-gray-200">
                    <h4 class="text-sm font-bold text-slate-700 mb-2 flex items-center">
                        <i class="fa-solid fa-user-plus mr-2 text-slate-400"></i> Nieuw account maken & koppelen
                    </h4>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_link_family">
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <input type="text" name="new_username" placeholder="Gebruikersnaam" class="w-full p-2 border rounded text-sm" required>
                            <input type="password" name="new_password" placeholder="Wachtwoord" class="w-full p-2 border rounded text-sm" required>
                        </div>
                        <div class="mb-3">
                            <input type="email" name="new_email" placeholder="Email (optioneel)" class="w-full p-2 border rounded text-sm">
                        </div>
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 rounded text-sm transition shadow-sm">
                            Account Maken & Direct Koppelen
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>