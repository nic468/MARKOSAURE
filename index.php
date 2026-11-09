<?php
header('Cache-Control: no-cache, must-revalidate');
$kanbanScriptVersion = filemtime(__DIR__ . '/assets/js/kanban.js');
$kanbanStyleVersion = filemtime(__DIR__ . '/assets/css/style.css');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestão de Tarefas - Kanban</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css?v=<?= $kanbanStyleVersion ?>" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-dark bg-dark mb-3">
    <div class="container-fluid">
        <a href="index.php" class="navbar-brand mb-0 h1" aria-label="Ir para o início">
            <i class="bi bi-kanban-fill me-2"></i>Gestão de Tarefas
        </a>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-bar-chart-fill"></i> Dashboard
            </a>
            <a href="registro.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-journal-text"></i> Registro
            </a>
            <button class="btn btn-outline-light btn-sm" id="btn-nova-coluna">
                <i class="bi bi-plus-lg"></i> Nova coluna
            </button>
            <button class="btn btn-light btn-sm" id="btn-nova-tarefa">
                <i class="bi bi-plus-lg"></i> Nova tarefa
            </button>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <div class="card board-toolbar shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">Quadro visual e organizado</h5>
                    <p class="text-muted mb-0">Arraste cards entre colunas, clique para editar e use os filtros para encontrar rapidamente o que precisa.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border"><i class="bi bi-arrows-move me-1"></i> Arraste para mover</span>
                    <span class="badge text-bg-light border"><i class="bi bi-pencil-square me-1"></i> Clique para editar</span>
                </div>
            </div>

            <div class="row g-2">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" id="filtro-busca" placeholder="Buscar por título...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-prioridade">
                        <option value="">Todas as prioridades</option>
                        <option value="alta">Alta</option>
                        <option value="media">Média</option>
                        <option value="baixa">Baixa</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filtro-etiqueta">
                        <option value="">Todas as etiquetas</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" id="btn-limpar-filtros" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="kanban-workspace">
        <div id="board" class="board d-flex gap-3 pb-4">
            <!-- Colunas geradas via JS -->
            <div class="text-center text-muted w-100 py-5" id="loading-msg">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Carregando quadro...</p>
            </div>
        </div>

        <aside class="card proximas-vencer-card shadow-sm" aria-labelledby="proximas-vencer-titulo">
            <div class="card-body">
                <div class="proximas-vencer-cabecalho mb-3">
                    <div class="d-flex align-items-start gap-2">
                        <span class="proximas-vencer-icone" aria-hidden="true"><i class="bi bi-calendar2-week"></i></span>
                        <div>
                            <h5 class="mb-1" id="proximas-vencer-titulo">Próximas a vencer</h5>
                            <p class="text-muted small mb-0">Acompanhe seus prazos mais próximos.</p>
                        </div>
                    </div>
                    <label class="visually-hidden" for="filtro-proximas-busca">Buscar tarefas próximas a vencer</label>
                    <div class="input-group input-group-sm proximas-vencer-busca mt-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="filtro-proximas-busca"
                               placeholder="Buscar tarefa..." aria-label="Buscar tarefas próximas a vencer">
                    </div>
                </div>
                <div id="lista-proximas-vencer" class="list-group proximas-vencer-lista" aria-live="polite">
                    <div class="text-center text-muted py-3">Carregando tarefas com vencimento...</div>
                </div>
            </div>
        </aside>
    </main>
</div>

<!-- Modal: Nova/Editar Tarefa -->
<div class="modal fade" id="modalTarefa" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <form id="form-tarefa">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTarefaTitulo">Nova Tarefa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="tarefa-id">

          <div class="mb-3">
            <label class="form-label">Título *</label>
            <input type="text" class="form-control" id="tarefa-titulo" maxlength="200" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Descrição</label>
            <textarea class="form-control" id="tarefa-descricao" rows="3"></textarea>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Coluna *</label>
              <select class="form-select" id="tarefa-coluna" required></select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Prioridade</label>
              <select class="form-select" id="tarefa-prioridade">
                <option value="baixa">Baixa</option>
                <option value="media" selected>Média</option>
                <option value="alta">Alta</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Data de vencimento</label>
            <input type="date" class="form-control" id="tarefa-vencimento">
          </div>

          <div class="mb-3">
            <label class="form-label">Recorrência</label>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="tarefa-recorrente">
              <label class="form-check-label" for="tarefa-recorrente">Atividade repetitiva</label>
            </div>
            <select class="form-select form-select-sm" id="tarefa-frequencia">
              <option value="nenhuma">Sem recorrência</option>
              <option value="diaria">Diária</option>
              <option value="semanal">Semanal</option>
              <option value="mensal">Mensal</option>
              <option value="anual">Anual</option>
              <option value="personalizada">Personalizada</option>
            </select>
          </div>

          <fieldset id="tarefa-dias-wrapper" class="mb-3 d-none">
            <legend class="form-label fs-6">Repetir em *</legend>
            <div id="tarefa-dias-semana" class="d-flex flex-wrap gap-2">
              <input class="btn-check" type="checkbox" id="tarefa-dia-0" value="0" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-0">Dom</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-1" value="1" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-1">Seg</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-2" value="2" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-2">Ter</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-3" value="3" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-3">Qua</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-4" value="4" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-4">Qui</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-5" value="5" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-5">Sex</label>
              <input class="btn-check" type="checkbox" id="tarefa-dia-6" value="6" autocomplete="off">
              <label class="btn btn-outline-primary btn-sm" for="tarefa-dia-6">Sáb</label>
            </div>
            <div id="tarefa-dias-erro" class="small text-danger mt-1 d-none" role="alert">
              Selecione pelo menos um dia da semana.
            </div>
          </fieldset>

          <div id="tarefa-horario-wrapper" class="mb-3 d-none">
            <label class="form-label" for="tarefa-horario-recorrencia">Horário da recorrência (opcional)</label>
            <input type="time" class="form-control form-control-sm" id="tarefa-horario-recorrencia">
            <div class="form-text">A próxima tarefa é criada somente quando esta for concluída.</div>
          </div>

          <div class="mb-3">
            <button class="btn btn-light w-100 d-flex justify-content-between align-items-center historico-toggle collapsed"
                type="button" data-bs-toggle="collapse" data-bs-target="#historico-tarefa-collapse"
                aria-expanded="false" aria-controls="historico-tarefa-collapse">
              <span class="fw-semibold"><i class="bi bi-clock-history me-2"></i>Histórico</span>
              <i class="bi bi-chevron-down historico-chevron" aria-hidden="true"></i>
            </button>
            <div class="collapse" id="historico-tarefa-collapse">
              <div id="tarefa-historico" class="border rounded-3 p-3 small bg-light mt-2"></div>
            </div>
          </div>

          <!-- Etiquetas -->
          <div class="mb-3">
            <label class="form-label">Etiquetas</label>
            <div id="tarefa-etiquetas-lista" class="d-flex flex-wrap gap-2 mb-2"></div>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control" id="nova-etiqueta-nome" placeholder="Nova etiqueta...">
              <input type="color" class="form-control form-control-color" id="nova-etiqueta-cor" value="#0d6efd">
              <button type="button" class="btn btn-outline-secondary" id="btn-add-etiqueta">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          </div>

          <!-- Checklist -->
          <div class="mb-2" id="checklist-wrapper">
            <label class="form-label d-flex justify-content-between align-items-center">
              <span>Checklist</span>
              <small class="text-muted" id="checklist-progresso-texto"></small>
            </label>
            <div class="progress mb-2" style="height:6px;" id="checklist-progresso-barra-wrapper">
              <div class="progress-bar bg-success" id="checklist-progresso-barra" style="width:0%"></div>
            </div>
            <ul class="list-group mb-2" id="checklist-itens"></ul>
            <div class="input-group input-group-sm" id="checklist-add-wrapper">
              <input type="text" class="form-control" id="novo-item-checklist" placeholder="Adicionar item...">
              <button type="button" class="btn btn-outline-secondary" id="btn-add-checklist">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
            <small class="text-muted d-none" id="checklist-aviso-salvar">Salve a tarefa antes de adicionar itens ao checklist.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-danger me-auto d-none" id="btn-excluir-tarefa">
            <i class="bi bi-trash"></i> Excluir
          </button>
          <button type="button" class="btn btn-outline-warning d-none" id="btn-cancelar-tarefa">
            <i class="bi bi-x-circle"></i> Cancelar tarefa
          </button>
          <button type="button" class="btn btn-success d-none" id="btn-concluir-tarefa">
            <i class="bi bi-check-circle"></i> Concluir
          </button>
          <button type="button" class="btn btn-secondary" id="btn-fechar-tarefa" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Motivo do cancelamento -->
<div class="modal fade" id="modalCancelamento" tabindex="-1" aria-labelledby="modalCancelamentoTitulo" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-cancelamento">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCancelamentoTitulo">Cancelar tarefa</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Informe por que esta tarefa está sendo cancelada.</p>
          <label class="form-label" for="motivo-cancelamento">Motivo *</label>
          <textarea class="form-control" id="motivo-cancelamento" rows="4" maxlength="1000" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Voltar</button>
          <button type="submit" class="btn btn-warning">Confirmar cancelamento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Nova/Editar Coluna -->
<div class="modal fade" id="modalColuna" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="form-coluna">
        <div class="modal-header">
          <h5 class="modal-title" id="modalColunaTitulo">Nova Coluna</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="coluna-id">
          <div class="mb-3">
            <label class="form-label">Nome *</label>
            <input type="text" class="form-control" id="coluna-nome" maxlength="50" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Cor</label>
            <input type="color" class="form-control form-control-color" id="coluna-cor" value="#6c757d">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary" id="btn-salvar-coluna">Criar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="toast" class="toast align-items-center text-white border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="toast-msg"></div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script async src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/kanban.js?v=<?= $kanbanScriptVersion ?>"></script>
</body>
</html>
