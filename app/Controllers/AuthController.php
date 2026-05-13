<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller;          // Importe la classe parente Controller
use App\Models\User;              // Pour vérifier/créer des utilisateurs
use App\Models\ActivityLog;       // Pour enregistrer les actions (login, logout...)
use App\Models\PasswordResetToken; // Pour gérer les tokens de réinitialisation de mot de passe

// Classe AuthController : gère toute l'authentification du site
// Connexion, inscription, déconnexion, mot de passe oublié et réinitialisation
class AuthController extends Controller
{
    // Propriété : modèle User pour les opérations sur les utilisateurs
    private User $userModel;
    // Propriété : modèle ActivityLog pour enregistrer les actions
    private ActivityLog $activityLog;

    // Constructeur : initialise la connexion DB et les deux modèles
    public function __construct()
    {
        parent::__construct();                          // Appelle le constructeur parent (connexion DB)
        $this->userModel = new User($this->db);         // Instancie le modèle User
        $this->activityLog = new ActivityLog($this->db); // Instancie le modèle ActivityLog
    }

    // Fonction : afficher la page de connexion (formulaire GET)
    // Si déjà connecté, redirige vers le bon tableau de bord
    public function loginForm(): void
    {
        if ($this->isLoggedIn()) {
            // Redirige selon le rôle : ADMIN -> /admin/dashboard, CLIENT -> /dashboard
            $this->redirect($_SESSION['role'] === 'ADMIN' ? '/admin/dashboard' : '/dashboard');
        }
        $this->view('auth/login', ['error' => '']); // Affiche le formulaire de connexion sans erreur
    }

    // Fonction : traiter le formulaire de connexion (POST)
    // Vérifie l'email et le mot de passe, puis crée la session
    public function login(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect($_SESSION['role'] === 'ADMIN' ? '/admin/dashboard' : '/dashboard');
        }

        $email = trim($_POST['email'] ?? '');    // Récupère et nettoie l'email du formulaire
        $password = $_POST['password'] ?? '';    // Récupère le mot de passe

        // Vérifie que les champs ne sont pas vides
        if (empty($email) || empty($password)) {
            $this->view('auth/login', ['error' => 'Veuillez remplir tous les champs.']);
            return;
        }

        $user = $this->userModel->findByEmail($email); // Cherche l'utilisateur par email

        if (!$user) {
            // Aucun compte trouvé avec cet email
            $this->view('auth/login', ['error' => 'Aucun compte trouve avec cet email.']);
            return;
        }

        if (!password_verify($password, $user['PASSWORD'])) {
            // Le mot de passe saisi ne correspond pas au hash en base
            $this->view('auth/login', ['error' => 'Mot de passe incorrect.']);
            return;
        }

        // Connexion réussie : stocke les infos dans la session PHP
        $_SESSION['user_id'] = $user['ID_USER'];                   // ID de l'utilisateur
        $_SESSION['user_name'] = $user['NAME'];                    // Nom de l'utilisateur
        $_SESSION['role'] = strtoupper(trim($user['ROLE']));       // Rôle en majuscules

        $this->activityLog->log((int)$user['ID_USER'], 'USER_LOGIN'); // Enregistre l'action de connexion

        // Redirige vers le bon tableau de bord selon le rôle
        if ($_SESSION['role'] === 'ADMIN') {
            $this->redirect('/admin/dashboard');
        } else {
            $this->redirect('/dashboard');
        }
    }

    // Fonction : afficher le formulaire d'inscription (GET)
    public function registerForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard'); // Déjà connecté : pas besoin de s'inscrire
        }
        $this->view('auth/register', ['error' => '', 'success' => '']); // Affiche le formulaire vide
    }

    // Fonction : traiter le formulaire d'inscription (POST)
    // Valide les données, crée le compte et envoie un email de bienvenue
    public function register(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $name = trim($_POST['name'] ?? '');       // Récupère et nettoie le nom
        $email = trim($_POST['email'] ?? '');     // Récupère et nettoie l'email
        $password = $_POST['password'] ?? '';     // Récupère le mot de passe
        $confirm = $_POST['confirm'] ?? '';       // Récupère la confirmation du mot de passe

        // Vérifie que les champs obligatoires sont remplis
        if (empty($name) || empty($email) || empty($password)) {
            $this->view('auth/register', ['error' => 'Veuillez remplir tous les champs obligatoires.', 'success' => '']);
            return;
        }

        // Vérifie que l'email a un format valide (ex: user@example.com)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('auth/register', ['error' => 'Adresse email invalide.', 'success' => '']);
            return;
        }

        // Vérifie que le mot de passe fait au moins 6 caractères
        if (strlen($password) < 6) {
            $this->view('auth/register', ['error' => 'Le mot de passe doit contenir au moins 6 caracteres.', 'success' => '']);
            return;
        }

        // Vérifie que les deux mots de passe correspondent
        if ($password !== $confirm) {
            $this->view('auth/register', ['error' => 'Les mots de passe ne correspondent pas.', 'success' => '']);
            return;
        }

        // Vérifie que l'email n'est pas déjà utilisé
        if ($this->userModel->emailExists($email)) {
            $this->view('auth/register', ['error' => 'Cet email est deja utilise.', 'success' => '']);
            return;
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT); // Hache le mot de passe (jamais en clair)
        $newId = $this->userModel->create($name, $email, $hashed); // Crée le compte en base

        $this->activityLog->log($newId, 'USER_REGISTER'); // Enregistre l'inscription dans le journal

        // Prépare et envoie un email de bienvenue à l'utilisateur
        $subject = "Bienvenue sur EmoEat, " . $name . " !";
        $body = "Bonjour " . $name . ",\r\n\r\n";
        $body .= "Votre compte EmoEat a été créé avec succès !\r\n\r\n";
        $body .= "Voici vos informations :\r\n";
        $body .= "- Nom : " . $name . "\r\n";
        $body .= "- Email : " . $email . "\r\n";
        $body .= "- Rôle : Client\r\n\r\n";
        $body .= "Vous pouvez vous connecter dès maintenant sur EmoEat.\r\n\r\n";
        $body .= "-- L'équipe EmoEat";
        $headers = "From: no-reply@emoeat.health\r\nReply-To: no-reply@emoeat.health\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        @mail($email, $subject, $body, $headers); // @ supprime les erreurs si l'email échoue

        // Affiche un message de succès sur la page d'inscription
        $this->view('auth/register', [
            'error' => '',
            'success' => 'Compte cree avec succes ! Un email de confirmation a été envoyé. Vous pouvez maintenant vous connecter.'
        ]);
    }

    // Fonction : déconnecter l'utilisateur
    // Enregistre la déconnexion dans le journal, détruit la session, redirige vers /login
    public function logout(): void
    {
        if ($this->isLoggedIn()) {
            $this->activityLog->log($this->getUserId(), 'USER_LOGOUT'); // Enregistre la déconnexion
        }
        session_destroy();         // Détruit complètement la session PHP
        $this->redirect('/login'); // Redirige vers la page de connexion
    }

    // Fonction : afficher le formulaire "mot de passe oublié" (GET)
    public function forgotPasswordForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/forgot_password', ['error' => '', 'success' => '']);
    }

    // Fonction : traiter le formulaire "mot de passe oublié" (POST)
    // Génère un token sécurisé et envoie un lien de réinitialisation par email
    public function forgotPassword(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $email = trim($_POST['email'] ?? ''); // Récupère l'email soumis

        // Vérifie que l'email est valide
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->view('auth/forgot_password', ['error' => 'Veuillez entrer une adresse email valide.', 'success' => '']);
            return;
        }

        $user = $this->userModel->findByEmail($email); // Cherche le compte avec cet email
        if (!$user) {
            $this->view('auth/forgot_password', ['error' => 'Aucun compte trouvé avec cette adresse email.', 'success' => '']);
            return;
        }

        $token = bin2hex(random_bytes(32)); // Génère un token aléatoire sécurisé (64 caractères hex)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expiration dans 1 heure

        $tokenModel = new PasswordResetToken($this->db);
        $tokenModel->create((int)$user['ID_USER'], $token, $expiresAt); // Sauvegarde le token en base

        // Construit le lien de réinitialisation
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = $protocol . '://' . $host . '/reset-password?token=' . $token;

        // Prépare et envoie l'email avec le lien
        $subject = "EmoEat - Réinitialisation de votre mot de passe";
        $message = "Bonjour " . htmlspecialchars($user['NAME']) . ",\r\n\r\n";
        $message .= "Cliquez sur le lien suivant pour définir un nouveau mot de passe :\r\n";
        $message .= $resetLink . "\r\n\r\n";
        $message .= "Ce lien expire dans 1 heure.\r\n\r\n";
        $message .= "-- L'équipe EmoEat";
        $headers = "From: no-reply@emoeat.health\r\nReply-To: no-reply@emoeat.health\r\nContent-Type: text/plain; charset=UTF-8\r\n";

        if (@mail($email, $subject, $message, $headers)) {
            $this->activityLog->log((int)$user['ID_USER'], 'PASSWORD_RESET_REQUESTED'); // Enregistre l'action
            $this->view('auth/forgot_password', ['error' => '', 'success' => 'Un email de réinitialisation a été envoyé à votre adresse.']);
        } else {
            $this->view('auth/forgot_password', ['error' => 'Erreur lors de l\'envoi de l\'email.', 'success' => '']);
        }
    }

    // Fonction : afficher le formulaire de réinitialisation de mot de passe (GET)
    // Vérifie que le token dans l'URL est valide avant d'afficher le formulaire
    public function resetPasswordForm(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $token = trim($_GET['token'] ?? '');             // Récupère le token depuis l'URL (?token=...)
        $tokenModel = new PasswordResetToken($this->db);
        $tokenRow = null;
        $error = '';

        if (empty($token)) {
            $error = 'Aucun token de réinitialisation fourni.';
        } else {
            $tokenRow = $tokenModel->validate($token); // Vérifie si le token est valide et non expiré
            if (!$tokenRow) {
                $error = 'Ce lien est invalide ou a expiré. Veuillez refaire une demande.';
            }
        }

        // Affiche le formulaire avec les infos de validation du token
        $this->view('auth/reset_password', [
            'error' => $error,
            'success' => '',
            'validToken' => $tokenRow !== null, // true = token valide, affiche le formulaire
            'tokenRow' => $tokenRow,            // Données du token (ID_USER, EMAIL...)
            'token' => $token,                  // Le token brut pour le champ caché du formulaire
        ]);
    }

    // Fonction : traiter le formulaire de réinitialisation de mot de passe (POST)
    // Valide le token et le nouveau mot de passe, puis met à jour en base
    public function resetPassword(): void
    {
        if ($this->isLoggedIn()) {
            $this->redirect('/dashboard');
        }

        $token = trim($_POST['token'] ?? '');             // Récupère le token depuis le champ caché du formulaire
        $tokenModel = new PasswordResetToken($this->db);
        $tokenRow = $tokenModel->validate($token);        // Vérifie si le token est encore valide

        if (!$tokenRow) {
            // Token invalide ou expiré : affiche une erreur
            $this->view('auth/reset_password', [
                'error' => 'Ce lien est invalide ou a expiré.',
                'success' => '',
                'validToken' => false,
                'tokenRow' => null,
                'token' => $token,
            ]);
            return;
        }

        $password = $_POST['new_password'] ?? '';         // Nouveau mot de passe saisi
        $confirm = $_POST['confirm_password'] ?? '';      // Confirmation du mot de passe

        // Vérifie que le champ mot de passe n'est pas vide
        if (empty($password)) {
            $this->view('auth/reset_password', ['error' => 'Veuillez entrer un nouveau mot de passe.', 'success' => '', 'validToken' => true, 'tokenRow' => $tokenRow, 'token' => $token]);
            return;
        }

        // Vérifie que le mot de passe fait au moins 6 caractères
        if (strlen($password) < 6) {
            $this->view('auth/reset_password', ['error' => 'Le mot de passe doit contenir au moins 6 caractères.', 'success' => '', 'validToken' => true, 'tokenRow' => $tokenRow, 'token' => $token]);
            return;
        }

        // Vérifie que les deux mots de passe correspondent
        if ($password !== $confirm) {
            $this->view('auth/reset_password', ['error' => 'Les mots de passe ne correspondent pas.', 'success' => '', 'validToken' => true, 'tokenRow' => $tokenRow, 'token' => $token]);
            return;
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);            // Hache le nouveau mot de passe
        $this->userModel->updatePassword((int)$tokenRow['ID_USER'], $hashed); // Met à jour en base
        $tokenModel->markUsed((int)$tokenRow['ID_TOKEN']);              // Invalide le token utilisé
        $this->activityLog->log((int)$tokenRow['ID_USER'], 'PASSWORD_RESET'); // Enregistre l'action

        // Affiche un message de succès, sans token valide (formulaire masqué)
        $this->view('auth/reset_password', [
            'error' => '',
            'success' => 'Mot de passe réinitialisé avec succès ! Vous pouvez maintenant vous connecter.',
            'validToken' => false,
            'tokenRow' => null,
            'token' => '',
        ]);
    }
}
