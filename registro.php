<?php
header('Cache-Control: no-cache, must-revalidate');
$registroScriptVersion = filemtime(__DIR__ . '/assets/js/registro.js');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro de Tarefas - Kanban</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a href="index.php" class="navbar-brand mb-0 h1" aria-label="Ir para o início"><i class="bi bi-journal-text me-2"></i>Registro de Tarefas</a>
    <div class="d-flex gap-2">
      <a href="dashboard.php" class="btn btn-outline-light btn-sm"><i class="bi bi-bar-chart-fill"></i> Dashboard</a>
      <a href="index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-kanban-fill"></i> Voltar ao quadro</a>
    </div>
  </div>
</nav>
<main class="container-fluid px-4 pb-5">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Tarefas encerradas</h5>
      <p class="text-muted">Histórico de tarefas concluídas e canceladas.</p>
      <div id="registro-conteudo">
        <div class="text-center text-muted py-4"><div class="spinner-border"></div></div>
      </div>
    </div>
  </div>
</main>
<div class="modal fade" id="modalDetalhesRegistro" tabindex="-1" aria-labelledby="modalDetalhesRegistroTitulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalDetalhesRegistroTitulo">Detalhes da tarefa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body" id="registro-detalhes-conteudo"></div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/registro.js?v=<?= $registroScriptVersion ?>"></script>
</body>
</html>
