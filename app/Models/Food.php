<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model

// Classe Food : gère toutes les opérations sur la table FOODS dans la base de données
class Food extends Model
{
    // Fonction : chercher un aliment par son ID
    // Retourne toutes les colonnes de l'aliment ou null si non trouvé
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM FOODS WHERE ID_FOOD = :id");
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT); // Lie l'ID en tant qu'entier
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null; // Retourne le résultat ou null
    }

    // Fonction : ajouter un nouvel aliment dans la base de données
    // Reçoit toutes les informations nutritionnelles de l'aliment
    public function create(string $name, string $category, int $calories, float $protein, float $carbs, float $fat, string $description): void
    {
        // Insère l'aliment avec tous ses champs nutritionnels
        $stmt = $this->db->prepare(
            "INSERT INTO FOODS (FOOD_NAME, CATEGORY, CALORIES, PROTEIN, CARBS, FAT, DESCRIPTION)
             VALUES (:name, :cat, :cal, :prot, :carbs, :fat, :desc)"
        );
        $stmt->bindParam(':name', $name);              // Nom de l'aliment
        $stmt->bindParam(':cat', $category);           // Catégorie (ex: Fruit, Dairy)
        $stmt->bindParam(':cal', $calories, \PDO::PARAM_INT); // Calories (entier)
        $stmt->bindParam(':prot', $protein);           // Protéines
        $stmt->bindParam(':carbs', $carbs);            // Glucides
        $stmt->bindParam(':fat', $fat);                // Lipides
        $stmt->bindParam(':desc', $description);       // Description/bénéfices
        $stmt->execute();
    }

    // Fonction : supprimer un aliment et toutes ses données liées
    // Supprime d'abord les dépendances (règles, recommandations) avant de supprimer l'aliment
    public function delete(int $id): void
    {
        foreach ([
            "DELETE FROM EMOTION_FOOD WHERE ID_FOOD = :id",      // 1. Supprime les règles émotion-aliment
            "DELETE FROM RECOMMENDATIONS WHERE ID_FOOD = :id",   // 2. Supprime les recommandations
            "DELETE FROM FOODS WHERE ID_FOOD = :id",             // 3. Supprime l'aliment lui-même
        ] as $sql) {
            $st = $this->db->prepare($sql);
            $st->bindParam(':id', $id, \PDO::PARAM_INT); // Lie l'ID pour chaque requête
            $st->execute();
        }
    }

    // Fonction : rechercher des aliments par nom ou catégorie
    // Si $query est vide, retourne tous les aliments
    public function search(string $query = ''): array
    {
        if ($query !== '') {
            // Recherche insensible à la casse dans le nom et la catégorie
            $stmt = $this->db->prepare(
                "SELECT ID_FOOD, FOOD_NAME, CATEGORY, CALORIES, PROTEIN, CARBS, FAT, DESCRIPTION
                 FROM FOODS WHERE LOWER(FOOD_NAME) LIKE :q OR LOWER(CATEGORY) LIKE :q ORDER BY FOOD_NAME"
            );
            $like = '%' . strtolower($query) . '%'; // Prépare le pattern de recherche
            $stmt->bindParam(':q', $like);
        } else {
            // Pas de recherche : retourne tous les aliments triés par nom
            $stmt = $this->db->prepare(
                "SELECT ID_FOOD, FOOD_NAME, CATEGORY, CALORIES, PROTEIN, CARBS, FAT, DESCRIPTION FROM FOODS ORDER BY FOOD_NAME"
            );
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC); // Retourne tous les résultats
    }

    // Fonction : récupérer tous les aliments (ID, nom, catégorie seulement)
    // Utilisé pour remplir les listes déroulantes dans l'interface admin
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT ID_FOOD, FOOD_NAME, CATEGORY FROM FOODS ORDER BY FOOD_NAME");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Fonction : compter le nombre total d'aliments dans la base
    // Utilisé dans le tableau de bord admin pour afficher les statistiques
    public function countAll(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM FOODS");
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C']; // Convertit en entier et retourne
    }
}
