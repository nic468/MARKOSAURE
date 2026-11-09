# Sistema de Gestão de Tarefas — Kanban

Quadro Kanban simples, para uso local/pessoal (sem login), em **PHP 8.2 + PDO + MySQL + Bootstrap 5.3**.

## Funcionalidades

- Colunas customizáveis (crie quantas quiser, com cor própria)
- Cards de tarefa com título, descrição, prioridade (baixa/média/alta) e data de vencimento
- Recorrência diária, semanal, mensal, anual ou personalizada por dias da semana, com horário recorrente opcional
- Seção **Próximas a vencer**, com busca própria, ordenação por vencimento e indicadores para tarefas atrasadas, de hoje e futuras
- **Checklist (subtarefas)** em cada tarefa, com barra de progresso no card (ex: 3/5)
- Anexos por item do checklist (PDF, Office, PNG/JPG, TXT e CSV; até 10 MB por arquivo), preservados no Registro
- **Etiquetas coloridas** reutilizáveis (ex: Urgente, Bloqueado, Aguardando terceiros), criadas na hora
- **Busca e filtros** por título, prioridade e etiqueta
- **Dashboard** com KPIs (total, concluídas, em aberto, vencidas) e gráficos: tarefas por coluna, por prioridade, e concluídas nas últimas 8 semanas
- **Registro de tarefas** com visualização dos dados, checklist, etiquetas e histórico de tarefas concluídas e canceladas
- Cancelamento com **motivo obrigatório**, preservado no registro da tarefa
- Apenas a ação explícita **Concluir** encerra uma tarefa; checklist completo e movimentação entre colunas não alteram seu status
- Arrastar e soltar (drag-and-drop) tarefas entre colunas e reordenar dentro da coluna
- Indicador visual de tarefa vencida
- Interface 100% responsiva (Bootstrap 5.3)

## Estrutura do projeto

```
kanban/
├── api/
│   ├── tasks.php        # CRUD de tarefas (inclui progresso de checklist e ids de etiquetas)
│   ├── columns.php      # CRUD de colunas
│   ├── subtasks.php     # CRUD de checklist (subtarefas)
│   ├── checklist_attachments.php # Upload e download de anexos do checklist
│   └── tags.php         # CRUD de etiquetas + vínculo com tarefas
├── assets/
│   ├── css/style.css
│   └── js/
│       ├── kanban.js     # renderização + drag-and-drop + checklist + etiquetas + filtros
│       └── dashboard.js  # gráficos do dashboard (Chart.js)
├── config/
│   └── database.php      # conexão PDO
├── storage/
│   └── checklist/        # arquivos enviados (bloqueados para acesso direto)
├── database.sql                       # script de criação do banco (instalação nova)
├── migration_checklist_etiquetas.sql  # migração para checklist e etiquetas
├── migration_recorrencia_personalizada.sql # migração para dias e horário da recorrência
├── index.php              # página principal do quadro
├── dashboard.php           # página de KPIs e gráficos
├── registro.php            # registro de tarefas encerradas
└── README.md
```

## Instalação (XAMPP)

### Instalação nova
1. Copie a pasta `kanban` inteira para `C:\xampp\htdocs\kanban`
2. Abra o **phpMyAdmin** e importe o arquivo `database.sql`
   — isso cria o banco `kanban_tasks`, todas as tabelas (incluindo checklist e etiquetas) e dados de exemplo.
3. Se seu MySQL tiver usuário/senha diferentes do padrão do XAMPP (`root` sem senha),
   ajuste as constantes em `config/database.php`.
4. Inicie o Apache e o MySQL no painel do XAMPP.
5. Acesse: `http://localhost/kanban/`

Para permitir anexos de até 10 MB, confira no `php.ini` se `upload_max_filesize` está em pelo menos `10M`
e `post_max_size` em pelo menos `12M`; reinicie o Apache após alterar esses valores.

### Já tinha instalado a versão anterior?
Importe no phpMyAdmin as migrações necessárias para a versão do seu banco antes de usar as novas funções:
`migration_checklist_etiquetas.sql` (checklist e etiquetas). As colunas de recorrência ausentes
(`dias_semana` e `horario_recorrencia`) são adicionadas automaticamente pela API de tarefas; se preferir,
`migration_recorrencia_personalizada.sql` também pode ser executada manualmente e é segura para reexecução.

## Como usar

- **Nova tarefa**: botão no topo, ou clique em "Adicionar coluna" para criar uma nova etapa do fluxo.
- **Editar tarefa**: clique no card.
- **Recorrência**: ative "Atividade repetitiva" e escolha uma frequência. Em "Personalizada", selecione ao menos um dia da semana; o horário é opcional e informativo. A próxima ocorrência só é criada quando a tarefa recorrente atual é concluída.
- **Checklist**: dentro do card (após salvo), adicione itens e marque conforme for concluindo — o progresso aparece direto no card do quadro.
- **Anexos do checklist**: use "Anexar documento" em um item do checklist. Os arquivos ficam disponíveis nos detalhes do Registro após concluir ou cancelar a tarefa.
- **Etiquetas**: dentro do card, clique no nome para ativar/desativar; use o ícone de lápis para editar nome e cor, ou crie uma nova com nome + cor.
- **Buscar/filtrar**: use a barra acima do quadro para filtrar por texto, prioridade ou etiqueta.
- **Próximas a vencer**: use a busca própria da seção para localizar tarefas abertas por título, sem alterar os filtros do quadro.
- **Mover tarefa**: arraste o card para outra coluna, ou reordene dentro da mesma coluna. Isso muda somente a coluna; para encerrar, use **Concluir**.
- **Dashboard**: clique em "Dashboard" no topo para ver KPIs e gráficos.
- **Registro**: clique em "Registro" para consultar tarefas encerradas e visualizar todos os dados e o histórico de cada tarefa.
- **Concluir tarefa**: abra uma tarefa salva e clique em "Concluir" para arquivá-la no Registro.
- **Excluir tarefa**: abra o card e clique em "Excluir".

## Possíveis evoluções futuras

- Autenticação via LDAP/AD (`ECBSA.LOCAL`), no mesmo padrão do SGTI/SIGTEM, caso o uso deixe de ser pessoal
- Reordenação de colunas (drag horizontal) e edição de colunas existentes
- Anexos e comentários por tarefa
- Exportação de relatório (PDF/Excel) das tarefas concluídas
- Notificações/lembretes de vencimento e tarefas recorrentes
