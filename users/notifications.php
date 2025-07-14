<?php
session_start();
require_once '../includes/config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
$user_id = $_SESSION['user_id'];
// Marquer toutes les notifications comme lues si demandé
if (isset($_POST['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET lu = 1 WHERE user_id = ?")->execute([$user_id]);
}
// Récupérer les notifications (non lues d'abord)
$notif_stmt = $pdo->prepare("SELECT id, message, lu, date FROM notifications WHERE user_id = ? ORDER BY lu ASC, date DESC");
$notif_stmt->execute([$user_id]);
$notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
$notif_count = $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id = $user_id AND lu = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes notifications</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <a class="nav-link" href="dash.php"><i class="fa fa-file-alt"></i> Mes articles</a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="notifications.php"><i class="fa fa-bell"></i> Notifications
                            <?php if ($notif_count): ?>
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
                            <a class="nav-link" href="dash.php"><i class="fa fa-file-alt"></i> Mes articles</a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link active" href="notifications.php"><i class="fa fa-bell"></i> Notifications
                                <?php if ($notif_count): ?>
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
            <div class="dashboard-header d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fa fa-bell"></i> Mes notifications</h2>
                <div class="d-flex gap-2">
                    <a href="../index.php" class="btn btn-outline-primary">Accueil</a>
                    <?php if (!empty($notifications)): ?>
                    <form method="post" class="mb-0">
                        <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary">Tout marquer comme lu</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-body p-2">
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info mb-0">Aucune notification.</div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($notifications as $notif): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center<?= !$notif['lu'] ? ' bg-light' : '' ?>">
                                    <span><?= htmlspecialchars($notif['message']) ?></span>
                                    <small class="text-muted ms-2" style="font-size:0.9em;">
                                        <?= date('d/m/Y H:i', strtotime($notif['date'])) ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 