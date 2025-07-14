<?php
// Gestion basique de la newsletter (affichage message)
if (!isset($newsletter_message)) {
    $newsletter_message = '';
    if (isset($_POST['newsletter_email'])) {
        $email = trim($_POST['newsletter_email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $newsletter_message = '<div class="alert alert-danger mt-3">Adresse email invalide.</div>';
        } else {
            // Ici, tu pourrais ajouter l'email à la base ou envoyer à Mailchimp, etc.
            $newsletter_message = '<div class="alert alert-success mt-3">Merci pour votre inscription à la newsletter !</div>';
        }
    }
}
?>
<footer class="footer bg-dark text-white pt-5 pb-3 mt-5">
    <div class="container">
        <div class="row gy-4">
            <!-- Présentation -->
            <div class="col-12 col-md-3">
                <h5 class="mb-3">UniversitéBlog</h5>
                <p class="small">La plateforme académique de référence pour les étudiants, chercheurs et passionnés de sciences.</p>
                <div class="d-flex gap-2 mt-3">
                    <a href="#" class="social-icon text-white"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon text-white"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon text-white"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-icon text-white"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <!-- Navigation -->
            <div class="col-6 col-md-2">
                <h6 class="mb-3">Navigation</h6>
                <ul class="list-unstyled">
                    <li><a href="index.php" class="text-white-50 text-decoration-none">Accueil</a></li>
                    <li><a href="essai.php" class="text-white-50 text-decoration-none">Articles</a></li>
                    <li><a href="#" class="text-white-50 text-decoration-none">Contact</a></li>
                </ul>
            </div>
            <!-- Légal -->
            <div class="col-6 col-md-2">
                <h6 class="mb-3">Légal</h6>
                <ul class="list-unstyled">
                    <li><a href="confidentialite.php" class="text-white-50 text-decoration-none">Politique de confidentialité</a></li>
                    <li><a href="mentions-legales.php" class="text-white-50 text-decoration-none">Mentions légales</a></li>
                </ul>
            </div>
            <!-- Newsletter -->
            <div class="col-12 col-md-5">
                <h6 class="mb-3">Newsletter</h6>
                <p class="small">Abonnez-vous pour recevoir les derniers articles et actualités.</p>
                <form method="post" class="newsletter-form d-flex flex-column flex-sm-row align-items-center gap-2 mb-2">
                    <input type="email" name="newsletter_email" class="form-control" placeholder="Votre email" required style="max-width:260px;">
                    <button type="submit" class="btn btn-primary">S'abonner</button>
                </form>
                <?php if (!empty($newsletter_message)) echo $newsletter_message; ?>
            </div>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="text-center my-3">
                <a href="/users/dash.php" class="btn btn-primary">Mon dashboard</a>
            </div>
        <?php endif; ?>
        <hr class="my-3 bg-light">
        <div class="text-center small">
            &copy; <?= date('Y') ?> UniversitéBlog. Tous droits réservés.
        </div>
    </div>
</footer>

    <!-- Bootstrap JS -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Scrollbar stylée */
        body::-webkit-scrollbar {
            width: 10px;
            background: #ecf0f1;
        }
        body::-webkit-scrollbar-thumb {
            background: #3498db;
            border-radius: 8px;
            transition: background 0.2s;
        }
        body::-webkit-scrollbar-thumb:hover {
            background: #2980b9;
        }
        /* Firefox */
        body {
            scrollbar-width: thin;
            scrollbar-color: #3498db #ecf0f1;
        }
        /* Bouton retour en haut */
        #scrollTopBtn {
            position: fixed;
            bottom: 32px;
            right: 32px;
            z-index: 9999;
            display: none;
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            box-shadow: 0 4px 16px 0 rgba(52,152,219,0.18);
            font-size: 2rem;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s, opacity 0.2s;
            opacity: 0.85;
        }
        #scrollTopBtn:hover {
            background: #2980b9;
            opacity: 1;
        }
        @media (max-width: 600px) {
            #scrollTopBtn {
                bottom: 18px;
                right: 18px;
                width: 40px;
                height: 40px;
                font-size: 1.4rem;
            }
        }
    </style>
    <!-- Bouton retour en haut -->
    <button id="scrollTopBtn" title="Retour en haut">&#8679;</button>
    <!-- Cookie Consent Banner -->
    <div id="cookieConsentBanner" class="cookie-consent-banner" style="display:none; position:fixed; bottom:0; left:0; width:100%; background:#222; color:#fff; z-index:9999; padding:16px 0; text-align:center;">
        Ce site utilise des cookies pour améliorer votre expérience. 
        <button id="acceptCookies" class="btn btn-success btn-sm ms-2">Accepter</button>
        <button id="declineCookies" class="btn btn-danger btn-sm ms-2">Refuser</button>
    </div>
    <!-- Fin Cookie Consent Banner -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Validation inscription
            const registerForm = document.querySelector('#registerModal form');
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    // Supprime tous les anciens messages d'alerte
                    registerForm.querySelectorAll('.alert').forEach(a => a.remove());

                    // Récupère les champs
                    const prenom = registerForm.querySelector('[name="prenom"]').value.trim();
                    const nom = registerForm.querySelector('[name="nom"]').value.trim();
                    const email = registerForm.querySelector('[name="email"]').value.trim();
                    const pwd = registerForm.querySelector('[name="pwd"]').value;
                    const cpwd = registerForm.querySelector('[name="cpwd"]').value;
                    const theme = registerForm.querySelector('select[name="theme"]').value;
                    const terms = registerForm.querySelector('#termsCheck')?.checked;

                    let error = "";

                    if (!prenom || !nom || !email || !pwd || !cpwd) {
                        error = "Tous les champs sont obligatoires.";
                    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        error = "Adresse email invalide.";
                    } else if (pwd.length < 8 || !/\d/.test(pwd)) {
                        error = "Le mot de passe doit contenir au moins 8 caractères et un chiffre.";
                    } else if (pwd !== cpwd) {
                        error = "Les mots de passe ne correspondent pas.";
                    } else if (!theme) {
                        error = "Veuillez sélectionner un thème.";
                    } else if (!terms) {
                        error = "Vous devez accepter les conditions d'utilisation.";
                    }

                    if (error) {
                        e.preventDefault();
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-danger js-error';
                        alert.setAttribute('role', 'alert');
                        alert.innerText = error;
                        registerForm.prepend(alert);
                    }
                });
            }

            // Validation connexion (optionnel)
            const loginForm = document.querySelector('#loginForm');
            if (loginForm) {
                loginForm.addEventListener('submit', function(e) {
                    loginForm.querySelectorAll('.alert').forEach(a => a.remove());

                    const email = loginForm.querySelector('[name="email"]').value.trim();
                    const pwd = loginForm.querySelector('[name="pwd"]').value;

                    let error = "";
                    if (!email || !pwd) {
                        error = "Veuillez remplir tous les champs.";
                    }

                    if (error) {
                        e.preventDefault();
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-danger js-error';
                        alert.setAttribute('role', 'alert');
                        alert.innerText = error;
                        loginForm.prepend(alert);
                    }
                });
            }

            if (document.querySelector('.alert-success')) {
                let form = document.querySelector('#loginModal form, #registerModal form');
                if (form) form.style.display = 'none';
            }

            // Scroll to top button
            const scrollTopBtn = document.getElementById('scrollTopBtn');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    scrollTopBtn.style.display = 'flex';
                } else {
                    scrollTopBtn.style.display = 'none';
                }
            });
            scrollTopBtn.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        <?php if (!empty($loginError)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            });
        <?php endif; ?>
        <?php if (!empty($registerError)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            });
        <?php endif; ?>
    </script>
</body>

</html>