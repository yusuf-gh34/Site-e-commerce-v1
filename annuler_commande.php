<?php
session_start();
require_once 'config/database.php';

// Vérifier si le client est connecté
if(!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login_client.php?error=connectez_vous");
    exit();
}

// Vérifier si l'ID de commande est fourni
if(!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: mes_commandes.php?error=missing_id");
    exit();
}

$commande_id = (int)$_GET['id'];
$client_id = $_SESSION['client_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Vérifier que la commande appartient bien au client et est annulable (statut "En attente" = id_status 1)
    $check_query = "SELECT c.id_commande, c.id_status, s.nom as status_nom 
                    FROM commande c
                    JOIN status s ON c.id_status = s.id_status
                    WHERE c.id_commande = :id_commande AND c.id_utilisateur = :id_utilisateur";
    
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id_commande', $commande_id);
    $check_stmt->bindParam(':id_utilisateur', $client_id);
    $check_stmt->execute();
    
    $commande = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$commande) {
        header("Location: mes_commandes.php?error=not_found");
        exit();
    }
    
    // Vérifier si la commande peut être annulée (statut "En attente" uniquement)
    if($commande['id_status'] != 1) {
        header("Location: mes_commandes.php?error=cannot_cancel");
        exit();
    }
    
    // Récupérer les produits de la commande pour restaurer les stocks
    $details_query = "SELECT id_produit, quantite FROM ligne_commande WHERE id_commande = :id_commande";
    $details_stmt = $db->prepare($details_query);
    $details_stmt->bindParam(':id_commande', $commande_id);
    $details_stmt->execute();
    $details = $details_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    // Restaurer les stocks des produits
    foreach($details as $detail) {
        $update_stock = "UPDATE produit SET stock = stock + :quantite WHERE id_produit = :id_produit";
        $update_stmt = $db->prepare($update_stock);
        $update_stmt->bindParam(':quantite', $detail['quantite']);
        $update_stmt->bindParam(':id_produit', $detail['id_produit']);
        $update_stmt->execute();
    }
    
    // Mettre à jour le statut de la commande vers "Annulée" (id_status = 6)
    $update_query = "UPDATE commande SET id_status = 6 WHERE id_commande = :id_commande";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':id_commande', $commande_id);
    $update_stmt->execute();
    
    // Valider la transaction
    $db->commit();
    
    // Rediriger avec message de succès
    header("Location: mes_commandes.php?success=canceled");
    exit();
    
} catch(Exception $e) {
    // En cas d'erreur, annuler la transaction
    if($db->inTransaction()) {
        $db->rollBack();
    }
    header("Location: mes_commandes.php?error=db_error");
    exit();
}
?>