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

// 2. OPSLAAN (POST REQUEST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // A. Update Basisgegevens
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

        // B. Update Allergieën (Eerst alles weg, dan nieuw erin)
        $pdo->prepare("DELETE FROM client_allergies WHERE client_id = ?")->execute([$client_id]);
        if (isset($_POST['allergies'])) {
            $stmt_all = $pdo->prepare("INSERT INTO client_allergies (client_id, allergy_type) VALUES (?, ?)");
            foreach ($_POST['allergies'] as $a) {
                $stmt_all->execute([$client_id, $a]);
            }
        }

        // C. Update Hulpmiddelen (Eerst alles weg, dan nieuw erin)
        $pdo->prepare("DELETE FROM client_aids WHERE client_id = ?")->execute([$client_id]);
        if (isset($_POST['aids'])) {
            $stmt_aid = $pdo->prepare("INSERT INTO client_aids (client_id, aid_name) VALUES (?, ?)");
            foreach ($_POST['aids'] as $aid) {
                $stmt_aid->execute([$client_id, $aid]);
            }
        }

        $pdo->commit();
        echo "<script>window.location.href='detail.php?id=$client_id';</script>";
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}

// 3. OPHALEN HUIDIGE GEGEVENS (GET REQUEST)
// Basis Data
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) die("Cliënt niet gevonden.");

// Lijstjes ophalen (om de checkboxes aan te vinken)
$stmt = $pdo->prepare("SELECT allergy_type FROM client_allergies WHERE client_id = ?");
$stmt->execute([$client_id]);
$current_allergies = $stmt->fetchAll(PDO::FETCH_COLUMN); // Geeft platte array: ['Pinda', 'Latex']

$stmt = $pdo->prepare("SELECT aid_name FROM client_aids WHERE client_id = ?");
$stmt->execute([$client_id]);
$current_aids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// HULP-ARRAYS VOOR FORMULIER OPTIES
$districten = ['Paramaribo','Wanica','Commewijne','Para','Saramacca','Marowijne','Coronie','Nickerie','Brokopondo','Sipaliwini'];
$wijken = ['Centrum','Beekhuizen','Blauwgrond','Flora','Latour','Livorno','Munder','Pontbuiten','Rainville','Tammenga','Weg naar See','Welgelegen','Overig'];
$opties_allergie = ['Antibiotica', 'Pinda', 'Lactose', 'Gluten', 'Latex', 'Insectenbeten', 'Huisstofmijt'];
$opties_hulpmiddelen = ['Bril', 'Gehoorapparaat', 'Kunstgebit', 'Rollator', 'Rolstoel', 'Wandelstok', 'Steunkousen', 'Incontinentiemateriaal'];
?>

<div class="max-w-5xl mx-auto mb-12">
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-800">✏️ Stamkaart Wijzigen</h2>
        <a href="detail.php?id=<?php echo $client_id; ?>" class="text-gray-500 hover:text-gray-700 font-bold">
            Annuleren ✕
        </a>
    </div>

    <?php if(isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow-lg p-6">
        
        <h3 class="text-lg font-bold text-teal-700 border-b pb-2 mb-4">1. Persoonlijke Gegevens</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div>
                <label class="block text-sm font-bold text-gray-700">Voornaam</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($client['first_name']); ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Achternaam</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($client['last_name']); ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Geslacht</label>
                <select name="gender" class="w-full p-2 border rounded">
                    <option value="M" <?php if($client['gender'] == 'M') echo 'selected'; ?>>Man</option>
                    <option value="V" <?php if($client['gender'] == 'V') echo 'selected'; ?>>Vrouw</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Geboortedatum</label>
                <input type="date" name="dob" value="<?php echo $client['dob']; ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">ID Nummer</label>
                <input type="text" name="id_number" value="<?php echo htmlspecialchars($client['id_number']); ?>" class="w-full p-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Status Dossier</label>
                <select name="is_active" class="w-full p-2 border rounded font-bold <?php echo $client['is_active'] ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'; ?>">
                    <option value="1" <?php if($client['is_active'] == 1) echo 'selected'; ?>>🟢 Actief</option>
                    <option value="0" <?php if($client['is_active'] == 0) echo 'selected'; ?>>🔴 Gearchiveerd (Uit zorg)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <label class="block text-sm font-bold text-gray-700">Nationaliteit</label>
                <input type="text" name="nationality" value="<?php echo htmlspecialchars($client['nationality']); ?>" class="w-full p-2 border rounded">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Spreektaal</label>
                <input type="text" name="language" value="<?php echo htmlspecialchars($client['language']); ?>" class="w-full p-2 border rounded">
            </div>
        </div>

        <h3 class="text-lg font-bold text-teal-700 border-b pb-2 mb-4">2. Adres & Woonsituatie</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700">Straat + Huisnummer</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($client['address']); ?>" class="w-full p-2 border rounded" required>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Wijk</label>
                <select name="neighborhood" class="w-full p-2 border rounded">
                    <?php foreach($wijken as $w): ?>
                        <option value="<?php echo $w; ?>" <?php if($client['neighborhood'] == $w) echo 'selected'; ?>><?php echo $w; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">District</label>
                <select name="district" class="w-full p-2 border rounded">
                    <?php foreach($districten as $d): ?>
                        <option value="<?php echo $d; ?>" <?php if($client['district'] == $d) echo 'selected'; ?>><?php echo $d; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Type Woning</label>
                <select name="housing_type" class="w-full p-2 border rounded">
                    <option value="Woonhuis" <?php if($client['housing_type'] == 'Woonhuis') echo 'selected'; ?>>Woonhuis</option>
                    <option value="Appartement" <?php if($client['housing_type'] == 'Appartement') echo 'selected'; ?>>Appartement</option>
                    <option value="Aanleunwoning" <?php if($client['housing_type'] == 'Aanleunwoning') echo 'selected'; ?>>Aanleunwoning</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700">Etage / Bereikbaarheid</label>
                <input type="text" name="floor_level" value="<?php echo htmlspecialchars($client['floor_level']); ?>" class="w-full p-2 border rounded" placeholder="bv. Begane grond">
            </div>
            <div class="md:col-span-3">
                <label class="block text-sm font-bold text-gray-700">Parkeerinformatie</label>
                <input type="text" name="parking_info" value="<?php echo htmlspecialchars($client['parking_info']); ?>" class="w-full p-2 border rounded">
            </div>
        </div>

        <h3 class="text-lg font-bold text-teal-700 border-b pb-2 mb-4">3. Contactpersonen</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 bg-gray-50 p-4 rounded border border-gray-200">
            <div class="md:col-span-3 font-bold text-gray-600">Eerste Contactpersoon</div>
            <input type="text" name="contact1_name" value="<?php echo htmlspecialchars($client['contact1_name']); ?>" placeholder="Naam" class="w-full p-2 border rounded">
            <input type="text" name="contact1_phone" value="<?php echo htmlspecialchars($client['contact1_phone']); ?>" placeholder="Telefoon" class="w-full p-2 border rounded">
            <input type="email" name="contact1_email" value="<?php echo htmlspecialchars($client['contact1_email']); ?>" placeholder="Email" class="w-full p-2 border rounded">
            
            <div class="md:col-span-3 font-bold text-gray-600 mt-2">Tweede Contactpersoon (Optioneel)</div>
            <input type="text" name="contact2_name" value="<?php echo htmlspecialchars($client['contact2_name']); ?>" placeholder="Naam" class="w-full p-2 border rounded">
            <input type="text" name="contact2_phone" value="<?php echo htmlspecialchars($client['contact2_phone']); ?>" placeholder="Telefoon" class="w-full p-2 border rounded">
            <input type="email" name="contact2_email" value="<?php echo htmlspecialchars($client['contact2_email']); ?>" placeholder="Email" class="w-full p-2 border rounded">
        </div>

        <h3 class="text-lg font-bold text-teal-700 border-b pb-2 mb-4">4. Medisch & Welzijn</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Diabetes</label>
                <select name="diabetes_type" class="w-full p-2 border rounded bg-white">
                    <option value="Geen" <?php if($client['diabetes_type'] == 'Geen') echo 'selected'; ?>>Geen</option>
                    <option value="Type 1" <?php if($client['diabetes_type'] == 'Type 1') echo 'selected'; ?>>Type 1 (Insuline)</option>
                    <option value="Type 2" <?php if($client['diabetes_type'] == 'Type 2') echo 'selected'; ?>>Type 2 (Tabletten/Dieet)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Allergieën</label>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <?php foreach($opties_allergie as $a): ?>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="allergies[]" value="<?php echo $a; ?>" 
                                   class="text-teal-600 rounded" 
                                   <?php if(in_array($a, $current_allergies)) echo 'checked'; ?>>
                            <span><?php echo $a; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">Hulpmiddelen</label>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <?php foreach($opties_hulpmiddelen as $h): ?>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="aids[]" value="<?php echo $h; ?>" 
                                   class="text-blue-600 rounded"
                                   <?php if(in_array($h, $current_aids)) echo 'checked'; ?>>
                            <span><?php echo $h; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-2">Algemene Opmerkingen / Bijzonderheden</label>
                <textarea name="notes" rows="4" class="w-full p-2 border rounded"><?php echo htmlspecialchars($client['notes']); ?></textarea>
            </div>
        </div>

        <div class="flex justify-end space-x-4 border-t pt-6">
            <a href="detail.php?id=<?php echo $client_id; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded">
                Annuleren
            </a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded shadow">
                💾 Wijzigingen Opslaan
            </button>
        </div>

    </form>
</div>

<?php include '../../includes/footer.php'; ?>