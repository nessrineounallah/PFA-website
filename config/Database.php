<?php
/* ================================================
   config/Database.php
   Classe qui gère la connexion à la base de données MySQL.
   On l'appelle depuis connexion.php avec new Database()
   ================================================ */
// Classe Database : crée et retourne une connexion PDO à la base de données MySQL
// Les paramètres sont lus depuis les variables d'environnement (ou valeurs par défaut)
class Database {
    // Propriétés : informations de connexion à la base de données
    private $host;     // Adresse du serveur MySQL (ex: localhost)
    private $db_name;  // Nom de la base de données (ex: emoeat)
    private $username; // Nom d'utilisateur MySQL (ex: root)
    private $password; // Mot de passe MySQL
    public $conn;      // Stockera la connexion PDO active

    // Constructeur : lit les paramètres depuis les variables d'environnement
    // Si une variable n'est pas définie, utilise la valeur par défaut (localhost, emoeat, root, '')
    public function __construct() {
        $this->host     = getenv('DB_HOST') ?: 'localhost'; // Serveur MySQL
        $this->db_name  = getenv('DB_NAME') ?: 'emoeat';   // Nom de la base de données
        $this->username = getenv('DB_USER') ?: 'root';      // Utilisateur MySQL
        $this->password = getenv('DB_PASSWORD') ?: '';      // Mot de passe (vide par défaut dans XAMPP)
    }

    // Méthode pour obtenir la connexion à la base de données
    // Retourne un objet PDO prêt à être utilisé pour les requêtes SQL
    public function getConnection() {
        $this->conn = null; // Réinitialise la connexion à null

        try {
            // Construit le DSN (Data Source Name) : chaîne qui identifie la base de données
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            // Crée la connexion PDO avec le DSN, l'utilisateur et le mot de passe
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // Configuration pour afficher les erreurs SQL proprement (via exceptions)
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch(PDOException $exception) {
            // Si la connexion échoue, affiche l'erreur et arrête l'exécution
            die("Erreur de connexion MySQL : " . $exception->getMessage());
        }

        return $this->conn; // Retourne la connexion PDO active
    }
}
?>