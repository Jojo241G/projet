<?php
try {
    $pdo = new PDO("pgsql:host=db;port=5432;dbname=projet_tuteur", "postgres", "jojo");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>