<?php
include 'includes/config.php';
include 'includes/pagination.php';

// Gestion cookies recherche/thème
if (isset($_GET['theme_id']) && isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted') {
    setcookie('theme_pref', $_GET['theme_id'], time() + 30*24*3600, '/');
}
if (isset($_GET['search']) && isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted') {
    setcookie('search_pref', $_GET['search'], time() + 30*24*3600, '/');
}

// Récupérer le thème de l'utilisateur (s'il est connecté)
$user_theme_id = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT theme_id FROM user_themes WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_theme_id = $stmt->fetchColumn();
}

// Pré-remplir avec cookie si pas de GET
$theme_id = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : (isset($_COOKIE['theme_pref']) ? (int)$_COOKIE['theme_pref'] : 0);
// Si aucun filtre n'est sélectionné et utilisateur connecté, récupérer ses thèmes préférés
// $user_theme_ids = []; // This line is now handled by the if/else block above
$search = isset($_GET['search']) ? trim($_GET['search']) : (isset($_COOKIE['search_pref']) ? $_COOKIE['search_pref'] : '');
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = 6; // 6 articles par page

// Requête pour compter le total d'articles
$count_sql = "SELECT COUNT(*) as total FROM articles a WHERE a.statut = 'publié'";
$count_params = [];
$count_where = [];

if ($search !== '') {
    $count_where[] = "(a.titre LIKE :search OR a.description LIKE :search OR a.contenu LIKE :search)";
    $count_params['search'] = '%' . $search . '%';
}
if ($theme_id > 0) {
    $count_where[] = "a.theme_id = :theme_id";
    $count_params['theme_id'] = $theme_id;
}

if ($count_where) {
    $count_sql .= " AND " . implode(' AND ', $count_where);
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($count_params);
$total_articles = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Créer la pagination
$pagination = create_pagination($total_articles, $items_per_page, $current_page);

// Requête pour récupérer les articles avec pagination
$sql = "SELECT a.id, a.titre, a.description, a.image, a.date_creation, a.user_id, a.statut, u.prenom, u.nom, t.name AS theme_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        JOIN themes t ON a.theme_id = t.id
        WHERE a.statut = 'publié'";
$params = [];
$where = [];
if ($search !== '') {
    $where[] = "(a.titre LIKE :search OR a.description LIKE :search OR a.contenu LIKE :search)";
    $params['search'] = '%' . $search . '%';
}
// Filtrage des articles
if ($theme_id > 0) {
    $where[] = "a.theme_id = :theme_id";
    $params['theme_id'] = $theme_id;
} else if ($user_theme_id) {
    $where[] = "a.theme_id = :theme_id";
    $params['theme_id'] = $user_theme_id;
}
if ($where) {
    $sql .= " AND " . implode(' AND ', $where);
}
$sql .= " ORDER BY a.date_creation DESC LIMIT " . $pagination->getLimit() . " OFFSET " . $pagination->getOffset();

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Toujours charger la liste des thèmes pour le filtre
$themes = $pdo->query("SELECT id, name FROM themes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Articles</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background: #fff;
            min-height: 100vh;
        }
        .card-article {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            background: rgba(255,255,255,0.95);
            transition: transform 0.2s, box-shadow 0.2s;
            color: #222;
        }
        .card-article:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 16px 40px 0 rgba(31, 38, 135, 0.18);
            background: #f8f9fa;
        }
        .card-article .card-img-top {
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            height: 220px;
            object-fit: cover;
            background: #eee;
        }
        .badge-theme {
            background: linear-gradient(90deg, #ff512f 0%, #dd2476 100%);
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.55em 1.2em;
            box-shadow: 0 0 8px 2px rgba(221,36,118,0.18), 0 2px 8px 0 rgba(0,0,0,0.08);
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.12);
            border: 2px solid #fff2;
        }
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #222;
        }
        .card-description {
            font-size: 1rem;
            color: #555;
        }
        .card-date {
            color: #b0b0b0;
            font-size: 0.95rem;
        }
        .btn-futur {
            background: linear-gradient(90deg, #00c6ff 0%, #0072ff 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s, color 0.2s;
        }
        .btn-futur:hover {
            background: linear-gradient(90deg, #0072ff 0%, #00c6ff 100%);
            color: #fff;
        }
        .search-bar {
            max-width: 700px;
            margin: 0 auto 2rem auto;
        }
    </style>
</head>
<body>
<!-- Bouton retour -->
<div class="container" style="max-width: 900px; margin-top: 2rem; margin-bottom: 0.5rem;">
    <a href="index.php" class="btn btn-outline-secondary mb-3">&larr; Retour à l'accueil</a>
</div>
<!-- Moteur de recherche -->
<div class="container" style="max-width: 900px; margin-top: 2.5rem; margin-bottom: 1.5rem;">
    <form method="get" class="search-bar mb-3">
        <div class="row g-2 align-items-center justify-content-center">
            <!-- <div class="col-md-6 mb-2 mb-md-0">
                 <input type="text" class="form-control form-control-lg" name="search" placeholder="Rechercher un article..." value="<?= htmlspecialchars($search) ?>"> -->
            <!-- </div> --> 
            <div class="col-md-4 mb-2 mb-md-0">
                <?php if (!empty($themes)): ?>
                    <select class="form-select form-select-lg" name="theme_id">
                        <option value="0">Tous les thèmes</option>
                        <?php foreach ($themes as $theme): ?>
                            <option value="<?= $theme['id'] ?>" <?= $theme_id == $theme['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($theme['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-futur w-100" type="submit">Rechercher</button>
            </div>
        </div>
    </form>
</div>
<!-- Liste des articles (cards) -->
<div class="container" style="max-width: 1200px; margin-bottom: 2.5rem;">
    <h2 class="mb-4 text-center text-dark fw-bold" style="letter-spacing:2px; font-size:2rem;">Derniers articles</h2>
    <div class="row g-4 justify-content-center">
        <?php if (empty($articles)): ?>
            <div class="col-12 text-center text-dark-50">Aucun article trouvé.</div>
        <?php endif; ?>
        <?php foreach ($articles as $article): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-article h-100">
                    <?php
                        $imgSrc = !empty($article['image']) && file_exists('uploads/' . $article['image'])
                            ? 'uploads/' . htmlspecialchars($article['image'])
                            : 'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=600&q=80';
                    ?>
                    <img src="<?= $imgSrc ?>" class="card-img-top" alt="Image article">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge badge-theme me-2"><?= htmlspecialchars($article['theme_name']) ?></span>
                            <span class="card-date ms-auto">
                                <?= date('d/m/Y', strtotime($article['date_creation'])) ?>
                            </span>
                        </div>
                        <h3 class="card-title mb-2" style="font-size:1.2rem; font-weight:600; letter-spacing:0.5px; color:#222;">
                            <?= htmlspecialchars($article['titre']) ?>
                        </h3>
                        <div class="card-description mb-3" style="font-size:1rem; color:#555;">
                            <?= htmlspecialchars($article['description']) ?>
                        </div>
                        <small class="text-muted mb-3">
                            Par <?php echo isset($article['prenom']) ? htmlspecialchars($article['prenom'] . ' ' . $article['nom']) : 'Admin'; ?>,
                            le <?php echo date('d/m/Y', strtotime($article['date_creation'])); ?>
                        </small>
                        <div class="mt-auto text-end">
                            <a href="lire.php?id=<?= $article['id'] ?>" class="btn btn-futur px-4">Lire</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <!-- Pagination -->
    <?php 
    // Construire l'URL de base avec les paramètres de recherche
    $base_url = '?';
    $params = [];
    if ($search !== '') $params[] = 'search=' . urlencode($search);
    if ($theme_id > 0) $params[] = 'theme_id=' . $theme_id;
    if (!empty($params)) {
        $base_url .= implode('&', $params) . '&';
    }
    
    echo $pagination->render($base_url, 'page');
    ?>
</div>
</body>
</html>
