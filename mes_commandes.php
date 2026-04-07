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

// Récupérer les commandes du client avec les détails
$query = "SELECT c.*, s.nom as status_nom 
          FROM Commande c
          JOIN Status s ON c.id_status = s.id_status
          WHERE c.id_utilisateur = :id_utilisateur
          ORDER BY c.date_commande DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':id_utilisateur', $_SESSION['client_id']);
$stmt->execute();
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les lignes de commande pour chaque commande
foreach($commandes as &$commande) {
    $query_details = "SELECT lc.*, p.nom as produit_nom, p.image 
                      FROM Ligne_commande lc
                      JOIN Produit p ON lc.id_produit = p.id_produit
                      WHERE lc.id_commande = :id_commande";
    $stmt_details = $db->prepare($query_details);
    $stmt_details->bindParam(':id_commande', $commande['id_commande']);
    $stmt_details->execute();
    $commande['details'] = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
    $commande['nb_articles'] = count($commande['details']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes commandes - Boutique</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            transition: all 0.3s;
            font-weight: 500;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Page title */
        .page-title {
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 32px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title p {
            color: #666;
            margin-top: 8px;
        }

        /* Order card */
        .order-card {
            background: white;
            border-radius: 20px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        /* Order header */
        .order-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .order-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: baseline;
        }

        .order-number {
            font-size: 18px;
            font-weight: bold;
        }

        .order-number span {
            color: #667eea;
            font-size: 20px;
        }

        .order-date {
            color: #888;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .order-status {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .status-en-attente {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmee {
            background: #cfe2ff;
            color: #084298;
        }

        .status-expediee {
            background: #cff4fc;
            color: #055160;
        }

        .status-livree {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
        }

        /* Order body */
        .order-body {
            padding: 0;
        }

        /* Products list */
        .products-list {
            padding: 20px 25px;
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
            width: 70px;
            height: 70px;
            background: #f5f5f5;
            border-radius: 12px;
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

        .product-details {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .product-price {
            font-size: 13px;
            color: #888;
        }

        .product-quantity {
            color: #666;
            font-size: 14px;
        }

        .product-total {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
            min-width: 100px;
            text-align: right;
        }

        /* Order footer */
        .order-footer {
            background: #f8f9fa;
            padding: 18px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid #e9ecef;
        }

        .order-total {
            display: flex;
            gap: 15px;
            align-items: baseline;
            flex-wrap: wrap;
        }

        .total-label {
            font-size: 16px;
            color: #666;
        }

        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }

        .total-articles {
            font-size: 13px;
            color: #888;
            background: #e9ecef;
            padding: 4px 12px;
            border-radius: 20px;
        }

        /* Toggle button */
        .toggle-details {
            background: transparent;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .toggle-details:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        .toggle-icon {
            transition: transform 0.3s;
        }

        .toggle-icon.rotated {
            transform: rotate(180deg);
        }

        /* Details section (collapsed by default) */
        .details-section {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            background: #fafbfc;
            border-top: 1px solid #e9ecef;
        }

        .details-section.show {
            max-height: 2000px;
            transition: max-height 0.6s ease-in;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 30px;
        }

        .btn-shop {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            transition: transform 0.3s;
        }

        .btn-shop:hover {
            transform: translateY(-2px);
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-card {
            animation: fadeIn 0.5s ease-out;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .product-item {
                flex-wrap: wrap;
            }
            
            .product-total {
                text-align: left;
                margin-left: 85px;
            }
            
            .order-footer {
                flex-direction: column;
                align-items: stretch;
            }
            
            .toggle-details {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>📦 Ma Boutique</h1>
            </div>
            <a href="index.php" class="back-btn">← Continuer mes achats</a>
        </div>
    </div>

    <div class="main-content">
        <div class="page-title">
            <h2>📋 Mes commandes</h2>
            <p>Retrouvez l'historique et le suivi de toutes vos commandes</p>
        </div>

        <?php if(count($commandes) > 0): ?>
            <?php foreach($commandes as $index => $commande): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-info">
                            <div class="order-number">
                                📄 Commande <span>#<?php echo str_pad($commande['id_commande'], 8, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="order-date">
                                📅 <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?>
                            </div>
                            <div class="order-status status-<?php echo str_replace(' ', '-', strtolower($commande['status_nom'])); ?>">
                                <?php 
                                    $status_icons = [
                                        'En attente' => '⏳',
                                        'Confirmée' => '✅',
                                        'Expédiée' => '🚚',
                                        'Livrée' => '📦',
                                        'Annulée' => '❌'
                                    ];
                                    $icon = $status_icons[$commande['status_nom']] ?? '📋';
                                    echo $icon . ' ' . htmlspecialchars($commande['status_nom']);
                                ?>
                            </div>
                        </div>
                        <button class="toggle-details" onclick="toggleDetails(<?php echo $index; ?>)">
                            <span>Voir les détails</span>
                            <span class="toggle-icon" id="toggle-icon-<?php echo $index; ?>">▼</span>
                        </button>
                    </div>

                    <div class="order-body">
                        <div class="products-list">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 15px; color: #888; font-size: 13px; font-weight: 500;">
                                <span>Produit</span>
                                <span style="margin-right: 100px;">Total</span>
                            </div>
                            <?php foreach($commande['details'] as $detail): ?>
                                <div class="product-item">
                                    <div class="product-image">
                                        <?php if(!empty($detail['image']) && file_exists('uploads/products/' . $detail['image'])): ?>
                                            <img src="uploads/products/<?php echo $detail['image']; ?>" alt="<?php echo htmlspecialchars($detail['produit_nom']); ?>">
                                        <?php else: ?>
                                            📦
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-details">
                                        <div class="product-name"><?php echo htmlspecialchars($detail['produit_nom']); ?></div>
                                        <div class="product-price"><?php echo number_format($detail['prix_unitaire'], 0, ',', ' '); ?> DH</div>
                                    </div>
                                    <div class="product-quantity">x<?php echo $detail['quantite']; ?></div>
                                    <div class="product-total"><?php echo number_format($detail['prix_unitaire'] * $detail['quantite'], 0, ',', ' '); ?> DH</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="order-footer">
                        <div class="order-total">
                            <span class="total-label">Total de la commande :</span>
                            <span class="total-amount"><?php echo number_format($commande['prix_totale'], 0, ',', ' '); ?> DH</span>
                            <span class="total-articles"><?php echo $commande['nb_articles']; ?> article(s)</span>
                        </div>
                        <div class="order-actions">
                            <?php if($commande['status_nom'] == 'En attente'): ?>
                                <button class="toggle-details" onclick="annulerCommande(<?php echo $commande['id_commande']; ?>)" style="color: #dc3545;">
                                    ❌ Annuler la commande
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section détails cachée (pour plus d'infos si besoin) -->
                    <div class="details-section" id="details-<?php echo $index; ?>">
                        <div style="padding: 20px 25px; background: #f8f9fa;">
                            <h4 style="margin-bottom: 15px; color: #333;">📝 Informations complémentaires</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                                <div>
                                    <strong>ID Commande :</strong>
                                    <p style="color: #666;">#<?php echo str_pad($commande['id_commande'], 8, '0', STR_PAD_LEFT); ?></p>
                                </div>
                                <div>
                                    <strong>Date de commande :</strong>
                                    <p style="color: #666;"><?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?></p>
                                </div>
                                <div>
                                    <strong>Statut actuel :</strong>
                                    <p style="color: #667eea;"><?php echo htmlspecialchars($commande['status_nom']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📦</div>
                <h3>Vous n'avez pas encore passé de commande</h3>
                <p>Découvrez nos produits et faites votre première commande dès maintenant !</p>
                <a href="index.php" class="btn-shop">🛍️ Découvrir nos produits</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
        <p style="font-size: 12px; margin-top: 10px;">Suivez l'état de vos commandes en temps réel</p>
    </div>

    <script>
        function toggleDetails(index) {
            const detailsSection = document.getElementById('details-' + index);
            const toggleIcon = document.getElementById('toggle-icon-' + index);
            
            if (detailsSection.classList.contains('show')) {
                detailsSection.classList.remove('show');
                toggleIcon.classList.remove('rotated');
                toggleIcon.parentElement.querySelector('span:first-child').textContent = 'Voir les détails';
            } else {
                detailsSection.classList.add('show');
                toggleIcon.classList.add('rotated');
                toggleIcon.parentElement.querySelector('span:first-child').textContent = 'Masquer les détails';
            }
        }

        function annulerCommande(id) {
            if(confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
                window.location.href = 'annuler_commande.php?id=' + id;
            }
        }
    </script>
</body>
</html>