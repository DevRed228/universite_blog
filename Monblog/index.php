<?php
// Inclusion de la configuration de la base de données
include 'includes/config.php';

// -------------------
// Gestion du thème choisi (GET ou cookie)
// -------------------
if (isset($_GET['theme_id']) && isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted') {
    setcookie('theme_pref', $_GET['theme_id'], time() + 30*24*3600, '/');
}
$theme_id = isset($_GET['theme_id']) ? (int)$_GET['theme_id'] : (isset($_COOKIE['theme_pref']) ? (int)$_COOKIE['theme_pref'] : 0);

// Si aucun filtre n'est sélectionné et utilisateur connecté, récupérer ses thèmes préférés
$user_theme_ids = [];
if ($theme_id === 0 && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT theme_id FROM user_themes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_theme_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Récupérer le thème de l'utilisateur (s'il est connecté)
$user_theme_id = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT theme_id FROM user_themes WHERE user_id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $user_theme_id = $stmt->fetchColumn();
}

// Récupérer les articles publiés avec l'auteur (admin ou utilisateur)
if ($user_theme_id) {
    // 1. Jusqu'à 3 articles du thème préféré
    $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN themes t ON a.theme_id = t.id
        WHERE a.statut = 'publié' AND a.theme_id = ?
        ORDER BY a.date_creation DESC LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_theme_id]);
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // 2. Compléter avec d'autres articles d'autres thèmes si moins de 3
    if (count($articles) < 3) {
        $ids = array_column($articles, 'id');
        $placeholders = !empty($ids) ? implode(',', array_fill(0, count($ids), '?')) : '';
        $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
            FROM articles a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN themes t ON a.theme_id = t.id
            WHERE a.statut = 'publié' AND a.theme_id != ?";
        if (!empty($ids)) {
            $sql .= " AND a.id NOT IN ($placeholders)";
        }
        $sql .= " ORDER BY a.date_creation DESC LIMIT " . (3 - count($articles));
        $params = array_merge([$user_theme_id], $ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $more = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $articles = array_merge($articles, $more);
    }
} else {
    $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        LEFT JOIN themes t ON a.theme_id = t.id
        WHERE a.statut = 'publié'
        ORDER BY a.date_creation DESC LIMIT 3";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Limiter l'affichage à 3 articles maximum
$articles = array_slice($articles, 0, 3);

// -------------------
// Gestion de la session et de la connexion/déconnexion
// -------------------
// Démarrage de la session AVANT tout HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}

// Traitement du formulaire de connexion
$loginError = '';
$loginSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $loginEmail = $_POST['email'] ?? '';
    $loginPwd = $_POST['pwd'] ?? '';

    if (!empty($loginEmail) && !empty($loginPwd)) {
        // Connexion admin centralisée
        require_once __DIR__ . '/includes/traitement.php';
        list($isAdmin, $msg) = connecter_admin($pdo, $loginEmail, $loginPwd);
        if ($isAdmin) {
            session_regenerate_id(true);
            $loginSuccess = $msg;
            header('Location: admin/admin.php');
            exit();
        } else {
            // Sinon, vérifier dans la table users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$loginEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($loginPwd, $user['pwd'])) {
                // Vérifier si l'utilisateur est banni
                if (!empty($user['banni']) && $user['banni'] == 1) {
                    $loginError = "Votre compte a été banni. Contactez l'administrateur pour plus d'informations.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['is_admin'] = false;
                    $loginSuccess = "Connexion réussie ! Redirection dans 3 secondes...";
                    header('Location: index.php');
                    exit();
                }
            } else {
                $loginError = $msg;
            }
        }
    } else {
        $loginError = "Veuillez remplir tous les champs.";
    }
}

// Traitement du formulaire d'inscription
$registerError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pwd = $_POST['pwd'] ?? '';
    $cpwd = $_POST['cpwd'] ?? '';
    $themesChoisis = isset($_POST['theme']) ? [$_POST['theme']] : [];

    // Validation
    if (empty($prenom) || empty($nom) || empty($email) || empty($pwd) || empty($cpwd)) {
        $registerError = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = "Adresse email invalide.";
    } elseif (strlen($pwd) < 8 || !preg_match('/\d/', $pwd)) {
        $registerError = "Le mot de passe doit contenir au moins 8 caractères et un chiffre.";
    } elseif ($pwd !== $cpwd) {
        $registerError = "Les mots de passe ne correspondent pas.";
    } elseif (count($themesChoisis) !== 1) {
        $registerError = "Veuillez sélectionner un seul thème.";
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $registerError = "Cet email est déjà utilisé.";
        } else {
            // Insérer l'utilisateur
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, pwd) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$prenom, $nom, $email, $hash])) {
                $userId = $pdo->lastInsertId();
                // Associer le thème unique
                $stmtTheme = $pdo->prepare("INSERT INTO user_themes (user_id, theme_id) VALUES (?, ?)");
                $stmtTheme->execute([$userId, $themesChoisis[0]]);
                // Connexion automatique après inscription
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['nom'] = $nom;
                $_SESSION['is_admin'] = false;
                // Envoyer un email de bienvenue
                require_once __DIR__ . '/includes/email.php';
                send_welcome_email($email, $prenom);
                $registerSuccess = "Inscription réussie ! Redirection dans 3 secondes...";
                header('Location: index.php');
                exit();
            } else {
                $registerError = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<?php
// Démarrage de la session AVANT tout HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}

// Inclusion de la connexion à la base
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/traitement.php';
require_once __DIR__ . '/includes/email.php';

$loginError = '';
$loginSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $loginEmail = $_POST['email'] ?? '';
    $loginPwd = $_POST['pwd'] ?? '';

    if (!empty($loginEmail) && !empty($loginPwd)) {
        list($isAdmin, $msg) = connecter_admin($loginEmail, $loginPwd);
        if ($isAdmin) {
            session_regenerate_id(true);
            $loginSuccess = $msg;
            header('Location: admin/admin.php');
            exit();
        } else {
            // Sinon, vérifier dans la table users
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$loginEmail]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($loginPwd, $user['pwd'])) {
                // Vérifier si l'utilisateur est banni
                if (!empty($user['banni']) && $user['banni'] == 1) {
                    $loginError = "Votre compte a été banni. Contactez l'administrateur pour plus d'informations.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['prenom'] = $user['prenom'];
                    $_SESSION['nom'] = $user['nom'];
                    $_SESSION['is_admin'] = false;
                    $loginSuccess = "Connexion réussie ! Redirection dans 3 secondes...";
                    header('Location: index.php');
                    exit();
                }
            } else {
                $loginError = $msg;
            }
        }
    } else {
        $loginError = "Veuillez remplir tous les champs.";
    }
}

// Traitement du formulaire d'inscription
$registerError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $prenom = trim($_POST['prenom'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pwd = $_POST['pwd'] ?? '';
    $cpwd = $_POST['cpwd'] ?? '';
    $themesChoisis = isset($_POST['theme']) ? [$_POST['theme']] : [];

    // Validation
    if (empty($prenom) || empty($nom) || empty($email) || empty($pwd) || empty($cpwd)) {
        $registerError = "Tous les champs sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = "Adresse email invalide.";
    } elseif (strlen($pwd) < 8 || !preg_match('/\d/', $pwd)) {
        $registerError = "Le mot de passe doit contenir au moins 8 caractères et un chiffre.";
    } elseif ($pwd !== $cpwd) {
        $registerError = "Les mots de passe ne correspondent pas.";
    } elseif (count($themesChoisis) !== 1) {
        $registerError = "Veuillez sélectionner un seul thème.";
    } else {
        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $registerError = "Cet email est déjà utilisé.";
        } else {
            // Insérer l'utilisateur
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (prenom, nom, email, pwd) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$prenom, $nom, $email, $hash])) {
                $userId = $pdo->lastInsertId();
                // Associer le thème unique
                $stmtTheme = $pdo->prepare("INSERT INTO user_themes (user_id, theme_id) VALUES (?, ?)");
                $stmtTheme->execute([$userId, $themesChoisis[0]]);
                // Connexion automatique après inscription
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;
                $_SESSION['prenom'] = $prenom;
                $_SESSION['nom'] = $nom;
                $_SESSION['is_admin'] = false;
                // Envoyer un email de bienvenue
                require_once __DIR__ . '/includes/email.php';
                send_welcome_email($email, $prenom);
                $registerSuccess = "Inscription réussie ! Redirection dans 3 secondes...";
                header('Location: index.php');
                exit();
            } else {
                $registerError = "Erreur lors de l'inscription. Veuillez réessayer.";
            }
        }
    }
}

// Toujours charger la liste des thèmes pour le formulaire d'inscription
$themes = $pdo->query("SELECT id, name FROM themes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniversitéBlog - Plateforme académique</title>
    <!-- Bootstrap CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background: linear-gradient(120deg,rgb(255, 255, 255) 0%,rgb(255, 255, 255) 100%);
            min-height: 100vh;
        }
        .card-article {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(6px);
            transition: transform 0.2s, box-shadow 0.2s;
            color: #fff;
        }
        .card-article:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 16px 40px 0 rgba(31, 38, 135, 0.35);
            background: rgba(255,255,255,0.10);
        }
        .card-article .card-img-top {
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            height: 220px;
            object-fit: cover;
            background: #222;
        }
        .badge-theme {
            background: linear-gradient(90deg, #ff512f 0%, #dd2476 100%);
            color: #fff;
            font-size: 1.05rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 0.55em 1.2em;
            box-shadow: 0 0 8px 2px rgba(221,36,118,0.25), 0 2px 8px 0 rgba(0,0,0,0.10);
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.18);
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
            margin: 0 auto 2.5rem auto;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
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
                    <li class="nav-item">
                        <a class="nav-link" href="essai.php">Articles</a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#">Forum</a>
                    </li> -->
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li> -->
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin/admin.php">Admin</a>
                        </li>
                    <?php endif; ?>
                    
                        <li class="nav-item">
                            <a class="nav-link" href="users/dash.php">Mon dashboard</a>
                        </li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item position-relative">
                            <a class="nav-link" href="users/notifications.php" title="Notifications">
                                <i class="fa fa-bell"></i>
                                <?php
                                $notif_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0");
                                $notif_count_stmt->execute([$_SESSION['user_id']]);
                                $notif_count = $notif_count_stmt->fetchColumn();
                                ?>
                                <?php if ($notif_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.8em;">
                                        <?= $notif_count ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Authentication Links -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- Afficher le menu profil -->
                    <div class="dropdown ms-3" id="userDropdownContainer">
                        <a href="#" class="d-flex align-items-center text-white text-decoration-none dropdown-toggle"
                            id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?>&background=3498db&color=fff"
                                alt="Profile" class="profile-img me-2">
                            <span
                                id="usernameDisplay"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['nom']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark text-small shadow" aria-labelledby="dropdownUser1">
                            <li><a class="dropdown-item" href="#">Profil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Déconnexion</a></li>
                        </ul>
                    </div>

                <?php else: ?>
                    <!-- Afficher les boutons connexion/inscription -->
                    <div class="d-flex">
                        <button class="btn btn-outline-light me-2" data-bs-toggle="modal"
                            data-bs-target="#loginModal">Connexion</button>
                        <button class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#registerModal">Inscription</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Explorez le savoir universitaire</h1>
            <p class="lead mb-5">Rejoignez notre communauté académique et personnalisez votre expérience en
                sélectionnant vos thèmes d'intérêt.</p>
            <div class="d-flex justify-content-center align-items-center mt-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="users/profile.php" class="btn btn-success btn-lg px-4">Voir mon profil</a>
                <?php else: ?>
                    <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Se
                        connecte</button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Creer son
                        compte</button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php
    // Récupérer les thèmes favoris de l'utilisateur connecté
    $user_theme_ids = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT theme_id FROM user_themes WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_theme_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    // Récupérer jusqu'à 5 articles des thèmes favoris
    $articles = [];
    if (!empty($user_theme_ids)) {
        $in = implode(',', array_fill(0, count($user_theme_ids), '?'));
        $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
            FROM articles a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN themes t ON a.theme_id = t.id
            WHERE a.statut = 'publié' AND a.theme_id IN ($in)
            ORDER BY a.date_creation DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($user_theme_ids);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Si moins de 5 articles, compléter avec d'autres articles publiés (hors de ses thèmes)
    if (!empty($user_theme_ids) && count($articles) < 3) {
        $ids = array_column($articles, 'id');
        $placeholders = !empty($ids) ? implode(',', array_fill(0, count($ids), '?')) : '';
        $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
            FROM articles a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN themes t ON a.theme_id = t.id
            WHERE a.statut = 'publié' AND a.theme_id NOT IN ($in)";
        if (!empty($ids)) {
            $sql .= " AND a.id NOT IN ($placeholders)";
        }
        $sql .= " ORDER BY a.date_creation DESC LIMIT " . (3 - count($articles));
        $params = array_merge($user_theme_ids, $ids);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $more = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $articles = array_merge($articles, $more);
    }
    // Si pas connecté ou pas de thème, afficher les 5 derniers articles publiés
    if (empty($user_theme_ids)) {
        $sql = "SELECT a.id, a.titre, a.contenu, a.date_creation, a.image, a.description, a.user_id, a.statut, u.prenom, u.nom, t.name as theme_name
            FROM articles a
            LEFT JOIN users u ON a.user_id = u.id
            LEFT JOIN themes t ON a.theme_id = t.id
            WHERE a.statut = 'publié'
            ORDER BY a.date_creation DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    ?>
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
                            <h5 class="card-title mb-2" style="font-size:1.2rem; font-weight:600; letter-spacing:0.5px; color:#222;"><?= htmlspecialchars($article['titre']) ?></h5>
                            <p class="card-text mb-3" style="font-size:1rem; color:#555;">
                                <?= htmlspecialchars(mb_strimwidth($article['description'], 0, 120, '...')) ?>
                            </p>
                            <small class="text-muted">
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
    </div>
    <div class="text-center my-4"><a href="essai.php" class="btn btn-primary btn-lg">Voir plus d'articles</a></div>


    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Connexion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger"><?php echo $loginError; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($loginSuccess)): ?>
                        <div class="alert alert-success"><?php echo $loginSuccess; ?></div>
                    <?php endif; ?>
                    <form id="loginForm" method="POST">
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label">Adresse email</label>
                            <input type="email" class="form-control" id="loginEmail" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="loginPassword" name="pwd" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">Se souvenir de moi</label>
                            </div>
                            <a href="#" class="text-decoration-none">Mot de passe oublié?</a>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary w-100">Se connecter</button>
                    </form>
                    <div class="text-center mt-3">
                        <p>Pas encore membre? <a href="#" class="text-decoration-none" data-bs-toggle="modal"
                                data-bs-target="#registerModal" data-bs-dismiss="modal">Créer un compte</a></p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Registration Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="registerModalLabel">Inscription</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Registration Form Steps -->
                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger"><?php echo $registerError; ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <!-- Step 1: Personal Information -->
                        <div class="form-step active">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="firstName" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" name="prenom" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="lastName" class="form-label">Nom</label>
                                    <input type="text" class="form-control" name="nom" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" name="pwd" required>
                                <div class="form-text">Minimum 8 caractères avec au moins un chiffre.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirmPassword" name="cpwd" required>
                            </div>
                            <h5 class="mb-3">Choisissez votre thème</h5>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <select class="form-select" name="theme" required>
                                        <option value="">-- Choisir un thème --</option>
                                        <?php foreach ($themes as $theme): ?>
                                            <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required>
                                <label class="form-check-label" for="termsCheck">
                                    J'accepte les <a href="#">conditions d'utilisation</a> et la <a href="#">politique
                                        de confidentialité</a>
                                </label>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="register" class="btn btn-primary">Finaliser son
                                    inscription</button>
                            </div>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>Déjà membre? <a href="#" class="text-decoration-none" data-bs-toggle="modal"
                                data-bs-target="#loginModal" data-bs-dismiss="modal">Se connecter</a></p>
                    </div>

                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>