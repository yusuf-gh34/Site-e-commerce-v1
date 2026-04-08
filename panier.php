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
    header("Location: panier.php?success=update");
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
        $message_success = 'Panier vidé';
    } elseif($_GET['success'] == 'update') {
        $message_success = 'Quantité mise à jour';
    }
}

if(isset($_GET['error'])) {
    if($_GET['error'] == 'stock_insuffisant') {
        $message_error = 'Stock insuffisant';
    }
}

// Message pour inviter à la connexion
if(!$est_connecte && count($panier_items) > 0) {
    $message_warning = 'Connectez-vous pour finaliser votre commande. <a href="login_client.php?redirect=panier.php" style="color:#856404;">Se connecter</a>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - Ma Boutique</title>
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
            color: #2563eb;
        }

        .login-link {
            background: #2563eb;
            color: white;
            padding: 8px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .login-link:hover {
            background: #1e40af;
        }

        /* Messages */
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #10b981;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #ef4444;
            font-size: 14px;
        }

        .alert-warning {
            background: #fed7aa;
            color: #92400e;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #f59e0b;
            font-size: 14px;
        }

        .alert-warning a {
            color: #92400e;
            font-weight: bold;
        }

        /* Cart container */
        .cart-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
            overflow: hidden;
            margin: 30px 0;
        }

        .cart-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eaeaea;
        }

        .cart-header h2 {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Table */
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }

        .cart-table th {
            text-align: left;
            padding: 15px 20px;
            background: #fafafa;
            color: #666;
            font-weight: 500;
            font-size: 13px;
            border-bottom: 1px solid #eaeaea;
        }

        .cart-table td {
            padding: 20px;
            border-bottom: 1px solid #eaeaea;
            vertical-align: middle;
        }

        .product-cell {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .product-image {
            width: 60px;
            height: 60px;
            background: #f5f5f5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-name {
            font-weight: 500;
            color: #111;
            font-size: 14px;
        }

        .product-price {
            font-weight: 600;
            color: #2563eb;
            font-size: 16px;
        }

        .quantity-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .quantity-input {
            width: 60px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
        }

        .update-btn {
            background: #f0f0f0;
            color: #444;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        }

        .update-btn:hover {
            background: #e0e0e0;
        }

        .remove-btn {
            background: none;
            color: #ef4444;
            border: none;
            cursor: pointer;
            font-size: 20px;
            text-decoration: none;
            padding: 5px 10px;
            transition: color 0.2s;
        }

        .remove-btn:hover {
            color: #dc2626;
        }

        .product-subtotal {
            font-weight: 600;
            font-size: 16px;
            color: #111;
        }

        /* Cart summary */
        .cart-summary {
            background: #fafafa;
            padding: 20px 25px;
            border-top: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .total-label {
            font-size: 16px;
            font-weight: 500;
            color: #666;
        }

        .total-amount {
            font-size: 28px;
            font-weight: 700;
            color: #2563eb;
            margin-left: 10px;
        }

        .cart-actions {
            display: flex;
            gap: 12px;
        }

        .btn-empty {
            background: #f0f0f0;
            color: #666;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-empty:hover {
            background: #e0e0e0;
        }

        .btn-continue {
            background: #f0f0f0;
            color: #444;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-continue:hover {
            background: #e0e0e0;
        }

        .btn-checkout {
            background: #2563eb;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-checkout:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        /* Empty cart */
        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-cart h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .empty-cart p {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .btn-shop {
            background: #2563eb;
            color: white;
            padding: 10px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
        }

        .btn-shop:hover {
            background: #1e40af;
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

        @media (max-width: 768px) {
            .cart-table th, .cart-table td {
                padding: 12px;
            }
            
            .product-cell {
                flex-direction: column;
                text-align: center;
            }
            
            .product-image {
                width: 50px;
                height: 50px;
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
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <a href="index.php" class="back-btn">← Continuer</a>
                    <?php if(!$est_connecte && count($panier_items) > 0): ?>
                        <a href="login_client.php?redirect=panier.php" class="login-link">🔐 Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if($message_success): ?>
            <div class="toast">✅ <?php echo htmlspecialchars($message_success); ?></div>
        <?php endif; ?>
        
        <?php if($message_error): ?>
            <div class="alert-error">❌ <?php echo htmlspecialchars($message_error); ?></div>
        <?php endif; ?>

        <?php if($message_warning): ?>
            <div class="alert-warning">⚠️ <?php echo $message_warning; ?></div>
        <?php endif; ?>

        <div class="cart-container">
            <div class="cart-header">
                <h2>🛒 Mon panier</h2>
            </div>

            <?php if(count($panier_items) > 0): ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($panier_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
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
                                    <a href="panier.php?action=supprimer&id=<?php echo $item['id']; ?>" class="remove-btn" onclick="return confirm('Supprimer ?')">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="cart-summary">
                    <div>
                        <span class="total-label">Total</span>
                        <span class="total-amount"><?php echo number_format($total, 0, ',', ' '); ?> DH</span>
                    </div>
                    <div class="cart-actions">
                        <a href="panier.php?action=vider" class="btn-empty" onclick="return confirm('Vider le panier ?')">Vider</a>
                        <a href="index.php" class="btn-continue">Continuer</a>
                        <?php if($est_connecte): ?>
                            <a href="commande.php" class="btn-checkout">Commander →</a>
                        <?php else: ?>
                            <a href="login_client.php?redirect=panier.php" class="btn-checkout" style="background:#f59e0b;">Connexion</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Panier vide</h3>
                    <p>Ajoutez des produits depuis notre boutique</p>
                    <a href="index.php" class="btn-shop">Voir les produits</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique</p>
    </div>

    <script>
        // Animation du toast
        setTimeout(() => {
            const toast = document.querySelector('.toast');
            if(toast) {
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 2500);
            }
        }, 100);
    </script>
</body>
</html>