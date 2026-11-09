document.addEventListener('DOMContentLoaded', carregarRegistro);

const modalDetalhesRegistro = new bootstrap.Modal(document.getElementById('modalDetalhesRegistro'));

async function carregarRegistro() {
    const container = document.getElementById('registro-conteudo');
    try {
        const resposta = await fetch('api/tasks.php?registros=1').then(r => r.json());
        if (!resposta.sucesso) throw new Error(resposta.erro || 'Não foi possível carregar o registro.');

        if (!resposta.registros.length) {
            container.innerHTML = '<div class="alert alert-light border">Nenhuma tarefa encerrada foi registrada.</div>';
            return;
        }

        container.innerHTML = `
            <div class="table-responsive">
              <table class="table table-hover align-middle">
                <thead><tr><th>Tarefa</th><th>Status</th><th>Coluna</th><th>Motivo do cancelamento</th><th>Encerrada em</th><th scope="col">Detalhes</th></tr></thead>
                <tbody>${resposta.registros.map(registro => `
                  <tr>
                    <td><strong>${escapar(registro.titulo)}</strong></td>
                    <td>${registro.status === 'cancelada'
                        ? '<span class="badge text-bg-warning">Cancelada</span>'
                        : '<span class="badge text-bg-success">Concluída</span>'}</td>
                    <td>${escapar(registro.coluna_nome || '-')}</td>
                    <td>${registro.status === 'cancelada' ? escapar(registro.cancelamento_motivo || '-') : '<span class="text-muted">-</span>'}</td>
                    <td>${formatarData(registro.encerrada_em || registro.concluida_em)}</td>
                    <td class="text-nowrap"><button type="button" class="btn btn-primary btn-sm" data-tarefa-id="${Number(registro.id)}">
                      <i class="bi bi-eye"></i> Ver detalhes
                    </button></td>
                  </tr>`).join('')}</tbody>
              </table>
            </div>`;
        container.querySelectorAll('[data-tarefa-id]').forEach(botao => {
            botao.addEventListener('click', () => visualizarTarefa(botao.dataset.tarefaId));
        });
    } catch (erro) {
        container.innerHTML = `<div class="alert alert-danger">${escapar(erro.message)}</div>`;
    }
}

async function visualizarTarefa(id) {
    const conteudo = document.getElementById('registro-detalhes-conteudo');
    conteudo.innerHTML = '<div class="text-center text-muted py-4"><div class="spinner-border"></div></div>';
    modalDetalhesRegistro.show();

    try {
        const resposta = await fetch(`api/tasks.php?registro_tarefa_id=${encodeURIComponent(id)}`).then(r => r.json());
        if (!resposta.sucesso) throw new Error(resposta.erro || 'Não foi possível carregar os detalhes.');
        conteudo.innerHTML = renderizarDetalhes(resposta);
    } catch (erro) {
        conteudo.innerHTML = `<div class="alert alert-danger">${escapar(erro.message)}</div>`;
    }
}

function renderizarDetalhes({ tarefa, subtarefas, anexos_checklist = [], etiquetas, historico }) {
    const diasSemana = String(tarefa.dias_semana || '')
        .split(',')
        .filter(Boolean)
        .map(dia => ({ '0': 'Dom', '1': 'Seg', '2': 'Ter', '3': 'Qua', '4': 'Qui', '5': 'Sex', '6': 'Sáb' })[dia])
        .filter(Boolean);
    const recorrencia = tarefa.recorrente
        ? `Sim (${rotuloFrequencia(tarefa.frequencia)}${diasSemana.length ? `: ${diasSemana.join(', ')}` : ''}${tarefa.horario_recorrencia ? ` às ${String(tarefa.horario_recorrencia).slice(0, 5)}` : ''})`
        : 'Não';
    const listaSubtarefas = subtarefas.length
        ? `<ul class="list-group">${subtarefas.map(item => {
            const anexos = anexos_checklist.filter(anexo => Number(anexo.subtarefa_id) === Number(item.id));
            const listaAnexos = anexos.length
                ? `<ul class="list-unstyled small mt-2 mb-0">${anexos.map(anexo => `
                    <li><i class="bi bi-paperclip me-1"></i>
                      <a href="api/checklist_attachments.php?download=${Number(anexo.id)}">${escapar(anexo.nome_original)}</a>
                      <span class="text-muted">(${formatarTamanhoArquivo(anexo.tamanho)})</span>
                    </li>`).join('')}</ul>`
                : '';

            return `
            <li class="list-group-item">
              <div class="d-flex gap-2 align-items-center">
                <i class="bi ${Number(item.concluida) ? 'bi-check-square-fill text-success' : 'bi-square text-muted'}"></i>
                <span class="${Number(item.concluida) ? 'text-decoration-line-through text-muted' : ''}">${escapar(item.titulo)}</span>
              </div>
              ${listaAnexos}
            </li>`;
        }).join('')}</ul>`
        : '<p class="text-muted mb-0">Sem itens no checklist.</p>';
    const listaEtiquetas = etiquetas.length
        ? etiquetas.map(etiqueta => `<span class="badge rounded-pill me-1" style="background:${escapar(etiqueta.cor)}">${escapar(etiqueta.nome)}</span>`).join('')
        : '<span class="text-muted">Sem etiquetas.</span>';
    const listaHistorico = historico.length
        ? historico.map(item => `
            <div class="border-start border-2 ps-2 mb-3">
              <div class="fw-semibold">${escapar(item.tipo)}</div>
              <div>${escapar(item.mensagem)}</div>
              <small class="text-muted">${formatarData(item.created_at)}</small>
            </div>`).join('')
        : '<p class="text-muted mb-0">Sem histórico registrado.</p>';

    return `
      <h4>${escapar(tarefa.titulo)}</h4>
      <p class="text-muted" style="white-space:pre-wrap">${escapar(tarefa.descricao || 'Sem descrição.')}</p>
      <dl class="row">
        <dt class="col-sm-4">Status</dt><dd class="col-sm-8">${tarefa.status === 'cancelada' ? 'Cancelada' : 'Concluída'}</dd>
        <dt class="col-sm-4">Coluna</dt><dd class="col-sm-8">${escapar(tarefa.coluna_nome || '-')}</dd>
        <dt class="col-sm-4">Prioridade</dt><dd class="col-sm-8">${rotuloPrioridade(tarefa.prioridade)}</dd>
        <dt class="col-sm-4">Vencimento</dt><dd class="col-sm-8">${formatarData(tarefa.data_vencimento)}</dd>
        <dt class="col-sm-4">Recorrente</dt><dd class="col-sm-8">${recorrencia}</dd>
        <dt class="col-sm-4">Criada em</dt><dd class="col-sm-8">${formatarData(tarefa.created_at)}</dd>
        <dt class="col-sm-4">Atualizada em</dt><dd class="col-sm-8">${formatarData(tarefa.updated_at)}</dd>
        <dt class="col-sm-4">Encerrada em</dt><dd class="col-sm-8">${formatarData(tarefa.encerrada_em || tarefa.concluida_em)}</dd>
        ${tarefa.status === 'cancelada' ? `<dt class="col-sm-4">Motivo do cancelamento</dt><dd class="col-sm-8" style="white-space:pre-wrap">${escapar(tarefa.cancelamento_motivo || '-')}</dd>` : ''}
        <dt class="col-sm-4">Etiquetas</dt><dd class="col-sm-8">${listaEtiquetas}</dd>
      </dl>
      <h6 class="mt-4">Checklist</h6>${listaSubtarefas}
      <h6 class="mt-4">Histórico</h6>${listaHistorico}`;
}

function escapar(valor) {
    const div = document.createElement('div');
    div.textContent = valor ?? '';
    return div.innerHTML;
}

function formatarData(valor) {
    if (!valor) return '-';
    const somenteData = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);
    if (somenteData) {
        return new Date(Number(somenteData[1]), Number(somenteData[2]) - 1, Number(somenteData[3])).toLocaleDateString('pt-BR');
    }
    const data = new Date(valor.replace(' ', 'T'));
    return Number.isNaN(data.getTime()) ? '-' : data.toLocaleString('pt-BR');
}

function formatarTamanhoArquivo(tamanho) {
    const bytes = Number(tamanho);
    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} KB`
        : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function rotuloPrioridade(prioridade) {
    return ({ baixa: 'Baixa', media: 'Média', alta: 'Alta' })[prioridade] || '-';
}

function rotuloFrequencia(frequencia) {
    return ({ diaria: 'Diária', semanal: 'Semanal', mensal: 'Mensal', anual: 'Anual', personalizada: 'Personalizada' })[frequencia] || 'Sem recorrência';
}
