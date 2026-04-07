<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $telephone = trim($_POST['telephone']);
    $nom = trim($_POST['nom']);
    $adresse = trim($_POST['adresse']);
    $plain_password = trim($_POST['password']);
    
    if(empty($telephone) || empty($nom) || empty($plain_password)) {
        $message = "<div style='color: red; padding: 10px; background: #ffe6e6; border-radius: 5px; margin-bottom: 20px;'>❌ Tous les champs sauf adresse sont obligatoires !</div>";
    } else {
        $hashed_password = password_hash($plain_password, PASSWORD_DEFAULT);
        $role_id = 1; // Administrateur
        
        // Vérifier si existe
        $check_query = "SELECT id_utilisateur FROM Utilisateur WHERE telephone = :telephone";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            // Mettre à jour
            $update_query = "UPDATE Utilisateur SET nom = :nom, adresse = :adresse, password = :password, role_id = :role_id WHERE telephone = :telephone";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':nom', $nom);
            $update_stmt->bindParam(':adresse', $adresse);
            $update_stmt->bindParam(':password', $hashed_password);
            $update_stmt->bindParam(':role_id', $role_id);
            $update_stmt->bindParam(':telephone', $telephone);
            
            if($update_stmt->execute()) {
                $message = "<div style='color: green; padding: 10px; background: #e6ffe6; border-radius: 5px; margin-bottom: 20px;'>✅ Compte administrateur mis à jour !<br>Téléphone: $telephone<br>Mot de passe: $plain_password</div>";
            } else {
                $message = "<div style='color: red; padding: 10px; background: #ffe6e6; border-radius: 5px; margin-bottom: 20px;'>❌ Erreur lors de la mise à jour</div>";
            }
        } else {
            // Créer nouveau
            $insert_query = "INSERT INTO Utilisateur (telephone, nom, adresse, password, role_id, date_creation) 
                             VALUES (:telephone, :nom, :adresse, :password, :role_id, NOW())";
            
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':telephone', $telephone);
            $insert_stmt->bindParam(':nom', $nom);
            $insert_stmt->bindParam(':adresse', $adresse);
            $insert_stmt->bindParam(':password', $hashed_password);
            $insert_stmt->bindParam(':role_id', $role_id);
            
            if($insert_stmt->execute()) {
                $message = "<div style='color: green; padding: 10px; background: #e6ffe6; border-radius: 5px; margin-bottom: 20px;'>✅ Compte administrateur créé !<br>Téléphone: $telephone<br>Mot de passe: $plain_password</div>";
            } else {
                $message = "<div style='color: red; padding: 10px; background: #ffe6e6; border-radius: 5px; margin-bottom: 20px;'>❌ Erreur lors de la création</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer Administrateur</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #5a67d8;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>👑 Créer un compte Administrateur</h2>
        
        <?php echo $message; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Téléphone :</label>
                <input type="text" name="telephone" required>
            </div>
            
            <div class="form-group">
                <label>Nom complet :</label>
                <input type="text" name="nom" required>
            </div>
            
            <div class="form-group">
                <label>Adresse :</label>
                <input type="text" name="adresse">
            </div>
            
            <div class="form-group">
                <label>Mot de passe :</label>
                <input type="text" name="password" required>
            </div>
            
            <button type="submit">Créer / Mettre à jour</button>
        </form>
        

    </div>
</body>
</html>