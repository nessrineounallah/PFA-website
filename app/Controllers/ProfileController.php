<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller;    // Importe la classe parente Controller
use App\Models\UserProfile; // Pour gérer le profil nutritionnel (poids, taille, allergies, objectif)
use App\Models\User;        // Pour récupérer les infos de l'utilisateur (nom, email)
use App\Models\ActivityLog; // Pour enregistrer la mise à jour du profil dans le journal

// Classe ProfileController : gère la page de profil nutritionnel de l'utilisateur
// Affiche et sauvegarde : poids, taille, allergies, objectif, et calcule l'IMC
class ProfileController extends Controller
{
    // Fonction : afficher la page de profil (GET)
    // Charge le profil existant, les infos utilisateur et calcule l'IMC
    public function index(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId();                      // ID de l'utilisateur connecté
        $profileModel = new UserProfile($this->db);        // Instancie le modèle profil
        $userModel = new User($this->db);                  // Instancie le modèle utilisateur

        $profile = $profileModel->findByUser($userId);     // Récupère le profil nutritionnel
        $userInfo = $userModel->findById($userId);         // Récupère les infos du compte (nom, email)

        // Récupère le message flash stocké en session (après une redirection)
        $message = '';
        $msg_type = 'success';
        if (isset($_SESSION['message'])) {
            $message = $_SESSION['message'];               // Message à afficher (succès ou erreur)
            $msg_type = $_SESSION['msg_type'] ?? 'success'; // Type : 'success' ou 'danger'
            unset($_SESSION['message'], $_SESSION['msg_type']); // Supprime de la session après lecture
        }

        // Calcul de l'IMC (Indice de Masse Corporelle) = poids / (taille en mètres)²
        $bmi = null;
        $bmi_label = '';
        $bmi_class = '';
        $w = (float)($profile['WEIGHT'] ?? 0); // Poids en kg
        $h = (float)($profile['HEIGHT'] ?? 0); // Taille en cm
        if ($w > 0 && $h > 0) {
            $hm = $h / 100;                           // Convertit la taille en mètres
            $bmi = round($w / ($hm * $hm), 1);        // Calcule l'IMC arrondi à 1 décimale
            // Détermine la catégorie IMC et la classe CSS correspondante
            if ($bmi < 18.5) { $bmi_label = 'Insuffisance pondérale'; $bmi_class = 'bmi-under'; }
            elseif ($bmi < 25) { $bmi_label = 'Poids normal ✓'; $bmi_class = 'bmi-normal'; }
            elseif ($bmi < 30) { $bmi_label = 'Surpoids'; $bmi_class = 'bmi-over'; }
            else { $bmi_label = 'Obésité'; $bmi_class = 'bmi-obese'; }
        }

        // Envoie toutes les données à la vue pour les afficher
        $this->view('profile/index', [
            'profile' => $profile,       // Données du profil nutritionnel
            'userInfo' => $userInfo,     // Nom et email de l'utilisateur
            'message' => $message,       // Message flash (succès/erreur)
            'msg_type' => $msg_type,     // Type du message pour la couleur CSS
            'bmi' => $bmi,               // Valeur de l'IMC calculé
            'bmi_label' => $bmi_label,   // Libellé de la catégorie IMC
            'bmi_class' => $bmi_class,   // Classe CSS pour la couleur de l'IMC
            'name' => $this->getUserName(), // Nom pour la navbar
            'role' => $this->getUserRole(), // Rôle pour la navbar
        ]);
    }

    // Fonction : sauvegarder le profil nutritionnel (POST)
    // Valide les données du formulaire et les enregistre en base de données
    public function save(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId();                        // ID de l'utilisateur connecté
        $weight = (float)($_POST['weight'] ?? 0);            // Poids récupéré du formulaire (en kg)
        $height = (float)($_POST['height'] ?? 0);            // Taille récupérée du formulaire (en cm)
        $allergies = trim($_POST['allergies'] ?? '');        // Liste des allergies (texte libre)
        $goal = trim($_POST['goal'] ?? '');                  // Objectif nutritionnel

        if ($weight <= 0 || $height <= 0) {
            // Données invalides : stocke un message d'erreur en session
            $_SESSION['message'] = "Veuillez entrer un poids et une taille valides.";
            $_SESSION['msg_type'] = "danger"; // Couleur rouge pour l'erreur
        } else {
            // Données valides : sauvegarde le profil
            $profileModel = new UserProfile($this->db);
            $profileModel->save($userId, $weight, $height, $allergies, $goal); // INSERT ou UPDATE selon l'existence

            $activityLog = new ActivityLog($this->db);
            $activityLog->log($userId, 'PROFILE_UPDATED'); // Enregistre l'action dans le journal

            // Stocke un message de succès en session pour l'afficher après la redirection
            $_SESSION['message'] = "Profil nutritionnel sauvegardé avec succès !";
            $_SESSION['msg_type'] = "success"; // Couleur verte pour le succès
        }

        $this->redirect('/profile'); // Redirige vers la page de profil (affichage avec le message flash)
    }
}
