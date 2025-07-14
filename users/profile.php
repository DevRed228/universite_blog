<?php
// Démarrage de la session et inclusion de la configuration
session_start();
require_once '../includes/config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// -------------------
// Récupérer les informations de l'utilisateur connecté
// -------------------
$user_id = $_SESSION['user_id'];
$sql = "SELECT prenom, nom, email, created_at FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les thèmes choisis par l'utilisateur
$sqlThemes = "SELECT t.name FROM themes t
              INNER JOIN user_themes ut ON ut.theme_id = t.id
              WHERE ut.user_id = ?";
$stmtThemes = $pdo->prepare($sqlThemes);
$stmtThemes->execute([$user_id]);
$userThemes = $stmtThemes->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les thèmes disponibles
$allThemes = $pdo->query("SELECT id, name FROM themes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Traitement du changement de thème
if (isset($_POST['update_theme']) && isset($_POST['theme']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $newTheme = (int)$_POST['theme'];
    // Vérifier si le thème est déjà celui de l'utilisateur
    $stmt = $pdo->prepare('SELECT theme_id FROM user_themes WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $currentTheme = $stmt->fetchColumn();
    if ($currentTheme != $newTheme) {
        // Supprimer l'ancien thème
        $pdo->prepare('DELETE FROM user_themes WHERE user_id = ?')->execute([$user_id]);
        // Ajouter le nouveau thème
        $pdo->prepare('INSERT INTO user_themes (user_id, theme_id) VALUES (?, ?)')->execute([$user_id, $newTheme]);
        // Rafraîchir la page pour afficher le nouveau thème
        header('Location: profile.php?theme_updated=1');
        exit();
    } else {
        header('Location: profile.php?theme_updated=0');
        exit();
    }
}

// -------------------
// Génération du token CSRF pour suppression de compte
// -------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// -------------------
// Suppression du compte utilisateur (et de ses articles)
// -------------------
if (isset($_POST['delete_account']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $user_id = $_SESSION['user_id'];
    // Supprimer les articles de l'utilisateur
    $stmt = $pdo->prepare('DELETE FROM articles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    // Supprimer l'utilisateur
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    // Déconnexion
    session_unset();
    session_destroy();
    header('Location: ../index.php?account_deleted=1');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil de l'utilisateur</title>
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-card {
            max-width: 500px;
            margin: 40px auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            border-radius: 16px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-top: -60px;
            border: 4px solid #fff;
            background: #eee;
        }
        .theme-badge {
            margin: 2px 4px 2px 0;
        }
    </style>
</head>
<body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">UniversitéBlog</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Accueil</a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#">Thèmes</a>
                    </li> -->
                    <li class="nav-item">
                        <a class="nav-link" href="../essai.php">Articles</a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#">Forum</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li> -->
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="profile-card bg-white p-4 mt-5">
            <div class="text-center">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['prenom'].' '.$user['nom']); ?>&background=3498db&color=fff"
                     alt="Avatar" class="profile-avatar shadow">
                <h3 class="mt-3 mb-0"><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h3>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="small text-secondary mb-4">Inscrit le <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
            </div>
            <hr>
            <h5 class="mb-3">Thème choisi</h5>
            <?php if (!empty($userThemes)): ?>
                <div>
                    <span class="badge bg-primary theme-badge"><?php echo htmlspecialchars($userThemes[0]); ?></span>
                </div>
            <?php else: ?>
                <div class="alert alert-info">Aucun thème sélectionné.</div>
            <?php endif; ?>
            <form method="post" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="mb-3">
                    <label for="theme" class="form-label">Changer de thème préféré</label>
                    <select class="form-select" name="theme" id="theme" required>
                        <option value="">-- Choisir un thème --</option>
                        <?php foreach ($allThemes as $theme): ?>
                            <option value="<?= $theme['id'] ?>" <?= (!empty($userThemes) && $userThemes[0] == $theme['name']) ? 'selected' : '' ?>><?= htmlspecialchars($theme['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="update_theme" class="btn btn-primary">Mettre à jour</button>
                <?php if (isset($_GET['theme_updated']) && $_GET['theme_updated'] == 1): ?>
                    <div class="alert alert-success mt-2">Thème mis à jour !</div>
                <?php elseif (isset($_GET['theme_updated']) && $_GET['theme_updated'] == 0): ?>
                    <div class="alert alert-warning mt-2">Vous avez déjà ce thème.</div>
                <?php endif; ?>
            </form>
            <hr>
            <div class="text-center">
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                    <a href="dash.php" class="btn btn-primary">Mon dashboard</a>
                    <a href="../logout.php" class="btn btn-outline-danger">Déconnexion</a>
                    <a href="../index.php" class="btn btn btn-outline-primary">Retour à l'accueil</a>
                    <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est irréversible.');" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <button type="submit" name="delete_account" class="btn btn-danger">Supprimer mon compte</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>