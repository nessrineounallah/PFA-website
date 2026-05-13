<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller; // Importe la classe parente Controller
use App\Models\Recommendation; // Pour compter et récupérer les recommandations de l'utilisateur
use App\Models\UserEmotion;    // Pour compter les émotions enregistrées
use App\Models\UserProfile;    // Pour vérifier si l'utilisateur a un profil nutritionnel

// Classe DashboardController : gère le tableau de bord personnel de l'utilisateur connecté
class DashboardController extends Controller
{
    // Fonction : afficher le tableau de bord de l'utilisateur
    // Nécessite une connexion (requireAuth)
    public function index(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId();    // Récupère l'ID de l'utilisateur connecté
        $name = $this->getUserName();    // Récupère le nom de l'utilisateur
        $role = $this->getUserRole();    // Récupère le rôle (CLIENT ou ADMIN)

        $recModel = new Recommendation($this->db);  // Instancie le Model des recommandations
        $emoModel = new UserEmotion($this->db);      // Instancie le Model des émotions
        $profileModel = new UserProfile($this->db);  // Instancie le Model du profil

        $cntRec = $recModel->countByUser($userId);         // Nombre total de recommandations reçues
        $cntEmo = $emoModel->countByUser($userId);         // Nombre total d'émotions enregistrées
        $hasProfile = $profileModel->hasProfile($userId);  // true si le profil est complété
        $recent = $recModel->getRecentByUser($userId, 5);  // 5 dernières recommandations

        // Envoie toutes ces données à la vue pour les afficher
        $this->view('dashboard/index', [
            'name' => $name,           // Nom de l'utilisateur
            'role' => $role,           // Rôle (CLIENT/ADMIN)
            'cntRec' => $cntRec,       // Compteur de recommandations
            'cntEmo' => $cntEmo,       // Compteur d'émotions
            'hasProfile' => $hasProfile, // Si le profil est présent
            'recent' => $recent,       // Derniers aliments recommandés
        ]);
    }
}
