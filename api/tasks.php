<?php
/**
 * API de Tarefas - api/tasks.php
 * Métodos: GET (listar), POST (criar), PUT (atualizar / mover), DELETE (remover)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/task_lifecycle.php';
require_once __DIR__ . '/checklist_attachments_storage.php';

$pdo    = getConnection();
$metodo = $_SERVER['REQUEST_METHOD'];

function responder(bool $sucesso, array $dados = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['sucesso' => $sucesso], $dados), JSON_UNESCAPED_UNICODE);
    exit;
}

function corpoRequisicao(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function obterDadosRecorrencia(array $dados): array
{
    $recorrente = !empty($dados['recorrente']);
    $frequencia = $recorrente ? (string)($dados['frequencia'] ?? 'nenhuma') : 'nenhuma';
    $frequenciasPermitidas = ['nenhuma', 'diaria', 'semanal', 'mensal', 'anual', 'personalizada'];

    if (!in_array($frequencia, $frequenciasPermitidas, true)) {
        responder(false, ['erro' => 'Frequência de recorrência inválida.'], 422);
    }

    $diasSelecionados = [];
    if ($frequencia === 'personalizada') {
        $diasRecebidos = $dados['dias_semana'] ?? [];
        if (!is_array($diasRecebidos)) {
            responder(false, ['erro' => 'Selecione pelo menos um dia da semana para a recorrência personalizada.'], 422);
        }

        foreach ($diasRecebidos as $dia) {
            $dia = (string)$dia;
            if (!in_array($dia, ['0', '1', '2', '3', '4', '5', '6'], true)) {
                responder(false, ['erro' => 'Um ou mais dias selecionados são inválidos.'], 422);
            }
            $diasSelecionados[] = (int)$dia;
        }
        $diasSelecionados = array_values(array_unique($diasSelecionados));
        sort($diasSelecionados);

        if (!$diasSelecionados) {
            responder(false, ['erro' => 'Selecione pelo menos um dia da semana para a recorrência personalizada.'], 422);
        }
    }

    $horarioRecorrencia = null;
    if ($recorrente && !empty($dados['horario_recorrencia'])) {
        $horarioRecebido = (string)$dados['horario_recorrencia'];
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $horarioRecebido)) {
            responder(false, ['erro' => 'Informe um horário válido para a recorrência.'], 422);
        }
        $horarioRecorrencia = $horarioRecebido;
    }

    return [
        'recorrente' => $recorrente,
        'frequencia' => $frequencia,
        'dias_semana' => $diasSelecionados ? implode(',', $diasSelecionados) : null,
        'horario_recorrencia' => $horarioRecorrencia,
    ];
}

function ensureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS tarefas_historico (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tarefa_id INT NOT NULL,
        tipo VARCHAR(30) NOT NULL,
        mensagem TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_hist_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE
    )");
    garantirTabelaAnexosChecklist($pdo);

    $colunas = [
        'status' => "ALTER TABLE tarefas ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'aberta'",
        'cancelamento_motivo' => "ALTER TABLE tarefas ADD COLUMN cancelamento_motivo TEXT NULL",
        'encerrada_em' => "ALTER TABLE tarefas ADD COLUMN encerrada_em TIMESTAMP NULL DEFAULT NULL",
        'concluida_em' => "ALTER TABLE tarefas ADD COLUMN concluida_em TIMESTAMP NULL DEFAULT NULL",
        'recorrente' => "ALTER TABLE tarefas ADD COLUMN recorrente TINYINT(1) NOT NULL DEFAULT 0",
        'frequencia' => "ALTER TABLE tarefas ADD COLUMN frequencia VARCHAR(20) NOT NULL DEFAULT 'nenhuma'",
        'dias_semana' => "ALTER TABLE tarefas ADD COLUMN dias_semana VARCHAR(13) NULL DEFAULT NULL",
        'horario_recorrencia' => "ALTER TABLE tarefas ADD COLUMN horario_recorrencia TIME NULL DEFAULT NULL",
        'proxima_repeticao' => "ALTER TABLE tarefas ADD COLUMN proxima_repeticao DATE NULL",
    ];

    foreach ($colunas as $nome => $sql) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = ?");
        $stmt->execute([$nome]);
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }
}

function obterNomeColuna(PDO $pdo, int $colunaId): string
{
    $stmt = $pdo->prepare('SELECT nome FROM colunas WHERE id = ?');
    $stmt->execute([$colunaId]);
    return (string)$stmt->fetchColumn();
}

try {
    ensureSchema($pdo);

    switch ($metodo) {

        // ---------------------------------------------------------
        // LISTAR TAREFAS
        // ---------------------------------------------------------
        case 'GET':
            if (isset($_GET['registros'])) {
                $stmt = $pdo->query("SELECT t.*, c.nome AS coluna_nome
                    FROM tarefas t
                    LEFT JOIN colunas c ON c.id = t.coluna_id
                    WHERE t.status IN ('concluida', 'cancelada')
                    ORDER BY COALESCE(t.encerrada_em, t.concluida_em) DESC, t.id DESC");
                responder(true, ['registros' => $stmt->fetchAll()]);
            }

            if (isset($_GET['historico_tarefa_id'])) {
                $tarefaId = (int)$_GET['historico_tarefa_id'];
                $stmt = $pdo->prepare('SELECT id, tipo, mensagem, created_at FROM tarefas_historico WHERE tarefa_id = ? ORDER BY created_at DESC, id DESC LIMIT 20');
                $stmt->execute([$tarefaId]);
                responder(true, ['historico' => $stmt->fetchAll()]);
            }

            if (isset($_GET['registro_tarefa_id'])) {
                $tarefaId = (int)$_GET['registro_tarefa_id'];
                $stmt = $pdo->prepare('SELECT t.*, c.nome AS coluna_nome
                    FROM tarefas t
                    LEFT JOIN colunas c ON c.id = t.coluna_id
                    WHERE t.id = ? AND t.status IN (\'concluida\', \'cancelada\')');
                $stmt->execute([$tarefaId]);
                $tarefa = $stmt->fetch();
                if (!$tarefa) {
                    responder(false, ['erro' => 'Tarefa encerrada não encontrada.'], 404);
                }

                $stmt = $pdo->prepare('SELECT id, titulo, concluida, ordem FROM subtarefas WHERE tarefa_id = ? ORDER BY ordem ASC, id ASC');
                $stmt->execute([$tarefaId]);
                $subtarefas = $stmt->fetchAll();

                $stmt = $pdo->prepare('SELECT a.id, a.subtarefa_id, a.nome_original, a.mime_type, a.tamanho, a.created_at
                    FROM checklist_anexos a
                    INNER JOIN subtarefas s ON s.id = a.subtarefa_id
                    WHERE s.tarefa_id = ?
                    ORDER BY a.created_at ASC, a.id ASC');
                $stmt->execute([$tarefaId]);
                $anexosChecklist = $stmt->fetchAll();

                $stmt = $pdo->prepare('SELECT e.id, e.nome, e.cor
                    FROM etiquetas e
                    INNER JOIN tarefa_etiquetas te ON te.etiqueta_id = e.id
                    WHERE te.tarefa_id = ?
                    ORDER BY e.nome');
                $stmt->execute([$tarefaId]);
                $etiquetas = $stmt->fetchAll();

                $stmt = $pdo->prepare('SELECT id, tipo, mensagem, created_at FROM tarefas_historico WHERE tarefa_id = ? ORDER BY created_at DESC, id DESC');
                $stmt->execute([$tarefaId]);
                responder(true, [
                    'tarefa' => $tarefa,
                    'subtarefas' => $subtarefas,
                    'anexos_checklist' => $anexosChecklist,
                    'etiquetas' => $etiquetas,
                    'historico' => $stmt->fetchAll(),
                ]);
            }

            $sql = "SELECT t.*,
                        (SELECT COUNT(*) FROM subtarefas s WHERE s.tarefa_id = t.id) AS total_subtarefas,
                        (SELECT COUNT(*) FROM subtarefas s WHERE s.tarefa_id = t.id AND s.concluida = 1) AS subtarefas_concluidas,
                        (SELECT GROUP_CONCAT(te.etiqueta_id) FROM tarefa_etiquetas te WHERE te.tarefa_id = t.id) AS etiquetas_ids
                    FROM tarefas t
                    ORDER BY t.coluna_id, t.ordem ASC, t.id ASC";
            $stmt = $pdo->query($sql);
            responder(true, ['tarefas' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------
        // CRIAR TAREFA
        // ---------------------------------------------------------
        case 'POST':
            $dados = corpoRequisicao();

            $titulo    = trim($dados['titulo'] ?? '');
            $colunaId  = (int)($dados['coluna_id'] ?? 0);
            $descricao = trim($dados['descricao'] ?? '');
            $prioridade = $dados['prioridade'] ?? 'media';
            $vencimento = !empty($dados['data_vencimento']) ? $dados['data_vencimento'] : null;
            $recorrencia = obterDadosRecorrencia($dados);

            if ($titulo === '' || $colunaId <= 0) {
                responder(false, ['erro' => 'Título e coluna são obrigatórios.'], 422);
            }

            if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
                $prioridade = 'media';
            }

            // próxima posição dentro da coluna
            $stmtOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima FROM tarefas WHERE coluna_id = ?');
            $stmtOrdem->execute([$colunaId]);
            $proximaOrdem = (int)$stmtOrdem->fetch()['proxima'];

            $stmt = $pdo->prepare(
                'INSERT INTO tarefas (coluna_id, titulo, descricao, prioridade, data_vencimento, ordem, recorrente, frequencia, dias_semana, horario_recorrencia, proxima_repeticao)
                 VALUES (:coluna_id, :titulo, :descricao, :prioridade, :data_vencimento, :ordem, :recorrente, :frequencia, :dias_semana, :horario_recorrencia, :proxima_repeticao)'
            );
            $stmt->execute([
                ':coluna_id'         => $colunaId,
                ':titulo'            => $titulo,
                ':descricao'         => $descricao,
                ':prioridade'        => $prioridade,
                ':data_vencimento'   => $vencimento,
                ':ordem'             => $proximaOrdem,
                ':recorrente'        => (int)$recorrencia['recorrente'],
                ':frequencia'        => $recorrencia['frequencia'],
                ':dias_semana'       => $recorrencia['dias_semana'],
                ':horario_recorrencia' => $recorrencia['horario_recorrencia'],
                ':proxima_repeticao' => $recorrencia['recorrente']
                    ? calcularProximaData($recorrencia['frequencia'], $recorrencia['dias_semana'], $recorrencia['horario_recorrencia'])
                    : null,
            ]);

            $tarefaId = (int)$pdo->lastInsertId();
            registrarHistorico($pdo, $tarefaId, 'criada', 'Tarefa criada.');
            responder(true, ['id' => $tarefaId], 201);
            break;

        // ---------------------------------------------------------
        // ATUALIZAR / MOVER TAREFA
        // ---------------------------------------------------------
        case 'PUT':
            $dados = corpoRequisicao();
            $id = (int)($dados['id'] ?? 0);

            if ($id <= 0) {
                responder(false, ['erro' => 'ID da tarefa inválido.'], 422);
            }

            if (($dados['acao'] ?? '') === 'cancelar') {
                $motivo = trim($dados['motivo_cancelamento'] ?? '');
                if ($motivo === '') {
                    responder(false, ['erro' => 'O motivo do cancelamento é obrigatório.'], 422);
                }

                $stmt = $pdo->prepare(
                    'UPDATE tarefas SET status = :status, cancelamento_motivo = :motivo, encerrada_em = NOW(), concluida_em = NULL WHERE id = :id'
                );
                $stmt->execute([':status' => 'cancelada', ':motivo' => $motivo, ':id' => $id]);
                registrarHistorico($pdo, $id, 'cancelada', 'Tarefa cancelada. Motivo: ' . $motivo);
                responder(true);
            }

            if (($dados['acao'] ?? '') === 'concluir') {
                $titulo = trim($dados['titulo'] ?? '');
                $colunaId = (int)($dados['coluna_id'] ?? 0);
                $descricao = trim($dados['descricao'] ?? '');
                $prioridade = $dados['prioridade'] ?? 'media';
                $vencimento = !empty($dados['data_vencimento']) ? $dados['data_vencimento'] : null;
                $recorrencia = obterDadosRecorrencia($dados);

                if ($titulo === '' || $colunaId <= 0) {
                    responder(false, ['erro' => 'Título e coluna são obrigatórios.'], 422);
                }
                if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
                    $prioridade = 'media';
                }
                $stmt = $pdo->prepare('SELECT id FROM colunas WHERE id = ?');
                $stmt->execute([$colunaId]);
                if (!$stmt->fetchColumn()) {
                    responder(false, ['erro' => 'Coluna não encontrada.'], 404);
                }

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT id, status FROM tarefas WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $tarefaBase = $stmt->fetch();
                if (!$tarefaBase) {
                    $pdo->rollBack();
                    responder(false, ['erro' => 'Tarefa não encontrada.'], 404);
                }
                if ($tarefaBase['status'] === 'concluida') {
                    $pdo->commit();
                    responder(true);
                }
                if ($tarefaBase['status'] === 'cancelada') {
                    $pdo->rollBack();
                    responder(false, ['erro' => 'Uma tarefa cancelada não pode ser concluída.'], 422);
                }

                $stmt = $pdo->prepare(
                    "UPDATE tarefas SET coluna_id = :coluna_id, ordem = 999, titulo = :titulo, descricao = :descricao,
                     prioridade = :prioridade, data_vencimento = :data_vencimento, recorrente = :recorrente,
                     frequencia = :frequencia, dias_semana = :dias_semana, horario_recorrencia = :horario_recorrencia,
                     proxima_repeticao = :proxima_repeticao, status = 'concluida',
                     cancelamento_motivo = NULL, encerrada_em = COALESCE(encerrada_em, NOW()),
                     concluida_em = COALESCE(concluida_em, NOW()) WHERE id = :id"
                );
                $stmt->execute([
                    ':coluna_id' => $colunaId,
                    ':titulo' => $titulo,
                    ':descricao' => $descricao,
                    ':prioridade' => $prioridade,
                    ':data_vencimento' => $vencimento,
                    ':recorrente' => (int)$recorrencia['recorrente'],
                    ':frequencia' => $recorrencia['frequencia'],
                    ':dias_semana' => $recorrencia['dias_semana'],
                    ':horario_recorrencia' => $recorrencia['horario_recorrencia'],
                    ':proxima_repeticao' => $recorrencia['recorrente']
                        ? calcularProximaData($recorrencia['frequencia'], $recorrencia['dias_semana'], $recorrencia['horario_recorrencia'])
                        : null,
                    ':id' => $id,
                ]);
                registrarHistorico($pdo, $id, 'concluida', 'Tarefa marcada como concluída.');

                criarProximaOcorrencia($pdo, $id);

                $pdo->commit();
                responder(true);
            }

            // Movimentação simples (drag-and-drop): apenas coluna_id + ordem
            if (isset($dados['mover']) && $dados['mover'] === true) {
                $colunaId = (int)($dados['coluna_id'] ?? 0);
                $ordem    = (int)($dados['ordem'] ?? 0);

                $pdo->beginTransaction();
                $stmt = $pdo->prepare('SELECT id FROM tarefas WHERE id = ? FOR UPDATE');
                $stmt->execute([$id]);
                $tarefaBase = $stmt->fetch();
                if (!$tarefaBase) {
                    $pdo->rollBack();
                    responder(false, ['erro' => 'Tarefa não encontrada.'], 404);
                }
                $stmt = $pdo->prepare('UPDATE tarefas SET coluna_id = :coluna_id, ordem = :ordem WHERE id = :id');
                $stmt->execute([':coluna_id' => $colunaId, ':ordem' => $ordem, ':id' => $id]);

                $nomeColuna = obterNomeColuna($pdo, $colunaId);

                registrarHistorico($pdo, $id, 'movida', 'Tarefa movida para a coluna ' . $nomeColuna . '.');

                $pdo->commit();
                responder(true);
                break;
            }

            // Edição completa dos dados da tarefa
            $titulo     = trim($dados['titulo'] ?? '');
            $descricao  = trim($dados['descricao'] ?? '');
            $prioridade = $dados['prioridade'] ?? 'media';
            $vencimento = !empty($dados['data_vencimento']) ? $dados['data_vencimento'] : null;
            $recorrencia = obterDadosRecorrencia($dados);

            if ($titulo === '') {
                responder(false, ['erro' => 'Título é obrigatório.'], 422);
            }

            if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) {
                $prioridade = 'media';
            }

            $stmt = $pdo->prepare(
                'UPDATE tarefas
                 SET titulo = :titulo, descricao = :descricao, prioridade = :prioridade, data_vencimento = :data_vencimento,
                     recorrente = :recorrente, frequencia = :frequencia, dias_semana = :dias_semana,
                     horario_recorrencia = :horario_recorrencia, proxima_repeticao = :proxima_repeticao
                 WHERE id = :id'
            );
            $stmt->execute([
                ':titulo'            => $titulo,
                ':descricao'         => $descricao,
                ':prioridade'        => $prioridade,
                ':data_vencimento'   => $vencimento,
                ':recorrente'        => (int)$recorrencia['recorrente'],
                ':frequencia'        => $recorrencia['frequencia'],
                ':dias_semana'       => $recorrencia['dias_semana'],
                ':horario_recorrencia' => $recorrencia['horario_recorrencia'],
                ':proxima_repeticao' => $recorrencia['recorrente']
                    ? calcularProximaData($recorrencia['frequencia'], $recorrencia['dias_semana'], $recorrencia['horario_recorrencia'])
                    : null,
                ':id'                => $id,
            ]);

            registrarHistorico($pdo, $id, 'editada', 'Tarefa editada.');
            responder(true);
            break;

        // ---------------------------------------------------------
        // EXCLUIR TAREFA
        // ---------------------------------------------------------
        case 'DELETE':
            parse_str(file_get_contents('php://input'), $dados);
            $id = (int)($_GET['id'] ?? $dados['id'] ?? 0);

            if ($id <= 0) {
                responder(false, ['erro' => 'ID da tarefa inválido.'], 422);
            }

            $stmtAnexos = $pdo->prepare('SELECT a.arquivo FROM checklist_anexos a
                INNER JOIN subtarefas s ON s.id = a.subtarefa_id WHERE s.tarefa_id = ?');
            $stmtAnexos->execute([$id]);
            $arquivosAnexos = $stmtAnexos->fetchAll(PDO::FETCH_COLUMN);

            $stmt = $pdo->prepare('DELETE FROM tarefas WHERE id = ?');
            $stmt->execute([$id]);

            $avisos = limparArquivosAnexosChecklist($arquivosAnexos);
            responder(true, $avisos ? ['aviso' => 'A tarefa foi removida, mas alguns arquivos anexados não puderam ser limpos do armazenamento.'] : []);
            break;

        default:
            responder(false, ['erro' => 'Método não suportado.'], 405);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(false, ['erro' => 'Erro interno: ' . $e->getMessage()], 500);
}
