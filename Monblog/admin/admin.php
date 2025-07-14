<?php
// Inclusion de la configuration et du traitement des articles
include '../includes/config.php';
include '../includes/traitement.php';
include '../includes/email.php';

// Sécurisation de l'accès : seul un admin connecté peut accéder à cette page
if (empty($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../index.php');
    exit();
}

// -------------------
// Ce fichier gère le dashboard administrateur :
// - Affichage de tous les articles (admin + utilisateurs)
// - Validation/refus des articles utilisateurs
// - Modification/suppression d'articles
// -------------------

$message = '';
$erreurs = [];

// Ajout d'un article (admin)
if (isset($_POST['ajouter_article'])) {
    list($ok, $erreurs) = ajouter_article($pdo, $_POST, null, true);
    if ($ok) {
        $message = '<div class="alert alert-success">Article ajouté avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// Modification d'un article (admin)
if (isset($_POST['modifier_article'])) {
    list($ok, $erreurs) = modifier_article($pdo, $_POST, null, true);
    if ($ok) {
        $message = '<div class="alert alert-success">Article modifié avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// Suppression d'un article (admin)
if (isset($_GET['supprimer'])) {
    list($ok, $erreurs) = supprimer_article($pdo, (int)$_GET['supprimer'], null, true);
    if ($ok) {
        $message = '<div class="alert alert-success">Article supprimé avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// Validation/refus d'article (admin)
if (isset($_GET['valider'])) {
    $article_id = (int)$_GET['valider'];
    
    // Récupérer les infos de l'article et de l'utilisateur
    $stmt = $pdo->prepare("SELECT a.titre, a.user_id FROM articles a WHERE a.id = ?");
    $stmt->execute([$article_id]);
    $article_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $ok = valider_article_admin($pdo, $article_id);
    if ($ok) {
        $message = '<div class="alert alert-success">Article validé et publié.</div>';
        // Ajouter une notification interne si c'est un article d'utilisateur
        if ($article_info && $article_info['user_id']) {
            $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $notif_stmt->execute([
                $article_info['user_id'],
                'Votre article "' . $article_info['titre'] . '" a été validé et publié.'
            ]);
        }
    } else {
        $message = '<div class="alert alert-danger">Erreur lors de la validation.</div>';
    }
}

// Gestion du refus avec motif
if (isset($_POST['refuser_id']) && isset($_POST['motif_refus'])) {
    $article_id = (int)$_POST['refuser_id'];
    $motif = trim($_POST['motif_refus']);
    // Récupérer les infos de l'article et de l'utilisateur
    $stmt = $pdo->prepare("SELECT a.titre, a.user_id FROM articles a WHERE a.id = ?");
    $stmt->execute([$article_id]);
    $article_info = $stmt->fetch(PDO::FETCH_ASSOC);
    // Enregistrer le motif dans la base (ajouter colonne si besoin)
    $pdo->prepare("UPDATE articles SET statut = 'refusé', motif_refus = ? WHERE id = ?")->execute([$motif, $article_id]);
    // Notification interne
    if ($article_info && $article_info['user_id']) {
        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notif_stmt->execute([
            $article_info['user_id'],
            'Votre article "' . $article_info['titre'] . '" a été refusé. Motif : ' . $motif
        ]);
    }
    $message = '<div class="alert alert-warning">Article refusé.</div>';
}

// Récupération des articles
$articles = get_articles($pdo);

// Récupération des thèmes
$themes = get_themes($pdo);

// Récupération des statistiques
$stats = [];
// Nombre d'utilisateurs
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Nombre d'articles publiés
$stmt = $pdo->query("SELECT COUNT(*) as count FROM articles WHERE statut = 'publié'");
$stats['published_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Nombre d'articles en attente
$stmt = $pdo->query("SELECT COUNT(*) as count FROM articles WHERE statut = 'en attente'");
$stats['pending_articles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Compter le nombre d'articles en attente pour la sidebar
$nb_en_attente = $pdo->query("SELECT COUNT(*) FROM articles WHERE statut = 'en attente'")->fetchColumn();

// Détermination du mode d'édition (ajout ou modification)
$edit_mode = false;
$edit_article = null;
if (isset($_GET['modifier'])) {
    $edit_mode = true;
    $edit_article = get_article_by_id($pdo, (int)$_GET['modifier']);
    if (!$edit_article) {
        $message = '<div class="alert alert-danger">Article non trouvé.</div>';
    }
}

// Gestion des filtres admin
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_theme = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : 0;
$filter_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filter_auteur = isset($_GET['auteur']) ? trim($_GET['auteur']) : '';
// Filtrer les articles selon les critères
$filtered_articles = array_filter($articles, function($a) use ($search, $filter_theme, $filter_statut, $filter_auteur) {
    $ok = true;
    if ($search !== '') {
        $ok = stripos($a['titre'], $search) !== false || stripos($a['description'], $search) !== false;
    }
    if ($ok && $filter_theme > 0) {
        $ok = isset($a['theme_id']) && $a['theme_id'] == $filter_theme;
    }
    if ($ok && $filter_statut !== '' && $filter_statut !== 'all') {
        $ok = $a['statut'] === $filter_statut;
    }
    if ($ok && $filter_auteur !== '') {
        $nom = (isset($a['prenom']) ? $a['prenom'] : '') . ' ' . (isset($a['nom']) ? $a['nom'] : '');
        $ok = stripos($nom, $filter_auteur) !== false;
    }
    return $ok;
});
// Séparer à nouveau par statut
$articles_en_attente = array_filter($filtered_articles, function($a) { return $a['statut'] === 'en attente'; });
$articles_publies = array_filter($filtered_articles, function($a) { return $a['statut'] === 'publié'; });
$articles_refuses = array_filter($filtered_articles, function($a) { return $a['statut'] === 'refusé'; });

?>


<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
    <script src="../assets/js/script.js"></script>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <h4 class="px-3 py-2">Admin</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="admin.php"><i class="fa fa-dashboard"></i> Dashboard
                                <?php if ($nb_en_attente > 0): ?>
                                    <span class="badge bg-warning ms-2"><?= $nb_en_attente ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">Utilisateurs</a>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- Sidebar mobile (menu burger) -->
            <nav class="d-md-none navbar navbar-dark bg-dark mb-3">
                <div class="container-fluid">
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminMobileMenu" aria-controls="adminMobileMenu" aria-expanded="false" aria-label="Menu">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="adminMobileMenu">
                        <ul class="navbar-nav">
                            <li class="nav-item mb-2">
                                <a class="nav-link active" href="admin.php"><i class="fa fa-dashboard"></i> Dashboard
                                    <?php if ($nb_en_attente > 0): ?>
                                        <span class="badge bg-warning ms-2"><?= $nb_en_attente ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="users.php">Utilisateurs</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="dashboard-header d-flex justify-content-between align-items-center">
                    <h2>Gestion des articles</h2>
                    <div>
                        <a href="../index.php" class="btn btn-outline-primary me-2">Accueil</a>
                        <a href="../logout.php" class="btn btn-outline-danger">Déconnexion</a>
                    </div>
                </div>
                <?php if (!empty($message)) echo $message; ?>
                
                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="users-count">0</span></h4>
                                        <p class="card-text mb-0">Utilisateurs</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="published-count">0</span></h4>
                                        <p class="card-text mb-0">Articles publiés</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="pending-count">0</span></h4>
                                        <p class="card-text mb-0">En attente</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Barre de recherche et filtres -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Recherche titre ou description" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control" name="auteur" placeholder="Auteur" value="<?= htmlspecialchars($filter_auteur) ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="theme_id">
                                    <option value="0">Tous les thèmes</option>
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?= $theme['id'] ?>" <?= $filter_theme == $theme['id'] ? 'selected' : '' ?>><?= htmlspecialchars($theme['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="statut">
                                    <option value="all" <?= $filter_statut === 'all' ? 'selected' : '' ?>>Tous statuts</option>
                                    <option value="en attente" <?= $filter_statut === 'en attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="publié" <?= $filter_statut === 'publié' ? 'selected' : '' ?>>Publié</option>
                                    <option value="refusé" <?= $filter_statut === 'refusé' ? 'selected' : '' ?>>Refusé</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-primary w-100" type="submit">Filtrer</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Formulaire d'ajout ou de modification d'article -->
                <div class="card mb-4">
                    <div class="card-header">
                        <?php if ($edit_mode): ?>
                            Modifier l'article
                        <?php else: ?>
                            Ajouter un nouvel article
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="" enctype="multipart/form-data">
                            <?php if ($edit_mode): ?>
                                <input type="hidden" name="id" value="<?= htmlspecialchars($edit_article['id']) ?>">
                            <?php endif; ?>
                            <!-- Génération du token CSRF -->
                            <?php
                            if (empty($_SESSION['csrf_token'])) {
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            }
                            $csrf_token = $_SESSION['csrf_token'];
                            ?>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div class="mb-3">
                                <label for="theme_id" class="form-label">Thème</label>
                                <select class="form-select" id="theme_id" name="theme_id" required>
                                    <option value="">-- Choisir un thème --</option>
                                    <?php foreach ($themes as $theme): ?>
                                        <option value="<?= $theme['id'] ?>" <?= ($edit_mode && $edit_article['theme_id'] == $theme['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($theme['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="titre" class="form-label">Titre</label>
                                <input type="text" class="form-control" id="titre" name="titre" required value="<?= $edit_mode ? htmlspecialchars($edit_article['titre']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <input type="text" class="form-control" id="description" name="description" maxlength="255" required value="<?= $edit_mode ? htmlspecialchars($edit_article['description']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label for="image" class="form-label">Image</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                <?php if ($edit_mode && !empty($edit_article['image'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($edit_article['image']) ?>" class="img-thumb mt-2" alt="Image actuelle">
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="contenu" class="form-label">Contenu</label>
                                <textarea class="form-control" id="contenu" name="contenu" rows="5" required><?= $edit_mode ? htmlspecialchars($edit_article['contenu']) : '' ?></textarea>
                            </div>
                            <?php if ($edit_mode): ?>
                                <button type="submit" name="modifier_article" class="btn btn-warning">Modifier</button>
                                <a href="admin.php" class="btn btn-secondary">Annuler</a>
                            <?php else: ?>
                                <button type="submit" name="ajouter_article" class="btn btn-primary">Ajouter</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <!-- Tableau des articles EN ATTENTE -->
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark fw-bold">Articles en attente de validation</div>
                    <div class="card-body">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>Statut</th>
                                    <th>Valider</th>
                                    <th>Refuser</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($articles_en_attente)): ?>
                                    <tr><td colspan="8" class="text-center">Aucun article en attente.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($articles_en_attente as $article): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($article['id']) ?></td>
                                            <td><?= htmlspecialchars($article['titre']) ?></td>
                                            <td><?= isset($article['prenom']) ? htmlspecialchars($article['prenom'] . ' ' . $article['nom']) : 'Admin' ?></td>
                                            <td><span class="badge bg-warning text-dark">En attente</span></td>
                                            <td><a href="?valider=<?= $article['id'] ?>" class="btn btn-sm btn-success">Valider</a></td>
                                            <td>
                                                <button class="btn btn-sm btn-secondary" onclick="showRefusForm(<?= $article['id'] ?>)">Refuser</button>
                                                <form method="post" id="refus-form-<?= $article['id'] ?>" style="display:none; margin-top:5px;">
                                                    <input type="hidden" name="refuser_id" value="<?= $article['id'] ?>">
                                                    <input type="text" name="motif_refus" class="form-control form-control-sm mb-1" placeholder="Motif du refus" required>
                                                    <button type="submit" class="btn btn-sm btn-danger">Confirmer le refus</button>
                                                </form>
                                            </td>
                                            <td><a href="?modifier=<?= $article['id'] ?>" class="btn btn-sm btn-warning">Modifier</a></td>
                                            <td><a href="?supprimer=<?= $article['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?');">Supprimer</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Tableau des articles PUBLIÉS -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white fw-bold">Articles publiés</div>
                    <div class="card-body">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>Statut</th>
                                    <th>Validé par</th>
                                    <th>Date validation</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($articles_publies)): ?>
                                    <tr><td colspan="8" class="text-center">Aucun article publié.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($articles_publies as $article): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($article['id']) ?></td>
                                            <td><?= htmlspecialchars($article['titre']) ?></td>
                                            <td><?= isset($article['prenom']) ? htmlspecialchars($article['prenom'] . ' ' . $article['nom']) : 'Admin' ?></td>
                                            <td><span class="badge bg-success">Publié</span></td>
                                            <td><?= htmlspecialchars($article['admin_validation'] ?? 'Admin') ?></td>
                                            <td><?= !empty($article['date_validation']) ? date('d/m/Y H:i', strtotime($article['date_validation'])) : '' ?></td>
                                            <td><a href="?modifier=<?= $article['id'] ?>" class="btn btn-sm btn-warning">Modifier</a></td>
                                            <td><a href="?supprimer=<?= $article['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?');">Supprimer</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Tableau des articles REFUSÉS -->
                <div class="card mb-4">
                    <div class="card-header bg-danger text-white fw-bold">Articles refusés</div>
                    <div class="card-body">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Auteur</th>
                                    <th>Statut</th>
                                    <th>Motif du refus</th>
                                    <th>Refusé par</th>
                                    <th>Date refus</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($articles_refuses)): ?>
                                    <tr><td colspan="9" class="text-center">Aucun article refusé.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($articles_refuses as $article): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($article['id']) ?></td>
                                            <td><?= htmlspecialchars($article['titre']) ?></td>
                                            <td><?= isset($article['prenom']) ? htmlspecialchars($article['prenom'] . ' ' . $article['nom']) : 'Admin' ?></td>
                                            <td><span class="badge bg-danger">Refusé</span></td>
                                            <td><?= htmlspecialchars($article['motif_refus'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($article['admin_validation'] ?? 'Admin') ?></td>
                                            <td><?= !empty($article['date_validation']) ? date('d/m/Y H:i', strtotime($article['date_validation'])) : '' ?></td>
                                            <td><a href="?modifier=<?= $article['id'] ?>" class="btn btn-sm btn-warning">Modifier</a></td>
                                            <td><a href="?supprimer=<?= $article['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?');">Supprimer</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Script pour l'animation des statistiques -->
    <script>
        // Données des statistiques depuis PHP
        const statsData = {
            users: <?= $stats['users'] ?>,
            published: <?= $stats['published_articles'] ?>,
            pending: <?= $stats['pending_articles'] ?>
        };
        
        // Fonction d'animation de comptage
        function animateCounter(elementId, targetValue, duration = 1500) {
            const element = document.getElementById(elementId);
            const startValue = 0;
            const increment = targetValue / (duration / 16); // 60 FPS
            let currentValue = startValue;
            
            const timer = setInterval(() => {
                currentValue += increment;
                if (currentValue >= targetValue) {
                    currentValue = targetValue;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(currentValue);
            }, 16);
        }
        
        // Lancer l'animation quand la page est chargée
        document.addEventListener('DOMContentLoaded', function() {
            // Délai pour que l'utilisateur voie l'effet
            setTimeout(() => {
                animateCounter('users-count', statsData.users, 1200);
                animateCounter('published-count', statsData.published, 1200);
                animateCounter('pending-count', statsData.pending, 1200);
            }, 300);
        });
    </script>
    <!-- Ajout d'un script JS pour afficher le champ motif lors du clic sur Refuser -->
    <script>
function showRefusForm(articleId) {
    var form = document.getElementById('refus-form-' + articleId);
    if (form) form.style.display = 'block';
}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.getElementById('contenu');
    if (textarea) {
        CKEDITOR.replace('contenu', {
            language: 'fr',
            extraPlugins: 'image2,uploadimage',
            removePlugins: 'image',
            toolbar: [
                { name: 'clipboard', items: [ 'Cut', 'Copy', 'Paste', 'Undo', 'Redo' ] },
                { name: 'styles', items: [ 'Format', 'Font', 'FontSize' ] },
                { name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat' ] },
                { name: 'colors', items: [ 'TextColor', 'BGColor' ] },
                { name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Blockquote' ] },
                { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule', 'SpecialChar' ] },
                { name: 'links', items: [ 'Link', 'Unlink' ] },
                { name: 'tools', items: [ 'Maximize' ] }
            ],
            filebrowserUploadUrl: '../uploads/', // à adapter selon le script d'upload
            filebrowserUploadMethod: 'form'
        });
    }
});
</script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>