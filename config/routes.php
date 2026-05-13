<?php
/**
 * config/routes.php
 * Définition de toutes les routes de l'application EmoEat.
 * $router est injecté depuis public/index.php via $app->getRouter().
 * Chaque route associe : une méthode HTTP + un chemin URL → Controller@méthode
 */

// --- PAGE D'ACCUEIL ---
// GET / → affiche la page d'accueil publique
$router->get('/', 'HomeController@index');

// --- AUTHENTIFICATION ---
// GET /login → affiche le formulaire de connexion
$router->get('/login', 'AuthController@loginForm');
// POST /login → traite le formulaire de connexion (vérifie email/mot de passe)
$router->post('/login', 'AuthController@login');
// GET /register → affiche le formulaire d'inscription
$router->get('/register', 'AuthController@registerForm');
// POST /register → traite l'inscription (crée le compte)
$router->post('/register', 'AuthController@register');
// GET /logout → déconnecte l'utilisateur et redirige vers /login
$router->get('/logout', 'AuthController@logout');
// GET /forgot-password → affiche le formulaire "mot de passe oublié"
$router->get('/forgot-password', 'AuthController@forgotPasswordForm');
// POST /forgot-password → envoie l'email de réinitialisation
$router->post('/forgot-password', 'AuthController@forgotPassword');
// GET /reset-password → affiche le formulaire de réinitialisation (avec token dans l'URL)
$router->get('/reset-password', 'AuthController@resetPasswordForm');
// POST /reset-password → traite le nouveau mot de passe et invalide le token
$router->post('/reset-password', 'AuthController@resetPassword');

// --- TABLEAU DE BORD UTILISATEUR ---
// GET /dashboard → affiche le tableau de bord personnel de l'utilisateur connecté
$router->get('/dashboard', 'DashboardController@index');

// --- ADMINISTRATION (réservé aux ADMIN) ---
// GET /admin/dashboard → tableau de bord admin avec les statistiques globales
$router->get('/admin/dashboard', 'AdminController@dashboard');
// GET /admin/users → liste des utilisateurs
$router->get('/admin/users', 'AdminController@users');
// POST /admin/users → actions sur les utilisateurs (changer rôle, supprimer...)
$router->post('/admin/users', 'AdminController@usersPost');
// GET /admin/foods → liste des aliments
$router->get('/admin/foods', 'AdminController@foods');
// POST /admin/foods → ajout ou suppression d'un aliment
$router->post('/admin/foods', 'AdminController@foodsPost');
// GET /admin/emotions → liste des émotions et leurs règles d'association
$router->get('/admin/emotions', 'AdminController@emotions');
// POST /admin/emotions → ajout, suppression d'émotion ou de règle émotion→aliment
$router->post('/admin/emotions', 'AdminController@emotionsPost');
// GET /admin/activity-log → journal de toutes les actions des utilisateurs
$router->get('/admin/activity-log', 'AdminController@activityLog');

// --- PROFIL NUTRITIONNEL ---
// GET /profile → affiche le profil (poids, taille, allergies, IMC calculé)
$router->get('/profile', 'ProfileController@index');
// POST /profile → sauvegarde le profil nutritionnel
$router->post('/profile', 'ProfileController@save');

// --- RECOMMANDATION ---
// GET /recommendation → affiche le formulaire de sélection d'émotion (avec token CSRF)
$router->get('/recommendation', 'RecommendationController@index');
// POST /recommendation → génère les recommandations alimentaires selon l'émotion choisie
$router->post('/recommendation', 'RecommendationController@getRecommendation');

// --- HISTORIQUE ---
// GET /history → affiche l'historique des émotions et recommandations de l'utilisateur
$router->get('/history', 'HistoryController@index');
