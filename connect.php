<?php
// Fichier de connexion à PostgreSQL sur Render (connect.php)

// 1. Récupération de l'URL de la base de données depuis les variables d'environnement
$db_url = getenv('DATABASE_URL');

// 2. Sécurité : Vérification que la variable est bien définie
if ($db_url === false) {
    // Si l'application ne trouve pas la variable, on arrête tout avec un message clair.
    // Cela aide énormément à déboguer les déploiements.
    die("❌ Erreur critique : La variable d'environnement DATABASE_URL n'est pas définie.
         Vérifiez qu'elle est bien ajoutée dans l'onglet 'Environment' de votre service sur Render.");
}

// 3. Analyse de l'URL pour en extraire les composants
$db_parts = parse_url($db_url);

$host     = $db_parts['host'];
$port     = $db_parts['port'] ?? 5432; // Port par défaut de PostgreSQL si non spécifié
$dbname   = ltrim($db_parts['path'], '/');
$user     = $db_parts['user'];
$password = $db_parts['pass'];

// 4. Construction de la chaîne DSN (Data Source Name) pour PDO
//    On ajoute sslmode=require pour assurer une connexion sécurisée, comme recommandé par Render.
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    // 5. Création de l'instance de connexion PDO
    $pdo = new PDO($dsn, $user, $password);

    // Configuration des attributs de PDO pour un meilleur comportement
    // Active le mode d'erreur par exception pour un débogage plus simple
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Assure que les requêtes et résultats sont par défaut en UTF-8
    $pdo->exec("SET NAMES 'UTF8'");

    // Si on arrive ici, la connexion est réussie !
    // La variable $pdo est maintenant prête à être utilisée dans les autres fichiers de votre projet.
    // Exemple : require_once 'connect.php'; $query = $pdo->query('SELECT * FROM users');

} catch (PDOException $e) {
    // En cas d'échec de la connexion, on affiche un message d'erreur clair et on arrête le script.
    die("❌ Erreur de connexion à la base de données PostgreSQL : " . $e->getMessage());
}

?>