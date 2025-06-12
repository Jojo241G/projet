--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4
-- Dumped by pg_dump version 17.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: set_default_task_due_date(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.set_default_task_due_date() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
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
$$;


ALTER FUNCTION public.set_default_task_due_date() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: commentaires; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.commentaires (
    id integer NOT NULL,
    tache_id integer,
    utilisateur_id integer,
    contenu text NOT NULL,
    date_commentaire timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.commentaires OWNER TO postgres;

--
-- Name: commentaires_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.commentaires_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.commentaires_id_seq OWNER TO postgres;

--
-- Name: commentaires_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.commentaires_id_seq OWNED BY public.commentaires.id;


--
-- Name: equipe_membres; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipe_membres (
    equipe_id integer NOT NULL,
    utilisateur_id integer NOT NULL
);


ALTER TABLE public.equipe_membres OWNER TO postgres;

--
-- Name: equipes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipes (
    id integer NOT NULL,
    nom_equipe character varying(255) NOT NULL,
    projet_id integer,
    chef_projet_id integer,
    date_creation timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.equipes OWNER TO postgres;

--
-- Name: equipes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.equipes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipes_id_seq OWNER TO postgres;

--
-- Name: equipes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.equipes_id_seq OWNED BY public.equipes.id;


--
-- Name: fichiers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.fichiers (
    id integer NOT NULL,
    tache_id integer,
    chemin_fichier text NOT NULL,
    nom_fichier character varying(255),
    date_ajout timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    utilisateur_id integer
);


ALTER TABLE public.fichiers OWNER TO postgres;

--
-- Name: fichiers_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.fichiers_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.fichiers_id_seq OWNER TO postgres;

--
-- Name: fichiers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.fichiers_id_seq OWNED BY public.fichiers.id;


--
-- Name: historique; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.historique (
    id integer NOT NULL,
    tache_id integer,
    utilisateur_id integer,
    action text NOT NULL,
    date_action timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    projet_id integer
);


ALTER TABLE public.historique OWNER TO postgres;

--
-- Name: historique_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.historique_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.historique_id_seq OWNER TO postgres;

--
-- Name: historique_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.historique_id_seq OWNED BY public.historique.id;


--
-- Name: historique_retards; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.historique_retards (
    id integer NOT NULL,
    projet_id integer,
    tache_id integer,
    duree_estimee integer,
    duree_reelle integer,
    personnes_assignees integer,
    retard boolean
);


ALTER TABLE public.historique_retards OWNER TO postgres;

--
-- Name: historique_retards_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.historique_retards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.historique_retards_id_seq OWNER TO postgres;

--
-- Name: historique_retards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.historique_retards_id_seq OWNED BY public.historique_retards.id;


--
-- Name: parametres_app; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.parametres_app (
    cle character varying(50) NOT NULL,
    valeur text
);


ALTER TABLE public.parametres_app OWNER TO postgres;

--
-- Name: projet_membres; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.projet_membres (
    projet_id integer NOT NULL,
    utilisateur_id integer NOT NULL
);


ALTER TABLE public.projet_membres OWNER TO postgres;

--
-- Name: projets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.projets (
    id integer NOT NULL,
    nom character varying(200) NOT NULL,
    description text,
    date_debut date,
    date_fin date,
    cree_par integer,
    date_creation timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    modifie_par integer,
    derniere_modification timestamp without time zone,
    chef_id integer
);


ALTER TABLE public.projets OWNER TO postgres;

--
-- Name: projets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.projets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.projets_id_seq OWNER TO postgres;

--
-- Name: projets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.projets_id_seq OWNED BY public.projets.id;


--
-- Name: sauvegardes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sauvegardes (
    id integer NOT NULL,
    projet_id integer,
    sauvegarde_json jsonb NOT NULL,
    date_sauvegarde timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.sauvegardes OWNER TO postgres;

--
-- Name: sauvegardes_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sauvegardes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sauvegardes_id_seq OWNER TO postgres;

--
-- Name: sauvegardes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sauvegardes_id_seq OWNED BY public.sauvegardes.id;


--
-- Name: soumissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.soumissions (
    id integer NOT NULL,
    projet_id integer NOT NULL,
    tache_id integer,
    fichier_path text NOT NULL,
    message text,
    soumis_par_id integer NOT NULL,
    destinataire_id integer NOT NULL,
    date_soumission timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    statut character varying(20) DEFAULT 'soumis'::character varying
);


ALTER TABLE public.soumissions OWNER TO postgres;

--
-- Name: soumissions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.soumissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.soumissions_id_seq OWNER TO postgres;

--
-- Name: soumissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.soumissions_id_seq OWNED BY public.soumissions.id;


--
-- Name: taches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.taches (
    id integer NOT NULL,
    nom character varying(200) NOT NULL,
    description text,
    projet_id integer,
    assigne_a integer,
    date_debut date,
    date_fin_estimee date,
    date_fin_reelle date,
    statut character varying(20) DEFAULT 'non commencé'::character varying,
    progression integer DEFAULT 0,
    priorite character varying(10) DEFAULT 'moyenne'::character varying,
    CONSTRAINT taches_priorite_check CHECK (((priorite)::text = ANY ((ARRAY['haute'::character varying, 'moyenne'::character varying, 'basse'::character varying])::text[]))),
    CONSTRAINT taches_progression_check CHECK (((progression >= 0) AND (progression <= 100))),
    CONSTRAINT taches_statut_check CHECK (((statut)::text = ANY ((ARRAY['non commencé'::character varying, 'en cours'::character varying, 'terminé'::character varying])::text[])))
);


ALTER TABLE public.taches OWNER TO postgres;

--
-- Name: taches_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.taches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.taches_id_seq OWNER TO postgres;

--
-- Name: taches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.taches_id_seq OWNED BY public.taches.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    nom character varying(100) NOT NULL,
    email character varying(150) NOT NULL,
    mot_de_passe text NOT NULL,
    role character varying(20) NOT NULL,
    date_creation timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'chef'::character varying, 'membre'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: commentaires id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.commentaires ALTER COLUMN id SET DEFAULT nextval('public.commentaires_id_seq'::regclass);


--
-- Name: equipes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipes ALTER COLUMN id SET DEFAULT nextval('public.equipes_id_seq'::regclass);


--
-- Name: fichiers id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fichiers ALTER COLUMN id SET DEFAULT nextval('public.fichiers_id_seq'::regclass);


--
-- Name: historique id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique ALTER COLUMN id SET DEFAULT nextval('public.historique_id_seq'::regclass);


--
-- Name: historique_retards id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique_retards ALTER COLUMN id SET DEFAULT nextval('public.historique_retards_id_seq'::regclass);


--
-- Name: projets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projets ALTER COLUMN id SET DEFAULT nextval('public.projets_id_seq'::regclass);


--
-- Name: sauvegardes id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sauvegardes ALTER COLUMN id SET DEFAULT nextval('public.sauvegardes_id_seq'::regclass);


--
-- Name: soumissions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions ALTER COLUMN id SET DEFAULT nextval('public.soumissions_id_seq'::regclass);


--
-- Name: taches id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taches ALTER COLUMN id SET DEFAULT nextval('public.taches_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Data for Name: commentaires; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.commentaires (id, tache_id, utilisateur_id, contenu, date_commentaire) FROM stdin;
\.


--
-- Data for Name: equipe_membres; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipe_membres (equipe_id, utilisateur_id) FROM stdin;
1	6
1	5
2	5
\.


--
-- Data for Name: equipes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipes (id, nom_equipe, projet_id, chef_projet_id, date_creation) FROM stdin;
1	equipe1	1	2	2025-06-10 00:49:42.437441
2	equipe7	\N	7	2025-06-11 17:09:00.090685
\.


--
-- Data for Name: fichiers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.fichiers (id, tache_id, chemin_fichier, nom_fichier, date_ajout, utilisateur_id) FROM stdin;
\.


--
-- Data for Name: historique; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.historique (id, tache_id, utilisateur_id, action, date_action, projet_id) FROM stdin;
1	\N	3	Upload du fichier admin_dashboard.php	2025-06-11 10:18:37.412072	1
2	1	3	Soumission du fichier : admin_dashboard.php	2025-06-11 10:39:45.498642	1
3	1	3	Soumission du fichier : admin_dashboard.php	2025-06-11 10:40:04.292678	1
4	\N	3	Upload du fichier base_de_données.txt	2025-06-11 11:26:59.333953	1
5	1	3	Soumission du fichier : base_de_donn&eacute;es.txt	2025-06-11 11:27:10.264344	1
6	\N	3	Upload du fichier base_de_données.txt	2025-06-11 11:27:42.772741	1
7	\N	3	Upload du fichier api_history.php	2025-06-11 11:27:49.333373	1
8	1	3	Soumission du fichier : api_history.php	2025-06-11 11:28:07.821117	1
9	\N	3	Suppression de l'élément: Ajout	2025-06-11 11:53:01.774424	1
10	\N	3	Suppression de l'élément: api_history.php	2025-06-11 11:53:11.770696	1
11	\N	3	Renommage de admin_dashboard.php en admin_dashboard2.php	2025-06-11 11:54:17.045172	1
12	\N	3	Sauvegarde du fichier: admin_dashboard2.php	2025-06-11 13:10:13.9114	1
13	\N	3	Sauvegarde du fichier: admin_dashboard2.php	2025-06-11 13:10:36.654105	1
14	\N	6	Upload du fichier delete_user.php	2025-06-11 14:04:33.171209	1
15	\N	6	Upload du fichier api_predict_delay.php	2025-06-11 14:05:31.740663	1
16	2	6	Soumission du fichier : api_predict_delay.php	2025-06-11 14:05:49.163097	1
17	3	5	Soumission du fichier : delete_user.php	2025-06-11 14:07:05.74939	1
18	\N	4	Upload du fichier create_user.php	2025-06-11 16:10:22.838684	1
20	\N	3	Suppression de l'élément: admin_dashboard2.php	2025-06-11 17:20:08.011641	1
21	\N	3	Suppression de l'élément: api_predict_delay.php	2025-06-11 17:20:10.721327	1
22	\N	3	Suppression de l'élément: delete_user.php	2025-06-11 17:20:20.466852	1
23	\N	3	Sauvegarde du fichier: create_user.php	2025-06-11 18:58:06.984642	1
\.


--
-- Data for Name: historique_retards; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.historique_retards (id, projet_id, tache_id, duree_estimee, duree_reelle, personnes_assignees, retard) FROM stdin;
\.


--
-- Data for Name: parametres_app; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.parametres_app (cle, valeur) FROM stdin;
app_nom	Mon Projet de Gestion
app_langue	fr
app_timezone	UTC
securite_mdp_longueur	8
email_host	
email_port	587
email_user	
email_pass	
ia_api_key	
maintenance_mode	0
ia_prediction_active	1
\.


--
-- Data for Name: projet_membres; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.projet_membres (projet_id, utilisateur_id) FROM stdin;
1	3
1	6
1	5
\.


--
-- Data for Name: projets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.projets (id, nom, description, date_debut, date_fin, cree_par, date_creation, modifie_par, derniere_modification, chef_id) FROM stdin;
1	Developpement d'une application de stock	il doit voir les fonctionalité d'ajout , de suppression et de modification (CRDU)	2025-06-10	2025-06-12	2	2025-06-10 00:30:00.094635	\N	\N	\N
\.


--
-- Data for Name: sauvegardes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sauvegardes (id, projet_id, sauvegarde_json, date_sauvegarde) FROM stdin;
\.


--
-- Data for Name: soumissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.soumissions (id, projet_id, tache_id, fichier_path, message, soumis_par_id, destinataire_id, date_soumission, statut) FROM stdin;
3	1	1	/admin_dashboard.php	jj	3	2	2025-06-11 10:40:04.285927	approuvé
6	1	2	/api_predict_delay.php	j&#039;ai fini chef	6	2	2025-06-11 14:05:49.157947	approuvé
8	1	\N	/create_user.php	fin	4	2	2025-06-11 16:10:55.268145	rejeté
\.


--
-- Data for Name: taches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.taches (id, nom, description, projet_id, assigne_a, date_debut, date_fin_estimee, date_fin_reelle, statut, progression, priorite) FROM stdin;
1	Ajout	fonctionalité Ajout de l'app	1	3	\N	2025-06-11	\N	terminé	100	haute
2	modification	modification 	1	6	\N	2025-06-11	\N	terminé	100	moyenne
3	suppression	supprimer	1	5	\N	2025-06-11	\N	terminé	100	basse
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (id, nom, email, mot_de_passe, role, date_creation) FROM stdin;
1	joslath	ojoslath@gmail.com	admin	admin	2025-06-09 22:16:51.606445
3	Dawson	dawson@gmail.com	1234	membre	2025-06-09 22:16:51.606445
2	Brindel	brindelndengue8@gmail.com	gestion	chef	2025-06-09 22:16:51.606445
4	Alima	Alima@gmail.com	1234	membre	2025-06-10 00:13:46.443613
5	Liandra	liandra@gmail.com	1234	membre	2025-06-10 00:15:16.796337
6	Lena	lena@gmail.com	1234	membre	2025-06-10 00:17:29.621956
7	keva	keva@gmail.com	1234	chef	2025-06-11 17:11:16.688222
\.


--
-- Name: commentaires_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.commentaires_id_seq', 1, false);


--
-- Name: equipes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.equipes_id_seq', 2, true);


--
-- Name: fichiers_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.fichiers_id_seq', 1, false);


--
-- Name: historique_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.historique_id_seq', 23, true);


--
-- Name: historique_retards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.historique_retards_id_seq', 1, false);


--
-- Name: projets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.projets_id_seq', 3, true);


--
-- Name: sauvegardes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sauvegardes_id_seq', 1, false);


--
-- Name: soumissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.soumissions_id_seq', 8, true);


--
-- Name: taches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.taches_id_seq', 9, true);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 7, true);


--
-- Name: commentaires commentaires_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.commentaires
    ADD CONSTRAINT commentaires_pkey PRIMARY KEY (id);


--
-- Name: equipe_membres equipe_membres_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipe_membres
    ADD CONSTRAINT equipe_membres_pkey PRIMARY KEY (equipe_id, utilisateur_id);


--
-- Name: equipes equipes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipes
    ADD CONSTRAINT equipes_pkey PRIMARY KEY (id);


--
-- Name: equipes equipes_projet_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipes
    ADD CONSTRAINT equipes_projet_id_key UNIQUE (projet_id);


--
-- Name: fichiers fichiers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fichiers
    ADD CONSTRAINT fichiers_pkey PRIMARY KEY (id);


--
-- Name: historique historique_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique
    ADD CONSTRAINT historique_pkey PRIMARY KEY (id);


--
-- Name: historique_retards historique_retards_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique_retards
    ADD CONSTRAINT historique_retards_pkey PRIMARY KEY (id);


--
-- Name: parametres_app parametres_app_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametres_app
    ADD CONSTRAINT parametres_app_pkey PRIMARY KEY (cle);


--
-- Name: projet_membres projet_membres_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projet_membres
    ADD CONSTRAINT projet_membres_pkey PRIMARY KEY (projet_id, utilisateur_id);


--
-- Name: projets projets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projets
    ADD CONSTRAINT projets_pkey PRIMARY KEY (id);


--
-- Name: sauvegardes sauvegardes_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sauvegardes
    ADD CONSTRAINT sauvegardes_pkey PRIMARY KEY (id);


--
-- Name: soumissions soumissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions
    ADD CONSTRAINT soumissions_pkey PRIMARY KEY (id);


--
-- Name: taches taches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taches
    ADD CONSTRAINT taches_pkey PRIMARY KEY (id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: taches trg_default_due_date; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_default_due_date BEFORE INSERT ON public.taches FOR EACH ROW EXECUTE FUNCTION public.set_default_task_due_date();


--
-- Name: commentaires commentaires_tache_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.commentaires
    ADD CONSTRAINT commentaires_tache_id_fkey FOREIGN KEY (tache_id) REFERENCES public.taches(id) ON DELETE CASCADE;


--
-- Name: commentaires commentaires_utilisateur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.commentaires
    ADD CONSTRAINT commentaires_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: equipe_membres equipe_membres_equipe_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipe_membres
    ADD CONSTRAINT equipe_membres_equipe_id_fkey FOREIGN KEY (equipe_id) REFERENCES public.equipes(id) ON DELETE CASCADE;


--
-- Name: equipe_membres equipe_membres_utilisateur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipe_membres
    ADD CONSTRAINT equipe_membres_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: equipes equipes_chef_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipes
    ADD CONSTRAINT equipes_chef_projet_id_fkey FOREIGN KEY (chef_projet_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipes equipes_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipes
    ADD CONSTRAINT equipes_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE SET NULL;


--
-- Name: fichiers fichiers_tache_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fichiers
    ADD CONSTRAINT fichiers_tache_id_fkey FOREIGN KEY (tache_id) REFERENCES public.taches(id) ON DELETE CASCADE;


--
-- Name: fichiers fichiers_utilisateur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.fichiers
    ADD CONSTRAINT fichiers_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: historique historique_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique
    ADD CONSTRAINT historique_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- Name: historique_retards historique_retards_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique_retards
    ADD CONSTRAINT historique_retards_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- Name: historique_retards historique_retards_tache_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique_retards
    ADD CONSTRAINT historique_retards_tache_id_fkey FOREIGN KEY (tache_id) REFERENCES public.taches(id) ON DELETE CASCADE;


--
-- Name: historique historique_tache_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique
    ADD CONSTRAINT historique_tache_id_fkey FOREIGN KEY (tache_id) REFERENCES public.taches(id) ON DELETE CASCADE;


--
-- Name: historique historique_utilisateur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historique
    ADD CONSTRAINT historique_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: projet_membres projet_membres_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projet_membres
    ADD CONSTRAINT projet_membres_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- Name: projet_membres projet_membres_utilisateur_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projet_membres
    ADD CONSTRAINT projet_membres_utilisateur_id_fkey FOREIGN KEY (utilisateur_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: projets projets_chef_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projets
    ADD CONSTRAINT projets_chef_id_fkey FOREIGN KEY (chef_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: projets projets_cree_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projets
    ADD CONSTRAINT projets_cree_par_fkey FOREIGN KEY (cree_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: projets projets_modifie_par_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projets
    ADD CONSTRAINT projets_modifie_par_fkey FOREIGN KEY (modifie_par) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sauvegardes sauvegardes_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sauvegardes
    ADD CONSTRAINT sauvegardes_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- Name: soumissions soumissions_destinataire_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions
    ADD CONSTRAINT soumissions_destinataire_id_fkey FOREIGN KEY (destinataire_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: soumissions soumissions_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions
    ADD CONSTRAINT soumissions_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- Name: soumissions soumissions_soumis_par_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions
    ADD CONSTRAINT soumissions_soumis_par_id_fkey FOREIGN KEY (soumis_par_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: soumissions soumissions_tache_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.soumissions
    ADD CONSTRAINT soumissions_tache_id_fkey FOREIGN KEY (tache_id) REFERENCES public.taches(id) ON DELETE SET NULL;


--
-- Name: taches taches_assigne_a_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taches
    ADD CONSTRAINT taches_assigne_a_fkey FOREIGN KEY (assigne_a) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: taches taches_projet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.taches
    ADD CONSTRAINT taches_projet_id_fkey FOREIGN KEY (projet_id) REFERENCES public.projets(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

create schema projet_tuteur_v9mg_user; 

