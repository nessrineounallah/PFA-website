<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model

// Classe UserProfile : gère le profil nutritionnel des utilisateurs
// Stocke : poids, taille, allergies, objectif (ex: perte de poids)
class UserProfile extends Model
{
    // Fonction : récupérer le profil nutritionnel d'un utilisateur par son ID
    // Retourne le profil ou null si l'utilisateur n'a pas encore créé de profil
    public function findByUser(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM USER_PROFILE WHERE ID_USER = :u");
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null; // Retourne le profil ou null
    }

    // Fonction : vérifier si l'utilisateur a déjà un profil enregistré
    // Utilisé dans le tableau de bord pour afficher un avertissement si le profil est manquant
    public function hasProfile(int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM USER_PROFILE WHERE ID_USER = :u");
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C'] > 0; // Retourne true si au moins une ligne existe
    }

    // Fonction : sauvegarder (créer ou mettre à jour) le profil nutritionnel
    // Si le profil existe déjà : UPDATE ; sinon : INSERT
    public function save(int $userId, float $weight, float $height, string $allergies, string $goal): void
    {
        $existing = $this->findByUser($userId); // Vérifie si un profil existe déjà

        if ($existing) {
            // Le profil existe : on le met à jour
            $sql = "UPDATE USER_PROFILE SET WEIGHT = :weight, HEIGHT = :height,
                    ALLERGIES = :allergies, GOAL = :goal WHERE ID_USER = :u";
        } else {
            // Pas encore de profil : on en crée un nouveau
            $sql = "INSERT INTO USER_PROFILE (ID_USER, WEIGHT, HEIGHT, ALLERGIES, GOAL)
                    VALUES (:u, :weight, :height, :allergies, :goal)";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':u', $userId, \PDO::PARAM_INT); // ID de l'utilisateur
        $stmt->bindParam(':weight', $weight);              // Poids en kg
        $stmt->bindParam(':height', $height);              // Taille en cm
        $stmt->bindParam(':allergies', $allergies);        // Liste des allergies
        $stmt->bindParam(':goal', $goal);                  // Objectif (ex: perte de poids)
        $stmt->execute();
    }
}
