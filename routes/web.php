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

    // Projetos Incentivados / PRONACs / Plano de Captação (Etapa 19)
    // Rotas estaticas antes das dinamicas {id}.
    $router->get('/projects',                       'IncentiveProjectController@index',          ['AuthMiddleware']);
    $router->get('/projects/create',                'IncentiveProjectController@create',         ['AuthMiddleware']);
    $router->post('/projects',                      'IncentiveProjectController@store',          ['AuthMiddleware']);
    $router->get('/projects/{id}',                  'IncentiveProjectController@show',           ['AuthMiddleware']);
    $router->get('/projects/{id}/edit',             'IncentiveProjectController@edit',           ['AuthMiddleware']);
    $router->post('/projects/{id}/update',          'IncentiveProjectController@update',         ['AuthMiddleware']);
    $router->get('/projects/{id}/budget',           'IncentiveProjectController@budget',         ['AuthMiddleware']);
    $router->post('/projects/{id}/budget',          'IncentiveProjectController@budgetStore',    ['AuthMiddleware']);
    $router->post('/projects/{id}/budget/{itemId}/archive', 'IncentiveProjectController@budgetArchive', ['AuthMiddleware']);
    $router->get('/projects/{id}/dashboard',        'IncentiveProjectController@dashboard',      ['AuthMiddleware']);
    $router->post('/projects/{id}/activate-capture','IncentiveProjectController@activateCapture',['AuthMiddleware']);
    $router->post('/projects/{id}/archive',         'IncentiveProjectController@archive',        ['AuthMiddleware']);
    $router->post('/projects/{id}/restore',         'IncentiveProjectController@restore',        ['AuthMiddleware']);

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

    // Rotas contextuais de propostas (Etapa 10) — antes de {id} dinâmico de propostas
    $router->get('/companies/{id}/proposals/create',     'ProposalController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/proposals/create',      'ProposalController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/proposals/create', 'ProposalController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/quotas/{id}/proposals/create',        'ProposalController@createForQuota',        ['AuthMiddleware']);

    // Rotas contextuais de documentos (Etapa 11)
    $router->get('/companies/{id}/documents/create',     'DocumentController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/documents/create',      'DocumentController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/documents/create', 'DocumentController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/quotas/{id}/documents/create',        'DocumentController@createForQuota',        ['AuthMiddleware']);
    $router->get('/proposals/{id}/documents/create',     'DocumentController@createForProposal',     ['AuthMiddleware']);
    $router->get('/leads/{id}/documents/create',         'DocumentController@createForLead',         ['AuthMiddleware']);

    // Rotas contextuais de patrocinadores (Etapa 12)
    $router->get('/companies/{id}/sponsors/create',     'SponsorController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/sponsors/create',      'SponsorController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/sponsors/create', 'SponsorController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/proposals/{id}/sponsors/create',     'SponsorController@createForProposal',     ['AuthMiddleware']);
    $router->get('/quotas/{id}/sponsors/create',        'SponsorController@createForQuota',        ['AuthMiddleware']);

    // Rotas contextuais de contrapartidas (Etapa 13)
    $router->get('/companies/{id}/counterparts/create',     'CounterpartController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/counterparts/create',      'CounterpartController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/counterparts/create', 'CounterpartController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/proposals/{id}/counterparts/create',     'CounterpartController@createForProposal',     ['AuthMiddleware']);
    $router->get('/quotas/{id}/counterparts/create',        'CounterpartController@createForQuota',        ['AuthMiddleware']);
    $router->get('/sponsors/{id}/counterparts/create',      'CounterpartController@createForSponsor',      ['AuthMiddleware']);

    // Rotas contextuais de contratos (Etapa 14)
    $router->get('/companies/{id}/contracts/create',     'ContractController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/contracts/create',      'ContractController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/contracts/create', 'ContractController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/proposals/{id}/contracts/create',     'ContractController@createForProposal',     ['AuthMiddleware']);
    $router->get('/quotas/{id}/contracts/create',        'ContractController@createForQuota',        ['AuthMiddleware']);
    $router->get('/sponsors/{id}/contracts/create',      'ContractController@createForSponsor',      ['AuthMiddleware']);

    // Rotas contextuais de financeiro (Etapa 15)
    $router->get('/companies/{id}/financials/create',     'FinancialController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/financials/create',      'FinancialController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/financials/create', 'FinancialController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/proposals/{id}/financials/create',     'FinancialController@createForProposal',     ['AuthMiddleware']);
    $router->get('/quotas/{id}/financials/create',        'FinancialController@createForQuota',        ['AuthMiddleware']);
    $router->get('/sponsors/{id}/financials/create',      'FinancialController@createForSponsor',      ['AuthMiddleware']);
    $router->get('/contracts/{id}/financials/create',     'FinancialController@createForContract',     ['AuthMiddleware']);

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

    // Propostas comerciais (Etapa 10) — rotas estáticas antes das dinâmicas {id}
    $router->get('/proposals',                    'ProposalController@index',        ['AuthMiddleware']);
    $router->get('/proposals/create',             'ProposalController@create',       ['AuthMiddleware']);
    $router->post('/proposals',                   'ProposalController@store',        ['AuthMiddleware']);
    $router->get('/proposals/{id}',               'ProposalController@show',         ['AuthMiddleware']);
    $router->get('/proposals/{id}/pdf',           'ProposalController@pdf',          ['AuthMiddleware']);
    $router->get('/proposals/{id}/edit',          'ProposalController@edit',         ['AuthMiddleware']);
    $router->post('/proposals/{id}/update',       'ProposalController@update',       ['AuthMiddleware']);
    $router->get('/proposals/{id}/version',       'ProposalController@versionForm',  ['AuthMiddleware']);
    $router->post('/proposals/{id}/version',      'ProposalController@versionStore', ['AuthMiddleware']);
    $router->post('/proposals/{id}/mark-sent',    'ProposalController@markSent',     ['AuthMiddleware']);
    $router->post('/proposals/{id}/status',       'ProposalController@status',       ['AuthMiddleware']);
    $router->post('/proposals/{id}/archive',      'ProposalController@archive',      ['AuthMiddleware']);
    $router->post('/proposals/{id}/restore',      'ProposalController@restore',      ['AuthMiddleware']);

    // Documentos e arquivos (Etapa 11) — rotas estáticas antes das dinâmicas {id}
    $router->get('/documents',                    'DocumentController@index',        ['AuthMiddleware']);
    $router->get('/documents/create',             'DocumentController@create',       ['AuthMiddleware']);
    $router->post('/documents',                   'DocumentController@store',        ['AuthMiddleware']);
    $router->get('/documents/{id}',               'DocumentController@show',         ['AuthMiddleware']);
    $router->get('/documents/{id}/download',      'DocumentController@download',     ['AuthMiddleware']);
    $router->get('/documents/{id}/edit',          'DocumentController@edit',         ['AuthMiddleware']);
    $router->post('/documents/{id}/update',       'DocumentController@update',       ['AuthMiddleware']);
    $router->get('/documents/{id}/version',       'DocumentController@versionForm',  ['AuthMiddleware']);
    $router->post('/documents/{id}/version',      'DocumentController@versionStore', ['AuthMiddleware']);
    $router->post('/documents/{id}/status',       'DocumentController@status',       ['AuthMiddleware']);
    $router->post('/documents/{id}/archive',      'DocumentController@archive',      ['AuthMiddleware']);
    $router->post('/documents/{id}/restore',      'DocumentController@restore',      ['AuthMiddleware']);

    // Patrocinadores / Fechamentos (Etapa 12)
    $router->get('/sponsors',                    'SponsorController@index',        ['AuthMiddleware']);
    $router->get('/sponsors/create',             'SponsorController@create',       ['AuthMiddleware']);
    $router->post('/sponsors',                   'SponsorController@store',        ['AuthMiddleware']);
    $router->get('/sponsors/{id}',               'SponsorController@show',         ['AuthMiddleware']);
    $router->get('/sponsors/{id}/edit',          'SponsorController@edit',         ['AuthMiddleware']);
    $router->post('/sponsors/{id}/update',       'SponsorController@update',       ['AuthMiddleware']);
    $router->post('/sponsors/{id}/confirm',      'SponsorController@confirm',      ['AuthMiddleware']);
    $router->post('/sponsors/{id}/status',       'SponsorController@status',       ['AuthMiddleware']);
    $router->post('/sponsors/{id}/archive',      'SponsorController@archive',      ['AuthMiddleware']);
    $router->post('/sponsors/{id}/restore',      'SponsorController@restore',      ['AuthMiddleware']);
    $router->get('/sponsors/{id}/documents/create', 'DocumentController@createForSponsor', ['AuthMiddleware']);

    // Contrapartidas dos Patrocinadores (Etapa 13)
    $router->get('/counterparts',                    'CounterpartController@index',        ['AuthMiddleware']);
    $router->get('/counterparts/create',             'CounterpartController@create',       ['AuthMiddleware']);
    $router->post('/counterparts',                   'CounterpartController@store',        ['AuthMiddleware']);
    $router->get('/counterparts/{id}',               'CounterpartController@show',         ['AuthMiddleware']);
    $router->get('/counterparts/{id}/edit',          'CounterpartController@edit',         ['AuthMiddleware']);
    $router->post('/counterparts/{id}/update',       'CounterpartController@update',       ['AuthMiddleware']);
    $router->post('/counterparts/{id}/status',       'CounterpartController@status',       ['AuthMiddleware']);
    $router->post('/counterparts/{id}/deliver',      'CounterpartController@deliver',      ['AuthMiddleware']);
    $router->post('/counterparts/{id}/archive',      'CounterpartController@archive',      ['AuthMiddleware']);
    $router->post('/counterparts/{id}/restore',      'CounterpartController@restore',      ['AuthMiddleware']);
    $router->get('/counterparts/{id}/documents/create', 'DocumentController@createForCounterpart', ['AuthMiddleware']);

    // Contratos / Instrumentos de Formalização (Etapa 14)
    $router->get('/contracts',                    'ContractController@index',        ['AuthMiddleware']);
    $router->get('/contracts/create',             'ContractController@create',       ['AuthMiddleware']);
    $router->post('/contracts',                   'ContractController@store',        ['AuthMiddleware']);
    $router->get('/contracts/{id}',               'ContractController@show',         ['AuthMiddleware']);
    $router->get('/contracts/{id}/edit',          'ContractController@edit',         ['AuthMiddleware']);
    $router->post('/contracts/{id}/update',       'ContractController@update',       ['AuthMiddleware']);
    $router->post('/contracts/{id}/status',       'ContractController@status',       ['AuthMiddleware']);
    $router->post('/contracts/{id}/approve',      'ContractController@approve',      ['AuthMiddleware']);
    $router->post('/contracts/{id}/mark-signed',  'ContractController@markSigned',   ['AuthMiddleware']);
    $router->post('/contracts/{id}/archive',      'ContractController@archive',      ['AuthMiddleware']);
    $router->post('/contracts/{id}/restore',      'ContractController@restore',      ['AuthMiddleware']);
    $router->get('/contracts/{id}/documents/create', 'DocumentController@createForContract', ['AuthMiddleware']);

    // Financeiro detalhado (Etapa 15)
    $router->get('/financials',                    'FinancialController@index',        ['AuthMiddleware']);
    $router->get('/financials/create',             'FinancialController@create',       ['AuthMiddleware']);
    $router->post('/financials',                   'FinancialController@store',        ['AuthMiddleware']);
    $router->get('/financials/{id}',               'FinancialController@show',         ['AuthMiddleware']);
    $router->get('/financials/{id}/edit',          'FinancialController@edit',         ['AuthMiddleware']);
    $router->post('/financials/{id}/update',       'FinancialController@update',       ['AuthMiddleware']);
    $router->post('/financials/{id}/status',       'FinancialController@status',       ['AuthMiddleware']);
    $router->post('/financials/{id}/confirm',      'FinancialController@confirm',      ['AuthMiddleware']);
    $router->post('/financials/{id}/reconcile',    'FinancialController@reconcile',    ['AuthMiddleware']);
    $router->post('/financials/{id}/archive',      'FinancialController@archive',      ['AuthMiddleware']);
    $router->post('/financials/{id}/restore',      'FinancialController@restore',      ['AuthMiddleware']);
    $router->get('/financials/{id}/documents/create', 'DocumentController@createForFinancialEntry', ['AuthMiddleware']);

    // Comissoes dos captadores (Etapa 20A - motor de calculo)
    $router->get('/commissions',                         'CollectorCommissionController@index',                ['AuthMiddleware']);
    $router->get('/commissions/pools',                   'CollectorCommissionController@pools',                ['AuthMiddleware']);
    $router->get('/commissions/{id}',                    'CollectorCommissionController@show',                 ['AuthMiddleware']);
    $router->post('/financials/{id}/commissions/recalculate', 'CollectorCommissionController@recalculateFinancial', ['AuthMiddleware']);

    // Rotas contextuais de dossiês (Etapa 16)
    $router->get('/companies/{id}/dossiers/create',     'SponsorDossierController@createForCompany',     ['AuthMiddleware']);
    $router->get('/contacts/{id}/dossiers/create',      'SponsorDossierController@createForContact',      ['AuthMiddleware']);
    $router->get('/opportunities/{id}/dossiers/create', 'SponsorDossierController@createForOpportunity', ['AuthMiddleware']);
    $router->get('/proposals/{id}/dossiers/create',     'SponsorDossierController@createForProposal',     ['AuthMiddleware']);
    $router->get('/quotas/{id}/dossiers/create',        'SponsorDossierController@createForQuota',        ['AuthMiddleware']);
    $router->get('/sponsors/{id}/dossiers/create',      'SponsorDossierController@createForSponsor',      ['AuthMiddleware']);
    $router->get('/contracts/{id}/dossiers/create',     'SponsorDossierController@createForContract',     ['AuthMiddleware']);

    // Dossiês / Prestação Comercial (Etapa 16)
    $router->get('/sponsor-dossiers',                              'SponsorDossierController@index',        ['AuthMiddleware']);
    $router->get('/sponsor-dossiers/create',                       'SponsorDossierController@create',       ['AuthMiddleware']);
    $router->post('/sponsor-dossiers',                             'SponsorDossierController@store',        ['AuthMiddleware']);
    $router->get('/sponsor-dossiers/{id}',                         'SponsorDossierController@show',         ['AuthMiddleware']);
    $router->get('/sponsor-dossiers/{id}/edit',                    'SponsorDossierController@edit',           ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/update',                 'SponsorDossierController@update',       ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/generate',               'SponsorDossierController@generate',     ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/approve',                'SponsorDossierController@approve',      ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/deliver',                'SponsorDossierController@deliver',        ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/status',                 'SponsorDossierController@status',         ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/archive',                'SponsorDossierController@archive',      ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/restore',                'SponsorDossierController@restore',      ['AuthMiddleware']);
    $router->get('/sponsor-dossiers/{id}/print',                   'SponsorDossierController@print',          ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/items',                  'SponsorDossierController@storeItem',    ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/items/{itemId}/update',  'SponsorDossierController@updateItem',   ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/items/{itemId}/archive', 'SponsorDossierController@archiveItem',  ['AuthMiddleware']);
    $router->post('/sponsor-dossiers/{id}/items/{itemId}/restore', 'SponsorDossierController@restoreItem',  ['AuthMiddleware']);
    $router->get('/sponsor-dossiers/{id}/documents/create',        'DocumentController@createForSponsorDossier', ['AuthMiddleware']);

    // Relatórios / Indicadores gerenciais (Etapa 17)
    $router->get('/reports/snapshots',                    'ReportController@snapshots',        ['AuthMiddleware']);
    $router->get('/reports/snapshots/{id}',               'ReportController@snapshotShow',     ['AuthMiddleware']);
    $router->post('/reports/snapshots',                   'ReportController@storeSnapshot',  ['AuthMiddleware']);
    $router->post('/reports/snapshots/{id}/archive',      'ReportController@archiveSnapshot',  ['AuthMiddleware']);
    $router->post('/reports/snapshots/{id}/restore',      'ReportController@restoreSnapshot',  ['AuthMiddleware']);
    $router->get('/reports/print',                        'ReportController@printGeneral',     ['AuthMiddleware']);
    $router->get('/reports/pipeline',                     'ReportController@pipeline',         ['AuthMiddleware']);
    $router->get('/reports/proposals',                    'ReportController@proposals',        ['AuthMiddleware']);
    $router->get('/reports/sponsors',                       'ReportController@sponsors',         ['AuthMiddleware']);
    $router->get('/reports/financials',                   'ReportController@financials',       ['AuthMiddleware']);
    $router->get('/reports/contracts',                    'ReportController@contracts',        ['AuthMiddleware']);
    $router->get('/reports/counterparts',                 'ReportController@counterparts',     ['AuthMiddleware']);
    $router->get('/reports/dossiers',                     'ReportController@dossiers',         ['AuthMiddleware']);
    $router->get('/reports/tasks',                        'ReportController@tasks',            ['AuthMiddleware']);
    $router->get('/reports/leads',                        'ReportController@leads',            ['AuthMiddleware']);
    $router->get('/reports/{reportKey}/print',            'ReportController@printReport',      ['AuthMiddleware']);
    $router->get('/reports',                              'ReportController@index',            ['AuthMiddleware']);

    // Endpoint público de leads (sem AuthMiddleware; CORS para WordPress)
    $router->post('/api/leads/site', 'Api\LeadApiController@site');
    $router->add('OPTIONS', '/api/leads/site', 'Api\LeadApiController@site');

    // Credenciamento de captadores — manifestação pública (Etapa 18)
    $router->post('/api/collectors/site', 'Api\CollectorApplicationApiController@site');
    $router->add('OPTIONS', '/api/collectors/site', 'Api\CollectorApplicationApiController@site');

    // Jornada pública documental (token, sem login)
    $router->get('/captadores/credenciamento/{token}', 'CollectorPublicController@show');
    $router->post('/captadores/credenciamento/{token}/documents', 'CollectorPublicController@upload');
    $router->post('/captadores/credenciamento/{token}/cadastro', 'CollectorPublicController@register');

    // Assinatura eletrônica pública (token, sem login)
    $router->get('/assinatura/{token}', 'SignaturePublicController@show');
    $router->post('/assinatura/{token}/sign', 'SignaturePublicController@sign');
    $router->get('/assinatura/{token}/documento', 'SignaturePublicController@documento');
    $router->get('/assinatura/{token}/auditoria', 'SignaturePublicController@auditoria');
    $router->get('/assinatura/{token}/pdf', 'SignaturePublicController@pdf');
    $router->get('/assinatura/{token}/pdf/download', 'SignaturePublicController@pdfDownload');

    // Modelos de contrato
    $router->get('/contract-templates', 'ContractTemplateController@index', ['AuthMiddleware']);
    $router->get('/contract-templates/create', 'ContractTemplateController@create', ['AuthMiddleware']);
    $router->post('/contract-templates', 'ContractTemplateController@store', ['AuthMiddleware']);
    $router->get('/contract-templates/{id}', 'ContractTemplateController@show', ['AuthMiddleware']);
    $router->get('/contract-templates/{id}/edit', 'ContractTemplateController@edit', ['AuthMiddleware']);
    $router->post('/contract-templates/{id}/update', 'ContractTemplateController@update', ['AuthMiddleware']);
    $router->post('/contract-templates/{id}/archive', 'ContractTemplateController@archive', ['AuthMiddleware']);
    $router->post('/contract-templates/{id}/restore', 'ContractTemplateController@restore', ['AuthMiddleware']);
    $router->get('/contract-templates/{id}/preview', 'ContractTemplateController@preview', ['AuthMiddleware']);

    // Assinaturas internas
    $router->get('/signature-requests', 'SignatureRequestController@index', ['AuthMiddleware']);
    $router->get('/signature-requests/{id}', 'SignatureRequestController@show', ['AuthMiddleware']);
    $router->get('/signature-requests/{id}/pdf', 'SignatureRequestController@pdf', ['AuthMiddleware']);
    $router->post('/signature-requests/{id}/send', 'SignatureRequestController@send', ['AuthMiddleware']);
    $router->post('/signature-requests/{id}/sign-contratante', 'SignatureRequestController@signContratante', ['AuthMiddleware']);
    $router->post('/signature-requests/{id}/cancel', 'SignatureRequestController@cancel', ['AuthMiddleware']);
    $router->post('/signature-requests/{id}/archive', 'SignatureRequestController@archive', ['AuthMiddleware']);
    $router->post('/signature-requests/{id}/restore', 'SignatureRequestController@restore', ['AuthMiddleware']);

    // Credenciamento de captadores — módulo interno
    $router->get('/collector-applications',                              'CollectorApplicationController@index',           ['AuthMiddleware']);
    $router->get('/collector-applications/create',                      'CollectorApplicationController@create',        ['AuthMiddleware']);
    $router->post('/collector-applications',                            'CollectorApplicationController@store',         ['AuthMiddleware']);
    $router->get('/collector-applications/{id}',                          'CollectorApplicationController@show',          ['AuthMiddleware']);
    $router->get('/collector-applications/{id}/edit',                     'CollectorApplicationController@edit',          ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/update',                'CollectorApplicationController@update',        ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/status',                'CollectorApplicationController@status',        ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/request-documents',     'CollectorApplicationController@requestDocuments', ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/review-document',       'CollectorApplicationController@reviewDocument', ['AuthMiddleware']);
    $router->get('/collector-applications/{id}/documents/{documentId}/view',     'CollectorApplicationController@viewDocument', ['AuthMiddleware']);
    $router->get('/collector-applications/{id}/documents/{documentId}/download', 'CollectorApplicationController@downloadDocument', ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/approve',               'CollectorApplicationController@approve',         ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/reject',                'CollectorApplicationController@reject',          ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/generate-contract',     'CollectorApplicationController@generateContract', ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/prepare-access',        'CollectorApplicationController@prepareAccess',   ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/release-access',        'CollectorApplicationController@releaseAccess',   ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/archive',               'CollectorApplicationController@archive',       ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/restore',               'CollectorApplicationController@restore',       ['AuthMiddleware']);

    // Etapa 18C — Cadastro mestre do captador (nested na candidatura)
    $router->get('/collector-applications/{id}/collector/create',       'CollectorController@create',                   ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/collector',             'CollectorController@store',                    ['AuthMiddleware']);
    $router->get('/collector-applications/{id}/collector/edit',         'CollectorController@edit',                     ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/collector/update',      'CollectorController@update',                   ['AuthMiddleware']);
    $router->post('/collector-applications/{id}/collector/validate',    'CollectorController@validateRegistration',     ['AuthMiddleware']);

    // Etapa 18C Fase 2 — Atribuição comercial (nested create/store no captador)
    $router->get('/collectors/{id}/assignments/create',                 'CollectorAssignmentController@create',          ['AuthMiddleware']);
    $router->post('/collectors/{id}/assignments',                       'CollectorAssignmentController@store',           ['AuthMiddleware']);
    $router->post('/collector-assignments/{id}/authorize',              'CollectorAssignmentController@authorize',       ['AuthMiddleware']);
    $router->post('/collector-assignments/{id}/cancel',                 'CollectorAssignmentController@cancel',          ['AuthMiddleware']);
    $router->post('/collector-assignments/{id}/convert',                'CollectorAssignmentController@convert',         ['AuthMiddleware']);

    // Etapa 18C Fase 2 — Captações rastreadas (deals)
    $router->get('/collectors/{id}/deals/create',                       'CollectorDealController@create',               ['AuthMiddleware']);
    $router->post('/collectors/{id}/deals',                             'CollectorDealController@store',                ['AuthMiddleware']);
    $router->get('/collector-deals/{id}/edit',                          'CollectorDealController@edit',                 ['AuthMiddleware']);
    $router->post('/collector-deals/{id}/update',                       'CollectorDealController@update',               ['AuthMiddleware']);
    $router->post('/collector-deals/{id}/archive',                      'CollectorDealController@archive',              ['AuthMiddleware']);


    // Etapa 18C Fase 2B - Portal do Captador (carteira propria do captador externo)
    $router->get('/portal',                          'CollectorPortalController@dashboard',      ['AuthMiddleware']);
    $router->get('/portal/prospects/create',         'CollectorPortalController@prospectCreate', ['AuthMiddleware']);
    $router->post('/portal/prospects',               'CollectorPortalController@prospectStore',  ['AuthMiddleware']);
    $router->get('/portal/deals/{id}',               'CollectorPortalController@dealShow',       ['AuthMiddleware']);
    $router->post('/portal/deals/{id}/note',         'CollectorPortalController@dealNote',       ['AuthMiddleware']);
    $router->post('/portal/companies/{id}/contacts', 'CollectorPortalController@contactStore',   ['AuthMiddleware']);
    // Etapa 18C — Cadastro mestre de captadores (listagem)
    $router->get('/collectors',                                         'CollectorController@index',                    ['AuthMiddleware']);
    $router->get('/collectors/{id}',                                    'CollectorController@show',                     ['AuthMiddleware']);

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
