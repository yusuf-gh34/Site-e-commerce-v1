<?php
session_start();
require_once '../config/database.php';

// Si déjà connecté, rediriger vers le dashboard approprié
if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if($_SESSION['user_role'] == 1) {
        header("Location: dashboard.php");
    } elseif($_SESSION['user_role'] == 2) {
        header("Location: gestionnaire_dashboard.php");
    }
    exit();
}

$error = '';

if($_SERVER["REQUEST_METHOD"] == "POST") {
    $telephone = trim($_POST['telephone']);
    $password = trim($_POST['password']);
    
    if(empty($telephone) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Requête pour vérifier l'utilisateur (admin role_id=1 ou gestionnaire role_id=2)
        $query = "SELECT id_utilisateur, telephone, nom, password, role_id 
                  FROM Utilisateur 
                  WHERE telephone = :telephone AND role_id IN (1, 2)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':telephone', $telephone);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier le mot de passe
            if(password_verify($password, $user['password'])) {
                // Connexion réussie
                $_SESSION['user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_telephone'] = $user['telephone'];
                $_SESSION['user_role'] = $user['role_id'];
                
                // Redirection selon le rôle
                if($user['role_id'] == 1) {
                    // Administrateur
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id_utilisateur'];
                    $_SESSION['admin_nom'] = $user['nom'];
                    $_SESSION['admin_telephone'] = $user['telephone'];
                    $_SESSION['admin_role'] = $user['role_id'];
                    header("Location: dashboard.php");
                } elseif($user['role_id'] == 2) {
                    // Gestionnaire
                    $_SESSION['manager_logged_in'] = true;
                    $_SESSION['manager_id'] = $user['id_utilisateur'];
                    $_SESSION['manager_nom'] = $user['nom'];
                    $_SESSION['manager_telephone'] = $user['telephone'];
                    header("Location: gestionnaire_dashboard.php");
                }
                exit();
            } else {
                $error = "Mot de passe incorrect";
            }
        } else {
            $error = "Aucun compte administrateur ou gestionnaire trouvé avec ce numéro";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Système de Gestion</title>
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
        
        .login-header p {
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
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        /* Conteneur pour le champ mot de passe avec bouton */
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-container input {
            width: 100%;
            padding-right: 45px;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            cursor: pointer;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: normal;
            color: #888;
            padding: 0;
            transition: color 0.3s;
        }
        
        .toggle-password:hover {
            color: #667eea;
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
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
        }
        
        .info-badge {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #999;
        }
        
        .role-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }
        
        .role-badge {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 20px;
        }
        
        .role-badge.admin {
            background: #e8eaf6;
            color: #3949ab;
        }
        
        .role-badge.manager {
            background: #e0f2f1;
            color: #00796b;
        }
        
        .toggle-password:active {
            transform: scale(0.95);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Espace de connexion</h2>
            <p>Administrateurs & Gestionnaires</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-group">
                <label>Telephone</label>
                <input type="tel" id="telephone" name="telephone" required 
                       placeholder="Entrez votre numero de telephone">
            </div>
            
            <div class="form-group">
                <label>Mot de passe</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required 
                           placeholder="Entrez votre mot de passe">
                    <button type="button" class="toggle-password" onclick="togglePassword()">
                        Afficher
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
        
        <div class="info-badge">
            <span>Acces reserve</span>
        </div>
        <div class="role-badges">
            <span class="role-badge admin">Administrateur</span>
            <span class="role-badge manager">Gestionnaire</span>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = 'Masquer';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = 'Afficher';
            }
        }
    </script>
</body>
</html>