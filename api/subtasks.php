<?php
/**
 * API de Subtarefas (Checklist) - api/subtasks.php
 * Métodos: GET (listar por tarefa_id), POST (criar), PUT (marcar concluída / renomear), DELETE (remover)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
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

try {
    garantirTabelaAnexosChecklist($pdo);

    switch ($metodo) {

        // ---------------------------------------------------------
        // LISTAR SUBTAREFAS (todas, ou filtradas por tarefa_id)
        // ---------------------------------------------------------
        case 'GET':
            if (isset($_GET['tarefa_id'])) {
                $stmt = $pdo->prepare('SELECT * FROM subtarefas WHERE tarefa_id = ? ORDER BY ordem ASC, id ASC');
                $stmt->execute([(int)$_GET['tarefa_id']]);
            } else {
                $stmt = $pdo->query('SELECT * FROM subtarefas ORDER BY tarefa_id, ordem ASC, id ASC');
            }
            responder(true, ['subtarefas' => $stmt->fetchAll()]);
            break;

        // ---------------------------------------------------------
        // CRIAR SUBTAREFA
        // ---------------------------------------------------------
        case 'POST':
            $dados    = corpoRequisicao();
            $tarefaId = (int)($dados['tarefa_id'] ?? 0);
            $titulo   = trim($dados['titulo'] ?? '');

            if ($tarefaId <= 0 || $titulo === '') {
                responder(false, ['erro' => 'Tarefa e título são obrigatórios.'], 422);
            }

            $stmtOrdem = $pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 AS proxima FROM subtarefas WHERE tarefa_id = ?');
            $stmtOrdem->execute([$tarefaId]);
            $proximaOrdem = (int)$stmtOrdem->fetch()['proxima'];

            $stmt = $pdo->prepare(
                'INSERT INTO subtarefas (tarefa_id, titulo, ordem) VALUES (:tarefa_id, :titulo, :ordem)'
            );
            $pdo->beginTransaction();
            $stmt->execute([':tarefa_id' => $tarefaId, ':titulo' => $titulo, ':ordem' => $proximaOrdem]);
            $subtarefaId = (int)$pdo->lastInsertId();

            $pdo->commit();
            responder(true, ['id' => $subtarefaId], 201);
            break;

        // ---------------------------------------------------------
        // ATUALIZAR SUBTAREFA (marcar concluída / renomear)
        // ---------------------------------------------------------
        case 'PUT':
            $dados = corpoRequisicao();
            $id = (int)($dados['id'] ?? 0);

            if ($id <= 0) {
                responder(false, ['erro' => 'ID inválido.'], 422);
            }

            $stmtAtual = $pdo->prepare('SELECT tarefa_id FROM subtarefas WHERE id = ?');
            $stmtAtual->execute([$id]);
            $atual = $stmtAtual->fetch();
            if (!$atual) {
                responder(false, ['erro' => 'Subtarefa não encontrada.'], 404);
            }

            $campos = [];
            $params = [':id' => $id];

            if (array_key_exists('concluida', $dados)) {
                $campos[] = 'concluida = :concluida';
                $params[':concluida'] = (int)(bool)$dados['concluida'];
            }
            if (array_key_exists('titulo', $dados)) {
                $tituloNovo = trim((string)$dados['titulo']);
                if ($tituloNovo === '') {
                    responder(false, ['erro' => 'Título não pode ser vazio.'], 422);
                }
                $campos[] = 'titulo = :titulo';
                $params[':titulo'] = $tituloNovo;
            }

            if (empty($campos)) {
                responder(false, ['erro' => 'Nada para atualizar.'], 422);
            }

            $sql = 'UPDATE subtarefas SET ' . implode(', ', $campos) . ' WHERE id = :id';
            $pdo->beginTransaction();
            $pdo->prepare($sql)->execute($params);

            $pdo->commit();
            responder(true);
            break;

        // ---------------------------------------------------------
        // EXCLUIR SUBTAREFA
        // ---------------------------------------------------------
        case 'DELETE':
            parse_str(file_get_contents('php://input'), $dados);
            $id = (int)($_GET['id'] ?? $dados['id'] ?? 0);

            if ($id <= 0) {
                responder(false, ['erro' => 'ID inválido.'], 422);
            }

            $stmtAnexos = $pdo->prepare('SELECT arquivo FROM checklist_anexos WHERE subtarefa_id = ?');
            $stmtAnexos->execute([$id]);
            $arquivosAnexos = $stmtAnexos->fetchAll(PDO::FETCH_COLUMN);

            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM subtarefas WHERE id = ?')->execute([$id]);
            $pdo->commit();
            $avisos = limparArquivosAnexosChecklist($arquivosAnexos);
            responder(true, $avisos ? ['aviso' => 'O item foi removido, mas alguns arquivos anexados não puderam ser limpos do armazenamento.'] : []);
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
