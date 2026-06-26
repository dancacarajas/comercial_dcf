<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Counterpart;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\User;

/**
 * Módulo Patrocinadores / Fechamentos Comerciais (Etapa 12).
 */
final class SponsorController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('sponsors.view');

        $model   = new Sponsor();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('sponsors/index', [
            'title'             => 'Patrocinadores / Fechamentos',
            'items'             => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'           => $filters,
            'sponsorshipTypes'  => $model->getSponsorshipTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'statuses'          => $model->getStatuses(),
            'paymentStatuses'   => $model->getPaymentStatuses(),
            'model'             => $model,
            'companies'         => $this->companyFilterOptions($filters),
            'contacts'          => $this->linkOptions('contacts', 'name'),
            'opportunities'     => $this->linkOptions('opportunities', 'title'),
            'proposals'         => $this->linkOptions('proposals', 'title'),
            'quotas'            => (new Quota())->activeOptions(),
            'users'             => (new User())->activeList(),
            'page'              => $page,
            'pages'             => $pages,
            'total'             => $total,
            'perPage'           => self::PER_PAGE,
            'hasFilters'        => $this->hasActiveFilters($filters),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'sponsorship_type'    => 'patrocinio_direto',
            'funding_mechanism'   => 'lei_rouanet',
            'status'              => 'fechamento_registrado',
            'payment_status'      => 'pendente',
            'project_year'        => 2026,
            'festival_edition'    => 'Dança Carajás Festival 2026',
            'closed_at'           => date('Y-m-d H:i'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $id = (int) ($params['id'] ?? 0);
        $co = $id > 0 ? (new Company())->findById($id) : null;
        if ($co === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'company_id' => $id,
            'sponsor_display_name' => (string) ($co['name'] ?? ''),
            'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
            'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
            'project_year' => 2026, 'festival_edition' => 'Dança Carajás Festival 2026',
            'closed_at' => date('Y-m-d H:i'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'company_id' => (int) $contact['company_id'], 'contact_id' => $id,
            'sponsor_display_name' => (string) ($contact['name'] ?? ''),
            'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
            'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
            'project_year' => 2026, 'festival_edition' => 'Dança Carajás Festival 2026',
            'closed_at' => date('Y-m-d H:i'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'company_id' => (int) $opp['company_id'],
            'contact_id' => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id' => $id,
            'quota_id' => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'committed_amount' => $opp['estimated_value'],
            'sponsor_display_name' => (string) ($opp['title'] ?? ''),
            'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
            'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
            'project_year' => 2026, 'festival_edition' => 'Dança Carajás Festival 2026',
            'closed_at' => date('Y-m-d H:i'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $id   = (int) ($params['id'] ?? 0);
        $prop = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($prop === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'company_id' => (int) $prop['company_id'],
            'contact_id' => $prop['contact_id'] ? (int) $prop['contact_id'] : null,
            'opportunity_id' => $prop['opportunity_id'] ? (int) $prop['opportunity_id'] : null,
            'proposal_id' => $id,
            'quota_id' => $prop['quota_id'] ? (int) $prop['quota_id'] : null,
            'committed_amount' => $prop['proposed_value'],
            'sponsor_display_name' => (string) ($prop['title'] ?? ''),
            'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
            'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
            'project_year' => 2026, 'festival_edition' => 'Dança Carajás Festival 2026',
            'closed_at' => date('Y-m-d H:i'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('sponsors/create', 'Novo fechamento comercial', $this->prefillFromQuery([
            'quota_id' => $id,
            'committed_amount' => $quota['amount'],
            'sponsor_display_name' => (string) ($quota['name'] ?? ''),
            'sponsorship_type' => 'patrocinio_direto', 'funding_mechanism' => 'lei_rouanet',
            'status' => 'fechamento_registrado', 'payment_status' => 'pendente',
            'project_year' => 2026, 'festival_edition' => 'Dança Carajás Festival 2026',
            'closed_at' => date('Y-m-d H:i'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('sponsors.create');
        csrf_verify();

        $model = new Sponsor();
        $data  = $this->collectInput($model);
        $data  = $this->applyAutofill($data);

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('sponsors/create', 'Novo fechamento comercial', $data, $errors);
            return;
        }

        $model->applyQuotaSnapshot($data);
        $this->applyConfirmDefaults($data);

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('sponsor_created', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        $this->maybeCloseLinked((int) $id, $data);

        flash('success', 'Fechamento comercial registrado com sucesso.');
        $this->redirect('/sponsors/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.view');
        $sponsor = $this->findOr404($params['id'] ?? null);
        $model   = new Sponsor();
        $sid     = (int) $sponsor['id'];

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findBySponsor($sid, 10);
            $documentSummary = $documentModel->summaryBySponsor($sid);
        }

        $counterparts       = [];
        $counterpartSummary = ['total' => 0, 'delivered' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0];
        $counterpartModel   = null;
        if (can('counterparts.view')) {
            $counterpartModel   = new Counterpart();
            $counterparts       = $counterpartModel->findBySponsor($sid, 10);
            $counterpartSummary = $counterpartModel->summaryBySponsor($sid);
        }

        $contracts       = [];
        $contractSummary = ['total' => 0, 'signed' => 0, 'awaiting_signature' => 0, 'vigente' => 0, 'expired' => 0, 'formalized_total' => 0.0];
        $contractModel   = null;
        if (can('contracts.view')) {
            $contractModel   = new Contract();
            $contracts       = $contractModel->findBySponsor($sid, 10);
            $contractSummary = $contractModel->summaryBySponsor($sid);
        }

        $this->view('sponsors/show', [
            'title'           => $sponsor['sponsor_display_name'] ?? 'Patrocinador',
            'sponsor'         => $sponsor,
            'model'           => $model,
            'sponsorshipTypes'=> $model->getSponsorshipTypes(),
            'fundingMechanisms'=> $model->getFundingMechanisms(),
            'statuses'        => $model->getStatuses(),
            'paymentStatuses' => $model->getPaymentStatuses(),
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
            'counterparts'       => $counterparts,
            'counterpartSummary' => $counterpartSummary,
            'counterpartModel'   => $counterpartModel,
            'contracts'          => $contracts,
            'contractSummary'    => $contractSummary,
            'contractModel'      => $contractModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.edit');
        $sponsor = $this->findOr404($params['id'] ?? null);

        if (!empty($sponsor['archived_at'])) {
            flash('error', 'Este fechamento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/sponsors/' . (int) $sponsor['id']);
            return;
        }

        $this->renderForm('sponsors/edit', 'Editar fechamento', $sponsor, [], $sponsor);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.edit');
        csrf_verify();

        $sponsor = $this->findOr404($params['id'] ?? null);
        $id      = (int) $sponsor['id'];

        if (!empty($sponsor['archived_at'])) {
            flash('error', 'Este fechamento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/sponsors/' . $id);
            return;
        }

        $model  = new Sponsor();
        $data   = $this->collectInput($model);
        $data   = $this->applyAutofill($data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('sponsors/edit', 'Editar fechamento', $data, $errors, array_merge($sponsor, $data));
            return;
        }

        $statusChanged  = (string) $sponsor['status'] !== (string) ($data['status'] ?? '');
        $paymentChanged = (string) $sponsor['payment_status'] !== (string) ($data['payment_status'] ?? '');
        $quotaChanged   = (int) ($sponsor['quota_id'] ?? 0) !== (int) ($data['quota_id'] ?? 0);

        if ($quotaChanged) {
            $model->applyQuotaSnapshot($data);
        }

        $this->applyConfirmDefaults($data, $sponsor);
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('sponsor_updated', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('sponsor_status_changed', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        }
        if ($paymentChanged) {
            (new ActivityLog())->record('sponsor_payment_status_changed', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        }

        flash('success', 'Fechamento atualizado com sucesso.');
        $this->redirect('/sponsors/' . $id);
    }

    public function confirm(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.confirm');
        csrf_verify();

        $sponsor = $this->findOr404($params['id'] ?? null);
        $id      = (int) $sponsor['id'];
        $userId  = $_SESSION['user_id'] ?? null;

        (new Sponsor())->confirm($id, $userId ?? 0);
        (new ActivityLog())->record('sponsor_confirmed', $userId, 'sponsor', $id);

        flash('success', 'Fechamento confirmado.');
        $this->redirect('/sponsors/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.status');
        csrf_verify();

        $sponsor = $this->findOr404($params['id'] ?? null);
        $id      = (int) $sponsor['id'];
        $model   = new Sponsor();

        $newStatus  = clean((string) input('status', (string) ($sponsor['status'] ?? '')));
        $newPayment = clean((string) input('payment_status', (string) ($sponsor['payment_status'] ?? '')));
        $note       = trim((string) input('notes', ''));

        $errors = [];
        if (!array_key_exists($newStatus, $model->getStatuses())) {
            $errors['status'] = 'Status inválido.';
        }
        if (!array_key_exists($newPayment, $model->getPaymentStatuses())) {
            $errors['payment_status'] = 'Status de pagamento inválido.';
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/sponsors/' . $id);
            return;
        }

        $patch = [
            'status'         => $newStatus,
            'payment_status' => $newPayment,
            'updated_by'     => $_SESSION['user_id'] ?? null,
        ];

        if ($note !== '') {
            $prev = trim((string) ($sponsor['notes'] ?? ''));
            $patch['notes'] = $prev === '' ? $note : ($prev . "\n[" . date('Y-m-d H:i') . "] " . $note);
        }

        if ($newStatus === 'confirmado' && empty($sponsor['confirmed_at'])) {
            $patch['confirmed_at'] = date('Y-m-d H:i:s');
            $patch['confirmed_by'] = $_SESSION['user_id'] ?? null;
        }

        if ($newPayment === 'recebido' && empty($sponsor['received_at'])) {
            $patch['received_at'] = date('Y-m-d');
        }

        $statusChanged  = (string) $sponsor['status'] !== $newStatus;
        $paymentChanged = (string) $sponsor['payment_status'] !== $newPayment;

        $model->updateStatus($id, $patch);

        if ($statusChanged) {
            (new ActivityLog())->record('sponsor_status_changed', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        }
        if ($paymentChanged) {
            (new ActivityLog())->record('sponsor_payment_status_changed', $_SESSION['user_id'] ?? null, 'sponsor', $id);
        }

        flash('success', 'Status atualizado.');
        $this->redirect('/sponsors/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.archive');
        csrf_verify();

        $sponsor = $this->findOr404($params['id'] ?? null);
        $id      = (int) $sponsor['id'];

        (new Sponsor())->archive($id);
        (new ActivityLog())->record('sponsor_archived', $_SESSION['user_id'] ?? null, 'sponsor', $id);

        flash('success', 'Fechamento arquivado.');
        $this->redirect('/sponsors/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('sponsors.archive');
        csrf_verify();

        $sponsor = $this->findOr404($params['id'] ?? null);
        $id      = (int) $sponsor['id'];

        (new Sponsor())->restore($id);
        (new ActivityLog())->record('sponsor_restored', $_SESSION['user_id'] ?? null, 'sponsor', $id);

        flash('success', 'Fechamento restaurado.');
        $this->redirect('/sponsors/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                    => (string) input('q', ''),
            'company_id'           => (int) input('company_id', 0),
            'contact_id'           => (int) input('contact_id', 0),
            'opportunity_id'       => (int) input('opportunity_id', 0),
            'proposal_id'          => (int) input('proposal_id', 0),
            'quota_id'             => (int) input('quota_id', 0),
            'sponsorship_type'     => (string) input('sponsorship_type', ''),
            'funding_mechanism'    => (string) input('funding_mechanism', ''),
            'status'               => (string) input('status', ''),
            'payment_status'       => (string) input('payment_status', ''),
            'responsible_user_id'  => (int) input('responsible_user_id', 0),
            'project_year'         => (int) input('project_year', 0),
            'awaiting_contribution'=> input('awaiting_contribution') !== null ? 1 : 0,
            'overdue'              => input('overdue') !== null ? 1 : 0,
            'closed_from'          => (string) input('closed_from', ''),
            'closed_to'            => (string) input('closed_to', ''),
            'confirmed_from'       => (string) input('confirmed_from', ''),
            'confirmed_to'         => (string) input('confirmed_to', ''),
            'show_archived'        => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Sponsor $model): array
    {
        $companyId = (int) input('company_id', 0);

        $displayName = clean((string) input('sponsor_display_name', ''));
        if ($displayName === '' && $companyId > 0) {
            $co = (new Company())->findById($companyId);
            if ($co !== null) {
                $displayName = (string) ($co['name'] ?? '');
            }
        }

        return [
            'company_id'                 => $companyId,
            'contact_id'                 => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'             => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'proposal_id'                => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'quota_id'                   => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'primary_document_id'        => input('primary_document_id') !== null && input('primary_document_id') !== '' ? (int) input('primary_document_id') : null,
            'sponsor_display_name'       => $displayName,
            'sponsorship_type'           => clean((string) input('sponsorship_type', 'patrocinio_direto')),
            'funding_mechanism'          => clean((string) input('funding_mechanism', 'lei_rouanet')),
            'project_year'               => (int) input('project_year', 2026),
            'festival_edition'           => clean((string) input('festival_edition', 'Dança Carajás Festival 2026')),
            'committed_amount'           => $model->normalizeMoney((string) input('committed_amount', '')),
            'confirmed_amount'           => $model->normalizeMoney((string) input('confirmed_amount', '')),
            'in_kind_description'        => trim((string) input('in_kind_description', '')) ?: null,
            'in_kind_estimated_value'    => $model->normalizeMoney((string) input('in_kind_estimated_value', '')),
            'status'                     => clean((string) input('status', 'fechamento_registrado')),
            'payment_status'             => clean((string) input('payment_status', 'pendente')),
            'closed_at'                  => $model->normalizeDateTime((string) input('closed_at', '')),
            'confirmed_at'               => $model->normalizeDateTime((string) input('confirmed_at', '')),
            'expected_payment_date'      => $model->normalizeDate((string) input('expected_payment_date', '')),
            'received_at'                => $model->normalizeDate((string) input('received_at', '')),
            'public_announcement_allowed'=> input('public_announcement_allowed') !== null ? 1 : 0,
            'pronac_number'              => clean((string) input('pronac_number', '')) ?: null,
            'incentive_law'              => clean((string) input('incentive_law', '')) ?: null,
            'incentive_notes'            => trim((string) input('incentive_notes', '')) ?: null,
            'responsible_user_id'        => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
            'notes'                      => trim((string) input('notes', '')) ?: null,
            'internal_notes'             => trim((string) input('internal_notes', '')) ?: null,
            'close_linked'               => ($cl = input('close_linked')) !== null && $cl !== '' && $cl !== '0' ? 1 : 0,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach (['company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'primary_document_id'] as $k) {
            $q = input($k);
            if ($q !== null && $q !== '') {
                $data[$k] = (int) $q;
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyAutofill(array $data): array
    {
        if (!empty($data['proposal_id'])) {
            $prop = (new Proposal())->findById((int) $data['proposal_id']);
            if ($prop !== null) {
                if (empty($data['company_id'])) {
                    $data['company_id'] = (int) $prop['company_id'];
                }
                if (empty($data['contact_id']) && !empty($prop['contact_id'])) {
                    $data['contact_id'] = (int) $prop['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($prop['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $prop['opportunity_id'];
                }
                if (empty($data['quota_id']) && !empty($prop['quota_id'])) {
                    $data['quota_id'] = (int) $prop['quota_id'];
                }
                if (($data['committed_amount'] ?? null) === null && $prop['proposed_value'] !== null) {
                    $data['committed_amount'] = $prop['proposed_value'];
                }
                if (empty($data['sponsor_display_name'])) {
                    $data['sponsor_display_name'] = (string) ($prop['title'] ?? '');
                }
            }
        }

        if (!empty($data['opportunity_id'])) {
            $opp = (new Opportunity())->findById((int) $data['opportunity_id']);
            if ($opp !== null) {
                if (empty($data['company_id'])) {
                    $data['company_id'] = (int) $opp['company_id'];
                }
                if (empty($data['contact_id']) && !empty($opp['contact_id'])) {
                    $data['contact_id'] = (int) $opp['contact_id'];
                }
                if (empty($data['quota_id']) && !empty($opp['quota_id'])) {
                    $data['quota_id'] = (int) $opp['quota_id'];
                }
                if (($data['committed_amount'] ?? null) === null && $opp['estimated_value'] !== null) {
                    $data['committed_amount'] = $opp['estimated_value'];
                }
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $existing */
    private function applyConfirmDefaults(array &$data, array $existing = []): void
    {
        if (($data['status'] ?? '') === 'confirmado') {
            if (empty($data['confirmed_at']) && empty($existing['confirmed_at'])) {
                $data['confirmed_at'] = date('Y-m-d H:i:s');
            }
            if (empty($existing['confirmed_by'])) {
                $data['confirmed_by'] = $_SESSION['user_id'] ?? null;
            }
        }

        if (($data['payment_status'] ?? '') === 'recebido' && empty($data['received_at']) && empty($existing['received_at'])) {
            $data['received_at'] = date('Y-m-d');
        }
    }

    /** @param array<string, mixed> $data */
    private function maybeCloseLinked(int $sponsorId, array $data): void
    {
        if (empty($data['close_linked'])) {
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;

        $oppId = (int) ($data['opportunity_id'] ?? 0);
        if ($oppId > 0) {
            (new Opportunity())->updateStatus($oppId, ['status' => 'fechado', 'updated_by' => $userId]);
            (new ActivityLog())->record('opportunity_status_changed', $userId, 'opportunity', $oppId);
        }

        $propId = (int) ($data['proposal_id'] ?? 0);
        if ($propId > 0) {
            (new Proposal())->update($propId, ['status' => 'fechada', 'updated_by' => $userId]);
            (new ActivityLog())->record('proposal_status_changed', $userId, 'proposal', $propId);
        }
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function validateLinks(array $data, array &$errors): void
    {
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && (new Company())->findById($companyId) === null) {
            $errors['company_id'] = 'Empresa não encontrada.';
        }

        $contactId = (int) ($data['contact_id'] ?? 0);
        if ($contactId > 0) {
            $contact = (new Contact())->findById($contactId);
            if ($contact === null) {
                $errors['contact_id'] = 'Contato não encontrado.';
            } elseif ($companyId > 0 && (int) $contact['company_id'] !== $companyId) {
                $errors['contact_id'] = 'O contato não pertence à empresa selecionada.';
            }
        }

        $oppId = (int) ($data['opportunity_id'] ?? 0);
        if ($oppId > 0) {
            $opp = (new Opportunity())->findById($oppId);
            if ($opp === null) {
                $errors['opportunity_id'] = 'Oportunidade não encontrada.';
            } elseif ($companyId > 0 && (int) $opp['company_id'] !== $companyId) {
                $errors['opportunity_id'] = 'A oportunidade não pertence à empresa selecionada.';
            }
        }

        $propId = (int) ($data['proposal_id'] ?? 0);
        if ($propId > 0) {
            $prop = (new Proposal())->findById($propId);
            if ($prop === null) {
                $errors['proposal_id'] = 'Proposta não encontrada.';
            } elseif ($companyId > 0 && (int) $prop['company_id'] !== $companyId) {
                $errors['proposal_id'] = 'A proposta não pertence à empresa selecionada.';
            }
        }

        $quotaId = (int) ($data['quota_id'] ?? 0);
        if ($quotaId > 0 && (new Quota())->findById($quotaId) === null) {
            $errors['quota_id'] = 'Cota não encontrada.';
        }

        $docId = (int) ($data['primary_document_id'] ?? 0);
        if ($docId > 0 && (new Document())->findById($docId) === null) {
            $errors['primary_document_id'] = 'Documento não encontrado.';
        }

        $respId = (int) ($data['responsible_user_id'] ?? 0);
        if ($respId > 0 && (new User())->findBy('id', $respId) === null) {
            $errors['responsible_user_id'] = 'Responsável não encontrado.';
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function linkOptions(string $table, string $labelCol): array
    {
        return (new Proposal())->filterLinkOptions($table, $labelCol);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $sponsor
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $sponsor = []): void
    {
        $model     = new Sponsor();
        $companyId = (int) ($old['company_id'] ?? ($sponsor['company_id'] ?? 0));
        $companyContacts = $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [];
        $documents = $companyId > 0 && can('documents.view')
            ? (new Document())->filterOptionsByCompany($companyId)
            : [];

        $this->view($view, [
            'title'             => $title,
            'old'               => $old,
            'errors'            => $errors,
            'sponsor'           => $sponsor,
            'sponsorshipTypes'  => $model->getSponsorshipTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'statuses'          => $model->getStatuses(),
            'paymentStatuses'   => $model->getPaymentStatuses(),
            'companies'         => (new Company())->activeOptions(),
            'opportunities'     => $this->linkOptions('opportunities', 'title'),
            'proposals'         => $this->linkOptions('proposals', 'title'),
            'quotas'            => (new Quota())->activeOptions(),
            'users'             => (new User())->activeList(),
            'companyContacts'   => $companyContacts,
            'documents'         => $documents,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function companyFilterOptions(array $filters): array
    {
        $companies  = (new Company())->activeOptions();
        $selectedId = (int) ($filters['company_id'] ?? 0);

        if ($selectedId <= 0) {
            return $companies;
        }

        foreach ($companies as $co) {
            if ((int) ($co['id'] ?? 0) === $selectedId) {
                return $companies;
            }
        }

        $archived = (new Company())->findById($selectedId);
        if ($archived !== null) {
            $companies[] = [
                'id'   => $selectedId,
                'name' => (string) ($archived['name'] ?? '') . ' (arquivada)',
            ];
        }

        return $companies;
    }

    /** @param array<string, mixed> $filters */
    private function hasActiveFilters(array $filters): bool
    {
        foreach (['q', 'sponsorship_type', 'funding_mechanism', 'status', 'payment_status',
            'closed_from', 'closed_to', 'confirmed_from', 'confirmed_to'] as $k) {
            if (trim((string) ($filters[$k] ?? '')) !== '') {
                return true;
            }
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
            'responsible_user_id', 'project_year'] as $k) {
            if ((int) ($filters[$k] ?? 0) > 0) {
                return true;
            }
        }

        foreach (['awaiting_contribution', 'overdue', 'show_archived'] as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = is_numeric($id) ? (new Sponsor())->findById((int) $id) : null;
        if ($row === null) {
            $this->abort(404, 'Fechamento comercial não encontrado.');
        }

        return $row;
    }
}
