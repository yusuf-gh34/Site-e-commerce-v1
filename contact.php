<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les catégories pour le menu
$categories = $db->query("SELECT * FROM Categorie ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Traitement du formulaire de contact
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($nom)) {
        $errors[] = "Veuillez entrer votre nom.";
    }
    
    if (empty($email)) {
        $errors[] = "Veuillez entrer votre email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Veuillez entrer un email valide.";
    }
    
    if (empty($telephone)) {
        $errors[] = "Veuillez entrer votre numéro de téléphone.";
    } elseif (!preg_match("/^[0-9+\-\s]{10,20}$/", $telephone)) {
        $errors[] = "Veuillez entrer un numéro de téléphone valide.";
    }
    
    if (empty($sujet)) {
        $errors[] = "Veuillez sélectionner un sujet.";
    }
    
    if (empty($message)) {
        $errors[] = "Veuillez entrer votre message.";
    } elseif (strlen($message) < 10) {
        $errors[] = "Votre message doit contenir au moins 10 caractères.";
    }
    
    // Si pas d'erreurs, enregistrer en base de données
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO Contact (nom, email, telephone, sujet, message, date_envoi, statut) 
                    VALUES (:nom, :email, :telephone, :sujet, :message, NOW(), 'non lu')";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':nom' => $nom,
                ':email' => $email,
                ':telephone' => $telephone,
                ':sujet' => $sujet,
                ':message' => $message
            ]);
            
            $success_message = "✅ Votre message a bien été envoyé ! Notre équipe vous répondra dans les meilleurs délais.";
            
            // Réinitialiser le formulaire
            $nom = $email = $telephone = $sujet = $message = '';
            
        } catch (Exception $e) {
            $error_message = "❌ Une erreur est survenue. Veuillez réessayer plus tard.";
        }
    } else {
        $error_message = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Ma Boutique</title>
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

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
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

        .logo p {
            font-size: 12px;
            opacity: 0.8;
        }

        .user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .login-btn, .register-btn, .dashboard-btn, .logout-btn, .profile-btn, .orders-btn, .contact-btn {
            padding: 8px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .login-btn {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .login-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .register-btn {
            background: #ffc107;
            color: #333;
        }

        .register-btn:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .dashboard-btn {
            background: #28a745;
            color: white;
        }

        .dashboard-btn:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .logout-btn {
            background: #dc3545;
            color: white;
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .profile-btn {
            background: #17a2b8;
            color: white;
        }

        .profile-btn:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .orders-btn {
            background: #6c757d;
            color: white;
        }

        .orders-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .contact-btn {
            background: #20c997;
            color: white;
        }

        .contact-btn:hover {
            background: #1ba87e;
            transform: translateY(-2px);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.15);
            padding: 5px 15px;
            border-radius: 30px;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
            text-decoration: none;
            color: white;
            display: inline-block;
        }

        .cart-icon:hover {
            background: rgba(255,255,255,0.3);
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ffc107;
            color: #333;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        /* Navigation categories */
        .nav-categories {
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 80px;
            z-index: 999;
        }

        .categories-menu {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            overflow-x: auto;
            gap: 5px;
            padding: 10px 20px;
        }

        .categories-menu a {
            padding: 8px 20px;
            text-decoration: none;
            color: #333;
            border-radius: 20px;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .categories-menu a:hover,
        .categories-menu a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Contact Section */
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .contact-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
        }

        .contact-info h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }

        .contact-info p {
            line-height: 1.6;
            margin-bottom: 30px;
            opacity: 0.9;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .info-text h4 {
            margin-bottom: 5px;
        }

        .info-text p {
            margin: 0;
            font-size: 14px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
        }

        .social-links a:hover {
            background: rgba(255,255,255,0.4);
            transform: translateY(-3px);
        }

        .contact-form {
            padding: 40px;
        }

        .contact-form h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .contact-form .subtitle {
            color: #888;
            margin-bottom: 30px;
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

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        /* Map section */
        .map-section {
            margin-top: 40px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .map-section iframe {
            width: 100%;
            height: 300px;
            border: 0;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }

        .footer a {
            color: white;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .contact-container {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
            }
            
            .user-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <a href="index.php" style="text-decoration: none; color: white;">
                <div class="logo">
                    <h1>Ma Boutique</h1>
                    <p>Qualité et satisfaction garanties</p>
                </div>
            </a>
            <div class="user-actions">
                <?php if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true): ?>
                    <div class="user-info">
                        <span>👋 Bonjour, <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
                    </div>
                    <a href="profil.php" class="profile-btn">👤 Profil</a>
                    <a href="mes_commandes.php" class="orders-btn">📦 Mes commandes</a>
                    <a href="contact.php" class="contact-btn">📧 Contact</a>
                    <a href="logout_client.php" class="logout-btn" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">🔓 Déconnexion</a>
                <?php else: ?>
                    <a href="login_client.php" class="login-btn">🔐 Connexion</a>
                    <a href="register.php" class="register-btn">📝 Inscription</a>
                    <a href="contact.php" class="contact-btn">📧 Contact</a>
                <?php endif; ?>
                <a href="panier.php" class="cart-icon">
                    🛒 Panier
                    <span class="cart-count"><?php echo isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0; ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="nav-categories">
        <div class="categories-menu">
            <a href="index.php">Tous les produits</a>
            <?php foreach($categories as $cat): ?>
                <a href="index.php?categorie=<?php echo $cat['id_categorie']; ?>">
                    <?php echo htmlspecialchars($cat['nom']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main-content">
        <div class="contact-container">
            <div class="contact-info">
                <h2>📞 Contactez-nous</h2>
                <p>Notre équipe est à votre disposition pour répondre à toutes vos questions. N'hésitez pas à nous contacter par téléphone, email ou via ce formulaire.</p>
                
                <div class="info-item">
                    <div class="info-icon">📍</div>
                    <div class="info-text">
                        <h4>Adresse</h4>
                        <p>123 Avenue Mohammed V<br>Casablanca, Maroc</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-text">
                        <h4>Téléphone</h4>
                        <p>+212 5 22 12 34 56<br>Du lundi au vendredi, 9h-18h</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📱</div>
                    <div class="info-text">
                        <h4>WhatsApp</h4>
                        <p>+212 6 12 34 56 78<br>7j/7 de 10h à 20h</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">✉️</div>
                    <div class="info-text">
                        <h4>Email</h4>
                        <p>contact@maboutique.com<br>serviceclient@maboutique.com</p>
                    </div>
                </div>
                
                
            </div>
            
            <div class="contact-form">
                <h2>Envoyez-nous un message</h2>
                <p class="subtitle">Nous vous répondrons dans les 24h</p>
                
                <?php if($success_message): ?>
                    <div class="alert-success">
                        ✅ <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                    <div class="alert-error">
                        ❌ <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="contactForm" onsubmit="return preventDoubleSubmit()">
                    <div class="form-group">
                        <label>Nom complet <span class="required">*</span></label>
                        <input type="text" name="nom" value="<?php echo htmlspecialchars($nom ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ($_SESSION['client_email'] ?? '')); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Téléphone <span class="required">*</span></label>
                        <input type="tel" name="telephone" value="<?php echo htmlspecialchars($telephone ?? ($_SESSION['client_telephone'] ?? '')); ?>" placeholder="0612345678 ou +212612345678" required>
                        <small style="color: #888; font-size: 12px;">Format: 0612345678 ou +212612345678</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Sujet <span class="required">*</span></label>
                        <select name="sujet" required>
                            <option value="">-- Sélectionnez un sujet --</option>
                            <option value="Information produit" <?php echo (isset($sujet) && $sujet == 'Information produit') ? 'selected' : ''; ?>>Information produit</option>
                            <option value="Commande" <?php echo (isset($sujet) && $sujet == 'Commande') ? 'selected' : ''; ?>>Question sur ma commande</option>
                            <option value="Livraison" <?php echo (isset($sujet) && $sujet == 'Livraison') ? 'selected' : ''; ?>>Livraison</option>
                            <option value="Retour" <?php echo (isset($sujet) && $sujet == 'Retour') ? 'selected' : ''; ?>>Retour / Réclamation</option>
                            <option value="Partenaire" <?php echo (isset($sujet) && $sujet == 'Partenaire') ? 'selected' : ''; ?>>Devenir partenaire</option>
                            <option value="Autre" <?php echo (isset($sujet) && $sujet == 'Autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Message <span class="required">*</span></label>
                        <textarea name="message" placeholder="Décrivez votre demande en détails..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn-submit" id="submitBtn">
                        📨 Envoyer le message
                    </button>
                </form>
            </div>
        </div>
        
        >
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés | <a href="contact.php">Contactez-nous</a></p>
    </div>

    <script>
        function preventDoubleSubmit() {
            var submitBtn = document.getElementById('submitBtn');
            if (submitBtn.disabled) {
                return false; // Empêche la double soumission
            }
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Envoi en cours...';
            return true;
        }
    </script>
</body>
</html>