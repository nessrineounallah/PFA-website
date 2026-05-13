<?php
namespace App\Core; // Ce fichier appartient au namespace App\Core

// Classe Model : classe de base dont héritent tous les Models (User, Food, Emotion...)
// Elle fournit simplement la connexion PDO à la base de données
class Model
{
    // Propriété : connexion PDO à la base de données, partagée par tous les Models
    protected \PDO $db;

    // Constructeur : reçoit la connexion PDO en paramètre et la stocke
    // Chaque Model est créé dans un Controller avec : new User($this->db)
    public function __construct(\PDO $db)
    {
        $this->db = $db; // Stocke la connexion pour l'utiliser dans les requêtes SQL
    }
}
