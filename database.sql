-- ============================================================
-- Sistema de Gestão de Tarefas (Kanban)
-- Banco de dados: kanban_tasks
-- ============================================================

CREATE DATABASE IF NOT EXISTS kanban_tasks
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE kanban_tasks;

-- Colunas do quadro (ex: A Fazer, Em Andamento, Concluído...)
CREATE TABLE IF NOT EXISTS colunas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    cor VARCHAR(20) DEFAULT '#6c757d',
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tarefas (cards)
CREATE TABLE IF NOT EXISTS tarefas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coluna_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    prioridade ENUM('baixa', 'media', 'alta') NOT NULL DEFAULT 'media',
    data_vencimento DATE NULL,
    ordem INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'aberta',
    cancelamento_motivo TEXT NULL,
    encerrada_em TIMESTAMP NULL DEFAULT NULL,
    recorrente TINYINT(1) NOT NULL DEFAULT 0,
    frequencia VARCHAR(20) NOT NULL DEFAULT 'nenhuma',
    dias_semana VARCHAR(13) NULL DEFAULT NULL,
    horario_recorrencia TIME NULL DEFAULT NULL,
    proxima_repeticao DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tarefa_coluna FOREIGN KEY (coluna_id)
        REFERENCES colunas(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tarefas_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarefa_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    mensagem TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_tarefa FOREIGN KEY (tarefa_id)
        REFERENCES tarefas(id) ON DELETE CASCADE
);

-- Colunas padrão
INSERT INTO colunas (nome, cor, ordem) VALUES
    ('A Fazer', '#6c757d', 1),
    ('Em Andamento', '#0d6efd', 2),
    ('Em Revisão', '#ffc107', 3),
    ('Concluído', '#198754', 4);

-- Subtarefas / checklist de cada tarefa
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

-- Documentos anexados aos itens do checklist
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

-- Etiquetas (labels) reutilizáveis
CREATE TABLE IF NOT EXISTS etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(40) NOT NULL,
    cor VARCHAR(20) NOT NULL DEFAULT '#0d6efd',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_etiqueta_nome (nome)
);

-- Relação N:N entre tarefas e etiquetas
CREATE TABLE IF NOT EXISTS tarefa_etiquetas (
    tarefa_id INT NOT NULL,
    etiqueta_id INT NOT NULL,
    PRIMARY KEY (tarefa_id, etiqueta_id),
    CONSTRAINT fk_te_tarefa FOREIGN KEY (tarefa_id) REFERENCES tarefas(id) ON DELETE CASCADE,
    CONSTRAINT fk_te_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas(id) ON DELETE CASCADE
);

-- Data em que a tarefa foi concluída (usado no dashboard "concluídas por semana")
ALTER TABLE tarefas ADD COLUMN concluida_em TIMESTAMP NULL DEFAULT NULL;

-- Etiquetas de exemplo
INSERT INTO etiquetas (nome, cor) VALUES
    ('Urgente', '#dc3545'),
    ('Bloqueado', '#6f42c1'),
    ('Aguardando terceiros', '#fd7e14');

-- Algumas tarefas de exemplo (opcional - pode apagar)
INSERT INTO tarefas (coluna_id, titulo, descricao, prioridade, data_vencimento, ordem) VALUES
    (1, 'Configurar ambiente XAMPP', 'Instalar e configurar Apache, MySQL e PHP para o sistema.', 'media', NULL, 1),
    (2, 'Revisar chamados abertos SGTI', 'Verificar status dos chamados pendentes de equipamento.', 'alta', NULL, 1),
    (4, 'Deploy inicial do sistema', 'Publicado com sucesso no ambiente de testes.', 'baixa', NULL, 1);

-- Checklist de exemplo para a primeira tarefa
INSERT INTO subtarefas (tarefa_id, titulo, concluida, ordem) VALUES
    (1, 'Baixar o XAMPP', 1, 1),
    (1, 'Instalar Apache e MySQL', 1, 2),
    (1, 'Importar database.sql', 0, 3);
