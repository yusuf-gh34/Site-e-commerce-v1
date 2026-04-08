<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer les catégories
$categories = $db->query("SELECT * FROM Categorie ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Gestion recherche et filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Requête produits
$sql = "SELECT p.*, c.nom as categorie_nom 
        FROM Produit p 
        LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
        WHERE p.stock > 0";

$count_sql = "SELECT COUNT(*) as total FROM Produit p WHERE p.stock > 0";
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

$stmt_count = $db->prepare($count_sql);
foreach($params as $key => $value) {
    $stmt_count->bindValue($key, $value);
}
$stmt_count->execute();
$total_products = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $limit);

$stmt = $db->prepare($sql);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if(isset($_GET['message']) && $_GET['message'] == 'deconnexion') {
    $message = '<div class="toast">✅ Déconnecté</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Boutique</title>
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

        .search-form {
            flex: 1;
            max-width: 400px;
        }

        .search-box {
            display: flex;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .search-box input {
            flex: 1;
            padding: 10px 15px;
            border: none;
            outline: none;
            font-size: 14px;
        }

        .search-box button {
            padding: 10px 20px;
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 500;
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

        /* Style pour le nom cliquable */
        .user-name {
    position: relative;
    color: #2563eb !important;
    font-weight: 700;
    font-size: 14px;
    padding: 6px 12px;
    transition: all 0.3s ease;
    display: inline-block;
}

.user-name::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #2563eb, #10b981);
    transition: width 0.3s ease;
}

.user-name:hover::after {
    width: 80%;
}

.user-name:hover {
    color: #10b981 !important;
    transform: translateY(-2px);
}

        .cart {
    position: relative;
    font-weight: 600;
    padding: 8px 18px;
    border-radius: 25px;
    background: white;
    border: 2px solid #2563eb;
    color: #2563eb !important;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
}

.cart:hover {
    background: #2563eb;
    color: white !important;
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
    transform: translateY(-2px);
}

.cart-count {
    background: #2563eb;
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 12px;
    font-weight: bold;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.cart:hover .cart-count {
    background: white;
    color: #2563eb;
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

        .categories-list a:hover,
        .categories-list a.active {
            background: #2563eb;
            color: white;
        }

        /* Hero */
        .hero {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin: 30px 0;
            text-align: center;
        }

        .hero h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            display: inline-block;
        }

        /* Products grid */
.products {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.product {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    transition: all 0.2s;
    cursor: pointer;
    border: 1px solid #eaeaea;
    display: flex;                /* Ajouté */
    flex-direction: column;       /* Ajouté */
    justify-content: space-between; /* Ajouté */
}

.product:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.product-image {
    height: 200px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-info {
    padding: 15px;
    display: flex;                /* Ajouté */
    flex-direction: column;       /* Ajouté */
    flex-grow: 1;                 /* Ajouté */
}

.product-category {
    font-size: 12px;
    color: #2563eb;
    margin-bottom: 5px;
}

.product-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 8px;
}

.product-price {
    font-size: 20px;
    font-weight: 700;
    color: #2563eb;
    margin: 10px 0;
}

.product-stock {
    font-size: 12px;
    color: #10b981;
    margin-bottom: 12px;
}

.product-stock.low {
    color: #f59e0b;
}

.product-info form {
    margin-top: auto;             /* Ajouté pour pousser le bouton en bas */
}

.btn-add {
    width: 100%;
    padding: 10px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.2s;
}

.btn-add:hover {
    background: #1e40af;
}

.btn-add:disabled {
    background: #ccc;
    cursor: not-allowed;
}


        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 40px 0;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover, .pagination .active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }

        .empty {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 12px;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: #666;
            font-size: 13px;
            border-top: 1px solid #eaeaea;
            margin-top: 40px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .footer {
    background: #202e4d;
    border-top: 1px solid #eaeaea;
    margin-top: 60px;
    padding: 40px 0 20px;
}

.footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid #eaeaea;
}

.footer-col h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #f9fafb;
}

.footer-col ul {
    list-style: none;
    padding: 0;
}

.footer-col ul li {
    margin-bottom: 10px;
}

.footer-col ul li a {
    color: #9ca3af;
    text-decoration: none;
    font-size: 13px;
    transition: color 0.2s;
}

.footer-col ul li a:hover {
    color: #2563eb;
}

.footer-bottom {
    text-align: center;
}

.footer-links {
    display: flex;
    justify-content: center;
    gap: 25px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.footer-links a {
    color: #666;
    text-decoration: none;
    font-size: 12px;
}

.footer-links a:hover {
    color: #2563eb;
}

.copyright {
    color: #888;
    font-size: 12px;
}

@media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 20px;
    }
    
    .footer-links {
        gap: 15px;
        flex-direction: column;
    }
}

        /* Modal original */
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
            color: #2563eb;
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

        .btn-add-modal {
            background: #2563eb;
            color: white;
        }

        .btn-close-modal {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            .header-inner {
                flex-direction: column;
            }
            
            .search-form {
                max-width: 100%;
                width: 100%;
            }
            
            .hero h2 {
                font-size: 22px;
            }
            
            .modal-body {
                flex-direction: column;
            }
            
            .footer-links {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <?php echo $message; ?>

    <div class="header">
        <div class="container">
            <div class="header-inner">
                <div class="logo">
                    <a href="index.php">Ma<span>Boutique</span></a>
                </div>
                
                <form class="search-form" method="GET" action="">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                        <?php if($categorie_id > 0): ?>
                            <input type="hidden" name="categorie" value="<?php echo $categorie_id; ?>">
                        <?php endif; ?>
                        <button type="submit">OK</button>
                    </div>
                </form>
                
                <div class="user-links">
                    <?php if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true): ?>
                        <a href="profil.php" class="user-name">
                            👋 <?php echo htmlspecialchars($_SESSION['client_nom']); ?>
                        </a>
                        <a href="mes_commandes.php">Commandes</a>
                        <a href="contact.php">Contact</a>
                        <a href="logout_client.php" onclick="return confirm('Déconnexion ?')">Déconnexion</a>
                    <?php else: ?>
                        <a href="login_client.php">Connexion</a>
                        <a href="register.php">Inscription</a>
                        <a href="contact.php">Contact</a>
                    <?php endif; ?>
                    <a href="panier.php" class="cart">
                        🛒 Panier
                        <span class="cart-count"><?php echo isset($_SESSION['panier']) ? array_sum($_SESSION['panier']) : 0; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="categories">
        <div class="container">
            <div class="categories-list">
                <a href="index.php" class="<?php echo $categorie_id == 0 ? 'active' : ''; ?>">Tous</a>
                <?php foreach($categories as $cat): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['categorie' => $cat['id_categorie'], 'page' => 1])); ?>" 
                       class="<?php echo $categorie_id == $cat['id_categorie'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat['nom']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if(empty($search) && $categorie_id == 0): ?>
        <div class="hero">
            <h2>Bienvenue</h2>
            <p>Les meilleurs produits aux meilleurs prix</p>
        </div>
        <?php endif; ?>

        <div>
            <h2 class="section-title">
                <?php if(!empty($search)): ?>
                    🔍 <?php echo htmlspecialchars($search); ?>
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
                    🛍️ Nos produits
                <?php endif; ?>
                <span style="font-size: 14px; font-weight: normal; color: #888;">(<?php echo $total_products; ?>)</span>
            </h2>
        </div>

        <?php if(count($produits) > 0): ?>
        <div class="products">
            <?php foreach($produits as $produit): ?>
            <div class="product" onclick="showProductDetails(<?php echo htmlspecialchars(json_encode($produit)); ?>)">
                <div class="product-image">
                    <?php if(!empty($produit['image']) && file_exists('uploads/products/' . $produit['image'])): ?>
                        <img src="uploads/products/<?php echo $produit['image']; ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>">
                    <?php else: ?>
                        📦
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <div class="product-category"><?php echo $produit['categorie_nom']; ?></div>
                    <div class="product-title"><?php echo htmlspecialchars($produit['nom']); ?></div>
                    <div class="product-price"><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</div>
                    <div class="product-stock <?php echo $produit['stock'] <= 5 ? 'low' : ''; ?>">
                        <?php if($produit['stock'] > 0): ?>
                            ✅ Stock: <?php echo $produit['stock']; ?>
                        <?php else: ?>
                            ❌ Rupture
                        <?php endif; ?>
                    </div>
                    <form method="POST" action="ajouter_panier.php" onclick="event.stopPropagation()">
                        <input type="hidden" name="id_produit" value="<?php echo $produit['id_produit']; ?>">
                        <button type="submit" class="btn-add" <?php echo $produit['stock'] <= 0 ? 'disabled' : ''; ?>>
                            <?php echo $produit['stock'] > 0 ? 'Ajouter au panier' : 'Indisponible'; ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">◀ Préc.</a>
            <?php endif; ?>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Suiv. ▶</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty">
            <p>😕 Aucun produit trouvé</p>
            <a href="index.php" style="color: #2563eb;">Voir tous les produits</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h3>🚀 Découvrez</h3>
                <ul>
                    <li><a href="a_propos.php">À propos de nous</a></li>
                    <li><a href="blog.php">Blog</a></li>
                    <li><a href="livraison.php">Livraison & retours</a></li>
                    <li><a href="contact.php">Contactez-nous</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h3>💰 Gagnez de l'argent</h3>
                <ul>
                    <li><a href="vendre.php">Vendez vos produits</a></li>
                    <li><a href="partenaire.php">Devenez partenaire</a></li>
                    <li><a href="affiliation.php">Programme d'affiliation</a></li>
                    <li><a href="publicite.php">Publicité sur MaBoutique</a></li>
                </ul>
            </div>
            
            
            
            <div class="footer-col">
                <h3>🛒 Votre compte</h3>
                <ul>
                    <?php if(isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true): ?>
                        <li><a href="profil.php">Mon profil</a></li>
                        <li><a href="mes_commandes.php">Mes commandes</a></li>
                        <li><a href="panier.php">Mon panier</a></li>
                        <li><a href="favoris.php">Mes favoris</a></li>
                        <li><a href="logout_client.php" onclick="return confirm('Déconnexion ?')">Déconnexion</a></li>
                    <?php else: ?>
                        <li><a href="login_client.php">Connexion</a></li>
                        <li><a href="register.php">Créer un compte</a></li>
                        <li><a href="mot_de_passe_oublie.php">Mot de passe oublié ?</a></li>
                        <li><a href="newsletter.php">Newsletter</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-links">
                <a href="conditions.php">Conditions générales</a>
                <a href="confidentialite.php">Politique de confidentialité</a>
                <a href="cookies.php">Gestion des cookies</a>
                <a href="mentions.php">Mentions légales</a>
            </div>
            <p class="copyright">&copy; <?php echo date('Y'); ?> Ma Boutique - Tous droits réservés</p>
        </div>
    </div>
</div>


    <!-- Modal original avec la méthode showProductDetails -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-body" id="modalBody">
                <!-- Contenu dynamique -->
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
                            <button type="submit" class="btn-modal btn-add-modal" ${product.stock <= 0 ? 'disabled' : ''}>
                                ${product.stock > 0 ? '🛒 Ajouter au panier' : '❌ Rupture de stock'}
                            </button>
                        </form>
                        <button type="button" class="btn-modal btn-close-modal" onclick="closeModal()">Fermer</button>
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
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
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