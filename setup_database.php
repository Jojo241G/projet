<?php
// Fichier : setup_database.php
// Ce script crée les tables nécessaires dans la base de données.
// À n'exécuter qu'une seule fois !

// 1. On inclut notre fichier de connexion
require_once 'connexion.php';

echo "Connexion à la base de données réussie.<br>";

try {
    // 2. On écrit la requête SQL pour créer la table "utilisateurs"
    //    TEXT est utilisé pour les chaînes de caractères (VARCHAR).
    //    SERIAL PRIMARY KEY est pour un ID qui s'incrémente automatiquement.
    $sql = "CREATE TABLE utilisateurs (
        id SERIAL PRIMARY KEY,
        email TEXT NOT NULL UNIQUE,
        mot_de_passe TEXT NOT NULL,
        date_inscription TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    // 3. On exécute la requête via PDO
    $pdo->exec($sql);

    echo "La table 'utilisateurs' a été créée avec succès !<br>";

} catch (PDOException $e) {
    // Affiche un message si quelque chose ne va pas (par exemple, si la table existe déjà)
    die("ERREUR lors de la création de la table : " . $e->getMessage());
}

echo "Le script de configuration de la base de données est terminé.";

?>