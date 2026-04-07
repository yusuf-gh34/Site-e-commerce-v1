<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $telephone = trim($_POST['telephone']);
    $nom = trim($_POST['nom']);
    $adresse = trim($_POST['adresse']);
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role_id = 3; // ID pour le rôle client
    
    // Validation des champs
    if(empty($telephone) || empty($nom) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caractères";
    } elseif($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier si le téléphone existe déjà
        $check_query = "SELECT COUNT(*) FROM Utilisateur WHERE telephone = :telephone";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':telephone' => $telephone]);
        if($check_stmt->fetchColumn() > 0) {
            $error = "Ce numéro de téléphone est déjà utilisé";
        } else {
            // Vérifier si l'email existe déjà (si fourni)
            if(!empty($email)) {
                $check_email = "SELECT COUNT(*) FROM Utilisateur WHERE email = :email";
                $check_stmt_email = $db->prepare($check_email);
                $check_stmt_email->execute([':email' => $email]);
                if($check_stmt_email->fetchColumn() > 0) {
                    $error = "Cet email est déjà utilisé";
                }
            }
            
            if(empty($error)) {
                // Hachage du mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insertion du nouveau client
                $query = "INSERT INTO Utilisateur (telephone, nom, adresse, email, password, role_id) 
                          VALUES (:telephone, :nom, :adresse, :email, :password, :role_id)";
                $stmt = $db->prepare($query);
                
                if($stmt->execute([
                    ':telephone' => $telephone,
                    ':nom' => $nom,
                    ':adresse' => $adresse,
                    ':email' => $email,
                    ':password' => $hashed_password,
                    ':role_id' => $role_id
                ])) {
                    $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
                    // Redirection après 2 secondes
                    header("refresh:2;url=login_client.php");
                } else {
                    $error = "Une erreur est survenue. Veuillez réessayer.";
                }
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
    <title>Inscription - Boutique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 500px;
            max-width: 100%;
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h2 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666;
            font-size: 14px;
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
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-size: 16px;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 15px;
        }
        
        .back-link a {
            color: #888;
            text-decoration: none;
            font-size: 13px;
        }
        
        .back-link a:hover {
            color: #667eea;
        }
        
        .info-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2>📝 Créer un compte</h2>
            <p>Inscrivez-vous pour passer vos commandes</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="success-message">
                ✅ <?php echo htmlspecialchars($success); ?>
                <br>
                <small>Redirection vers la page de connexion...</small>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Nom complet <span class="required">*</span></label>
                <input type="text" name="nom" required 
                       value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"
                       placeholder="Entrez votre nom complet">
            </div>
            
            <div class="form-group">
                <label>Téléphone <span class="required">*</span></label>
                <input type="tel" name="telephone" required 
                       value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                       placeholder="Ex: 0612345678">
            </div>
            
            <div class="form-group">
                <label>Email <span style="color: #888; font-size: 12px;">(optionnel)</span></label>
                <input type="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       placeholder="exemple@email.com">
                <div class="info-text">L'email est optionnel mais recommandé pour recevoir vos factures</div>
            </div>
            
            <div class="form-group">
                <label>Adresse</label>
                <textarea name="adresse" placeholder="Votre adresse complète"><?php echo isset($_POST['adresse']) ? htmlspecialchars($_POST['adresse']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Mot de passe <span class="required">*</span></label>
                <input type="password" name="password" required 
                       placeholder="Minimum 6 caractères">
                <div class="password-requirements">🔒 Le mot de passe doit contenir au moins 6 caractères</div>
            </div>
            
            <div class="form-group">
                <label>Confirmer le mot de passe <span class="required">*</span></label>
                <input type="password" name="confirm_password" required 
                       placeholder="Retapez votre mot de passe">
            </div>
            
            <button type="submit" class="btn-register">S'inscrire</button>
        </form>
        
        <hr>
        
        <div class="login-link">
            Vous avez déjà un compte ? <a href="login_client.php">Connectez-vous</a>
        </div>
        <div class="back-link">
            <a href="index.php">← Retour à la boutique</a>
        </div>
    </div>
</body>
</html>