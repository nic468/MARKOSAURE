-- ============================================================
-- Migração: Checklist, Etiquetas e Progresso
-- Rode este script SOMENTE SE você já tinha importado a versão
-- anterior do database.sql. Se está instalando do zero, ignore
-- este arquivo e use apenas o database.sql atualizado.
-- ============================================================

USE kanban_tasks;

CREATE TABLE IF NOT EXISTS subtarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    concluida TINYINT(1) NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subtarefa_tarefa FOREIGN KEY (tarefa_id)
        REFERENCES tarefas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS checklist_anexos (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(40) NOT NULL,
    cor VARCHAR(20) NOT NULL DEFAULT '#0d6efd',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_etiqueta_nome (nome)
);

CREATE TABLE IF NOT EXISTS tarefa_etiquetas (
    tarefa_id INT NOT NULL,
    etiqueta_id INT NOT NULL,
    PRIMARY KEY (tarefa_id, etiqueta_id),
    CONSTRAINT fk_te_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
    CONSTRAINT fk_te_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
);

-- Adiciona a coluna apenas se ela ainda não existir
SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'kanban_tasks' AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'concluida_em'
);
SET @sql = IF(@coluna_existe = 0,
    'ALTER TABLE tarefas ADD COLUMN concluida_em TIMESTAMP NULL DEFAULT NULL',
    'SELECT "Coluna concluida_em já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Campos de encerramento e cancelamento
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'kanban_tasks' AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'status') = 0,
    'ALTER TABLE tarefas ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT ''aberta''',
    'SELECT "Coluna status já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'kanban_tasks' AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'cancelamento_motivo') = 0,
    'ALTER TABLE tarefas ADD COLUMN cancelamento_motivo TEXT NULL',
    'SELECT "Coluna cancelamento_motivo já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'kanban_tasks' AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'encerrada_em') = 0,
    'ALTER TABLE tarefas ADD COLUMN encerrada_em TIMESTAMP NULL DEFAULT NULL',
    'SELECT "Coluna encerrada_em já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO etiquetas (nome, cor) VALUES
    ('Urgente', '#dc3545'),
    ('Bloqueado', '#6f42c1'),
    ('Aguardando terceiros', '#fd7e14');
