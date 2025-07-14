<?php
// Configuration email - utilise les constantes de config.php
// SITE_NAME et SITE_URL sont déjà définis dans config.php
define('SITE_EMAIL', 'noreply@monblog.fr');

/**
 * Envoie un email avec template
 */
function send_email($to, $subject, $template, $data = []) {
    // Headers
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SITE_NAME . " <" . SITE_EMAIL . ">" . "\r\n";
    
    // Générer le contenu HTML
    $content = generate_email_template($template, $data);
    
    // Envoyer l'email
    return mail($to, $subject, $content, $headers);
}

/**
 * Génère le template HTML de l'email
 */
function generate_email_template($template, $data) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f8f9fa; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . SITE_NAME . '</h1>
            </div>
            <div class="content">';
    
    switch ($template) {
        case 'welcome':
            $html .= '
                <h2>Bienvenue sur ' . SITE_NAME . ' !</h2>
                <p>Bonjour <strong>' . htmlspecialchars($data['prenom']) . '</strong>,</p>
                <p>Votre compte a été créé avec succès. Vous pouvez maintenant :</p>
                <ul>
                    <li>Publier des articles sur vos thèmes préférés</li>
                    <li>Participer à la communauté universitaire</li>
                    <li>Accéder à votre dashboard personnel</li>
                </ul>
                <p><a href="' . SITE_URL . '/users/dash.php" class="btn">Accéder à mon dashboard</a></p>';
            break;
            
        case 'article_validated':
            $html .= '
                <h2>Votre article a été validé !</h2>
                <p>Bonjour <strong>' . htmlspecialchars($data['prenom']) . '</strong>,</p>
                <p>Félicitations ! Votre article <strong>"' . htmlspecialchars($data['titre']) . '"</strong> a été validé par notre équipe et est maintenant visible sur le site.</p>
                <p><a href="' . SITE_URL . '/lire.php?id=' . $data['article_id'] . '" class="btn">Voir mon article</a></p>';
            break;
            
        case 'article_rejected':
            $html .= '
                <h2>Article non validé</h2>
                <p>Bonjour <strong>' . htmlspecialchars($data['prenom']) . '</strong>,</p>
                <p>Votre article <strong>"' . htmlspecialchars($data['titre']) . '"</strong> n\'a pas pu être validé.</p>
                <p>Raison : ' . htmlspecialchars($data['raison'] ?? 'Ne respecte pas les règles de publication') . '</p>
                <p>Vous pouvez modifier votre article et le soumettre à nouveau.</p>';
            break;
    }
    
    $html .= '
            </div>
            <div class="footer">
                <p>Cet email a été envoyé automatiquement par ' . SITE_NAME . '</p>
                <p>Si vous ne souhaitez plus recevoir ces notifications, contactez-nous.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Envoie un email de bienvenue après inscription
 */
function send_welcome_email($user_email, $prenom) {
    $subject = "Bienvenue sur " . SITE_NAME . " !";
    return send_email($user_email, $subject, 'welcome', ['prenom' => $prenom]);
}

/**
 * Envoie une notification de validation d'article
 */
function send_article_validated_email($user_email, $prenom, $titre, $article_id) {
    $subject = "Votre article a été validé - " . SITE_NAME;
    return send_email($user_email, $subject, 'article_validated', [
        'prenom' => $prenom,
        'titre' => $titre,
        'article_id' => $article_id
    ]);
}

/**
 * Envoie une notification de refus d'article
 */
function send_article_rejected_email($user_email, $prenom, $titre, $raison = null) {
    $subject = "Article non validé - " . SITE_NAME;
    return send_email($user_email, $subject, 'article_rejected', [
        'prenom' => $prenom,
        'titre' => $titre,
        'raison' => $raison
    ]);
}
?> 