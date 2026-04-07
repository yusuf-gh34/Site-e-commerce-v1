<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les catégories pour le menu
$categories = $db->query("SELECT * FROM Categorie ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Gestion de la recherche et des filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Construction de la requête des produits
$sql = "SELECT p.*, c.nom as categorie_nom 
        FROM Produit p 
        LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
        WHERE p.stock > 0";

$count_sql = "SELECT COUNT(*) as total 
              FROM Produit p 
              WHERE p.stock > 0";

$params = [];

if(!empty($search)) {
    $sql .= " AND (p.nom LIKE :search OR p.description LIKE :search)";
    $count_sql .= " AND (p.nom LIKE :search OR p.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if($categorie_id > 0) {
    $sql .= " AND p.id_categorie = :categorie_id";
    $count_sql .= " AND p.id_categorie = :categorie_id";
    $params[':categorie_id'] = $categorie_id;
}

$sql .= " ORDER BY p.id_produit DESC LIMIT :limit OFFSET :offset";

// Compter le nombre total de produits
$stmt_count = $db->prepare($count_sql);
foreach($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_products = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $limit);

// Récupérer les produits
$stmt = $db->prepare($sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Afficher le message de déconnexion si présent
$message = '';
if(isset($_GET['message']) && $_GET['message'] == 'deconnexion') {
    $message = '<div class="alert-success" style="position: fixed; top: 100px; right: 20px; z-index: 9999; background: #28a745; color: white; padding: 12px 20px; border-radius: 5px; animation: fadeOut 3s forwards;">
                    ✅ Vous avez été déconnecté avec succès
                </div>
                <style>
                    @keyframes fadeOut {
                        0% { opacity: 1; }
                        70% { opacity: 1; }
                        100% { opacity: 0; visibility: hidden; }
                    }
                </style>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boutique - Système de Gestion</title>
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

        .search-bar {
            flex: 1;
            max-width: 500px;
            display: flex;
        }

        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 5px 0 0 5px;
            font-size: 14px;
        }

        .search-bar button {
            padding: 10px 20px;
            background: #ffc107;
            border: none;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            font-weight: bold;
        }

        /* Boutons utilisateur */
        .user-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .login-btn, .register-btn, .dashboard-btn, .logout-btn, .profile-btn, .orders-btn {
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,0.15);
            padding: 5px 15px;
            border-radius: 30px;
        }

        .user-info span {
            font-size: 14px;
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
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        /* Hero section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .hero h2 {
            font-size: 36px;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 18px;
            opacity: 0.9;
        }

        /* Section titles */
        .section-title {
            font-size: 28px;
            margin-bottom: 25px;
            color: #333;
            border-left: 4px solid #667eea;
            padding-left: 15px;
        }

        /* Products grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-image {
            height: 200px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 15px;
        }

        .product-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .product-category {
            font-size: 12px;
            color: #888;
            margin-bottom: 10px;
        }

        .product-price {
            font-size: 22px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .product-stock {
            font-size: 12px;
            color: #28a745;
            margin-bottom: 15px;
        }

        .product-stock.low {
            color: #dc3545;
        }

        .btn-add-cart {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }

        .btn-add-cart:hover {
            transform: scale(1.02);
        }

        .btn-add-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 10px 15px;
            background: white;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .pagination a:hover, .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 30px;
            margin-top: 50px;
        }

        /* Message no results */
        .no-results {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
        }

        .no-results a {
            color: #667eea;
            text-decoration: none;
        }

        /* Modal pour détails produit */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            overflow: auto;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            max-height: 85%;
            overflow-y: auto;
            animation: modalFade 0.3s;
        }

        @keyframes modalFade {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #888;
            padding: 10px 20px;
        }

        .close:hover {
            color: #333;
        }

        .modal-body {
            display: flex;
            flex-wrap: wrap;
            padding: 20px;
            gap: 30px;
        }

        .modal-image {
            flex: 1;
            min-width: 250px;
        }

        .modal-image img {
            width: 100%;
            border-radius: 10px;
            object-fit: cover;
        }

        .modal-image .no-image {
            width: 100%;
            height: 250px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            border-radius: 10px;
        }

        .modal-details {
            flex: 1;
        }

        .modal-details h2 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }

        .modal-category {
            color: #888;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .modal-price {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
        }

        .modal-stock {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 5px;
        }

        .modal-stock.in-stock {
            background: #d4edda;
            color: #155724;
        }

        .modal-stock.low-stock {
            background: #fff3cd;
            color: #856404;
        }

        .modal-stock.out-stock {
            background: #f8d7da;
            color: #721c24;
        }

        .modal-description {
            margin-bottom: 20px;
            line-height: 1.6;
            color: #555;
        }

        .modal-description h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-modal {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }

        .btn-modal:hover {
            transform: scale(1.02);
        }

        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-close {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
            }
            
            .search-bar {
                max-width: 100%;
                width: 100%;
            }
            
            .hero h2 {
                font-size: 24px;
            }
            
            .hero p {
                font-size: 14px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }

            .modal-body {
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
    <?php echo $message; ?>

    <div class="header">
        <div class="header-content">
            <div class="logo">
                <h1>Ma Boutique</h1>
                <p>Qualité et satisfaction garanties</p>
            </div>
            <form class="search-bar" method="GET" action="">
                <input type="text" name="search" placeholder="Rechercher un produit..." value="<?php echo htmlspecialchars($search); ?>">
                <?php if($categorie_id > 0): ?>
                    <input type="hidden" name="categorie" value="<?php echo $categorie_id; ?>">
                <?php endif; ?>
                <button type="submit">Rechercher</button>
            </form>
            <div class="user-actions">
    <?php if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true): ?>
        <div class="user-info">
            <span>👋 Bonjour, <?php echo htmlspecialchars($_SESSION['client_nom']); ?></span>
        </div>
        <a href="profil.php" class="profile-btn">👤 Profil</a>
        <a href="mes_commandes.php" class="orders-btn">📦 Mes commandes</a>
        <a href="contact.php" class="contact-btn" style="background: #20c997; color: white; padding: 8px 18px; border-radius: 5px; text-decoration: none;">📧 Contact</a>
        <a href="logout_client.php" class="logout-btn" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">🔓 Déconnexion</a>
    <?php else: ?>
        <a href="login_client.php" class="login-btn">🔐 Connexion</a>
        <a href="register.php" class="register-btn">📝 Inscription</a>
        <a href="contact.php" class="contact-btn" style="background: #20c997; color: white; padding: 8px 18px; border-radius: 5px; text-decoration: none;">📧 Contact</a>
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
            <a href="index.php" class="<?php echo $categorie_id == 0 ? 'active' : ''; ?>">Tous les produits</a>
            <?php foreach($categories as $cat): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['categorie' => $cat['id_categorie'], 'page' => 1])); ?>" 
                   class="<?php echo $categorie_id == $cat['id_categorie'] ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($cat['nom']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="main-content">
        <!-- Hero section (cachée si recherche ou filtre) -->
        <?php if(empty($search) && $categorie_id == 0): ?>
        <div class="hero">
            <h2>Bienvenue sur notre boutique</h2>
            <p>Découvrez nos produits de qualité aux meilleurs prix</p>
        </div>
        <?php endif; ?>

        <!-- Titre pour la liste des produits -->
        <div class="section-title">
            <?php if(!empty($search)): ?>
                Résultats pour "<?php echo htmlspecialchars($search); ?>"
            <?php elseif($categorie_id > 0): ?>
                <?php 
                    $cat_nom = '';
                    foreach($categories as $cat) {
                        if($cat['id_categorie'] == $categorie_id) {
                            $cat_nom = $cat['nom'];
                            break;
                        }
                    }
                    echo $cat_nom;
                ?>
            <?php else: ?>
                Tous nos produits
            <?php endif; ?>
            <span style="font-size: 14px; color: #888;">(<?php echo $total_products; ?> produits)</span>
        </div>

        <!-- Liste des produits -->
        <?php if(count($produits) > 0): ?>
        <div class="products-grid">
            <?php foreach($produits as $produit): ?>
            <div class="product-card" onclick="showProductDetails(<?php echo htmlspecialchars(json_encode($produit)); ?>)">
                <div class="product-image">
                    <?php if(!empty($produit['image']) && file_exists('uploads/products/' . $produit['image'])): ?>
                        <img src="uploads/products/<?php echo $produit['image']; ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <?php else: ?>
                        📦
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-title"><?php echo htmlspecialchars($produit['nom']); ?></div>
                    <div class="product-category"><?php echo $produit['categorie_nom']; ?></div>
                    <div class="product-price"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</div>
                    <div class="product-stock <?php echo $produit['stock'] <= 5 ? 'low' : ''; ?>">
                        Stock: <?php echo $produit['stock']; ?>
                        <?php if($produit['stock'] <= 5): ?>
                            ⚠️ 
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="ajouter_panier.php" onclick="event.stopPropagation()">
                        <input type="hidden" name="id_produit" value="<?php echo $produit['id_produit']; ?>">
                        <button type="submit" class="btn-add-cart" <?php echo $produit['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <?php echo $produit['stock'] > 0 ? 'Ajouter au panier' : 'Rupture de stock'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Précédent</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Suivant »</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-results">
            <p>Aucun produit trouvé.</p>
            <a href="index.php">Voir tous les produits</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
    </div>

    <!-- Modal pour les détails du produit -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-body" id="modalBody">
                <!-- Contenu dynamique chargé par JavaScript -->
            </div>
        </div>
    </div>

    <script>
        let currentProduct = null;

        function showProductDetails(product) {
            currentProduct = product;
            
            const imageHtml = product.image && product.image !== '' 
                ? `<img src="uploads/products/${product.image}" alt="${escapeHtml(product.nom)}" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'>📦</div>'">`
                : `<div class="no-image">📦</div>`;
            
            let stockClass = '';
            let stockText = '';
            if(product.stock <= 0) {
                stockClass = 'out-stock';
                stockText = 'Rupture de stock';
            } else if(product.stock <= 5) {
                stockClass = 'low-stock';
                stockText = `Stock faible ${product.stock} exemplaires`;
            } else {
                stockClass = 'in-stock';
                stockText = `En stock: ${product.stock} exemplaires`;
            }
            
            const modalHtml = `
                <div class="modal-image">
                    ${imageHtml}
                </div>
                <div class="modal-details">
                    <h2>${escapeHtml(product.nom)}</h2>
                    <div class="modal-category">Catégorie: ${escapeHtml(product.categorie_nom)}</div>
                    <div class="modal-price">${formatNumber(product.prix)} DH</div>
                    <div class="modal-stock ${stockClass}">
                        📦 ${stockText}
                    </div>
                    <div class="modal-description">
                        <h4>Description</h4>
                        <p>${product.description && product.description !== '' ? escapeHtml(product.description) : 'Aucune description disponible pour ce produit.'}</p>
                    </div>
                    <div class="modal-actions">
                        <form method="POST" action="ajouter_panier.php" style="flex: 1;">
                            <input type="hidden" name="id_produit" value="${product.id_produit}">
                            <button type="submit" class="btn-modal btn-add" ${product.stock <= 0 ? 'disabled' : ''}>
                                ${product.stock > 0 ? '🛒 Ajouter au panier' : '❌ Rupture de stock'}
                            </button>
                        </form>
                        <button type="button" class="btn-modal btn-close" onclick="closeModal()">Fermer</button>
                    </div>
                </div>
            `;
            
            document.getElementById('modalBody').innerHTML = modalHtml;
            document.getElementById('productModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        function escapeHtml(text) {
            if(!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatNumber(number) {
            return new Intl.NumberFormat('fr-FR').format(number);
        }

        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>