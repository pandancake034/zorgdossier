<?php
// logout.php

// 1. Start de sessie (zodat we toegang hebben tot de huidige gegevens)
session_start();

// 2. Maak alle sessie-variabelen leeg (user_id, role, etc.)
$_SESSION = [];

// 3. Vernietig de sessie volledig op de server
session_destroy();

// 4. Verwijder ook de sessie-cookie uit de browser (voor de zekerheid)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 5. Stuur de gebruiker terug naar de login pagina
// We voegen '?msg=logout' toe zodat we een melding kunnen tonen
header("Location: login.php?msg=logout");
exit;
?>