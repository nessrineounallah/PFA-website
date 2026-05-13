<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller;    // Importe la classe parente Controller
use App\Models\Recommendation; // Pour récupérer l'historique des recommandations
use App\Models\UserEmotion;    // Pour récupérer l'historique des émotions

// Classe HistoryController : gère la page d'historique de l'utilisateur
// Affiche l'historique des émotions saisies et des aliments recommandés
class HistoryController extends Controller
{
    // Fonction : afficher la page d'historique
    // Nécessite une connexion (requireAuth)
    public function index(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId(); // Récupère l'ID de l'utilisateur connecté

        $emoModel = new UserEmotion($this->db);      // Instancie le Model des émotions
        $recModel = new Recommendation($this->db);   // Instancie le Model des recommandations

        $emoHistory = $emoModel->getHistoryByUser($userId); // Récupère l'historique des émotions
        $history = $recModel->getHistoryByUser($userId);    // Récupère l'historique des recommandations

        // Envoie les deux historiques à la vue
        $this->view('history/index', [
            'emoHistory' => $emoHistory, // Liste des émotions enregistrées
            'history' => $history,       // Liste des aliments recommandés
        ]);
    }

    // Fonction statique : retourner l'emoji correspondant à une émotion
    // Exemple : 'happy' -> '😊', 'sad' -> '😢'
    // Statique = peut être appelée sans créer une instance : HistoryController::emoEmoji('happy')
    public static function emoEmoji(string $name): string
    {
        // Tableau qui associe chaque émotion à son emoji
        $map = [
            'happy' => '😊', 'sad' => '😢', 'angry' => '😠', 'stress' => '😰',
            'stressed' => '😰', 'excited' => '🤩', 'anxious' => '😟', 'calm' => '😌',
            'tired' => '😴', 'fear' => '😱', 'joy' => '😄',
        ];
        // Retourne l'emoji trouvé, ou '😶' par défaut si l'émotion est inconnue
        return $map[strtolower(trim($name))] ?? '😶';
    }
}
