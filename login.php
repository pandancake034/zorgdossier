<?php
// login.php
session_start();
require 'config/db.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$error = '';

// Als het formulier is verzonden
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // Zoek de gebruiker (Prepared Statement voor veiligheid)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Bestaat de gebruiker EN klopt het wachtwoord?
        if ($user && password_verify($password, $user['password'])) {
            
            // Check of account actief is
            if ($user['is_active'] == 1) {
                // Sessie vullen
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Doorsturen naar dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Dit account is inactief. Neem contact op met beheer.";
            }
        } else {
            $error = "Ongeldige gebruikersnaam of wachtwoord.";
        }
    } else {
        $error = "Vul alle velden in.";
    }
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zorgdossier Inloggen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-full max-w-md bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="bg-teal-600 p-4 text-center">
            <h1 class="text-white text-2xl font-bold">Zorgdossier Suriname</h1>
            <p class="text-teal-100 text-sm">Veilige toegang</p>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                        Gebruikersnaam
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-teal-500" 
                           id="username" name="username" type="text" placeholder="bv. avinash" required>
                </div>

                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        Wachtwoord
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-teal-500" 
                           id="password" name="password" type="password" placeholder="******************" required>
                </div>

                <div class="flex items-center justify-between">
                    <button class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300" 
                            type="submit">
                        Inloggen
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-gray-50 text-center p-4 text-xs text-gray-500 border-t">
            &copy; 2025 Zorgdossier Systeem. Alle rechten voorbehouden.
        </div>
    </div>

</body>
</html>
