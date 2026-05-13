<?php
namespace App\Controllers; // Ce fichier appartient au namespace App\Controllers

use App\Core\Controller; // Importe la classe parente Controller

// Classe HomeController : gère la page d'accueil publique du site
class HomeController extends Controller
{
    // Fonction : afficher la page d'accueil (accessible à tout le monde, sans connexion)
    public function index(): void
    {
        $this->view('home/index'); // Affiche la vue app/Views/home/index.php
    }
}
