// ============================================================
// Dashboard - KPIs e gráficos (Chart.js)
// ============================================================

document.addEventListener('DOMContentLoaded', carregarDashboard);

async function carregarDashboard() {
    const [respColunas, respTarefas] = await Promise.all([
        fetch('api/columns.php').then(r => r.json()),
        fetch('api/tasks.php').then(r => r.json()),
    ]);

    if (!respColunas.sucesso || !respTarefas.sucesso) return;

    const colunas = respColunas.colunas;
    const tarefas = respTarefas.tarefas;

    montarKpis(tarefas, colunas);
    montarGraficoColunas(tarefas, colunas);
    montarGraficoPrioridade(tarefas);
    montarGraficoSemanas(tarefas);
}

function montarKpis(tarefas, colunas) {
    const ultimaColunaId = colunas.reduce((max, c) => Number(c.ordem) > max.ordem ? c : max, colunas[0])?.id;
    const hoje = obterDataHojeSaoPaulo();

    const concluidas = tarefas.filter(t => t.concluida_em !== null).length;
    const abertas = tarefas.length - concluidas;
    const vencidas = tarefas.filter(t =>
        t.concluida_em === null && t.data_vencimento && String(t.data_vencimento).slice(0, 10) < hoje
    ).length;

    document.getElementById('kpi-total').textContent = tarefas.length;
    document.getElementById('kpi-concluidas').textContent = concluidas;
    document.getElementById('kpi-abertas').textContent = abertas;
    document.getElementById('kpi-vencidas').textContent = vencidas;
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

function montarGraficoColunas(tarefas, colunas) {
    const labels = colunas.map(c => c.nome);
    const cores = colunas.map(c => c.cor);
    const valores = colunas.map(c => tarefas.filter(t => Number(t.coluna_id) === Number(c.id)).length);

    new Chart(document.getElementById('grafico-colunas'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{ label: 'Tarefas', data: valores, backgroundColor: cores }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}

function montarGraficoPrioridade(tarefas) {
    const mapa = { alta: 0, media: 0, baixa: 0 };
    tarefas.forEach(t => { if (mapa[t.prioridade] !== undefined) mapa[t.prioridade]++; });

    new Chart(document.getElementById('grafico-prioridade'), {
        type: 'doughnut',
        data: {
            labels: ['Alta', 'Média', 'Baixa'],
            datasets: [{
                data: [mapa.alta, mapa.media, mapa.baixa],
                backgroundColor: ['#dc3545', '#ffc107', '#198754'],
            }],
        },
        options: { plugins: { legend: { position: 'bottom' } } },
    });
}

function montarGraficoSemanas(tarefas) {
    const semanas = [];
    const hoje = new Date();

    for (let i = 7; i >= 0; i--) {
        const inicio = new Date(hoje);
        inicio.setDate(hoje.getDate() - (i * 7 + hoje.getDay()));
        inicio.setHours(0, 0, 0, 0);
        const fim = new Date(inicio);
        fim.setDate(inicio.getDate() + 6);
        fim.setHours(23, 59, 59, 999);
        semanas.push({ inicio, fim, label: `${inicio.getDate()}/${inicio.getMonth() + 1}`, total: 0 });
    }

    tarefas
        .filter(t => t.concluida_em)
        .forEach(t => {
            const data = new Date(t.concluida_em.replace(' ', 'T'));
            const semana = semanas.find(s => data >= s.inicio && data <= s.fim);
            if (semana) semana.total++;
        });

    new Chart(document.getElementById('grafico-semanas'), {
        type: 'line',
        data: {
            labels: semanas.map(s => s.label),
            datasets: [{
                label: 'Concluídas',
                data: semanas.map(s => s.total),
                borderColor: '#198754',
                backgroundColor: 'rgba(25,135,84,.15)',
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        },
    });
}
