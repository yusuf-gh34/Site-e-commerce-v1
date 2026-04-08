<?php
session_start();
require_once 'config/database.php';

// Vérifier si le client est connecté
if(!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header("Location: login_client.php?error=connectez_vous");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Gestion des messages
$success_message = '';
$error_message = '';

if(isset($_GET['success'])) {
    if($_GET['success'] == 'canceled') {
        $success_message = '✅ Votre commande a été annulée avec succès. Les stocks ont été restaurés.';
    }
}

if(isset($_GET['error'])) {
    switch($_GET['error']) {
        case 'missing_id':
            $error_message = '❌ Aucune commande spécifiée.';
            break;
        case 'not_found':
            $error_message = '❌ Commande introuvable.';
            break;
        case 'cannot_cancel':
            $error_message = '❌ Cette commande ne peut plus être annulée car elle a déjà été confirmée ou expédiée.';
            break;
        case 'db_error':
            $error_message = '❌ Une erreur est survenue lors de l\'annulation. Veuillez réessayer.';
            break;
        default:
            $error_message = '❌ Une erreur est survenue.';
    }
}

// Récupérer les commandes du client
$query = "SELECT c.*, s.nom as status_nom 
          FROM commande c
          JOIN status s ON c.id_status = s.id_status
          WHERE c.id_utilisateur = :id_utilisateur
          ORDER BY c.date_commande DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_utilisateur', $_SESSION['client_id']);
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les lignes de commande pour chaque commande
foreach($commandes as $key => $commande) {
    $query_details = "SELECT lc.*, p.nom as produit_nom, p.image 
                      FROM ligne_commande lc
                      JOIN produit p ON lc.id_produit = p.id_produit
                      WHERE lc.id_commande = :id_commande";
    $stmt_details = $db->prepare($query_details);
    $stmt_details->bindParam(':id_commande', $commande['id_commande']);
    $stmt_details->execute();
    $commandes[$key]['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    $commandes[$key]['nb_articles'] = count($commandes[$key]['details']);
}

// Vérifier les commandes éligibles à l'annulation (date limite de 24h)
foreach($commandes as $key => $commande) {
    $date_commande = new DateTime($commande['date_commande']);
    $now = new DateTime();
    $interval = $now->diff($date_commande);
    $hours_diff = ($interval->days * 24) + $interval->h;
    
    // Annulable seulement si statut "En attente" (id_status = 1) et moins de 24h
    $commandes[$key]['can_cancel'] = ($commande['id_status'] == 1 && $hours_diff < 24);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes commandes - Ma Boutique</title>
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

        .toast.error {
            background: #ef4444;
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

        /* Main */
        .main-content {
            margin: 40px auto;
        }

        .page-title {
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 24px;
            color: #111;
            margin-bottom: 8px;
        }

        .page-title p {
            color: #666;
            font-size: 14px;
        }

        /* Order card */
        .order-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow 0.2s;
        }

        .order-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        /* Order header */
        .order-header {
            padding: 20px;
            border-bottom: 1px solid #eaeaea;
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: baseline;
        }

        .order-number {
            font-weight: 600;
            color: #2563eb;
            font-size: 15px;
        }

        .order-date {
            color: #888;
            font-size: 13px;
        }

        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-en-attente {
            background: #fef3c7;
            color: #d97706;
        }

        .status-confirmee {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-expediee {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .status-livree {
            background: #d1fae5;
            color: #10b981;
        }

        .status-annulee {
            background: #fee2e2;
            color: #ef4444;
        }

        .cancel-info {
            font-size: 11px;
            color: #888;
            margin-left: 10px;
        }

        /* Products list */
        .products-list {
            padding: 0 20px;
        }

        .product-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 60px;
            height: 60px;
            background: #f5f5f5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image span {
            font-size: 28px;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .product-price {
            font-size: 12px;
            color: #888;
        }

        .product-quantity {
            color: #666;
            font-size: 14px;
            margin: 0 15px;
            flex-shrink: 0;
        }

        .product-total {
            font-weight: 600;
            color: #2563eb;
            font-size: 14px;
            min-width: 80px;
            text-align: right;
            flex-shrink: 0;
        }

        /* Order footer */
        .order-footer {
            padding: 15px 20px;
            background: #fafafa;
            border-top: 1px solid #eaeaea;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-total {
            display: flex;
            align-items: baseline;
            gap: 10px;
            flex-wrap: wrap;
        }

        .total-label {
            font-size: 14px;
            color: #666;
        }

        .total-amount {
            font-size: 18px;
            font-weight: 700;
            color: #2563eb;
        }

        .total-articles {
            font-size: 12px;
            color: #888;
        }

        .btn-cancel {
            background: none;
            border: 1px solid #ef4444;
            color: #ef4444;
            font-size: 13px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .btn-cancel:hover {
            background: #fee2e2;
            transform: translateY(-1px);
        }

        .btn-cancel.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            border-color: #ccc;
            color: #999;
        }

        .btn-cancel.disabled:hover {
            background: none;
            transform: none;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 1px solid #eaeaea;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #111;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .btn-shop {
            background: #2563eb;
            color: white;
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            display: inline-block;
            transition: all 0.2s;
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
            .product-item {
                flex-wrap: wrap;
            }
            
            .product-total {
                text-align: left;
                margin-left: 75px;
            }
        }
    </style>
</head>
<body>
    <?php if($success_message): ?>
        <div class="toast"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if($error_message): ?>
        <div class="toast error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                
                <div class="user-links">
                    <span style="color:#666;">👋 <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
                    <a href="mes_commandes.php" style="color:#2563eb;">Commandes</a>
                    <a href="contact.php">Contact</a>
                    <a href="logout_client.php" onclick="return confirm('Déconnexion ?')">Déconnexion</a>
                    <a href="panier.php" class="cart">
                        🛒 Panier
                        <span class="cart-count"><?php echo isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container main-content">
        <div class="page-title">
            <h2>📦 Mes commandes</h2>
            <p>Retrouvez l'historique de toutes vos commandes</p>
        </div>

        <?php if(count($commandes) > 0): ?>
            <?php foreach($commandes as $commande): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <span class="order-number">#<?php echo str_pad($commande['id_commande'], 8, '0', STR_PAD_LEFT); ?></span>
                            <span class="order-date">📅 <?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></span>
                            <span class="order-status status-<?php 
                                $status_class = strtolower($commande['status_nom']);
                                $status_class = str_replace(' ', '-', $status_class);
                                $status_class = str_replace('é', 'e', $status_class);
                                echo $status_class; 
                            ?>">
                                <?php 
                                    $status_icon = '';
                                    switch($commande['status_nom']) {
                                        case 'En attente': $status_icon = '⏳ '; break;
                                        case 'Confirmée': $status_icon = '✅ '; break;
                                        case 'Expédiée': $status_icon = '🚚 '; break;
                                        case 'Livrée': $status_icon = '🏠 '; break;
                                        case 'Annulée': $status_icon = '❌ '; break;
                                    }
                                    echo $status_icon . htmlspecialchars($commande['status_nom']); 
                                ?>
                            </span>
                            <?php if($commande['can_cancel']): ?>
                                <span class="cancel-info"></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="products-list">
                        <?php if(isset($commande['details']) && count($commande['details']) > 0): ?>
                            <?php foreach($commande['details'] as $detail): ?>
                                <div class="product-item">
                                    <div class="product-image">
                                        <?php if(!empty($detail['image']) && file_exists('uploads/products/' . $detail['image'])): ?>
                                            <img src="uploads/products/<?php echo $detail['image']; ?>" alt="<?php echo htmlspecialchars($detail['produit_nom']); ?>">
                                        <?php else: ?>
                                            <span>📦</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <div class="product-name"><?php echo htmlspecialchars($detail['produit_nom']); ?></div>
                                        <div class="product-price"><?php echo number_format($detail['prix_unitaire'], 0, ',', ' '); ?> DH</div>
                                    </div>
                                    <div class="product-quantity">x<?php echo $detail['quantite']; ?></div>
                                    <div class="product-total"><?php echo number_format($detail['prix_unitaire'] * $detail['quantite'], 0, ',', ' '); ?> DH</div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="product-item">
                                <div class="product-info">Aucun détail disponible</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="order-footer">
                        <div class="order-total">
                            <span class="total-label">Total</span>
                            <span class="total-amount"><?php echo number_format($commande['prix_totale'], 0, ',', ' '); ?> DH</span>
                            <span class="total-articles">(<?php echo $commande['nb_articles']; ?> article<?php echo $commande['nb_articles'] > 1 ? 's' : ''; ?>)</span>
                        </div>
                        <?php if($commande['can_cancel']): ?>
                            <button class="btn-cancel" onclick="annulerCommande(<?php echo $commande['id_commande']; ?>)">
                                ❌ Annuler la commande
                            </button>
                        <?php elseif($commande['status_nom'] == 'Annulée'): ?>
                            <span style="font-size: 12px; color: #888;">✔️ Commande annulée</span>
                        <?php elseif($commande['id_status'] == 1): ?>
                            <button class="btn-cancel disabled" disabled>
                                ⏰ Délai d'annulation dépassé (24h)
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>Aucune commande</h3>
                <p>Vous n'avez pas encore passé de commande</p>
                <a href="index.php" class="btn-shop">🛍️ Découvrir nos produits</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique</p>
    </div>

    <script>
        function annulerCommande(id) {
            if(confirm('⚠️ Êtes-vous sûr de vouloir annuler cette commande ?\n\nLes stocks seront automatiquement restaurés.')) {
                window.location.href = 'annuler_commande.php?id=' + id;
            }
        }
    </script>
</body>
</html>