<?php
namespace App\Core; // Ce fichier appartient au namespace App\Core

use Database; // Importe la classe Database (config/Database.php) pour la connexion MySQL

// Classe Controller : classe de base dont héritent tous les Controllers
// Elle fournit des outils communs : connexion DB, affichage de vues, redirections, gestion de session
class Controller
{
    // Propriété : connexion PDO à la base de données
    protected \PDO $db;

    // Constructeur : appelé automatiquement à la création de chaque Controller
    // Établit la connexion à la base de données via la classe Database
    public function __construct()
    {
        $database = new \Database();        // Crée une instance de la classe Database
        $this->db = $database->getConnection(); // Récupère la connexion PDO
    }

    // Fonction : afficher une vue (fichier HTML/PHP dans app/Views/)
    // $viewName = chemin relatif ex: 'auth/login', $data = tableau de variables à passer à la vue
    protected function view(string $viewName, array $data = []): void
    {
        extract($data); // Convertit le tableau en variables individuelles (ex: $data['name'] devient $name)
        $viewPath = dirname(__DIR__) . '/Views/' . $viewName . '.php'; // Chemin complet vers le fichier vue
        if (!file_exists($viewPath)) {
            // Si la vue n'existe pas, retourne une erreur 500
            http_response_code(500);
            echo "View not found: $viewName";
            return;
        }
        require $viewPath; // Inclut et exécute le fichier de la vue
    }

    // Fonction : rediriger le navigateur vers une autre URL
    // exit() arrête l'exécution du script après la redirection
    protected function redirect(string $url): void
    {
        header("Location: " . $url); // Envoie l'en-tête HTTP de redirection
        exit(); // Arrête le script pour éviter d'exécuter du code après la redirection
    }

    // Fonction : vérifier si l'utilisateur est connecté
    // Retourne true si la session contient un user_id
    protected function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    // Fonction : vérifier si l'utilisateur connecté est un administrateur
    // L'utilisateur doit être connecté ET avoir le rôle 'ADMIN'
    protected function isAdmin(): bool
    {
        return $this->isLoggedIn() && strtoupper(trim($_SESSION['role'] ?? '')) === 'ADMIN';
    }

    // Fonction : forcer la connexion — redirige vers /login si non connecté
    // À appeler au début de chaque méthode qui nécessite une connexion
    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            $this->redirect('/login'); // Redirige vers la page de connexion
        }
    }

    // Fonction : forcer le rôle admin — redirige vers /login si pas admin
    // À appeler au début de chaque méthode réservée aux administrateurs
    protected function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->redirect('/login'); // Redirige si l'utilisateur n'est pas admin
        }
    }

    // Fonction : récupérer l'ID de l'utilisateur connecté depuis la session
    // Retourne 0 si personne n'est connecté
    protected function getUserId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    // Fonction : récupérer le nom de l'utilisateur connecté depuis la session
    // Retourne 'Utilisateur' par défaut si le nom n'est pas en session
    protected function getUserName(): string
    {
        return $_SESSION['user_name'] ?? 'Utilisateur';
    }

    // Fonction : récupérer le rôle de l'utilisateur connecté (CLIENT ou ADMIN)
    // Retourne 'CLIENT' par défaut
    protected function getUserRole(): string
    {
        return strtoupper(trim($_SESSION['role'] ?? 'CLIENT'));
    }
}
