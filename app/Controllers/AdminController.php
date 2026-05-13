<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller;       // Importe la classe parente Controller
use App\Models\User;           // Pour gérer les utilisateurs (liste, supprimer, changer rôle)
use App\Models\Food;           // Pour gérer les aliments (ajouter, supprimer, rechercher)
use App\Models\Emotion;        // Pour gérer les émotions et les règles émotion→aliment
use App\Models\Recommendation; // Pour compter le total des recommandations
use App\Models\ActivityLog;    // Pour journaliser et afficher les actions des utilisateurs

// Classe AdminController : interface d'administration réservée aux ADMIN
// Gère le tableau de bord, les utilisateurs, les aliments, les émotions et le journal d'activité
class AdminController extends Controller
{
    // Propriété : modèle pour enregistrer les actions admin dans le journal
    private ActivityLog $activityLog;

    // Constructeur : initialise la connexion DB et le modèle ActivityLog
    public function __construct()
    {
        parent::__construct();                            // Appelle le constructeur parent
        $this->activityLog = new ActivityLog($this->db); // Instancie le modèle ActivityLog
    }

    // Fonction : afficher le tableau de bord admin avec les statistiques globales
    public function dashboard(): void
    {
        $this->requireAdmin(); // Redirige vers /dashboard si pas ADMIN

        // Instancie tous les modèles nécessaires pour les statistiques
        $userModel = new User($this->db);
        $foodModel = new Food($this->db);
        $emotionModel = new Emotion($this->db);
        $recModel = new Recommendation($this->db);

        $totalUsers = $userModel->countAll();      // Nombre total d'utilisateurs
        $totalFoods = $foodModel->countAll();      // Nombre total d'aliments
        $totalEmo = $emotionModel->countAll();     // Nombre total d'émotions
        $totalRec = $recModel->countAll();         // Nombre total de recommandations
        $users = $userModel->getRecentUsers(10);   // 10 derniers utilisateurs inscrits

        // Affiche la vue avec toutes les statistiques
        $this->view('dashboard/admin', [
            'admin_name' => $this->getUserName(), // Nom de l'admin pour l'affichage
            'totalUsers' => $totalUsers,           // Stats pour les cartes du tableau de bord
            'totalFoods' => $totalFoods,
            'totalEmo' => $totalEmo,
            'totalRec' => $totalRec,
            'users' => $users,                     // Tableau des 10 derniers utilisateurs
        ]);
    }

    // Fonction : gérer la page des utilisateurs (liste + actions) (GET et POST)
    // Permet de supprimer un utilisateur ou de changer son rôle (CLIENT/ADMIN)
    public function users(): void
    {
        $this->requireAdmin(); // Accès réservé aux ADMIN

        $userModel = new User($this->db);
        $adminId = $this->getUserId(); // ID de l'admin connecté (pour éviter de s'auto-supprimer)
        $msg = '';
        $msg_type = 'success';

        if (isset($_POST['delete_user'])) {
            // Action : supprimer un utilisateur
            $delId = (int)$_POST['del_id']; // ID de l'utilisateur à supprimer
            if ($delId === $adminId) {
                // Sécurité : un admin ne peut pas supprimer son propre compte
                $msg = "Vous ne pouvez pas supprimer votre propre compte.";
                $msg_type = 'danger';
            } else {
                $userModel->delete($delId);                                        // Supprime en cascade
                $this->activityLog->log($adminId, 'ADMIN_DELETE_USER_' . $delId); // Journalise l'action
                $msg = "Utilisateur #$delId supprimé avec succès.";
            }
        }

        if (isset($_POST['change_role'])) {
            // Action : changer le rôle d'un utilisateur
            $chId = (int)$_POST['ch_id'];                                         // ID à modifier
            $chRole = ($_POST['ch_role'] === 'ADMIN') ? 'ADMIN' : 'CLIENT';       // Valide le rôle
            if ($chId === $adminId) {
                // Sécurité : un admin ne peut pas modifier son propre rôle
                $msg = "Vous ne pouvez pas modifier votre propre rôle.";
                $msg_type = 'danger';
            } else {
                $userModel->updateRole($chId, $chRole);                                              // Met à jour le rôle
                $this->activityLog->log($adminId, 'ADMIN_CHANGE_ROLE_' . $chId . '_TO_' . $chRole); // Journalise
                $msg = "Rôle de l'utilisateur #$chId mis à jour : $chRole.";
            }
        }

        $search = trim($_GET['q'] ?? '');      // Terme de recherche depuis l'URL (?q=...)
        $users = $userModel->search($search);  // Recherche les utilisateurs (vide = tous)

        // Affiche la vue avec la liste des utilisateurs et les messages
        $this->view('admin/users', [
            'users' => $users,          // Liste des utilisateurs
            'search' => $search,        // Terme de recherche (pour ré-afficher dans le champ)
            'msg' => $msg,              // Message de succès ou d'erreur
            'msg_type' => $msg_type,    // Type du message ('success' ou 'danger')
            'admin_id' => $adminId,     // ID de l'admin (pour désactiver les boutons sur son compte)
        ]);
    }

    // Alias POST pour users() : les formulaires POST sur /admin/users appellent cette méthode
    public function usersPost(): void
    {
        $this->users(); // Délègue entièrement à users() qui gère GET et POST
    }

    // Fonction : gérer la page des aliments (liste + ajout + suppression)
    public function foods(): void
    {
        $this->requireAdmin(); // Accès réservé aux ADMIN

        $foodModel = new Food($this->db);
        $adminId = $this->getUserId(); // ID de l'admin pour le journal
        $msg = '';
        $msg_type = 'success';

        if (isset($_POST['add_food'])) {
            // Action : ajouter un nouvel aliment
            $fname = trim($_POST['food_name'] ?? '');     // Nom de l'aliment
            $cat = trim($_POST['food_category'] ?? '');   // Catégorie (Fruit, Protein...)
            $calories = (int)($_POST['food_cal'] ?? 0);  // Calories
            $protein = (int)($_POST['food_prot'] ?? 0);  // Protéines en grammes
            $carbs = (int)($_POST['food_carb'] ?? 0);    // Glucides en grammes
            $fat = (int)($_POST['food_fat'] ?? 0);       // Lipides en grammes
            $desc = trim($_POST['food_desc'] ?? '');      // Description/bénéfices

            if (empty($fname) || empty($cat)) {
                // Validation : nom et catégorie obligatoires
                $msg = "Le nom et la catégorie sont obligatoires.";
                $msg_type = 'danger';
            } else {
                $foodModel->create($fname, $cat, $calories, $protein, $carbs, $fat, $desc); // Crée l'aliment
                $this->activityLog->log($adminId, 'ADMIN_ADD_FOOD_' . $fname);              // Journalise
                $msg = "Aliment \"$fname\" ajouté avec succès.";
            }
        }

        if (isset($_POST['delete_food'])) {
            // Action : supprimer un aliment (cascade sur les règles et recommandations)
            $delId = (int)$_POST['del_id'];
            $foodModel->delete($delId);                                         // Supprime en cascade
            $this->activityLog->log($adminId, 'ADMIN_DELETE_FOOD_' . $delId); // Journalise
            $msg = "Aliment #$delId supprimé.";
        }

        $search = trim($_GET['q'] ?? '');       // Terme de recherche
        $foods = $foodModel->search($search);   // Recherche les aliments
        // Liste des catégories disponibles pour le formulaire d'ajout
        $categories = ['Fruit', 'Vegetable', 'Grain', 'Protein', 'Dairy', 'Dessert', 'Beverage', 'Legume', 'Nut', 'Other'];

        // Affiche la vue avec la liste des aliments et le formulaire d'ajout
        $this->view('admin/foods', [
            'foods' => $foods,             // Liste des aliments
            'search' => $search,           // Terme de recherche
            'msg' => $msg,                 // Message de succès ou d'erreur
            'msg_type' => $msg_type,       // Type du message
            'categories' => $categories,   // Catégories pour le <select>
        ]);
    }

    // Alias POST pour foods() : les formulaires POST sur /admin/foods appellent cette méthode
    public function foodsPost(): void
    {
        $this->foods(); // Délègue entièrement à foods()
    }

    // Fonction : gérer la page des émotions (liste + ajout + suppression + règles)
    // Permet de créer/supprimer des émotions et de définir les règles émotion→aliment
    public function emotions(): void
    {
        $this->requireAdmin(); // Accès réservé aux ADMIN

        $emotionModel = new Emotion($this->db);
        $foodModel = new Food($this->db);
        $adminId = $this->getUserId(); // ID de l'admin pour le journal
        $msg = '';
        $msg_type = 'success';

        if (isset($_POST['add_emotion'])) {
            // Action : ajouter une nouvelle émotion
            $ename = trim($_POST['emo_name'] ?? ''); // Nom de l'émotion (ex: "happy")
            if (empty($ename)) {
                $msg = "Le nom de l'émotion est obligatoire.";
                $msg_type = 'danger';
            } else {
                $emotionModel->create($ename, '');                              // Crée l'émotion
                $this->activityLog->log($adminId, 'ADMIN_ADD_EMOTION_' . $ename); // Journalise
                $msg = "Émotion \"$ename\" ajoutée avec succès.";
            }
        }

        if (isset($_POST['delete_emotion'])) {
            // Action : supprimer une émotion (cascade sur USER_EMOTIONS, RECOMMENDATIONS, EMOTION_FOOD)
            $delId = (int)$_POST['del_emo'];
            $emotionModel->delete($delId);                                          // Supprime en cascade
            $this->activityLog->log($adminId, 'ADMIN_DELETE_EMOTION_' . $delId);   // Journalise
            $msg = "Émotion #$delId supprimée.";
        }

        if (isset($_POST['add_rule'])) {
            // Action : ajouter une règle émotion → aliment avec un intensité par défaut de 5
            $rEmo = (int)($_POST['rule_emo'] ?? 0);   // ID de l'émotion
            $rFood = (int)($_POST['rule_food'] ?? 0); // ID de l'aliment
            $rInt = 5;                                 // Intensité par défaut (échelle 1-10)
            if ($rEmo > 0 && $rFood > 0) {
                $emotionModel->addRule($rEmo, $rFood, $rInt);                                   // Crée la règle
                $this->activityLog->log($adminId, 'ADMIN_ADD_RULE_E' . $rEmo . '_F' . $rFood); // Journalise
                $msg = "Règle ajoutée (émotion #$rEmo → aliment #$rFood).";
            } else {
                $msg = "Veuillez sélectionner une émotion et un aliment.";
                $msg_type = 'danger';
            }
        }

        $emotions = $emotionModel->getAll();  // Toutes les émotions
        $foods = $foodModel->getAll();        // Tous les aliments (pour le <select> des règles)
        $rules = $emotionModel->getRules();   // Toutes les règles émotion→aliment

        // Affiche la vue avec les émotions, aliments, règles et messages
        $this->view('admin/emotions', [
            'emotions' => $emotions, // Liste des émotions
            'foods' => $foods,       // Liste des aliments pour le formulaire de règle
            'rules' => $rules,       // Toutes les règles existantes
            'msg' => $msg,           // Message de succès ou d'erreur
            'msg_type' => $msg_type, // Type du message
        ]);
    }

    // Alias POST pour emotions() : les formulaires POST sur /admin/emotions appellent cette méthode
    public function emotionsPost(): void
    {
        $this->emotions(); // Délègue entièrement à emotions()
    }

    // Fonction : afficher le journal d'activité (toutes les actions des utilisateurs)
    // Permet de rechercher dans le journal par mot-clé
    public function activityLog(): void
    {
        $this->requireAdmin(); // Accès réservé aux ADMIN

        $logModel = new ActivityLog($this->db);
        $search = trim($_GET['q'] ?? '');      // Terme de recherche (filtre sur ACTION ou USERNAME)
        $logs = $logModel->search($search);    // Récupère les entrées du journal (filtrées ou toutes)

        // Affiche la vue du journal d'activité
        $this->view('admin/activity_log', [
            'logs' => $logs,       // Tableau des entrées du journal
            'search' => $search,   // Terme de recherche pour ré-affichage
        ]);
    }
}
