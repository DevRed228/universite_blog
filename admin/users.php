<?php
// Inclusion de la configuration et du traitement des articles
include '../includes/config.php';
include '../includes/traitement.php';

// Sécurisation de l'accès : seul un admin connecté peut accéder à cette page
if (empty($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}

// -------------------
// Ce fichier gère la gestion des utilisateurs dans le dashboard admin
// - Affichage de tous les utilisateurs
// - Informations détaillées (thèmes, articles, etc.)
// - Actions possibles (suppression, etc.)
// -------------------

$message = '';
$erreurs = [];

// Suppression d'un utilisateur (admin)
if (isset($_GET['supprimer'])) {
    $user_id = (int)$_GET['supprimer'];
    
    // Vérifier que l'utilisateur existe
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Supprimer les articles de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM articles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Supprimer les associations de thèmes
        $stmt = $pdo->prepare("DELETE FROM user_themes WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Supprimer l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $message = '<div class="alert alert-success">Utilisateur supprimé avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">Utilisateur non trouvé.</div>';
    }
}

// Bannissement/Débannissement d'un utilisateur
if (isset($_GET['bannir'])) {
    $user_id = (int)$_GET['bannir'];
    $stmt = $pdo->prepare("UPDATE users SET banni = 1 WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $message = '<div class="alert alert-warning">Utilisateur banni avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">Erreur lors du bannissement.</div>';
    }
}

if (isset($_GET['debannir'])) {
    $user_id = (int)$_GET['debannir'];
    $stmt = $pdo->prepare("UPDATE users SET banni = 0 WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        $message = '<div class="alert alert-success">Utilisateur débanni avec succès.</div>';
    } else {
        $message = '<div class="alert alert-danger">Erreur lors du débannissement.</div>';
    }
}

// Récupération de tous les utilisateurs avec leurs statistiques
$sql = "SELECT u.id, u.prenom, u.nom, u.email, u.created_at, u.banni,
        COUNT(DISTINCT a.id) as nb_articles,
        COUNT(DISTINCT CASE WHEN a.statut = 'publié' THEN a.id END) as nb_articles_publies,
        COUNT(DISTINCT CASE WHEN a.statut = 'en attente' THEN a.id END) as nb_articles_attente,
        t.name as theme
        FROM users u
        LEFT JOIN articles a ON u.id = a.user_id
        LEFT JOIN user_themes ut ON u.id = ut.user_id
        LEFT JOIN themes t ON ut.theme_id = t.id
        GROUP BY u.id
        ORDER BY u.created_at DESC";

$stmt = $pdo->query($sql);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$total_users = count($users);
$total_articles = array_sum(array_column($users, 'nb_articles'));
$total_published = array_sum(array_column($users, 'nb_articles_publies'));
$total_pending = array_sum(array_column($users, 'nb_articles_attente'));
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Gestion des utilisateurs</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="../assets/js/script.js"></script>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <h4 class="px-3 py-2">Admin</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Articles</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">Utilisateurs</a>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="dashboard-header d-flex justify-content-between align-items-center">
                    <h2>Gestion des utilisateurs</h2>
                    <div>
                        <a href="../index.php" class="btn btn-outline-primary me-2">Accueil</a>
                        <a href="../logout.php" class="btn btn-outline-danger">Déconnexion</a>
                    </div>
                </div>
                <?php if (!empty($message)) echo $message; ?>
                
                <!-- Statistiques utilisateurs -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="total-users">0</span></h4>
                                        <p class="card-text mb-0">Total utilisateurs</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="total-articles">0</span></h4>
                                        <p class="card-text mb-0">Total articles</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-newspaper fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="published-articles">0</span></h4>
                                        <p class="card-text mb-0">Articles publiés</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><span id="pending-articles">0</span></h4>
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
                
                <!-- Liste des utilisateurs -->
                <div class="card">
                    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <h5 class="mb-0">Liste des utilisateurs (<?= $total_users ?>)</h5>
                        <input type="search" id="userSearch" class="form-control w-auto" placeholder="Rechercher un utilisateur...">
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center text-muted">Aucun utilisateur trouvé.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nom complet</th>
                                            <th>Email</th>
                                            <th>Statut</th>
                                            <th>Thème</th>
                                            <th>Articles</th>
                                            <th>Inscription</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr class="<?= $user['banni'] ? 'table-danger' : '' ?>">
                                                <td><?= htmlspecialchars($user['id']) ?></td>
                                                <td>
                                                    <strong><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></strong>
                                                </td>
                                                <td><?= htmlspecialchars($user['email']) ?></td>
                                                <td>
                                                    <?php if ($user['banni']): ?>
                                                        <span class="badge bg-danger">BANNI</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Actif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($user['theme'])): ?>
                                                        <span class="badge bg-secondary"><?= htmlspecialchars($user['theme']) ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucun thème</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <small class="text-muted">Total: <?= $user['nb_articles'] ?></small>
                                                        <small class="text-success">Publiés: <?= $user['nb_articles_publies'] ?></small>
                                                        <small class="text-warning">En attente: <?= $user['nb_articles_attente'] ?></small>
                                                    </div>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($user['banni']): ?>
                                                            <a href="?debannir=<?= $user['id'] ?>" 
                                                               class="btn btn-sm btn-success" 
                                                               onclick="return confirm('Êtes-vous sûr de vouloir débannir cet utilisateur ?')">
                                                                <i class="fas fa-user-check"></i> Débannir
                                                            </a>
                                                        <?php else: ?>
                                                            <a href="?bannir=<?= $user['id'] ?>" 
                                                               class="btn btn-sm btn-warning" 
                                                               onclick="return confirm('Êtes-vous sûr de vouloir bannir cet utilisateur ? Il ne pourra plus se connecter.')">
                                                                <i class="fas fa-user-slash"></i> Bannir
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="?supprimer=<?= $user['id'] ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cela supprimera aussi tous ses articles.')">
                                                            <i class="fas fa-trash"></i> Supprimer
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Script pour l'animation des statistiques -->
    <script>
        // Données des statistiques depuis PHP
        const statsData = {
            totalUsers: <?= $total_users ?>,
            totalArticles: <?= $total_articles ?>,
            publishedArticles: <?= $total_published ?>,
            pendingArticles: <?= $total_pending ?>
        };
        
        // Fonction d'animation de comptage
        function animateCounter(elementId, targetValue, duration = 1500) {
            const element = document.getElementById(elementId);
            const startValue = 0;
            const increment = targetValue / (duration / 16);
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
            setTimeout(() => {
                animateCounter('total-users', statsData.totalUsers, 1200);
                animateCounter('total-articles', statsData.totalArticles, 1200);
                animateCounter('published-articles', statsData.publishedArticles, 1200);
                animateCounter('pending-articles', statsData.pendingArticles, 1200);
            }, 300);
        });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearch');
    const table = document.getElementById('usersTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>

</html> 