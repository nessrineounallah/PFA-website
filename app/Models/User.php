<?php
namespace App\Models; // Ce fichier appartient au namespace App\Models

use App\Core\Model; // Importe la classe parente Model qui contient la connexion PDO

// Classe User : gère toutes les opérations sur la table USERS dans la base de données
class User extends Model
{
    // Fonction : chercher un utilisateur par son adresse email
    // Utilisé lors de la connexion (login) pour retrouver le compte
    public function findByEmail(string $email): ?array
    {
        // Sélectionne les champs nécessaires dont le mot de passe (pour vérification)
        $stmt = $this->db->prepare("SELECT ID_USER, NAME, EMAIL, PASSWORD, ROLE, CREATED_AT FROM USERS WHERE EMAIL = :email");
        $stmt->bindParam(':email', $email); // Lie l'email au paramètre :email
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC); // Récupère une seule ligne
        return $row ?: null; // Retourne le résultat ou null si non trouvé
    }

    // Fonction : chercher un utilisateur par son ID
    // Utilisé pour afficher le profil ou les infos d'un utilisateur
    public function findById(int $id): ?array
    {
        // Ne sélectionne PAS le mot de passe (pas nécessaire ici)
        $stmt = $this->db->prepare("SELECT ID_USER, NAME, EMAIL, ROLE, CREATED_AT FROM USERS WHERE ID_USER = :id");
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT); // Lie l'ID en tant qu'entier
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // Fonction : vérifier si un email est déjà utilisé dans la base
    // Utilisé lors de l'inscription pour éviter les doublons
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS CNT FROM USERS WHERE EMAIL = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$row['CNT'] > 0; // Retourne true si l'email existe déjà
    }

    // Fonction : créer un nouveau compte utilisateur
    // Reçoit le nom, l'email, et le mot de passe déjà haché (jamais en clair)
    // Retourne l'ID du nouvel utilisateur créé
    public function create(string $name, string $email, string $hashedPassword): int
    {
        // Insère dans la table USERS avec le rôle CLIENT par défaut et la date actuelle
        $stmt = $this->db->prepare(
            "INSERT INTO USERS (name, email, password, role, created_at) VALUES (:name, :email, :password, 'CLIENT', NOW())"
        );
        $stmt->bindParam(':name', $name);           // Lie le nom
        $stmt->bindParam(':email', $email);         // Lie l'email
        $stmt->bindParam(':password', $hashedPassword); // Lie le mot de passe haché
        $stmt->execute();

        $newId = (int)$this->db->lastInsertId(); // Récupère l'ID auto-incrémenté du nouvel utilisateur

        // Insère aussi dans la table CLIENT pour marquer cet utilisateur comme client
        $stmtC = $this->db->prepare("INSERT INTO CLIENT (id_user) VALUES (:id_user)");
        $stmtC->bindParam(':id_user', $newId, \PDO::PARAM_INT);
        $stmtC->execute();

        return $newId; // Retourne l'ID du nouvel utilisateur
    }

    // Fonction : mettre à jour le mot de passe d'un utilisateur
    // Reçoit toujours un mot de passe haché, jamais en clair
    public function updatePassword(int $userId, string $hashedPassword): void
    {
        $stmt = $this->db->prepare("UPDATE USERS SET PASSWORD = :pwd WHERE ID_USER = :u");
        $stmt->execute([':pwd' => $hashedPassword, ':u' => $userId]); // Passe les valeurs directement dans execute
    }

    // Fonction : changer le rôle d'un utilisateur (CLIENT <-> ADMIN)
    // Si promu ADMIN : insère dans la table ADMIN (si pas déjà présent)
    // Si rétrogradé CLIENT : supprime de la table ADMIN
    public function updateRole(int $userId, string $role): void
    {
        // Met à jour le rôle dans la table USERS
        $stmt = $this->db->prepare("UPDATE USERS SET ROLE = :r WHERE ID_USER = :id");
        $stmt->bindParam(':r', $role, \PDO::PARAM_STR);
        $stmt->bindParam(':id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        if ($role === 'ADMIN') {
            // Vérifie si l'utilisateur est déjà dans la table ADMIN
            $chk = $this->db->prepare("SELECT COUNT(*) AS C FROM ADMIN WHERE ID_USER = :id");
            $chk->bindParam(':id', $userId, \PDO::PARAM_INT);
            $chk->execute();
            $row = $chk->fetch(\PDO::FETCH_ASSOC);
            if ((int)$row['C'] === 0) {
                // Pas encore admin : l'insère dans la table ADMIN
                $ins = $this->db->prepare("INSERT INTO ADMIN (ID_USER) VALUES (:id)");
                $ins->bindParam(':id', $userId, \PDO::PARAM_INT);
                $ins->execute();
            }
        } else {
            // Rôle CLIENT : supprime de la table ADMIN s'il y était
            $del = $this->db->prepare("DELETE FROM ADMIN WHERE ID_USER = :id");
            $del->bindParam(':id', $userId, \PDO::PARAM_INT);
            $del->execute();
        }
    }

    public function delete(int $userId): void
    {
        $tables = [
            "DELETE FROM RECOMMENDATIONS WHERE ID_USER = :id",
            "DELETE FROM USER_EMOTIONS WHERE ID_USER = :id",
            "DELETE FROM USER_PROFILE WHERE ID_USER = :id",
            "DELETE FROM ACTIVITY_LOG WHERE ID_USER = :id",
            "DELETE FROM CLIENT WHERE ID_USER = :id",
            "DELETE FROM ADMIN WHERE ID_USER = :id",
            "DELETE FROM USERS WHERE ID_USER = :id",
        ];
        foreach ($tables as $sql) {
            $st = $this->db->prepare($sql);
            $st->bindParam(':id', $userId, \PDO::PARAM_INT);
            $st->execute();
        }
    }

    public function search(string $query = ''): array
    {
        if ($query !== '') {
            $stmt = $this->db->prepare(
                "SELECT ID_USER, NAME, EMAIL, ROLE, CREATED_AT FROM USERS
                 WHERE LOWER(NAME) LIKE :q OR LOWER(EMAIL) LIKE :q ORDER BY CREATED_AT DESC"
            );
            $like = '%' . strtolower($query) . '%';
            $stmt->bindParam(':q', $like);
        } else {
            $stmt = $this->db->prepare("SELECT ID_USER, NAME, EMAIL, ROLE, CREATED_AT FROM USERS ORDER BY CREATED_AT DESC");
        }
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRecentUsers(int $limit = 10): array
    {
        $stmt = $this->db->prepare("SELECT ID_USER, NAME, EMAIL, ROLE, CREATED_AT FROM USERS ORDER BY CREATED_AT DESC");
        $stmt->execute();
        $users = [];
        $count = 0;
        while ($u = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if ($count >= $limit) break;
            $users[] = $u;
            $count++;
        }
        return $users;
    }

    public function countAll(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) AS C FROM USERS");
        $stmt->execute();
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$r['C'];
    }
}
