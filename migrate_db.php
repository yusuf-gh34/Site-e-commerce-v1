<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Créer la table pour les statuts par vendeur
    $sql = "CREATE TABLE IF NOT EXISTS Commande_Gestionnaire_Status (
        id_commande INT NOT NULL,
        id_gestionnaire INT NOT NULL,
        id_status INT NOT NULL DEFAULT 1,
        date_mise_a_jour TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_commande, id_gestionnaire),
        FOREIGN KEY (id_commande) REFERENCES Commande(id_commande) ON DELETE CASCADE,
        FOREIGN KEY (id_gestionnaire) REFERENCES Utilisateur(id_utilisateur) ON DELETE CASCADE,
        FOREIGN KEY (id_status) REFERENCES Status(id_status)
    )";

    $db->exec($sql);
    echo "Table Commande_Gestionnaire_Status créée avec succès.\n";

    // Insérer les statuts existants pour les commandes actuelles
    $sql_insert = "INSERT IGNORE INTO Commande_Gestionnaire_Status (id_commande, id_gestionnaire, id_status)
        SELECT DISTINCT lc.id_commande, p.id_gestionnaire, c.id_status
        FROM Ligne_commande lc
        JOIN Produit p ON lc.id_produit = p.id_produit
        JOIN Commande c ON lc.id_commande = c.id_commande
        WHERE p.id_gestionnaire IS NOT NULL";

    $db->exec($sql_insert);
    echo "Statuts existants migrés avec succès.\n";

} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>