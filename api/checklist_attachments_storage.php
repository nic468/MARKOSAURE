<?php

declare(strict_types=1);

function garantirTabelaAnexosChecklist(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS checklist_anexos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subtarefa_id INT NOT NULL,
        nome_original VARCHAR(255) NOT NULL,
        arquivo VARCHAR(80) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        tamanho BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_checklist_anexo_arquivo (arquivo),
        CONSTRAINT fk_checklist_anexo_subtarefa FOREIGN KEY (subtarefa_id)
            REFERENCES subtarefas(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function caminhoAnexoChecklist(string $arquivo): ?string
{
    if (!preg_match('/\A[a-f0-9]{32}\.[a-z0-9]{1,10}\z/', $arquivo)) {
        return null;
    }

    return __DIR__ . '/../storage/checklist/' . $arquivo;
}

function limparArquivosAnexosChecklist(array $arquivos): array
{
    $avisos = [];
    foreach ($arquivos as $arquivo) {
        $caminho = caminhoAnexoChecklist((string)$arquivo);
        if ($caminho === null || (is_file($caminho) && !unlink($caminho))) {
            $avisos[] = (string)$arquivo;
        }
    }

    if ($avisos) {
        error_log('Não foi possível remover todos os arquivos de anexos do checklist: ' . implode(', ', $avisos));
    }

    return $avisos;
}
