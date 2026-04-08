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

// Récupérer l'adresse de l'utilisateur depuis la base de données
$query_user = "SELECT adresse FROM utilisateur WHERE id_utilisateur = :id_utilisateur";
$stmt_user = $db->prepare($query_user);
$stmt_user->bindParam(':id_utilisateur', $_SESSION['client_id']);
$stmt_user->execute();
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
$user_adresse = $user_data['adresse'] ?? '';

// Traitement du formulaire
if($_SERVER["REQUEST_METHOD"] == "POST") {
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if(empty($adresse_livraison)) {
        $error = "Veuillez saisir votre adresse de livraison";
    } else {
        try {
            $db->beginTransaction();
            
            // Vérifier le stock avant de créer la commande
            foreach($_SESSION['panier'] as $id_produit => $quantite) {
                if(isset($_SESSION['panier_produits'][$id_produit])) {
                    // Vérifier le stock actuel
                    $check_stock = "SELECT stock FROM Produit WHERE id_produit = :id_produit FOR UPDATE";
                    $check_stmt = $db->prepare($check_stock);
                    $check_stmt->bindParam(':id_produit', $id_produit);
                    $check_stmt->execute();
                    $produit = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if(!$produit || $produit['stock'] < $quantite) {
                        throw new Exception("Stock insuffisant pour un des produits");
                    }
                }
            }
            
            // Calculer le total
            $prix_total = 0;
            foreach($_SESSION['panier'] as $id_produit => $quantite) {
                if(isset($_SESSION['panier_produits'][$id_produit])) {
                    $prix_total += $_SESSION['panier_produits'][$id_produit]['prix'] * $quantite;
                }
            }
            
            // Créer la commande
            $query = "INSERT INTO Commande (id_status, prix_totale, id_utilisateur, adresse_livraison, date_commande) 
                      VALUES (1, :prix_total, :id_utilisateur, :adresse_livraison, NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':prix_total', $prix_total);
            $stmt->bindParam(':id_utilisateur', $_SESSION['client_id']);
            $stmt->bindParam(':adresse_livraison', $adresse_livraison);
            $stmt->execute();
            
            $commande_id = $db->lastInsertId();
            $adresse_livraison_saved = $adresse_livraison;
            
            // Mettre à jour l'adresse dans la table utilisateur si elle a changé
            if($user_adresse !== $adresse_livraison && !empty($adresse_livraison)) {
                $update_adresse = "UPDATE utilisateur SET adresse = :adresse WHERE id_utilisateur = :id_utilisateur";
                $update_stmt = $db->prepare($update_adresse);
                $update_stmt->bindParam(':adresse', $adresse_livraison);
                $update_stmt->bindParam(':id_utilisateur', $_SESSION['client_id']);
                $update_stmt->execute();
            }
            
            // Créer les lignes de commande
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
            
            // Créer le règlement
            $query = "INSERT INTO Reglement (montant, paye_a_livraison, id_commande) 
                      VALUES (:prix_total, 0, :id_commande)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':prix_total', $prix_total);
            $stmt->bindParam(':id_commande', $commande_id);
            $stmt->execute();
            
            $db->commit();
            
            // Vider le panier
            unset($_SESSION['panier']);
            unset($_SESSION['panier_produits']);
            
            $success = true;
            
        } catch(Exception $e) {
            $db->rollBack();
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
    <title>Commande - Ma Boutique</title>
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

        .back-btn {
            text-decoration: none;
            color: #444;
            font-size: 14px;
            padding: 8px 16px;
            border-radius: 8px;
            background: #f0f0f0;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #e0e0e0;
        }

        .user-info {
            background: #f0f0f0;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 14px;
        }

        /* Style distinctif pour le nom utilisateur */
        .user-name {
            position: relative;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white !important;
            padding: 6px 14px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            text-decoration: none;
        }

        .user-name:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white !important;
        }

        .user-name::before {
            content: '👤';
            font-size: 14px;
        }

        /* Main */
        .main-content {
            margin: 40px auto;
        }

        /* Success */
        .success-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            padding: 40px;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .success-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .success-card h2 {
            color: #10b981;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .order-number {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            font-size: 20px;
            font-weight: 600;
            color: #2563eb;
        }

        .delivery-address {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: left;
            border-left: 3px solid #10b981;
        }

        .delivery-address h4 {
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }

        .btn-orders {
            background: #2563eb;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            font-weight: 500;
        }

        .btn-orders:hover {
            background: #1e40af;
        }

        .btn-continue {
            background: #f0f0f0;
            color: #444;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
        }

        /* Order form */
        .order-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 768px) {
            .order-grid {
                grid-template-columns: 1fr;
            }
        }

        .order-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            overflow: hidden;
        }

        .card-header {
            padding: 18px 20px;
            border-bottom: 1px solid #eaeaea;
            background: white;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-body {
            padding: 20px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
            color: #333;
        }

        .form-group label .required {
            color: #ef4444;
        }

        .form-group input, 
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-group input:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37,99,235,0.1);
        }

        .form-group input:disabled {
            background: #f5f5f5;
            color: #666;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        small {
            color: #888;
            font-size: 12px;
        }

        /* Payment */
        .payment-box {
            background: #d1fae5;
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }

        .payment-box .icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .payment-box h4 {
            color: #065f46;
            margin-bottom: 8px;
        }

        .payment-box p {
            color: #555;
            font-size: 13px;
        }

        /* Cart items */
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eaeaea;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: 500;
            font-size: 14px;
        }

        .item-price {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }

        .item-quantity {
            color: #666;
            font-size: 14px;
        }

        .item-total {
            font-weight: 600;
            color: #2563eb;
        }

        .order-total {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 700;
        }

        .order-total span:last-child {
            color: #2563eb;
            font-size: 22px;
        }

        .btn-submit {
            width: 100%;
            background: #2563eb;
            color: white;
            border: none;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
        }

        .btn-submit:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #ef4444;
            font-size: 14px;
        }

        .info-message {
            background: #dbeafe;
            color: #1e40af;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid #2563eb;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 30px;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #eaeaea;
            margin-top: 40px;
        }

        hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #eaeaea;
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
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a href="panier.php" class="back-btn">← Panier</a>
                    <a href="profil.php" class="user-name">
                        <?php echo htmlspecialchars($_SESSION['client_nom']); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container main-content">
        <?php if($success): ?>
            <div class="success-card">
                <div class="success-icon">✅</div>
                <h2>Commande confirmée !</h2>
                <p>Merci pour votre commande</p>
                <div class="order-number">
                    #<?php echo str_pad($commande_id, 8, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="delivery-address">
                    <h4>📍 Livraison</h4>
                    <p><?php echo nl2br(htmlspecialchars($adresse_livraison_saved)); ?></p>
                </div>
                <p>Paiement à la livraison : <strong><?php echo number_format($prix_total, 0, ',', ' '); ?> DH</strong></p>
                <a href="mes_commandes.php" class="btn-orders">📦 Mes commandes</a>
                <a href="index.php" class="btn-continue">← Continuer</a>
            </div>
        <?php else: ?>
            <?php if($error): ?>
                <div class="error-message">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if(empty($user_adresse)): ?>
                <div class="info-message">
                    📝 <strong>Informations manquantes :</strong> Veuillez renseigner votre adresse de livraison.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="order-grid">
                    <!-- Livraison -->
                    <div class="order-card">
                        <div class="card-header">
                            <h3>📍 Livraison</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Nom</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['client_nom']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="text" value="<?php echo htmlspecialchars($_SESSION['client_telephone']); ?>" disabled>
                            </div>
                            <div class="form-group">
                                <label>Adresse <span class="required">*</span></label>
                                <textarea name="adresse_livraison" required placeholder="Votre adresse complète..." <?php echo !empty($user_adresse) ? '' : 'autofocus'; ?>><?php echo htmlspecialchars($user_adresse); ?></textarea>

                                <?php if(!empty($user_adresse)): ?>
                                    <small style="color: #10b981; display: block; margin-top: 5px;">✓ Adresse préremplie depuis votre profil</small>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label>Notes (optionnel)</label>
                                <textarea name="notes" placeholder="Instructions de livraison..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Paiement & Récap -->
                    <div class="order-card">
                        <div class="card-header">
                            <h3>💳 Paiement</h3>
                        </div>
                        <div class="card-body">
                            <div class="payment-box">
                                <div class="icon">🚚</div>
                                <h4>Paiement à la livraison</h4>
                                <p>Espèces ou carte bancaire</p>
                            </div>
                        </div>

                        <div class="card-header">
                            <h3>🛒 Récapitulatif</h3>
                        </div>
                        <div class="card-body">
                            <?php 
                            $total = 0;
                            foreach($_SESSION['panier'] as $id => $quantite):
                                if(isset($_SESSION['panier_produits'][$id])):
                                    $produit = $_SESSION['panier_produits'][$id];
                                    $sous_total = $produit['prix'] * $quantite;
                                    $total += $sous_total;
                            ?>
                                <div class="cart-item">
                                    <div>
                                        <div class="item-name"><?php echo htmlspecialchars($produit['nom']); ?></div>
                                        <div class="item-price"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</div>
                                    </div>
                                    <div class="item-quantity">x<?php echo $quantite; ?></div>
                                    <div class="item-total"><?php echo number_format($sous_total, 0, ',', ' '); ?> DH</div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                            <div class="order-total">
                                <span>Total</span>
                                <span><?php echo number_format($total, 0, ',', ' '); ?> DH</span>
                            </div>
                            
                            <button type="submit" class="btn-submit">Confirmer la commande</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique</p>
    </div>
</body>
</html>