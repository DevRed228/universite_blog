# UniversitéBlog

UniversitéBlog est une plateforme web universitaire moderne permettant la publication, la gestion et la validation d’articles par des utilisateurs et des administrateurs. Le site met l’accent sur la sécurité, l’expérience utilisateur, la conformité RGPD et la clarté du code.

---

## 1. Présentation générale

- **Utilisateurs** : peuvent s’inscrire, choisir un thème favori, publier/modifier/supprimer leurs articles, recevoir des notifications internes, et gérer leur profil.
- **Administrateurs** : valident ou refusent les articles, peuvent saisir un motif de refus, notifier les auteurs, filtrer/rechercher les articles, et gérer les utilisateurs.
- **Expérience moderne** : interface responsive, notifications, dashboards clairs, éditeur de texte riche (CKEditor), navigation fluide.

---

## 2. Structure du projet

```
/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── includes/
│   ├── config.php
│   ├── traitement.php
│   ├── footer.php
├── admin/
│   ├── admin.php
│   ├── users.php
├── users/
│   ├── dash.php
│   ├── profile.php
│   ├── notifications.php
├── index.php
├── essai.php
├── lire.php
├── confidentialite.php
├── mentions-legales.php
├── logout.php
├── uploads/
└── readme.txt
```

---

## 3. Fonctionnalités principales

### a) Inscription et connexion
- Formulaires sécurisés (validation JS + PHP, CSRF, hashage des mots de passe)
- Choix d’un thème favori à l’inscription (modifiable ensuite)
- Connexion/déconnexion, gestion de session

### b) Dashboard utilisateur
- Sidebar responsive (menu burger sur mobile)
- Publication, modification, suppression d’articles
- Éditeur riche (CKEditor) : gras, italique, images, tableaux, etc.
- Statut des articles : en attente, publié, refusé
- Notifications internes (cloche + page dédiée)
- Affichage des articles par thème favori
- Profil : modification du thème, suppression du compte

### c) Dashboard administrateur
- Vue sur tous les articles (séparés par statut)
- Validation/refus avec saisie du motif
- Notification automatique à l’auteur
- Filtres rapides : titre, auteur, thème, statut
- Recherche, statistiques, badges de statut
- Gestion des utilisateurs (bannir, supprimer)
- **Recherche rapide des utilisateurs** : champ de recherche instantanée (nom, email, thème) au-dessus du tableau
- Responsive design (sidebar burger, tableaux scrollables)

### d) Notifications internes
- Table dédiée en base de données
- Notification à chaque validation/refus d’article
- Affichage dans le dashboard et la navbar
- Page dédiée pour consulter l’historique

### e) Sécurité
- CSRF sur tous les formulaires sensibles
- Requêtes préparées (anti-SQLi)
- Validation serveur et client
- Suppression sécurisée (un utilisateur ne peut supprimer que ses propres articles)
- Gestion des sessions et des droits (admin/user)

### f) RGPD et conformité
- Bannière de consentement cookies
- Pages “Politique de confidentialité” et “Mentions légales”
- Suppression de compte utilisateur (et de ses articles)

### g) Expérience utilisateur
- Interface claire, moderne, et responsive (Bootstrap)
- Feedback visuel après chaque action
- Navigation fluide (sidebar, navbar, boutons d’accès rapide)
- Pagination sur les articles et commentaires
- Barre de recherche et filtres rapides

---

## 4. Points techniques avancés
- **Éditeur riche CKEditor** : configuration avancée (images, tableaux, couleurs, etc.)
- **Upload d’images** : gestion sécurisée, formats autorisés
- **Séparation stricte des rôles** (admin/user)
- **Notifications internes** : table dédiée, affichage dynamique
- **Design mobile-first**
- **Code PHP commenté et structuré**

---

## 5. Améliorations possibles
- Pagination avancée sur toutes les listes
- Système de brouillons pour les articles
- Notifications email (en plus des notifications internes)
- Système de commentaires enrichi (édition, signalement)
- Filtres avancés (multi-thèmes, multi-auteurs)

---

## 6. Installation et utilisation

1. **Cloner le projet**
2. **Créer la base de données** (voir le script SQL fourni)
3. **Configurer `includes/config.php`** avec vos identifiants MySQL
4. **Lancer le serveur local (XAMPP, WAMP, etc.)**
5. **Accéder à `index.php`**

---

## 7. Crédits et licence

Projet réalisé dans le cadre d’un exercice universitaire. Code open-source, réutilisable et modifiable.

---

**Contact** : Pour toute question, suggestion ou bug, contactez l’administrateur du projet.

---

## 8. Systèmes intégrés et fonctionnement détaillé

### a) Authentification et gestion des sessions
- **Technologies** : PHP natif, MySQL
- **Fonctionnement** :
  - À l’inscription, les mots de passe sont hashés (password_hash) et stockés en base.
  - À la connexion, vérification via password_verify et création d’une session PHP (`$_SESSION['user_id']`).
  - Les droits admin sont gérés via `$_SESSION['is_admin']`.
  - Accès aux dashboards protégé par des redirections si non connecté ou non admin.

### b) Dashboard utilisateur
- **Technologies** : PHP, Bootstrap, CKEditor
- **Fonctionnement** :
  - Sidebar responsive (Bootstrap + menu burger)
  - Liste des articles de l’utilisateur, filtrés par thème favori
  - Formulaire d’édition avec CKEditor (édition riche, upload d’images)
  - Notifications affichées via une cloche et une page dédiée
  - Modification du profil et du thème favori

### c) Dashboard administrateur
- **Technologies** : PHP, Bootstrap, JS natif
- **Fonctionnement** :
  - Vue sur tous les articles, séparés par statut (en attente, publié, refusé)
  - Validation/refus d’articles avec saisie d’un motif (popup ou champ)
  - Notification automatique à l’auteur (notification interne + email possible)
  - Statistiques globales (cartes animées en JS)
  - Gestion des utilisateurs (bannir, supprimer)
  - **Recherche rapide** : champ de recherche JS qui filtre instantanément le tableau des utilisateurs (nom, email, thème)

### d) Notifications internes
- **Technologies** : Table MySQL `notifications`, PHP, Bootstrap
- **Fonctionnement** :
  - Lorsqu’un article est validé/refusé, une notification est créée en base pour l’auteur
  - Les notifications sont affichées dans le dashboard et la navbar (cloche)
  - Page dédiée pour consulter l’historique, marquer comme lu

### e) Éditeur de texte riche (CKEditor)
- **Technologies** : CKEditor 4 (CDN), PHP
- **Fonctionnement** :
  - Intégré dans les formulaires d’édition d’article (admin et user)
  - Barre d’outils complète (gras, italique, images, tableaux, couleurs, etc.)
  - Upload d’images possible (gestion serveur à adapter selon besoin)

### f) Sécurité
- **Technologies** : PHP, MySQL
- **Fonctionnement** :
  - CSRF : tokens sur les formulaires sensibles
  - Requêtes préparées (PDO) pour éviter les injections SQL
  - Validation côté client (JS) et serveur (PHP)
  - Suppression sécurisée (un user ne peut supprimer que ses propres articles)
  - Gestion stricte des droits (admin/user)

### g) Responsive et expérience utilisateur
- **Technologies** : Bootstrap 5, FontAwesome
- **Fonctionnement** :
  - Sidebar et navbar adaptatives (menu burger sur mobile)
  - Tableaux scrollables sur petit écran
  - Feedback visuel après chaque action (alertes, badges, animations)

### h) RGPD et conformité
- **Technologies** : PHP, JS
- **Fonctionnement** :
  - Pages légales accessibles
  - Suppression de compte utilisateur (et de ses données)

---

## 9. Exposé technique avec extraits de code

### 1. Authentification et gestion des sessions

**a) Inscription d’un utilisateur (hashage du mot de passe)**
```php
// Traitement de l'inscription
if (isset($_POST['email'], $_POST['pwd'])) {
    $email = $_POST['email'];
    $pwd = password_hash($_POST['pwd'], PASSWORD_DEFAULT); // Hashage sécurisé
    $stmt = $pdo->prepare("INSERT INTO users (email, pwd) VALUES (?, ?)");
    $stmt->execute([$email, $pwd]);
}
```
*On ne stocke jamais le mot de passe en clair. On utilise `password_hash` pour le sécuriser.*

**b) Connexion et création de session**
```php
if (isset($_POST['email'], $_POST['pwd'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_POST['email']]);
    $user = $stmt->fetch();
    if ($user && password_verify($_POST['pwd'], $user['pwd'])) {
        $_SESSION['user_id'] = $user['id']; // L'utilisateur est connecté
    }
}
```
*On vérifie le mot de passe avec `password_verify` et on crée une session PHP.*

---

### 2. Dashboard utilisateur : publication d’article avec éditeur riche

**a) Formulaire d’édition avec CKEditor**
```html
<form method="post">
    <textarea name="contenu" id="contenu"></textarea>
    <button type="submit">Publier</button>
</form>
<script src="https://cdn.ckeditor.com/4.22.1/full/ckeditor.js"></script>
<script>
    CKEDITOR.replace('contenu', {
        language: 'fr',
        extraPlugins: 'image2,uploadimage',
        toolbar: [ /* ... outils ... */ ]
    });
</script>
```
*L’utilisateur peut mettre en forme son texte, insérer des images, etc. grâce à CKEditor.*

---

### 3. Notifications internes

**a) Création d’une notification lors de la validation/refus d’un article**
```php
// Lorsqu'un admin valide/refuse un article
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)");
$stmt->execute([$auteur_id, "Votre article a été validé !", "validation"]);
```
*On enregistre la notification en base pour l’auteur concerné.*

**b) Affichage des notifications dans le dashboard**
```php
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
foreach ($notifications as $notif) {
    echo "<div class='alert alert-info'>{$notif['message']}</div>";
}
```
*L’utilisateur voit ses notifications dans son dashboard.*

---

### 4. Recherche rapide des utilisateurs (dashboard admin)

**a) Champ de recherche et filtrage JS**
```html
<input type="search" id="userSearch" placeholder="Rechercher un utilisateur...">
<table id="usersTable">
    <!-- ... -->
</table>
<script>
document.getElementById('userSearch').addEventListener('input', function() {
    const filter = this.value.toLowerCase();
    document.querySelectorAll('#usersTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
```
*Le tableau se filtre instantanément selon la saisie (nom, email, thème…).*

---

### 5. Sécurité : requêtes préparées et CSRF

**a) Requête préparée (anti-injection SQL)**
```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```
*On n’insère jamais directement les variables dans la requête SQL.*

**b) Protection CSRF**
```php
// Génération du token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
<?php
// Vérification à la soumission
if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die('Erreur CSRF');
}
```
*On ajoute un token unique dans chaque formulaire sensible pour éviter les attaques CSRF.*

---

### 6. Responsive design (Bootstrap)

**a) Sidebar responsive**
```html
<nav class="navbar navbar-dark bg-dark d-md-none">
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="sidebarMenu">
        <!-- Liens du menu -->
    </div>
</nav>
```
*Sur mobile, la sidebar devient un menu burger grâce à Bootstrap.*

---

### 7. Suppression sécurisée d’un utilisateur

```php
if (isset($_GET['supprimer'])) {
    $user_id = (int)$_GET['supprimer'];
    // Suppression des articles de l'utilisateur
    $pdo->prepare("DELETE FROM articles WHERE user_id = ?")->execute([$user_id]);
    // Suppression de l'utilisateur
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
}
```
*On supprime d’abord les articles liés pour éviter les incohérences, puis l’utilisateur.*

---

### 8. Upload d’images dans les articles

**a) Configuration CKEditor côté client**
```js
CKEDITOR.replace('contenu', {
    extraPlugins: 'uploadimage',
    filebrowserUploadUrl: 'upload.php',
    filebrowserUploadMethod: 'form'
});
```
**b) Script PHP pour gérer l’upload (upload.php)**
```php
if (isset($_FILES['upload'])) {
    $file = $_FILES['upload'];
    $target = 'uploads/' . basename($file['name']);
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $url = '/uploads/' . $file['name'];
        echo json_encode([ 'uploaded' => 1, 'fileName' => $file['name'], 'url' => $url ]);
    }
}
```
*L’utilisateur peut insérer des images dans ses articles via CKEditor. Le script PHP gère l’enregistrement dans le dossier uploads/.*

---

### 9. Pagination des articles et commentaires

**a) Limiter le nombre d’articles affichés par page**
```php
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare("SELECT * FROM articles LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();
```
*On affiche 10 articles par page, avec navigation entre les pages.*

---

### 10. Gestion des thèmes favoris

**a) Sélection et modification du thème favori**
```php
// À l'inscription ou dans le profil
<select name="theme_id">
    <?php foreach ($themes as $theme): ?>
        <option value="<?= $theme['id'] ?>" <?= $theme['id'] == $user_theme_id ? 'selected' : '' ?>>
            <?= htmlspecialchars($theme['name']) ?>
        </option>
    <?php endforeach; ?>
</select>
```
*L’utilisateur choisit son thème favori à l’inscription ou dans son profil. Les articles affichés sont filtrés selon ce choix.*

---

## Mot de fin

Merci d’avoir pris le temps de découvrir UniversitéBlog à travers ce rapport détaillé. Ce projet vise à offrir une expérience moderne, sécurisée et pédagogique autour de la publication universitaire. Il est le fruit d’un travail rigoureux, avec une attention particulière portée à la clarté du code, à la sécurité et à l’expérience utilisateur.

N’hésitez pas à contribuer, à proposer des améliorations ou à vous inspirer de ce projet pour vos propres développements !

Bonne exploration et bonne continuation sur UniversitéBlog 🚀