<?php
// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) session_start();

// Inclusion de la config si besoin
if (!isset($pdo)) require_once __DIR__ . '/config.php';

// -------------------
// Fonctions utilitaires de sécurité
// -------------------
function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && $token === $_SESSION['csrf_token'];
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// -------------------
// Validation des champs d'article
// -------------------
function valider_article($data) {
    $erreurs = [];
    if (empty($data['titre'])) $erreurs[] = "Le titre est obligatoire.";
    if (empty($data['description'])) $erreurs[] = "La description est obligatoire.";
    if (empty($data['contenu'])) $erreurs[] = "Le contenu est obligatoire.";
    if (empty($data['theme_id']) || (int)$data['theme_id'] <= 0) $erreurs[] = "Le thème est obligatoire.";
    return $erreurs;
}

// -------------------
// Ajout d'un article (utilisateur ou admin)
// -------------------
function ajouter_article($pdo, $data, $user_id = null, $is_admin = false) {
    $erreurs = valider_article($data);
    if (!check_csrf($data['csrf_token'] ?? '')) {
        $erreurs[] = "Erreur de sécurité (CSRF).";
    }
    if (!empty($erreurs)) return [false, $erreurs];
    $image_name = null;
    // Gestion de l'upload d'image
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $image_name = uniqid('img_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name);
        } else {
            $erreurs[] = "Format d'image non autorisé.";
            return [false, $erreurs];
        }
    }
    $sql = $is_admin
        ? "INSERT INTO articles (theme_id, titre, description, image, contenu) VALUES (:theme_id, :titre, :description, :image, :contenu)"
        : "INSERT INTO articles (theme_id, titre, description, image, contenu, user_id, statut) VALUES (:theme_id, :titre, :description, :image, :contenu, :user_id, 'en attente')";
    $params = [
        'theme_id' => $data['theme_id'],
        'titre' => $data['titre'],
        'description' => $data['description'],
        'image' => $image_name,
        'contenu' => $data['contenu'],
    ];
    if (!$is_admin) $params['user_id'] = $user_id;
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    return [$ok, $ok ? [] : ["Erreur lors de l'ajout de l'article."]];
}

// -------------------
// Modification d'un article (utilisateur ou admin)
// -------------------
function modifier_article($pdo, $data, $user_id = null, $is_admin = false) {
    $erreurs = valider_article($data);
    if (!check_csrf($data['csrf_token'] ?? '')) {
        $erreurs[] = "Erreur de sécurité (CSRF).";
    }
    if (!empty($erreurs)) return [false, $erreurs];
    $id = (int)$data['id'];
    $image_name = $data['old_image'] ?? null;
    // Upload nouvelle image ?
    if (!empty($_FILES['image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $image_name = uniqid('img_') . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], '../uploads/' . $image_name);
        } else {
            $erreurs[] = "Format d'image non autorisé.";
            return [false, $erreurs];
        }
    }
    $sql = $is_admin
        ? "UPDATE articles SET theme_id = :theme_id, titre = :titre, description = :description, image = :image, contenu = :contenu WHERE id = :id"
        : "UPDATE articles SET theme_id = :theme_id, titre = :titre, description = :description, image = :image, contenu = :contenu, statut = 'en attente' WHERE id = :id AND user_id = :user_id";
    $params = [
        'theme_id' => $data['theme_id'],
        'titre' => $data['titre'],
        'description' => $data['description'],
        'image' => $image_name,
        'contenu' => $data['contenu'],
        'id' => $id
    ];
    if (!$is_admin) $params['user_id'] = $user_id;
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    return [$ok, $ok ? [] : ["Erreur lors de la modification de l'article."]];
}

// -------------------
// Suppression d'un article (utilisateur ou admin)
// -------------------
function supprimer_article($pdo, $id, $user_id = null, $is_admin = false) {
    // Supprimer l'image associée si elle existe
    $img = $pdo->prepare("SELECT image FROM articles WHERE id = :id");
    $img->execute(['id' => $id]);
    $img_row = $img->fetch(PDO::FETCH_ASSOC);
    if ($img_row && !empty($img_row['image']) && file_exists('../uploads/' . $img_row['image'])) {
        unlink('../uploads/' . $img_row['image']);
    }
    $sql = $is_admin
        ? "DELETE FROM articles WHERE id = :id"
        : "DELETE FROM articles WHERE id = :id AND user_id = :user_id";
    $params = ['id' => $id];
    if (!$is_admin) $params['user_id'] = $user_id;
    $stmt = $pdo->prepare($sql);
    $ok = $stmt->execute($params);
    return [$ok, $ok ? [] : ["Erreur lors de la suppression de l'article."]];
}

// -------------------
// Validation/refus d'un article (admin)
// -------------------
function valider_article_admin($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE articles SET statut = 'publié' WHERE id = ?");
    return $stmt->execute([$id]);
}
function refuser_article_admin($pdo, $id) {
    $stmt = $pdo->prepare("UPDATE articles SET statut = 'refusé' WHERE id = ?");
    return $stmt->execute([$id]);
}
// -------------------
// Fonctions utilitaires pour récupérer les articles et thèmes
// -------------------
function get_articles($pdo) {
    $sql = "SELECT a.id, a.titre, a.description, a.image, t.name AS theme_name, a.date_creation, a.statut, a.user_id FROM articles a JOIN themes t ON a.theme_id = t.id ORDER BY a.date_creation DESC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function get_themes($pdo) {
    return $pdo->query("SELECT id, name FROM themes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}
function get_article_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
// -------------------
// Connexion de l'admin
// -------------------
// Identifiants admin prédéfinis
define('ADMIN_EMAIL', 'admin@monblog.fr');
// define('ADMIN_PASSWORD', 'motdepassefort'); // Ancien mot de passe en clair
// Hash généré avec password_hash('motdepassefort', PASSWORD_DEFAULT)
define('ADMIN_PASSWORD_HASH', '$2y$10$J3plZtSj.vLK523aaJuve.t7KMAHhJZA/4OlpXWEakl7Ml0q.nxY2'); // À remplacer par ton vrai hash

function connecter_admin($email, $password) {
    if ($email === ADMIN_EMAIL && password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        $_SESSION['prenom'] = 'Admin';
        $_SESSION['nom'] = 'Principal';
        return [true, "Connexion admin réussie !"];
    }
    return [false, "Identifiants admin incorrects."];
}
// ... autres fonctions si besoin ...

