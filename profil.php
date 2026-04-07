<?php
session_start();
require_once 'config/database.php';

// Vérifier si le client est connecté
if(!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login_client.php?error=connectez_vous");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// Récupérer les informations du client
$query = "SELECT id_utilisateur, nom, telephone, email, adresse, date_inscription 
          FROM Utilisateur 
          WHERE id_utilisateur = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['client_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement de la modification
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = trim($_POST['nom']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $adresse = trim($_POST['adresse']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if(empty($nom) || empty($telephone)) {
        $error = "Le nom et le téléphone sont obligatoires";
    } else {
        try {
            // Vérifier si le téléphone existe déjà pour un autre utilisateur
            $check = "SELECT id_utilisateur FROM Utilisateur WHERE telephone = :telephone AND id_utilisateur != :id";
            $check_stmt = $db->prepare($check);
            $check_stmt->bindParam(':telephone', $telephone);
            $check_stmt->bindParam(':id', $_SESSION['client_id']);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error = "Ce numéro de téléphone est déjà utilisé";
            } else {
                // Mise à jour des informations
                if(!empty($new_password)) {
                    if($new_password !== $confirm_password) {
                        $error = "Les mots de passe ne correspondent pas";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $query = "UPDATE Utilisateur SET nom = :nom, telephone = :telephone, email = :email, adresse = :adresse, password = :password WHERE id_utilisateur = :id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(':password', $hashed_password);
                    }
                } else {
                    $query = "UPDATE Utilisateur SET nom = :nom, telephone = :telephone, email = :email, adresse = :adresse WHERE id_utilisateur = :id";
                    $stmt = $db->prepare($query);
                }
                
                if(!isset($error) || empty($error)) {
                    $stmt->bindParam(':nom', $nom);
                    $stmt->bindParam(':telephone', $telephone);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':adresse', $adresse);
                    $stmt->bindParam(':id', $_SESSION['client_id']);
                    $stmt->execute();
                    
                    // Mettre à jour la session
                    $_SESSION['client_nom'] = $nom;
                    $_SESSION['client_telephone'] = $telephone;
                    
                    $success = "Vos informations ont été mises à jour avec succès";
                    
                    // Recharger les données
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $_SESSION['client_id']);
                    $stmt->execute();
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch(Exception $e) {
            $error = "Une erreur est survenue";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Boutique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logo h1 {
            font-size: 24px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .main-content {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .profile-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .profile-header h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .profile-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-save {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }

        .btn-save:hover {
            transform: translateY(-2px);
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .info-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .divider {
            border-top: 1px solid #e9ecef;
            margin: 25px 0;
        }

        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Ma Boutique</h1>
                <p>Mon profil</p>
            </div>
            <a href="index.php" class="back-btn">← Retour à la boutique</a>
        </div>
    </div>

    <div class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <h2>👤 Mon profil</h2>
                <p>Gérez vos informations personnelles</p>
            </div>
            <div class="profile-body">
                <?php if($success): ?>
                    <div class="alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if($error): ?>
                    <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom complet *</label>
                            <input type="text" name="nom" value="<?php echo htmlspecialchars($client['nom']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone *</label>
                            <input type="tel" name="telephone" value="<?php echo htmlspecialchars($client['telephone']); ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Adresse de livraison</label>
                        <textarea name="adresse" placeholder="Votre adresse complète"><?php echo htmlspecialchars($client['adresse'] ?? ''); ?></textarea>
                    </div>

                    <div class="divider"></div>

                    <h3 style="margin-bottom: 20px;">🔒 Changer de mot de passe</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nouveau mot de passe</label>
                            <input type="password" name="new_password" placeholder="Laissez vide pour ne pas changer">
                        </div>
                        <div class="form-group">
                            <label>Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" placeholder="Confirmez le nouveau mot de passe">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">💾 Enregistrer les modifications</button>
                </form>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
    </div>
</body>
</html>