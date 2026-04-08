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
            // Vérifier si la table Contact existe, sinon la créer
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

$toast_message = '';
if($success_message) {
    $toast_message = '<div class="toast">' . $success_message . '</div>';
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

        /* Categories */
        .categories {
            background: white;
            border-bottom: 1px solid #eaeaea;
            padding: 12px 0;
            overflow-x: auto;
        }

        .categories-list {
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }

        .categories-list a {
            padding: 6px 16px;
            text-decoration: none;
            color: #444;
            font-size: 14px;
            border-radius: 20px;
            background: #f0f0f0;
        }

        .categories-list a:hover {
            background: #2563eb;
            color: white;
        }

        /* Contact Section */
        .contact-section {
            margin: 30px 0;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        /* Contact Info Card */
        .contact-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }

        .contact-card-header {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            padding: 25px;
            color: white;
        }

        .contact-card-header h2 {
            font-size: 22px;
            margin-bottom: 8px;
        }

        .contact-card-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .contact-info-list {
            padding: 25px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .info-icon {
            width: 45px;
            height: 45px;
            background: #f0f0f0;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .info-text h4 {
            font-size: 14px;
            color: #666;
            margin-bottom: 4px;
        }

        .info-text p {
            font-size: 14px;
            color: #111;
            font-weight: 500;
        }

        /* Contact Form */
        .form-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }

        .form-header {
            padding: 25px;
            border-bottom: 1px solid #eaeaea;
        }

        .form-header h2 {
            font-size: 22px;
            color: #111;
            margin-bottom: 5px;
        }

        .form-header p {
            font-size: 13px;
            color: #888;
        }

        .contact-form {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: #1e40af;
        }

        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #dc2626;
        }

        /* Map Section */
        .map-section {
            margin-top: 30px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #eaeaea;
        }

        .map-section iframe {
            width: 100%;
            height: 250px;
            border: 0;
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
            
            .contact-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <?php echo $toast_message; ?>

    <div class="header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                
                <div class="user-links">
                    <?php if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true): ?>
                        <span style="color:#666;">👋 <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
                        <a href="mes_commandes.php">Commandes</a>
                        <a href="contact.php" style="color:#2563eb;">Contact</a>
                        <a href="logout_client.php" onclick="return confirm('Déconnexion ?')">Déconnexion</a>
                    <?php else: ?>
                        <a href="login_client.php">Connexion</a>
                        <a href="register.php">Inscription</a>
                        <a href="contact.php" style="color:#2563eb;">Contact</a>
                    <?php endif; ?>
                    <a href="panier.php" class="cart">
                        🛒 Panier
                        <span class="cart-count"><?php echo isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    

    <div class="container">
        <div class="contact-section">
            <div class="contact-grid">
                <!-- Informations de contact -->
                <div class="contact-card">
                    <div class="contact-card-header">
                        <h2>📞 Contactez-nous</h2>
                        <p>Notre équipe est à votre disposition</p>
                    </div>
                    <div class="contact-info-list">
                        <div class="info-item">
                            <div class="info-icon">📍</div>
                            <div class="info-text">
                                <h4>Adresse</h4>
                                <p>------------------</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">📞</div>
                            <div class="info-text">
                                <h4>Téléphone</h4>
                                <p>------------------</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">✉️</div>
                            <div class="info-text">
                                <h4>Email</h4>
                                <p>------------------</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">⏰</div>
                            <div class="info-text">
                                <h4>Horaires</h4>
                                <p>Lun - Ven : 9h - 18h</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulaire de contact -->
                <div class="form-card">
                    <div class="form-header">
                        <h2>Envoyez-nous un message</h2>
                        <p>Nous vous répondrons dans les 24h</p>
                    </div>
                    <div class="contact-form">
                        <?php if($error_message): ?>
                            <div class="alert-error">
                                ❌ <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="contactForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nom complet <span class="required">*</span></label>
                                    <input type="text" name="nom" value="<?php echo htmlspecialchars($nom ?? ''); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email <span class="required">*</span></label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($email ?? ($_SESSION['client_email'] ?? '')); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Téléphone <span class="required">*</span></label>
                                    <input type="tel" name="telephone" value="<?php echo htmlspecialchars($telephone ?? ($_SESSION['client_telephone'] ?? '')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Sujet <span class="required">*</span></label>
                                    <select name="sujet" required>
                                        <option value="">-- Sélectionnez --</option>
                                        <option value="Information produit" <?php echo (isset($sujet) && $sujet == 'Information produit') ? 'selected' : ''; ?>>Information produit</option>
                                        <option value="Commande" <?php echo (isset($sujet) && $sujet == 'Commande') ? 'selected' : ''; ?>>Question sur ma commande</option>
                                        <option value="Livraison" <?php echo (isset($sujet) && $sujet == 'Livraison') ? 'selected' : ''; ?>>Livraison</option>
                                        <option value="Retour" <?php echo (isset($sujet) && $sujet == 'Retour') ? 'selected' : ''; ?>>Retour / Réclamation</option>
                                        <option value="Autre" <?php echo (isset($sujet) && $sujet == 'Autre') ? 'selected' : ''; ?>>Autre</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Message <span class="required">*</span></label>
                                <textarea name="message" placeholder="Décrivez votre demande..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-submit" id="submitBtn">
                                📨 Envoyer le message
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Carte -->
            <div class="map-section">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d13294.123456789!2d-7.632!3d33.573!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xda7cdb8f4d8f8d8f%3A0x8d8f8d8f8d8f8d8f!2sCasablanca%2C%20Morocco!5e0!3m2!1sen!2s!4v1234567890" 
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique</p>
    </div>

    <script>
        function preventDoubleSubmit() {
            var submitBtn = document.getElementById('submitBtn');
            if (submitBtn.disabled) {
                return false;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Envoi en cours...';
            return true;
        }
        
        document.getElementById('contactForm')?.addEventListener('submit', preventDoubleSubmit);
    </script>
</body>
</html>