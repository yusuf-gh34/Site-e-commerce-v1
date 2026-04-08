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
    $role_id = 3;
    
    // Validation
    if(empty($telephone) || empty($nom) || empty($password)) {
        $error = "Veuillez remplir tous les champs obligatoires";
    } elseif(strlen($password) < 6) {
        $error = "Le mot de passe doit contenir au moins 6 caracteres";
    } elseif($password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Vérifier téléphone
        $check_query = "SELECT COUNT(*) FROM Utilisateur WHERE telephone = :telephone";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([':telephone' => $telephone]);
        if($check_stmt->fetchColumn() > 0) {
            $error = "Ce numero est deja utilise";
        } else {
            if(!empty($email)) {
                $check_email = "SELECT COUNT(*) FROM Utilisateur WHERE email = :email";
                $check_stmt_email = $db->prepare($check_email);
                $check_stmt_email->execute([':email' => $email]);
                if($check_stmt_email->fetchColumn() > 0) {
                    $error = "Cet email est deja utilise";
                }
            }
            
            if(empty($error)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
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
                    $success = "Inscription reussie !";
                    header("refresh:2;url=login_client.php");
                } else {
                    $error = "Une erreur est survenue";
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
    <title>Inscription - Ma Boutique</title>
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

        /* Register Box */
        .register-wrapper {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .register-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border: 1px solid #eaeaea;
        }

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h2 {
            font-size: 28px;
            color: #111;
            margin-bottom: 8px;
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
            font-size: 14px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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

        .btn-register {
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

        .btn-register:hover {
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

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border: 1px solid #a7f3d0;
        }

        .info-text {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eaeaea;
            font-size: 12px;
            color: #888;
        }

        .info-note {
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
            
            .register-box {
                padding: 30px 20px;
            }
            
            .register-header h2 {
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
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                
                <div class="user-links">
                    <a href="index.php">Accueil</a>
                    <a href="login_client.php">Connexion</a>
                </div>
            </div>
        </div>
    </div>

    <div class="register-wrapper">
        <div class="register-box">
            <div class="register-header">
                <h2>Inscription</h2>
                <p>Creez votre compte client</p>
            </div>
            
            <?php if($error): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($success); ?>
                    <br><small>Redirection...</small>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Nom complet <span class="required">*</span></label>
                    <input type="text" name="nom" required 
                           value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>"
                           placeholder="Votre nom">
                </div>
                
                <div class="form-group">
                    <label>Telephone <span class="required">*</span></label>
                    <input type="tel" name="telephone" required 
                           value="<?php echo isset($_POST['telephone']) ? htmlspecialchars($_POST['telephone']) : ''; ?>"
                           placeholder="06 XX XX XX XX">
                </div>
                
                <div class="form-group">
                    <label>Email <span style="color:#888;">(optionnel)</span></label>
                    <input type="email" name="email" 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           placeholder="exemple@email.com">
                    <div class="info-note">Recommande pour recevoir vos factures</div>
                </div>
                
                <div class="form-group">
                    <label>Adresse</label>
                    <textarea name="adresse" placeholder="Votre adresse complete"><?php echo isset($_POST['adresse']) ? htmlspecialchars($_POST['adresse']) : ''; ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Mot de passe <span class="required">*</span></label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required placeholder="Minimum 6 caracteres">
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            Afficher
                        </button>
                    </div>
                    <div class="info-note">6 caracteres minimum</div>
                </div>
                
                <div class="form-group">
                    <label>Confirmer <span class="required">*</span></label>
                    <div class="password-container">
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Retapez le mot de passe">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                            Afficher
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-register">S'inscrire</button>
            </form>
            
            <div class="info-text">
                Deja inscrit ? <a href="login_client.php" style="color: #2563eb; text-decoration: none;">Se connecter</a>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits reserves</p>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId, btn) {
            const passwordInput = document.getElementById(inputId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                btn.textContent = 'Masquer';
            } else {
                passwordInput.type = 'password';
                btn.textContent = 'Afficher';
            }
        }
    </script>
</body>
</html>