<?php /* ================================================================
   layouts/main.php - Gabarit principal (layout) commun à toutes les pages
   Ce fichier est inclus par Controller::view() pour envelopper le contenu.
   Il ajoute automatiquement le <head> HTML, la navbar et le footer.
   ================================================================ */ ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Titre de la page : chaque vue peut définir $pageTitle, sinon 'EmoEat' par défaut -->
    <title><?php echo $pageTitle ?? 'EmoEat'; ?></title>
    <!-- Feuille de style principale avec versioning (?v=25 pour invalider le cache) -->
    <link rel="stylesheet" href="/style.css?v=25">
</head>
<body>

<?php /* Inclut la barre de navigation (affiche les liens selon le rôle et l'état de connexion) */ ?>
<?php require dirname(__DIR__) . '/partials/navbar.php'; ?>

<?php /* Zone de contenu principal : $content est défini par chaque vue spécifique */ ?>
<?php echo $content ?? ''; ?>

<?php /* Inclut le pied de page (footer) commun */ ?>
<?php require dirname(__DIR__) . '/partials/footer.php'; ?>

</body>
</html>
