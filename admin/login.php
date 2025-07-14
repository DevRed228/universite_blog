<?php
session_start();
require_once '../includes/traitement.php';

// Si déjà connecté en tant qu'admin, rediriger vers le dashboard
if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: admin.php');
    exit();
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_admin'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    list($isAdmin, $msg) = connecter_admin($email, $password);
    if ($isAdmin) {
        header('Location: admin.php');
        exit();
    } else {
        $loginError = $msg;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container d-flex align-items-center justify-content-center min-vh-100">
        <div class="card shadow p-4" style="max-width: 400px; width: 100%;">
            <div class="text-center mb-4">
                <img src="../assets/images/logo.png" alt="Logo" style="max-width: 80px;" onerror="this.style.display='none'">
                <h3 class="mt-2 mb-0">Espace Administrateur</h3>
                <p class="text-muted mb-0">UniversitéBlog</p>
            </div>
            <?php if ($loginError): ?>
                <div class="alert alert-danger text-center"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="adminEmail" class="form-label">Adresse email</label>
                    <input type="email" name="email" id="adminEmail" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="adminPassword" class="form-label">Mot de passe</label>
                    <input type="password" name="password" id="adminPassword" class="form-control" required>
                </div>
                <button type="submit" name="login_admin" class="btn btn-primary w-100">Connexion</button>
            </form>
            <div class="text-center mt-3">
                <a href="../index.php" class="text-decoration-none">&larr; Retour à l'accueil</a>
            </div>
        </div>
    </div>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html> 