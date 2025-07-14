<?php
include 'includes/config.php';
session_start();

// Générer un identifiant de session pour les invités
if (empty($_SESSION['user_id'])) {
    if (empty($_COOKIE['session_vote']) && isset($_COOKIE['cookie_consent']) && $_COOKIE['cookie_consent'] === 'accepted') {
        $session_vote = bin2hex(random_bytes(16));
        setcookie('session_vote', $session_vote, time() + 365*24*3600, '/');
    } else {
        $session_vote = $_COOKIE['session_vote'] ?? null;
    }
} else {
    $session_vote = null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$sql = "SELECT a.titre, a.description, a.contenu, a.image, a.date_creation, a.user_id, a.statut, u.prenom, u.nom, t.name AS theme_name
        FROM articles a
        LEFT JOIN users u ON a.user_id = u.id
        JOIN themes t ON a.theme_id = t.id
        WHERE a.id = :id AND a.statut = 'publié'";
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Article introuvable ou non publié.</div></div>";
    exit;
}
// Image de l'article ou image par défaut
$imgSrc = !empty($article['image']) && file_exists('uploads/' . $article['image'])
    ? 'uploads/' . htmlspecialchars($article['image'])
    : 'https://images.unsplash.com/photo-1465101046530-73398c7f28ca?auto=format&fit=crop&w=600&q=80';

$user_id = $_SESSION['user_id'] ?? null;

// Gestion du like/dislike
if (isset($_POST['vote_comment_id'], $_POST['vote_type'])) {
    $vote_comment_id = (int)$_POST['vote_comment_id'];
    $vote_type = $_POST['vote_type'] === 'like' ? 'like' : 'dislike';
    // Vérifier si déjà voté
    $sql = "SELECT * FROM comment_likes WHERE comment_id = :comment_id AND ".
        ($user_id ? "user_id = :user_id" : "session_id = :session_id");
    $params = ['comment_id' => $vote_comment_id];
    if ($user_id) {
        $params['user_id'] = $user_id;
    } else {
        $params['session_id'] = $session_vote;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        // Si même type, annule le vote (toggle), sinon met à jour
        if ($existing['type'] === $vote_type) {
            $sql = "DELETE FROM comment_likes WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['id' => $existing['id']]);
        } else {
            $sql = "UPDATE comment_likes SET type = :type WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['type' => $vote_type, 'id' => $existing['id']]);
        }
    } else {
        $sql = "INSERT INTO comment_likes (comment_id, user_id, session_id, type) VALUES (:comment_id, :user_id, :session_id, :type)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'comment_id' => $vote_comment_id,
            'user_id' => $user_id,
            'session_id' => $session_vote,
            'type' => $vote_type
        ]);
    }
    // Rafraîchir la page pour éviter le repost
    header('Location: lire.php?id=' . $id . '#comment-' . $vote_comment_id);
    exit;
}

// Suppression d'un commentaire
$comment_message = '';
if (isset($_GET['delete_comment'])) {
    $comment_id = (int)$_GET['delete_comment'];
    $sql = "SELECT * FROM comments WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $comment_id, 'user_id' => $user_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($comment) {
        $sql = "DELETE FROM comments WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $comment_id]);
        $comment_message = '<div class="alert alert-success">Commentaire supprimé.</div>';
    } else {
        $comment_message = '<div class="alert alert-danger">Action non autorisée.</div>';
    }
}

// Modification d'un commentaire
if (isset($_POST['edit_comment_id'])) {
    $edit_comment_id = (int)$_POST['edit_comment_id'];
    $edit_contenu = trim($_POST['edit_contenu'] ?? '');
    $sql = "SELECT * FROM comments WHERE id = :id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $edit_comment_id, 'user_id' => $user_id]);
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($comment && !empty($edit_contenu) && mb_strlen($edit_contenu) <= 1000) {
        $sql = "UPDATE comments SET contenu = :contenu WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['contenu' => $edit_contenu, 'id' => $edit_comment_id]);
        $comment_message = '<div class="alert alert-success">Commentaire modifié.</div>';
    } else {
        $comment_message = '<div class="alert alert-danger">Action non autorisée ou contenu invalide.</div>';
    }
}

// Traitement ajout commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commenter'])) {
    $contenu = trim($_POST['contenu'] ?? '');
    $pseudo = null;
    if (!$user_id) {
        $pseudo = trim($_POST['pseudo'] ?? '');
    }
    if (empty($contenu) || (!$user_id && empty($pseudo))) {
        $comment_message = '<div class="alert alert-warning">Veuillez remplir tous les champs.</div>';
    } elseif (mb_strlen($contenu) > 1000) {
        $comment_message = '<div class="alert alert-warning">Le commentaire est trop long (1000 caractères max).</div>';
    } else {
        $sql = "INSERT INTO comments (article_id, user_id, pseudo, contenu) VALUES (:article_id, :user_id, :pseudo, :contenu)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'article_id' => $id,
            'user_id' => $user_id,
            'pseudo' => $pseudo,
            'contenu' => $contenu
        ]);
        $comment_message = '<div class="alert alert-success">Commentaire publié !</div>';
    }
}

// Récupérer les commentaires avec pagination
$comments_per_page = 5;
$current_comment_page = isset($_GET['comment_page']) ? max(1, (int)$_GET['comment_page']) : 1;

// Compter le total de commentaires
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM comments WHERE article_id = ?");
$stmt->execute([$id]);
$total_comments = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination des commentaires
$comment_offset = ($current_comment_page - 1) * $comments_per_page;

$sql = "SELECT c.*, u.prenom, u.nom FROM comments c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.article_id = :article_id 
        ORDER BY c.date_creation DESC 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':article_id', $id, PDO::PARAM_INT);
$stmt->bindValue(':limit', $comments_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $comment_offset, PDO::PARAM_INT);
$stmt->execute();
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer le nombre de pages pour les commentaires
$total_comment_pages = ceil($total_comments / $comments_per_page);

// Pour édition inline
$edit_comment_id = isset($_GET['edit_comment']) ? (int)$_GET['edit_comment'] : null;

// Récupérer les likes/dislikes pour tous les commentaires
$comment_ids = array_column($comments, 'id');
$likes = $dislikes = $user_votes = [];
if ($comment_ids) {
    $in = str_repeat('?,', count($comment_ids) - 1) . '?';
    $sql = "SELECT comment_id, type, COUNT(*) as nb FROM comment_likes WHERE comment_id IN ($in) GROUP BY comment_id, type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($comment_ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['type'] === 'like') $likes[$row['comment_id']] = $row['nb'];
        if ($row['type'] === 'dislike') $dislikes[$row['comment_id']] = $row['nb'];
    }
    // Récupérer le vote de l'utilisateur/session pour chaque commentaire
    $sql = "SELECT comment_id, type FROM comment_likes WHERE comment_id IN ($in) AND ".
        ($user_id ? "user_id = ?" : "session_id = ?");
    $params = $comment_ids;
    $params[] = $user_id ? $user_id : $session_vote;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $user_votes[$row['comment_id']] = $row['type'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($article['titre']) ?> - Lecture</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .card-article {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.10);
            background: #fff;
            color: #2c3e50;
            max-width: 700px;
            margin: 40px auto;
        }
        .card-article .card-img-top {
            border-top-left-radius: 18px;
            border-top-right-radius: 18px;
            height: 320px;
            object-fit: cover;
            background: #ecf0f1;
        }
        .badge-theme {
            background: #3498db;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 8px;
            padding: 0.5em 1em;
        }
        .card-title {
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .card-description {
            color: #555;
            font-size: 1.15rem;
        }
        .card-date {
            color: #888;
            font-size: 1rem;
        }
        .article-content {
            font-size: 1.15rem;
            line-height: 1.7;
            color: #2c3e50;
            margin-top: 1.5rem;
        }
        .btn-center {
            display: flex;
            justify-content: center;
        }
        /* Commentaires stylés */
        .comments-section {
            max-width: 700px;
            margin: 40px auto 0 auto;
            background: #fff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.10);
            padding: 2.5rem 2rem 2rem 2rem;
            color: #2c3e50;
        }
        .comment {
            background: #ecf0f1;
            border-radius: 12px;
            box-shadow: 0 2px 8px 0 rgba(44,62,80,0.07);
            padding: 1.2rem 1.2rem 1rem 1.2rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: flex-start;
            gap: 1.2rem;
            position: relative;
        }
        .comment:last-child {
            margin-bottom: 0;
        }
        .comment-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #3498db;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 2px 8px 0 rgba(52,152,219,0.08);
            flex-shrink: 0;
        }
        .comment-content {
            flex: 1;
        }
        .comment-header {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            margin-bottom: 0.2rem;
        }
        .comment-author {
            font-weight: 600;
            color: #3498db;
            font-size: 1.08rem;
        }
        .comment-date {
            color: #888;
            font-size: 0.97rem;
        }
        .comment-actions {
            margin-left: auto;
            display: flex;
            gap: 0.5rem;
        }
        .comment-body {
            font-size: 1.08rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .vote-btn {
            border: none;
            background: none;
            color: #3498db;
            font-size: 1.15rem;
            margin-right: 8px;
            cursor: pointer;
            transition: color 0.2s, transform 0.2s;
            padding: 0 4px;
        }
        .vote-btn.active, .vote-btn:focus {
            color: #2980b9;
            font-weight: bold;
            transform: scale(1.15);
        }
        .vote-btn.dislike.active {
            color: #e74c3c;
        }
        .vote-btn:hover {
            color: #2980b9;
        }
        .vote-btn.dislike:hover {
            color: #e74c3c;
        }
        .vote-count {
            font-size: 1rem;
            font-weight: 600;
            margin-right: 12px;
            color: #2c3e50;
        }
        @media (max-width: 600px) {
            .comments-section {
                padding: 1.2rem 0.5rem 1rem 0.5rem;
            }
            .comment {
                padding: 0.8rem 0.5rem 0.7rem 0.5rem;
                gap: 0.7rem;
            }
            .comment-avatar {
                width: 38px;
                height: 38px;
                font-size: 1.1rem;
            }
        }
        .btn-retour {
            background: #3498db;
            color: #fff !important;
            border: none;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.6em 1.4em;
            box-shadow: 0 2px 8px 0 rgba(52,152,219,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-retour:hover, .btn-retour:focus {
            background: #2980b9;
            color: #fff;
            box-shadow: 0 4px 16px 0 rgba(52,152,219,0.18);
        }
        .retour-container {
            margin-top: 32px;
            margin-bottom: 32px;
            display: flex;
            justify-content: flex-start;
        }
        .card-article {
            margin-top: 0;
            margin-bottom: 48px;
        }
        .comments-section {
            margin-top: 48px;
            margin-bottom: 32px;
        }
        @media (max-width: 600px) {
            .retour-container {
                margin-top: 18px;
                margin-bottom: 18px;
            }
            .card-article {
                margin-bottom: 28px;
            }
            .comments-section {
                margin-top: 28px;
                margin-bottom: 18px;
            }
        }
    </style>
</head>
<body>
    <!-- Bouton Retour tout en haut -->
    <div class="container retour-container">
        <a href="essai.php" class="btn btn-retour">&larr; Retour à la liste des articles</a>
    </div>
<div class="container py-5">
    <div class="card card-article">
        <img src="<?= $imgSrc ?>" class="card-img-top" alt="Image article">
        <div class="card-body">
            <div class="d-flex justify-content-center align-items-center mb-2">
                <span class="badge badge-theme me-2"><?= htmlspecialchars($article['theme_name']) ?></span>
                <span class="card-date ms-3">Publié le <?= date('d/m/Y', strtotime($article['date_creation'])) ?></span>
            </div>
            <h1 class="card-title text-center mb-3"><?= htmlspecialchars($article['titre']) ?></h1>
            <p class="card-description text-center mb-4"><?= htmlspecialchars($article['description']) ?></p>
            <div class="text-center mb-4">
                <small class="text-muted">
                    Par <?php echo isset($article['prenom']) ? htmlspecialchars($article['prenom'] . ' ' . $article['nom']) : 'Admin'; ?>,
                    le <?php echo date('d/m/Y', strtotime($article['date_creation'])); ?>
                </small>
            </div>
            <div class="article-content">
                <?= $article['contenu'] ?>
            </div>
            <div class="btn-center mt-4">
                <a href="essai.php" class="btn btn-outline-secondary">&larr; Retour à la liste</a>
            </div>
        </div>
    </div>

    <!-- Section Commentaires -->
    <div class="comments-section mt-5">
        <h3 class="mb-4">
            Commentaires 
            <span class="badge bg-primary"><?= $total_comments ?></span>
        </h3>
        <?php if (!empty($comment_message)) echo $comment_message; ?>
        
        <!-- Formulaire de commentaire -->
        <form method="post" class="mb-4">
            <?php if (empty($user_id)): ?>
                <div class="mb-3">
                    <label for="pseudo" class="form-label">Votre pseudo</label>
                    <input type="text" class="form-control" id="pseudo" name="pseudo" maxlength="50" required>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="contenu" class="form-label">Votre commentaire</label>
                <textarea class="form-control" id="contenu" name="contenu" rows="3" maxlength="1000" required placeholder="Partagez votre avis sur cet article..."></textarea>
                <div class="form-text">Maximum 1000 caractères</div>
            </div>
            <button type="submit" name="commenter" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Publier
            </button>
        </form>
        
        <!-- Liste des commentaires -->
        <?php if (empty($comments)): ?>
            <div class="text-center text-muted py-4">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <p>Aucun commentaire pour cet article. Soyez le premier à commenter !</p>
            </div>
        <?php else: ?>
            <!-- Pagination des commentaires -->
            <?php if ($total_comment_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <small class="text-muted">
                        Affichage <?= ($comment_offset + 1) ?>-<?= min($comment_offset + $comments_per_page, $total_comments) ?> sur <?= $total_comments ?> commentaires
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm">
                            <?php if ($current_comment_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?= $id ?>&comment_page=<?= $current_comment_page - 1 ?>">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $current_comment_page - 2); $i <= min($total_comment_pages, $current_comment_page + 2); $i++): ?>
                                <li class="page-item <?= $i == $current_comment_page ? 'active' : '' ?>">
                                    <a class="page-link" href="?id=<?= $id ?>&comment_page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_comment_page < $total_comment_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?id=<?= $id ?>&comment_page=<?= $current_comment_page + 1 ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
            
            <?php foreach ($comments as $comment): ?>
                <div class="comment" id="comment-<?= $comment['id'] ?>">
                    <div class="comment-avatar">
                        <?php
                            $name = $comment['prenom'] ? $comment['prenom'] . ' ' . $comment['nom'] : $comment['pseudo'];
                            $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
                            echo $initial;
                        ?>
                    </div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">
                                <?= htmlspecialchars($name) ?>
                            </span>
                            <span class="comment-date">
                                <?= date('d/m/Y H:i', strtotime($comment['date_creation'])) ?>
                            </span>
                            <?php if ($user_id && $comment['user_id'] == $user_id): ?>
                                <div class="comment-actions">
                                    <a href="lire.php?id=<?= $id ?>&edit_comment=<?= $comment['id'] ?>#comment-<?= $comment['id'] ?>" class="btn btn-sm btn-warning">Modifier</a>
                                    <a href="lire.php?id=<?= $id ?>&delete_comment=<?= $comment['id'] ?>" class="btn btn-sm btn-danger ms-2" onclick="return confirm('Supprimer ce commentaire ?');">Supprimer</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($edit_comment_id === (int)$comment['id'] && $user_id && $comment['user_id'] == $user_id): ?>
                            <form method="post" class="mb-2">
                                <input type="hidden" name="edit_comment_id" value="<?= $comment['id'] ?>">
                                <textarea class="form-control mb-2" name="edit_contenu" rows="3" maxlength="1000" required><?= htmlspecialchars($comment['contenu']) ?></textarea>
                                <button type="submit" class="btn btn-sm btn-success">Enregistrer</button>
                                <a href="lire.php?id=<?= $id ?>" class="btn btn-sm btn-secondary ms-2">Annuler</a>
                            </form>
                        <?php else: ?>
                            <div class="comment-body"><?= nl2br(htmlspecialchars($comment['contenu'])) ?></div>
                        <?php endif; ?>
                        <!-- Like/Dislike -->
                        <div class="mt-2">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="vote_comment_id" value="<?= $comment['id'] ?>">
                                <input type="hidden" name="vote_type" value="like">
                                <button type="submit" class="vote-btn<?= (isset($user_votes[$comment['id']]) && $user_votes[$comment['id']] === 'like') ? ' active' : '' ?>" title="J'aime">
                                    👍
                                </button>
                                <span class="vote-count"><?= $likes[$comment['id']] ?? 0 ?></span>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="vote_comment_id" value="<?= $comment['id'] ?>">
                                <input type="hidden" name="vote_type" value="dislike">
                                <button type="submit" class="vote-btn dislike<?= (isset($user_votes[$comment['id']]) && $user_votes[$comment['id']] === 'dislike') ? ' active' : '' ?>" title="Je n'aime pas">
                                    👎
                                </button>
                                <span class="vote-count"><?= $dislikes[$comment['id']] ?? 0 ?></span>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
