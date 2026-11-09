-- ============================================================
-- Migração: Recorrência personalizada e horário da recorrência
-- Rode uma vez em instalações existentes de kanban_tasks.
-- Não é necessário em bancos criados com o database.sql atualizado.
-- ============================================================

USE kanban_tasks;

SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'dias_semana'
);
SET @sql = IF(@coluna_existe = 0,
    'ALTER TABLE tarefas ADD COLUMN dias_semana VARCHAR(13) NULL DEFAULT NULL AFTER frequencia',
    'SELECT "Coluna dias_semana já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @coluna_existe = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tarefas' AND COLUMN_NAME = 'horario_recorrencia'
);
SET @sql = IF(@coluna_existe = 0,
    'ALTER TABLE tarefas ADD COLUMN horario_recorrencia TIME NULL DEFAULT NULL AFTER dias_semana',
    'SELECT "Coluna horario_recorrencia já existe"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
