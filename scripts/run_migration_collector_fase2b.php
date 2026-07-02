<?php

declare(strict_types=1);

/**
 * Migration idempotente — Etapa 18C / Fase 2B (Portal do Captador).
 *
 * Cria as permissões do portal do captador e as concede à role captador-externo.
 * Não remove nada da Fase 2 nem do fluxo interno. Pode rodar várias vezes.
 *
 * Uso: php scripts/run_migration_collector_fase2b.php
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

$perms = [
    ['collector_portal.view',             'Portal do captador: acessar'],
    ['collector_portal.companies.create', 'Portal do captador: cadastrar empresa/prospect'],
    ['collector_portal.contacts.create',  'Portal do captador: cadastrar contato'],
    ['collector_portal.deals.view',       'Portal do captador: ver captações próprias'],
    ['collector_portal.deals.note',       'Portal do captador: registrar observações'],
];

$insP = $pdo->prepare('INSERT IGNORE INTO permissions (name, slug, description, created_at, updated_at) VALUES (:n, :s, :d, NOW(), NOW())');
foreach ($perms as [$slug, $name]) {
    $insP->execute(['n' => $name, 's' => $slug, 'd' => $name]);
    echo "permissão ok: {$slug}\n";
}

$role = $pdo->query("SELECT id FROM roles WHERE slug='captador-externo' LIMIT 1")->fetch();
if ($role === false) {
    fwrite(STDERR, "ATENÇÃO: role captador-externo não encontrada; permissões criadas mas não concedidas.\n");
    exit(1);
}
$roleId = (int) $role['id'];

$slugList = "'" . implode("','", array_map(static fn ($p) => $p[0], $perms)) . "'";
$ids = $pdo->query("SELECT id FROM permissions WHERE slug IN ({$slugList})")->fetchAll(PDO::FETCH_COLUMN);
$insRP = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (:r, :p, NOW())');
foreach ($ids as $pid) {
    $insRP->execute(['r' => $roleId, 'p' => (int) $pid]);
}

$operationalPerms = [
    'dashboard.view',
    'incentive_projects.view',
    'companies.view',
    'companies.create',
    'contacts.view',
    'contacts.create',
];
$operationalList = "'" . implode("','", $operationalPerms) . "'";
$opIds = $pdo->query("SELECT id FROM permissions WHERE slug IN ({$operationalList})")->fetchAll(PDO::FETCH_COLUMN);
foreach ($opIds as $pid) {
    $insRP->execute(['r' => $roleId, 'p' => (int) $pid]);
}

$forbidden = [
    'companies.edit',
    'opportunities.view',
    'opportunities.create',
    'opportunities.edit',
    'proposals.view',
    'sponsors.view',
    'contracts.view',
    'financials.view',
    'dossiers.view',
    'reports.view',
    'users.view',
    'permissions.view',
    'collector_applications.view',
    'collector_applications.release_access',
];
$forbiddenList = "'" . implode("','", $forbidden) . "'";
$removed = $pdo->exec(
    "DELETE rp FROM role_permissions rp
      JOIN permissions p ON p.id = rp.permission_id
     WHERE rp.role_id = {$roleId}
       AND p.slug IN ({$forbiddenList})"
);
echo "\nPermissoes internas removidas de captador-externo: " . (int) $removed . "\n";

$granted = $pdo->query(
    "SELECT p.slug FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id
      WHERE rp.role_id = {$roleId} AND p.slug LIKE 'collector_portal%' ORDER BY p.slug"
)->fetchAll(PDO::FETCH_COLUMN);

echo "\nPermissões do portal concedidas a captador-externo:\n";
foreach ($granted as $g) { echo "  - {$g}\n"; }
echo "\nMigration Fase 2B concluída.\n";
exit(0);
