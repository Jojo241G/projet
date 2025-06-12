<?php
session_start();
require_once 'connexion.php';

// =================================================================
// BLOC API : GÈRE LES ACTIONS AJAX DE LA PAGE
// =================================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Accès non autorisé.']);
        exit();
    }

    try {
        switch ($_POST['action']) {
            case 'toggle_ia_status':
                $new_status = $_POST['status'] === 'true' ? '1' : '0';
                $sql = "INSERT INTO parametres_app (cle, valeur) VALUES ('ia_prediction_active', ?) ON CONFLICT (cle) DO UPDATE SET valeur = EXCLUDED.valeur";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$new_status]);
                echo json_encode(['success' => true]);
                break;

            case 'get_ia_predictions':
                $predictions = [];
                $risk_counts = ['high' => 0, 'moderate' => 0, 'low' => 0];

                // [RISQUE 1 HAUT] Tâche critique en retard
                $stmt1 = $pdo->query("SELECT p.nom as project_name, t.nom as task_name, u.nom as user_name FROM taches t JOIN projets p ON t.projet_id = p.id JOIN users u ON t.assigne_a = u.id WHERE t.priorite = 'haute' AND t.statut != 'terminé' AND t.date_fin_estimee < NOW() LIMIT 1");
                if ($row = $stmt1->fetch(PDO::FETCH_ASSOC)) {
                    $predictions[] = ['risk_level' => 'high', 'title' => 'Tâche Critique en Retard', 'cause' => "La tâche \"{$row['task_name']}\" (Projet: {$row['project_name']}) a dépassé son échéance.", 'suggestion' => 'Contacter ' . $row['user_name'] . ' pour identifier le blocage.'];
                    $risk_counts['high']++;
                }

                // [RISQUE 2 HAUT] Projet dépassé avec tâches actives
                $stmt2 = $pdo->query("SELECT p.nom as project_name, COUNT(t.id) as active_tasks FROM projets p JOIN taches t ON p.id = t.projet_id WHERE p.date_fin < NOW() AND t.statut != 'terminé' GROUP BY p.id, p.nom ORDER BY p.date_fin DESC LIMIT 1");
                if ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $predictions[] = ['risk_level' => 'high', 'title' => 'Projet en Dépassement', 'cause' => "Le projet \"{$row['project_name']}\" est terminé mais contient encore {$row['active_tasks']} tâches actives.", 'suggestion' => 'Clore les tâches restantes ou ajuster la date de fin du projet.'];
                    $risk_counts['high']++;
                }
                
                // [RISQUE 3 MODÉRÉ] Utilisateur surchargé
                $stmt3 = $pdo->query("SELECT u.nom, COUNT(t.id) as task_count FROM users u JOIN taches t ON u.id = t.assigne_a WHERE t.statut != 'terminé' GROUP BY u.id, u.nom HAVING COUNT(t.id) > 5 ORDER BY task_count DESC LIMIT 1");
                if ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
                    $predictions[] = ['risk_level' => 'moderate', 'title' => 'Surcharge de Travail', 'cause' => "{$row['nom']} est assigné à {$row['task_count']} tâches actives, risquant un goulot d'étranglement.", 'suggestion' => 'Envisager de réattribuer certaines tâches pour équilibrer la charge.'];
                    $risk_counts['moderate']++;
                }

                // [RISQUE 4 MODÉRÉ] Incohérence de données
                $stmt4 = $pdo->query("SELECT COUNT(*) FROM taches WHERE statut = 'terminé' AND date_fin_reelle IS NULL");
                if ($count = $stmt4->fetchColumn() > 0) {
                     $predictions[] = ['risk_level' => 'moderate', 'title' => 'Incohérence de Données', 'cause' => "{$count} tâche(s) sont marquées comme 'terminé' mais n'ont pas de date de fin réelle enregistrée.", 'suggestion' => 'Lancer un script de maintenance pour corriger ces entrées et assurer la fiabilité des rapports.'];
                     $risk_counts['moderate']++;
                }

                // [RISQUE 5 FAIBLE] Tâches critiques non assignées
                $stmt5 = $pdo->query("SELECT COUNT(*) FROM taches WHERE priorite = 'haute' AND assigne_a IS NULL AND statut != 'terminé'");
                if ($count = $stmt5->fetchColumn() > 0) {
                    $predictions[] = ['risk_level' => 'low', 'title' => 'Tâche Critique Orpheline', 'cause' => "{$count} tâche(s) à haute priorité ne sont assignées à personne.", 'suggestion' => 'Passer en revue les tâches non assignées et les attribuer rapidement.'];
                    $risk_counts['low']++;
                }

                echo json_encode(['success' => true, 'predictions' => $predictions, 'summary' => ['total' => count($predictions), 'distribution' => $risk_counts]]);
                break;
            
            // [NOUVEAU] Action pour le rapport de performance
            case 'get_performance_report':
                $stmt = $pdo->query("
                    SELECT t.nom as task_name, p.nom as project_name, u.nom as user_name, t.date_debut, t.date_fin_estimee, t.date_fin_reelle
                    FROM taches t
                    JOIN projets p ON t.projet_id = p.id
                    JOIN users u ON t.assigne_a = u.id
                    WHERE t.statut = 'terminé' AND t.date_fin_reelle IS NOT NULL AND t.date_debut IS NOT NULL AND t.date_fin_estimee IS NOT NULL
                    ORDER BY t.date_fin_reelle DESC
                    LIMIT 10
                ");
                $completed_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $report = [];
                foreach($completed_tasks as $task) {
                    $start = new DateTime($task['date_debut']);
                    $estimated_end = new DateTime($task['date_fin_estimee']);
                    $real_end = new DateTime($task['date_fin_reelle']);
                    
                    $estimated_duration = $start->diff($estimated_end)->days;
                    $real_duration = $start->diff($real_end)->days;
                    
                    $report[] = [
                        'task_name' => $task['task_name'],
                        'project_name' => $task['project_name'],
                        'user_name' => $task['user_name'],
                        'performance' => $estimated_duration - $real_duration // Positif = en avance, Négatif = en retard
                    ];
                }
                echo json_encode(['success' => true, 'report' => $report]);
                break;
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erreur de base de données.']);
    }
    exit();
}

// LOGIQUE DE CHARGEMENT INITIAL DE LA PAGE
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$stmt = $pdo->prepare("SELECT valeur FROM parametres_app WHERE cle = 'ia_prediction_active'");
$stmt->execute();
$is_ia_active = $stmt->fetchColumn() === '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centre de Contrôle IA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        :root {
            --bg-color: #0d1117; --panel-color: #161b22; --border-color: #30363d;
            --text-primary: #c9d1d9; --text-secondary: #8b949e;
            --accent-glow: #39d39f; --accent-glow-transparent: rgba(57, 211, 159, 0.1);
            --risk-high: #f85149; --risk-moderate: #f59e0b; --risk-low: #3fb950;
        }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bg-color); color: var(--text-primary); margin: 0; display: flex; }
        .sidebar { width: 250px; background-color: var(--panel-color); border-right: 1px solid var(--border-color); height: 100vh; position: fixed; display: flex; flex-direction: column; }
        .sidebar-header { padding: 1.5em; text-align: center; border-bottom: 1px solid var(--border-color); }
        .sidebar-header h2 { font-size: 1.2em; color: var(--accent-glow); margin: 0; letter-spacing: 1px; }
        .sidebar-nav { flex-grow: 1; padding: 1em 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 0.8em; padding: 0.8em 1.5em; text-decoration: none; color: var(--text-secondary); transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-nav a:hover { color: var(--text-primary); background-color: var(--accent-glow-transparent); }
        .sidebar-nav a.active { color: var(--accent-glow); border-left-color: var(--accent-glow); }
        
        .main-content { margin-left: 250px; padding: 2em; width: calc(100% - 250px); }
        .header h1 { font-size: 2em; font-weight: 600; margin-bottom: 1em; }

        .card { background: rgba(22, 27, 34, 0.7); border: 1px solid var(--border-color); padding: 1.5em; border-radius: 12px; backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
        
        .tabs { display: flex; gap: 0.5em; border-bottom: 1px solid var(--border-color); margin-bottom: 2em; }
        .tab-btn { background: none; border: none; color: var(--text-secondary); padding: 1em 1.5em; cursor: pointer; font-size: 1em; font-weight: 500; position: relative; }
        .tab-btn::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background-color: var(--accent-glow); transform: scaleX(0); transition: transform 0.3s ease; }
        .tab-btn.active { color: var(--accent-glow); }
        .tab-btn.active::after { transform: scaleX(1); }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5em; margin-bottom: 2em; }
        .card h3 { margin: 0 0 1em 0; font-weight: 500; color: var(--text-secondary); }
        .summary-value { font-size: 2.5em; font-weight: 700; color: var(--text-primary); }
        .chart-container { position: relative; height:120px; }

        .prediction-card { padding: 1.5em; border-radius: 12px; margin-bottom: 1em; border-left: 4px solid; }
        .prediction-card.risk-high { background-color: rgba(248, 81, 73, 0.1); border-color: var(--risk-high); }
        .prediction-card.risk-moderate { background-color: rgba(245, 158, 11, 0.1); border-color: var(--risk-moderate); }
        .prediction-card.risk-low { background-color: rgba(63, 185, 80, 0.1); border-color: var(--risk-low); }
        .prediction-card h4 { margin: 0 0 0.8em 0; font-size: 1.1em; display:flex; align-items:center; gap: 0.6em; font-weight: 600; }
        .prediction-card.risk-high h4 { color: var(--risk-high); }
        .prediction-card.risk-moderate h4 { color: var(--risk-moderate); }
        .prediction-card.risk-low h4 { color: var(--risk-low); }
        .prediction-card p { font-size: 0.9em; line-height: 1.6; margin: 0.3em 0; color: var(--text-secondary); }
        .prediction-card .label { font-weight: 600; color: var(--text-primary); }

        .performance-table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        .performance-table th, .performance-table td { padding: 0.8em 1em; text-align: left; border-bottom: 1px solid var(--border-color); }
        .performance-table th { font-weight: 500; color: var(--text-secondary); }
        .perf-indicator { font-weight: 700; }
        .perf-indicator.positive { color: var(--risk-low); }
        .perf-indicator.negative { color: var(--risk-high); }

        .btn { background-color: var(--accent-glow); color: #0d1117; border: none; padding: 0.7em 1.2em; border-radius: 8px; cursor: pointer; font-size: 0.9em; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.5em; }
        .btn:hover { filter: brightness(1.2); }
        .btn:disabled { background-color: #30363d; color: var(--text-secondary); cursor: not-allowed; }
        
        .loader, #no-risks-message, #no-report-message { text-align: center; padding: 3em; color: var(--text-secondary); font-size: 1.1em; }
        .loader .fa-spinner { font-size: 2em; color: var(--accent-glow); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header"><h2>PANEL ADMIN</h2></div>
    <nav class="sidebar-nav">
        <a href="create_user.php"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a>
        <a href="#" class="active"><i class="fas fa-robot fa-fw"></i> Contrôle IA</a>
    </nav>
</aside>

<main class="main-content">
    <header class="header"><h1><i class="fas fa-brain" style="color:var(--accent-glow)"></i> Centre de Contrôle IA</h1></header>

    <div class="card">
        <div class="tabs">
            <button class="tab-btn active" data-tab="predictive"><i class="fas fa-eye"></i> Analyse Prédictive</button>
            <button class="tab-btn" data-tab="retrospective"><i class="fas fa-history"></i> Analyse Rétrospective</button>
        </div>

        <div id="predictive" class="tab-content active">
            <div class="dashboard-grid">
                <div class="card">
                    <h3>Statut du Module</h3>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding: 1em 0;">
                        <p id="ia-status-text" style="margin:0; font-weight:600;"><?= $is_ia_active ? 'Actif' : 'Inactif'; ?></p>
                        <label class="toggle-switch">
                            <input type="checkbox" id="iaToggle" <?= $is_ia_active ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
                <div class="card">
                    <h3>Risques Détectés</h3>
                    <p class="summary-value" id="total-risks-summary">--</p>
                </div>
                <div class="card">
                    <h3>Répartition</h3>
                    <div class="chart-container"><canvas id="riskChart"></canvas></div>
                </div>
            </div>
            <div id="predictions-container">
                <button class="btn" id="updateBtn" <?= !$is_ia_active ? 'disabled' : ''; ?>><i class="fas fa-sync-alt"></i> Forcer l'analyse prédictive</button>
                <div class="loader" style="display:none;"><i class="fas fa-spinner fa-spin"></i></div>
                <div id="predictions-grid" style="margin-top: 1.5em;"></div>
                <div id="no-risks-message" style="display:none;"><i class="fas fa-shield-alt" style="color: var(--risk-low); font-size: 1.5em; margin-bottom: 0.5em;"></i><br>Le système est stable. Aucune anomalie critique détectée.</div>
            </div>
        </div>

        <div id="retrospective" class="tab-content">
            <h3>Rapport de Performance (10 dernières tâches terminées)</h3>
            <button class="btn" id="generateReportBtn"><i class="fas fa-file-alt"></i> Générer le Rapport</button>
            <div class="loader" style="display:none;"><i class="fas fa-spinner fa-spin"></i></div>
            <div id="report-container" style="margin-top: 1.5em;"></div>
             <div id="no-report-message" style="display:none;"><i class="fas fa-info-circle"></i><br>Aucune donnée de performance disponible pour le moment.</div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Éléments du DOM
    const iaToggle = document.getElementById('iaToggle');
    const updateBtn = document.getElementById('updateBtn');
    const generateReportBtn = document.getElementById('generateReportBtn');
    const tabs = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    // Éléments d'affichage
    const iaStatusText = document.getElementById('ia-status-text');
    const predictionsGrid = document.getElementById('predictions-grid');
    const noRisksMessage = document.getElementById('no-risks-message');
    const totalRisksSummary = document.getElementById('total-risks-summary');
    const reportContainer = document.getElementById('report-container');
    const noReportMessage = document.getElementById('no-report-message');
    let riskChart;

    // --- Fonctions Utilitaires ---
    async function apiCall(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        for(const key in data) formData.append(key, data[key]);
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Erreur réseau');
            const result = await response.json();
            if (!result.success) throw new Error(result.error || 'Erreur inconnue');
            return result;
        } catch (error) {
            alert(`Erreur: ${error.message}`);
            return null;
        }
    }

    function showLoader(container, show = true) {
        const loader = container.querySelector('.loader');
        if (loader) loader.style.display = show ? 'block' : 'none';
    }

    // --- Logique des Onglets ---
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            tabContents.forEach(content => {
                content.classList.remove('active');
                if(content.id === tab.dataset.tab) {
                    content.classList.add('active');
                }
            });
        });
    });

    // --- Logique de l'Analyse Prédictive ---
    function setPredictivePanelVisibility(visible) {
        updateBtn.disabled = !visible;
        iaStatusText.textContent = visible ? 'Actif' : 'Inactif';
        if(visible) {
            fetchAndRenderPredictions();
        } else {
            predictionsGrid.innerHTML = '';
            noRisksMessage.style.display = 'block';
            totalRisksSummary.textContent = 'N/A';
            if(riskChart) riskChart.destroy();
        }
    }

    function renderRiskChart(distribution) {
        const ctx = document.getElementById('riskChart').getContext('2d');
        if(riskChart) riskChart.destroy();
        riskChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Élevé', 'Modéré', 'Faible'],
                datasets: [{
                    data: [distribution.high, distribution.moderate, distribution.low],
                    backgroundColor: ['#f85149', '#f59e0b', '#3fb950'],
                    borderColor: '#161b22', borderWidth: 3, hoverOffset: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '70%',
                plugins: { legend: { display: false } }
            }
        });
    }

    function renderPredictions(data) {
        predictionsGrid.innerHTML = '';
        totalRisksSummary.textContent = data.summary.total;
        
        if (data.predictions.length === 0) {
            noRisksMessage.style.display = 'block';
            predictionsGrid.style.display = 'none';
        } else {
            noRisksMessage.style.display = 'none';
            predictionsGrid.style.display = 'grid';
            const icons = { high: 'fa-fire-alt', moderate: 'fa-exclamation-triangle', low: 'fa-info-circle' };
            data.predictions.forEach(pred => {
                predictionsGrid.innerHTML += `
                    <div class="prediction-card risk-${pred.risk_level}">
                        <h4><i class="fas ${icons[pred.risk_level]}"></i> ${pred.title}</h4>
                        <p><span class="label">Cause:</span> ${pred.cause}</p>
                        <p><span class="label">Suggestion:</span> ${pred.suggestion}</p>
                    </div>`;
            });
        }
        renderRiskChart(data.summary.distribution);
    }

    async function fetchAndRenderPredictions() {
        showLoader(document.getElementById('predictive'));
        predictionsGrid.style.display = 'none';
        noRisksMessage.style.display = 'none';
        
        const result = await apiCall('get_ia_predictions');
        
        showLoader(document.getElementById('predictive'), false);
        if (result && result.success) {
            renderPredictions(result);
        } else {
            predictionsGrid.innerHTML = '<p style="color: var(--risk-high);">Erreur de chargement.</p>';
        }
    }

    // --- Logique de l'Analyse Rétrospective ---
    async function fetchAndRenderPerformance() {
        showLoader(document.getElementById('retrospective'));
        reportContainer.innerHTML = '';
        noReportMessage.style.display = 'none';

        const result = await apiCall('get_performance_report');
        
        showLoader(document.getElementById('retrospective'), false);
        if(result && result.success) {
            if(result.report.length === 0) {
                noReportMessage.style.display = 'block';
                return;
            }
            let tableHTML = '<table class="performance-table"><thead><tr><th>Tâche</th><th>Membre</th><th>Performance</th></tr></thead><tbody>';
            result.report.forEach(item => {
                let perfClass = item.performance > 0 ? 'positive' : (item.performance < 0 ? 'negative' : '');
                let perfText = item.performance > 0 ? `+${item.performance} j` : `${item.performance} j`;
                if(item.performance === 0) perfText = 'À temps';

                tableHTML += `
                    <tr>
                        <td><strong>${item.task_name}</strong><br><small>${item.project_name}</small></td>
                        <td>${item.user_name}</td>
                        <td class="perf-indicator ${perfClass}">${perfText}</td>
                    </tr>
                `;
            });
            tableHTML += '</tbody></table>';
            reportContainer.innerHTML = tableHTML;
        } else {
             reportContainer.innerHTML = '<p style="color: var(--risk-high);">Erreur de chargement du rapport.</p>';
        }
    }

    // --- Écouteurs d'Événements ---
    iaToggle.addEventListener('change', async function() {
        await apiCall('toggle_ia_status', { status: this.checked });
        setPredictivePanelVisibility(this.checked);
    });

    updateBtn.addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Analyse...';
        fetchAndRenderPredictions().finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-sync-alt"></i> Analyser';
        });
    });
    
    generateReportBtn.addEventListener('click', function() {
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> Génération...';
        fetchAndRenderPerformance().finally(() => {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-file-alt"></i> Générer le Rapport';
        });
    });

    // --- Initialisation ---
    if(iaToggle.checked) {
        fetchAndRenderPredictions();
    }
});
</script>

</body>
</html>