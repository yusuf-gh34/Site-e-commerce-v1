<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Initialiser le panier si nécessaire
if(!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}
if(!isset($_SESSION['panier_produits'])) {
    $_SESSION['panier_produits'] = [];
}

// Traitement des actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$produit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($action == 'supprimer' && $produit_id > 0) {
    unset($_SESSION['panier'][$produit_id]);
    unset($_SESSION['panier_produits'][$produit_id]);
    header("Location: panier.php?success=supprime");
    exit();
}

if($action == 'vider') {
    $_SESSION['panier'] = [];
    $_SESSION['panier_produits'] = [];
    header("Location: panier.php?success=vide");
    exit();
}

if($action == 'update' && $produit_id > 0) {
    $quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 1;
    if($quantite <= 0) {
        unset($_SESSION['panier'][$produit_id]);
        unset($_SESSION['panier_produits'][$produit_id]);
    } else {
        // Vérifier le stock
        $query = "SELECT stock FROM Produit WHERE id_produit = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $produit_id);
        $stmt->execute();
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($produit && $quantite <= $produit['stock']) {
            $_SESSION['panier'][$produit_id] = $quantite;
        } else {
            header("Location: panier.php?error=stock_insuffisant");
            exit();
        }
    }
    header("Location: panier.php");
    exit();
}

// Calculer le total du panier
$total = 0;
$panier_items = [];

foreach($_SESSION['panier'] as $id => $quantite) {
    if(isset($_SESSION['panier_produits'][$id])) {
        $prix = $_SESSION['panier_produits'][$id]['prix'];
        $sous_total = $prix * $quantite;
        $total += $sous_total;
        
        $panier_items[] = [
            'id' => $id,
            'nom' => $_SESSION['panier_produits'][$id]['nom'],
            'prix' => $prix,
            'quantite' => $quantite,
            'sous_total' => $sous_total,
            'image' => isset($_SESSION['panier_produits'][$id]['image']) ? $_SESSION['panier_produits'][$id]['image'] : null
        ];
    }
}

// Vérifier si l'utilisateur est connecté
$est_connecte = isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true;

// Récupérer les messages
$message_success = '';
$message_error = '';
$message_warning = '';

if(isset($_GET['success'])) {
    if($_GET['success'] == 'supprime') {
        $message_success = 'Produit supprimé du panier';
    } elseif($_GET['success'] == 'vide') {
        $message_success = 'Votre panier a été vidé';
    } elseif($_GET['success'] == 'update') {
        $message_success = 'Quantité mise à jour';
    }
}

if(isset($_GET['error'])) {
    if($_GET['error'] == 'stock_insuffisant') {
        $message_error = 'Stock insuffisant pour ce produit';
    }
}

// Message pour inviter à la connexion si panier non vide et utilisateur non connecté
if(!$est_connecte && count($panier_items) > 0) {
    $message_warning = 'Vous n\'êtes pas connecté. <a href="login_client.php?redirect=panier.php" style="color: #856404; font-weight: bold;">Connectez-vous</a> ou <a href="register.php?redirect=panier.php" style="color: #856404; font-weight: bold;">inscrivez-vous</a> pour finaliser votre commande. Votre panier sera sauvegardé.';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - Boutique</title>
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

        .login-link {
            background: #28a745;
            color: white;
            padding: 8px 18px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }

        .login-link:hover {
            background: #218838;
        }

        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .cart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .cart-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
        }

        .cart-header h2 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Messages */
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
            animation: fadeOut 3s forwards;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #ffc107;
        }

        .alert-warning a {
            color: #856404;
            font-weight: bold;
        }

        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; display: none; }
        }

        /* Table */
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            text-align: left;
            padding: 15px 20px;
            background: #f8f9fa;
            color: #333;
            font-weight: 600;
            border-bottom: 2px solid #e9ecef;
        }

        .cart-table td {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .product-image {
            width: 80px;
            height: 80px;
            background: #f0f0f0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }

        .product-price {
            color: #667eea;
            font-weight: bold;
            font-size: 18px;
        }

        .quantity-input {
            width: 70px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-align: center;
            font-size: 14px;
        }

        .quantity-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .update-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .update-btn:hover {
            background: #218838;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }

        .remove-btn:hover {
            background: #c82333;
        }

        .product-subtotal {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }

        /* Cart summary */
        .cart-summary {
            background: #f8f9fa;
            padding: 25px;
            border-top: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .total-label {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .total-amount {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }

        .cart-actions {
            display: flex;
            gap: 15px;
        }

        .btn-empty {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: transform 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-empty:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-checkout {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: transform 0.2s;
            display: inline-block;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
        }

        .btn-checkout-disabled {
            background: #ccc;
            color: #666;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            cursor: not-allowed;
            display: inline-block;
        }

        .btn-continue {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
        }

        .btn-continue:hover {
            background: #218838;
        }

        /* Empty cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-cart h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }

        .empty-cart p {
            color: #666;
            margin-bottom: 30px;
        }

        .btn-shop {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }

        @media (max-width: 768px) {
            .cart-table th, .cart-table td {
                padding: 12px;
            }
            
            .product-image {
                width: 50px;
                height: 50px;
                font-size: 24px;
            }
            
            .cart-summary {
                flex-direction: column;
                text-align: center;
            }
            
            .cart-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .cart-actions a, .cart-actions button {
                text-align: center;
                width: 100%;
            }
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
            <div style="display: flex; gap: 15px; align-items: center;">
                <a href="index.php" class="back-btn">← Continuer mes achats</a>
                <?php if(!$est_connecte): ?>
                    <a href="login_client.php?redirect=panier.php" class="login-link">🔐 Se connecter</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if($message_success): ?>
            <div class="alert-success">✅ <?php echo htmlspecialchars($message_success); ?></div>
        <?php endif; ?>
        
        <?php if($message_error): ?>
            <div class="alert-error">❌ <?php echo htmlspecialchars($message_error); ?></div>
        <?php endif; ?>

        <?php if($message_warning): ?>
            <div class="alert-warning">⚠️ <?php echo $message_warning; ?></div>
        <?php endif; ?>

        <div class="cart-container">
            <div class="cart-header">
                <h2>🛒 Mon Panier</h2>
            </div>

            <?php if(count($panier_items) > 0): ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix unitaire</th>
                            <th>Quantité</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($panier_items as $item): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 15px;">
                                        <div class="product-image">
                                            <?php if(!empty($item['image']) && file_exists('uploads/products/' . $item['image'])): ?>
                                                <img src="uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['nom']); ?>">
                                            <?php else: ?>
                                                📦
                                            <?php endif; ?>
                                        </div>
                                        <div class="product-name"><?php echo htmlspecialchars($item['nom']); ?></div>
                                    </div>
                                </td>
                                <td class="product-price"><?php echo number_format($item['prix'], 0, ',', ' '); ?> DH</td>
                                <td>
                                    <form method="POST" action="panier.php?action=update&id=<?php echo $item['id']; ?>" class="quantity-form">
                                        <input type="number" name="quantite" value="<?php echo $item['quantite']; ?>" min="1" max="99" class="quantity-input">
                                        <button type="submit" class="update-btn">Mettre à jour</button>
                                    </form>
                                </td>
                                <td class="product-subtotal"><?php echo number_format($item['sous_total'], 0, ',', ' '); ?> DH</td>
                                <td>
                                    <a href="panier.php?action=supprimer&id=<?php echo $item['id']; ?>" class="remove-btn" onclick="return confirm('Supprimer ce produit ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div>
                        <span class="total-label">Total TTC :</span>
                        <span class="total-amount"><?php echo number_format($total, 0, ',', ' '); ?> DH</span>
                    </div>
                    <div class="cart-actions">
                        <a href="panier.php?action=vider" class="btn-empty" onclick="return confirm('Vider tout le panier ?')">🗑️ Vider le panier</a>
                        <a href="index.php" class="btn-continue">🛍️ Continuer mes achats</a>
                        <?php if($est_connecte): ?>
                            <a href="commande.php" class="btn-checkout">📦 Passer la commande →</a>
                        <?php else: ?>
                            <a href="login_client.php?redirect=panier.php" class="btn-checkout" style="background: #ffc107; color: #333;">🔐 Connectez-vous pour commander</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Votre panier est vide</h3>
                    <p>Découvrez nos produits et ajoutez-les à votre panier</p>
                    <a href="index.php" class="btn-shop">Découvrir nos produits</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
    </div>
</body>
</html>