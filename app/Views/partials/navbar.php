<?php
/* partials/navbar.php - Barre de navigation commune à toutes les pages
   Affiche des liens différents selon que l'utilisateur est connecté ou non,
   et selon son rôle (ADMIN ou CLIENT). */

$_cur = basename($_SERVER['REQUEST_URI']);  // Nom de la page courante (pour mettre le lien en 'active')
$_logged = isset($_SESSION['user_id']);     // true si l'utilisateur est connecté (session active)
$_uname = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : ''; // Nom de l'utilisateur connecté
$_urole = isset($_SESSION['role']) ? $_SESSION['role'] : '';           // Rôle de l'utilisateur (ADMIN ou CLIENT)
?>
<nav class="navbar">
    <div class="nav-container">

        <!-- Logo : pointe vers le dashboard si connecté, vers l'accueil sinon -->
        <a href="<?php echo $_logged ? '/dashboard' : '/'; ?>" class="nav-logo">
            <span class="logo-icon">&#127869;</span>
            <span>Emo<span class="logo-accent">Eat</span></span>
        </a>

        <div class="nav-right">
        <div class="nav-links" id="navLinks">
            <?php if($_logged): /* Liens visibles uniquement quand l'utilisateur est connecté */ ?>
                <!-- Lien tableau de bord : classe 'active' si on est sur /dashboard -->
                <a href="/dashboard" class="<?php echo strpos($_cur, 'dashboard') !== false ? 'active':''; ?>">&#127968; Tableau de bord</a>
                <!-- Lien recommandations : classe 'active' si on est sur /recommendation -->
                <a href="/recommendation" class="<?php echo strpos($_cur, 'recommendation') !== false ? 'active':''; ?>">&#127869; Recommandations</a>
                <!-- Lien historique : classe 'active' si on est sur /history -->
                <a href="/history" class="<?php echo strpos($_cur, 'history') !== false ? 'active':''; ?>">&#128202; Historique</a>
                <!-- Lien profil : classe 'active' si on est sur /profile -->
                <a href="/profile" class="<?php echo strpos($_cur, 'profile') !== false ? 'active':''; ?>">&#128100; Profil</a>
                <?php if($_urole === 'ADMIN'): /* Lien admin : visible uniquement pour les admins */ ?>
                    <a href="/admin/dashboard" class="admin-link <?php echo strpos($_cur, 'admin') !== false ? 'active':''; ?>">&#9881;&#65039; Admin</a>
                <?php endif; ?>
                <!-- Bouton de déconnexion -->
                <a href="/logout" class="nav-btn logout-btn">&#128682; D&eacute;connexion</a>
            <?php else: /* Liens visibles quand l'utilisateur n'est PAS connecté */ ?>
                <a href="/" class="<?php echo $_cur === '' || $_cur === '/' ? 'active':''; ?>">Accueil</a>
                <a href="/register" class="<?php echo strpos($_cur, 'register') !== false ? 'active':''; ?>">S'inscrire</a>
                <a href="/login" class="nav-btn">&#128273; Se connecter</a>
            <?php endif; ?>
        </div>
        <!-- Bouton pour basculer entre le mode clair et le mode sombre -->
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Mode nuit / jour">&#127769;</button>
        <!-- Bouton hamburger pour les petits écrans (ouvre/ferme le menu) -->
        <button class="hamburger" onclick="document.getElementById('navLinks').classList.toggle('open')">&#9776;</button>
        </div>

    </div>
</nav>
<script>
// Script exécuté immédiatement : applique le thème sauvegardé (clair ou sombre)
(function(){
    var saved = localStorage.getItem('emoeat_theme') || 'light'; // Lit depuis localStorage
    document.documentElement.setAttribute('data-theme', saved);  // Applique l'attribut data-theme sur <html>
    var btn = document.getElementById('themeToggle');             // Récupère le bouton
    if(btn) btn.textContent = saved === 'dark' ? '\u2600\uFE0F' : '\uD83C\uDF19'; // ☀️ ou 🌙
})();
// Fonction appelée lors du clic sur le bouton de thème : bascule entre clair et sombre
function toggleTheme(){
    var root = document.documentElement;
    var isDark = root.getAttribute('data-theme') === 'dark'; // Vérifie le thème actuel
    var next = isDark ? 'light' : 'dark';                    // Inverse le thème
    root.setAttribute('data-theme', next);                   // Applique le nouveau thème
    localStorage.setItem('emoeat_theme', next);              // Sauvegarde dans localStorage
    document.getElementById('themeToggle').textContent = next === 'dark' ? '\u2600\uFE0F' : '\uD83C\uDF19'; // Met à jour l'icône
}
</script>
