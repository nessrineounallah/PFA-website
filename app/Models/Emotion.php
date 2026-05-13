<?php
// Déclare que ce fichier utilise PHP
namespace App\Models; // Déclare que cette classe appartient au namespace "App\Models"

use App\Core\Model; // Importe la classe parente Model (qui contient la connexion PDO $this->db)

// Classe Emotion : gère toutes les opérations sur la table EMOTIONS dans la base de données
class Emotion extends Model
{
    // Fonction : chercher une émotion par son ID
    // Reçoit un entier $id, retourne un tableau (array) ou null si non trouvé
    public function findById(int $id): ?array
    {
        // Prépare la requête SQL pour sélectionner l'émotion avec l'ID donné
        $stmt = $this->db->prepare("SELECT * FROM EMOTIONS WHERE ID_EMOTION = :id");
        // Lie la valeur de $id au paramètre :id dans la requête (PARAM_INT = type entier)
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        // Exécute la requête SQL
        $stmt->execute();
        // Récupère une seule ligne de résultat sous forme de tableau associatif (clé => valeur)
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Retourne le résultat si trouvé, sinon retourne null
        return $row ?: null;
    }

    // Fonction : récupérer toutes les émotions triées par nom
    // Retourne un tableau de toutes les émotions
    public function getAll(): array
    {
        // Prépare la requête SQL pour sélectionner ID, nom et description de toutes les émotions
        $stmt = $this->db->prepare("SELECT ID_EMOTION, EMOTION_NAME, DESCRIPTION FROM EMOTIONS ORDER BY EMOTION_NAME");
        // Exécute la requête (pas de paramètres à lier ici)
        $stmt->execute();
        // Retourne toutes les lignes sous forme de tableau de tableaux associatifs
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Fonction : récupérer les émotions groupées par nom (sans doublons)
    // Utile pour afficher une liste unique d'émotions
    public function getGrouped(): array
    {
        // Prépare la requête SQL : GROUP BY supprime les doublons, MIN() garde le plus petit ID
        $stmt = $this->db->prepare("SELECT MIN(ID_EMOTION) AS ID_EMOTION, EMOTION_NAME FROM EMOTIONS GROUP BY EMOTION_NAME ORDER BY EMOTION_NAME");
        // Exécute la requête
        $stmt->execute();
        // Retourne le résultat sous forme de tableau
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Fonction : créer (ajouter) une nouvelle émotion dans la base de données
    // Reçoit le nom et la description, ne retourne rien (void)
    public function create(string $name, string $description): void
    {
        // Prépare la requête SQL d'insertion avec deux paramètres :name et :desc
        $stmt = $this->db->prepare("INSERT INTO EMOTIONS (EMOTION_NAME, DESCRIPTION) VALUES (:name, :desc)");
        // Lie la variable $name au paramètre :name
        $stmt->bindParam(':name', $name);
        // Lie la variable $description au paramètre :desc
        $stmt->bindParam(':desc', $description);
        // Exécute l'insertion dans la base de données
        $stmt->execute();
    }

    // Fonction : supprimer une émotion et toutes ses données liées
    // Reçoit l'ID de l'émotion à supprimer, ne retourne rien (void)
    public function delete(int $id): void
    {
        // Boucle sur une liste de 4 requêtes SQL à exécuter dans l'ordre
        foreach ([
            // 1. Supprime les règles associées à cette émotion dans la table EMOTION_FOOD
            "DELETE FROM EMOTION_FOOD WHERE ID_EMOTION = :id",
            // 2. Supprime les entrées de cette émotion dans les émotions des utilisateurs
            "DELETE FROM USER_EMOTIONS WHERE ID_EMOTION = :id",
            // 3. Supprime les recommandations liées à cette émotion
            "DELETE FROM RECOMMENDATIONS WHERE ID_EMOTION = :id",
            // 4. Supprime l'émotion elle-même de la table EMOTIONS
            "DELETE FROM EMOTIONS WHERE ID_EMOTION = :id",
        ] as $sql) {
            // Prépare chaque requête SQL de la liste
            $st = $this->db->prepare($sql);
            // Lie l'ID au paramètre :id pour chaque requête
            $st->bindParam(':id', $id, \PDO::PARAM_INT);
            // Exécute la requête de suppression
            $st->execute();
        }
    }

    // Fonction : ajouter une règle qui lie une émotion à un aliment avec une intensité
    // Reçoit l'ID de l'émotion, l'ID de l'aliment, et un score d'intensité
    public function addRule(int $emotionId, int $foodId, int $intensity): void
    {
        // Prépare la requête SQL d'insertion dans la table EMOTION_FOOD
        $stmt = $this->db->prepare(
            "INSERT INTO EMOTION_FOOD (ID_EMOTION, ID_FOOD, INTENSITY) VALUES (:e, :f, :i)"
        );
        // Lie l'ID de l'émotion au paramètre :e
        $stmt->bindParam(':e', $emotionId, \PDO::PARAM_INT);
        // Lie l'ID de l'aliment au paramètre :f
        $stmt->bindParam(':f', $foodId, \PDO::PARAM_INT);
        // Lie le score d'intensité au paramètre :i
        $stmt->bindParam(':i', $intensity, \PDO::PARAM_INT);
        // Exécute l'insertion
        $stmt->execute();
    }

    // Fonction : récupérer toutes les règles (émotion → aliment) avec les détails
    // Retourne un tableau de toutes les règles triées par intensité décroissante
    public function getRules(): array
    {
        // Prépare la requête SQL avec des jointures pour récupérer les noms des émotions et aliments
        $stmt = $this->db->prepare(
            "SELECT ef.ID_RULE, ef.INTENSITY, e.EMOTION_NAME, f.FOOD_NAME
             FROM EMOTION_FOOD ef
             JOIN EMOTIONS e ON e.ID_EMOTION = ef.ID_EMOTION  -- Jointure pour avoir le nom de l'émotion
             JOIN FOODS f ON f.ID_FOOD = ef.ID_FOOD            -- Jointure pour avoir le nom de l'aliment
             ORDER BY ef.INTENSITY DESC" // Trier du plus intense au moins intense
        );
        // Exécute la requête
        $stmt->execute();
        // Retourne toutes les règles sous forme de tableau
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Fonction : récupérer les aliments recommandés pour une émotion spécifique
    // Reçoit l'ID de l'émotion, retourne la liste des aliments avec leurs détails nutritionnels
    public function getFoodsForEmotion(int $emotionId): array
    {
        // Prépare la requête SQL avec jointure entre EMOTION_FOOD et FOODS
        $stmt = $this->db->prepare(
            "SELECT f.id_food, f.food_name, f.calories, f.category,
                    f.protein, f.carbs, f.fat, f.description AS benefit,
                    ef.intensity AS score          -- L'intensité devient le score de recommandation
             FROM EMOTION_FOOD ef
             JOIN FOODS f ON f.id_food = ef.id_food  -- Jointure pour récupérer les détails de l'aliment
             WHERE ef.id_emotion = :emo              -- Filtre par l'ID de l'émotion donnée
             ORDER BY ef.intensity DESC" // Trier du plus recommandé au moins recommandé
        );
        // Lie l'ID de l'émotion au paramètre :emo
        $stmt->bindParam(':emo', $emotionId, \PDO::PARAM_INT);
        // Exécute la requête
        $stmt->execute();
        // Retourne tous les aliments trouvés
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Fonction : compter le nombre total d'émotions dans la base de données
    // Retourne un entier (int)
    public function countAll(): int
    {
        // Prépare la requête SQL pour compter toutes les lignes de la table EMOTIONS
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM EMOTIONS");
        // Exécute la requête
        $stmt->execute();
        // Récupère la ligne résultat (contient la clé 'C' avec le nombre)
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        // Convertit la valeur en entier et la retourne
        return (int)$r['C'];
    }
}
