<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'utilisateur est connecté et est un gestionnaire (role_id = 2)
if(!isset($_SESSION['manager_logged_in']) || $_SESSION['manager_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$id_gestionnaire = $_SESSION['manager_id'];

$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';

$success_message = '';
$error_message = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // === MODIFIER UN PRODUIT (sans le prix) ===
    if (isset($_POST['edit_product'])) {
        $id_produit = $_POST['id_produit'];
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        $stock = $_POST['stock'];
        $id_categorie = $_POST['id_categorie'];
        
        $query_img = "SELECT image FROM Produit WHERE id_produit = :id_produit";
        $stmt_img = $db->prepare($query_img);
        $stmt_img->execute([':id_produit' => $id_produit]);
        $current_image = $stmt_img->fetch(PDO::FETCH_ASSOC)['image'] ?? null;
        $image_name = $current_image;
        
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed)) {
                if($current_image && file_exists('../uploads/products/' . $current_image)) {
                    unlink('../uploads/products/' . $current_image);
                }
                $image_name = time() . '_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/products/' . $image_name;
                if(!is_dir('../uploads/products/')) {
                    mkdir('../uploads/products/', 0777, true);
                }
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
            } else {
                $error_message = "Format d'image non autorisé.";
            }
        }
        
        if(empty($error_message)) {
            $query = "UPDATE Produit SET nom = :nom, description = :description, 
                      stock = :stock, image = :image, id_categorie = :id_categorie 
                      WHERE id_produit = :id_produit";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':stock' => $stock,
                ':image' => $image_name,
                ':id_categorie' => $id_categorie,
                ':id_produit' => $id_produit
            ]);
            $success_message = "Produit modifié avec succès !";
        }
    }
    
    // === MODIFICATION COMPLÈTE D'UNE COMMANDE ===
    if (isset($_POST['update_order_details'])) {
        $id_commande = $_POST['id_commande'];
        $id_status = $_POST['id_status'];
        $adresse_livraison = $_POST['adresse_livraison'] ?? null;
        $nouveau_total = $_POST['nouveau_total'] ?? 0;
        $products = isset($_POST['products']) ? $_POST['products'] : [];
        
        try {
            $db->beginTransaction();
            
            // 1. Mettre à jour le statut et l'adresse
            $query = "UPDATE Commande SET id_status = :id_status, adresse_livraison = :adresse WHERE id_commande = :id_commande";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id_status' => $id_status,
                ':adresse' => $adresse_livraison,
                ':id_commande' => $id_commande
            ]);
            
            // 2. Récupérer les anciens produits pour restaurer les stocks
            $old_products = $db->prepare("SELECT id_produit, quantite FROM Ligne_commande WHERE id_commande = :id_commande");
            $old_products->execute([':id_commande' => $id_commande]);
            $old_items = $old_products->fetchAll(PDO::FETCH_ASSOC);
            
            // Restaurer les stocks des anciens produits
            foreach ($old_items as $old) {
                $restore = $db->prepare("UPDATE Produit SET stock = stock + :quantite WHERE id_produit = :id_produit");
                $restore->execute([
                    ':quantite' => $old['quantite'],
                    ':id_produit' => $old['id_produit']
                ]);
            }
            
            // 3. Supprimer les anciennes lignes de commande
            $delete = $db->prepare("DELETE FROM Ligne_commande WHERE id_commande = :id_commande");
            $delete->execute([':id_commande' => $id_commande]);
            
            // 4. Insérer les nouvelles lignes et mettre à jour les stocks
            foreach ($products as $product) {
                $id_produit = $product['id_produit'];
                $quantite = $product['quantite'];
                
                $check_stock = $db->prepare("SELECT stock FROM Produit WHERE id_produit = :id_produit");
                $check_stock->execute([':id_produit' => $id_produit]);
                $stock_dispo = $check_stock->fetchColumn();
                
                if ($quantite > $stock_dispo) {
                    throw new Exception("Stock insuffisant pour le produit. Disponible: $stock_dispo");
                }
                
                $insert = $db->prepare("INSERT INTO Ligne_commande (id_commande, id_produit, quantite, prix_unitaire) 
                                         VALUES (:id_commande, :id_produit, :quantite, 
                                         (SELECT prix FROM Produit WHERE id_produit = :id_produit2))");
                $insert->execute([
                    ':id_commande' => $id_commande,
                    ':id_produit' => $id_produit,
                    ':quantite' => $quantite,
                    ':id_produit2' => $id_produit
                ]);
                
                $update_stock = $db->prepare("UPDATE Produit SET stock = stock - :quantite WHERE id_produit = :id_produit");
                $update_stock->execute([
                    ':quantite' => $quantite,
                    ':id_produit' => $id_produit
                ]);
            }
            
            // 5. Mettre à jour le prix total de la commande
            $update_total = $db->prepare("UPDATE Commande SET prix_totale = :total WHERE id_commande = :id_commande");
            $update_total->execute([
                ':total' => $nouveau_total,
                ':id_commande' => $id_commande
            ]);
            
            // 6. Mettre à jour le règlement
            $query_reglement = "UPDATE Reglement SET montant = :total WHERE id_commande = :id_commande";
            $stmt_reglement = $db->prepare($query_reglement);
            $stmt_reglement->execute([
                ':total' => $nouveau_total,
                ':id_commande' => $id_commande
            ]);
            
            // 7. Si le statut est "livrée", marquer comme payé
            if($id_status == 5) {
                $query_reglement = "UPDATE Reglement SET paye_a_livraison = 1 WHERE id_commande = :id_commande";
                $stmt_reglement = $db->prepare($query_reglement);
                $stmt_reglement->execute([':id_commande' => $id_commande]);
            }
            
            $db->commit();
            $success_message = "Commande modifiée avec succès !";
            
            header("Location: gestionnaire_dashboard.php?action=orders&success=1");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la modification: " . $e->getMessage();
        }
    }
    
    // === MODIFICATION SIMPLE DU STATUT ===
    if (isset($_POST['update_order_status'])) {
        $id_commande = $_POST['id_commande'];
        $id_status = $_POST['id_status'];
        
        $query = "UPDATE Commande SET id_status = :id_status WHERE id_commande = :id_commande";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_status' => $id_status,
            ':id_commande' => $id_commande
        ]);
        
        if($id_status == 5) {
            $query_reglement = "UPDATE Reglement SET paye_a_livraison = 1 WHERE id_commande = :id_commande";
            $stmt_reglement = $db->prepare($query_reglement);
            $stmt_reglement->execute([':id_commande' => $id_commande]);
        }
        
        $success_message = "Statut de la commande mis à jour avec succès !";
    }
}

// Récupération des données
$produits = $db->query("
    SELECT p.*, c.nom as categorie_nom 
    FROM Produit p 
    LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
    ORDER BY p.id_produit DESC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT * FROM Categorie")->fetchAll(PDO::FETCH_ASSOC);

$commandes = $db->query("
    SELECT c.*, u.nom as client_nom, u.telephone as client_telephone, s.nom as status_nom 
    FROM Commande c 
    JOIN Utilisateur u ON c.id_utilisateur = u.id_utilisateur 
    JOIN Status s ON c.id_status = s.id_status 
    ORDER BY c.date_commande DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach($commandes as $key => $commande) {
    $details = $db->prepare("
        SELECT lc.*, p.nom as produit_nom, p.image, p.prix, p.stock as stock_actuel
        FROM Ligne_commande lc
        JOIN Produit p ON lc.id_produit = p.id_produit
        WHERE lc.id_commande = :id_commande
    ");
    $details->execute([':id_commande' => $commande['id_commande']]);
    $commandes[$key]['produits'] = $details->fetchAll(PDO::FETCH_ASSOC);
}

$status_list = $db->query("SELECT * FROM Status")->fetchAll(PDO::FETCH_ASSOC);
$all_products = $db->query("SELECT id_produit, nom, prix, stock FROM Produit ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = [];
$query = "SELECT COUNT(*) as total FROM Produit";
$stmt = $db->query($query);
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Commande";
$stmt = $db->query($query);
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Commande WHERE id_status = 1";
$stmt = $db->query($query);
$stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Commande WHERE id_status = 2";
$stmt = $db->query($query);
$stats['confirmed_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Gestionnaire</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
        }
        
        .navbar h1 { font-size: 24px; }
        
        .user-info { display: flex; align-items: center; gap: 20px; }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 250px;
            height: calc(100% - 70px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu li a {
            display: block;
            padding: 12px 25px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .sidebar-menu li a:hover {
            background: #f0f0f0;
            padding-left: 30px;
        }
        
        .sidebar-menu li.active a {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .main-content {
            margin-left: 250px;
            margin-top: 70px;
            padding: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-5px); }
        
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        
        .stat-number { font-size: 32px; font-weight: bold; color: #11998e; }
        
        .content-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .content-card h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(17,153,142,0.4);
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input[readonly] {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        .form-group input[type="file"] {
            padding: 5px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 85%;
            overflow-y: auto;
        }
        
        .close {
            float: right;
            font-size: 28px;
            cursor: pointer;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .current-image {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .stock-warning {
            color: #dc3545;
            font-weight: bold;
        }
        
        .price-display {
            color: #11998e;
            font-weight: bold;
        }
        
        .info-note {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #0066cc;
        }
        
        .phone-link {
            color: #11998e;
            text-decoration: none;
            font-weight: 500;
        }
        
        .phone-link:hover {
            text-decoration: underline;
        }
        
        /* Styles pour la modification de commande */
        .order-product-item {
            background: #f8f9fa;
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-product-info {
            flex: 2;
        }
        
        .order-product-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .order-product-actions input {
            width: 70px;
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 18px;
            padding: 5px;
        }
        
        .btn-icon.delete { color: #dc3545; }
        .btn-icon.delete:hover { opacity: 0.7; }
        
        .add-product-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Dashboard Gestionnaire</h1>
        <div class="user-info">
            <span>👋 Bonjour, <?php echo htmlspecialchars($_SESSION['manager_nom']); ?></span>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </div>
    
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li class="<?php echo $action == 'dashboard' ? 'active' : ''; ?>">
                <a href="?action=dashboard">📊 Tableau de bord</a>
            </li>
            <li class="<?php echo $action == 'products' ? 'active' : ''; ?>">
                <a href="?action=products">🛍️ Gestion des produits</a>
            </li>
            <li class="<?php echo $action == 'orders' ? 'active' : ''; ?>">
                <a href="?action=orders">📦 Gestion des commandes</a>
            </li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="container">
            <?php if($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- DASHBOARD -->
            <?php if($action == 'dashboard'): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>🛍️ Total produits</h3>
                        <div class="stat-number"><?php echo $stats['total_products']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>📦 Total commandes</h3>
                        <div class="stat-number"><?php echo $stats['total_orders']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>⏳ Commandes en attente</h3>
                        <div class="stat-number"><?php echo $stats['pending_orders']; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>✅ Commandes confirmées</h3>
                        <div class="stat-number"><?php echo $stats['confirmed_orders']; ?></div>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2>📋 Dernières commandes</h2>
                    <?php if(count($commandes) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucune commande pour le moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr><th>ID</th><th>Client</th><th>Téléphone</th><th>Total</th><th>Statut</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach(array_slice($commandes, 0, 5) as $commande): ?>
                                <tr>
                                    <td>#<?php echo $commande['id_commande']; ?></td>
                                    <td><?php echo htmlspecialchars($commande['client_nom']); ?></td>
                                    <td>
                                        <?php if($commande['client_telephone']): ?>
                                            <a href="tel:<?php echo $commande['client_telephone']; ?>" class="phone-link">
                                                📞 <?php echo htmlspecialchars($commande['client_telephone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($commande['prix_totale'], 0, ',', ' '); ?> DH</td>
                                    <td><span class="badge badge-<?php echo $commande['id_status'] <= 2 ? 'warning' : ($commande['id_status'] == 5 ? 'success' : 'info'); ?>"><?php echo $commande['status_nom']; ?></span></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- GESTION DES PRODUITS -->
            <?php if($action == 'products'): ?>
                <div class="content-card">
                    <h2>🛍️ Liste des produits</h2>
                   
                    
                    <?php if(count($produits) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucun produit disponible.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Nom</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($produits as $produit): ?>
                                <tr>
                                    <td><?php echo $produit['id_produit']; ?></td>
                                    <td>
                                        <?php if($produit['image'] && file_exists('../uploads/products/' . $produit['image'])): ?>
                                            <img src="../uploads/products/<?php echo $produit['image']; ?>" class="product-image">
                                        <?php else: ?>
                                            <span style="color: #999;">📷 Aucune</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                                    <td><?php echo $produit['categorie_nom']; ?></td>
                                    <td class="price-display"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</td>
                                    <td class="<?php echo $produit['stock'] <= 5 ? 'stock-warning' : ''; ?>">
                                        <?php echo $produit['stock']; ?>
                                        <?php if($produit['stock'] <= 5): ?> ⚠️<?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button onclick='editProduct(<?php echo json_encode($produit); ?>)' class="btn btn-warning btn-sm">✏️ Modifier</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- GESTION DES COMMANDES -->
            <?php if($action == 'orders'): ?>
                <div class="content-card">
                    <h2>📦 Toutes les commandes</h2>
                    <?php if(count($commandes) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucune commande pour le moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>Adresse livraison</th>
                                    <th>Total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Produits</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($commandes as $commande): ?>
                                <tr>
                                    <td>#<?php echo $commande['id_commande']; ?></td>
                                    <td><?php echo htmlspecialchars($commande['client_nom']); ?></td>
                                    <td>
                                        <?php if($commande['client_telephone']): ?>
                                            <a href="tel:<?php echo $commande['client_telephone']; ?>" class="phone-link">
                                                📞 <?php echo htmlspecialchars($commande['client_telephone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">Non renseigné</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($commande['adresse_livraison'] ?? 'Non spécifiée'); ?></td>
                                    <td><?php echo number_format($commande['prix_totale'], 0, ',', ' '); ?> DH</td>
                                    <td>
                                        <span class="badge badge-<?php echo $commande['id_status'] <= 2 ? 'warning' : ($commande['id_status'] == 5 ? 'success' : 'info'); ?>">
                                            <?php echo $commande['status_nom']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($commande['date_commande'])); ?></td>
                                    <td>
                                        <?php foreach($commande['produits'] as $produit): ?>
                                            <div>• <?php echo htmlspecialchars($produit['produit_nom']); ?> (x<?php echo $produit['quantite']; ?>)</div>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button onclick='editOrder(
                                            <?php echo $commande['id_commande']; ?>,
                                            "<?php echo addslashes($commande['client_nom']); ?>",
                                            "<?php echo addslashes($commande['client_telephone']); ?>",
                                            <?php echo $commande['id_status']; ?>,
                                            "<?php echo addslashes($commande['adresse_livraison'] ?? ''); ?>",
                                            <?php 
                                                $products_json = [];
                                                foreach($commande['produits'] as $p) {
                                                    $stock_check = $db->prepare("SELECT stock FROM Produit WHERE id_produit = :id");
                                                    $stock_check->execute([':id' => $p['id_produit']]);
                                                    $current_stock = $stock_check->fetchColumn();
                                                    $products_json[] = [
                                                        'id_produit' => $p['id_produit'],
                                                        'nom' => $p['produit_nom'],
                                                        'prix' => $p['prix'],
                                                        'quantite' => $p['quantite'],
                                                        'stock_disponible' => $current_stock
                                                    ];
                                                }
                                                echo json_encode($products_json);
                                            ?>
                                        )' class="btn btn-warning btn-sm">✏️ Modifier</button>
                                        
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                                            <select name="id_status" onchange="this.form.submit()" style="padding: 5px; border-radius: 5px;">
                                                <?php foreach($status_list as $status): ?>
                                                    <option value="<?php echo $status['id_status']; ?>" <?php echo $status['id_status'] == $commande['id_status'] ? 'selected' : ''; ?>>
                                                        <?php echo $status['nom']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="hidden" name="update_order_status">
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- MODAL POUR MODIFIER UN PRODUIT -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
            <h2>✏️ Modifier le produit</h2>
            <div class="info-note">
                ⚠️ Le prix ne peut pas être modifié. Contactez l'administrateur pour changer le prix.
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id_produit" id="edit_id">
                
                <div class="form-group">
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" id="edit_nom" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Prix (DH)</label>
                    <input type="text" id="edit_prix_display" readonly style="background: #f5f5f5; color: #11998e; font-weight: bold;">
                    <small style="color: #888;">Le prix est modifiable uniquement par l'administrateur</small>
                </div>
                
                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" id="edit_stock" required>
                </div>
                
                <div class="form-group">
                    <label>Catégorie *</label>
                    <select name="id_categorie" id="edit_categorie" required>
                        <?php foreach($categories as $categorie): ?>
                            <option value="<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Nouvelle image</label>
                    <input type="file" name="image" accept="image/*">
                    <div id="current_image_container" style="margin-top: 10px;"></div>
                </div>
                
                <button type="submit" name="edit_product" class="btn btn-primary">💾 Enregistrer les modifications</button>
            </form>
        </div>
    </div>
    
    <!-- MODAL POUR MODIFIER UNE COMMANDE -->
    <div id="editOrderModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeOrderModal()">&times;</span>
            <h2>✏️ Modifier la commande #<span id="edit_order_id"></span></h2>
            
            <form method="POST" id="editOrderForm">
                <input type="hidden" name="id_commande" id="order_id">
                <input type="hidden" name="update_order_details" value="1">
                
                <div class="form-group">
                    <label>👤 Client</label>
                    <input type="text" id="order_client" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>📞 Téléphone</label>
                    <input type="text" id="order_phone" class="form-control" readonly style="background: #f5f5f5;">
                </div>
                
                <div class="form-group">
                    <label>🏷️ Statut de la commande</label>
                    <select name="id_status" id="order_status" class="form-control" style="width: 100%; padding: 8px;">
                        <?php foreach($status_list as $status): ?>
                            <option value="<?php echo $status['id_status']; ?>"><?php echo htmlspecialchars($status['nom']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>📝 Adresse de livraison</label>
                    <textarea name="adresse_livraison" id="order_address" rows="2" class="form-control" style="width: 100%;"></textarea>
                </div>
                
                <h3 style="margin: 20px 0 15px 0;">🛍️ Produits commandés</h3>
                <div id="order_products_list" style="margin-bottom: 20px;"></div>
                
                <div class="add-product-section">
                    <label style="font-weight: bold;">➕ Ajouter un produit</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                        <select id="add_product_select" style="flex: 2; padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                            <option value="">-- Sélectionner un produit --</option>
                            <?php foreach($all_products as $product): ?>
                                <option value="<?php echo $product['id_produit']; ?>" data-prix="<?php echo $product['prix']; ?>" data-stock="<?php echo $product['stock']; ?>">
                                    <?php echo htmlspecialchars($product['nom']); ?> - <?php echo number_format($product['prix'], 0, ',', ' '); ?> DH (Stock: <?php echo $product['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="add_product_qty" placeholder="Quantité" min="1" style="width: 100px; padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
                        <button type="button" class="btn btn-primary" onclick="addProductToOrder()">➕ Ajouter</button>
                    </div>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: right;">
                    <strong>💰 Total de la commande : </strong>
                    <span id="order_total_display" style="font-size: 20px; color: #11998e;">0 DH</span>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">💾 Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentOrderProducts = [];
        
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id_produit;
            document.getElementById('edit_nom').value = product.nom;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_prix_display').value = new Intl.NumberFormat('fr-FR').format(product.prix) + ' DH';
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_categorie').value = product.id_categorie;
            
            const imageContainer = document.getElementById('current_image_container');
            if(product.image) {
                imageContainer.innerHTML = `
                    <p>Image actuelle :</p>
                    <img src="../uploads/products/${product.image}" class="current-image">
                    <small style="display: block; color: #666;">Laissez vide pour conserver cette image</small>
                `;
            } else {
                imageContainer.innerHTML = '<small style="color: #666;">Aucune image actuellement</small>';
            }
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function editOrder(orderId, clientName, clientPhone, currentStatus, currentAddress, products) {
            document.getElementById('edit_order_id').innerText = orderId;
            document.getElementById('order_id').value = orderId;
            document.getElementById('order_client').value = clientName;
            document.getElementById('order_phone').value = clientPhone || 'Non renseigné';
            document.getElementById('order_status').value = currentStatus;
            document.getElementById('order_address').value = currentAddress || '';
            
            currentOrderProducts = products;
            renderOrderProducts();
            updateOrderTotal();
            
            document.getElementById('editOrderModal').style.display = 'block';
        }
        
        function renderOrderProducts() {
            const container = document.getElementById('order_products_list');
            if (!currentOrderProducts.length) {
                container.innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Aucun produit dans cette commande</p>';
                return;
            }
            
            container.innerHTML = '';
            currentOrderProducts.forEach((product, index) => {
                const productDiv = document.createElement('div');
                productDiv.className = 'order-product-item';
                productDiv.innerHTML = `
                    <div class="order-product-info">
                        <strong>${escapeHtml(product.nom)}</strong><br>
                        <small>Prix unitaire: ${formatNumber(product.prix)} DH | Stock dispo: ${product.stock_disponible}</small>
                        <input type="hidden" name="products[${index}][id_produit]" value="${product.id_produit}">
                        <input type="hidden" name="products[${index}][nom]" value="${escapeHtml(product.nom)}">
                    </div>
                    <div class="order-product-actions">
                        <label>Quantité:</label>
                        <input type="number" name="products[${index}][quantite]" value="${product.quantite}" min="1" max="${product.stock_disponible + product.quantite}" onchange="updateProductQuantity(${index}, this.value)">
                        <span><strong>${formatNumber(product.prix * product.quantite)} DH</strong></span>
                        <button type="button" class="btn-icon delete" onclick="removeProductFromOrder(${index})" title="Supprimer">🗑️</button>
                    </div>
                `;
                container.appendChild(productDiv);
            });
        }
        
        function updateProductQuantity(index, newQuantity) {
            newQuantity = parseInt(newQuantity);
            if (isNaN(newQuantity) || newQuantity < 1) {
                newQuantity = 1;
            }
            const maxStock = currentOrderProducts[index].stock_disponible + currentOrderProducts[index].quantite;
            if (newQuantity > maxStock) {
                alert(`Stock insuffisant. Quantité maximum disponible: ${maxStock}`);
                newQuantity = maxStock;
            }
            currentOrderProducts[index].quantite = newQuantity;
            renderOrderProducts();
            updateOrderTotal();
        }
        
        function removeProductFromOrder(index) {
            if (confirm('Supprimer ce produit de la commande ?')) {
                currentOrderProducts.splice(index, 1);
                renderOrderProducts();
                updateOrderTotal();
            }
        }
        
        function addProductToOrder() {
            const select = document.getElementById('add_product_select');
            const qtyInput = document.getElementById('add_product_qty');
            const productId = select.value;
            const quantity = parseInt(qtyInput.value);
            
            if (!productId) {
                alert('Veuillez sélectionner un produit');
                return;
            }
            if (!quantity || quantity < 1) {
                alert('Veuillez entrer une quantité valide');
                return;
            }
            
            const selectedOption = select.options[select.selectedIndex];
            const productName = selectedOption.text.split(' - ')[0];
            const productPrice = parseFloat(selectedOption.dataset.prix);
            const productStock = parseInt(selectedOption.dataset.stock);
            
            const existingIndex = currentOrderProducts.findIndex(p => p.id_produit == productId);
            if (existingIndex !== -1) {
                const newQty = currentOrderProducts[existingIndex].quantite + quantity;
                if (newQty > productStock + currentOrderProducts[existingIndex].quantite) {
                    alert(`Stock insuffisant. Quantité maximum disponible: ${productStock + currentOrderProducts[existingIndex].quantite}`);
                    return;
                }
                currentOrderProducts[existingIndex].quantite = newQty;
            } else {
                currentOrderProducts.push({
                    id_produit: parseInt(productId),
                    nom: productName,
                    prix: productPrice,
                    quantite: quantity,
                    stock_disponible: productStock
                });
            }
            
            select.value = '';
            qtyInput.value = '';
            renderOrderProducts();
            updateOrderTotal();
        }
        
        function updateOrderTotal() {
            const total = currentOrderProducts.reduce((sum, product) => sum + (product.prix * product.quantite), 0);
            document.getElementById('order_total_display').innerHTML = formatNumber(total) + ' DH';
            
            let totalInput = document.getElementById('order_total_input');
            if (!totalInput) {
                totalInput = document.createElement('input');
                totalInput.type = 'hidden';
                totalInput.name = 'nouveau_total';
                totalInput.id = 'order_total_input';
                document.getElementById('editOrderForm').appendChild(totalInput);
            }
            totalInput.value = total;
        }
        
        function formatNumber(n) {
            return new Intl.NumberFormat('fr-FR').format(Math.round(n));
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function closeOrderModal() {
            document.getElementById('editOrderModal').style.display = 'none';
            document.getElementById('add_product_select').value = '';
            document.getElementById('add_product_qty').value = '';
        }
        
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const orderModal = document.getElementById('editOrderModal');
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
            if (event.target == orderModal) {
                closeOrderModal();
            }
        }
    </script>
</body>
</html>