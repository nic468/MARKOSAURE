<?php
/**
 * Anexos dos itens de checklist.
 * GET ?subtarefa_id=X lista; GET ?download=X baixa; POST envia um arquivo.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/checklist_attachments_storage.php';

const TAMANHO_MAXIMO_ANEXO = 10 * 1024 * 1024;

function responderAnexo(bool $sucesso, array $dados = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['sucesso' => $sucesso], $dados), JSON_UNESCAPED_UNICODE);
    exit;
}

function diretorioAnexosChecklist(): string
{
    return __DIR__ . '/../storage/checklist';
}

function tiposAnexoPermitidos(): array
{
    return [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
    ];
}

$pdo = getConnection();
$arquivoMovido = null;

try {
    garantirTabelaAnexosChecklist($pdo);
    $metodo = $_SERVER['REQUEST_METHOD'];

    if ($metodo === 'GET' && isset($_GET['download'])) {
        $id = (int)$_GET['download'];
        $stmt = $pdo->prepare('SELECT nome_original, arquivo, mime_type, tamanho FROM checklist_anexos WHERE id = ?');
        $stmt->execute([$id]);
        $anexo = $stmt->fetch();
        $caminho = $anexo ? caminhoAnexoChecklist((string)$anexo['arquivo']) : null;
        if (!$anexo || $caminho === null) {
            responderAnexo(false, ['erro' => 'Anexo não encontrado.'], 404);
        }

        if (!is_file($caminho)) {
            responderAnexo(false, ['erro' => 'O arquivo anexado não está disponível no armazenamento.'], 404);
        }

        header_remove('Content-Type');
        header('Content-Type: ' . $anexo['mime_type']);
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($anexo['nome_original']));
        header('Content-Length: ' . filesize($caminho));
        header('X-Content-Type-Options: nosniff');
        readfile($caminho);
        exit;
    }

    if ($metodo === 'DELETE') {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            responderAnexo(false, ['erro' => 'ID do anexo inválido.'], 422);
        }

        $stmt = $pdo->prepare('SELECT a.arquivo, t.status
            FROM checklist_anexos a
            INNER JOIN subtarefas s ON s.id = a.subtarefa_id
            INNER JOIN tarefas t ON t.id = s.tarefa_id
            WHERE a.id = ?');
        $stmt->execute([$id]);
        $anexo = $stmt->fetch();
        if (!$anexo) {
            responderAnexo(false, ['erro' => 'Anexo não encontrado.'], 404);
        }
        if ($anexo['status'] !== 'aberta') {
            responderAnexo(false, ['erro' => 'Não é possível remover anexos de uma tarefa encerrada.'], 422);
        }

        $pdo->prepare('DELETE FROM checklist_anexos WHERE id = ?')->execute([$id]);
        $avisos = limparArquivosAnexosChecklist([$anexo['arquivo']]);
        responderAnexo(true, $avisos ? ['aviso' => 'O anexo foi removido do checklist, mas não foi possível limpar o arquivo do armazenamento.'] : []);
    }

    if ($metodo === 'GET') {
        $subtarefaId = (int)($_GET['subtarefa_id'] ?? 0);
        if ($subtarefaId <= 0) {
            responderAnexo(false, ['erro' => 'ID do item de checklist inválido.'], 422);
        }

        $stmt = $pdo->prepare('SELECT id FROM subtarefas WHERE id = ?');
        $stmt->execute([$subtarefaId]);
        if (!$stmt->fetchColumn()) {
            responderAnexo(false, ['erro' => 'Item de checklist não encontrado.'], 404);
        }

        $stmt = $pdo->prepare('SELECT id, subtarefa_id, nome_original, mime_type, tamanho, created_at
            FROM checklist_anexos WHERE subtarefa_id = ? ORDER BY created_at ASC, id ASC');
        $stmt->execute([$subtarefaId]);
        responderAnexo(true, ['anexos' => $stmt->fetchAll()]);
    }

    if ($metodo === 'POST') {
        $subtarefaId = (int)($_POST['subtarefa_id'] ?? 0);
        if ($subtarefaId <= 0) {
            responderAnexo(false, ['erro' => 'ID do item de checklist inválido.'], 422);
        }

        $stmt = $pdo->prepare('SELECT t.status FROM subtarefas s
            INNER JOIN tarefas t ON t.id = s.tarefa_id WHERE s.id = ?');
        $stmt->execute([$subtarefaId]);
        $statusTarefa = $stmt->fetchColumn();
        if ($statusTarefa === false) {
            responderAnexo(false, ['erro' => 'Item de checklist não encontrado.'], 404);
        }
        if ($statusTarefa !== 'aberta') {
            responderAnexo(false, ['erro' => 'Não é possível anexar arquivos a uma tarefa encerrada.'], 422);
        }

        if (!isset($_FILES['arquivo']) || !is_array($_FILES['arquivo'])) {
            responderAnexo(false, ['erro' => 'Selecione um arquivo para anexar.'], 422);
        }

        $upload = $_FILES['arquivo'];
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $mensagem = in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'O arquivo excede o limite de 10 MB.'
                : 'Não foi possível receber o arquivo enviado.';
            responderAnexo(false, ['erro' => $mensagem], 422);
        }

        $tamanho = (int)$upload['size'];
        if ($tamanho <= 0 || $tamanho > TAMANHO_MAXIMO_ANEXO) {
            responderAnexo(false, ['erro' => 'Cada arquivo deve ter no máximo 10 MB.'], 422);
        }

        $nomeOriginal = basename(str_replace('\\', '/', (string)$upload['name']));
        $nomeOriginal = trim((string)preg_replace('/[\x00-\x1F\x7F]/', '', $nomeOriginal));
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        $tiposPermitidos = tiposAnexoPermitidos();
        if ($nomeOriginal === '' || !isset($tiposPermitidos[$extensao])) {
            responderAnexo(false, ['erro' => 'Tipo de arquivo não permitido. Use PDF, arquivos Office, PNG/JPG, TXT ou CSV.'], 422);
        }

        if (!is_uploaded_file($upload['tmp_name'])) {
            responderAnexo(false, ['erro' => 'O arquivo enviado não é válido.'], 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($upload['tmp_name']);
        if (!in_array($mimeType, $tiposPermitidos[$extensao], true)) {
            responderAnexo(false, ['erro' => 'O conteúdo do arquivo não corresponde ao tipo informado.'], 422);
        }

        $diretorio = diretorioAnexosChecklist();
        if (!is_dir($diretorio) && !mkdir($diretorio, 0700, true) && !is_dir($diretorio)) {
            throw new RuntimeException('Não foi possível criar o diretório de anexos.');
        }

        $nomeArmazenado = bin2hex(random_bytes(16)) . '.' . $extensao;
        $arquivoMovido = $diretorio . DIRECTORY_SEPARATOR . $nomeArmazenado;
        if (!move_uploaded_file($upload['tmp_name'], $arquivoMovido)) {
            $arquivoMovido = null;
            throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
        }

        $stmt = $pdo->prepare('INSERT INTO checklist_anexos
            (subtarefa_id, nome_original, arquivo, mime_type, tamanho)
            VALUES (:subtarefa_id, :nome_original, :arquivo, :mime_type, :tamanho)');
        $stmt->execute([
            ':subtarefa_id' => $subtarefaId,
            ':nome_original' => substr($nomeOriginal, 0, 255),
            ':arquivo' => $nomeArmazenado,
            ':mime_type' => $mimeType,
            ':tamanho' => $tamanho,
        ]);

        responderAnexo(true, [
            'id' => (int)$pdo->lastInsertId(),
            'nome_original' => $nomeOriginal,
        ], 201);
    }

    responderAnexo(false, ['erro' => 'Método não suportado.'], 405);
} catch (Throwable $e) {
    if ($arquivoMovido !== null && is_file($arquivoMovido) && !unlink($arquivoMovido)) {
        error_log('Não foi possível remover um anexo após falha no registro do banco de dados.');
    }
    responderAnexo(false, ['erro' => 'Erro interno ao processar o anexo: ' . $e->getMessage()], 500);
}
