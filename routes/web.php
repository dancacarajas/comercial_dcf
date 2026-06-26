<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Definicao das rotas web.
 *
 * Retorna uma funcao que recebe o Router e registra as rotas.
 * Handlers no formato 'Controller@metodo' (namespace App\Controllers).
 */

return function (Router $router): void {

    // -----------------------------------------------------------------
    // Instalador web (Etapa 9B) — bloqueado após storage/installed.lock
    // -----------------------------------------------------------------
    $router->get('/install',                 'InstallController@index');
    $router->get('/install/database',       'InstallController@database');
    $router->post('/install/database',      'InstallController@saveDatabase');
    $router->post('/install/test-database', 'InstallController@testDatabase');
    $router->get('/install/system',         'InstallController@system');
    $router->post('/install/system',        'InstallController@saveSystem');
    $router->post('/install/regenerate-token', 'InstallController@regenerateToken');
    $router->get('/install/admin',          'InstallController@admin');
    $router->post('/install/admin',         'InstallController@saveAdmin');
    $router->get('/install/review',         'InstallController@review');
    $router->post('/install/run',           'InstallController@run');
    $router->get('/install/complete',       'InstallController@complete');

    // Tela inicial publica — confirma que a base esta instalada.
    $router->get('/', 'HomeController@index');

    // -----------------------------------------------------------------
    // Autenticacao
    // -----------------------------------------------------------------
    $router->get('/login',  'AuthController@showLogin');
    $router->post('/login', 'AuthController@login');
    $router->post('/logout', 'AuthController@logout');
    $router->get('/forgot-password', 'AuthController@forgot');

    // -----------------------------------------------------------------
    // Area protegida (exige sessao autenticada)
    // A checagem fina de PERMISSAO e feita dentro de cada controller
    // via AuthMiddleware::requirePermission('...').
    // -----------------------------------------------------------------
    $router->get('/dashboard', 'DashboardController@index', ['AuthMiddleware']);

    // Usuarios
    $router->get('/users',                   'UserController@index',        ['AuthMiddleware']);
    $router->get('/users/create',            'UserController@create',       ['AuthMiddleware']);
    $router->post('/users',                  'UserController@store',        ['AuthMiddleware']);
    $router->get('/users/{id}',              'UserController@show',         ['AuthMiddleware']);
    $router->get('/users/{id}/edit',         'UserController@edit',         ['AuthMiddleware']);
    $router->post('/users/{id}/update',      'UserController@update',       ['AuthMiddleware']);
    $router->post('/users/{id}/activate',    'UserController@activate',     ['AuthMiddleware']);
    $router->post('/users/{id}/deactivate',  'UserController@deactivate',   ['AuthMiddleware']);
    $router->post('/users/{id}/reset-password', 'UserController@resetPassword', ['AuthMiddleware']);

    // Empresas / Prospects (Etapa 4) — sem exclusao fisica (arquivamento logico)
    $router->get('/companies',                'CompanyController@index',   ['AuthMiddleware']);
    $router->get('/companies/create',         'CompanyController@create',  ['AuthMiddleware']);
    $router->post('/companies',               'CompanyController@store',   ['AuthMiddleware']);
    $router->get('/companies/{id}',           'CompanyController@show',    ['AuthMiddleware']);
    $router->get('/companies/{id}/edit',      'CompanyController@edit',    ['AuthMiddleware']);
    $router->post('/companies/{id}/update',   'CompanyController@update',  ['AuthMiddleware']);
    $router->post('/companies/{id}/archive',  'CompanyController@archive', ['AuthMiddleware']);
    $router->post('/companies/{id}/restore',  'CompanyController@restore', ['AuthMiddleware']);
    // Rotas contextuais: novo contato / nova oportunidade com a empresa ja selecionada
    $router->get('/companies/{id}/contacts/create',      'ContactController@createForCompany',     ['AuthMiddleware']);
    $router->get('/companies/{id}/opportunities/create', 'OpportunityController@createForCompany', ['AuthMiddleware']);

    // Contatos (Etapa 5) — vinculados obrigatoriamente a uma empresa
    $router->get('/contacts',                'ContactController@index',   ['AuthMiddleware']);
    $router->get('/contacts/create',         'ContactController@create',  ['AuthMiddleware']);
    $router->post('/contacts',               'ContactController@store',   ['AuthMiddleware']);
    // Rota contextual: nova oportunidade com contato/empresa ja selecionados
    $router->get('/contacts/{id}/opportunities/create', 'OpportunityController@createForContact', ['AuthMiddleware']);

    // Rotas contextuais de tarefas (Etapa 8)
    $router->get('/companies/{id}/tasks/create',     'TaskController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/tasks/create',      'TaskController@createForContact',     ['AuthMiddleware']);
    $router->get('/opportunities/{id}/tasks/create', 'TaskController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/contacts/{id}',           'ContactController@show',    ['AuthMiddleware']);
    $router->get('/contacts/{id}/edit',      'ContactController@edit',    ['AuthMiddleware']);
    $router->post('/contacts/{id}/update',   'ContactController@update',  ['AuthMiddleware']);
    $router->post('/contacts/{id}/archive',  'ContactController@archive', ['AuthMiddleware']);
    $router->post('/contacts/{id}/restore',  'ContactController@restore', ['AuthMiddleware']);

    // Oportunidades / CRM de Captacao (Etapa 6) — sem exclusao fisica
    // pipeline e create ANTES de {id} para nao serem capturados pelo curinga.
    $router->get('/opportunities',                 'OpportunityController@index',    ['AuthMiddleware']);
    $router->get('/opportunities/pipeline',        'OpportunityController@pipeline', ['AuthMiddleware']);
    $router->get('/opportunities/create',          'OpportunityController@create',   ['AuthMiddleware']);
    $router->post('/opportunities',                'OpportunityController@store',    ['AuthMiddleware']);
    $router->get('/opportunities/{id}',            'OpportunityController@show',     ['AuthMiddleware']);
    $router->get('/opportunities/{id}/edit',       'OpportunityController@edit',     ['AuthMiddleware']);
    $router->post('/opportunities/{id}/update',    'OpportunityController@update',   ['AuthMiddleware']);
    $router->post('/opportunities/{id}/status',    'OpportunityController@status',   ['AuthMiddleware']);
    $router->post('/opportunities/{id}/archive',   'OpportunityController@archive',  ['AuthMiddleware']);
    $router->post('/opportunities/{id}/restore',   'OpportunityController@restore',  ['AuthMiddleware']);

    // Cotas de patrocinio (Etapa 7) — rotas estaticas antes das dinamicas {id}
    $router->get('/quotas',                'QuotaController@index',   ['AuthMiddleware']);
    $router->get('/quotas/create',         'QuotaController@create',  ['AuthMiddleware']);
    $router->post('/quotas',               'QuotaController@store',   ['AuthMiddleware']);
    $router->get('/quotas/{id}',           'QuotaController@show',    ['AuthMiddleware']);
    $router->get('/quotas/{id}/edit',      'QuotaController@edit',    ['AuthMiddleware']);
    $router->post('/quotas/{id}/update',   'QuotaController@update',  ['AuthMiddleware']);
    $router->post('/quotas/{id}/archive',  'QuotaController@archive', ['AuthMiddleware']);
    $router->post('/quotas/{id}/restore',  'QuotaController@restore', ['AuthMiddleware']);

    // Tarefas e follow-ups (Etapa 8) — rotas estaticas antes das dinamicas {id}
    $router->get('/tasks',                 'TaskController@index',    ['AuthMiddleware']);
    $router->get('/tasks/create',          'TaskController@create',   ['AuthMiddleware']);
    $router->post('/tasks',                'TaskController@store',    ['AuthMiddleware']);
    $router->get('/tasks/{id}',            'TaskController@show',      ['AuthMiddleware']);
    $router->get('/tasks/{id}/edit',       'TaskController@edit',      ['AuthMiddleware']);
    $router->post('/tasks/{id}/update',    'TaskController@update',    ['AuthMiddleware']);
    $router->post('/tasks/{id}/complete',  'TaskController@complete',  ['AuthMiddleware']);
    $router->post('/tasks/{id}/reopen',    'TaskController@reopen',    ['AuthMiddleware']);
    $router->post('/tasks/{id}/archive',   'TaskController@archive',   ['AuthMiddleware']);
    $router->post('/tasks/{id}/restore',   'TaskController@restore',   ['AuthMiddleware']);

    // Leads do site (Etapa 9)
    $router->get('/leads',                      'LeadController@index',         ['AuthMiddleware']);
    $router->get('/leads/create',               'LeadController@create',        ['AuthMiddleware']);
    $router->post('/leads',                     'LeadController@store',         ['AuthMiddleware']);
    $router->get('/leads/{id}',                 'LeadController@show',          ['AuthMiddleware']);
    $router->get('/leads/{id}/edit',            'LeadController@edit',          ['AuthMiddleware']);
    $router->post('/leads/{id}/update',         'LeadController@update',        ['AuthMiddleware']);
    $router->get('/leads/{id}/convert',         'LeadController@convertForm',   ['AuthMiddleware']);
    $router->post('/leads/{id}/convert',        'LeadController@convert',       ['AuthMiddleware']);
    $router->post('/leads/{id}/archive',        'LeadController@archive',       ['AuthMiddleware']);
    $router->post('/leads/{id}/restore',        'LeadController@restore',       ['AuthMiddleware']);
    $router->post('/leads/{id}/mark-duplicate','LeadController@markDuplicate', ['AuthMiddleware']);
    $router->post('/leads/{id}/discard',        'LeadController@discard',       ['AuthMiddleware']);

    // Endpoint público de leads (sem AuthMiddleware; CORS para WordPress)
    $router->post('/api/leads/site', 'Api\LeadApiController@site');
    $router->add('OPTIONS', '/api/leads/site', 'Api\LeadApiController@site');

    // Perfis
    $router->get('/roles',              'RoleController@index',  ['AuthMiddleware']);
    $router->get('/roles/{id}',         'RoleController@show',   ['AuthMiddleware']);
    $router->get('/roles/{id}/edit',    'RoleController@edit',   ['AuthMiddleware']);
    $router->post('/roles/{id}/update', 'RoleController@update', ['AuthMiddleware']);

    // Permissoes
    $router->get('/permissions', 'PermissionController@index', ['AuthMiddleware']);

    // -----------------------------------------------------------------
    // Healthcheck. Em producao (APP_DEBUG=false) NAO expoe dados sensiveis.
    // -----------------------------------------------------------------
    $router->get('/health', function (): void {
        $config = require dirname(__DIR__) . '/config/app.php';
        $debug  = (bool) ($config['debug'] ?? false);

        header('Content-Type: application/json; charset=utf-8');

        if ($debug) {
            echo json_encode([
                'status' => 'ok',
                'php'    => PHP_VERSION,
                'env'    => $config['env'] ?? 'unknown',
                'time'   => date('c'),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Producao: resposta minima.
        echo json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    });
};
