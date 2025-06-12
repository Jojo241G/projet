-- 1. UTILISATEURS ET ROLES
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    mot_de_passe TEXT NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'chef', 'membre')),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. PROJETS
CREATE TABLE projets (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    date_debut DATE,
    date_fin DATE,
    cree_par INT REFERENCES users(id) ON DELETE SET NULL,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. TACHES
CREATE TABLE taches (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    description TEXT,
    projet_id INT REFERENCES projets(id) ON DELETE CASCADE,
    assigne_a INT REFERENCES users(id) ON DELETE SET NULL,
    date_debut DATE,
    date_fin_estimee DATE,
    date_fin_reelle DATE,
    statut VARCHAR(20) CHECK (statut IN ('non commencé', 'en cours', 'terminé')) DEFAULT 'non commencé',
    progression INT CHECK (progression BETWEEN 0 AND 100) DEFAULT 0,
    priorite VARCHAR(10) CHECK (priorite IN ('haute', 'moyenne', 'basse')) DEFAULT 'moyenne'
);

-- 4. HISTORIQUE DES ACTIONS
CREATE TABLE historique (
    id SERIAL PRIMARY KEY,
    tache_id INT REFERENCES taches(id) ON DELETE CASCADE,
    utilisateur_id INT REFERENCES users(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    date_action TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. COMMENTAIRES DE COLLABORATION
CREATE TABLE commentaires (
    id SERIAL PRIMARY KEY,
    tache_id INT REFERENCES taches(id) ON DELETE CASCADE,
    utilisateur_id INT REFERENCES users(id) ON DELETE CASCADE,
    contenu TEXT NOT NULL,
    date_commentaire TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. PIECES JOINTES
CREATE TABLE fichiers (
    id SERIAL PRIMARY KEY,
    tache_id INT REFERENCES taches(id) ON DELETE CASCADE,
    chemin_fichier TEXT NOT NULL,
    nom_fichier VARCHAR(255),
    date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. DONNEES POUR IA (ENTRAINEMENT / HISTORIQUE DE RETARD)
CREATE TABLE historique_retards (
    id SERIAL PRIMARY KEY,
    projet_id INT REFERENCES projets(id) ON DELETE CASCADE,
    tache_id INT REFERENCES taches(id) ON DELETE CASCADE,
    duree_estimee INT,   -- en jours
    duree_reelle INT,    -- en jours
    personnes_assignees INT,
    retard BOOLEAN       -- TRUE si retardé, FALSE sinon
);

-- 8. SAUVEGARDES / IMPORTS (LOGIQUE)
CREATE TABLE sauvegardes (
    id SERIAL PRIMARY KEY,
    projet_id INT REFERENCES projets(id) ON DELETE CASCADE,
    sauvegarde_json JSONB NOT NULL,
    date_sauvegarde TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
select*from users;

-- ATTENTION : Ces requêtes insèrent les mots de passe en CLAIR.
-- C'est une pratique DANGEREUSE et non recommandée pour la sécurité.

-- Insertion de l'administrateur
INSERT INTO users (nom, email, mot_de_passe, role) VALUES
('Admin Ojoslath', 'ojoslath@gmail.com', 'admin', 'admin');

-- Insertion du chef de projet
INSERT INTO users (nom, email, mot_de_passe, role) VALUES
('Chef Brindel', 'brindelndengue8@gmail.com', 'gestion', 'chef');

-- Insertion du membre d'équipe
INSERT INTO users (nom, email, mot_de_passe, role) VALUES
('Membre User', 'user@gmail.com', '1234', 'membre');

-- TABLE POUR LES ÉQUIPES
CREATE TABLE equipes (
    id SERIAL PRIMARY KEY,
    nom_equipe VARCHAR(255) NOT NULL,
    projet_id INT UNIQUE REFERENCES projets(id) ON DELETE SET NULL, -- Une équipe est liée à un seul projet
    chef_projet_id INT REFERENCES users(id) ON DELETE SET NULL,     -- Le chef de projet assigné à cette équipe
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLE PIVOT POUR LIER LES UTILISATEURS AUX ÉQUIPES (MEMBRES)
CREATE TABLE equipe_membres (
    equipe_id INT NOT NULL REFERENCES equipes(id) ON DELETE CASCADE,
    utilisateur_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (equipe_id, utilisateur_id) -- Empêche d'ajouter plusieurs fois le même membre à la même équipe
);

CREATE TABLE parametres_app (
    cle VARCHAR(50) PRIMARY KEY,
    valeur TEXT
);

-- Optionnel : Insérer quelques valeurs par défaut
INSERT INTO parametres_app (cle, valeur) VALUES
    ('app_langue', 'fr'),
    ('app_timezone', 'UTC'),
    ('securite_mdp_longueur', '8'),
    ('maintenance_mode', '0');

ALTER TABLE projets
ADD COLUMN modifie_par INT REFERENCES users(id) ON DELETE SET NULL,
ADD COLUMN derniere_modification TIMESTAMP;

-- Supprimez les tables equipes et equipe_membres si elles existent
-- DROP TABLE IF EXISTS equipe_membres;
-- DROP TABLE IF EXISTS equipes;

-- Créez cette table pour lier directement les utilisateurs aux projets
CREATE TABLE projet_membres (
    projet_id INT NOT NULL REFERENCES projets(id) ON DELETE CASCADE,
    utilisateur_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (projet_id, utilisateur_id) -- Un membre ne peut être qu'une fois dans un projet
);


ALTER TABLE projets ADD COLUMN chef_id INT REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE fichiers
ADD COLUMN utilisateur_id INT REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE soumissions (
    id SERIAL PRIMARY KEY,
    projet_id INT NOT NULL REFERENCES projets(id) ON DELETE CASCADE,
    tache_id INT REFERENCES taches(id) ON DELETE SET NULL,
    fichier_path TEXT NOT NULL,
    message TEXT,
    soumis_par_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    destinataire_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    date_soumission TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut VARCHAR(20) DEFAULT 'soumis'
);


ALTER TABLE historique
ADD COLUMN projet_id INT REFERENCES projets(id) ON DELETE CASCADE;

SELECT id, nom, date_fin_estimee
FROM taches
WHERE date_fin_estimee IS NULL OR date_fin_estimee::text = '';


-- Mettre à jour les dates d'échéance pour les tâches où date_fin_estimee est NULL
UPDATE taches
SET date_fin_estimee = CASE
    WHEN statut = 'non commencé' THEN CURRENT_DATE + INTERVAL '7 days'
    WHEN statut = 'en cours' THEN CURRENT_DATE + INTERVAL '3 days'
    WHEN statut = 'terminé' THEN CURRENT_DATE
    ELSE date_fin_estimee -- Garder la date existante si non NULL
END
WHERE date_fin_estimee IS NULL;



CREATE OR REPLACE FUNCTION set_default_task_due_date()
RETURNS TRIGGER AS $$
BEGIN
    -- On vérifie si une nouvelle tâche est insérée SANS date de fin estimée
    IF NEW.date_fin_estimee IS NULL THEN
        -- On applique la même logique que votre requête
        NEW.date_fin_estimee := CASE
            WHEN NEW.statut = 'non commencé' THEN CURRENT_DATE + INTERVAL '7 days'
            WHEN NEW.statut = 'en cours' THEN CURRENT_DATE + INTERVAL '3 days'
            WHEN NEW.statut = 'terminé' THEN CURRENT_DATE
            ELSE CURRENT_DATE + INTERVAL '7 days' -- Une valeur par défaut si le statut est inconnu
        END;
    END IF;
    
    -- On retourne la nouvelle ligne modifiée pour qu'elle soit insérée
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;



-- On s'assure qu'un ancien trigger du même nom n'existe pas
DROP TRIGGER IF EXISTS trg_default_due_date ON taches;

-- On crée le nouveau trigger
CREATE TRIGGER trg_default_due_date
BEFORE INSERT ON taches -- Il se déclenche AVANT l'insertion
FOR EACH ROW -- Pour chaque ligne insérée
EXECUTE FUNCTION set_default_task_due_date();