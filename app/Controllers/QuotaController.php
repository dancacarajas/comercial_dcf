<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\FinancialEntry;
use App\Models\SponsorDossier;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\Contract;
use App\Models\Counterpart;

/**
 * Módulo Cotas de Patrocínio (Etapa 7).
 *
 * Quantidades manuais + resumo calculado a partir das oportunidades vinculadas.
 * Sem exclusão física (arquivamento lógico).
 * Permissões: quotas.view / quotas.create / quotas.edit
 * (edit cobre edição, arquivamento e restauração).
 */
final class QuotaController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('quotas.view');

        $model   = new Quota();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $items = $model->paginate($filters, $page, self::PER_PAGE);

        $this->view('quotas/index', [
            'title'      => 'Cotas de patrocínio',
            'items'      => $items,
            'filters'    => $filters,
            'statuses'   => $model->getStatuses(),
            'model'      => $model,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'perPage'    => self::PER_PAGE,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('quotas.create');

        $this->renderForm('quotas/create', 'Nova cota', ['status' => 'disponivel'], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('quotas.create');
        csrf_verify();

        $model = new Quota();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'create');
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('quotas/create', 'Nova cota', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('quota_created', $_SESSION['user_id'] ?? null, 'quota', $id);

        flash('success', 'Cota cadastrada com sucesso.');
        $this->redirect('/quotas/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('quotas.view');

        $quota = $this->findOr404($params['id'] ?? null);
        $model = new Quota();

        $linkedSummary = [];
        $linked        = [];
        if (can('opportunities.view')) {
            $linkedSummary = $model->linkedOpportunitiesSummary((int) $quota['id']);
            $linked        = $model->linkedOpportunities((int) $quota['id'], 10);
        }

        $proposals       = [];
        $proposalSummary = ['total' => 0, 'sent' => 0, 'open' => 0, 'total_value' => 0.0];
        $proposalModel   = null;
        if (can('proposals.view')) {
            $proposalModel   = new Proposal();
            $proposals       = $proposalModel->findByQuota((int) $quota['id'], 6);
            $proposalSummary = $proposalModel->summaryByQuota((int) $quota['id']);
        }

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByQuota((int) $quota['id'], 6);
            $documentSummary = $documentModel->summaryByQuota((int) $quota['id']);
        }

        $sponsors       = [];
        $sponsorSummary = ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
        $sponsorModel   = null;
        if (can('sponsors.view')) {
            $sponsorModel   = new Sponsor();
            $sponsors       = $sponsorModel->findByQuota((int) $quota['id'], 6);
            $sponsorSummary = $sponsorModel->summaryByQuota((int) $quota['id']);
        }

        $counterparts        = [];
        $counterpartSummary  = ['total' => 0, 'delivered' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0];
        $counterpartModel    = null;
        if (can('counterparts.view')) {
            $counterpartModel   = new Counterpart();
            $counterparts       = $counterpartModel->findByQuota((int) $quota['id'], 6);
            $counterpartSummary = $counterpartModel->summaryByQuota((int) $quota['id']);
        }

        $contracts       = [];
        $contractSummary = ['total' => 0, 'signed' => 0, 'awaiting_signature' => 0, 'vigente' => 0, 'expired' => 0, 'formalized_total' => 0.0];
        $contractModel   = null;
        if (can('contracts.view')) {
            $contractModel   = new Contract();
            $contracts       = $contractModel->findByQuota((int) $quota['id'], 6);
            $contractSummary = $contractModel->summaryByQuota((int) $quota['id']);
        }

        $financials       = [];
        $financialSummary = ['total' => 0, 'planned_total' => 0.0, 'received_total' => 0.0, 'remaining_total' => 0.0, 'received' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0, 'reconciled' => 0];
        $financialModel   = null;
        if (can('financials.view')) {
            $financialModel   = new FinancialEntry();
            $financials       = $financialModel->findByQuota((int) $quota['id'], 6);
            $financialSummary = $financialModel->summaryByQuota((int) $quota['id']);
        }

        $dossiers       = [];
        $dossierSummary = ['total' => 0, 'approved' => 0, 'delivered' => 0, 'pending' => 0, 'with_pending_counterparts' => 0, 'with_overdue_counterparts' => 0, 'with_balance' => 0];
        $dossierModel   = null;
        if (can('dossiers.view')) {
            $dossierModel   = new SponsorDossier();
            $dossiers       = $dossierModel->findByQuota((int) $quota['id'], 6);
            $dossierSummary = $dossierModel->summaryByQuota((int) $quota['id']);
        }

        $this->view('quotas/show', [
            'title'         => $quota['name'] ?? 'Cota',
            'quota'         => $quota,
            'model'         => $model,
            'statuses'      => $model->getStatuses(),
            'idealProfiles' => $model->getIdealProfiles(),
            'remaining'     => $model->remainingQuantity($quota),
            'linkedSummary' => $linkedSummary,
            'linked'        => $linked,
            'canSeeOpps'    => can('opportunities.view'),
            'oppStatusLabels' => (new \App\Models\Opportunity())->getStatusLabels(),
            'proposals'       => $proposals,
            'proposalSummary' => $proposalSummary,
            'proposalModel'   => $proposalModel,
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
            'sponsors'        => $sponsors,
            'sponsorSummary'  => $sponsorSummary,
            'sponsorModel'    => $sponsorModel,
            'counterparts'       => $counterparts,
            'counterpartSummary' => $counterpartSummary,
            'counterpartModel'   => $counterpartModel,
            'contracts'          => $contracts,
            'contractSummary'    => $contractSummary,
            'contractModel'      => $contractModel,
            'financials'         => $financials,
            'financialSummary'   => $financialSummary,
            'financialModel'     => $financialModel,
            'dossiers'           => $dossiers,
            'dossierSummary'     => $dossierSummary,
            'dossierModel'       => $dossierModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('quotas.edit');

        $quota = $this->findOr404($params['id'] ?? null);

        if (!empty($quota['archived_at'])) {
            flash('error', 'Esta cota está arquivada. Restaure-a antes de editar.');
            $this->redirect('/quotas/' . (int) $quota['id']);
            return;
        }

        $this->renderForm('quotas/edit', 'Editar cota', $quota, [], $quota);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('quotas.edit');
        csrf_verify();

        $quota = $this->findOr404($params['id'] ?? null);
        $id    = (int) $quota['id'];

        if (!empty($quota['archived_at'])) {
            flash('error', 'Esta cota está arquivada. Restaure-a antes de editar.');
            $this->redirect('/quotas/' . $id);
            return;
        }

        $model = new Quota();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'update');
        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($quota, $data);
            $this->renderForm('quotas/edit', 'Editar cota', $data, $errors, $merged);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('quota_updated', $_SESSION['user_id'] ?? null, 'quota', $id);

        flash('success', 'Cota atualizada com sucesso.');
        $this->redirect('/quotas/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('quotas.edit');
        csrf_verify();

        $quota = $this->findOr404($params['id'] ?? null);
        $id    = (int) $quota['id'];

        if (empty($quota['archived_at'])) {
            (new Quota())->archive($id);
            (new ActivityLog())->record('quota_archived', $_SESSION['user_id'] ?? null, 'quota', $id);
            flash('success', 'Cota arquivada.');
        }

        $this->redirect('/quotas/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('quotas.edit');
        csrf_verify();

        $quota = $this->findOr404($params['id'] ?? null);
        $id    = (int) $quota['id'];

        if (!empty($quota['archived_at'])) {
            (new Quota())->restore($id);
            (new ActivityLog())->record('quota_restored', $_SESSION['user_id'] ?? null, 'quota', $id);
            flash('success', 'Cota restaurada.');
        }

        $this->redirect('/quotas/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function collectFilters(): array
    {
        return [
            'q'             => (string) input('q', ''),
            'status'        => (string) input('status', ''),
            'amount_min'    => input('amount_min') !== null ? (string) input('amount_min') : '',
            'amount_max'    => input('amount_max') !== null ? (string) input('amount_max') : '',
            'show_archived' => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(Quota $model): array
    {
        return [
            'name'               => clean((string) input('name', '')),
            'commercial_name'    => clean((string) input('commercial_name', '')) ?: null,
            'amount'             => $model->normalizeMoney((string) input('amount', '')),
            'available_quantity' => (int) input('available_quantity', 0),
            'reserved_quantity'  => (int) input('reserved_quantity', 0),
            'closed_quantity'    => (int) input('closed_quantity', 0),
            'description'        => trim((string) input('description', '')) ?: null,
            'ideal_profile'      => clean((string) input('ideal_profile', '')) ?: null,
            'status'             => clean((string) input('status', 'disponivel')),
            'display_order'      => (int) input('display_order', 0),
            'notes'              => trim((string) input('notes', '')) ?: null,
        ];
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $quota
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $quota = []): void
    {
        $model = new Quota();

        $this->view($view, [
            'title'         => $title,
            'old'           => $old,
            'errors'        => $errors,
            'quota'         => $quota,
            'statuses'      => $model->getStatuses(),
            'idealProfiles' => $model->getIdealProfiles(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $quota = is_numeric($id) ? (new Quota())->findById((int) $id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }

        return $quota;
    }
}
