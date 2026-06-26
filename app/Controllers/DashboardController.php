<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Counterpart;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\Task;

/**
 * Painel administrativo protegido (tela institucional inicial).
 *
 * Exibe o controle de acesso e, a partir da Etapa 4, um card mínimo
 * do módulo Empresas (apenas contagem + link de gestão).
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $adminPermissions = [
            'dashboard.view', 'users.view', 'users.create', 'users.edit',
            'users.activate', 'users.deactivate', 'users.reset_password',
            'roles.view', 'roles.edit', 'permissions.view', 'logs.view', 'settings.view',
        ];

        $sessionPerms = $_SESSION['permissions'] ?? [];
        $activeAdmin  = array_values(array_intersect($adminPermissions, is_array($sessionPerms) ? $sessionPerms : []));

        // Cards mínimos de CRM (somente para quem pode visualizar).
        $companiesCount = null;
        if (can('companies.view')) {
            $companiesCount = (new Company())->count(['show_archived' => 0]);
        }

        $contactsCount = null;
        if (can('contacts.view')) {
            $contactsCount = (new Contact())->count(['show_archived' => 0]);
        }

        $opportunitiesOpen = null;
        $opportunitiesValue = null;
        if (can('opportunities.view')) {
            $oppModel           = new Opportunity();
            $opportunitiesOpen  = $oppModel->count(['show_archived' => 0, 'open' => 1]);
            $pipeline           = $oppModel->pipelineSummary(['open' => 1]);
            $value              = 0.0;
            foreach ($oppModel->getOpenStatuses() as $slug) {
                $value += (float) ($pipeline[$slug]['total'] ?? 0);
            }
            $opportunitiesValue = $value;
        }

        $quotasCount     = null;
        $quotasAvailable = null;
        if (can('quotas.view')) {
            $quotaModel      = new Quota();
            $quotasCount     = $quotaModel->count(['show_archived' => 0]);
            $quotasAvailable = $quotaModel->count(['show_archived' => 0, 'status' => 'disponivel']);
        }

        $tasksOpen = null;
        $tasksOverdue = null;
        $tasksToday = null;
        $tasksMine = null;
        if (can('tasks.view')) {
            $taskModel    = new Task();
            $tasksOpen    = $taskModel->countOpen();
            $tasksOverdue = $taskModel->countOverdue();
            $tasksToday   = $taskModel->countDueToday();
            $tasksMine    = $taskModel->countMyOpen((int) ($_SESSION['user_id'] ?? 0));
        }

        $leadsNew = null;
        $leadsTriagem = null;
        $leadsConverted = null;
        $leadsDiscarded = null;
        if (can('leads.view')) {
            $leadModel      = new Lead();
            $leadsNew       = $leadModel->countByStatus('novo');
            $leadsTriagem   = $leadModel->countByStatus('em_triagem');
            $leadsConverted = $leadModel->count(['converted' => 1, 'show_archived' => 0]);
            $leadsDiscarded = $leadModel->countByStatus('descartado');
        }

        $proposalsTotal = null;
        $proposalsSent = null;
        $proposalsOpen = null;
        $proposalsExpired = null;
        $proposalsOpenValue = null;
        if (can('proposals.view')) {
            $proposalModel      = new Proposal();
            $proposalsTotal     = $proposalModel->count(['show_archived' => 0]);
            $proposalsSent      = $proposalModel->countSent();
            $proposalsOpen      = $proposalModel->countOpen();
            $proposalsExpired   = $proposalModel->countExpired();
            $proposalsOpenValue = $proposalModel->sumOpenValue();
        }

        $documentsTotal = null;
        $documentsActive = null;
        $documentsExpiring = null;
        $documentsExpired = null;
        if (can('documents.view')) {
            $documentModel     = new Document();
            $documentsTotal    = $documentModel->count(['show_archived' => 0]);
            $documentsActive   = $documentModel->countActive();
            $documentsExpiring = $documentModel->countExpiringSoon(30);
            $documentsExpired  = $documentModel->countExpired();
        }

        $sponsorsTotal = null;
        $sponsorsConfirmed = null;
        $sponsorsCommitted = null;
        $sponsorsConfirmedAmount = null;
        $sponsorsAwaiting = null;
        $sponsorsOverdue = null;
        if (can('sponsors.view')) {
            $sponsorModel            = new Sponsor();
            $sponsorsTotal           = $sponsorModel->countActive();
            $sponsorsConfirmed       = $sponsorModel->countConfirmed();
            $sponsorsCommitted       = $sponsorModel->sumCommitted();
            $sponsorsConfirmedAmount = $sponsorModel->sumConfirmed();
            $sponsorsAwaiting        = $sponsorModel->countAwaitingContribution();
            $sponsorsOverdue         = $sponsorModel->countOverdue();
        }

        $counterpartsTotal = null;
        $counterpartsPending = null;
        $counterpartsDelivered = null;
        $counterpartsPartial = null;
        $counterpartsOverdue = null;
        if (can('counterparts.view')) {
            $counterpartModel      = new Counterpart();
            $counterpartsTotal     = $counterpartModel->countActive();
            $counterpartsPending   = $counterpartModel->countPending();
            $counterpartsDelivered = $counterpartModel->countDelivered();
            $counterpartsPartial   = $counterpartModel->countPartial();
            $counterpartsOverdue   = $counterpartModel->countOverdue();
        }

        $contractsTotal = null;
        $contractsSigned = null;
        $contractsAwaiting = null;
        $contractsVigente = null;
        $contractsExpired = null;
        $contractsFormalized = null;
        if (can('contracts.view')) {
            $contractModel       = new Contract();
            $contractsTotal      = $contractModel->countActive();
            $contractsSigned     = $contractModel->countSigned();
            $contractsAwaiting   = $contractModel->countAwaitingSignature();
            $contractsVigente    = $contractModel->countVigente();
            $contractsExpired    = $contractModel->countExpired();
            $contractsFormalized = $contractModel->sumFormalized();
        }

        $this->view('dashboard/index', [
            'title'              => 'Painel Administrativo',
            'user'               => $this->currentUser(),
            'roleNames'          => $_SESSION['role_names'] ?? [],
            'adminActive'        => $activeAdmin,
            'companiesCount'     => $companiesCount,
            'contactsCount'      => $contactsCount,
            'opportunitiesOpen'  => $opportunitiesOpen,
            'opportunitiesValue' => $opportunitiesValue,
            'quotasCount'        => $quotasCount,
            'quotasAvailable'    => $quotasAvailable,
            'tasksOpen'          => $tasksOpen,
            'tasksOverdue'       => $tasksOverdue,
            'tasksToday'         => $tasksToday,
            'tasksMine'          => $tasksMine,
            'leadsNew'           => $leadsNew,
            'leadsTriagem'       => $leadsTriagem,
            'leadsConverted'     => $leadsConverted,
            'leadsDiscarded'     => $leadsDiscarded,
            'proposalsTotal'     => $proposalsTotal,
            'proposalsSent'      => $proposalsSent,
            'proposalsOpen'      => $proposalsOpen,
            'proposalsExpired'   => $proposalsExpired,
            'proposalsOpenValue' => $proposalsOpenValue,
            'documentsTotal'     => $documentsTotal,
            'documentsActive'    => $documentsActive,
            'documentsExpiring'  => $documentsExpiring,
            'documentsExpired'   => $documentsExpired,
            'sponsorsTotal'           => $sponsorsTotal,
            'sponsorsConfirmed'       => $sponsorsConfirmed,
            'sponsorsCommitted'       => $sponsorsCommitted,
            'sponsorsConfirmedAmount' => $sponsorsConfirmedAmount,
            'sponsorsAwaiting'        => $sponsorsAwaiting,
            'sponsorsOverdue'         => $sponsorsOverdue,
            'counterpartsTotal'       => $counterpartsTotal,
            'counterpartsPending'     => $counterpartsPending,
            'counterpartsDelivered'   => $counterpartsDelivered,
            'counterpartsPartial'     => $counterpartsPartial,
            'counterpartsOverdue'     => $counterpartsOverdue,
            'contractsTotal'          => $contractsTotal,
            'contractsSigned'         => $contractsSigned,
            'contractsAwaiting'       => $contractsAwaiting,
            'contractsVigente'        => $contractsVigente,
            'contractsExpired'        => $contractsExpired,
            'contractsFormalized'     => $contractsFormalized,
        ], 'layouts/admin');
    }
}
