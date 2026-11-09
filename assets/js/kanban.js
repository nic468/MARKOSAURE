// ============================================================
// Kanban - lógica de front-end
// ============================================================

const API_TAREFAS   = 'api/tasks.php';
const API_COLUNAS   = 'api/columns.php';
const API_SUBTASKS  = 'api/subtasks.php';
const API_TAGS      = 'api/tags.php';
const API_ANEXOS_CHECKLIST = 'api/checklist_attachments.php';

let colunas = [];
let tarefas = [];
let todasEtiquetas = [];
let etiquetasDaTarefaAtual = []; // ids das etiquetas já vinculadas à tarefa aberta no modal
let tarefaArrastada = null;

const filtros = { busca: '', prioridade: '', etiqueta: '' };

function controlarModal(elementoId, acao) {
    const Modal = window.bootstrap?.Modal;
    if (!Modal) {
        if (acao === 'show') {
            mostrarToast('Não foi possível abrir a janela. Verifique a conexão e recarregue a página.', 'danger');
        }
        return;
    }

    Modal.getOrCreateInstance(document.getElementById(elementoId))[acao]();
}

async function buscarDados(url, tempoLimite = 15000) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), tempoLimite);

    try {
        const resposta = await fetch(url, { signal: controller.signal });
        return await resposta.json();
    } catch (erro) {
        if (erro.name === 'AbortError') {
            throw new Error('A solicitação demorou demais. Verifique o servidor e tente novamente.');
        }
        throw erro;
    } finally {
        clearTimeout(timeoutId);
    }
}

// ------------------------------------------------------------
// Inicialização
// ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', carregarQuadro);

document.getElementById('btn-nova-tarefa').addEventListener('click', () => abrirModalTarefa());
document.getElementById('btn-nova-coluna').addEventListener('click', () => abrirModalColuna());

document.getElementById('form-tarefa').addEventListener('submit', salvarTarefa);
document.getElementById('form-coluna').addEventListener('submit', salvarColuna);
document.getElementById('btn-excluir-tarefa').addEventListener('click', excluirTarefa);
document.getElementById('btn-cancelar-tarefa').addEventListener('click', () => {
    document.getElementById('form-cancelamento').reset();
    controlarModal('modalCancelamento', 'show');
});
document.getElementById('form-cancelamento').addEventListener('submit', cancelarTarefa);
document.getElementById('btn-concluir-tarefa').addEventListener('click', concluirTarefa);

document.getElementById('btn-add-checklist').addEventListener('click', adicionarItemChecklist);
document.getElementById('novo-item-checklist').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); adicionarItemChecklist(); }
});

document.getElementById('btn-add-etiqueta').addEventListener('click', criarEAssociarEtiqueta);
document.getElementById('nova-etiqueta-nome').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); criarEAssociarEtiqueta(); }
});

document.getElementById('filtro-busca').addEventListener('input', e => {
    filtros.busca = e.target.value.toLowerCase();
    renderizarQuadro();
});
document.getElementById('filtro-prioridade').addEventListener('change', e => {
    filtros.prioridade = e.target.value;
    renderizarQuadro();
});
document.getElementById('filtro-etiqueta').addEventListener('change', e => {
    filtros.etiqueta = e.target.value;
    renderizarQuadro();
});
document.getElementById('btn-limpar-filtros').addEventListener('click', () => {
    filtros.busca = ''; filtros.prioridade = ''; filtros.etiqueta = '';
    document.getElementById('filtro-busca').value = '';
    document.getElementById('filtro-prioridade').value = '';
    document.getElementById('filtro-etiqueta').value = '';
    renderizarQuadro();
});

document.getElementById('filtro-proximas-busca').addEventListener('input', renderizarProximasAVencer);

document.getElementById('tarefa-recorrente').addEventListener('change', atualizarEstadoRecorrencia);
document.getElementById('tarefa-frequencia').addEventListener('change', atualizarEstadoRecorrencia);
document.querySelectorAll('#tarefa-dias-semana input[type="checkbox"]').forEach(input => {
    input.addEventListener('change', () => {
        document.getElementById('tarefa-dias-erro').classList.add('d-none');
    });
});

// ------------------------------------------------------------
// Carregamento de dados
// ------------------------------------------------------------
async function carregarQuadro() {
    try {
        const [respColunas, respTarefas, respEtiquetas] = await Promise.all([
            buscarDados(API_COLUNAS),
            buscarDados(API_TAREFAS),
            buscarDados(API_TAGS),
        ]);

        if (!respColunas.sucesso || !respTarefas.sucesso) {
            throw new Error('Falha ao carregar dados do servidor.');
        }

        colunas = respColunas.colunas;
        tarefas = respTarefas.tarefas.filter(tarefaVisivelNoQuadro);
        todasEtiquetas = respEtiquetas.sucesso ? respEtiquetas.etiquetas : [];

        renderizarQuadro();
        renderizarProximasAVencer();
        preencherSelectColunas();
        preencherFiltroEtiquetas();
    } catch (erro) {
        document.getElementById('lista-proximas-vencer').textContent =
            'Não foi possível carregar as tarefas com vencimento. Recarregue a página para tentar novamente.';
        document.getElementById('board').innerHTML = `
            <div class="alert alert-danger w-100">
                Não foi possível carregar o quadro. Verifique se o banco de dados
                <code>kanban_tasks</code> foi criado/migrado (arquivos <code>database.sql</code> /
                <code>migration_checklist_etiquetas.sql</code>) e se o MySQL está rodando.<br>
                <small>${erro.message}</small>
            </div>`;
    }
}

function preencherFiltroEtiquetas() {
    const select = document.getElementById('filtro-etiqueta');
    const atual = select.value;
    select.innerHTML = '<option value="">Todas as etiquetas</option>' +
        todasEtiquetas.map(e => `<option value="${e.id}">${escapeHtml(e.nome)}</option>`).join('');
    select.value = atual;
}

// ------------------------------------------------------------
// Renderização do quadro
// ------------------------------------------------------------
function tarefaPassaNosFiltros(tarefa) {
    if (filtros.busca && !tarefa.titulo.toLowerCase().includes(filtros.busca)) return false;
    if (filtros.prioridade && tarefa.prioridade !== filtros.prioridade) return false;
    if (filtros.etiqueta) {
        const ids = (tarefa.etiquetas_ids || '').split(',').filter(Boolean);
        if (!ids.includes(String(filtros.etiqueta))) return false;
    }
    return true;
}

function tarefaVisivelNoQuadro(tarefa) {
    return !['cancelada', 'concluida'].includes(tarefa.status);
}

function renderizarQuadro() {
    const board = document.getElementById('board');
    board.innerHTML = '';

    const temFiltrosAtivos = Boolean(filtros.busca || filtros.prioridade || filtros.etiqueta);

    if (!colunas.length) {
        board.innerHTML = `
            <div class="empty-board-state">
                <div>
                    <h5 class="fw-semibold">Comece criando seu primeiro quadro</h5>
                    <p class="text-muted mb-3">Adicione uma coluna para organizar suas atividades.</p>
                    <button class="btn btn-primary" onclick="document.getElementById('btn-nova-coluna').click()">
                        <i class="bi bi-plus-lg me-1"></i> Criar coluna
                    </button>
                </div>
            </div>`;
        return;
    }

    if (temFiltrosAtivos && tarefas.filter(tarefaPassaNosFiltros).length === 0) {
        board.innerHTML = `
            <div class="empty-board-state">
                <div>
                    <h5 class="fw-semibold">Nenhuma tarefa encontrada</h5>
                    <p class="text-muted mb-3">Ajuste os filtros ou limpe a busca para ver mais atividades.</p>
                    <button class="btn btn-outline-primary" onclick="document.getElementById('btn-limpar-filtros').click()">
                        <i class="bi bi-funnel me-1"></i> Limpar filtros
                    </button>
                </div>
            </div>`;
        return;
    }

    colunas.forEach(coluna => {
        const tarefasColuna = tarefas
            .filter(t => Number(t.coluna_id) === Number(coluna.id))
            .filter(tarefaPassaNosFiltros)
            .sort((a, b) => a.ordem - b.ordem);

        const totalNaColuna = tarefas.filter(t => Number(t.coluna_id) === Number(coluna.id)).length;

        const colunaEl = document.createElement('div');
        colunaEl.className = 'coluna';
        colunaEl.dataset.colunaId = coluna.id;

        colunaEl.innerHTML = `
            <div class="coluna-header" style="background:${coluna.cor}">
                <span>${escapeHtml(coluna.nome)}</span>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-light btn-editar-coluna" title="Editar coluna">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light btn-excluir-coluna" title="Excluir coluna">
                        <i class="bi bi-trash"></i>
                    </button>
                    <span class="badge-contagem">${tarefasColuna.length}${tarefasColuna.length !== totalNaColuna ? ` / ${totalNaColuna}` : ''}</span>
                </div>
            </div>
            <div class="coluna-body" data-coluna-id="${coluna.id}"></div>
        `;

        const body = colunaEl.querySelector('.coluna-body');
        const btnEditar = colunaEl.querySelector('.btn-editar-coluna');
        const btnExcluir = colunaEl.querySelector('.btn-excluir-coluna');
        btnEditar?.addEventListener('click', e => {
            e.stopPropagation();
            abrirModalColuna(coluna);
        });
        btnExcluir?.addEventListener('click', e => {
            e.stopPropagation();
            excluirColuna(coluna.id);
        });

        if (tarefasColuna.length === 0) {
            body.innerHTML = '<div class="empty-column-state">Nenhuma tarefa aqui.</div>';
        } else {
            tarefasColuna.forEach(tarefa => body.appendChild(criarCardTarefa(tarefa)));
        }

        configurarDropZone(body);
        board.appendChild(colunaEl);
    });

    const addColBtn = document.createElement('div');
    addColBtn.className = 'btn-add-coluna d-flex align-items-start pt-2';
    addColBtn.innerHTML = `
        <button class="btn btn-outline-secondary w-100" onclick="document.getElementById('btn-nova-coluna').click()">
            <i class="bi bi-plus-lg"></i> Adicionar coluna
        </button>`;
    board.appendChild(addColBtn);
}

function renderizarProximasAVencer() {
    const lista = document.getElementById('lista-proximas-vencer');
    const busca = document.getElementById('filtro-proximas-busca').value.trim().toLocaleLowerCase('pt-BR');
    const hoje = obterDataHojeSaoPaulo();
    const tarefasComVencimento = tarefas
        .filter(tarefa => tarefaVisivelNoQuadro(tarefa) && dataCalendarioValida(tarefa.data_vencimento))
        .filter(tarefa => !busca || String(tarefa.titulo || '').toLocaleLowerCase('pt-BR').includes(busca))
        .sort((a, b) => String(a.data_vencimento).slice(0, 10).localeCompare(String(b.data_vencimento).slice(0, 10))
            || Number(a.ordem || 0) - Number(b.ordem || 0)
            || Number(a.id) - Number(b.id));

    lista.replaceChildren();
    if (!tarefasComVencimento.length) {
        const vazio = document.createElement('div');
        vazio.className = 'text-center text-muted py-3';
        vazio.textContent = busca
            ? 'Nenhuma tarefa com vencimento corresponde à busca.'
            : 'Não há tarefas abertas com data de vencimento.';
        lista.appendChild(vazio);
        return;
    }

    tarefasComVencimento.forEach(tarefa => {
        const dataVencimento = String(tarefa.data_vencimento).slice(0, 10);
        const diasRestantes = diasEntreDatas(hoje, dataVencimento);
        const urgencia = diasRestantes < 0
            ? { texto: 'Atrasada', classe: 'text-bg-danger' }
            : diasRestantes === 0
                ? { texto: 'Vence hoje', classe: 'text-bg-warning' }
                : { texto: `Vence em ${diasRestantes} ${diasRestantes === 1 ? 'dia' : 'dias'}`, classe: 'text-bg-primary' };
        const coluna = colunas.find(item => Number(item.id) === Number(tarefa.coluna_id));
        const horarioRecorrencia = tarefa.recorrente && tarefa.horario_recorrencia
            ? `<span><i class="bi bi-arrow-repeat me-1"></i>Horário recorrente: ${escapeHtml(String(tarefa.horario_recorrencia).slice(0, 5))}</span>`
            : '';
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'list-group-item list-group-item-action proximas-vencer-item text-start';
        item.innerHTML = `
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <span class="fw-semibold">${escapeHtml(tarefa.titulo)}</span>
                <span class="badge ${urgencia.classe}">${urgencia.texto}</span>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3 mt-2 small text-muted">
                <span><i class="bi bi-columns-gap me-1"></i>${escapeHtml(coluna?.nome || 'Sem coluna')}</span>
                <span class="badge text-bg-light border">${escapeHtml(rotuloPrioridade(tarefa.prioridade))}</span>
                <span><i class="bi bi-calendar-event me-1"></i>Vencimento: ${formatarData(dataVencimento)}</span>
                ${horarioRecorrencia}
            </div>`;
        item.addEventListener('click', () => abrirModalTarefa(tarefa));
        lista.appendChild(item);
    });
}

function criarCardTarefa(tarefa) {
    const card = document.createElement('div');
    card.className = `card-tarefa prioridade-${tarefa.prioridade}`;
    card.draggable = true;
    card.dataset.tarefaId = tarefa.id;

    const vencido = classificarVencimento(tarefa.data_vencimento) === 'atrasada';
    const dataFormatada = tarefa.data_vencimento ? formatarData(tarefa.data_vencimento) : '';

    const idsEtiquetas = (tarefa.etiquetas_ids || '').split(',').filter(Boolean).map(Number);
    const etiquetasTarefa = todasEtiquetas.filter(e => idsEtiquetas.includes(e.id));
    const tagsHtml = etiquetasTarefa.map(e =>
        `<span class="badge rounded-pill" style="background:${e.cor}">${escapeHtml(e.nome)}</span>`
    ).join(' ');

    const totalSub = Number(tarefa.total_subtarefas || 0);
    const feitasSub = Number(tarefa.subtarefas_concluidas || 0);
    const progressoHtml = totalSub > 0 ? `
        <div class="checklist-mini mt-2">
            <div class="progress" style="height:5px;">
                <div class="progress-bar bg-success" style="width:${Math.round((feitasSub / totalSub) * 100)}%"></div>
            </div>
            <small class="text-muted"><i class="bi bi-check2-square"></i> ${feitasSub}/${totalSub}</small>
        </div>` : '';

    const recorrenteHtml = tarefa.recorrente ? '<span class="badge text-bg-info"><i class="bi bi-arrow-repeat"></i> Recorrente</span>' : '';

    const headerHtml = `
        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            ${tagsHtml ? `<div class="d-flex flex-wrap gap-1 flex-grow-1">${tagsHtml}</div>` : '<div class="flex-grow-1"></div>'}
            <button type="button" class="btn btn-link btn-sm p-0 text-secondary btn-editar-tarefa" title="Editar tarefa">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>`;

    card.innerHTML = `
        ${headerHtml}
        <div class="titulo">${escapeHtml(tarefa.titulo)}</div>
        ${tarefa.descricao ? `<div class="descricao">${escapeHtml(truncar(tarefa.descricao, 90))}</div>` : ''}
        <div class="rodape">
            <span class="badge text-bg-light border">${rotuloPrioridade(tarefa.prioridade)}</span>
            ${recorrenteHtml}
            ${dataFormatada ? `<span class="vencimento ${vencido ? 'vencido' : ''}"><i class="bi bi-calendar-event"></i> ${dataFormatada}</span>` : '<span></span>'}
        </div>
        ${progressoHtml}
    `;

    card.querySelector('.btn-editar-tarefa').addEventListener('click', e => {
        e.stopPropagation();
        abrirModalTarefa(tarefa);
    });

    card.addEventListener('click', () => abrirModalTarefa(tarefa));

    card.addEventListener('dragstart', () => {
        tarefaArrastada = tarefa;
        card.classList.add('dragging');
    });
    card.addEventListener('dragend', () => card.classList.remove('dragging'));

    return card;
}

function configurarDropZone(body) {
    body.addEventListener('dragover', e => {
        e.preventDefault();
        body.classList.add('drag-over');

        const afterElement = elementoAposPosicao(body, e.clientY);
        const dragging = document.querySelector('.dragging');
        if (!dragging) return;

        if (afterElement == null) {
            body.appendChild(dragging);
        } else {
            body.insertBefore(dragging, afterElement);
        }
    });

    body.addEventListener('dragleave', () => body.classList.remove('drag-over'));

    body.addEventListener('drop', async e => {
        e.preventDefault();
        body.classList.remove('drag-over');
        if (!tarefaArrastada) return;

        const novaColunaId = Number(body.dataset.colunaId);
        const idsNaOrdem = [...body.querySelectorAll('.card-tarefa')].map(c => Number(c.dataset.tarefaId));
        const novaOrdem = idsNaOrdem.indexOf(tarefaArrastada.id) + 1;

        await moverTarefa(tarefaArrastada.id, novaColunaId, novaOrdem);
        tarefaArrastada = null;
        await carregarQuadro();
    });
}

function elementoAposPosicao(container, y) {
    const els = [...container.querySelectorAll('.card-tarefa:not(.dragging)')];
    return els.reduce((maisProximo, el) => {
        const box = el.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        if (offset < 0 && offset > maisProximo.offset) {
            return { offset, element: el };
        }
        return maisProximo;
    }, { offset: -Infinity, element: null }).element;
}

// ------------------------------------------------------------
// Ações: Tarefas
// ------------------------------------------------------------
async function abrirModalTarefa(tarefa = null) {
    const form = document.getElementById('form-tarefa');
    form.reset();
    document.getElementById('tarefa-dias-erro').classList.add('d-none');
    window.bootstrap?.Collapse.getOrCreateInstance(
        document.getElementById('historico-tarefa-collapse'),
        { toggle: false }
    ).hide();

    document.getElementById('tarefa-id').value = tarefa?.id ?? '';
    document.getElementById('tarefa-titulo').value = tarefa?.titulo ?? '';
    document.getElementById('tarefa-descricao').value = tarefa?.descricao ?? '';
    document.getElementById('tarefa-prioridade').value = tarefa?.prioridade ?? 'media';
    document.getElementById('tarefa-vencimento').value = tarefa?.data_vencimento ?? '';
    document.getElementById('tarefa-coluna').value = tarefa?.coluna_id ?? (colunas[0]?.id ?? '');
    document.getElementById('tarefa-recorrente').checked = Boolean(tarefa?.recorrente);
    document.getElementById('tarefa-frequencia').value = tarefa?.frequencia ?? 'nenhuma';
    const diasSelecionados = String(tarefa?.dias_semana ?? '').split(',').filter(Boolean);
    document.getElementById('tarefa-horario-recorrencia').value = String(tarefa?.horario_recorrencia ?? '').slice(0, 5);
    document.querySelectorAll('#tarefa-dias-semana input[type="checkbox"]').forEach(input => {
        input.checked = diasSelecionados.includes(input.value);
    });
    document.getElementById('tarefa-historico').innerHTML = '<small class="text-muted">Carregando histórico...</small>';
    atualizarEstadoRecorrencia();

    document.getElementById('modalTarefaTitulo').textContent = tarefa ? 'Editar Tarefa' : 'Nova Tarefa';
    document.getElementById('btn-excluir-tarefa').classList.toggle('d-none', !tarefa);
    document.getElementById('btn-cancelar-tarefa').classList.toggle('d-none', !tarefa || ['cancelada', 'concluida'].includes(tarefa.status));
    document.getElementById('btn-concluir-tarefa').classList.toggle('d-none', !tarefa || ['cancelada', 'concluida'].includes(tarefa.status));
    document.getElementById('btn-fechar-tarefa').classList.toggle('d-none', !!tarefa);

    const checklistWrapper = document.getElementById('checklist-add-wrapper');
    const checklistAviso = document.getElementById('checklist-aviso-salvar');
    checklistWrapper.classList.toggle('d-none', !tarefa);
    checklistAviso.classList.toggle('d-none', !!tarefa);
    document.getElementById('checklist-itens').innerHTML = '';
    document.getElementById('checklist-progresso-texto').textContent = '';
    document.getElementById('checklist-progresso-barra').style.width = '0%';

    document.getElementById('tarefa-etiquetas-lista').innerHTML = '';
    etiquetasDaTarefaAtual = [];

    controlarModal('modalTarefa', 'show');

    if (tarefa) {
        await Promise.all([carregarChecklist(tarefa.id), carregarEtiquetasDaTarefa(tarefa.id), carregarHistoricoTarefa(tarefa.id)]);
    }
}

function atualizarEstadoRecorrencia() {
    const recorrente = document.getElementById('tarefa-recorrente').checked;
    const frequencia = document.getElementById('tarefa-frequencia');
    frequencia.disabled = !recorrente;
    const personalizada = recorrente && frequencia.value === 'personalizada';
    document.getElementById('tarefa-dias-wrapper').classList.toggle('d-none', !personalizada);
    document.getElementById('tarefa-horario-wrapper').classList.toggle('d-none', !recorrente);
    if (!personalizada) document.getElementById('tarefa-dias-erro').classList.add('d-none');
}

function preencherSelectColunas() {
    const select = document.getElementById('tarefa-coluna');
    select.innerHTML = colunas.map(c => `<option value="${c.id}">${escapeHtml(c.nome)}</option>`).join('');
}

async function salvarTarefa(e) {
    e.preventDefault();

    if (!validarDiasRecorrencia()) return;

    const id = document.getElementById('tarefa-id').value;
    const payload = obterDadosTarefaFormulario();

    try {
        let resp;
        if (id) {
            payload.id = Number(id);
            resp = await fetch(API_TAREFAS, { method: 'PUT', body: JSON.stringify(payload) });
            const data = await resp.json();
            if (!data.sucesso) throw new Error(data.erro || 'Erro ao salvar tarefa.');

            await moverTarefa(Number(id), payload.coluna_id, 999);
            controlarModal('modalTarefa', 'hide');
            mostrarToast('Tarefa atualizada!', 'success');
            await carregarQuadro();
        } else {
            resp = await fetch(API_TAREFAS, { method: 'POST', body: JSON.stringify(payload) });
            const data = await resp.json();
            if (!data.sucesso) throw new Error(data.erro || 'Erro ao salvar tarefa.');

            await moverTarefa(data.id, payload.coluna_id, 999);
            controlarModal('modalTarefa', 'hide');
            mostrarToast('Tarefa criada!', 'success');
            await carregarQuadro();
        }
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

function obterDadosTarefaFormulario() {
    const recorrente = document.getElementById('tarefa-recorrente').checked;
    const frequencia = recorrente ? document.getElementById('tarefa-frequencia').value : 'nenhuma';
    return {
        titulo: document.getElementById('tarefa-titulo').value.trim(),
        descricao: document.getElementById('tarefa-descricao').value.trim(),
        prioridade: document.getElementById('tarefa-prioridade').value,
        data_vencimento: document.getElementById('tarefa-vencimento').value || null,
        coluna_id: Number(document.getElementById('tarefa-coluna').value),
        recorrente,
        frequencia,
        dias_semana: frequencia === 'personalizada'
            ? [...document.querySelectorAll('#tarefa-dias-semana input[type="checkbox"]:checked')].map(input => Number(input.value))
            : [],
        horario_recorrencia: recorrente
            ? document.getElementById('tarefa-horario-recorrencia').value || null
            : null,
    };
}

function validarDiasRecorrencia() {
    const personalizada = document.getElementById('tarefa-recorrente').checked
        && document.getElementById('tarefa-frequencia').value === 'personalizada';
    const diasSelecionados = document.querySelectorAll('#tarefa-dias-semana input[type="checkbox"]:checked').length;
    const erro = document.getElementById('tarefa-dias-erro');
    erro.classList.toggle('d-none', !personalizada || diasSelecionados > 0);

    if (personalizada && diasSelecionados === 0) {
        mostrarToast('Selecione pelo menos um dia da semana para a recorrência personalizada.', 'warning');
        return false;
    }

    return true;
}

async function concluirTarefa() {
    const form = document.getElementById('form-tarefa');
    if (!form.reportValidity()) return;
    if (!validarDiasRecorrencia()) return;

    const id = document.getElementById('tarefa-id').value;
    if (!id) return;

    try {
        const resp = await fetch(API_TAREFAS, {
            method: 'PUT',
            body: JSON.stringify({ ...obterDadosTarefaFormulario(), id: Number(id), acao: 'concluir' }),
        });
        const data = await resp.json();
        if (!data.sucesso) throw new Error(data.erro || 'Erro ao concluir tarefa.');

        controlarModal('modalTarefa', 'hide');
        mostrarToast('Tarefa concluída e registrada.', 'success');
        await carregarQuadro();
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

async function cancelarTarefa(e) {
    e.preventDefault();

    const form = document.getElementById('form-cancelamento');
    if (!form.reportValidity()) return;

    const id = document.getElementById('tarefa-id').value;
    const motivo = document.getElementById('motivo-cancelamento').value.trim();
    if (!id || !motivo) return;

    try {
        const resp = await fetch(API_TAREFAS, {
            method: 'PUT',
            body: JSON.stringify({ id: Number(id), acao: 'cancelar', motivo_cancelamento: motivo }),
        });
        const data = await resp.json();
        if (!data.sucesso) throw new Error(data.erro || 'Erro ao cancelar tarefa.');

        controlarModal('modalCancelamento', 'hide');
        controlarModal('modalTarefa', 'hide');
        mostrarToast('Tarefa cancelada e registrada.', 'success');
        await carregarQuadro();
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

async function excluirTarefa() {
    const id = document.getElementById('tarefa-id').value;
    if (!id) return;
    if (!confirm('Tem certeza que deseja excluir esta tarefa?')) return;

    try {
        const resp = await fetch(`${API_TAREFAS}?id=${id}`, { method: 'DELETE' });
        const data = await resp.json();
        if (!data.sucesso) throw new Error(data.erro || 'Erro ao excluir.');

        controlarModal('modalTarefa', 'hide');
        mostrarToast(data.aviso || 'Tarefa excluída.', data.aviso ? 'warning' : 'success');
        await carregarQuadro();
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

async function moverTarefa(id, colunaId, ordem) {
    const resp = await fetch(API_TAREFAS, {
        method: 'PUT',
        body: JSON.stringify({ id, mover: true, coluna_id: colunaId, ordem }),
    });
    const data = await resp.json();
    if (!data.sucesso) throw new Error(data.erro || 'Erro ao mover tarefa.');
}

// ------------------------------------------------------------
// Ações: Checklist (subtarefas)
// ------------------------------------------------------------
async function carregarChecklist(tarefaId) {
    const resp = await fetch(`${API_SUBTASKS}?tarefa_id=${tarefaId}`).then(r => r.json());
    if (!resp.sucesso) return;
    renderizarChecklist(resp.subtarefas);
}

async function carregarHistoricoTarefa(tarefaId) {
    const resp = await fetch(`${API_TAREFAS}?historico_tarefa_id=${tarefaId}`).then(r => r.json());
    if (!resp.sucesso) return;
    renderizarHistorico(resp.historico || []);
}

function renderizarHistorico(itens) {
    const container = document.getElementById('tarefa-historico');
    if (!itens.length) {
        container.innerHTML = '<small class="text-muted">Ainda não há registros para esta tarefa.</small>';
        return;
    }

    container.innerHTML = itens.map(item => {
        const data = item.created_at ? new Date(item.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '';
        return `<div class="border-start border-2 ps-2 mb-2"><div class="fw-semibold">${escapeHtml(item.tipo)}</div><div>${escapeHtml(item.mensagem)}</div><small class="text-muted">${escapeHtml(data)}</small></div>`;
    }).join('');
}

function renderizarChecklist(itens) {
    const lista = document.getElementById('checklist-itens');
    lista.innerHTML = '';

    itens.forEach(item => {
        const li = document.createElement('li');
        li.className = 'list-group-item py-2';
        li.innerHTML = `
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input mt-0" ${item.concluida == 1 ? 'checked' : ''}>
                <span class="flex-grow-1 ${item.concluida == 1 ? 'text-decoration-line-through text-muted' : ''}">${escapeHtml(item.titulo)}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-action="remover-subtarefa" aria-label="Remover item do checklist"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="anexos-checklist mt-2 ms-4">
                <div class="small text-muted">Carregando anexos...</div>
            </div>
        `;

        li.querySelector('.form-check-input').addEventListener('change', async e => {
            await fetch(API_SUBTASKS, {
                method: 'PUT',
                body: JSON.stringify({ id: item.id, concluida: e.target.checked }),
            });
            await carregarChecklist(item.tarefa_id);
            await atualizarTarefasEmMemoria();
        });

        li.querySelector('[data-action="remover-subtarefa"]').addEventListener('click', async () => {
            try {
                const resp = await fetch(`${API_SUBTASKS}?id=${item.id}`, { method: 'DELETE' });
                const resultado = await resp.json();
                if (!resultado.sucesso) throw new Error(resultado.erro || 'Não foi possível remover o item do checklist.');
                await carregarChecklist(item.tarefa_id);
                await atualizarTarefasEmMemoria();
                if (resultado.aviso) mostrarToast(resultado.aviso, 'warning');
            } catch (erro) {
                mostrarToast(erro.message, 'danger');
            }
        });

        lista.appendChild(li);
        carregarAnexosChecklist(item.id, li.querySelector('.anexos-checklist'));
    });

    const total = itens.length;
    const feitas = itens.filter(i => i.concluida == 1).length;
    const pct = total > 0 ? Math.round((feitas / total) * 100) : 0;

    document.getElementById('checklist-progresso-texto').textContent = total > 0 ? `${feitas}/${total}` : '';
    document.getElementById('checklist-progresso-barra').style.width = `${pct}%`;
}

async function carregarAnexosChecklist(subtarefaId, container) {
    try {
        const resp = await fetch(`${API_ANEXOS_CHECKLIST}?subtarefa_id=${subtarefaId}`).then(r => r.json());
        if (!resp.sucesso) throw new Error(resp.erro || 'Não foi possível carregar os anexos.');

        const lista = resp.anexos.length
            ? `<ul class="list-unstyled mb-2">${resp.anexos.map(anexo => `
                <li class="small d-flex align-items-center gap-2">
                    <i class="bi bi-paperclip me-1"></i>
                    <a class="flex-grow-1" href="${API_ANEXOS_CHECKLIST}?download=${Number(anexo.id)}">${escapeHtml(anexo.nome_original)}</a>
                    <span class="text-muted">(${formatarTamanhoArquivo(anexo.tamanho)})</span>
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" data-anexo-id="${Number(anexo.id)}" aria-label="Remover anexo">
                        <i class="bi bi-trash"></i>
                    </button>
                </li>`).join('')}</ul>`
            : '';

        container.innerHTML = `
            ${lista}
            <label class="btn btn-sm btn-outline-secondary mb-0">
                <i class="bi bi-paperclip"></i> Anexar documento
                <input type="file" class="d-none" multiple
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.png,.jpg,.jpeg,.txt,.csv">
            </label>
            <small class="text-muted ms-2">PDF, Office, PNG/JPG, TXT ou CSV · até 10 MB por arquivo</small>`;

        container.querySelector('input[type="file"]').addEventListener('change', event => {
            enviarAnexosChecklist(subtarefaId, event.currentTarget, container);
        });
        container.querySelectorAll('[data-anexo-id]').forEach(botao => {
            botao.addEventListener('click', async () => {
                if (!confirm('Remover este documento do checklist?')) return;
                try {
                    const resposta = await fetch(`${API_ANEXOS_CHECKLIST}?id=${botao.dataset.anexoId}`, { method: 'DELETE' });
                    const resultado = await resposta.json();
                    if (!resultado.sucesso) throw new Error(resultado.erro || 'Não foi possível remover o anexo.');
                    await carregarAnexosChecklist(subtarefaId, container);
                    mostrarToast(resultado.aviso || 'Documento removido do checklist.', resultado.aviso ? 'warning' : 'success');
                } catch (erro) {
                    mostrarToast(erro.message, 'danger');
                }
            });
        });
    } catch (erro) {
        container.innerHTML = `<small class="text-danger">${escapeHtml(erro.message)}</small>`;
    }
}

async function enviarAnexosChecklist(subtarefaId, input, container) {
    const arquivos = Array.from(input.files || []);
    if (!arquivos.length) return;

    input.disabled = true;
    try {
        for (const arquivo of arquivos) {
            const formulario = new FormData();
            formulario.append('subtarefa_id', String(subtarefaId));
            formulario.append('arquivo', arquivo);

            const resp = await fetch(API_ANEXOS_CHECKLIST, { method: 'POST', body: formulario });
            const resultado = await resp.json();
            if (!resultado.sucesso) throw new Error(resultado.erro || `Não foi possível anexar ${arquivo.name}.`);
        }

        mostrarToast(arquivos.length === 1 ? 'Documento anexado ao checklist.' : 'Documentos anexados ao checklist.', 'success');
        await carregarAnexosChecklist(subtarefaId, container);
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
        await carregarAnexosChecklist(subtarefaId, container);
    } finally {
        input.value = '';
        input.disabled = false;
    }
}

function formatarTamanhoArquivo(tamanho) {
    const bytes = Number(tamanho);
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function adicionarItemChecklist() {
    const tarefaId = document.getElementById('tarefa-id').value;
    const input = document.getElementById('novo-item-checklist');
    const titulo = input.value.trim();

    if (!tarefaId) {
        mostrarToast('Salve a tarefa antes de adicionar itens ao checklist.', 'warning');
        return;
    }
    if (!titulo) return;

    await fetch(API_SUBTASKS, {
        method: 'POST',
        body: JSON.stringify({ tarefa_id: Number(tarefaId), titulo }),
    });

    input.value = '';
    await carregarChecklist(tarefaId);
    await atualizarTarefasEmMemoria();
}

// ------------------------------------------------------------
// Ações: Etiquetas
// ------------------------------------------------------------
async function carregarEtiquetasDaTarefa(tarefaId) {
    const resp = await fetch(`${API_TAGS}?tarefa_id=${tarefaId}`).then(r => r.json());
    if (!resp.sucesso) return;
    etiquetasDaTarefaAtual = resp.etiquetas.map(e => e.id);
    renderizarEtiquetasNoModal();
}

function renderizarEtiquetasNoModal() {
    const container = document.getElementById('tarefa-etiquetas-lista');
    const tarefaId = document.getElementById('tarefa-id').value;

    if (todasEtiquetas.length === 0) {
        container.innerHTML = '<small class="text-muted">Nenhuma etiqueta cadastrada ainda.</small>';
        return;
    }

    container.innerHTML = todasEtiquetas.map(e => {
        const ativa = etiquetasDaTarefaAtual.includes(e.id);
        return `
            <div class="etiqueta-item d-inline-flex align-items-center gap-1">
                <button type="button"
                    class="badge rounded-pill border-0 etiqueta-toggle"
                    data-id="${Number(e.id)}"
                    style="background:${ativa ? corEtiquetaCss(e.cor) : '#e9ecef'}; color:${ativa ? '#fff' : '#495057'}; cursor:pointer;">
                    ${ativa ? '<i class="bi bi-check-lg"></i> ' : ''}${escapeHtml(e.nome)}
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-editar-etiqueta"
                    data-id="${Number(e.id)}" aria-label="Editar etiqueta" title="Editar etiqueta">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-etiqueta"
                    data-id="${Number(e.id)}" aria-label="Excluir etiqueta" title="Excluir etiqueta">
                    <i class="bi bi-trash"></i>
                </button>
            </div>`;
    }).join('');

    container.querySelectorAll('.etiqueta-toggle').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!tarefaId) {
                mostrarToast('Salve a tarefa antes de aplicar etiquetas.', 'warning');
                return;
            }
            const etiquetaId = Number(btn.dataset.id);
            const jaAtiva = etiquetasDaTarefaAtual.includes(etiquetaId);

            try {
                const resposta = await fetch(API_TAGS, {
                    method: 'POST',
                    body: JSON.stringify({ tarefa_id: Number(tarefaId), etiqueta_id: etiquetaId, associar: !jaAtiva }),
                }).then(r => r.json());
                if (!resposta.sucesso) throw new Error(resposta.erro || 'Não foi possível atualizar a etiqueta da tarefa.');

                await carregarEtiquetasDaTarefa(tarefaId);
                await atualizarTarefasEmMemoria();
            } catch (erro) {
                mostrarToast(erro.message, 'danger');
            }
        });
    });

    container.querySelectorAll('.btn-editar-etiqueta').forEach(btn => {
        btn.addEventListener('click', () => iniciarEdicaoEtiqueta(
            todasEtiquetas.find(etiqueta => Number(etiqueta.id) === Number(btn.dataset.id)),
            btn.closest('.etiqueta-item')
        ));
    });

    container.querySelectorAll('.btn-excluir-etiqueta').forEach(btn => {
        btn.addEventListener('click', () => excluirEtiqueta(
            todasEtiquetas.find(etiqueta => Number(etiqueta.id) === Number(btn.dataset.id))
        ));
    });
}

async function excluirEtiqueta(etiqueta) {
    if (!etiqueta) return;
    if (!confirm(`Excluir a etiqueta "${etiqueta.nome}"? Ela será removida de todas as tarefas.`)) return;

    try {
        const resposta = await fetch(`${API_TAGS}?id=${encodeURIComponent(etiqueta.id)}`, { method: 'DELETE' })
            .then(r => r.json());
        if (!resposta.sucesso) throw new Error(resposta.erro || 'Não foi possível excluir a etiqueta.');

        todasEtiquetas = todasEtiquetas.filter(item => Number(item.id) !== Number(etiqueta.id));
        etiquetasDaTarefaAtual = etiquetasDaTarefaAtual.filter(id => Number(id) !== Number(etiqueta.id));
        if (Number(filtros.etiqueta) === Number(etiqueta.id)) {
            filtros.etiqueta = '';
            document.getElementById('filtro-etiqueta').value = '';
        }

        preencherFiltroEtiquetas();
        renderizarEtiquetasNoModal();
        renderizarQuadro();
        renderizarProximasAVencer();
        mostrarToast('Etiqueta excluída e removida de todas as tarefas.', 'success');
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

function iniciarEdicaoEtiqueta(etiqueta, container) {
    if (!etiqueta || !container) return;

    container.innerHTML = `
        <div class="form-editar-etiqueta d-flex flex-wrap align-items-center gap-1">
            <input type="text" class="form-control form-control-sm" name="nome" maxlength="40"
                aria-label="Nome da etiqueta" required value="${escapeAttribute(etiqueta.nome)}">
            <input type="color" class="form-control form-control-color form-control-sm" name="cor"
                aria-label="Cor da etiqueta" value="${corEtiquetaCss(etiqueta.cor)}">
            <button type="button" class="btn btn-sm btn-success btn-salvar-edicao-etiqueta" aria-label="Salvar etiqueta" title="Salvar">
                <i class="bi bi-check-lg"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary btn-cancelar-edicao-etiqueta"
                aria-label="Cancelar edição" title="Cancelar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>`;

    const editor = container.querySelector('.form-editar-etiqueta');
    const salvarEdicao = async () => {
        const nomeInput = editor.querySelector('[name="nome"]');
        const nome = nomeInput.value.trim();
        if (!nome) {
            nomeInput.focus();
            return;
        }

        const salvar = editor.querySelector('.btn-salvar-edicao-etiqueta');
        salvar.disabled = true;
        try {
            const resposta = await fetch(API_TAGS, {
                method: 'PUT',
                body: JSON.stringify({
                    id: Number(etiqueta.id),
                    nome,
                    cor: editor.querySelector('[name="cor"]').value,
                }),
            }).then(r => r.json());
            if (!resposta.sucesso) throw new Error(resposta.erro || 'Não foi possível editar a etiqueta.');

            const listaAtualizada = await fetch(API_TAGS).then(r => r.json());
            if (!listaAtualizada.sucesso) throw new Error(listaAtualizada.erro || 'Não foi possível recarregar as etiquetas.');
            todasEtiquetas = listaAtualizada.etiquetas;
            preencherFiltroEtiquetas();
            renderizarEtiquetasNoModal();
            renderizarQuadro();
            renderizarProximasAVencer();
            mostrarToast('Etiqueta atualizada.', 'success');
        } catch (erro) {
            salvar.disabled = false;
            mostrarToast(erro.message, 'danger');
        }
    };

    editor.querySelector('.btn-salvar-edicao-etiqueta').addEventListener('click', salvarEdicao);
    editor.querySelector('[name="nome"]').addEventListener('keydown', evento => {
        if (evento.key === 'Enter') {
            evento.preventDefault();
            salvarEdicao();
        }
    });
    container.querySelector('.btn-cancelar-edicao-etiqueta').addEventListener('click', renderizarEtiquetasNoModal);
    editor.querySelector('[name="nome"]').focus();
}

async function criarEAssociarEtiqueta() {
    const nomeInput = document.getElementById('nova-etiqueta-nome');
    const corInput = document.getElementById('nova-etiqueta-cor');
    const nome = nomeInput.value.trim();
    const tarefaId = document.getElementById('tarefa-id').value;

    if (!nome) return;

    try {
        const resp = await fetch(API_TAGS, {
            method: 'POST',
            body: JSON.stringify({ nome, cor: corInput.value }),
        }).then(r => r.json());

        if (!resp.sucesso) throw new Error(resp.erro || 'Erro ao criar etiqueta.');

        nomeInput.value = '';

        const listaAtualizada = await fetch(API_TAGS).then(r => r.json());
        if (listaAtualizada.sucesso) {
            todasEtiquetas = listaAtualizada.etiquetas;
            preencherFiltroEtiquetas();
        }

        if (tarefaId) {
            await fetch(API_TAGS, {
                method: 'POST',
                body: JSON.stringify({ tarefa_id: Number(tarefaId), etiqueta_id: resp.id, associar: true }),
            });
            await carregarEtiquetasDaTarefa(tarefaId);
            await atualizarTarefasEmMemoria();
        } else {
            renderizarEtiquetasNoModal();
        }
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

// Recarrega apenas a lista de tarefas (sem fechar o modal), para refletir
// progresso de checklist / etiquetas nos cards em tempo real.
async function atualizarTarefasEmMemoria() {
    const resp = await fetch(API_TAREFAS).then(r => r.json());
    if (resp.sucesso) {
        tarefas = resp.tarefas.filter(tarefaVisivelNoQuadro);
        renderizarQuadro();
        renderizarProximasAVencer();
    }
}

// ------------------------------------------------------------
// Ações: Colunas
// ------------------------------------------------------------
function abrirModalColuna(coluna = null) {
    const form = document.getElementById('form-coluna');
    form.reset();

    document.getElementById('coluna-id').value = coluna?.id ?? '';
    document.getElementById('coluna-nome').value = coluna?.nome ?? '';
    document.getElementById('coluna-cor').value = coluna?.cor ?? '#6c757d';
    document.getElementById('modalColunaTitulo').textContent = coluna ? 'Editar Coluna' : 'Nova Coluna';
    document.getElementById('btn-salvar-coluna').textContent = coluna ? 'Salvar' : 'Criar';

    controlarModal('modalColuna', 'show');
}

async function salvarColuna(e) {
    e.preventDefault();

    const id = document.getElementById('coluna-id').value;
    const payload = {
        nome: document.getElementById('coluna-nome').value.trim(),
        cor: document.getElementById('coluna-cor').value,
    };

    try {
        const method = id ? 'PUT' : 'POST';
        if (id) payload.id = Number(id);

        const resp = await fetch(API_COLUNAS, { method, body: JSON.stringify(payload) });
        const data = await resp.json();
        if (!data.sucesso) throw new Error(data.erro || 'Erro ao salvar coluna.');

        controlarModal('modalColuna', 'hide');
        mostrarToast(id ? 'Coluna atualizada!' : 'Coluna criada!', 'success');
        await carregarQuadro();
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

async function excluirColuna(colunaId) {
    const coluna = colunas.find(c => Number(c.id) === Number(colunaId));
    if (!coluna) return;
    if (!confirm(`Tem certeza que deseja excluir a coluna "${coluna.nome}"? As tarefas dela serão removidas.`)) return;

    try {
        const resp = await fetch(`${API_COLUNAS}?id=${colunaId}`, { method: 'DELETE' });
        const data = await resp.json();
        if (!data.sucesso) throw new Error(data.erro || 'Erro ao excluir coluna.');

        mostrarToast('Coluna excluída.', 'success');
        await carregarQuadro();
    } catch (erro) {
        mostrarToast(erro.message, 'danger');
    }
}

// ------------------------------------------------------------
// Utilitários
// ------------------------------------------------------------
function escapeHtml(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
}

function escapeAttribute(texto) {
    return escapeHtml(texto).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function truncar(texto, max) {
    return texto.length > max ? texto.slice(0, max) + '…' : texto;
}

function formatarData(dataIso) {
    const [ano, mes, dia] = dataIso.split('-');
    return `${dia}/${mes}/${ano}`;
}

function corEtiquetaCss(cor) {
    return /^#[0-9a-fA-F]{6}$/.test(String(cor)) ? cor : '#0d6efd';
}

function obterDataHojeSaoPaulo() {
    const partes = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/Sao_Paulo',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const valores = Object.fromEntries(partes.filter(parte => parte.type !== 'literal').map(parte => [parte.type, parte.value]));
    return `${valores.year}-${valores.month}-${valores.day}`;
}

function dataCalendarioValida(valor) {
    return typeof valor === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(valor) && Number.isFinite(diasEntreDatas(valor, valor));
}

function diasEntreDatas(inicio, fim) {
    const converter = data => {
        const partes = /^(\d{4})-(\d{2})-(\d{2})$/.exec(data);
        if (!partes) return NaN;
        const timestamp = Date.UTC(Number(partes[1]), Number(partes[2]) - 1, Number(partes[3]));
        const dataNormalizada = new Date(timestamp).toISOString().slice(0, 10);
        return dataNormalizada === data ? timestamp : NaN;
    };
    const inicioTimestamp = converter(inicio);
    const fimTimestamp = converter(fim);
    return Number.isFinite(inicioTimestamp) && Number.isFinite(fimTimestamp)
        ? (fimTimestamp - inicioTimestamp) / 86400000
        : NaN;
}

function classificarVencimento(dataVencimento) {
    if (!dataCalendarioValida(dataVencimento)) return null;
    const hoje = obterDataHojeSaoPaulo();
    if (dataVencimento < hoje) return 'atrasada';
    if (dataVencimento === hoje) return 'hoje';
    return 'futura';
}

function rotuloPrioridade(p) {
    return { baixa: 'Baixa', media: 'Média', alta: 'Alta' }[p] ?? p;
}

function mostrarToast(mensagem, tipo = 'success') {
    if (!window.bootstrap?.Toast) {
        window.alert(mensagem);
        return;
    }

    const toastEl = document.getElementById('toast');
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toastEl.className = `toast align-items-center text-white border-0 bg-${tipo}`;
    document.getElementById('toast-msg').textContent = mensagem;
    toast.show();
}
