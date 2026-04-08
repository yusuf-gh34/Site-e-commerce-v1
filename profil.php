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
          FROM utilisateur 
          WHERE id_utilisateur = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['client_id']);
$stmt->execute();
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Traitement de la modification
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom = trim($_POST['nom']);
    $telephone = trim($_POST['telephone']);
    $email = !empty(trim($_POST['email'])) ? trim($_POST['email']) : null;
    $adresse = trim($_POST['adresse']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if(empty($nom) || empty($telephone)) {
        $error = "Le nom et le téléphone sont obligatoires";
    } else {
        try {
            // Vérifier si le téléphone existe déjà pour un autre utilisateur
            $check = "SELECT id_utilisateur FROM utilisateur WHERE telephone = :telephone AND id_utilisateur != :id";
            $check_stmt = $db->prepare($check);
            $check_stmt->bindParam(':telephone', $telephone);
            $check_stmt->bindParam(':id', $_SESSION['client_id']);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0) {
                $error = "Ce numéro de téléphone est déjà utilisé";
            } else {
                // Vérifier si l'email existe déjà pour un autre utilisateur (si email fourni)
                if(!empty($email)) {
                    $check_email = "SELECT id_utilisateur FROM utilisateur WHERE email = :email AND id_utilisateur != :id";
                    $check_email_stmt = $db->prepare($check_email);
                    $check_email_stmt->bindParam(':email', $email);
                    $check_email_stmt->bindParam(':id', $_SESSION['client_id']);
                    $check_email_stmt->execute();
                    
                    if($check_email_stmt->rowCount() > 0) {
                        $error = "Cet email est déjà utilisé";
                    }
                }
                
                if(empty($error)) {
                    // Mise à jour des informations
                    if(!empty($new_password)) {
                        if($new_password !== $confirm_password) {
                            $error = "Les mots de passe ne correspondent pas";
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $query = "UPDATE utilisateur SET nom = :nom, telephone = :telephone, email = :email, adresse = :adresse, password = :password WHERE id_utilisateur = :id";
                            $stmt = $db->prepare($query);
                            $stmt->bindParam(':password', $hashed_password);
                        }
                    } else {
                        $query = "UPDATE utilisateur SET nom = :nom, telephone = :telephone, email = :email, adresse = :adresse WHERE id_utilisateur = :id";
                        $stmt = $db->prepare($query);
                    }
                    
                    if(empty($error)) {
                        $stmt->bindParam(':nom', $nom);
                        $stmt->bindParam(':telephone', $telephone);
                        $stmt->bindParam(':email', $email);
                        $stmt->bindParam(':adresse', $adresse);
                        $stmt->bindParam(':id', $_SESSION['client_id']);
                        
                        if($stmt->execute()) {
                            // Mettre à jour la session
                            $_SESSION['client_nom'] = $nom;
                            $_SESSION['client_telephone'] = $telephone;
                            $_SESSION['client_email'] = $email;
                            
                            $success = "Vos informations ont été mises à jour avec succès";
                            
                            // Recharger les données
                            $stmt_refresh = $db->prepare("SELECT id_utilisateur, nom, telephone, email, adresse, date_inscription FROM utilisateur WHERE id_utilisateur = :id");
                            $stmt_refresh->bindParam(':id', $_SESSION['client_id']);
                            $stmt_refresh->execute();
                            $client = $stmt_refresh->fetch(PDO::FETCH_ASSOC);
                        } else {
                            $error = "Une erreur est survenue lors de la mise à jour";
                        }
                    }
                }
            }
        } catch(Exception $e) {
            $error = "Une erreur est survenue: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil - Ma Boutique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #fafafa;
            color: #111;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 1000;
            animation: fadeOut 3s forwards;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #eaeaea;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .logo a {
            font-size: 24px;
            font-weight: 700;
            text-decoration: none;
            color: #111;
        }

        .logo span {
            color: #2563eb;
        }

        .user-links {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-links a {
            text-decoration: none;
            color: #444;
            font-size: 14px;
        }

        .user-links a:hover {
            color: #2563eb;
        }

        .cart {
            position: relative;
            font-weight: 500;
        }

        .cart-count {
            background: #2563eb;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
        }

        /* Profile Section */
        .profile-section {
            background: white;
            border-radius: 12px;
            margin: 30px 0;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }

        .profile-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .profile-header h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .profile-header p {
            opacity: 0.9;
            font-size: 14px;
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
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
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
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }

        .btn-save:hover {
            background: #1e40af;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #10b981;
            font-size: 14px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
            font-size: 14px;
        }

        .divider {
            border-top: 1px solid #eaeaea;
            margin: 25px 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            display: inline-block;
        }

        .info-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #eaeaea;
            margin-top: 40px;
        }

        @media (max-width: 768px) {
            .header-inner {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <?php if($success): ?>
        <div class="toast">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                
                <div class="user-links">
                    <span style="color:#666;">👋 <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
                    <a href="profil.php" style="color:#2563eb;">Profil</a>
                    <a href="mes_commandes.php">Commandes</a>
                    <a href="logout_client.php" onclick="return confirm('Déconnexion ?')">Déconnexion</a>
                    <a href="panier.php" class="cart">
                        🛒 Panier
                        <span class="cart-count"><?php echo isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="profile-section">
            <div class="profile-header">
                <h2>👤 Mon profil</h2>
                <p>Gérez vos informations personnelles</p>
            </div>
            <div class="profile-body">
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
                        <div class="info-text">Optionnel - utilisé pour les communications</div>
                    </div>

                    <div class="form-group">
                        <label>Adresse de livraison</label>
                        <textarea name="adresse" placeholder="Votre adresse complète"><?php echo htmlspecialchars($client['adresse'] ?? ''); ?></textarea>
                    </div>

                    <div class="divider"></div>

                    <h3 class="section-title">🔒 Changer de mot de passe</h3>
                    
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
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique</p>
    </div>
</body>
</html>