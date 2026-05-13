<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model

// Classe UserEmotion : enregistre et consulte les émotions saisies par les utilisateurs
// Chaque fois qu'un utilisateur choisit une émotion, elle est enregistrée avec la date
class UserEmotion extends Model
{
    // Fonction : compter combien de fois un utilisateur a enregistré une émotion
    // Utilisé dans le tableau de bord pour afficher les statistiques personnelles
    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM USER_EMOTIONS WHERE ID_USER = :u");
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C']; // Retourne le nombre d'émotions enregistrées
    }

    // Fonction : récupérer l'historique des émotions d'un utilisateur
    // Retourne la liste des émotions avec leur date, du plus récent au plus ancien
    public function getHistoryByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ue.EMOTION_DATE, e.EMOTION_NAME
             FROM USER_EMOTIONS ue
             JOIN EMOTIONS e ON e.ID_EMOTION = ue.ID_EMOTION -- Jointure pour avoir le nom de l'émotion
             WHERE ue.ID_USER = :u
             ORDER BY ue.EMOTION_DATE DESC" // Du plus récent au plus ancien
        );
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Retourne toutes les émotions
    }

    // Fonction : enregistrer une émotion pour un utilisateur
    // Appelée quand l'utilisateur choisit une émotion et demande une recommandation
    public function save(int $userId, int $emotionId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO USER_EMOTIONS (id_user, id_emotion, emotion_date) VALUES (:u, :e, NOW())"
        );
        $stmt->execute([':u' => $userId, ':e' => $emotionId]); // Passe les valeurs directement
    }
}
