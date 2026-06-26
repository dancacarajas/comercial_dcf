-- =====================================================================
-- Migração — Etapa 3 (Usuários, Perfis e Permissões)
--
-- Idempotente: usa `slug` como referência e ON DUPLICATE KEY UPDATE,
-- podendo ser executada mais de uma vez sem duplicar registros.
-- Compatível com MySQL 5.7+/MariaDB 10.3+.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Perfis obrigatórios
-- ---------------------------------------------------------------------
INSERT INTO `roles` (`name`, `slug`, `description`) VALUES
    ('Administrador Geral',     'administrador-geral',  'Acesso total ao sistema'),
    ('Captação / Comercial',    'captacao-comercial',   'Operação comercial (preparado para CRM)'),
    ('Produção / Coordenação',  'producao-coordenacao', 'Patrocinadores fechados e contrapartidas'),
    ('Comunicação',             'comunicacao',          'Entregas de marca e comprovação de comunicação'),
    ('Leitura / Consulta',      'leitura-consulta',     'Somente leitura')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- ---------------------------------------------------------------------
-- 2) Permissões administrativas + reservadas (módulos futuros)
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `description`) VALUES
    -- Administrativas
    ('Acessar painel',        'dashboard.view',        'Visualizar o painel'),
    ('Ver usuários',          'users.view',            'Listar e visualizar usuários'),
    ('Criar usuários',        'users.create',          'Criar usuários'),
    ('Editar usuários',       'users.edit',            'Editar usuários'),
    ('Excluir usuários',      'users.delete',          'Excluir usuários (reservado)'),
    ('Ativar usuários',       'users.activate',        'Ativar usuários'),
    ('Inativar usuários',     'users.deactivate',      'Inativar usuários'),
    ('Redefinir senha',       'users.reset_password',  'Redefinir senha provisória'),
    ('Ver perfis',            'roles.view',            'Listar e visualizar perfis'),
    ('Criar perfis',          'roles.create',          'Criar perfis'),
    ('Editar perfis',         'roles.edit',            'Editar permissões de perfis'),
    ('Excluir perfis',        'roles.delete',          'Excluir perfis (reservado)'),
    ('Ver permissões',        'permissions.view',      'Listar permissões'),
    ('Atribuir permissões',   'permissions.assign',    'Atribuir permissões a perfis'),
    ('Ver logs',              'logs.view',             'Visualizar logs de atividade'),
    ('Ver configurações',     'settings.view',         'Visualizar configurações'),
    -- Reservadas (módulos futuros, sem telas nesta etapa)
    ('Ver empresas',          'companies.view',        'Reservada para módulo futuro'),
    ('Criar empresas',        'companies.create',      'Reservada para módulo futuro'),
    ('Editar empresas',       'companies.edit',        'Reservada para módulo futuro'),
    ('Ver contatos',          'contacts.view',         'Reservada para módulo futuro'),
    ('Criar contatos',        'contacts.create',       'Reservada para módulo futuro'),
    ('Editar contatos',       'contacts.edit',         'Reservada para módulo futuro'),
    ('Ver oportunidades',     'opportunities.view',    'Reservada para módulo futuro'),
    ('Criar oportunidades',   'opportunities.create',  'Reservada para módulo futuro'),
    ('Editar oportunidades',  'opportunities.edit',    'Reservada para módulo futuro'),
    ('Ver cotas',             'quotas.view',           'Reservada para módulo futuro'),
    ('Ver tarefas',           'tasks.view',            'Reservada para módulo futuro'),
    ('Ver leads',             'leads.view',            'Reservada para módulo futuro'),
    ('Ver propostas',         'proposals.view',        'Reservada para módulo futuro'),
    ('Ver documentos',        'documents.view',        'Reservada para módulo futuro'),
    ('Ver patrocinadores',    'sponsors.view',         'Reservada para módulo futuro'),
    ('Ver contrapartidas',    'counterparts.view',     'Reservada para módulo futuro'),
    ('Ver relatórios',        'reports.view',          'Reservada para módulo futuro')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- ---------------------------------------------------------------------
-- 3) Matriz de permissões por perfil
-- ---------------------------------------------------------------------

-- Administrador Geral: TODAS as permissões
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`slug` = 'administrador-geral'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Captação / Comercial
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view',
    'companies.view', 'companies.create', 'companies.edit',
    'contacts.view', 'contacts.create', 'contacts.edit',
    'opportunities.view', 'opportunities.create', 'opportunities.edit',
    'quotas.view', 'tasks.view', 'leads.view', 'proposals.view', 'documents.view'
)
WHERE r.`slug` = 'captacao-comercial'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Produção / Coordenação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'documents.view', 'reports.view'
)
WHERE r.`slug` = 'producao-coordenacao'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Comunicação
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'sponsors.view', 'counterparts.view', 'documents.view', 'reports.view'
)
WHERE r.`slug` = 'comunicacao'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- Leitura / Consulta
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`slug` IN (
    'dashboard.view', 'companies.view', 'contacts.view', 'opportunities.view',
    'quotas.view', 'tasks.view', 'leads.view', 'proposals.view',
    'documents.view', 'sponsors.view', 'counterparts.view', 'reports.view'
)
WHERE r.`slug` = 'leitura-consulta'
ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`;

-- ---------------------------------------------------------------------
-- 4) Garante que o admin inicial tenha o perfil Administrador Geral
-- ---------------------------------------------------------------------
INSERT INTO `user_roles` (`user_id`, `role_id`)
SELECT u.`id`, r.`id`
FROM `users` u
JOIN `roles` r ON r.`slug` = 'administrador-geral'
WHERE u.`email` = 'admin@dancacarajas.com'
ON DUPLICATE KEY UPDATE `user_id` = `user_roles`.`user_id`;
