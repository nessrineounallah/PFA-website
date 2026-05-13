<?php
namespace App\Core; // Ce fichier appartient au namespace App\Core

// Classe Router : gère toutes les routes de l'application
// Une route = une URL + méthode HTTP (GET/POST) → un Controller@méthode
class Router
{
    // Propriété : tableau qui stocke toutes les routes enregistrées
    protected array $routes = [];

    // Fonction : enregistrer une route GET
    // Exemple: $router->get('/login', 'AuthController@loginForm')
    public function get(string $path, string $action): void
    {
        $this->addRoute('GET', $path, $action); // Ajoute la route avec la méthode GET
    }

    // Fonction : enregistrer une route POST (formulaires)
    // Exemple: $router->post('/login', 'AuthController@login')
    public function post(string $path, string $action): void
    {
        $this->addRoute('POST', $path, $action); // Ajoute la route avec la méthode POST
    }

    // Fonction interne : ajouter une route dans le tableau $routes
    // Sépare 'Controller@methode' en deux parties et stocke tout dans un tableau
    protected function addRoute(string $method, string $path, string $action): void
    {
        [$controller, $method_name] = explode('@', $action); // Sépare 'AuthController@login' en ['AuthController', 'login']
        $this->routes[] = [
            'method' => $method,                              // GET ou POST
            'path' => $path,                                  // URL (ex: /login)
            'controller' => 'App\\Controllers\\' . $controller, // Nom complet de la classe
            'action' => $method_name,                         // Nom de la méthode à appeler
        ];
    }

    // Fonction : trouver la route correspondante et appeler le bon Controller
    // Parcourt toutes les routes enregistrées, compare l'URL et la méthode HTTP
    public function dispatch(string $uri, string $method): void
    {
        foreach ($this->routes as $route) {
            // Vérifie si la méthode HTTP correspond ET si l'URL correspond
            if ($route['method'] === $method && $this->matchPath($route['path'], $uri, $params)) {
                $controllerClass = $route['controller']; // Ex: App\Controllers\AuthController
                $actionMethod = $route['action'];        // Ex: login
                $controller = new $controllerClass();    // Crée une instance du Controller
                call_user_func_array([$controller, $actionMethod], $params); // Appelle la méthode avec les paramètres d'URL
                return; // Arrête après avoir trouvé la route
            }
        }

        // Aucune route trouvée → affiche une erreur 404
        http_response_code(404);
        echo '<h1>404 - Page non trouvée</h1>';
    }

    // Fonction : vérifier si une URL correspond à un chemin de route
    // Gère les paramètres dynamiques comme {id} dans /user/{id}
    protected function matchPath(string $routePath, string $uri, &$params = []): bool
    {
        $params = [];
        // Transforme {id} en un pattern regex nommé : (?P<id>[^/]+)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#'; // Encadre le pattern pour matcher toute l'URL

        if (preg_match($pattern, $uri, $matches)) {
            // Extrait les valeurs des paramètres dynamiques (ex: l'id dans /user/5)
            foreach ($matches as $key => $value) {
                if (is_string($key)) { // Garde uniquement les groupes nommés (pas les indices numériques)
                    $params[] = $value;
                }
            }
            return true; // L'URL correspond
        }
        return false; // L'URL ne correspond pas
    }
}
