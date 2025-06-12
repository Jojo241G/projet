<?php
try {
    $pdo = new PDO("pgsql:host=dpg-d152is3uibrs73bgrilg-a.frankfurt-postgres.render.com;port=5432;dbname=projet_tuteur_v9mg", "projet_tuteur_v9mg_user", "i5m26GjTcPQ6C0hSHnkORa4fKYlmEet3");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>