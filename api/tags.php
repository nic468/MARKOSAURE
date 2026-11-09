<?php
/**
 * API de Etiquetas - api/tags.php
 * Métodos:
 *   GET    ?tarefa_id=X  -> etiquetas de uma tarefa específica
 *   GET    (sem params)  -> todas as etiquetas cadastradas
 *   POST   { nome, cor } -> cria etiqueta nova
 *   POST   { tarefa_id, etiqueta_id, associar: true/false } -> vincula/desvincula
 *   PUT    { id, nome, cor } -> atualiza uma etiqueta
 *   DELETE ?id=X          -> exclui etiqueta (remove de todas as tarefas)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

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
    switch ($metodo) {

        case 'GET':
            if (isset($_GET['tarefa_id'])) {
                $stmt = $pdo->prepare(
                    'SELECT e.* FROM etiquetas e
                     INNER JOIN tarefa_etiquetas te ON te.etiqueta_id = e.id
                     WHERE te.tarefa_id = ?
                     ORDER BY e.nome'
                );
                $stmt->execute([(int)$_GET['tarefa_id']]);
            } else {
                $stmt = $pdo->query('SELECT * FROM etiquetas ORDER BY nome');
            }
            responder(true, ['etiquetas' => $stmt->fetchAll()]);
            break;

        case 'POST':
            $dados = corpoRequisicao();

            // Vincular/desvincular etiqueta a uma tarefa
            if (isset($dados['tarefa_id'], $dados['etiqueta_id'])) {
                $tarefaId   = (int)$dados['tarefa_id'];
                $etiquetaId = (int)$dados['etiqueta_id'];
                $associar   = $dados['associar'] ?? true;

                if ($associar) {
                    $stmt = $pdo->prepare(
                        'INSERT IGNORE INTO tarefa_etiquetas (tarefa_id, etiqueta_id) VALUES (?, ?)'
                    );
                    $stmt->execute([$tarefaId, $etiquetaId]);
                } else {
                    $stmt = $pdo->prepare(
                        'DELETE FROM tarefa_etiquetas WHERE tarefa_id = ? AND etiqueta_id = ?'
                    );
                    $stmt->execute([$tarefaId, $etiquetaId]);
                }

                responder(true);
                break;
            }

            // Criar nova etiqueta
            $nome = trim($dados['nome'] ?? '');
            $cor  = trim($dados['cor'] ?? '#0d6efd');

            if ($nome === '') {
                responder(false, ['erro' => 'Nome da etiqueta é obrigatório.'], 422);
            }

            $stmt = $pdo->prepare('INSERT INTO etiquetas (nome, cor) VALUES (:nome, :cor)
                                   ON DUPLICATE KEY UPDATE cor = VALUES(cor)');
            $stmt->execute([':nome' => $nome, ':cor' => $cor]);

            $idStmt = $pdo->prepare('SELECT id FROM etiquetas WHERE nome = ?');
            $idStmt->execute([$nome]);

            responder(true, ['id' => (int)$idStmt->fetch()['id']], 201);
            break;

        case 'DELETE':
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                responder(false, ['erro' => 'ID inválido.'], 422);
            }

            $pdo->prepare('DELETE FROM etiquetas WHERE id = ?')->execute([$id]);
            responder(true);
            break;

        case 'PUT':
            $dados = corpoRequisicao();
            $id = (int)($dados['id'] ?? 0);
            $nome = trim((string)($dados['nome'] ?? ''));
            $cor = trim((string)($dados['cor'] ?? ''));

            if ($id <= 0) {
                responder(false, ['erro' => 'ID inválido.'], 422);
            }
            if ($nome === '' || mb_strlen($nome) > 40) {
                responder(false, ['erro' => 'Informe um nome de até 40 caracteres.'], 422);
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) {
                responder(false, ['erro' => 'Informe uma cor hexadecimal válida.'], 422);
            }

            try {
                $stmt = $pdo->prepare('UPDATE etiquetas SET nome = ?, cor = ? WHERE id = ?');
                $stmt->execute([$nome, $cor, $id]);
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    responder(false, ['erro' => 'Já existe uma etiqueta com esse nome.'], 409);
                }
                throw $e;
            }

            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare('SELECT 1 FROM etiquetas WHERE id = ?');
                $stmt->execute([$id]);
                if (!$stmt->fetchColumn()) {
                    responder(false, ['erro' => 'Etiqueta não encontrada.'], 404);
                }
            }

            responder(true);
            break;

        default:
            responder(false, ['erro' => 'Método não suportado.'], 405);
    }
} catch (Throwable $e) {
    responder(false, ['erro' => 'Erro interno: ' . $e->getMessage()], 500);
}
