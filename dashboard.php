<?php
$dashboardScriptVersion = filemtime(__DIR__ . '/assets/js/dashboard.js');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Gestão de Tarefas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a href="index.php" class="navbar-brand mb-0 h1" aria-label="Ir para o início">
            <i class="bi bi-bar-chart-fill me-2"></i>Dashboard
        </a>
        <div class="d-flex gap-2">
            <a href="registro.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-journal-text"></i> Registro
            </a>
            <a href="index.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-kanban-fill"></i> Voltar ao quadro
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">

    <!-- KPIs -->
    <div class="row g-3 mb-4" id="kpis">
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total de tarefas</div>
                    <div class="fs-2 fw-bold" id="kpi-total">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Concluídas</div>
                    <div class="fs-2 fw-bold text-success" id="kpi-concluidas">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Em aberto</div>
                    <div class="fs-2 fw-bold text-primary" id="kpi-abertas">-</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Vencidas</div>
                    <div class="fs-2 fw-bold text-danger" id="kpi-vencidas">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Tarefas por coluna</div>
                <div class="card-body"><canvas id="grafico-colunas" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header">Tarefas por prioridade</div>
                <div class="card-body"><canvas id="grafico-prioridade" height="220"></canvas></div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header">Tarefas concluídas nas últimas 8 semanas</div>
                <div class="card-body"><canvas id="grafico-semanas" height="120"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="assets/js/dashboard.js?v=<?= $dashboardScriptVersion ?>"></script>
</body>
</html>
