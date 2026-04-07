<?php
session_start();

// Détruire uniquement les sessions admin et gestionnaire
unset($_SESSION['user_logged_in']);
unset($_SESSION['user_id']);
unset($_SESSION['user_nom']);
unset($_SESSION['user_telephone']);
unset($_SESSION['user_role']);

unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_nom']);
unset($_SESSION['admin_telephone']);
unset($_SESSION['admin_role']);

unset($_SESSION['manager_logged_in']);
unset($_SESSION['manager_id']);
unset($_SESSION['manager_nom']);
unset($_SESSION['manager_telephone']);

// Vérifier si le client est toujours connecté
if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    // Si aucun client n'est connecté, détruire complètement la session
    $_SESSION = array();
    session_destroy();
}

// Rediriger vers la page de connexion admin
header("Location: login.php");
exit();
?>