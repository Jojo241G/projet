<?php
// Récupère l'URL complète de la base de données depuis la variable d'environnement fournie par Render.
// Cette variable contient DÉJÀ toutes vos informations : utilisateur, mot de passe, hôte, etc.
$host = getenv("DB_HOST");
$db   = getenv("DB_NAME");
$user = getenv("DB_USER");
$pass = getenv("DB_PASSWORD");
$port = getenv("DB_PORT");

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$pdo = new PDO($dsn, $user, $pass);

// Si la variable n'est pas trouvée (ce qui ne devrait pas arriver sur Render), on arrête tout.
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

    // Configure PDO pour qu'il affiche les erreurs SQL (très utile pour le débogage).
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si la connexion échoue, le script s'arrête et affiche un message d'erreur.
    // L'erreur "502 Bad Gateway" est souvent causée par un échec ici.
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
<?php
// Inclut le fichier qui établit la connexion à la base de données.
// C'est la ligne la plus importante à ajouter.
require_once 'connect.php'; // Assurez-vous que le nom du fichier est correct

// La suite de votre code peut maintenant utiliser la variable $pdo
// car elle a été créée par le fichier inclus.

// Exemple de la suite de votre code :
// session_start();
// $email = $_POST['email'];
// $password = $_POST['password'];
// ...
?>

