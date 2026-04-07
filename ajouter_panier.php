<?php
session_start();
require_once 'config/database.php';

class PanierManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function ajouterAuPanier($produit_id, $quantite = 1) {
        // Plus de vérification d'authentification - Tout le monde peut ajouter au panier
        
        // Vérifier produit
        $produit = $this->getProduit($produit_id);
        if(!$produit) {
            return ['success' => false, 'message' => 'Produit introuvable', 'redirect' => 'index.php'];
        }
        
        // Vérifier stock
        if($quantite > $produit['stock']) {
            return ['success' => false, 'message' => 'Stock insuffisant', 'redirect' => 'index.php'];
        }
        
        // Initialiser panier
        if(!isset($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
        }
        if(!isset($_SESSION['panier_produits'])) {
            $_SESSION['panier_produits'] = [];
        }
        
        // Ajouter au panier
        if(isset($_SESSION['panier'][$produit_id])) {
            $nouvelle_quantite = $_SESSION['panier'][$produit_id] + $quantite;
            if($nouvelle_quantite > $produit['stock']) {
                return ['success' => false, 'message' => 'Stock insuffisant pour cette quantité', 'redirect' => 'index.php'];
            }
            $_SESSION['panier'][$produit_id] = $nouvelle_quantite;
        } else {
            $_SESSION['panier'][$produit_id] = $quantite;
        }
        
        // Stocker infos produit
        $_SESSION['panier_produits'][$produit_id] = [
            'nom' => $produit['nom'],
            'prix' => $produit['prix'],
            'image' => $produit['image']
        ];
        
        return [
            'success' => true, 
            'message' => 'Produit ajouté au panier', 
            'cart_count' => array_sum($_SESSION['panier']),
            'redirect' => null
        ];
    }
    
    private function getProduit($id) {
        $query = "SELECT id_produit, nom, prix, stock, image FROM Produit WHERE id_produit = :id AND stock > 0";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }
}

// Traitement de la requête
$manager = new PanierManager();

// Récupérer paramètres
$produit_id = isset($_POST['id_produit']) ? (int)$_POST['id_produit'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$quantite = isset($_POST['quantite']) ? (int)$_POST['quantite'] : 1;
$redirect = isset($_POST['redirect']) ? $_POST['redirect'] : (isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php');

if($produit_id <= 0) {
    header("Location: index.php?error=produit_invalide");
    exit();
}

if($quantite < 1) {
    $quantite = 1;
}

// Ajouter au panier
$resultat = $manager->ajouterAuPanier($produit_id, $quantite);

// Gérer la redirection
if($resultat['redirect']) {
    header("Location: " . $resultat['redirect'] . "?error=" . urlencode($resultat['message']));
    exit();
}

// Stocker le message de confirmation dans la session
if($resultat['success']) {
    $_SESSION['cart_message'] = $resultat['message'];
}

// Redirection avec message de succès
if(isset($_POST['ajax']) || isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode($resultat);
} else {
    header("Location: $redirect");
}
exit();
?>