<?php
// dashboard.php
// Header include (zorgt ook voor session check)
include 'includes/header.php'; 
?>

<div class="bg-white p-8 rounded-lg shadow-lg mb-8 border-l-8 border-teal-600">
    <h1 class="text-4xl font-bold text-gray-800 mb-2">Welkom, <?php echo ucfirst($username); ?>! 👋</h1>
    <p class="text-gray-600 text-lg">
        U bent ingelogd als <span class="font-bold text-teal-700 bg-teal-100 px-2 py-1 rounded"><?php echo ucfirst($role); ?></span>.
        Wat wilt u vandaag doen?
    </p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

    <?php if ($role === 'management'): ?>
        
        <a href="pages/clients/index.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-gray-200">
            <div class="text-teal-600 text-5xl mb-4 group-hover:scale-110 transition">👥</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Cliënten Dossiers</h3>
            <p class="text-gray-600 text-sm">Beheer intakes, bekijk rapportages en medische gegevens.</p>
        </a>

        <a href="pages/users/index.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-gray-200">
            <div class="text-blue-600 text-5xl mb-4 group-hover:scale-110 transition">📇</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">HR & Personeel</h3>
            <p class="text-gray-600 text-sm">Beheer zusters, contracten en NAW-gegevens.</p>
        </a>

        <a href="pages/planning/manage.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-gray-200">
            <div class="text-purple-600 text-5xl mb-4 group-hover:scale-110 transition">🗺️</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Rooster & Routes</h3>
            <p class="text-gray-600 text-sm">Maak wijkroutes en koppel zusters aan cliënten.</p>
        </a>

        <a href="pages/planning/manage_orders.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-gray-200">
            <div class="text-yellow-600 text-5xl mb-4 group-hover:scale-110 transition">📦</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Bestellingen</h3>
            <p class="text-gray-600 text-sm">Keur aanvragen voor materiaal en medicatie goed.</p>
        </a>

    <?php elseif ($role === 'zuster'): ?>

        <a href="pages/planning/view.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-teal-200 border-l-4">
            <div class="text-red-500 text-5xl mb-4 group-hover:scale-110 transition">🚑</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Mijn Route Vandaag</h3>
            <p class="text-gray-600 text-sm">Bekijk uw wijkroute en vink zorgtaken af.</p>
        </a>

        <a href="pages/clients/index.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-gray-200">
            <div class="text-teal-600 text-5xl mb-4 group-hover:scale-110 transition">📂</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Cliëntenlijst</h3>
            <p class="text-gray-600 text-sm">Zoek een dossier om te rapporteren of bestellen.</p>
        </a>

        <div class="block bg-gray-50 p-6 rounded-lg border border-dashed border-gray-300">
            <div class="text-gray-400 text-5xl mb-4">💬</div>
            <h3 class="font-bold text-xl mb-2 text-gray-500">Berichten</h3>
            <p class="text-gray-500 text-sm">Contact met collega's (Binnenkort).</p>
        </div>

    <?php elseif ($role === 'familie'): ?>

        <a href="pages/clients/index.php" class="group block bg-white p-6 rounded-lg shadow hover:shadow-xl transition transform hover:-translate-y-1 border border-pink-200">
            <div class="text-pink-600 text-5xl mb-4 group-hover:scale-110 transition">❤️</div>
            <h3 class="font-bold text-xl mb-2 text-gray-800">Mijn Familielid</h3>
            <p class="text-gray-600 text-sm">Lees rapportages en bekijk hoe het gaat.</p>
        </a>

    <?php endif; ?>

</div>

</body>
</html>
