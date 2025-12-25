<?php
// pages/profile/my_profile.php
include '../../includes/header.php';
require '../../config/db.php';

// Check ingelogd
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// 1. DATA OPSLAAN (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Basisgegevens (Email)
        $email = trim($_POST['email']);
        $stmt_user = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt_user->execute([$email, $user_id]);

        // Profielgegevens (Alleen als gebruiker een zuster/medewerker is)
        if ($_SESSION['role'] === 'zuster' || $_SESSION['role'] === 'management') {
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $neighborhood = $_POST['neighborhood'];
            $city = $_POST['city'];
            $has_car = isset($_POST['has_car']) ? 1 : 0;

            // Check of profiel bestaat (zou moeten, maar voor zekerheid)
            $check = $pdo->prepare("SELECT id FROM nurse_profiles WHERE user_id = ?");
            $check->execute([$user_id]);
            
            if ($check->rowCount() > 0) {
                $stmt_prof = $pdo->prepare("UPDATE nurse_profiles SET phone=?, address=?, neighborhood=?, city=?, has_car=? WHERE user_id=?");
                $stmt_prof->execute([$phone, $address, $neighborhood, $city, $has_car, $user_id]);
            } else {
                // Als er nog geen profiel was (bv management), maken we er een aan of negeren we het
                // Voor nu negeren we het om complexiteit te voorkomen, management heeft vaak geen nurse_profile
            }
        }

        $pdo->commit();
        $success = "Gegevens succesvol bijgewerkt!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Fout bij opslaan: " . $e->getMessage();
    }
}

// 2. DATA OPHALEN
// Left join zodat we altijd de user data hebben, ook als er geen profiel is
$sql = "SELECT u.username, u.email, u.role, np.* FROM users u 
        LEFT JOIN nurse_profiles np ON u.id = np.user_id 
        WHERE u.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$me = $stmt->fetch();

?>

<div class="max-w-4xl mx-auto mb-12">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 uppercase tracking-tight">
                <i class="fa-solid fa-user-pen mr-2 text-slate-400"></i> Mijn Profiel
            </h1>
            <p class="text-sm text-slate-500">Beheer uw contactgegevens en instellingen.</p>
        </div>
        <a href="my_hr.php" class="text-slate-500 hover:text-slate-700 font-bold text-sm flex items-center transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Terug naar HR
        </a>
    </div>

    <?php if($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 mb-6 shadow-sm flex items-center">
            <i class="fa-solid fa-circle-check mr-3 text-xl"></i>
            <div>
                <p class="font-bold">Gelukt!</p>
                <p class="text-sm"><?php echo $success; ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-800 p-4 mb-6 shadow-sm flex items-center">
            <i class="fa-solid fa-triangle-exclamation mr-3 text-xl"></i>
            <div>
                <p class="font-bold">Er ging iets mis</p>
                <p class="text-sm"><?php echo $error; ?></p>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <div class="md:col-span-1 space-y-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-300 p-6 text-center">
                <div class="h-24 w-24 bg-slate-100 rounded-full mx-auto flex items-center justify-center text-4xl text-slate-400 mb-4 border border-gray-200">
                    <i class="fa-solid fa-user"></i>
                </div>
                <h2 class="font-bold text-lg text-slate-800"><?php echo htmlspecialchars($me['first_name'] ? $me['first_name'].' '.$me['last_name'] : $me['username']); ?></h2>
                <p class="text-xs text-slate-500 uppercase font-bold mt-1 mb-3">
                    <?php echo htmlspecialchars($me['job_title'] ?? $me['role']); ?>
                </p>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Actief Account
                </span>
            </div>

            <div class="bg-blue-50 rounded-lg shadow-sm border border-blue-100 p-5">
                <h3 class="text-xs font-bold text-blue-800 uppercase mb-3 border-b border-blue-200 pb-2">
                    <i class="fa-solid fa-lock mr-2"></i> Account Info
                </h3>
                <div class="space-y-3 text-sm">
                    <div>
                        <label class="block text-[10px] uppercase text-blue-500 font-bold">Gebruikersnaam</label>
                        <div class="font-mono text-slate-700 bg-white/50 p-1 rounded"><?php echo htmlspecialchars($me['username']); ?></div>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase text-blue-500 font-bold">Rol</label>
                        <div class="text-slate-700"><?php echo ucfirst($me['role']); ?></div>
                    </div>
                    <div class="pt-2">
                        <a href="#" class="text-xs text-blue-600 hover:underline flex items-center opacity-50 cursor-not-allowed" title="Neem contact op met beheer">
                            <i class="fa-solid fa-key mr-1"></i> Wachtwoord wijzigen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-white rounded-lg shadow-sm border border-gray-300 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-gray-300">
                    <h3 class="font-bold text-slate-700 flex items-center">
                        <i class="fa-regular fa-address-card mr-2 text-slate-400"></i> Contactgegevens
                    </h3>
                </div>
                
                <div class="p-6 space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Emailadres</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($me['email']); ?>" class="w-full pl-10 p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0">
                        </div>
                        <p class="text-xs text-slate-400 mt-1">Wordt gebruikt voor notificaties en herstel.</p>
                    </div>

                    <?php if($_SESSION['role'] === 'zuster' || ($_SESSION['role'] === 'management' && isset($me['phone']))): ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Telefoonnummer</label>
                            <input type="text" name="phone" value="<?php echo htmlspecialchars($me['phone']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Vervoer</label>
                            <label class="flex items-center space-x-2 p-2 border border-gray-200 rounded bg-gray-50 cursor-pointer hover:bg-white">
                                <input type="checkbox" name="has_car" value="1" <?php if($me['has_car']) echo 'checked'; ?> class="text-blue-600 focus:ring-0 rounded">
                                <span class="text-sm text-slate-700">Ik beschik over eigen vervoer (Auto)</span>
                            </label>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <label class="block text-sm font-bold text-slate-700 mb-3">Adresgegevens</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Straat + Huisnummer</label>
                                <input type="text" name="address" value="<?php echo htmlspecialchars($me['address']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Wijk</label>
                                <input type="text" name="neighborhood" value="<?php echo htmlspecialchars($me['neighborhood']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Stad / District</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($me['city']); ?>" class="w-full p-2 border border-gray-300 rounded text-sm focus:border-blue-500 focus:ring-0">
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                </div>

                <div class="bg-gray-50 px-6 py-4 border-t border-gray-300 flex justify-end">
                    <button type="submit" class="bg-blue-700 hover:bg-blue-800 text-white font-bold py-2 px-6 rounded shadow-sm text-sm uppercase tracking-wide transition-transform transform hover:scale-105">
                        <i class="fa-solid fa-floppy-disk mr-2"></i> Opslaan
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<?php include '../../includes/footer.php'; ?>