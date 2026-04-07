<?php
session_start();
require_once 'config/database.php';

// Si déjà connecté en tant que client, rediriger
if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

// Sauvegarder le panier invité AVANT toute modification de session
$panier_invite = isset($_SESSION['panier']) ? $_SESSION['panier'] : [];
$panier_produits_invite = isset($_SESSION['panier_produits']) ? $_SESSION['panier_produits'] : [];

$error = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $telephone = trim($_POST['telephone']);
    $password = trim($_POST['password']);
    
    if(empty($telephone) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id_utilisateur, telephone, nom, password, role_id 
                  FROM Utilisateur 
                  WHERE telephone = :telephone AND role_id = 3";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(password_verify($password, $user['password'])) {
                // Session pour le client
                $_SESSION['client_logged_in'] = true;
                $_SESSION['client_id'] = $user['id_utilisateur'];
                $_SESSION['client_nom'] = $user['nom'];
                $_SESSION['client_telephone'] = $user['telephone'];
                $_SESSION['client_role'] = $user['role_id'];
                
                // === CORRECTION IMPORTANTE ===
                // Restaurer le panier sauvegardé au lieu de le vider
                if(!empty($panier_invite)) {
                    $_SESSION['panier'] = $panier_invite;
                    $_SESSION['panier_produits'] = $panier_produits_invite;
                    $_SESSION['cart_message'] = "Votre panier a été sauvegardé !";
                } else {
                    // Si pas de panier invité, créer un panier vide
                    $_SESSION['panier'] = [];
                    $_SESSION['panier_produits'] = [];
                }
                
                // Rediriger vers la page précédente ou le panier si demandé
                $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php';
                header("Location: $redirect");
                exit();
            } else {
                $error = "Mot de passe incorrect";
            }
        } else {
            $error = "Aucun compte client trouvé avec ce numéro";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Client - Boutique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 400px;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            transition: transform 0.2s;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #888;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Connexion Client</h2>
            <p>Connectez-vous à votre compte</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="telephone" required placeholder="Entrez votre numéro">
            </div>
            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required placeholder="Entrez votre mot de passe">
            </div>
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="register-link">
            <p>Pas encore de compte ? <a href="register.php">Inscrivez-vous</a></p>
        </div>
        <div class="back-link">
            <a href="index.php">← Retour à la boutique</a>
        </div>
    </div>
</body>
</html>