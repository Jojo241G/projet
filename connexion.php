<?php
try {
    $pdo = new PDO("pgsql:host=db;port=5432;dbname=projet_php", "admin", "motdepasse");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>