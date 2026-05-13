<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model

// Classe ActivityLog : enregistre et consulte le journal d'activité du site
// Chaque action importante (connexion, inscription, modification...) est tracée ici
class ActivityLog extends Model
{
    // Fonction : enregistrer une action dans le journal d'activité
    // Reçoit l'ID de l'utilisateur et le nom de l'action (ex: 'USER_LOGIN')
    public function log(int $userId, string $action): void
    {
        try {
            // Insère l'action avec la date et l'heure actuelles (NOW())
            $stmt = $this->db->prepare(
                "INSERT INTO ACTIVITY_LOG (ID_USER, ACTION, LOG_DATE) VALUES (:u, :a, NOW())"
            );
            $stmt->bindParam(':u', $userId, \PDO::PARAM_INT); // Lie l'ID utilisateur
            $stmt->bindParam(':a', $action, \PDO::PARAM_STR);  // Lie le nom de l'action
            $stmt->execute();
        } catch (\PDOException $e) {
            // Si erreur SQL, on l'ignore silencieusement pour ne pas bloquer l'application
        }
    }

    // Fonction : rechercher dans le journal d'activité
    // Si $query est vide, retourne tout le journal ; sinon filtre par nom, email ou action
    public function search(string $query = ''): array
    {
        if ($query !== '') {
            // Recherche dans le nom de l'utilisateur, son email ou le nom de l'action
            $stmt = $this->db->prepare(
                "SELECT al.ID_LOG, al.ACTION, al.LOG_DATE, u.NAME, u.EMAIL
                 FROM ACTIVITY_LOG al JOIN USERS u ON u.ID_USER = al.ID_USER
                 WHERE LOWER(u.NAME) LIKE :q1 OR LOWER(u.EMAIL) LIKE :q2 OR LOWER(al.ACTION) LIKE :q3
                 ORDER BY al.LOG_DATE DESC"
            );
            $like = '%' . strtolower($query) . '%'; // Prépare le pattern de recherche
            $stmt->bindParam(':q1', $like); // Lie pour la recherche par nom
            $stmt->bindParam(':q2', $like); // Lie pour la recherche par email
            $stmt->bindParam(':q3', $like); // Lie pour la recherche par action
        } else {
            // Pas de recherche : retourne tout le journal, du plus récent au plus ancien
            $stmt = $this->db->prepare(
                "SELECT al.ID_LOG, al.ACTION, al.LOG_DATE, u.NAME, u.EMAIL
                 FROM ACTIVITY_LOG al JOIN USERS u ON u.ID_USER = al.ID_USER
                 ORDER BY al.LOG_DATE DESC"
            );
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Retourne toutes les lignes du journal
    }
}
