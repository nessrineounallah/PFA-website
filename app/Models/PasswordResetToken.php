<?php
namespace App\Models; 

use App\Core\Model; // Importe la classe parente Model

// Classe PasswordResetToken : gère les tokens de réinitialisation de mot de passe
// Quand un utilisateur oublie son mot de passe, un token temporaire est généré et envoyé par email
class PasswordResetToken extends Model
{
    // Fonction : créer un nouveau token de réinitialisation pour un utilisateur
    // Invalide d'abord les anciens tokens non utilisés, puis en crée un nouveau
    public function create(int $userId, string $token, string $expiresAt): void
    {
        // Invalide tous les anciens tokens actifs de cet utilisateur (USED = 1 = utilisé/invalidé)
        $stDel = $this->db->prepare("UPDATE PASSWORD_RESET_TOKENS SET USED = 1 WHERE ID_USER = :u AND USED = 0");
        $stDel->execute([':u' => $userId]);

        // Crée un nouveau token avec sa date d'expiration (1 heure)
        $stIns = $this->db->prepare("INSERT INTO PASSWORD_RESET_TOKENS (ID_USER, TOKEN, EXPIRES_AT) VALUES (:u, :t, :e)");
        $stIns->execute([':u' => $userId, ':t' => $token, ':e' => $expiresAt]);
    }


    // Un token est valide si : il n'est pas utilisé (USED=0) ET il n'est pas expiré (EXPIRES_AT > maintenant)
    public function validate(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.ID_TOKEN, t.ID_USER, u.EMAIL, u.NAME
             FROM PASSWORD_RESET_TOKENS t
             JOIN USERS u ON u.ID_USER = t.ID_USER -- Jointure pour récupérer les infos de l'utilisateur
             WHERE t.TOKEN = :token AND t.USED = 0 AND t.EXPIRES_AT > NOW()" // Vérifie validité et expiration
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null; // Retourne les données du token ou null si invalide/expiré
    }

    // Fonction : marquer un token comme utilisé après la réinitialisation du mot de passe
    // Empêche la réutilisation du même lien de réinitialisation
    public function markUsed(int $tokenId): void
    {
        $stmt = $this->db->prepare("UPDATE PASSWORD_RESET_TOKENS SET USED = 1 WHERE ID_TOKEN = :id");
        $stmt->execute([':id' => $tokenId]); // Passe l'ID du token à invalider
    }
}
