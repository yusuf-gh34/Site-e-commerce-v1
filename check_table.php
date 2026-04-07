<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce_db', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $result = $pdo->query('SHOW TABLES LIKE "Commande_Gestionnaire_Status"');
    if ($result->rowCount() > 0) {
        echo 'Table existe déjà.' . PHP_EOL;
    } else {
        echo 'Table n\'existe pas.' . PHP_EOL;
    }
} catch(PDOException $e) {
    echo 'Erreur: ' . $e->getMessage() . PHP_EOL;
}
?>