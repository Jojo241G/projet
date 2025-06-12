<?php
// Connexion à PostgreSQL via la variable d’environnement DATABASE_URL sur Render

// Récupération de l'URL
$db_url = getenv('DATABASE_URL');
require_once 'connect.php';

// Sécurité : on vérifie que la variable est bien définie
if ($db_url === false) {
    die("❌ Erreur critique : La variable d'environnement DATABASE_URL n'est pas définie.
    Vérifiez qu'elle est bien ajoutée dans Render (service > Environment).");
}

// Analyse l'URL (postgres://user:pass@host:port/dbname)
$db_parts = parse_url($db_url);
$host     = $db_parts['host'];
$port     = $db_parts['port'] ?? 5432; // Port par défaut PostgreSQL
$dbname   = ltrim($db_parts['path'], '/'); // Supprime le "/" du nom de base
$user     = $db_parts['user'];
$password = $db_parts['pass'];

// Construction de la chaîne DSN pour PDO
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

try {
    // Connexion via PDO
    $pdo = new PDO($dsn, $user, $password);

    // Active le mode exception pour faciliter le débogage
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optionnel : UTF-8 par défaut
    $pdo->exec("SET NAMES 'UTF8'");

    // La variable $pdo est maintenant disponible pour tout le projet

} catch (PDOException $e) {
    // Affiche un message clair en cas d’erreur
    die("❌ Erreur de connexion à la base PostgreSQL : " . $e->getMessage());
}
?>

