<?php
/**
 * auth/login.php - Vue du formulaire de connexion
 * Variables reçues du contrôleur : $error (message d'erreur, vide si pas d'erreur)
 */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - EmoEat</title>
    <link rel="stylesheet" href="/style.css?v=25">
</head>
<body>
<?php require dirname(dirname(__DIR__)) . '/Views/partials/navbar.php'; ?>

<!-- Mise en page deux colonnes : image à gauche, formulaire à droite -->
<div class="auth-split">
    <!-- Panneau gauche : image décorative avec texte superposé -->
    <div class="auth-image-panel">
        <img src="/images/food-colorful.jpg" alt="Alimentation colorée saine" loading="lazy">
        <div class="auth-image-overlay">
            <span class="aio-badge">🥗 Nutrition Émotionnelle</span>
            <h2>Mangez selon ce que vous ressentez</h2>
            <p>Connectez-vous et laissez EmoEat vous guider vers une alimentation adaptée à vos émotions.</p>
        </div>
    </div>

    <!-- Panneau droit : carte avec le formulaire de connexion -->
    <div class="auth-form-panel">
        <div class="form-card">
            <!-- En-tête du formulaire -->
            <div class="form-logo">
                <div class="logo-circle">🥗</div>
                <h2>Bon retour !</h2>
                <p>Connectez-vous à votre compte EmoEat</p>
            </div>

            <!-- Affiche l'erreur (email invalide, mot de passe incorrect...) si présente -->
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">⚠️ <?php echo htmlspecialchars($error); /* htmlspecialchars évite l'injection XSS */ ?></div>
            <?php endif; ?>

            <!-- Formulaire de connexion soumis en POST vers /login -->
            <form method="POST" action="/login" novalidate>
                <!-- Champ email : pre-rempli si déjà soumis (pour ne pas le re-taper en cas d'erreur) -->
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="votre@email.com" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Champ mot de passe avec bouton pour afficher/masquer -->
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="pass-wrap">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="••••••••" required>
                        <!-- Bouton qui appelle togglePass() pour basculer entre type=password et type=text -->
                        <button type="button" class="pass-toggle" onclick="togglePass('password',this)" title="Afficher/masquer">👁</button>
                    </div>
                </div>

                <!-- Bouton de soumission du formulaire -->
                <button type="submit" name="login" class="btn btn-green btn-full" style="margin-top:4px;">
                    🔑 Se connecter
                </button>
            </form>

            <!-- Lien vers la page de réinitialisation de mot de passe -->
            <div class="form-footer" style="margin-top:16px;">
                <a href="/forgot-password" style="color:var(--text-l);font-size:13px;">🔒 Mot de passe oublié ?</a>
            </div>
            <div class="form-divider"></div>
            <!-- Lien vers la page d'inscription pour les nouveaux utilisateurs -->
            <div class="form-footer" style="margin-top:0;">
                Pas encore de compte ? <a href="/register">Créer un compte</a>
            </div>
        </div>
    </div>
</div>

<?php require dirname(dirname(__DIR__)) . '/Views/partials/footer.php'; ?>
<script>
// Fonction pour afficher ou masquer le mot de passe dans le champ input
function togglePass(id, btn) {
    var input = document.getElementById(id);
    if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; } // Affiche le mot de passe
    else { input.type = 'password'; btn.textContent = '👁'; }                       // Masque le mot de passe
}
</script>
</body>
</html>
