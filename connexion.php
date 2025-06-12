<?php
// Fichier : connect.php
// Ce fichier ne fait qu'une seule chose : se connecter à la base de données.

// Récupère l'URL complète de la base de données depuis la variable d'environnement fournie par Render.
$db_url = getenv('DATABASE_URL');

// Si la variable n'est pas trouvée, on arrête tout.
if ($db_url === false) {
    die("Erreur critique : La variable d'environnement DATABASE_URL n'est pas définie. Assurez-vous que la base de données est bien liée au service web dans Render.");
}

// PHP analyse cette URL pour en extraire chaque partie.
$db_parts = parse_url($db_url);

$host = $db_parts['host'];
$port = $db_parts['port'];
$dbname = ltrim($db_parts['path'], '/'); // Supprime le premier "/" du nom de la base
$user = $db_parts['user'];
$password = $db_parts['pass'];

// Construit la chaîne de connexion (DSN) pour PDO à partir des éléments extraits.
$dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";

try {
    // Tente la connexion avec les informations d'identification de Render.
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si la connexion échoue, le script s'arrête et affiche un message d'erreur.
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>

