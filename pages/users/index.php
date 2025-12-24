<?php
// pages/users/index.php
include '../../includes/header.php';
require '../../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'management') {
    echo "<script>window.location.href='../../dashboard.php';</script>";
    exit;
}

$sql = "SELECT users.*, np.first_name, np.last_name, np.job_title, np.phone, np.district, np.contract_type
        FROM users 
        LEFT JOIN nurse_profiles np ON users.id = np.user_id 
        ORDER BY users.role ASC, users.username ASC";
$users = $pdo->query($sql)->fetchAll();
?>

<div class="w-full max-w-7xl mx-auto">
    
    <div class="bg-white border border-gray-300 p-4 mb-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Personeelsbeheer</h1>
            <p class="text-xs text-slate-500">Toegangsbeheer en HR dossiers</p>
        </div>
        <a href="create.php" class="bg-blue-700 hover:bg-blue-800 text-white text-sm font-medium py-2 px-4 flex items-center transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Medewerker Toevoegen
        </a>
    </div>

    <div class="bg-white border border-gray-300 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-slate-500 border-b border-gray-300 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 font-bold">Gebruiker</th>
                    <th class="px-4 py-3 font-bold">Rol / Functie</th>
                    <th class="px-4 py-3 font-bold">Contact</th>
                    <th class="px-4 py-3 font-bold">Status</th>
                    <th class="px-4 py-3 font-bold text-center">Acties</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        
                        <td class="px-4 py-3 align-top">
                            <div class="font-bold text-slate-800">
                                <?php echo $user['first_name'] ? htmlspecialchars($user['first_name'].' '.$user['last_name']) : htmlspecialchars($user['username']); ?>
                            </div>
                            <div class="text-xs text-slate-400 font-mono mt-0.5">@<?php echo htmlspecialchars($user['username']); ?></div>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <?php if ($user['role'] == 'management'): ?>
                                <span class="bg-purple-50 text-purple-800 border border-purple-200 px-2 py-0.5 text-xs font-bold uppercase">Management</span>
                            <?php elseif ($user['role'] == 'zuster'): ?>
                                <div class="font-medium text-blue-800"><?php echo htmlspecialchars($user['job_title'] ?? 'Zorgpersoneel'); ?></div>
                                <div class="text-xs text-slate-500"><?php echo $user['contract_type'] ?? '-'; ?></div>
                            <?php else: ?>
                                <span class="bg-gray-100 text-gray-600 border border-gray-200 px-2 py-0.5 text-xs font-bold uppercase">Familie</span>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3 align-top text-slate-600">
                            <?php if ($user['phone']): ?>
                                <div class="flex items-center text-xs mb-1">
                                    <svg class="w-3 h-3 mr-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                    <?php echo htmlspecialchars($user['phone']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="text-xs text-blue-600 underline decoration-blue-200"><?php echo htmlspecialchars($user['email']); ?></div>
                        </td>

                        <td class="px-4 py-3 align-top">
                            <?php if ($user['is_active']): ?>
                                <span class="text-green-700 font-bold text-xs flex items-center">
                                    <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div> Actief
                                </span>
                            <?php else: ?>
                                <span class="text-red-700 font-bold text-xs flex items-center">
                                    <div class="w-2 h-2 bg-red-500 rounded-full mr-2"></div> Inactief
                                </span>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3 align-top text-center">
                            <div class="flex justify-center space-x-2">
                                <?php if ($user['role'] == 'zuster'): ?>
                                    <a href="profile.php?id=<?php echo $user['id']; ?>" class="text-slate-500 hover:text-blue-600 transition" title="HR Dossier">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </a>
                                <?php endif; ?>
                                <a href="edit.php?id=<?php echo $user['id']; ?>" class="text-slate-500 hover:text-orange-600 transition" title="Bewerken">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>