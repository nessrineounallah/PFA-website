<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model

// Classe Recommendation : gère les recommandations alimentaires des utilisateurs
// Enregistre les aliments recommandés selon l'émotion de l'utilisateur
class Recommendation extends Model
{
    // Fonction : compter le nombre de recommandations reçues par un utilisateur
    // Utilisé dans le tableau de bord pour afficher les statistiques personnelles
    public function countByUser(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM RECOMMENDATIONS WHERE ID_USER = :u");
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C']; // Retourne le nombre de recommandations
    }

    // Fonction : récupérer les dernières recommandations d'un utilisateur
    // $limit définit le nombre maximum de résultats à retourner (5 par défaut)
    public function getRecentByUser(int $userId, int $limit = 5): array
    {
        // Jointures pour récupérer le nom de l'aliment et le nom de l'émotion
        $stmt = $this->db->prepare(
            "SELECT f.FOOD_NAME, e.EMOTION_NAME, r.RECOMMENDATION_DATE
             FROM RECOMMENDATIONS r
             JOIN FOODS f ON f.ID_FOOD = r.ID_FOOD       -- Jointure pour avoir le nom de l'aliment
             JOIN EMOTIONS e ON e.ID_EMOTION = r.ID_EMOTION -- Jointure pour avoir le nom de l'émotion
             WHERE r.ID_USER = :u
             ORDER BY r.RECOMMENDATION_DATE DESC" // Du plus récent au plus ancien
        );
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        // Limite manuellement le nombre de résultats
        $results = [];
        $count = 0;
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($count >= $limit) break; // Arrête quand la limite est atteinte
            $results[] = $row;
            $count++;
        }
        return $results;
    }

    // Fonction : récupérer tout l'historique des recommandations d'un utilisateur
    // Retourne les détails complets : aliment, calories, catégorie, bénéfice, date, émotion
    public function getHistoryByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT f.FOOD_NAME, f.CALORIES, f.CATEGORY,
                    r.BENEFIT, r.RECOMMENDATION_DATE, e.EMOTION_NAME
             FROM RECOMMENDATIONS r
             JOIN FOODS f ON f.ID_FOOD = r.ID_FOOD
             JOIN EMOTIONS e ON e.ID_EMOTION = r.ID_EMOTION
             WHERE r.ID_USER = :u
             ORDER BY r.RECOMMENDATION_DATE DESC"
        );
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Retourne toutes les recommandations
    }

    // Fonction : enregistrer une recommandation dans la base de données
    // Appelée quand l'utilisateur sélectionne des aliments suite à une émotion
    public function save(int $emotionId, int $foodId, string $benefit, int $userId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO RECOMMENDATIONS (id_emotion, id_food, benefit, id_user, recommendation_date)
             VALUES (:e, :f, :b, :u, NOW())" // NOW() insère automatiquement la date et l'heure actuelles
        );
        // Passe toutes les valeurs en une seule fois via le tableau
        $stmt->execute([':e' => $emotionId, ':f' => $foodId, ':b' => $benefit, ':u' => $userId]);
    }

    // Fonction : compter le nombre total de recommandations dans la base
    // Utilisé dans le tableau de bord admin pour afficher les statistiques globales
    public function countAll(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM RECOMMENDATIONS");
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C']; // Convertit en entier et retourne
    }
}
