<?php
session_start();
require_once '../config/database.php';

// Vérifier si l'admin est connecté
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Gestion des actions
$action = isset($_GET['action']) ? $_GET['action'] : 'dashboard';
$success_message = '';
$error_message = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // === GESTION DES PRODUITS ===
    
    // Ajouter un produit
    if (isset($_POST['add_product'])) {
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        $prix = $_POST['prix'];
        $stock = $_POST['stock'];
        $id_categorie = $_POST['id_categorie'];
        
        // Gestion de l'upload d'image
        $image_name = null;
        if(isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if(in_array($ext, $allowed)) {
                $image_name = time() . '_' . uniqid() . '.' . $ext;
                $upload_path = '../uploads/products/' . $image_name;
                
                if(!is_dir('../uploads/products/')) {
                    mkdir('../uploads/products/', 0777, true);
                }
                
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
            } else {
                $error_message = "Format d'image non autorisé. Formats acceptés: jpg, jpeg, png, gif, webp";
            }
        }
        
        if(empty($error_message)) {
            $query = "INSERT INTO Produit (nom, description, prix, stock, image, id_categorie) 
                      VALUES (:nom, :description, :prix, :stock, :image, :id_categorie)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':prix' => $prix,
                ':stock' => $stock,
                ':image' => $image_name,
                ':id_categorie' => $id_categorie
            ]);
            $success_message = "Produit ajouté avec succès !";
        }
    }
    
    // Modifier un produit
    if (isset($_POST['edit_product'])) {
        $id_produit = $_POST['id_produit'];
        $nom = $_POST['nom'];
        $description = $_POST['description'];
        $prix = $_POST['prix'];
        $stock = $_POST['stock'];
        $id_categorie = $_POST['id_categorie'];
        
        // Récupérer l'image actuelle
        $query_img = "SELECT image FROM Produit WHERE id_produit = :id_produit";
        $stmt_img = $db->prepare($query_img);
        $stmt_img->execute([':id_produit' => $id_produit]);
        $current_image = $stmt_img->fetch(PDO::FETCH_ASSOC)['image'] ?? null;
        $image_name = $current_image;
        
        // Gestion de l'upload d'image
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
                move_uploaded_file($_FILES['image']['tmp_name'], $upload_path);
            } else {
                $error_message = "Format d'image non autorisé.";
            }
        }
        
        if(empty($error_message)) {
            $query = "UPDATE Produit SET nom = :nom, description = :description, prix = :prix, 
                      stock = :stock, image = :image, id_categorie = :id_categorie 
                      WHERE id_produit = :id_produit";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':description' => $description,
                ':prix' => $prix,
                ':stock' => $stock,
                ':image' => $image_name,
                ':id_categorie' => $id_categorie,
                ':id_produit' => $id_produit
            ]);
            $success_message = "Produit modifié avec succès !";
        }
    }
    
    // === GESTION DES COMMANDES ===
    
    // Modifier le statut d'une commande (simple)
    if (isset($_POST['update_order_status'])) {
        $id_commande = $_POST['id_commande'];
        $id_status = $_POST['id_status'];
        
        $query = "UPDATE Commande SET id_status = :id_status WHERE id_commande = :id_commande";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_status' => $id_status,
            ':id_commande' => $id_commande
        ]);
        
        // Si le statut est "livrée" (id_status = 5), mettre à jour le règlement
        if($id_status == 5) {
            $query_reglement = "UPDATE Reglement SET paye_a_livraison = 1 WHERE id_commande = :id_commande";
            $stmt_reglement = $db->prepare($query_reglement);
            $stmt_reglement->execute([':id_commande' => $id_commande]);
        }
        
        $success_message = "Statut de la commande mis à jour avec succès !";
    }
    
    // Modification complète d'une commande (produits, quantités, etc.)
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
                
                // Vérifier le stock
                $check_stock = $db->prepare("SELECT stock FROM Produit WHERE id_produit = :id_produit");
                $check_stock->execute([':id_produit' => $id_produit]);
                $stock_dispo = $check_stock->fetchColumn();
                
                if ($quantite > $stock_dispo) {
                    throw new Exception("Stock insuffisant pour le produit ID: $id_produit. Disponible: $stock_dispo");
                }
                
                // Insérer la ligne de commande
                $insert = $db->prepare("INSERT INTO Ligne_commande (id_commande, id_produit, quantite, prix_unitaire) 
                                         VALUES (:id_commande, :id_produit, :quantite, 
                                         (SELECT prix FROM Produit WHERE id_produit = :id_produit2))");
                $insert->execute([
                    ':id_commande' => $id_commande,
                    ':id_produit' => $id_produit,
                    ':quantite' => $quantite,
                    ':id_produit2' => $id_produit
                ]);
                
                // Mettre à jour le stock
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
            
            // 6. Si le statut est "livrée", mettre à jour le règlement
            if($id_status == 5) {
                $query_reglement = "UPDATE Reglement SET paye_a_livraison = 1 WHERE id_commande = :id_commande";
                $stmt_reglement = $db->prepare($query_reglement);
                $stmt_reglement->execute([':id_commande' => $id_commande]);
            }
            
            $db->commit();
            $success_message = "Commande modifiée avec succès !";
            
            // Rediriger pour rafraîchir la page
            header("Location: dashboard.php?action=orders&success=1");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error_message = "Erreur lors de la modification: " . $e->getMessage();
        }
    }
    
    // === GESTION DES GESTIONNAIRES ===
    
    // Ajouter un gestionnaire
    if (isset($_POST['add_manager'])) {
        $telephone = $_POST['telephone'];
        $nom = $_POST['nom'];
        $adresse = $_POST['adresse'];
        $email = !empty($_POST['email']) ? $_POST['email'] : null;
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role_id = 2;
        
        if (!empty($email)) {
            $check_query = "SELECT COUNT(*) FROM Utilisateur WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->execute([':email' => $email]);
            if ($check_stmt->fetchColumn() > 0) {
                $error_message = "Cet email est déjà utilisé !";
            } else {
                $query = "INSERT INTO Utilisateur (telephone, nom, adresse, email, password, role_id) 
                          VALUES (:telephone, :nom, :adresse, :email, :password, :role_id)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':telephone' => $telephone,
                    ':nom' => $nom,
                    ':adresse' => $adresse,
                    ':email' => $email,
                    ':password' => $password,
                    ':role_id' => $role_id
                ]);
                $success_message = "Gestionnaire ajouté avec succès !";
            }
        } else {
            $query = "INSERT INTO Utilisateur (telephone, nom, adresse, email, password, role_id) 
                      VALUES (:telephone, :nom, :adresse, :email, :password, :role_id)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':telephone' => $telephone,
                ':nom' => $nom,
                ':adresse' => $adresse,
                ':email' => null,
                ':password' => $password,
                ':role_id' => $role_id
            ]);
            $success_message = "Gestionnaire ajouté avec succès !";
        }
    }
    
    // === GESTION DES CATEGORIES ===
    
    // Ajouter une catégorie
    if (isset($_POST['add_category'])) {
        $nom = $_POST['nom'];
        
        $query = "INSERT INTO Categorie (nom) VALUES (:nom)";
        $stmt = $db->prepare($query);
        $stmt->execute([':nom' => $nom]);
        $success_message = "Catégorie ajoutée avec succès !";
        header("Location: dashboard.php?action=categories");
        exit();
    }
    
    // Modifier une catégorie
    if (isset($_POST['edit_category'])) {
        $id_categorie = $_POST['id_categorie'];
        $nom = $_POST['nom'];
        
        $query = "UPDATE Categorie SET nom = :nom WHERE id_categorie = :id_categorie";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nom' => $nom,
            ':id_categorie' => $id_categorie
        ]);
        $success_message = "Catégorie modifiée avec succès !";
        header("Location: dashboard.php?action=categories");
        exit();
    }
    
    // === GESTION DES STATUS ===
    
    // Ajouter un statut
    if (isset($_POST['add_status'])) {
        $nom = $_POST['nom'];
        
        $query = "INSERT INTO Status (nom) VALUES (:nom)";
        $stmt = $db->prepare($query);
        $stmt->execute([':nom' => $nom]);
        $success_message = "Statut ajouté avec succès !";
        header("Location: dashboard.php?action=statuses");
        exit();
    }
    
    // Modifier un statut
    if (isset($_POST['edit_status'])) {
        $id_status = $_POST['id_status'];
        $nom = $_POST['nom'];
        
        $query = "UPDATE Status SET nom = :nom WHERE id_status = :id_status";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nom' => $nom,
            ':id_status' => $id_status
        ]);
        $success_message = "Statut modifié avec succès !";
        header("Location: dashboard.php?action=statuses");
        exit();
    }
    
    // === GESTION DES MESSAGES DE CONTACT ===
    
    // Mettre à jour le statut d'un message
    if (isset($_POST['update_message_status'])) {
        $id_contact = $_POST['id_contact'];
        $statut = $_POST['statut'];
        
        $query = "UPDATE contact SET statut = :statut WHERE id_contact = :id_contact";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':statut' => $statut,
            ':id_contact' => $id_contact
        ]);
        $success_message = "Statut du message mis à jour !";
    }
    
    // Supprimer un message
    if (isset($_POST['delete_message'])) {
        $id_contact = $_POST['id_contact'];
        $query = "DELETE FROM contact WHERE id_contact = :id_contact";
        $stmt = $db->prepare($query);
        $stmt->execute([':id_contact' => $id_contact]);
        $success_message = "Message supprimé avec succès !";
    }
    
    // Marquer tous comme lus
    if (isset($_POST['mark_all_read'])) {
        $query = "UPDATE contact SET statut = 'lu' WHERE statut = 'non lu'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $success_message = "Tous les messages ont été marqués comme lus !";
        header("Location: dashboard.php?action=messages");
        exit();
    }
}

// Suppressions
if (isset($_GET['delete_product'])) {
    $id_produit = $_GET['delete_product'];
    
    // Récupérer l'image avant suppression
    $query_img = "SELECT image FROM Produit WHERE id_produit = :id_produit";
    $stmt_img = $db->prepare($query_img);
    $stmt_img->execute([':id_produit' => $id_produit]);
    $product = $stmt_img->fetch(PDO::FETCH_ASSOC);
    
    if($product && $product['image'] && file_exists('../uploads/products/' . $product['image'])) {
        unlink('../uploads/products/' . $product['image']);
    }
    
    $query = "DELETE FROM Produit WHERE id_produit = :id_produit";
    $stmt = $db->prepare($query);
    $stmt->execute([':id_produit' => $id_produit]);
    $success_message = "Produit supprimé avec succès !";
}

if (isset($_GET['delete_manager'])) {
    $id_utilisateur = $_GET['delete_manager'];
    $query = "DELETE FROM Utilisateur WHERE id_utilisateur = :id_utilisateur AND role_id = 2";
    $stmt = $db->prepare($query);
    $stmt->execute([':id_utilisateur' => $id_utilisateur]);
    $success_message = "Gestionnaire supprimé avec succès !";
}

if (isset($_GET['delete_category'])) {
    $id_categorie = $_GET['delete_category'];
    
    // Vérifier si la catégorie est utilisée
    $check = $db->prepare("SELECT COUNT(*) FROM Produit WHERE id_categorie = :id_categorie");
    $check->execute([':id_categorie' => $id_categorie]);
    if ($check->fetchColumn() > 0) {
        $error_message = "Impossible de supprimer cette catégorie car elle est utilisée par des produits.";
        header("Location: dashboard.php?action=categories&error=1");
        exit();
    } else {
        $query = "DELETE FROM Categorie WHERE id_categorie = :id_categorie";
        $stmt = $db->prepare($query);
        $stmt->execute([':id_categorie' => $id_categorie]);
        $success_message = "Catégorie supprimée avec succès !";
        header("Location: dashboard.php?action=categories");
        exit();
    }
}

if (isset($_GET['delete_status'])) {
    $id_status = $_GET['delete_status'];
    
    // Vérifier si le statut est utilisé
    $check = $db->prepare("SELECT COUNT(*) FROM Commande WHERE id_status = :id_status");
    $check->execute([':id_status' => $id_status]);
    if ($check->fetchColumn() > 0) {
        $error_message = "Impossible de supprimer ce statut car il est utilisé par des commandes.";
        header("Location: dashboard.php?action=statuses&error=1");
        exit();
    } else {
        $query = "DELETE FROM Status WHERE id_status = :id_status";
        $stmt = $db->prepare($query);
        $stmt->execute([':id_status' => $id_status]);
        $success_message = "Statut supprimé avec succès !";
        header("Location: dashboard.php?action=statuses");
        exit();
    }
}

if (isset($_GET['delete_contact'])) {
    $id_contact = $_GET['delete_contact'];
    $query = "DELETE FROM contact WHERE id_contact = :id_contact";
    $stmt = $db->prepare($query);
    $stmt->execute([':id_contact' => $id_contact]);
    $success_message = "Message supprimé avec succès !";
    header("Location: dashboard.php?action=messages");
    exit();
}

// Marquer un message comme lu
if (isset($_GET['mark_as_read'])) {
    $id_contact = $_GET['mark_as_read'];
    $query = "UPDATE contact SET statut = 'lu' WHERE id_contact = :id_contact";
    $stmt = $db->prepare($query);
    $stmt->execute([':id_contact' => $id_contact]);
    header("Location: dashboard.php?action=messages&view=" . $id_contact);
    exit();
}

// Statistiques
$stats = [];
$query = "SELECT COUNT(*) as total FROM Utilisateur WHERE role_id = 3";
$stmt = $db->query($query);
$stats['total_clients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Utilisateur WHERE role_id = 2";
$stmt = $db->query($query);
$stats['total_managers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Produit";
$stmt = $db->query($query);
$stats['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM Commande";
$stmt = $db->query($query);
$stats['total_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT SUM(prix_totale) as total FROM Commande WHERE id_status = 5";
$stmt = $db->query($query);
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Statistiques des messages
$query = "SELECT COUNT(*) as total FROM contact";
$stmt = $db->query($query);
$stats['total_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM contact WHERE statut = 'non lu'";
$stmt = $db->query($query);
$stats['unread_messages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Récupération des données
$produits = $db->query("
    SELECT p.*, c.nom as categorie_nom 
    FROM Produit p 
    LEFT JOIN Categorie c ON p.id_categorie = c.id_categorie 
    ORDER BY p.id_produit DESC
")->fetchAll(PDO::FETCH_ASSOC);

$categories = $db->query("SELECT * FROM Categorie ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

$commandes = $db->query("
    SELECT c.*, u.nom as client_nom, u.telephone as client_telephone, s.nom as status_nom 
    FROM Commande c 
    JOIN Utilisateur u ON c.id_utilisateur = u.id_utilisateur 
    JOIN Status s ON c.id_status = s.id_status 
    ORDER BY c.date_commande DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Détails des produits pour chaque commande
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

$clients = $db->query("SELECT * FROM Utilisateur WHERE role_id = 3 ORDER BY date_creation DESC")->fetchAll(PDO::FETCH_ASSOC);

$gestionnaires = $db->query("
    SELECT * FROM Utilisateur WHERE role_id = 2 ORDER BY date_creation DESC
")->fetchAll(PDO::FETCH_ASSOC);

$status_list = $db->query("SELECT * FROM Status ORDER BY id_status")->fetchAll(PDO::FETCH_ASSOC);

$roles = $db->query("SELECT * FROM Role ORDER BY id_role")->fetchAll(PDO::FETCH_ASSOC);

// Récupération des messages de contact
$messages = $db->query("SELECT * FROM contact ORDER BY date_envoi DESC")->fetchAll(PDO::FETCH_ASSOC);

// Récupération d'un message spécifique pour l'affichage détaillé
$view_message_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$view_message = null;
if ($view_message_id > 0) {
    $stmt = $db->prepare("SELECT * FROM contact WHERE id_contact = :id_contact");
    $stmt->execute([':id_contact' => $view_message_id]);
    $view_message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Marquer automatiquement comme lu si ce n'est pas déjà fait
    if ($view_message && $view_message['statut'] == 'non lu') {
        $update = $db->prepare("UPDATE contact SET statut = 'lu' WHERE id_contact = :id_contact");
        $update->execute([':id_contact' => $view_message_id]);
        $view_message['statut'] = 'lu';
    }
}

// Récupérer tous les produits pour l'ajout dans les commandes
$all_products = $db->query("SELECT id_produit, nom, prix, stock FROM Produit ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'erreur éventuelle
if (isset($_GET['error']) && $_GET['error'] == 1 && !empty($error_message)) {
    // L'erreur est déjà définie
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Système de Gestion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            width: 260px;
            height: calc(100% - 70px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar-menu { list-style: none; padding: 20px 0; }
        
        .sidebar-menu li { margin-bottom: 5px; position: relative; }
        
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .message-badge {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: translateY(-50%) scale(1); }
            50% { transform: translateY(-50%) scale(1.1); }
            100% { transform: translateY(-50%) scale(1); }
        }
        
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
        }
        
        .container { max-width: 1400px; margin: 0 auto; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover { transform: translateY(-5px); }
        
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        
        .stat-number { font-size: 36px; font-weight: bold; color: #667eea; }
        
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
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.4); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-success { background: #28a745; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }
        
        table { width: 100%; border-collapse: collapse; }
        
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        
        th { background: #f8f9fa; font-weight: 600; color: #495057; }
        
        tr:hover { background: #f8f9fa; }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input[type="file"] { padding: 5px; }
        
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
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
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            max-height: 85%;
            overflow-y: auto;
        }
        
        .close { float: right; font-size: 28px; cursor: pointer; }
        
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-primary { background: #007bff; color: white; }
        
        .product-image { width: 50px; height: 50px; object-fit: cover; border-radius: 5px; }
        .current-image { max-width: 100px; max-height: 100px; margin-top: 10px; border-radius: 5px; }
        
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        
        .stock-warning { color: #dc3545; font-weight: bold; }
        
        .phone-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .phone-link:hover { text-decoration: underline; }
        
        .grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        
        /* Styles pour les messages */
        .message-list-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .message-list-item:hover {
            background: #f8f9fa;
        }
        
        .message-list-item.unread {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .message-list-item.read {
            background: white;
        }
        
        .message-list-item.replied {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .message-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .message-sender {
            font-weight: bold;
            color: #333;
        }
        
        .message-subject {
            color: #667eea;
            font-weight: 500;
            margin: 5px 0;
        }
        
        .message-date {
            color: #888;
            font-size: 12px;
        }
        
        .message-excerpt {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }
        
        /* Styles pour la vue détaillée */
        .message-detail {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .message-detail-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .message-detail-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .message-detail-content {
            padding: 20px;
            background: #fafafa;
            border-radius: 8px;
            line-height: 1.6;
            margin: 20px 0;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
        }
        
        .response-form {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
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
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; }
            .grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>📊 Dashboard Administrateur</h1>
        <div class="user-info">
            <span>👋 Bonjour, <?php echo htmlspecialchars($_SESSION['admin_nom']); ?></span>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </div>
    
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li class="<?php echo $action == 'dashboard' ? 'active' : ''; ?>">
                <a href="?action=dashboard">📊 Tableau de bord</a>
            </li>
            <li class="<?php echo $action == 'products' ? 'active' : ''; ?>">
                <a href="?action=products">🛍️ Produits</a>
            </li>
            <li class="<?php echo $action == 'orders' ? 'active' : ''; ?>">
                <a href="?action=orders">📦 Commandes</a>
            </li>
            <li class="<?php echo $action == 'clients' ? 'active' : ''; ?>">
                <a href="?action=clients">👥 Clients</a>
            </li>
            <li class="<?php echo $action == 'managers' ? 'active' : ''; ?>">
                <a href="?action=managers">👨‍💼 Gestionnaires</a>
            </li>
            <li class="<?php echo $action == 'messages' ? 'active' : ''; ?>">
                <a href="?action=messages">
                    📧 Messages 
                    <?php if($stats['unread_messages'] > 0): ?>
                        <span class="message-badge"><?php echo $stats['unread_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="<?php echo $action == 'categories' ? 'active' : ''; ?>">
                <a href="?action=categories">📁 Catégories</a>
            </li>
            <li class="<?php echo $action == 'statuses' ? 'active' : ''; ?>">
                <a href="?action=statuses">🏷️ Statuts</a>
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
                    <div class="stat-card"><h3>👥 Total Clients</h3><div class="stat-number"><?php echo $stats['total_clients']; ?></div></div>
                    <div class="stat-card"><h3>👨‍💼 Total Gestionnaires</h3><div class="stat-number"><?php echo $stats['total_managers']; ?></div></div>
                    <div class="stat-card"><h3>🛍️ Total Produits</h3><div class="stat-number"><?php echo $stats['total_products']; ?></div></div>
                    <div class="stat-card"><h3>📦 Total Commandes</h3><div class="stat-number"><?php echo $stats['total_orders']; ?></div></div>
                    <div class="stat-card"><h3>💰 Chiffre d'affaires</h3><div class="stat-number"><?php echo number_format($stats['total_revenue'], 0, ',', ' '); ?> DH</div></div>
                    <div class="stat-card">
                        <h3>📧 Messages non lus</h3>
                        <div class="stat-number">
                            <?php 
                            $unread_count = $db->query("SELECT COUNT(*) as count FROM contact WHERE statut = 'non lu'")->fetch(PDO::FETCH_ASSOC)['count'];
                            echo $unread_count; 
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="content-card">
                    <h2>📋 Dernières commandes</h2>
                    <?php if(count($commandes) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucune commande pour le moment.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Téléphone</th>
                                    <th>Total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                </tr>
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
                
                <!-- Messages non lus sur le dashboard -->
                <?php 
                $unread_messages = $db->query("SELECT * FROM contact WHERE statut = 'non lu' ORDER BY date_envoi DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
                if(count($unread_messages) > 0): 
                ?>
                <div class="content-card">
                    <h2>📧 Messages non lus <span class="badge badge-danger"><?php echo count($unread_messages); ?></span></h2>
                    <?php foreach($unread_messages as $message): ?>
                        <div class="message-list-item unread" onclick="window.location.href='?action=messages&view=<?php echo $message['id_contact']; ?>'">
                            <div class="message-preview">
                                <span class="message-sender"><?php echo htmlspecialchars($message['nom']); ?></span>
                                <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($message['date_envoi'])); ?></span>
                            </div>
                            <div class="message-subject">📌 <?php echo htmlspecialchars($message['sujet']); ?></div>
                            <div class="message-excerpt"><?php echo htmlspecialchars(substr($message['message'], 0, 100)); ?>...</div>
                        </div>
                    <?php endforeach; ?>
                    <div style="text-align: center; margin-top: 15px;">
                        <a href="?action=messages" class="btn btn-primary btn-sm">Voir tous les messages non lus</a>
                    </div>
                </div>
                <?php else: ?>
                
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- GESTION DES PRODUITS -->
            <?php if($action == 'products'): ?>
                <div class="content-card">
                    <h2>➕ Ajouter un produit</h2>
                    <form method="POST" enctype="multipart/form-data" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="form-group"><label>Nom du produit *</label><input type="text" name="nom" required></div>
                        <div class="form-group"><label>Prix (DH) *</label><input type="number" step="0.01" name="prix" required></div>
                        <div class="form-group"><label>Stock *</label><input type="number" name="stock" required></div>
                        <div class="form-group">
                            <label>Catégorie *</label>
                            <select name="id_categorie" required>
                                <option value="">Sélectionner</option>
                                <?php foreach($categories as $categorie): ?>
                                    <option value="<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Description</label>
                            <textarea name="description" rows="3"></textarea>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label>Image du produit</label>
                            <input type="file" name="image" accept="image/*">
                            <small>Formats acceptés: JPG, JPEG, PNG, GIF, WEBP</small>
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <button type="submit" name="add_product" class="btn btn-primary">➕ Ajouter le produit</button>
                        </div>
                    </form>
                </div>
                
                <div class="content-card">
                    <h2>📦 Liste des produits (<?php echo count($produits); ?> produit<?php echo count($produits) > 1 ? 's' : ''; ?>)</h2>
                    <?php if(count($produits) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucun produit. Cliquez sur "Ajouter un produit" pour commencer.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th><th>Image</th><th>Nom</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Actions</th>
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
                                    <td><?php echo number_format($produit['prix'], 0, ',', ' '); ?> DH</td>
                                    <td class="<?php echo $produit['stock'] <= 5 ? 'stock-warning' : ''; ?>">
                                        <?php echo $produit['stock']; ?>
                                        <?php if($produit['stock'] <= 5): ?> ⚠️<?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <button onclick='editProduct(<?php echo json_encode($produit); ?>)' class="btn btn-warning btn-sm">✏️ Modifier</button>
                                        <a href="?action=products&delete_product=<?php echo $produit['id_produit']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce produit ?')">🗑️ Supprimer</a>
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
            
            <!-- GESTION DES CLIENTS -->
            <?php if($action == 'clients'): ?>
                <div class="content-card">
                    <h2>👥 Liste des clients</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Téléphone</th>
                                <th>Adresse</th>
                                <th>Email</th>
                                <th>Date d'inscription</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($clients as $client): ?>
                            <tr>
                                <td><?php echo $client['id_utilisateur']; ?></td>
                                <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                <td>
                                    <?php if($client['telephone']): ?>
                                        <a href="tel:<?php echo $client['telephone']; ?>" class="phone-link">
                                            📞 <?php echo $client['telephone']; ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo $client['telephone']; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($client['adresse']); ?></td>
                                <td><?php echo $client['email'] ? htmlspecialchars($client['email']) : '-'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($client['date_creation'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- GESTION DES GESTIONNAIRES -->
            <?php if($action == 'managers'): ?>
                <div class="content-card">
                    <h2>➕ Ajouter un gestionnaire</h2>
                    <form method="POST" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div class="form-group"><label>Nom complet *</label><input type="text" name="nom" required></div>
                        <div class="form-group"><label>Email (optionnel)</label><input type="email" name="email"></div>
                        <div class="form-group"><label>Téléphone *</label><input type="tel" name="telephone" required></div>
                        <div class="form-group"><label>Adresse</label><input type="text" name="adresse"></div>
                        <div class="form-group" style="grid-column: span 2;"><label>Mot de passe *</label><input type="password" name="password" required></div>
                        <div class="form-group" style="grid-column: span 2;"><button type="submit" name="add_manager" class="btn btn-primary">Ajouter le gestionnaire</button></div>
                    </form>
                </div>
                
                <div class="content-card">
                    <h2>👨‍💼 Liste des gestionnaires</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Adresse</th><th>Date d'ajout</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($gestionnaires as $gestionnaire): ?>
                            <tr>
                                <td><?php echo $gestionnaire['id_utilisateur']; ?></td>
                                <td><?php echo htmlspecialchars($gestionnaire['nom']); ?></td>
                                <td><?php echo !empty($gestionnaire['email']) ? htmlspecialchars($gestionnaire['email']) : '<span style="color: #888;">Non renseigné</span>'; ?></td>
                                <td><?php echo $gestionnaire['telephone']; ?></td>
                                <td><?php echo htmlspecialchars($gestionnaire['adresse']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($gestionnaire['date_creation'])); ?></td>
                                <td>
                                    <a href="?action=managers&delete_manager=<?php echo $gestionnaire['id_utilisateur']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce gestionnaire ?')">🗑️</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- GESTION DES MESSAGES -->
            <?php if($action == 'messages'): ?>
                <?php if($view_message && $view_message_id > 0): ?>
                    <!-- Vue détaillée d'un message -->
                    <div class="message-detail">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="margin: 0;">📧 Détail du message</h2>
                            <a href="?action=messages" class="btn btn-secondary btn-sm">← Retour à la liste</a>
                        </div>
                        
                        <div class="message-detail-header">
                            <h3><?php echo htmlspecialchars($view_message['sujet']); ?></h3>
                            <div class="message-preview" style="margin-top: 10px;">
                                <span class="message-sender">De: <?php echo htmlspecialchars($view_message['nom']); ?></span>
                                <span class="message-date">📅 <?php echo date('d/m/Y à H:i', strtotime($view_message['date_envoi'])); ?></span>
                            </div>
                        </div>
                        
                        <div class="message-detail-info">
                            <div>
                                <span class="info-label">📧 Email:</span>
                                <span class="info-value">
                                    <a href="mailto:<?php echo htmlspecialchars($view_message['email']); ?>">
                                        <?php echo htmlspecialchars($view_message['email']); ?>
                                    </a>
                                </span>
                            </div>
                            <div>
                                <span class="info-label">📞 Téléphone:</span>
                                <span class="info-value">
                                    <a href="tel:<?php echo htmlspecialchars($view_message['telephone']); ?>">
                                        <?php echo htmlspecialchars($view_message['telephone']); ?>
                                    </a>
                                </span>
                            </div>
                            <div>
                                <span class="info-label">🏷️ Statut:</span>
                                <span class="info-value">
                                    <form method="POST" style="display: inline-block;">
                                        <input type="hidden" name="id_contact" value="<?php echo $view_message['id_contact']; ?>">
                                        <select name="statut" onchange="this.form.submit()" style="padding: 5px; border-radius: 5px;">
                                            <option value="non lu" <?php echo $view_message['statut'] == 'non lu' ? 'selected' : ''; ?>>📖 Non lu</option>
                                            <option value="lu" <?php echo $view_message['statut'] == 'lu' ? 'selected' : ''; ?>>👁️ Lu</option>
                                            <option value="répondu" <?php echo $view_message['statut'] == 'répondu' ? 'selected' : ''; ?>>✉️ Répondu</option>
                                        </select>
                                        <input type="hidden" name="update_message_status">
                                    </form>
                                </span>
                            </div>
                        </div>
                        
                        <div class="message-detail-content">
                            <strong>📝 Message :</strong><br><br>
                            <?php echo nl2br(htmlspecialchars($view_message['message'])); ?>
                        </div>
                        
                        <div style="margin-top: 20px; text-align: right;">
                            <a href="?action=messages&delete_contact=<?php echo $view_message['id_contact']; ?>" class="btn btn-danger" onclick="return confirm('Supprimer ce message définitivement ?')">🗑️ Supprimer</a>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Liste des messages - UNIQUEMENT NON LUS -->
                    <div class="content-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="margin: 0;">📧 Messages non lus 
                                <?php 
                                $unread_count = $db->query("SELECT COUNT(*) as count FROM contact WHERE statut = 'non lu'")->fetch(PDO::FETCH_ASSOC)['count'];
                                if($unread_count > 0): ?>
                                    <span class="badge badge-danger"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </h2>
                            <form method="POST" style="margin: 0;">
                                <button type="submit" name="mark_all_read" class="btn btn-info btn-sm" onclick="return confirm('Marquer tous les messages comme lus ?')">✅ Tout marquer lu</button>
                            </form>
                        </div>
                        
                        <?php 
                        $unread_messages = $db->query("SELECT * FROM contact WHERE statut = 'non lu' ORDER BY date_envoi DESC")->fetchAll(PDO::FETCH_ASSOC);
                        
                        if(count($unread_messages) == 0): ?>
                            <p style="text-align: center; color: #666; padding: 40px;">📭 Aucun message non lu</p>
                        <?php else: ?>
                            <div>
                                <?php foreach($unread_messages as $message): ?>
                                    <div class="message-list-item unread" onclick="window.location.href='?action=messages&view=<?php echo $message['id_contact']; ?>'">
                                        <div class="message-preview">
                                            <span class="message-sender">
                                                <span class="badge badge-warning" style="margin-right: 8px;">Nouveau</span>
                                                <?php echo htmlspecialchars($message['nom']); ?>
                                            </span>
                                            <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($message['date_envoi'])); ?></span>
                                        </div>
                                        <div class="message-subject">📌 <?php echo htmlspecialchars($message['sujet']); ?></div>
                                        <div class="message-excerpt">
                                            📧 <?php echo htmlspecialchars($message['email']); ?> | 
                                            📞 <?php echo htmlspecialchars($message['telephone']); ?>
                                        </div>
                                        <div class="message-excerpt"><?php echo htmlspecialchars(substr($message['message'], 0, 80)); ?>...</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Option pour voir l'historique complet (messages lus et répondus) -->
                    <div class="content-card" style="margin-top: 20px;">
                        <details>
                            <summary style="cursor: pointer; font-weight: 500; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                                📜 Voir l'historique des messages (lus et répondus)
                            </summary>
                            <div style="margin-top: 15px;">
                                <?php 
                                $read_messages = $db->query("SELECT * FROM contact WHERE statut IN ('lu', 'répondu') ORDER BY date_envoi DESC")->fetchAll(PDO::FETCH_ASSOC);
                                
                                if(count($read_messages) == 0): ?>
                                    <p style="text-align: center; color: #666; padding: 20px;">Aucun message dans l'historique</p>
                                <?php else: ?>
                                    <?php foreach($read_messages as $message): ?>
                                        <div class="message-list-item <?php echo $message['statut']; ?>" onclick="window.location.href='?action=messages&view=<?php echo $message['id_contact']; ?>'">
                                            <div class="message-preview">
                                                <span class="message-sender">
                                                    <?php if($message['statut'] == 'répondu'): ?>
                                                        <span class="badge badge-success" style="margin-right: 8px;">✓ Répondu</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-info" style="margin-right: 8px;">Lu</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($message['nom']); ?>
                                                </span>
                                                <span class="message-date"><?php echo date('d/m/Y H:i', strtotime($message['date_envoi'])); ?></span>
                                            </div>
                                            <div class="message-subject">📌 <?php echo htmlspecialchars($message['sujet']); ?></div>
                                            <div class="message-excerpt">
                                                📧 <?php echo htmlspecialchars($message['email']); ?> | 
                                                📞 <?php echo htmlspecialchars($message['telephone']); ?>
                                            </div>
                                            <div class="message-excerpt"><?php echo htmlspecialchars(substr($message['message'], 0, 80)); ?>...</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- GESTION DES CATEGORIES -->
            <?php if($action == 'categories'): ?>
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">📁 Gestion des Catégories</h2>
                        <a href="?action=dashboard" class="btn btn-secondary btn-sm">← Retour au tableau de bord</a>
                    </div>
                    
                    <form method="POST" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h3 style="margin-bottom: 15px;">➕ Ajouter une nouvelle catégorie</h3>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Nom de la catégorie *</label>
                            <input type="text" name="nom" placeholder="Ex: Électronique, Vêtements, Maison..." required style="width: 100%;">
                        </div>
                        <button type="submit" name="add_category" class="btn btn-primary">➕ Ajouter la catégorie</button>
                    </form>
                    
                    <h3>📋 Liste des catégories existantes</h3>
                    <?php if(count($categories) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucune catégorie. Créez votre première catégorie ci-dessus.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom de la catégorie</th>
                                    <th>Nombre de produits</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($categories as $categorie): 
                                    $count_products = $db->prepare("SELECT COUNT(*) as count FROM Produit WHERE id_categorie = :id_categorie");
                                    $count_products->execute([':id_categorie' => $categorie['id_categorie']]);
                                    $product_count = $count_products->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <tr>
                                    <td><?php echo $categorie['id_categorie']; ?></td>
                                    <td>
                                        <span id="cat_name_<?php echo $categorie['id_categorie']; ?>"><?php echo htmlspecialchars($categorie['nom']); ?></span>
                                        <input type="text" id="cat_edit_<?php echo $categorie['id_categorie']; ?>" value="<?php echo htmlspecialchars($categorie['nom']); ?>" style="display:none; width:200px;" class="form-control">
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $product_count; ?> produit<?php echo $product_count > 1 ? 's' : ''; ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <button onclick="editCategory(<?php echo $categorie['id_categorie']; ?>)" class="btn btn-warning btn-sm" id="cat_edit_btn_<?php echo $categorie['id_categorie']; ?>">✏️ Modifier</button>
                                        <button onclick="saveCategory(<?php echo $categorie['id_categorie']; ?>)" class="btn btn-success btn-sm" id="cat_save_btn_<?php echo $categorie['id_categorie']; ?>" style="display:none;">💾 Enregistrer</button>
                                        <a href="?action=categories&delete_category=<?php echo $categorie['id_categorie']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer cette catégorie ?\nAttention: Les produits associés ne seront pas supprimés mais n\'auront plus de catégorie.')">🗑️ Supprimer</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- GESTION DES STATUTS -->
            <?php if($action == 'statuses'): ?>
                <div class="content-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;">🏷️ Gestion des Statuts de commande</h2>
                        <a href="?action=dashboard" class="btn btn-secondary btn-sm">← Retour au tableau de bord</a>
                    </div>
                    
                    <form method="POST" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
                        <h3 style="margin-bottom: 15px;">➕ Ajouter un nouveau statut</h3>
                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Nom du statut *</label>
                            <input type="text" name="nom" placeholder="Ex: En attente, En cours, Livrée, Annulée..." required style="width: 100%;">
                        </div>
                        <button type="submit" name="add_status" class="btn btn-primary">➕ Ajouter le statut</button>
                    </form>
                    
                    <h3>📋 Liste des statuts existants</h3>
                    <?php if(count($status_list) == 0): ?>
                        <p style="text-align: center; color: #666; padding: 40px;">Aucun statut. Créez votre premier statut ci-dessus.</p>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom du statut</th>
                                    <th>Nombre de commandes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($status_list as $status): 
                                    $count_orders = $db->prepare("SELECT COUNT(*) as count FROM Commande WHERE id_status = :id_status");
                                    $count_orders->execute([':id_status' => $status['id_status']]);
                                    $order_count = $count_orders->fetch(PDO::FETCH_ASSOC)['count'];
                                    
                                    $badge_class = '';
                                    $statut_nom_lower = strtolower($status['nom']);
                                    if(strpos($statut_nom_lower, 'annul') !== false || strpos($statut_nom_lower, 'refus') !== false) {
                                        $badge_class = 'badge-danger';
                                    } elseif(strpos($statut_nom_lower, 'livr') !== false || strpos($statut_nom_lower, 'termin') !== false) {
                                        $badge_class = 'badge-success';
                                    } elseif(strpos($statut_nom_lower, 'cours') !== false || strpos($statut_nom_lower, 'prépar') !== false) {
                                        $badge_class = 'badge-warning';
                                    } else {
                                        $badge_class = 'badge-info';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $status['id_status']; ?></td>
                                    <td>
                                        <span id="status_name_<?php echo $status['id_status']; ?>">
                                            <span class="badge <?php echo $badge_class; ?>" style="margin-right: 8px;">●</span>
                                            <?php echo htmlspecialchars($status['nom']); ?>
                                        </span>
                                        <input type="text" id="status_edit_<?php echo $status['id_status']; ?>" value="<?php echo htmlspecialchars($status['nom']); ?>" style="display:none; width:200px;" class="form-control">
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $order_count; ?> commande<?php echo $order_count > 1 ? 's' : ''; ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <button onclick="editStatus(<?php echo $status['id_status']; ?>)" class="btn btn-warning btn-sm" id="status_edit_btn_<?php echo $status['id_status']; ?>">✏️ Modifier</button>
                                        <button onclick="saveStatus(<?php echo $status['id_status']; ?>)" class="btn btn-success btn-sm" id="status_save_btn_<?php echo $status['id_status']; ?>" style="display:none;">💾 Enregistrer</button>
                                        <a href="?action=statuses&delete_status=<?php echo $status['id_status']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer ce statut ?\nAttention: Les commandes associées devront être reassignées.')">🗑️ Supprimer</a>
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
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="id_produit" id="edit_id">
                <div class="form-group"><label>Nom du produit *</label><input type="text" name="nom" id="edit_nom" required></div>
                <div class="form-group"><label>Description</label><textarea name="description" id="edit_description" rows="3"></textarea></div>
                <div class="form-group"><label>Prix (DH) *</label><input type="number" step="0.01" name="prix" id="edit_prix" required></div>
                <div class="form-group"><label>Stock *</label><input type="number" name="stock" id="edit_stock" required></div>
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
                <button type="submit" name="edit_product" class="btn btn-primary">💾 Enregistrer</button>
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
                
                <div class="form-group" style="margin: 15px 0;">
                    <label>➕ Ajouter un produit</label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select id="add_product_select" style="flex: 1; padding: 8px; border-radius: 5px; border: 1px solid #ddd;">
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
                    <span id="order_total_display" style="font-size: 20px; color: #667eea;">0 DH</span>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeOrderModal()">Annuler</button>
                    <button type="submit" name="update_order_details" class="btn btn-primary">💾 Enregistrer les modifications</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Variables pour la modification de commande
        let currentOrderProducts = [];
        
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id_produit;
            document.getElementById('edit_nom').value = product.nom;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_prix').value = product.prix;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_categorie').value = product.id_categorie;
            
            const imageContainer = document.getElementById('current_image_container');
            if(product.image) {
                imageContainer.innerHTML = `<p>Image actuelle :</p><img src="../uploads/products/${product.image}" class="current-image"><small>Laissez vide pour conserver cette image</small>`;
            } else {
                imageContainer.innerHTML = '<small>Aucune image actuellement</small>';
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
            
            // Vérifier si le produit est déjà dans la commande
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
            
            // Ajouter un champ caché pour le total
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
        
        // Gestion des clics sur les modals
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
        
        // Fonctions pour les catégories
        function editCategory(id) {
            document.getElementById('cat_name_' + id).style.display = 'none';
            document.getElementById('cat_edit_' + id).style.display = 'inline-block';
            document.getElementById('cat_edit_btn_' + id).style.display = 'none';
            document.getElementById('cat_save_btn_' + id).style.display = 'inline-block';
        }
        
        function saveCategory(id) {
            const newName = document.getElementById('cat_edit_' + id).value;
            if(!newName.trim()) {
                alert('Le nom ne peut pas être vide');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputId = document.createElement('input');
            inputId.name = 'id_categorie';
            inputId.value = id;
            
            const inputName = document.createElement('input');
            inputName.name = 'nom';
            inputName.value = newName;
            
            const inputAction = document.createElement('input');
            inputAction.name = 'edit_category';
            inputAction.value = '1';
            
            form.appendChild(inputId);
            form.appendChild(inputName);
            form.appendChild(inputAction);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Fonctions pour les statuts
        function editStatus(id) {
            document.getElementById('status_name_' + id).style.display = 'none';
            document.getElementById('status_edit_' + id).style.display = 'inline-block';
            document.getElementById('status_edit_btn_' + id).style.display = 'none';
            document.getElementById('status_save_btn_' + id).style.display = 'inline-block';
        }
        
        function saveStatus(id) {
            const newName = document.getElementById('status_edit_' + id).value;
            if(!newName.trim()) {
                alert('Le nom ne peut pas être vide');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const inputId = document.createElement('input');
            inputId.name = 'id_status';
            inputId.value = id;
            
            const inputName = document.createElement('input');
            inputName.name = 'nom';
            inputName.value = newName;
            
            const inputAction = document.createElement('input');
            inputAction.name = 'edit_status';
            inputAction.value = '1';
            
            form.appendChild(inputId);
            form.appendChild(inputName);
            form.appendChild(inputAction);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>