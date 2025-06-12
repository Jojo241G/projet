<?php
session_start();
require_once 'connexion.php';

// =================================================================================
// BLOC API POUR L'ANALYSE DE PROJET (appelé par le JavaScript de cette même page)
// =================================================================================
if (isset($_GET['action']) && $_GET['action'] === 'predict_delay') {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
        echo json_encode(['success' => false, 'error' => 'Accès non autorisé.']);
        exit();
    }

    $project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
    if (!$project_id) {
        echo json_encode(['success' => false, 'error' => 'ID de projet invalide.']);
        exit();
    }

    try {
        // --- Étape 1: Récupérer les données du projet et TOUTES ses tâches ---
        $stmt_project = $pdo->prepare("SELECT nom, date_debut, date_fin FROM projets WHERE id = ?");
        $stmt_project->execute([$project_id]);
        $project = $stmt_project->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            echo json_encode(['success' => false, 'error' => 'Projet non trouvé.']);
            exit();
        }
        
        $stmt_tasks = $pdo->prepare("
            SELECT t.*, u.nom as user_name
            FROM taches t
            LEFT JOIN users u ON t.assigne_a = u.id
            WHERE t.projet_id = :project_id
            ORDER BY u.nom, t.date_fin_estimee ASC
        ");
        $stmt_tasks->execute([':project_id' => $project_id]);
        $all_tasks = $stmt_tasks->fetchAll(PDO::FETCH_ASSOC);

        // --- Étape 2: Traiter les données en PHP pour calculer les statistiques et préparer l'analyse ---
        $stats = [
            'total_tasks' => count($all_tasks),
            'remaining_tasks' => 0,
            'overdue_tasks' => 0,
            'high_priority_remaining' => 0,
            'total_progress' => 0,
        ];
        $team_analysis = [];
        $today_dt = new DateTime();

        foreach ($all_tasks as $task) {
            $stats['total_progress'] += $task['progression'];
            if ($task['statut'] != 'terminé') {
                $stats['remaining_tasks']++;
                if (new DateTime($task['date_fin_estimee']) < $today_dt) {
                    $stats['overdue_tasks']++;
                }
                if ($task['priorite'] == 'haute') {
                    $stats['high_priority_remaining']++;
                }
                // Grouper pour l'analyse détaillée
                 if ($task['user_name']) {
                    $team_analysis[$task['user_name']][] = ['name' => $task['nom'], 'deadline' => $task['date_fin_estimee']];
                }
            }
        }
        $avg_progress = ($stats['total_tasks'] > 0) ? ($stats['total_progress'] / $stats['total_tasks']) : 0;

        // --- Étape 3: Calculer le score de risque pondéré ---
        $risk_score = 0;
        if ($stats['remaining_tasks'] > 0) {
            // Facteur 1: Tâches en retard (50%)
            $risk_score += ($stats['overdue_tasks'] / $stats['remaining_tasks']) * 50;
            // Facteur 3: Tâches prioritaires restantes (20%)
            $risk_score += ($stats['high_priority_remaining'] / $stats['remaining_tasks']) * 20;
        }

        // Facteur 2: Rapport temps écoulé vs progression (30%)
        if (!empty($project['date_debut']) && !empty($project['date_fin'])) {
            $start = new DateTime($project['date_debut']);
            $end = new DateTime($project['date_fin']);
            $total_duration = max(1, $start->diff($end)->days);
            $elapsed_duration = max(0, $start->diff($today_dt)->days);
            $time_elapsed_ratio = min($elapsed_duration / $total_duration, 1);
            $progress_ratio = $avg_progress / 100;
            $risk_score += max(0, $time_elapsed_ratio - $progress_ratio) * 30;
        }
        $final_risk_percent = min(100, round($risk_score));
        
        // --- Étape 4: Calculer le rythme requis pour chaque membre ---
        $days_left = 0;
        if (!empty($project['date_fin'])) {
            $end_date_dt = new DateTime($project['date_fin']);
            if ($today_dt <= $end_date_dt) {
                 $period = new DatePeriod($today_dt, new DateInterval('P1D'), (clone $end_date_dt)->modify('+1 day'));
                 foreach ($period as $day) { if ($day->format('N') < 6) $days_left++; }
            }
        }
        
        $response_data = [];
        foreach ($team_analysis as $member_name => $tasks) {
            $tasks_count = count($tasks);
            $pace_suggestion = '';
            if (empty($project['date_fin'])) {
                 $pace_suggestion = "Le projet n'a pas de date de fin définie.";
            } elseif ($days_left <= 0) {
                $pace_suggestion = "<strong>Délai du projet dépassé.</strong> Actions correctives requises.";
            } else {
                $pace = $tasks_count / $days_left;
                if ($pace > 1) $pace_suggestion = "Doit compléter <strong>" . round($pace, 1) . " tâches/jour</strong>.";
                else $pace_suggestion = "Doit compléter <strong>1 tâche tous les " . round(1 / $pace) . " jour(s)</strong>.";
            }
            $response_data[] = ['member_name' => $member_name, 'remaining_tasks_count' => $tasks_count, 'pace_suggestion' => $pace_suggestion, 'tasks' => $tasks];
        }

        echo json_encode([
            'success' => true,
            'project_name' => $project['nom'],
            'project_deadline' => !empty($project['date_fin']) ? date('d/m/Y', strtotime($project['date_fin'])) : 'N/A',
            'prediction_percent' => $final_risk_percent,
            'team_analysis' => $response_data
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Une erreur serveur est survenue: ' . $e->getMessage()]);
    }
    exit();
}

// =================================================================================
// LOGIQUE NORMALE DE LA PAGE
// =================================================================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'chef') {
    header('Location: login.php');
    exit();
}
$chef_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Chef de Projet';

$sql = "
    SELECT p.*,
    (SELECT AVG(t.progression) FROM taches t WHERE t.projet_id = p.id) as project_progress
    FROM projets p
    WHERE p.cree_par = ? OR p.id IN (SELECT pm.projet_id FROM projet_membres pm WHERE pm.utilisateur_id = ?)
    ORDER BY p.derniere_modification DESC, p.date_creation DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$chef_id, $chef_id]);
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_projets = count($projets);
$overdue_tasks = 0;
if ($total_projets > 0) {
    $project_ids = array_column($projets, 'id');
    $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
    $tasks_stmt = $pdo->prepare("SELECT COUNT(*) FROM taches WHERE projet_id IN ($placeholders) AND statut != 'terminé' AND date_fin_estimee < NOW()");
    $tasks_stmt->execute($project_ids);
    $overdue_tasks = $tasks_stmt->fetchColumn();
}

function getProjectStatus($project) {
    if (empty($project['date_fin'])) {
        return ['text' => 'En Cours', 'class' => 'status-on-track'];
    }
    $endDate = strtotime($project['date_fin']);
    $today = time();
    $progress = $project['project_progress'] ?? 0;
    if ($progress >= 100) return ['text' => 'Terminé', 'class' => 'status-completed'];
    if ($endDate < $today && $progress < 100) return ['text' => 'En Retard', 'class' => 'status-delayed'];
    return ['text' => 'En Cours', 'class' => 'status-on-track'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Chef de Projet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-bg: #d46b08; --main-bg: #fdfaf6; --text-light: #fff; --text-dark: #333;
            --accent-color: #a55204; --hover-bg: #a55204; --red: #dc3545; --orange: #fd7e14; --green: #28a745;
        }
        body { margin: 0; font-family: 'Segoe UI', sans-serif; background-color: var(--main-bg); display: flex; }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: var(--text-light); height: 100vh; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 25px; font-size: 1.5em; font-weight: bold; text-align: center; border-bottom: 1px solid #ffffff30; }
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 20px 0; margin: 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 15px; color: var(--text-light); text-decoration: none; padding: 15px 25px; transition: background-color 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--hover-bg); }
        .sidebar-footer { padding: 20px; border-top: 1px solid #ffffff30; }
        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 25px; margin-bottom: 30px; }
        .summary-card, .ai-card { background-color: #fff; padding: 25px; border-radius: 15px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        .ai-card { background: linear-gradient(135deg, #ffcc33, #d46b08); color: white; }
        .ai-card h3, .ai-card p { margin-top: 0; }
        .btn-predict { background-color: #fff; color: var(--sidebar-bg); border:none; padding: 10px 20px; border-radius: 8px; cursor:pointer; font-weight:bold; }
        .table-container { background-color: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.07); }
        .project-table { width: 100%; border-collapse: collapse; }
        .project-table th, .project-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .status-tag { padding: 4px 10px; border-radius: 15px; font-weight: bold; font-size: 0.8em; color: #fff; }
        .status-completed { background-color: #17a2b8; } .status-delayed { background-color: var(--red); } .status-on-track { background-color: var(--green); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(5px); overflow-y: auto; }
        .modal-content { background-color: #fff; margin: 10% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px; animation: slideIn 0.4s; }
        @keyframes slideIn { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display:flex; justify-content: space-between; align-items:center; }
        #prediction-result { margin-top: 20px; display: none; }
        #prediction-result .loader { border: 4px solid #f3f3f3; border-top: 4px solid var(--accent-color); border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 10px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .analysis-header { padding: 15px; background-color: #f8f9fa; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .member-analysis { margin-bottom: 15px; border: 1px solid #eee; border-radius: 8px; padding: 15px; }
        .member-analysis h4 { margin: 0 0 10px 0; border-bottom: 2px solid var(--accent-color); padding-bottom: 5px; }
        .member-analysis .pace-info { font-style: italic; color: #555; margin-bottom: 10px; }
        .task-list-analysis { list-style-type: none; padding-left: 0; }
        .task-list-analysis li { background: #f9f9f9; padding: 8px; border-radius: 4px; margin-bottom: 5px; font-size: 0.9em; display: flex; justify-content: space-between; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header">CHEF DE PROJET</div>
    <nav><ul class="sidebar-nav">
        <li><a href="chef_dashboard.php" class="active"><i class="fas fa-fw fa-tachometer-alt"></i> Tableau de bord</a></li>
        <li><a href="chef_gestionEquipe.php"><i class="fas fa-fw fa-users"></i> Gestion Équipes</a></li>
        <li><a href="historique.php"><i class="fas fa-fw fa-history"></i> Historique</a></li>
    </ul></nav>
    <div class="sidebar-footer">
        <a href="logout.php"><i class="fas fa-fw fa-sign-out-alt"></i> Déconnexion</a>
    </div>
</aside>

<main class="main-content">
    <header class="content-header">
        <h1>Bienvenue, <?php echo htmlspecialchars($user_nom); ?> !</h1>
    </header>

    <div class="dashboard-grid">
        <div class="summary-card">
            <h3>Projets Gérés</h3>
            <p style="font-size: 1.5em; font-weight: bold;"><?php echo $total_projets; ?></p>
        </div>
        <div class="summary-card">
            <h3>Tâches en Retard</h3>
            <p style="font-size: 1.5em; font-weight: bold; color: <?php echo $overdue_tasks > 0 ? 'var(--red)' : 'var(--green)'; ?>"><?php echo $overdue_tasks; ?></p>
        </div>
        <div class="ai-card">
            <h3><i class="fas fa-brain"></i> Assistant d'Analyse</h3>
            <p>Analysez la charge de travail et le rythme requis pour vos projets.</p>
            <button class="btn-predict" id="open-predict-modal-btn">Lancer une Analyse</button>
        </div>
    </div>

    <div class="table-container">
        <h2>Vos Projets</h2>
        <table class="project-table">
            <thead><tr><th>Projet</th><th>Progression</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if(empty($projets)): ?>
                    <tr><td colspan="4" style="text-align:center; padding: 20px;">Vous ne gérez aucun projet pour le moment.</td></tr>
                <?php else: foreach ($projets as $projet): $status = getProjectStatus($projet); ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($projet['nom']); ?></strong></td>
                        <td>
                            <progress value="<?= round($projet['project_progress'] ?? 0) ?>" max="100" style="width:100px;"></progress>
                            <?= round($projet['project_progress'] ?? 0) ?>%
                        </td>
                        <td><span class="status-tag <?php echo $status['class']; ?>"><?php echo $status['text']; ?></span></td>
                        <td><a href="view_project.php?id=<?php echo $projet['id']; ?>">Détails du projet <i class="fas fa-arrow-right"></i></a></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="prediction-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Analyse de la Charge de Travail</h2>
            <span class="close-btn" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div style="margin-top:20px;">
            <label for="project-select">Choisissez un projet à analyser :</label>
            <select id="project-select" style="width:100%; padding:8px; margin-top:5px;">
                <option value="">-- Sélectionner un projet --</option>
                <?php foreach($projets as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['id']); ?>"><?php echo htmlspecialchars($p['nom']); ?></option>
                <?php endforeach; ?>
            </select>
            <button id="get-prediction-btn" style="width:100%; margin-top:15px;" class="btn-predict">Obtenir l'Analyse</button>
        </div>
        <div id="prediction-result"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('prediction-modal');
    const openModalBtn = document.getElementById('open-predict-modal-btn');
    const closeModalBtn = modal.querySelector('.close-btn');
    const getPredictionBtn = document.getElementById('get-prediction-btn');
    const resultDiv = document.getElementById('prediction-result');
    const projectSelect = document.getElementById('project-select');

    function showModal() { modal.style.display = 'block'; }
    function hideModal() { modal.style.display = 'none'; resultDiv.style.display = 'none'; resultDiv.innerHTML = ''; }

    if (openModalBtn) openModalBtn.addEventListener('click', showModal);
    if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
    window.addEventListener('click', (e) => { if(e.target == modal) hideModal(); });

    getPredictionBtn.addEventListener('click', async function() {
        const projectId = projectSelect.value;
        if (!projectId) {
            alert('Veuillez sélectionner un projet.');
            return;
        }

        resultDiv.style.display = 'block';
        resultDiv.innerHTML = '<div class="loader"></div>';

        try {
            const response = await fetch(`?action=predict_delay&project_id=${projectId}`);
            const data = await response.json();

            if (data.success) {
                // [MODIFIÉ] Affichage du risque et des détails
                let riskColor = data.prediction_percent > 65 ? 'var(--red)' : (data.prediction_percent > 40 ? 'var(--orange)' : 'var(--green)');
                let html = `
                    <div class="analysis-header">
                        <h3>Analyse pour : ${data.project_name}</h3>
                        <p>Date de fin : <strong>${data.project_deadline}</strong> | Risque de retard : <strong style="color:${riskColor};">${data.prediction_percent}%</strong></p>
                    </div>
                `;

                if (data.team_analysis.length > 0) {
                    data.team_analysis.forEach(member => {
                        html += `
                            <div class="member-analysis">
                                <h4>${member.member_name} (${member.remaining_tasks_count} tâches restantes)</h4>
                                <p class="pace-info">${member.pace_suggestion}</p>
                                <ul class="task-list-analysis">
                        `;
                        member.tasks.forEach(task => {
                           let deadlineDate = new Date(task.deadline);
                           let isOverdue = deadlineDate < new Date();
                           let deadlineColor = isOverdue ? 'color: var(--red);' : '';
                           html += `<li><span>${task.name}</span><small style="${deadlineColor}">Échéance: ${deadlineDate.toLocaleDateString('fr-FR')}</small></li>`;
                        });
                        html += `
                                </ul>
                            </div>
                        `;
                    });
                } else {
                    html += '<p style="text-align:center;">Aucune tâche non terminée pour ce projet. Le travail est à jour !</p>';
                }
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<p style="color:var(--red); text-align:center;"><strong>Erreur :</strong> ${data.error}</p>`;
            }
        } catch (error) {
            resultDiv.innerHTML = '<p style="color:var(--red); text-align:center;">Erreur de communication avec le serveur.</p>';
        }
    });
});
</script>
</body>
</html>