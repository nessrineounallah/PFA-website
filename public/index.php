<?php
/**
 * Front Controller - public/index.php
 * C'est le seul point d'entrée de toute l'application.
 * Toutes les requêtes HTTP passent par ce fichier (grâce à .htaccess).
 */

// Charge l'autoloader de Composer : permet d'utiliser toutes les classes sans require() manuel
// Il utilise le namespace (App\Controllers, App\Models...) pour trouver les fichiers automatiquement
require __DIR__ . '/../vendor/autoload.php';

// Crée l'application : démarre la session, instancie le Router
$app = new App\Core\App();

// Récupère l'objet Router pour pouvoir enregistrer les routes
$router = $app->getRouter();

// Charge le fichier de routes qui définit toutes les URLs du site
// Exemple: $router->get('/login', 'AuthController@loginForm')
require __DIR__ . '/../config/routes.php';

// Lance l'application : compare l'URL courante aux routes enregistrées
// et appelle le bon Controller@méthode
$app->run();
