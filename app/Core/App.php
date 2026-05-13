<?php
namespace App\Core; // Ce fichier appartient au namespace App\Core

// Classe App : point de démarrage de toute l'application
// C'est la première classe instanciée dans public/index.php
class App
{
    // Propriété : contient l'objet Router qui gère les routes
    protected Router $router;

    // Constructeur : appelé automatiquement quand on fait new App()
    public function __construct()
    {
        session_start();              // Démarre la session PHP (pour stocker user_id, role, etc.)
        $this->router = new Router(); // Crée une nouvelle instance du Router
    }

    // Fonction : retourner l'objet Router
    // Utilisé dans public/index.php pour enregistrer les routes
    public function getRouter(): Router
    {
        return $this->router;
    }

    // Fonction : lancer l'application
    // Récupère l'URI et la méthode HTTP, puis demande au Router de trouver le bon Controller
    public function run(): void
    {
        $uri = $this->getUri();              // Récupère l'URL demandée (ex: /login, /dashboard)
        $method = $_SERVER['REQUEST_METHOD']; // Récupère la méthode HTTP (GET ou POST)
        $this->router->dispatch($uri, $method); // Le Router trouve et appelle le bon Controller
    }

    // Fonction : extraire l'URI propre de la requête HTTP
    // Supprime le dossier du script pour avoir un chemin relatif propre
    protected function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/'; // URL complète envoyée par le navigateur
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // Dossier où se trouve index.php
        if ($scriptDir !== '/' && $scriptDir !== '\\') {
            // Si le site est dans un sous-dossier, on supprime ce préfixe de l'URL
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = parse_url($uri, PHP_URL_PATH); // Supprime les paramètres GET (ex: ?id=1)
        $uri = '/' . trim($uri, '/');          // Normalise : toujours commencer par /
        return $uri;
    }
}
