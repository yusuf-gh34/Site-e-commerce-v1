<?php
session_start();
require_once 'config/database.php';

// Vérifier si le client est connecté
if(!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login_client.php?error=connectez_vous_pour_commander");
    exit();
}

// Vérifier si le panier n'est pas vide
if(!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    header("Location: panier.php?error=panier_vide");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = false;
$commande_id = null;
$prix_total = 0;
$adresse_livraison_saved = '';

// Traitement du formulaire de commande
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if(empty($adresse_livraison)) {
        $error = "Veuillez saisir votre adresse de livraison";
    } else {
        try {
            $db->beginTransaction();
            
            // Calculer le prix total
            $prix_total = 0;
            foreach($_SESSION['panier'] as $id_produit => $quantite) {
                if(isset($_SESSION['panier_produits'][$id_produit])) {
                    $prix_total += $_SESSION['panier_produits'][$id_produit]['prix'] * $quantite;
                }
            }
            
            // 1. Créer la commande (id_status = 1 = "En attente") - AJOUT de adresse_livraison
            $query = "INSERT INTO Commande (id_status, prix_totale, id_utilisateur, adresse_livraison) 
                      VALUES (1, :prix_total, :id_utilisateur, :adresse_livraison)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':prix_total', $prix_total);
            $stmt->bindParam(':id_utilisateur', $_SESSION['client_id']);
            $stmt->bindParam(':adresse_livraison', $adresse_livraison);
            $stmt->execute();
            
            $commande_id = $db->lastInsertId();
            
            // Sauvegarder l'adresse pour l'affichage
            $adresse_livraison_saved = $adresse_livraison;
            
            // 2. Créer les lignes de commande et mettre à jour le stock
            $query = "INSERT INTO Ligne_commande (id_commande, id_produit, quantite, prix_unitaire) 
                      VALUES (:id_commande, :id_produit, :quantite, :prix_unitaire)";
            $stmt = $db->prepare($query);
            
            foreach($_SESSION['panier'] as $id_produit => $quantite) {
                if(isset($_SESSION['panier_produits'][$id_produit])) {
                    $prix_unitaire = $_SESSION['panier_produits'][$id_produit]['prix'];
                    
                    $stmt->bindParam(':id_commande', $commande_id);
                    $stmt->bindParam(':id_produit', $id_produit);
                    $stmt->bindParam(':quantite', $quantite);
                    $stmt->bindParam(':prix_unitaire', $prix_unitaire);
                    $stmt->execute();
                    
                    // Mettre à jour le stock
                    $update_stock = "UPDATE Produit SET stock = stock - :quantite WHERE id_produit = :id_produit";
                    $update_stmt = $db->prepare($update_stock);
                    $update_stmt->bindParam(':quantite', $quantite);
                    $update_stmt->bindParam(':id_produit', $id_produit);
                    $update_stmt->execute();
                }
            }
            
            // 3. Créer le règlement (paiement à la livraison)
            $query = "INSERT INTO Reglement (montant, paye_a_livraison, id_commande) 
                      VALUES (:prix_total, 0, :id_commande)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':prix_total', $prix_total);
            $stmt->bindParam(':id_commande', $commande_id);
            $stmt->execute();
            
            // 4. Vider le panier
            $_SESSION['panier'] = [];
            $_SESSION['panier_produits'] = [];
            
            $db->commit();
            $success = true;
            
        } catch(Exception $e) {
            $db->rollBack();
            $error = "Une erreur est survenue lors de la création de votre commande : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passer commande - Boutique</title>
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

        .logo p {
            font-size: 12px;
            opacity: 0.8;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255,255,255,0.15);
            padding: 8px 20px;
            border-radius: 30px;
        }

        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Success message */
        .success-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .success-container h2 {
            color: #28a745;
            margin-bottom: 15px;
        }

        .order-number {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .delivery-address {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
            border-left: 4px solid #28a745;
        }

        .delivery-address h4 {
            color: #333;
            margin-bottom: 10px;
        }

        .btn-orders {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }

        /* Order form */
        .order-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .order-container {
                grid-template-columns: 1fr;
            }
        }

        .order-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }

        .section-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-body {
            padding: 25px;
        }

        /* Form styles */
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        /* Payment info */
        .payment-info {
            background: #e8f5e9;
            border: 2px solid #4caf50;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .payment-info .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .payment-info h4 {
            color: #2e7d32;
            margin-bottom: 10px;
        }

        .payment-info p {
            color: #555;
            font-size: 14px;
        }

        /* Cart summary */
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .cart-item-price {
            font-size: 12px;
            color: #888;
        }

        .cart-item-quantity {
            color: #666;
            margin: 0 15px;
        }

        .cart-item-total {
            font-weight: bold;
            color: #667eea;
        }

        .order-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 20px;
            font-weight: bold;
        }

        .order-total span:last-child {
            color: #667eea;
            font-size: 24px;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Ma Boutique</h1>
                <p>Qualité et satisfaction garanties</p>
            </div>
            <a href="panier.php" class="back-btn">← Retour au panier</a>
            <div class="user-info">
                <span>👋 <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if($success): ?>
            <div class="success-container">
                <div class="success-icon">✅</div>
                <h2>Commande confirmée !</h2>
                <p>Merci pour votre commande. Vous allez recevoir un email de confirmation.</p>
                <div class="order-number">
                    N° de commande : #<?php echo str_pad($commande_id, 8, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="delivery-address">
                    <h4>📍 Adresse de livraison</h4>
                    <p><?php echo nl2br(htmlspecialchars($adresse_livraison_saved)); ?></p>
                </div>
                <p>Vous payez à la livraison : <strong><?php echo number_format($prix_total, 0, ',', ' '); ?> DH</strong></p>
                <a href="mes_commandes.php" class="btn-orders">📦 Voir mes commandes</a>
                <a href="index.php" style="display: inline-block; margin-top: 20px; margin-left: 15px; color: #667eea;">Continuer mes achats →</a>
            </div>
        <?php else: ?>
            <?php if($error): ?>
                <div class="error-message">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="order-container">
                    <!-- Formulaire de livraison -->
                    <div class="order-section">
                        <div class="section-header">
                            <h3>📍 Adresse de livraison</h3>
                        </div>
                        <div class="section-body">
                            <div class="form-group">
                                <label>Nom complet</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['client_nom']); ?>" disabled style="background: #f8f9fa;">
                            </div>
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['client_telephone']); ?>" disabled style="background: #f8f9fa;">
                            </div>
                            <div class="form-group">
                                <label>Adresse de livraison <span class="required">*</span></label>
                                <textarea name="adresse_livraison" required placeholder="Votre adresse complète... (numéro, rue, code postal, ville)"></textarea>
                                <small style="color: #666; font-size: 12px;">Exemple: 15 Rue Mohammed V, 20000 Casablanca</small>
                            </div>
                            <div class="form-group">
                                <label>Notes (optionnel)</label>
                                <textarea name="notes" placeholder="Instructions de livraison, code d'accès, étage, etc."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Paiement et récapitulatif -->
                    <div class="order-section">
                        <div class="section-header">
                            <h3>💳 Mode de paiement</h3>
                        </div>
                        <div class="section-body">
                            <div class="payment-info">
                                <div class="icon">🚚</div>
                                <h4>Paiement à la livraison</h4>
                                <p>Vous payez directement lors de la réception de votre commande</p>
                                <p style="margin-top: 10px; font-weight: bold;">Espèces ou carte bancaire acceptées</p>
                            </div>
                        </div>

                        <div class="section-header" style="margin-top: 20px;">
                            <h3>🛒 Récapitulatif de la commande</h3>
                        </div>
                        <div class="section-body">
                            <?php 
                            $total = 0;
                            foreach($_SESSION['panier'] as $id => $quantite):
                                if(isset($_SESSION['panier_produits'][$id])):
                                    $produit = $_SESSION['panier_produits'][$id];
                                    $sous_total = $produit['prix'] * $quantite;
                                    $total += $sous_total;
                            ?>
                                <div class="cart-item">
                                    <div class="cart-item-info">
                                        <div class="cart-item-name"><?php echo htmlspecialchars($produit['nom']); ?></div>
                                        <div class="cart-item-price"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</div>
                                    </div>
                                    <div class="cart-item-quantity">x<?php echo $quantite; ?></div>
                                    <div class="cart-item-total"><?php echo number_format($sous_total, 0, ',', ' '); ?> DH</div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <div class="order-total">
                                <span>Total à payer à la livraison</span>
                                <span><?php echo number_format($total, 0, ',', ' '); ?> DH</span>
                            </div>
                            
                            <button type="submit" class="btn-submit">✅ Confirmer ma commande</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
        <p style="font-size: 12px; margin-top: 10px;">Paiement sécurisé à la livraison</p>
    </div>
</body>
</html>