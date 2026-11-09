<?php

declare(strict_types=1);

function registrarHistorico(PDO $pdo, int $tarefaId, string $tipo, string $mensagem): void
{
    $stmt = $pdo->prepare('INSERT INTO tarefas_historico (tarefa_id, tipo, mensagem) VALUES (?, ?, ?)');
    $stmt->execute([$tarefaId, $tipo, $mensagem]);
}

function calcularProximaData(
    string $frequencia,
    ?string $diasSemana = null,
    ?string $horarioRecorrencia = null,
    ?DateTimeImmutable $referencia = null
): ?string
{
    $intervalos = [
        'diaria' => '+1 day',
        'semanal' => '+7 days',
        'mensal' => '+1 month',
        'anual' => '+1 year',
    ];

    $fusoHorario = new DateTimeZone('America/Sao_Paulo');
    $agora = ($referencia ?? new DateTimeImmutable('now', $fusoHorario))->setTimezone($fusoHorario);

    if ($frequencia === 'personalizada') {
        $diasSelecionados = array_map('intval', explode(',', (string)$diasSemana));
        $diaAtual = (int)$agora->format('w');
        $horarioAindaFuturo = $horarioRecorrencia !== null
            && $horarioRecorrencia > $agora->format('H:i');

        if (in_array($diaAtual, $diasSelecionados, true) && $horarioAindaFuturo) {
            return $agora->format('Y-m-d');
        }

        $inicioDoDia = $agora->setTime(0, 0);
        for ($diasAdiante = 1; $diasAdiante <= 7; $diasAdiante++) {
            $dataCandidata = $inicioDoDia->modify('+' . $diasAdiante . ' days');
            if (in_array((int)$dataCandidata->format('w'), $diasSelecionados, true)) {
                return $dataCandidata->format('Y-m-d');
            }
        }

        return null;
    }

    return isset($intervalos[$frequencia])
        ? $agora->modify($intervalos[$frequencia])->format('Y-m-d')
        : null;
}

function criarProximaOcorrencia(PDO $pdo, int $tarefaId): ?int
{
    $stmt = $pdo->prepare('SELECT recorrente, frequencia, dias_semana, horario_recorrencia FROM tarefas WHERE id = ?');
    $stmt->execute([$tarefaId]);
    $tarefa = $stmt->fetch();
    if (!$tarefa || !(bool)$tarefa['recorrente']) {
        return null;
    }

    $colunaId = (int)$pdo->query('SELECT id FROM colunas ORDER BY ordem ASC, id ASC LIMIT 1')->fetchColumn();
    if ($colunaId <= 0) {
        throw new RuntimeException('Não existe coluna para criar a próxima ocorrência.');
    }

    $proximaData = calcularProximaData(
        (string)$tarefa['frequencia'],
        $tarefa['dias_semana'],
        $tarefa['horario_recorrencia'] ? substr((string)$tarefa['horario_recorrencia'], 0, 5) : null
    );
    $stmt = $pdo->prepare(
        'INSERT INTO tarefas (coluna_id, titulo, descricao, prioridade, data_vencimento, ordem, recorrente, frequencia, dias_semana, horario_recorrencia, proxima_repeticao)
         SELECT :coluna_id, titulo, descricao, prioridade, :data_vencimento, :ordem, recorrente, frequencia, dias_semana, horario_recorrencia, :proxima_repeticao
         FROM tarefas WHERE id = :id'
    );
    $stmt->execute([
        ':coluna_id' => $colunaId,
        ':data_vencimento' => $proximaData,
        ':ordem' => 1,
        ':proxima_repeticao' => $proximaData,
        ':id' => $tarefaId,
    ]);
    $novaTarefaId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO subtarefas (tarefa_id, titulo, concluida, ordem)
         SELECT :nova_tarefa_id, titulo, 0, ordem FROM subtarefas WHERE tarefa_id = :tarefa_id'
    );
    $stmt->execute([':nova_tarefa_id' => $novaTarefaId, ':tarefa_id' => $tarefaId]);

    $stmt = $pdo->prepare(
        'INSERT INTO tarefa_etiquetas (tarefa_id, etiqueta_id)
         SELECT :nova_tarefa_id, etiqueta_id FROM tarefa_etiquetas WHERE tarefa_id = :tarefa_id'
    );
    $stmt->execute([':nova_tarefa_id' => $novaTarefaId, ':tarefa_id' => $tarefaId]);

    registrarHistorico($pdo, $tarefaId, 'repetida', 'Tarefa recorrente concluída e uma nova ocorrência foi criada.');
    registrarHistorico($pdo, $novaTarefaId, 'criada', 'Ocorrência criada automaticamente por recorrência.');

    return $novaTarefaId;
}
