<?php

declare(strict_types=1);

/**
 * Migration idempotente - ETAPA 21A (configuracao de e-mail transacional).
 */

$root = dirname(__DIR__);
require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
spl_autoload_register(function (string $c) use ($root): void {
    if (strncmp($c, 'App\\', 4) !== 0) { return; }
    $f = $root . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (is_file($f)) { require $f; }
});

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

echo "== ETAPA 21A - E-mail Transacional ==\n\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `mail_settings` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `provider` VARCHAR(40) NOT NULL DEFAULT 'gmail',
        `smtp_host` VARCHAR(190) NULL DEFAULT NULL,
        `smtp_port` INT NOT NULL DEFAULT 587,
        `smtp_encryption` VARCHAR(20) NOT NULL DEFAULT 'tls',
        `smtp_username` VARCHAR(190) NULL DEFAULT NULL,
        `smtp_password_encrypted` TEXT NULL DEFAULT NULL,
        `from_name` VARCHAR(190) NULL DEFAULT NULL,
        `from_email` VARCHAR(190) NULL DEFAULT NULL,
        `reply_to_name` VARCHAR(190) NULL DEFAULT NULL,
        `reply_to_email` VARCHAR(190) NULL DEFAULT NULL,
        `enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `dry_run` TINYINT(1) NOT NULL DEFAULT 1,
        `hourly_limit` INT NOT NULL DEFAULT 20,
        `daily_limit` INT NOT NULL DEFAULT 100,
        `last_tested_at` DATETIME NULL DEFAULT NULL,
        `last_test_status` VARCHAR(40) NULL DEFAULT NULL,
        `last_test_message` TEXT NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabela mail_settings garantida\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `email_templates` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_key` VARCHAR(120) NOT NULL,
        `name` VARCHAR(190) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body_text` MEDIUMTEXT NULL DEFAULT NULL,
        `body_html` MEDIUMTEXT NULL DEFAULT NULL,
        `enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_email_templates_event` (`event_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabela email_templates garantida\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `email_outbox` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_key` VARCHAR(120) NOT NULL,
        `recipient_email` VARCHAR(190) NOT NULL,
        `recipient_name` VARCHAR(190) NULL DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body_text` MEDIUMTEXT NULL DEFAULT NULL,
        `body_html` MEDIUMTEXT NULL DEFAULT NULL,
        `payload_json` JSON NULL DEFAULT NULL,
        `status` VARCHAR(40) NOT NULL DEFAULT 'pending',
        `error_message` TEXT NULL DEFAULT NULL,
        `sent_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_email_outbox_event` (`event_key`),
        KEY `idx_email_outbox_status` (`status`),
        KEY `idx_email_outbox_recipient` (`recipient_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabela email_outbox garantida\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `email_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_key` VARCHAR(120) NOT NULL,
        `recipient_email` VARCHAR(190) NOT NULL,
        `recipient_name` VARCHAR(190) NULL DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `status` VARCHAR(40) NOT NULL,
        `error_message` TEXT NULL DEFAULT NULL,
        `payload_json` JSON NULL DEFAULT NULL,
        `sent_at` DATETIME NULL DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_email_logs_event` (`event_key`),
        KEY `idx_email_logs_status` (`status`),
        KEY `idx_email_logs_recipient` (`recipient_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabela email_logs garantida\n";

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `email_event_rules` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_key` VARCHAR(120) NOT NULL,
        `template_event_key` VARCHAR(120) NOT NULL,
        `recipient_type` VARCHAR(40) NOT NULL DEFAULT 'captador',
        `enabled` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_email_event_rules` (`event_key`, `recipient_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "tabela email_event_rules garantida\n";

$pdo->exec(
    "INSERT INTO `mail_settings`
        (`provider`, `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`,
         `from_name`, `from_email`, `reply_to_name`, `reply_to_email`, `enabled`, `dry_run`, `hourly_limit`, `daily_limit`, `created_at`, `updated_at`)
     SELECT 'gmail', 'smtp.gmail.com', 587, 'tls', 'dancacarajas@gmail.com',
            'Danca Carajas Captacao', 'dancacarajas@gmail.com', 'Equipe Danca Carajas', 'dancacarajas@gmail.com',
            0, 1, 20, 100, NOW(), NOW()
      WHERE NOT EXISTS (SELECT 1 FROM `mail_settings`)"
);
echo "configuracao padrao garantida\n";

$templates = [
    ['collector_application_received', 'Manifestacao recebida', 'Recebemos sua manifestacao para ser captador do Danca Carajas'],
    ['collector_documents_requested', 'Solicitacao de documentos', 'Envie seus documentos para seguir no credenciamento'],
    ['collector_documents_completed', 'Documentos completos', 'Documentos recebidos - analise sera iniciada'],
    ['collector_application_rejected', 'Candidatura reprovada', 'Resultado do credenciamento de captador'],
    ['collector_application_approved', 'Candidatura aprovada', 'Seu credenciamento foi aprovado'],
    ['collector_access_released', 'Acesso liberado', 'Acesso liberado ao sistema Danca Carajas Captacao'],
];
$tpl = new \App\Models\EmailTemplate();
foreach ($templates as [$key, $name, $subject]) {
    $tpl->upsert([
        'event_key' => $key,
        'name' => $name,
        'subject' => $subject,
        'body_text' => "Template base para {$name}.\n\nVariaveis serao conectadas na Etapa 21B.",
        'body_html' => "<p>Template base para {$name}.</p><p>Variaveis serao conectadas na Etapa 21B.</p>",
        'enabled' => 1,
    ]);
}
echo "templates base garantidos\n";

$permissions = [
    ['E-mail: visualizar configuracao', 'email_settings.view', 'Visualizar configuracao SMTP transacional'],
    ['E-mail: editar configuracao', 'email_settings.edit', 'Editar configuracao SMTP transacional'],
    ['E-mail: testar envio', 'email_settings.test', 'Enviar teste controlado de e-mail'],
    ['Templates de e-mail: visualizar', 'email_templates.view', 'Visualizar templates transacionais'],
    ['Templates de e-mail: editar', 'email_templates.edit', 'Editar templates transacionais'],
    ['Logs de e-mail: visualizar', 'email_logs.view', 'Visualizar logs transacionais de e-mail'],
];
$st = $pdo->prepare(
    'INSERT INTO `permissions` (`name`, `slug`, `description`)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`)'
);
foreach ($permissions as $permission) {
    $st->execute($permission);
}
echo "permissoes garantidas\n";

$pdo->exec(
    "INSERT INTO `role_permissions` (`role_id`, `permission_id`)
     SELECT r.`id`, p.`id`
       FROM `roles` r
       JOIN `permissions` p ON p.`slug` IN (
            'email_settings.view','email_settings.edit','email_settings.test',
            'email_templates.view','email_templates.edit','email_logs.view'
       )
      WHERE r.`slug` = 'administrador-geral'
     ON DUPLICATE KEY UPDATE `role_id` = `role_permissions`.`role_id`"
);
echo "permissoes concedidas ao administrador-geral\n";

echo "\nMigration ETAPA 21A concluida.\n";
