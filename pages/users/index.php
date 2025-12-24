<?php
// pages/users/index.php

// 1. Paden naar de configuratie en header
include '../../includes/header.php';
require '../../config/db.php';

// 2. BEVEILIGING: Alleen management mag hier komen
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
    // Stuur onbevoegden direct terug naar dashboard
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

// 3. QUERY: Haal gebruikers op + hun HR gegevens
// We gebruiken LEFT JOIN zodat we ook gebruikers zien die (nog) geen HR profiel hebben
$sql = "SELECT users.*, 
               nurse_profiles.first_name, 
               nurse_profiles.last_name, 
               nurse_profiles.job_title, 
               nurse_profiles.phone,
               nurse_profiles.district,
               nurse_profiles.contract_type
        FROM users 
        LEFT JOIN nurse_profiles ON users.id = nurse_profiles.user_id 
        ORDER BY users.role ASC, users.username ASC";

try {
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Fout bij ophalen gegevens: " . $e->getMessage());
}
?>

<div class="bg-white rounded-lg shadow-lg p-6">
    
    <div class="flex flex-col md:flex-row justify-between items-center mb-6">
        <div class="mb-4 md:mb-0">
            <h2 class="text-2xl font-bold text-gray-800">Personeelsbeheer</h2>
            <p class="text-gray-600">Beheer accounts en personeelsdossiers.</p>
        </div>
        <a href="create.php" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded shadow flex items-center transition duration-200">
            <span class="mr-2">➕</span> Nieuwe Medewerker
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
            <thead class="bg-gray-100 text-gray-600 uppercase text-sm leading-normal">
                <tr>
                    <th class="py-3 px-6 text-left">Naam / Inlog</th>
                    <th class="py-3 px-6 text-left">Functie</th>
                    <th class="py-3 px-6 text-left">Contact</th>
                    <th class="py-3 px-6 text-center">Status</th>
                    <th class="py-3 px-6 text-center">Acties</th>
                </tr>
            </thead>
            <tbody class="text-gray-600 text-sm font-light">
                
                <?php if (count($users) > 0): ?>
                    <?php foreach ($users as $user): ?>
                        <tr class="border-b border-gray-200 hover:bg-gray-50 transition duration-150">
                            
                            <td class="py-3 px-6 text-left whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="mr-3">
                                        <?php if($user['role'] == 'management'): ?>
                                            <span class="text-2xl" title="Management">👔</span>
                                        <?php elseif($user['role'] == 'zuster'): ?>
                                            <span class="text-2xl" title="Zorgpersoneel">👩‍⚕️</span>
                                        <?php else: ?>
                                            <span class="text-2xl" title="Familie">🏠</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="font-medium block text-gray-800 text-base">
                                            <?php 
                                                // Toon echte naam als die er is, anders gebruikersnaam
                                                echo $user['first_name'] 
                                                    ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) 
                                                    : htmlspecialchars($user['username']); 
                                            ?>
                                        </span>
                                        <span class="text-xs text-gray-500">Inlog: <?php echo htmlspecialchars($user['username']); ?></span>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3 px-6 text-left">
                                <?php if ($user['role'] == 'management'): ?>
                                    <span class="bg-purple-100 text-purple-700 py-1 px-3 rounded-full text-xs font-bold border border-purple-200">Management</span>
                                <?php elseif ($user['role'] == 'zuster'): ?>
                                    <span class="bg-blue-100 text-blue-700 py-1 px-3 rounded-full text-xs font-bold border border-blue-200">
                                        <?php echo htmlspecialchars($user['job_title'] ?? 'Zuster'); ?>
                                    </span>
                                    <?php if($user['contract_type']): ?>
                                        <div class="text-xs mt-1 text-gray-500 ml-1">Contract: <?php echo $user['contract_type']; ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="bg-pink-100 text-pink-700 py-1 px-3 rounded-full text-xs font-bold border border-pink-200">Familie</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-6 text-left">
                                <?php if ($user['phone']): ?>
                                    <div class="flex items-center mb-1">
                                        <span class="mr-1">📞</span> <?php echo htmlspecialchars($user['phone']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user['district']): ?>
                                    <div class="text-xs text-gray-500">
                                        📍 <?php echo htmlspecialchars($user['district']); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-xs text-gray-400 mt-1"><?php echo htmlspecialchars($user['email']); ?></div>
                            </td>

                            <td class="py-3 px-6 text-center">
                                <?php if ($user['is_active']): ?>
                                    <span class="bg-green-100 text-green-600 py-1 px-3 rounded-full text-xs font-semibold border border-green-200">Actief</span>
                                <?php else: ?>
                                    <span class="bg-red-100 text-red-600 py-1 px-3 rounded-full text-xs font-semibold border border-red-200">Inactief</span>
                                <?php endif; ?>
                            </td>

                            <td class="py-3 px-6 text-center">
                                <div class="flex item-center justify-center space-x-3">
                                    <?php if ($user['role'] == 'zuster'): ?>
                                        <a href="profile.php?id=<?php echo $user['id']; ?>" class="transform hover:text-blue-500 hover:scale-110 transition" title="Bekijk HR Dossier">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="edit.php?id=<?php echo $user['id']; ?>" class="transform hover:text-yellow-500 hover:scale-110 transition" title="Bewerken">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="py-6 text-center text-gray-500">
                            Geen medewerkers gevonden. Klik op "Nieuwe Medewerker" om te beginnen.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
