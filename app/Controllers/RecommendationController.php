<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller;        // Importe la classe parente Controller
use App\Models\Emotion;         // Pour récupérer les émotions et les aliments associés
use App\Models\Recommendation;  // Pour sauvegarder les recommandations choisies
use App\Models\UserEmotion;     // Pour enregistrer l'émotion ressentie par l'utilisateur
use App\Models\UserProfile;     // Pour lire les allergies et l'objectif nutritionnel
use App\Models\ActivityLog;     // Pour journaliser la sauvegarde d'une recommandation

// Classe RecommendationController : gère la page de recommandation alimentaire
// Selon l'émotion choisie, propose des aliments adaptés, filtre par allergies/calories,
// et permet à l'utilisateur de sauvegarder sa sélection dans l'historique.
class RecommendationController extends Controller
{
    // Fonction : afficher la page de recommandation (GET)
    // Prépare le token CSRF, charge le profil et la liste d'émotions dédupliquées
    public function index(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId(); // ID de l'utilisateur connecté

        // Génère un token CSRF s'il n'en existe pas déjà un en session
        // Ce token protège contre les attaques CSRF (falsification de requête)
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 caractères aléatoires
        }
        $csrf_token = $_SESSION['csrf_token']; // Token à intégrer dans le formulaire caché

        $profileModel = new UserProfile($this->db); // Instancie le modèle profil
        $emotionModel = new Emotion($this->db);     // Instancie le modèle émotion

        $profile = $profileModel->findByUser($userId);  // Charge le profil nutritionnel
        $rawEmotions = $emotionModel->getGrouped();     // Récupère toutes les émotions

        // Déduplique les émotions par leur label français
        // (ex: 'stress' et 'stressed' ont le même label 'Stressé', on n'en garde qu'un)
        $emotions = [];
        $seenLabels = [];
        foreach ($rawEmotions as $em) {
            $label = self::emoLabel($em['EMOTION_NAME']); // Traduit en français
            if (!isset($seenLabels[$label])) {            // Si ce label n'a pas encore été vu
                $seenLabels[$label] = true;               // Marque ce label comme vu
                $emotions[] = $em;                        // Ajoute l'émotion à la liste finale
            }
        }

        // Affiche la vue avec le formulaire de sélection d'émotion (sans résultats au départ)
        $this->view('recommendation/index', [
            'csrf_token' => $csrf_token,       // Token CSRF pour sécuriser le formulaire
            'profile' => $profile,             // Profil nutritionnel (allergies, objectif)
            'emotions' => $emotions,           // Liste des émotions disponibles
            'results' => [],                   // Pas encore de résultats (formulaire non soumis)
            'selected_emotion_id' => null,     // Aucune émotion sélectionnée
            'selected_emotion_nm' => '',       // Nom de l'émotion (vide)
            'filter_info' => '',               // Infos sur les filtres appliqués (vide)
            'db_error' => '',                  // Message d'erreur base de données (vide)
            'save_success' => false,           // Pas encore de sauvegarde effectuée
        ]);
    }

    // Fonction : traiter le formulaire de recommandation (POST)
    // Deux cas : 'get_reco' (demande de recommandation) ou 'save_selection' (sauvegarde)
    public function getRecommendation(): void
    {
        $this->requireAuth(); // Redirige vers /login si pas connecté

        $userId = $this->getUserId(); // ID de l'utilisateur connecté

        // Validation CSRF : compare le token soumis avec celui stocké en session
        // hash_equals() évite les attaques par timing (comparaison à temps constant)
        if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            die("Requête invalide."); // Stop l'exécution si le token est invalide ou manquant
        }

        $profileModel = new UserProfile($this->db); // Instancie le modèle profil
        $emotionModel = new Emotion($this->db);     // Instancie le modèle émotion

        $profile = $profileModel->findByUser($userId); // Charge le profil nutritionnel
        $rawEmotions = $emotionModel->getGrouped();    // Récupère toutes les émotions

        // Déduplique les émotions (même logique que dans index())
        $emotions = [];
        $seenLabels = [];
        foreach ($rawEmotions as $em) {
            $label = self::emoLabel($em['EMOTION_NAME']);
            if (!isset($seenLabels[$label])) {
                $seenLabels[$label] = true;
                $emotions[] = $em;
            }
        }

        $csrf_token = $_SESSION['csrf_token']; // Token CSRF pour ré-affichage dans la vue
        $results = [];                         // Tableau des aliments recommandés
        $selected_emotion_id = null;           // ID de l'émotion choisie
        $selected_emotion_nm = '';             // Nom de l'émotion choisie
        $filter_info = '';                     // Message sur les filtres appliqués
        $db_error = '';                        // Message d'erreur base de données
        $save_success = false;                 // Indicateur de sauvegarde réussie

        if (isset($_POST['save_selection'])) {
            // CAS 1 : l'utilisateur a cliqué "Sauvegarder ma sélection"
            $sEmoId = (int)($_POST['emotion_id'] ?? 0);           // ID de l'émotion à sauvegarder
            $sFoodIds = array_values(array_filter(array_map('intval', $_POST['selected_foods'] ?? []))); // IDs des aliments cochés

            if ($sEmoId > 0 && !empty($sFoodIds)) {
                // Clé unique en session pour éviter de sauvegarder deux fois la même sélection
                $saveKey = 'reco_saved_' . $userId . '_' . $sEmoId;
                if (empty($_SESSION[$saveKey])) {
                    $_SESSION[$saveKey] = true; // Marque comme déjà sauvegardé

                    // Enregistre l'émotion ressentie dans USER_EMOTIONS
                    $userEmoModel = new UserEmotion($this->db);
                    $userEmoModel->save($userId, $sEmoId);

                    // Pour chaque aliment sélectionné, crée une recommandation
                    $recModel = new Recommendation($this->db);
                    $stFd = $this->db->prepare("SELECT description FROM FOODS WHERE id_food = :id"); // Récupère la description
                    foreach ($sFoodIds as $fid) {
                        $stFd->execute([':id' => $fid]);
                        $fdRow = $stFd->fetch(\PDO::FETCH_ASSOC);
                        // Utilise la description de l'aliment ou un texte par défaut
                        $benefit = !empty($fdRow['DESCRIPTION'] ?? $fdRow['description'] ?? '') ? ($fdRow['DESCRIPTION'] ?? $fdRow['description']) : 'Recommandé pour votre état émotionnel.';
                        $recModel->save($sEmoId, $fid, $benefit, $userId); // Sauvegarde en base
                    }

                    // Enregistre l'action dans le journal d'activité
                    $activityLog = new ActivityLog($this->db);
                    $activityLog->log($userId, 'RECOMMENDATION_SAVED');
                }
                $save_success = true;          // Indique que la sauvegarde a réussi
                $selected_emotion_id = $sEmoId; // Conserve l'émotion pour ré-affichage

                $eRow = $emotionModel->findById($sEmoId);
                $selected_emotion_nm = $eRow['EMOTION_NAME'] ?? ''; // Nom de l'émotion
                $results = $emotionModel->getFoodsForEmotion($sEmoId); // Ré-affiche les aliments
            }
        } elseif (isset($_POST['get_reco']) && !empty($_POST['emotion'])) {
            // CAS 2 : l'utilisateur a soumis le formulaire pour obtenir des recommandations
            $emotionId = (int)$_POST['emotion']; // ID de l'émotion choisie dans le formulaire

            // Cherche l'émotion dans la liste pour récupérer son nom
            foreach ($emotions as $em) {
                $emId = (int)($em['ID_EMOTION'] ?? $em['id_emotion'] ?? 0);
                if ($emId === $emotionId) {
                    $selected_emotion_id = $emotionId;
                    $selected_emotion_nm = $em['EMOTION_NAME'] ?? $em['emotion_name'] ?? '';
                    break;
                }
            }

            // Si l'émotion n'était pas dans la liste dédupliquée, la cherche directement en base
            if ($selected_emotion_id === null && $emotionId > 0) {
                $selected_emotion_id = $emotionId;
                $eRow = $emotionModel->findById($emotionId);
                $selected_emotion_nm = $eRow['EMOTION_NAME'] ?? '';
            }

            if ($selected_emotion_id !== null) {
                // Récupère tous les aliments associés à cette émotion (sans filtres)
                $allFoods = $emotionModel->getFoodsForEmotion($selected_emotion_id);

                // Prépare les filtres depuis le profil nutritionnel
                $profileRow = is_array($profile) ? $profile : [];
                $goal = strtolower(trim($profileRow['GOAL'] ?? ''));            // Objectif (ex: "perte de poids")
                $allergiesRaw = strtolower(trim($profileRow['ALLERGIES'] ?? '')); // Allergies (ex: "gluten, lactose")
                $allergyList = array_filter(array_map('trim', explode(',', $allergiesRaw))); // Tableau des allergènes
                $filters = []; // Accumule les raisons d'exclusion des aliments

                foreach ($allFoods as $row) {
                    $foodName = strtolower($row['FOOD_NAME'] ?? $row['food_name'] ?? ''); // Nom de l'aliment
                    $calories = (int)($row['CALORIES'] ?? $row['calories'] ?? 0);          // Calories

                    // Filtre 1 : exclut les aliments qui contiennent un allergène
                    $blocked = false;
                    foreach ($allergyList as $allergen) {
                        if ($allergen !== '' && strpos($foodName, $allergen) !== false) {
                            $filters[] = "allergie ($allergen)"; // Raison d'exclusion
                            $blocked = true;
                            break;
                        }
                    }
                    if ($blocked) continue; // Passe à l'aliment suivant

                    // Filtre 2 : si objectif "perte de poids", exclut les aliments > 300 calories
                    if ($goal === 'perte de poids' && $calories > 300) {
                        $filters[] = "objectif perte de poids (>300 cal)";
                        continue; // Passe à l'aliment suivant
                    }
                    $results[] = $row; // L'aliment passe tous les filtres : l'ajoute aux résultats
                }

                // Construit un message résumant les filtres qui ont exclu des aliments
                if (!empty($filters)) {
                    $filter_info = "Filtres appliqués : " . implode(', ', array_unique($filters)) . ".";
                }
            }
        }

        // Affiche la vue avec les résultats filtrés
        $this->view('recommendation/index', [
            'csrf_token' => $csrf_token,                 // Token CSRF pour ré-affichage
            'profile' => $profile,                       // Profil nutritionnel
            'emotions' => $emotions,                     // Liste des émotions
            'results' => $results,                       // Aliments recommandés (filtrés)
            'selected_emotion_id' => $selected_emotion_id, // ID de l'émotion choisie
            'selected_emotion_nm' => $selected_emotion_nm, // Nom de l'émotion choisie
            'filter_info' => $filter_info,               // Message sur les filtres appliqués
            'db_error' => $db_error,                     // Message d'erreur (vide en général)
            'save_success' => $save_success,             // true si la sélection a été sauvegardée
        ]);
    }

    // Méthode statique : retourne l'emoji correspondant au nom d'une émotion
    // Utilisée dans les vues pour afficher une icône visuelle à côté de chaque émotion
    public static function emoEmoji(string $name): string
    {
        $map = [
            'happy' => '😊', 'sad' => '😢', 'angry' => '😠',
            'stress' => '😰', 'stressed' => '😰', 'excited' => '🤩',
            'anxious' => '😟', 'calm' => '😌', 'tired' => '😴',
            'fear' => '😱', 'joy' => '😄', 'love' => '❤️',
            'frustrated' => '😤', 'bored' => '😑', 'nervous' => '😬',
            'exhausted' => '😵', 'sick' => '🤒', 'sleepy' => '😪'
        ];
        return $map[strtolower(trim($name))] ?? '😶'; // '😶' si émotion inconnue
    }

    // Méthode statique : traduit un nom d'émotion anglais en étiquette française
    // Utilisée pour dédupliquer et afficher les émotions en français
    public static function emoLabel(string $name): string
    {
        $map = [
            'happy' => 'Joyeux', 'sad' => 'Triste',
            'angry' => 'En colère', 'stress' => 'Stressé',
            'stressed' => 'Stressé', 'excited' => 'Excité',
            'anxious' => 'Anxieux', 'calm' => 'Calme',
            'tired' => 'Fatigué', 'fear' => 'Apeuré',
            'joy' => 'Joyeux', 'love' => 'Amoureux',
            'frustrated' => 'Frustré', 'bored' => 'Ennuyé',
            'nervous' => 'Nerveux', 'exhausted' => 'Épuisé',
            'sick' => 'Malade', 'sleepy' => 'Somnolent',
        ];
        return $map[strtolower(trim($name))] ?? ucfirst(strtolower(trim($name))); // Traduit ou met en majuscule
    }

    // Méthode statique : retourne l'emoji alimentaire selon le nom ou la catégorie de l'aliment
    // Utilisée dans les vues pour afficher une icône à côté de chaque aliment recommandé
    public static function foodEmoji(string $name, string $cat = ''): string
    {
        $nm = strtolower($name);
        // Correspondance par mots-clés dans le nom de l'aliment
        $keywords = [
            'banane' => '🍌', 'pomme' => '🍎', 'orange' => '🍊', 'chocolat' => '🍫',
            'salade' => '🥗', 'riz' => '🍚', 'poulet' => '🍗', 'saumon' => '🐟',
            'soupe' => '🥣', 'pain' => '🍞', 'noix' => '🥜', 'lentilles' => '🫘',
            'oeuf' => '🥚', 'pâtes' => '🍝', 'avocat' => '🥑', 'légumes' => '🥦',
            'thé' => '🍵', 'café' => '☕', 'eau' => '💧', 'jus' => '🥤',
            'smoothie' => '🥤'
        ];
        foreach ($keywords as $kw => $em) {
            if (strpos($nm, $kw) !== false) return $em; // Retourne l'emoji si le mot-clé est trouvé
        }
        // Fallback : correspondance par catégorie si aucun mot-clé trouvé dans le nom
        $cats = ['fruit' => '🍎', 'vegetable' => '🥦', 'dairy' => '🥛', 'grain' => '🌾', 'protein' => '🥩', 'dessert' => '🍰', 'beverage' => '🥤'];
        return $cats[strtolower($cat)] ?? '🍽'; // '🍽' si catégorie inconnue
    }

    public static function getFoodImage(string $name, string $cat): string
    {
        $nm = strtolower($name);
        $localMap = [
            'flocon' => '/images/Berry Bliss Smoothie Bowl.jpg',
            'avoine' => '/images/Berry Bliss Smoothie Bowl.jpg',
            'lentille' => '/images/Irresistible Best Lentil Soup for a Cozy, Hearty Dinner.jpg',
            'escalope' => '/images/Escalopes de dinde panées - Recette Traditionelle.jpg',
            'dinde' => '/images/Escalopes de dinde panées - Recette Traditionelle.jpg',
            'ground beef' => '/images/Ground Beef Hot Honey Bowl.jpg',
            'hot honey' => '/images/Ground Beef Hot Honey Bowl.jpg',
            'patate' => '/images/Ground Beef Hot Honey Bowl.jpg',
            'chocolat' => '/images/Chocolate Sauce.jpg',
            'cacao' => '/images/Chocolate Sauce.jpg',
            'miel' => '/images/Homemade Honey Syrup_ Sweet, Simple, and So Useful!.jpg',
            'honey' => '/images/Homemade Honey Syrup_ Sweet, Simple, and So Useful!.jpg',
            'pizza' => '/images/download (10).jpg',
            'jus' => '/images/download (11).jpg',
            'orange' => '/images/download (11).jpg',
            'pate' => '/images/pasta.jpg',
            'pasta' => '/images/pasta.jpg',
            'fruit sec' => '/images/fruit sec.jpg',
            'noix' => '/images/fruit sec.jpg',
            'amande' => '/images/fruit sec.jpg',
            'menthe' => '/images/Tisane menthe.jpg',
            'the vert' => '/images/Tisane menthe.jpg',
            'thé vert' => '/images/Tisane menthe.jpg',
            'camomille' => '/images/Tisane camomille.jpg',
            'gingembre' => '/images/Tisane camomille.jpg',
            'tisane' => '/images/Tisane camomille.jpg',
        ];
        foreach ($localMap as $kw => $path) {
            if (strpos($nm, $kw) !== false) return $path;
        }

        $keywords = [
            'banane' => 'photo-1528825871115-3581a5387919',
            'pomme' => 'photo-1560806887-1e4cd0b6cbd6',
            'orange' => 'photo-1547514701-42782101795e',
            'fraise' => 'photo-1464965911861-746a04b4bca6',
            'myrtille' => 'photo-1498557850523-fd3d118b962e',
            'chocolat' => 'photo-1511381939415-e44f3c9a3d74',
            'salade' => 'photo-1512621776951-a57141f2eefd',
            'riz' => 'photo-1586201375761-83865001e31c',
            'poulet' => 'photo-1604908176997-125f25cc6f3d',
            'saumon' => 'photo-1467003909585-2f8a72700288',
            'soupe' => 'photo-1547592180-85f173990554',
            'pain' => 'photo-1509440159596-0249088772ff',
            'noix' => 'photo-1508061253366-f7da158b6d46',
            'avocat' => 'photo-1601039641847-7857b994d704',
            'smoothie' => 'photo-1490818387583-1baba5e638af',
            'jus' => 'photo-1534353436294-0dbd4bdac845',
            'oeuf' => 'photo-1582169505937-b9992bd01695',
            'lentilles' => 'photo-1515543904379-3d757afe72e4',
            'brocoli' => 'photo-1459411621453-7b03977f4bfc',
            'épinard' => 'photo-1576045057995-568f588f82fb',
            'carotte' => 'photo-1598170845058-32b9d6a5da37',
            'tomate' => 'photo-1546094096-0df4bcaaa337',
            'concombre' => 'photo-1604977042946-1eecc30f269e',
            'amande' => 'photo-1508061253366-f7da158b6d46',
            'yaourt' => 'photo-1571212515416-fef01fc43637',
            'lait' => 'photo-1563636619-e9143da7973b',
        ];
        foreach ($keywords as $kw => $pid) {
            if (strpos($nm, $kw) !== false) return "https://images.unsplash.com/$pid?w=400&q=75";
        }

        $catMap = [
            'fruit' => 'photo-1490474418585-ba9bad8fd0ea',
            'vegetable' => 'photo-1540420773420-3366772f4999',
            'grain' => 'photo-1586201375761-83865001e31c',
            'protein' => 'photo-1467003909585-2f8a72700288',
            'dairy' => 'photo-1563636619-e9143da7973b',
            'dessert' => 'photo-1563805042-7684c019e1cb',
            'beverage' => 'photo-1490818387583-1baba5e638af',
            'legume' => 'photo-1515543904379-3d757afe72e4',
            'nut' => 'photo-1508061253366-f7da158b6d46',
        ];
        $pid = $catMap[strtolower($cat)] ?? 'photo-1504674900247-0877df9cc836';
        return "https://images.unsplash.com/$pid?w=400&q=75";
    }
}
