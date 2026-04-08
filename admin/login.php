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
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $user['id_utilisateur'];
                    $_SESSION['admin_nom'] = $user['nom'];
                    $_SESSION['admin_telephone'] = $user['telephone'];
                    $_SESSION['admin_role'] = $user['role_id'];
                    header("Location: dashboard.php");
                } elseif($user['role_id'] == 2) {
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
            $error = "Aucun compte trouvé avec ce numéro";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur</title>
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

        /* Login Box */
        .login-wrapper {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            border: 1px solid #eaeaea;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            font-size: 28px;
            color: #111;
            margin-bottom: 8px;
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
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-container input {
            width: 100%;
            padding-right: 75px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #2563eb;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background 0.2s;
        }

        .toggle-password:hover {
            background: #f0f0f0;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            border: none;
            color: white;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .btn-login:hover {
            background: #1e40af;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #fecaca;
        }

        .info-text {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            font-size: 12px;
            color: #888;
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
            
            .login-box {
                padding: 30px 20px;
            }
            
            .login-header h2 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="#">Ma<span>Boutique</span></a>
                </div>
                
                
            </div>
        </div>
    </div>

    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-header">
                <h2>🔐 Connexion</h2>
                <p>Espace administrateur & gestionnaire</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label>📱 Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" required 
                           placeholder="Entrez votre numéro" value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label>🔒 Mot de passe</label>
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
            
            <div class="info-text">
                Accès réservé aux administrateurs et gestionnaires
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
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