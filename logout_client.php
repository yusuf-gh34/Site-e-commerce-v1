<?php
session_start();

// Détruire uniquement les sessions client
unset($_SESSION['client_logged_in']);
unset($_SESSION['client_id']);
unset($_SESSION['client_nom']);
unset($_SESSION['client_telephone']);
unset($_SESSION['client_role']);

// === NOUVEAU : Vider le panier à la déconnexion ===
unset($_SESSION['panier']);
unset($_SESSION['panier_produits']);

// Vérifier si un admin ou gestionnaire est toujours connecté
$admin_connecte = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$manager_connecte = isset($_SESSION['manager_logged_in']) && $_SESSION['manager_logged_in'] === true;

if (!$admin_connecte && !$manager_connecte) {
    // Si aucun admin/gestionnaire n'est connecté, détruire complètement la session
    $_SESSION = array();
    session_destroy();
}

// Rediriger vers la page d'accueil
header("Location: index.php?message=deconnexion");
exit();
?>