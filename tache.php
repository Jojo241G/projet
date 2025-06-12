<?php
// Démarrage de la session et connexion à la BD
session_start();
require_once 'connexion.php';

// =================================================================
// PARTIE 1 : TRAITEMENT DES APPELS API (AJAX)
// =================================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'error' => 'Accès non autorisé.']);
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $action = $_POST['action'];

    function can_user_edit_task($pdo, $user_id, $task_id) {
        $stmt = $pdo->prepare("SELECT 1 FROM taches WHERE id = ? AND assigne_a = ?");
        $stmt->execute([$task_id, $user_id]);
        return $stmt->fetchColumn() !== false;
    }

    try {
        switch ($action) {
            case 'update_task_status':
                $task_id = $_POST['task_id'];
                $new_status = $_POST['new_status'];
                if (!in_array($new_status, ['non commencé', 'en cours', 'terminé'])) {
                    throw new Exception('Statut invalide.');
                }
                if (!can_user_edit_task($pdo, $user_id, $task_id)) {
                    throw new Exception('Permission refusée.');
                }
                
                $progression = null;
                $date_fin_reelle = null;
                if ($new_status === 'terminé') {
                    $progression = 100;
                    $date_fin_reelle = date('Y-m-d H:i:s');
                } elseif ($new_status === 'non commencé') {
                    $progression = 0;
                }

                $sql = "UPDATE taches SET statut = ?, date_fin_reelle = ? " . ($progression !== null ? ", progression = ?" : "") . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $params = [$new_status, $date_fin_reelle];
                if ($progression !== null) $params[] = $progression;
                $params[] = $task_id;
                $stmt->execute($params);

                echo json_encode(['success' => true]);
                break;

            case 'update_task_progress':
                $task_id = $_POST['task_id'];
                $progress = (int)$_POST['progress'];
                if ($progress < 0 || $progress > 100) {
                    throw new Exception('Progression invalide.');
                }
                if (!can_user_edit_task($pdo, $user_id, $task_id)) {
                    throw new Exception('Permission refusée.');
                }
                
                $new_status = 'en cours';
                $date_fin_reelle = null;
                if ($progress == 100) {
                    $new_status = 'terminé';
                    $date_fin_reelle = date('Y-m-d H:i:s');
                } elseif ($progress == 0) {
                    $new_status = 'non commencé';
                }

                $sql = "UPDATE taches SET progression = ?, statut = ?, date_fin_reelle = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$progress, $new_status, $date_fin_reelle, $task_id]);
                
                echo json_encode(['success' => true, 'new_status' => $new_status]);
                break;

            default:
                throw new Exception('Action API non reconnue.');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit();
}

// =================================================================
// PARTIE 2 : LOGIQUE DE CHARGEMENT DE LA PAGE
// =================================================================

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nom = $_SESSION['user_nom'] ?? 'Membre';

$sql_tasks = "
    SELECT t.*, p.nom AS projet_nom, p.date_fin as projet_date_fin
    FROM taches t
    JOIN projets p ON t.projet_id = p.id
    WHERE t.assigne_a = ?
    ORDER BY 
        CASE t.priorite
            WHEN 'haute' THEN 1
            WHEN 'moyenne' THEN 2
            WHEN 'basse' THEN 3
        END, 
        t.date_fin_estimee ASC
";
$stmt = $pdo->prepare($sql_tasks);
$stmt->execute([$user_id]);
$all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tasks_by_status = ['non commencé' => [], 'en cours' => [], 'terminé' => []];

foreach ($all_tasks as $task) {
    if (!empty($task['date_fin_estimee'])) {
        $date = new DateTime($task['date_fin_estimee']);
        $task['date_echeance_formatee'] = $date->format('d/m/Y');
    } else {
        $task['date_echeance_formatee'] = 'Non définie';
    }
    
    // [LOGIQUE DE SYNCHRONISATION] Vérifier si l'échéance de la tâche dépasse celle du projet
    $task['is_desynced'] = false;
    if (!empty($task['date_fin_estimee']) && !empty($task['projet_date_fin'])) {
        if (strtotime($task['date_fin_estimee']) > strtotime($task['projet_date_fin'])) {
            $task['is_desynced'] = true;
        }
    }
    
    $tasks_by_status[$task['statut']][] = $task;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Tâches</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --light-bg: #f4f7f6; --light-card-bg: #ffffff; --light-text: #343a40; --light-border: #e9ecef;
            --light-accent: #5e72e4; --light-header: #32325d; --light-shadow: rgba(0,0,0,0.06);
            --dark-bg: #171923; --dark-card-bg: #2d3748; --dark-text: #e2e8f0; --dark-border: #4a5568;
            --dark-accent: #805ad5; --dark-header: #9f7aea; --dark-shadow: rgba(0,0,0,0.2);
        }
        body { margin: 0; font-family: 'Poppins', 'Segoe UI', sans-serif; background-color: var(--light-bg); color: var(--light-text); transition: background-color 0.3s, color 0.3s; }
        body.dark-mode { background-color: var(--dark-bg); color: var(--dark-text); }
        .main-wrapper { display: flex; }
        .sidebar { width: 260px; background-color: var(--light-card-bg); border-right: 1px solid var(--light-border); height: 100vh; position: fixed; display: flex; flex-direction: column; transition: all 0.3s; box-shadow: 0 0 30px var(--light-shadow); }
        body.dark-mode .sidebar { background-color: var(--dark-card-bg); border-right-color: var(--dark-border); box-shadow: 0 0 30px var(--dark-shadow); }
        .sidebar-header { padding: 25px; text-align: center; border-bottom: 1px solid var(--light-border); }
        body.dark-mode .sidebar-header { border-bottom-color: var(--dark-border); }
        .sidebar-header .logo { width: 40px; margin-bottom: 10px; }
        .sidebar-header h2 { font-size: 1.2em; margin: 0; color: var(--light-header); }
        body.dark-mode .sidebar-header h2 { color: var(--dark-header); }
        .sidebar-nav { flex-grow: 1; list-style: none; padding: 0; margin: 20px 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 15px; text-decoration: none; padding: 15px 25px; margin: 5px 15px; border-radius: 8px; color: #525f7f; transition: all 0.3s; }
        body.dark-mode .sidebar-nav a { color: #a0aec0; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background-color: var(--light-accent); color: white; }
        body.dark-mode .sidebar-nav a:hover, body.dark-mode .sidebar-nav a.active { background-color: var(--dark-accent); }
        .sidebar-footer { padding: 20px; border-top: 1px solid var(--light-border); }
        body.dark-mode .sidebar-footer { border-top-color: var(--dark-border); }
        .user-profile { text-align: center; }
        .user-profile .user-name { font-weight: 600; }
        .user-profile a { color: var(--light-accent); text-decoration: none; font-size: 0.9em; }
        body.dark-mode .user-profile a { color: var(--dark-accent); }

        .main-content { margin-left: 260px; padding: 30px; width: calc(100% - 260px); }
        .content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .content-header h1 { color: var(--light-header); font-size: 2em; }
        body.dark-mode .content-header h1 { color: var(--dark-header); }

        #kanban-board { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .kanban-column { background-color: var(--light-bg); border-radius: 12px; padding: 15px; }
        body.dark-mode .kanban-column { background-color: #2d374850; }
        .kanban-column h2 { font-size: 1.2em; padding-bottom: 10px; margin: 0 0 15px 0; border-bottom: 3px solid; }
        #todo-col h2 { border-color: #ffc107; }
        #inprogress-col h2 { border-color: var(--light-accent); }
        body.dark-mode #inprogress-col h2 { border-color: var(--dark-accent); }
        #done-col h2 { border-color: #28a745; }

        .task-list { min-height: 400px; list-style: none; padding: 0; }
        .task-card { background-color: var(--light-card-bg); border-radius: 8px; box-shadow: 0 4px 15px var(--light-shadow); padding: 15px; margin-bottom: 15px; cursor: grab; transition: all 0.3s; border-left: 5px solid; }
        .task-card:active { cursor: grabbing; box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        body.dark-mode .task-card { background-color: var(--dark-card-bg); box-shadow: none; }
        .task-card.dragging { opacity: 0.5; transform: scale(1.05); }

        .task-card.priority-haute { border-color: #dc3545; }
        .task-card.priority-moyenne { border-color: #ffc107; }
        .task-card.priority-basse { border-color: #17a2b8; }
        /* Style pour les tâches désynchronisées */
        .task-card.desynced { border-color: #fd7e14; background-color: #fffbeb; }
        body.dark-mode .task-card.desynced { background-color: rgba(253, 126, 20, 0.1); }

        .task-header { margin-bottom: 10px; }
        .task-header .project-name { font-size: 0.8em; color: #8898aa; font-weight: 600; background-color: var(--light-border); padding: 3px 8px; border-radius: 10px; display: inline-block; }
        body.dark-mode .task-header .project-name { background-color: var(--dark-border); }
        .task-header h3 { font-size: 1.1em; margin: 5px 0 0 0; }
        .task-description { font-size: 0.9em; color: #525f7f; margin-bottom: 15px; }
        body.dark-mode .task-description { color: #a0aec0; }
        
        .task-footer { font-size: 0.8em; color: #8898aa; }
        .task-footer .deadline { font-weight: 600; }
        .desync-warning { color: #fd7e14; margin-left: 8px; cursor: help; }

        .progress-slider { width: 100%; -webkit-appearance: none; appearance: none; height: 8px; background: var(--light-border); border-radius: 5px; outline: none; margin-top: 10px; }
        .progress-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; background: var(--light-accent); cursor: pointer; border-radius: 50%; }
        .progress-slider::-moz-range-thumb { width: 18px; height: 18px; background: var(--light-accent); cursor: pointer; border-radius: 50%; border: none; }
        body.dark-mode .progress-slider::-webkit-slider-thumb, body.dark-mode .progress-slider::-moz-range-thumb { background: var(--dark-accent); }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-header"><h2>Tableau de Bord</h2></div>
    <nav>
        <ul class="sidebar-nav">
            <li><a href="user_dashboard.php"><i class="fas fa-fw fa-home"></i> Mes Projets</a></li>
            <li><a href="calendrier.php"><i class="fas fa-fw fa-calendar-alt"></i> Mon Calendrier</a></li>
            <li><a href="#" class="active"><i class="fas fa-fw fa-check-square"></i> Mes Tâches</a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <div class="user-profile">
            <i class="fas fa-user"></i>
            <span class="user-name"><?php echo htmlspecialchars($user_nom); ?></span><br>
            <a href="login.php?action=logout">Déconnexion</a>
        </div>
    </div>
</aside>

<main class="main-content">
    <header class="content-header"><h1>Mes Tâches</h1></header>
    
    <div id="kanban-board">
        <div class="kanban-column" id="todo-col">
            <h2><i class="fas fa-list-ul"></i> À Faire</h2>
            <ul class="task-list" data-status="non commencé"></ul>
        </div>
        <div class="kanban-column" id="inprogress-col">
            <h2><i class="fas fa-sync-alt fa-spin"></i> En Cours</h2>
            <ul class="task-list" data-status="en cours"></ul>
        </div>
        <div class="kanban-column" id="done-col">
            <h2><i class="fas fa-check-double"></i> Terminé</h2>
            <ul class="task-list" data-status="terminé"></ul>
        </div>
    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const initialTasks = <?php echo json_encode($tasks_by_status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    const taskLists = {
        'non commencé': document.querySelector('#todo-col .task-list'),
        'en cours': document.querySelector('#inprogress-col .task-list'),
        'terminé': document.querySelector('#done-col .task-list')
    };

    function createTaskCard(task) {
        const card = document.createElement('li');
        // Ajout de la classe 'desynced' si la tâche est désynchronisée
        card.className = `task-card priority-${task.priorite} ${task.is_desynced ? 'desynced' : ''}`;
        card.draggable = true;
        card.dataset.taskId = task.id;
        card.dataset.status = task.statut;

        // Création de l'icône d'avertissement si nécessaire
        const desyncIcon = task.is_desynced 
            ? `<i class="fas fa-exclamation-triangle desync-warning" title="Attention: L'échéance de cette tâche dépasse celle du projet !"></i>`
            : '';

        card.innerHTML = `
            <div class="task-header">
                <span class="project-name">${task.projet_nom}</span>
                <h3>${task.nom}</h3>
            </div>
            <p class="task-description">${task.description || ''}</p>
            <input type="range" class="progress-slider" value="${task.progression}" min="0" max="100" step="5" ${task.statut === 'terminé' ? 'disabled' : ''}>
            <div class="task-footer">
                <span class="deadline">
                    <i class="fas fa-flag-checkered"></i> Échéance : ${task.date_echeance_formatee}
                    ${desyncIcon}
                </span>
            </div>
        `;
        return card;
    }

    function renderBoard() {
        Object.values(taskLists).forEach(list => list.innerHTML = '');
        for (const status in initialTasks) {
            if (taskLists[status]) {
                initialTasks[status].forEach(task => {
                    taskLists[status].appendChild(createTaskCard(task));
                });
            }
        }
    }

    async function apiCall(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        for(const key in data) {
            formData.append(key, data[key]);
        }
        try {
            const response = await fetch('', { method: 'POST', body: formData });
            return await response.json();
        } catch (error) {
            console.error('API Call Error:', error);
            return { success: false, error: 'Erreur de communication.' };
        }
    }

    let draggedTask = null;

    document.querySelectorAll('.task-list').forEach(list => {
        list.addEventListener('dragstart', (e) => {
            if (e.target.classList.contains('task-card')) {
                draggedTask = e.target;
                setTimeout(() => e.target.classList.add('dragging'), 0);
            }
        });
        list.addEventListener('dragend', () => {
            if (draggedTask) {
                draggedTask.classList.remove('dragging');
                draggedTask = null;
            }
        });
        list.addEventListener('dragover', (e) => e.preventDefault());
        list.addEventListener('drop', async (e) => {
            e.preventDefault();
            if (draggedTask) {
                const targetList = e.target.closest('.task-list');
                if (targetList && targetList !== draggedTask.parentElement) {
                    const newStatus = targetList.dataset.status;
                    const taskId = draggedTask.dataset.taskId;
                    const oldStatus = draggedTask.dataset.status;
                    const oldList = taskLists[oldStatus];

                    targetList.appendChild(draggedTask);
                    draggedTask.dataset.status = newStatus;

                    const result = await apiCall('update_task_status', { task_id: taskId, new_status: newStatus });
                    if (!result.success) {
                        alert(result.error);
                        // Annuler le déplacement en cas d'erreur
                        oldList.appendChild(draggedTask);
                        draggedTask.dataset.status = oldStatus;
                    } else {
                        // Mettre à jour la carte pour refléter le nouveau statut (ex: désactiver le slider si 'terminé')
                        const newCard = createTaskCard({...initialTasks[oldStatus].find(t => t.id == taskId), statut: newStatus, progression: newStatus === 'terminé' ? 100 : (newStatus === 'non commencé' ? 0 : draggedTask.querySelector('.progress-slider').value)});
                        targetList.replaceChild(newCard, draggedTask);
                    }
                }
            }
        });
    });

    document.getElementById('kanban-board').addEventListener('change', async (e) => {
        if (e.target.classList.contains('progress-slider')) {
            const card = e.target.closest('.task-card');
            const taskId = card.dataset.taskId;
            const progress = e.target.value;
            
            const result = await apiCall('update_task_progress', { task_id: taskId, progress: progress });
            
            if (result.success && result.new_status && result.new_status !== card.dataset.status) {
                const taskData = Object.values(initialTasks).flat().find(t => t.id == taskId);
                const updatedTask = {...taskData, statut: result.new_status, progression: progress};
                
                // Remplacer l'ancienne carte par la nouvelle dans la bonne colonne
                card.remove();
                taskLists[result.new_status].appendChild(createTaskCard(updatedTask));
            }
        }
    });

    renderBoard();
});
</script>

</body>
</html>