<?php
// Démarrage de la session et inclusion de la configuration
session_start();
require_once '../includes/config.php';
require_once '../includes/traitement.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Récupérer la liste des thèmes pour le formulaire
$themes = $pdo->query("SELECT id, name FROM themes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Message d'alerte à afficher à l'utilisateur
$message = '';
$erreurs = [];

// -------------------
// Publication d'un nouvel article
// -------------------
if (isset($_POST['ajouter_article'])) {
    list($ok, $erreurs) = ajouter_article($pdo, $_POST, $user_id, false);
    if ($ok) {
        $message = '<div class="alert alert-success">Article soumis à validation.</div>';
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// -------------------
// Suppression d'un article de l'utilisateur
// -------------------
if (isset($_GET['supprimer'])) {
    list($ok, $erreurs) = supprimer_article($pdo, (int)$_GET['supprimer'], $user_id, false);
    if ($ok) {
        $message = '<div class="alert alert-success">Article supprimé.</div>';
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// -------------------
// Préparation pour modification d'un article
// -------------------
$edit_mode = false;
$edit_article = null;
if (isset($_GET['modifier'])) {
    $id = (int)$_GET['modifier'];
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $edit_article = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit_article) {
        $edit_mode = true;
    }
}

// -------------------
// Traitement de la modification d'un article
// -------------------
if (isset($_POST['modifier_article'])) {
    list($ok, $erreurs) = modifier_article($pdo, $_POST, $user_id, false);
    if ($ok) {
        $message = '<div class="alert alert-success">Article modifié et soumis à validation.</div>';
        $edit_mode = false;
        $edit_article = null;
    } else {
        $message = '<div class="alert alert-danger">' . implode('<br>', $erreurs) . '</div>';
    }
}

// -------------------
// Récupérer les articles de l'utilisateur pour affichage
// -------------------
$sql = "SELECT a.id, a.titre, a.description, a.image, t.name AS theme_name, a.date_creation, a.statut FROM articles a JOIN themes t ON a.theme_id = t.id WHERE a.user_id = ? ORDER BY a.date_creation DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------
// Notifications utilisateur
// -------------------
// Marquer toutes les notifications comme lues si demandé
if (isset($_POST['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?")->execute([$user_id]);
}
// Récupérer les notifications (non lues d'abord)
$notif_stmt = $pdo->prepare("SELECT id, message, lu, date FROM notifications WHERE user_id = ? ORDER BY lu ASC, date DESC LIMIT 10");
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon espace - Publier un article</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
    <script src="../assets/js/script.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: var(--primary-color, #2c3e50);
            color: #fff;
            padding: 2rem 1rem 1rem 1rem;
            position: sticky;
            top: 0;
        }
        .sidebar .nav-link {
            color: #fff;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.8em;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            background: var(--secondary-color, #3498db);
            color: #fff;
        }
        .sidebar .fa {
            width: 22px;
            text-align: center;
        }
        @media (max-width: 991px) {
            .sidebar {
                min-height: auto;
                position: static;
                padding: 1rem 0.5rem;
            }
        }
        @media (max-width: 991px) {
            .dashboard-header { flex-direction: column; align-items: flex-start !important; gap: 1rem; }
            .card .card-body { padding: 1rem !important; }
            .table-responsive { margin-bottom: 1.5rem; }
            .sidebar { min-height: auto; position: static; padding: 1rem 0.5rem; }
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block bg-dark sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="../index.php"><i class="fa fa-home"></i> Accueil</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="dash.php"><i class="fa fa-file-alt"></i> Mes articles</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="notifications.php"><i class="fa fa-bell"></i> Notifications
                            <?php if ($notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND lu = 0")->fetchColumn()): ?>
                                <span class="badge bg-danger ms-2"><?= $notif_count ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="profile.php"><i class="fa fa-user"></i> Profil</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="../logout.php"><i class="fa fa-sign-out-alt"></i> Déconnexion</a>
                    </li>
                </ul>
            </div>
        </nav>
        <!-- Sidebar mobile (menu burger) -->
        <nav class="d-md-none navbar navbar-dark bg-dark mb-3">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userMobileMenu" aria-controls="userMobileMenu" aria-expanded="false" aria-label="Menu">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="userMobileMenu">
                    <ul class="navbar-nav">
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../index.php"><i class="fa fa-home"></i> Accueil</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="dash.php"><i class="fa fa-file-alt"></i> Mes articles</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="notifications.php"><i class="fa fa-bell"></i> Notifications
                                <?php if ($notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND lu = 0")->fetchColumn()): ?>
                                    <span class="badge bg-danger ms-2"><?= $notif_count ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="profile.php"><i class="fa fa-user"></i> Profil</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link" href="../logout.php"><i class="fa fa-sign-out-alt"></i> Déconnexion</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- Contenu principal -->
        <main class="col-md-10 ms-sm-auto px-md-4">
            <div class="dashboard-header d-flex justify-content-between align-items-center">
                <h2>Mon Dashboard</h2>
                <div class="d-md-none">
                    <a href="../logout.php" class="btn btn-outline-danger">Déconnexion</a>
                </div>
            </div>
            <?php if (!empty($message)) echo $message; ?>
            <!-- Formulaire d'ajout ou de modification d'article -->
            <div class="card mb-4">
                <div class="card-header">
                    <?php if ($edit_mode): ?>
                        Modifier l'article
                    <?php else: ?>
                        Publier un nouvel article
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?= htmlspecialchars($edit_article['id']) ?>">
                            <input type="hidden" name="old_image" value="<?= htmlspecialchars($edit_article['image']) ?>">
                        <?php endif; ?>
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
                            <a href="dash.php" class="btn btn-secondary">Annuler</a>
                        <?php else: ?>
                            <button type="submit" name="ajouter_article" class="btn btn-primary">Publier</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <!-- Tableau responsive -->
            <div class="table-responsive">
                <!-- Tableau des articles de l'utilisateur -->
                <div class="card">
                    <div class="card-header">Mes articles publiés</div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Thème</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                    <th>Modifier</th>
                                    <th>Supprimer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($articles)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Aucun article trouvé.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($articles as $article): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($article['id']) ?></td>
                                            <td><?= htmlspecialchars($article['titre']) ?></td>
                                            <td><?= htmlspecialchars($article['theme_name']) ?></td>
                                            <td><?= htmlspecialchars($article['date_creation']) ?></td>
                                            <td><?= htmlspecialchars($article['statut']) ?></td>
                                            <td>
                                                <a href="?modifier=<?= $article['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                            </td>
                                            <td>
                                                <a href="?supprimer=<?= $article['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?');">Supprimer</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

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
