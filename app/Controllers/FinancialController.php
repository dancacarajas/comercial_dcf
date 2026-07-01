<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\Document;
use App\Models\FinancialEntry;
use App\Models\IncentiveProject;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\User;

/**
 * Módulo Financeiro Detalhado / Parcelas (Etapa 15).
 */
final class FinancialController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('financials.view');

        $model   = new FinancialEntry();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('financials/index', [
            'title'              => 'Financeiro',
            'items'              => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'            => $filters,
            'entryTypes'         => $model->getEntryTypes(),
            'fundingMechanisms'  => $model->getFundingMechanisms(),
            'paymentMethods'     => $model->getPaymentMethods(),
            'statuses'           => $model->getStatuses(),
            'fiscalStatuses'     => $model->getFiscalDocumentStatuses(),
            'model'              => $model,
            'sponsors'           => $this->sponsorFilterOptions(),
            'contracts'          => $this->contractFilterOptions(),
            'companies'          => (new Company())->activeOptions(),
            'users'              => (new User())->activeList(),
            'page'               => $page,
            'pages'              => $pages,
            'total'              => $total,
            'perPage'            => self::PER_PAGE,
            'hasFilters'         => $this->hasActiveFilters($filters),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForSponsor(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id      = (int) ($params['id'] ?? 0);
        $sponsor = $id > 0 ? (new Sponsor())->findById($id) : null;
        if ($sponsor === null) {
            $this->abort(404, 'Patrocinador não encontrado.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->applyFromSponsor($this->prefillFromQuery([
            'sponsor_id'              => $id,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), $sponsor), []);
    }

    public function createForContract(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id       = (int) ($params['id'] ?? 0);
        $contract = $id > 0 ? (new Contract())->findById($id) : null;
        if ($contract === null) {
            $this->abort(404, 'Contrato não encontrado.');
        }
        $data = $this->applyFromContract($this->applyFromSponsor($this->prefillFromQuery([
            'contract_id'             => $id,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ])), $contract);
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $data, []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'company_id'              => $id,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'company_id'              => (int) $contact['company_id'],
            'contact_id'              => $id,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'company_id'              => (int) $opp['company_id'],
            'contact_id'              => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id'          => $id,
            'incentive_project_id'    => !empty($opp['incentive_project_id']) ? (int) $opp['incentive_project_id'] : null,
            'quota_id'                => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id   = (int) ($params['id'] ?? 0);
        $prop = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($prop === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'company_id'              => (int) $prop['company_id'],
            'contact_id'              => $prop['contact_id'] ? (int) $prop['contact_id'] : null,
            'opportunity_id'          => $prop['opportunity_id'] ? (int) $prop['opportunity_id'] : null,
            'proposal_id'             => $id,
            'incentive_project_id'    => !empty($prop['incentive_project_id']) ? (int) $prop['incentive_project_id'] : null,
            'quota_id'                => $prop['quota_id'] ? (int) $prop['quota_id'] : null,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('financials.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('financials/create', 'Novo lançamento financeiro', $this->prefillFromQuery([
            'quota_id'                => $id,
            'incentive_project_id'    => !empty($quota['incentive_project_id']) ? (int) $quota['incentive_project_id'] : null,
            'entry_type'              => 'parcela_patrocinio',
            'funding_mechanism'       => 'nao_definido',
            'payment_method'          => 'nao_definido',
            'status'                  => 'previsto',
            'fiscal_document_status'  => 'nao_aplicavel',
            'responsible_user_id'     => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('financials.create');
        csrf_verify();

        $model = new FinancialEntry();
        $data  = $this->collectInput($model);
        $data  = $this->applyFromContract($this->applyFromSponsor($data));
        $data  = $this->applyProjectScope($data);

        if (trim((string) ($data['title'] ?? '')) === '') {
            $sponsorId = (int) ($data['sponsor_id'] ?? 0);
            $sponsor   = $sponsorId > 0 ? (new Sponsor())->findById($sponsorId) : null;
            $data['title'] = 'Parcela — ' . (string) ($sponsor['sponsor_display_name'] ?? 'Patrocinador');
        }

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('financials/create', 'Novo lançamento financeiro', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('financial_entry_created', $_SESSION['user_id'] ?? null, 'financial_entry', (int) $id);

        flash('success', 'Lançamento financeiro registrado com sucesso.');
        $this->redirect('/financials/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('financials.view');
        $entry = $this->findOr404($params['id'] ?? null);
        $model = new FinancialEntry();
        $eid   = (int) $entry['id'];
        $sid   = (int) ($entry['sponsor_id'] ?? 0);

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->paginate(['sponsor_id' => $sid], 1, 10);
            $documentSummary = $documentModel->summaryBySponsor($sid);
        }

        $this->view('financials/show', [
            'title'             => $entry['title'] ?? 'Lançamento financeiro',
            'entry'             => $entry,
            'model'             => $model,
            'entryTypes'        => $model->getEntryTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'paymentMethods'    => $model->getPaymentMethods(),
            'statuses'          => $model->getStatuses(),
            'fiscalStatuses'    => $model->getFiscalDocumentStatuses(),
            'documents'         => $documents,
            'documentSummary'   => $documentSummary,
            'documentModel'     => $documentModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('financials.edit');
        $entry = $this->findOr404($params['id'] ?? null);

        if (!empty($entry['archived_at'])) {
            flash('error', 'Este lançamento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/financials/' . (int) $entry['id']);
            return;
        }

        $this->renderForm('financials/edit', 'Editar lançamento financeiro', $entry, [], $entry);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('financials.edit');
        csrf_verify();

        $entry = $this->findOr404($params['id'] ?? null);
        $id    = (int) $entry['id'];

        if (!empty($entry['archived_at'])) {
            flash('error', 'Este lançamento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/financials/' . $id);
            return;
        }

        $model  = new FinancialEntry();
        $data   = $this->collectInput($model);
        $data   = $this->applyFromContract($this->applyFromSponsor($data));
        $data   = $this->applyProjectScope($data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('financials/edit', 'Editar lançamento financeiro', $data, $errors, array_merge($entry, $data));
            return;
        }

        $statusChanged   = (string) $entry['status'] !== (string) ($data['status'] ?? '');
        $receivedChanged = (float) ($entry['received_amount'] ?? 0) !== (float) ($data['received_amount'] ?? 0);
        $proofChanged    = (int) ($entry['proof_document_id'] ?? 0) !== (int) ($data['proof_document_id'] ?? 0);
        $receiptChanged  = (int) ($entry['receipt_document_id'] ?? 0) !== (int) ($data['receipt_document_id'] ?? 0);
        $fiscalChanged   = (int) ($entry['fiscal_document_id'] ?? 0) !== (int) ($data['fiscal_document_id'] ?? 0);

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        $userId = $_SESSION['user_id'] ?? null;
        (new ActivityLog())->record('financial_entry_updated', $userId, 'financial_entry', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('financial_status_changed', $userId, 'financial_entry', $id);
        }
        if ($receivedChanged) {
            (new ActivityLog())->record('financial_received_amount_changed', $userId, 'financial_entry', $id);
        }
        if ($proofChanged) {
            (new ActivityLog())->record('financial_proof_document_linked', $userId, 'financial_entry', $id);
        }
        if ($receiptChanged) {
            (new ActivityLog())->record('financial_receipt_document_linked', $userId, 'financial_entry', $id);
        }
        if ($fiscalChanged) {
            (new ActivityLog())->record('financial_fiscal_document_linked', $userId, 'financial_entry', $id);
        }

        flash('success', 'Lançamento financeiro atualizado com sucesso.');
        $this->redirect('/financials/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('financials.status');
        csrf_verify();

        $entry  = $this->findOr404($params['id'] ?? null);
        $id     = (int) $entry['id'];
        $model  = new FinancialEntry();
        $userId = $_SESSION['user_id'] ?? null;
        $patch  = ['updated_by' => $userId];

        $statusChanged = false;
        $fiscalChanged = false;

        if (input('status') !== null && input('status') !== '') {
            $newStatus = clean((string) input('status'));
            if (!array_key_exists($newStatus, $model->getStatuses())) {
                flash('error', 'Status financeiro inválido.');
                $this->redirect('/financials/' . $id);
                return;
            }
            if ((string) ($entry['status'] ?? '') !== $newStatus) {
                $statusChanged = true;
            }
            $patch['status'] = $newStatus;
        }

        if (input('fiscal_document_status') !== null && input('fiscal_document_status') !== '') {
            $newFiscal = clean((string) input('fiscal_document_status'));
            if (!array_key_exists($newFiscal, $model->getFiscalDocumentStatuses())) {
                flash('error', 'Status fiscal inválido.');
                $this->redirect('/financials/' . $id);
                return;
            }
            if ((string) ($entry['fiscal_document_status'] ?? '') !== $newFiscal) {
                $fiscalChanged = true;
            }
            $patch['fiscal_document_status'] = $newFiscal;
        }

        $note = trim((string) input('notes', ''));
        if ($note !== '') {
            $prev = trim((string) ($entry['notes'] ?? ''));
            $patch['notes'] = $prev === '' ? $note : ($prev . "\n[" . date('Y-m-d H:i') . "] " . $note);
        }

        $newStatus = (string) ($patch['status'] ?? $entry['status'] ?? '');

        if ($newStatus === 'recebido' && empty($entry['received_at'])) {
            $patch['received_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'conciliado' && empty($entry['reconciled_at'])) {
            $patch['reconciled_at'] = date('Y-m-d H:i:s');
        }
        if ($newStatus === 'cancelado') {
            if (empty($entry['cancelled_at'])) {
                $patch['cancelled_at'] = date('Y-m-d H:i:s');
            }
            $patch['cancelled_by'] = $userId;
        }

        $model->updateStatus($id, $patch);

        if ($statusChanged) {
            (new ActivityLog())->record('financial_status_changed', $userId, 'financial_entry', $id);
        }
        if ($fiscalChanged) {
            (new ActivityLog())->record('financial_fiscal_status_changed', $userId, 'financial_entry', $id);
        }

        flash('success', 'Status atualizado.');
        $this->redirect('/financials/' . $id);
    }

    public function confirm(array $params): void
    {
        AuthMiddleware::requirePermission('financials.confirm');
        csrf_verify();

        $entry  = $this->findOr404($params['id'] ?? null);
        $id     = (int) $entry['id'];
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $model  = new FinancialEntry();

        $payload = [
            'received_amount'        => input('received_amount') !== null && input('received_amount') !== ''
                ? (string) input('received_amount') : null,
            'received_at'            => input('received_at') !== null ? (string) input('received_at') : '',
            'payment_method'         => clean((string) input('payment_method', '')),
            'transaction_reference'  => trim((string) input('transaction_reference', '')),
            'proof_document_id'      => input('proof_document_id') !== null && input('proof_document_id') !== ''
                ? (int) input('proof_document_id') : null,
            'receipt_document_id'    => input('receipt_document_id') !== null && input('receipt_document_id') !== ''
                ? (int) input('receipt_document_id') : null,
            'proof_notes'            => trim((string) input('proof_notes', '')),
            'receipt_notes'          => trim((string) input('receipt_notes', '')),
            'notes'                  => trim((string) input('notes', '')),
        ];

        $errors = [];
        foreach (['proof_document_id', 'receipt_document_id'] as $docField) {
            $docId = (int) ($payload[$docField] ?? 0);
            if ($docId > 0 && (new Document())->findById($docId) === null) {
                $errors[$docField] = 'Documento não encontrado.';
            }
        }
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/financials/' . $id);
            return;
        }

        $model->confirmPayment($id, $payload, $userId);

        (new ActivityLog())->record('financial_payment_confirmed', $userId ?: null, 'financial_entry', $id);

        $updated = $model->findById($id);
        if ($updated !== null && (string) ($updated['status'] ?? '') === 'recebido_parcial') {
            (new ActivityLog())->record('financial_partial_payment_confirmed', $userId ?: null, 'financial_entry', $id);
        }

        flash('success', 'Recebimento confirmado com sucesso.');
        $this->redirect('/financials/' . $id);
    }

    public function reconcile(array $params): void
    {
        AuthMiddleware::requirePermission('financials.reconcile');
        csrf_verify();

        $entry  = $this->findOr404($params['id'] ?? null);
        $id     = (int) $entry['id'];
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        (new FinancialEntry())->reconcile($id, [
            'reconciled_at'          => input('reconciled_at') !== null ? (string) input('reconciled_at') : '',
            'reconciliation_notes'   => trim((string) input('reconciliation_notes', '')),
            'bank_reference'         => trim((string) input('bank_reference', '')),
            'transaction_reference'  => trim((string) input('transaction_reference', '')),
        ], $userId);

        (new ActivityLog())->record('financial_reconciled', $userId ?: null, 'financial_entry', $id);

        flash('success', 'Lançamento conciliado com sucesso.');
        $this->redirect('/financials/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('financials.archive');
        csrf_verify();

        $entry = $this->findOr404($params['id'] ?? null);
        $id    = (int) $entry['id'];

        (new FinancialEntry())->archive($id);
        (new ActivityLog())->record('financial_entry_archived', $_SESSION['user_id'] ?? null, 'financial_entry', $id);

        flash('success', 'Lançamento financeiro arquivado.');
        $this->redirect('/financials/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('financials.archive');
        csrf_verify();

        $entry = $this->findOr404($params['id'] ?? null);
        $id    = (int) $entry['id'];

        (new FinancialEntry())->restore($id);
        (new ActivityLog())->record('financial_entry_restored', $_SESSION['user_id'] ?? null, 'financial_entry', $id);

        flash('success', 'Lançamento financeiro restaurado.');
        $this->redirect('/financials/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                      => (string) input('q', ''),
            'incentive_project_id'   => (int) input('incentive_project_id', 0),
            'sponsor_id'             => (int) input('sponsor_id', 0),
            'contract_id'            => (int) input('contract_id', 0),
            'company_id'             => (int) input('company_id', 0),
            'contact_id'             => (int) input('contact_id', 0),
            'opportunity_id'         => (int) input('opportunity_id', 0),
            'proposal_id'            => (int) input('proposal_id', 0),
            'quota_id'               => (int) input('quota_id', 0),
            'entry_type'             => (string) input('entry_type', ''),
            'funding_mechanism'      => (string) input('funding_mechanism', ''),
            'payment_method'         => (string) input('payment_method', ''),
            'status'                 => (string) input('status', ''),
            'fiscal_document_status' => (string) input('fiscal_document_status', ''),
            'responsible_user_id'    => (int) input('responsible_user_id', 0),
            'due_from'               => (string) input('due_from', ''),
            'due_to'                 => (string) input('due_to', ''),
            'received_from'          => (string) input('received_from', ''),
            'received_to'            => (string) input('received_to', ''),
            'overdue'                => input('overdue') !== null ? 1 : 0,
            'received'               => input('received') !== null ? 1 : 0,
            'partial'                => input('partial') !== null ? 1 : 0,
            'reconciled'             => input('reconciled') !== null ? 1 : 0,
            'pending'                => input('pending') !== null ? 1 : 0,
            'show_archived'          => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(FinancialEntry $model): array
    {
        return [
            'incentive_project_id' => ($projectId = (int) input('incentive_project_id', 0)) > 0 ? $projectId : null,
            'sponsor_id'             => (int) input('sponsor_id', 0),
            'contract_id'            => input('contract_id') !== null && input('contract_id') !== '' ? (int) input('contract_id') : null,
            'company_id'             => input('company_id') !== null && input('company_id') !== '' ? (int) input('company_id') : null,
            'contact_id'             => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'         => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'proposal_id'            => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'quota_id'               => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'proof_document_id'      => input('proof_document_id') !== null && input('proof_document_id') !== '' ? (int) input('proof_document_id') : null,
            'receipt_document_id'    => input('receipt_document_id') !== null && input('receipt_document_id') !== '' ? (int) input('receipt_document_id') : null,
            'fiscal_document_id'     => input('fiscal_document_id') !== null && input('fiscal_document_id') !== '' ? (int) input('fiscal_document_id') : null,
            'entry_number'           => clean((string) input('entry_number', '')) ?: null,
            'title'                  => clean((string) input('title', '')),
            'entry_type'             => clean((string) input('entry_type', 'parcela_patrocinio')),
            'funding_mechanism'      => clean((string) input('funding_mechanism', 'nao_definido')),
            'payment_method'         => clean((string) input('payment_method', 'nao_definido')),
            'status'                 => clean((string) input('status', 'previsto')),
            'fiscal_document_status' => clean((string) input('fiscal_document_status', 'nao_aplicavel')),
            'installment_number'     => input('installment_number') !== null && input('installment_number') !== '' ? (int) input('installment_number') : null,
            'installments_total'     => input('installments_total') !== null && input('installments_total') !== '' ? (int) input('installments_total') : null,
            'planned_amount'         => $model->normalizeMoney((string) input('planned_amount', '')),
            'received_amount'        => $model->normalizeMoney((string) input('received_amount', '0')),
            'due_date'               => $model->normalizeDate((string) input('due_date', '')),
            'expected_payment_date'  => $model->normalizeDate((string) input('expected_payment_date', '')),
            'received_at'            => $model->normalizeDateTime((string) input('received_at', '')),
            'reconciled_at'          => $model->normalizeDateTime((string) input('reconciled_at', '')),
            'cancelled_at'           => $model->normalizeDateTime((string) input('cancelled_at', '')),
            'payer_name'             => clean((string) input('payer_name', '')) ?: null,
            'payer_document'         => clean((string) input('payer_document', '')) ?: null,
            'bank_reference'         => clean((string) input('bank_reference', '')) ?: null,
            'transaction_reference'  => clean((string) input('transaction_reference', '')) ?: null,
            'proof_notes'            => trim((string) input('proof_notes', '')) ?: null,
            'receipt_notes'          => trim((string) input('receipt_notes', '')) ?: null,
            'fiscal_notes'           => trim((string) input('fiscal_notes', '')) ?: null,
            'reconciliation_notes'   => trim((string) input('reconciliation_notes', '')) ?: null,
            'notes'                  => trim((string) input('notes', '')) ?: null,
            'internal_notes'         => trim((string) input('internal_notes', '')) ?: null,
            'responsible_user_id'    => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach ([
            'incentive_project_id', 'sponsor_id', 'contract_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
            'proof_document_id', 'receipt_document_id', 'fiscal_document_id',
        ] as $k) {
            $q = input($k);
            if ($q !== null && $q !== '') {
                $data[$k] = (int) $q;
            }
        }

        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId > 0) {
            $contract = (new Contract())->findById($contractId);
            if ($contract !== null) {
                $data = $this->applyFromContract($this->applyFromSponsor($data), $contract);
            }
        } elseif (!empty($data['sponsor_id'])) {
            $data = $this->applyFromSponsor($data);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $sponsor
     * @return array<string, mixed>
     */
    private function applyFromSponsor(array $data, ?array $sponsor = null): array
    {
        $sponsorId = (int) ($data['sponsor_id'] ?? 0);
        if ($sponsor === null && $sponsorId > 0) {
            $sponsor = (new Sponsor())->findById($sponsorId);
        }

        if ($sponsor === null) {
            return $data;
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id'] as $fk) {
            if (empty($data[$fk]) && !empty($sponsor[$fk])) {
                $data[$fk] = (int) $sponsor[$fk];
            }
        }

        // Etapa 19: o financeiro herda o projeto do patrocinador.
        if (empty($data['incentive_project_id']) && !empty($sponsor['incentive_project_id'])) {
            $data['incentive_project_id'] = (int) $sponsor['incentive_project_id'];
        }

        if (($data['planned_amount'] ?? null) === null || $data['planned_amount'] === '') {
            if ($sponsor['confirmed_amount'] !== null && $sponsor['confirmed_amount'] !== '') {
                $data['planned_amount'] = $sponsor['confirmed_amount'];
            } elseif ($sponsor['committed_amount'] !== null && $sponsor['committed_amount'] !== '') {
                $data['planned_amount'] = $sponsor['committed_amount'];
            }
        }

        if (($data['planned_amount'] ?? null) === null || $data['planned_amount'] === '') {
            $proposalId = (int) ($data['proposal_id'] ?? $sponsor['proposal_id'] ?? 0);
            if ($proposalId > 0) {
                $prop = (new Proposal())->findById($proposalId);
                if ($prop !== null && $prop['proposed_value'] !== null) {
                    $data['planned_amount'] = $prop['proposed_value'];
                }
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $contract
     * @return array<string, mixed>
     */
    private function applyFromContract(array $data, ?array $contract = null): array
    {
        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contract === null && $contractId > 0) {
            $contract = (new Contract())->findById($contractId);
        }

        if ($contract === null) {
            return $data;
        }

        if (empty($data['sponsor_id']) && !empty($contract['sponsor_id'])) {
            $data['sponsor_id'] = (int) $contract['sponsor_id'];
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id'] as $fk) {
            if (empty($data[$fk]) && !empty($contract[$fk])) {
                $data[$fk] = (int) $contract[$fk];
            }
        }

        if (empty($data['incentive_project_id']) && !empty($contract['incentive_project_id'])) {
            $data['incentive_project_id'] = (int) $contract['incentive_project_id'];
        }

        if (($data['planned_amount'] ?? null) === null || $data['planned_amount'] === '') {
            if ($contract['formalized_value'] !== null && $contract['formalized_value'] !== '') {
                $data['planned_amount'] = $contract['formalized_value'];
            }
        }

        if (empty($data['funding_mechanism']) || $data['funding_mechanism'] === 'nao_definido') {
            if (!empty($contract['funding_mechanism'])) {
                $data['funding_mechanism'] = (string) $contract['funding_mechanism'];
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyProjectScope(array $data): array
    {
        foreach ([
            'proposal_id' => new Proposal(),
            'opportunity_id' => new Opportunity(),
            'quota_id' => new Quota(),
        ] as $field => $model) {
            if (!empty($data['incentive_project_id']) || empty($data[$field])) {
                continue;
            }
            $row = $model->findById((int) $data[$field]);
            if ($row !== null && !empty($row['incentive_project_id'])) {
                $data['incentive_project_id'] = (int) $row['incentive_project_id'];
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function validateLinks(array $data, array &$errors): void
    {
        $sponsorId = (int) ($data['sponsor_id'] ?? 0);
        if ($sponsorId > 0 && (new Sponsor())->findById($sponsorId) === null) {
            $errors['sponsor_id'] = 'Patrocinador não encontrado.';
        }

        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId > 0 && (new Contract())->findById($contractId) === null) {
            $errors['contract_id'] = 'Contrato não encontrado.';
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && (new Company())->findById($companyId) === null) {
            $errors['company_id'] = 'Empresa não encontrada.';
        }

        foreach ([
            'sponsor_id' => [new Sponsor(), 'O patrocinador'],
            'contract_id' => [new Contract(), 'O contrato'],
            'proposal_id' => [new Proposal(), 'A proposta'],
            'opportunity_id' => [new Opportunity(), 'A oportunidade'],
            'quota_id' => [new Quota(), 'A cota'],
        ] as $field => [$linkModel, $label]) {
            $linkId = (int) ($data[$field] ?? 0);
            if ($linkId <= 0 || empty($data['incentive_project_id'])) { continue; }
            $row = $linkModel->findById($linkId);
            if ($row !== null && (int) ($row['incentive_project_id'] ?? 0) !== (int) $data['incentive_project_id']) {
                $errors[$field] = $label . ' não pertence ao projeto incentivado informado.';
            }
        }

        $docModel = new Document();
        foreach ([
            'proof_document_id'  => 'Comprovante',
            'receipt_document_id'=> 'Recibo',
            'fiscal_document_id' => 'Documento fiscal',
        ] as $field => $label) {
            $docId = (int) ($data[$field] ?? 0);
            if ($docId > 0 && $docModel->findById($docId) === null) {
                $errors[$field] = $label . ' não encontrado.';
            }
        }

        $respId = (int) ($data['responsible_user_id'] ?? 0);
        if ($respId > 0 && (new User())->findBy('id', $respId) === null) {
            $errors['responsible_user_id'] = 'Responsável não encontrado.';
        }
    }

    /** @param array<string, mixed> $filters */
    private function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $k => $v) {
            if ($k === 'show_archived' && empty($v)) {
                continue;
            }
            if ($v !== '' && $v !== 0 && $v !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    private function sponsorFilterOptions(): array
    {
        $items = (new Sponsor())->paginate(['show_archived' => 0], 1, 200);

        return array_map(static fn ($s) => [
            'id'   => (int) $s['id'],
            'name' => (string) ($s['sponsor_display_name'] ?? ''),
        ], $items);
    }

    /** @return array<int, array<string, mixed>> */
    private function contractFilterOptions(): array
    {
        return (new Document())->filterContractOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function linkOptions(string $table, string $labelCol): array
    {
        return (new Proposal())->filterLinkOptions($table, $labelCol);
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = (new FinancialEntry())->findById((int) $id);
        if ($row === null) {
            $this->abort(404, 'Lançamento financeiro não encontrado.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $entry
     */
    private function renderForm(string $view, string $title, array $old, array $errors, ?array $entry = null): void
    {
        $model      = new FinancialEntry();
        $sponsorId  = (int) ($old['sponsor_id'] ?? 0);
        $companyId  = (int) ($old['company_id'] ?? 0);
        $contractId = (int) ($old['contract_id'] ?? 0);
        $projectId  = (int) ($old['incentive_project_id'] ?? ($entry['incentive_project_id'] ?? 0));

        $sponsors  = (new Sponsor())->paginate(['show_archived' => 0], 1, 300);
        $documents = [];
        if (can('documents.view')) {
            $docFilters = array_filter([
                'company_id'  => $companyId ?: null,
                'sponsor_id'  => $sponsorId ?: null,
                'contract_id' => $contractId ?: null,
            ]);
            $documents = (new Document())->paginate($docFilters, 1, 100);
        }

        $planned   = is_numeric($old['planned_amount'] ?? null) ? (float) $old['planned_amount'] : 0.0;
        $received  = is_numeric($old['received_amount'] ?? null) ? (float) $old['received_amount'] : 0.0;
        $remaining = $model->calculateRemaining($planned, $received);

        $this->view($view, [
            'title'             => $title,
            'entry'             => $entry ?? $old,
            'old'               => $old,
            'errors'            => $errors,
            'remainingPreview'  => $remaining,
            'entryTypes'        => $model->getEntryTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'paymentMethods'    => $model->getPaymentMethods(),
            'statuses'          => $model->getStatuses(),
            'fiscalStatuses'    => $model->getFiscalDocumentStatuses(),
            'projects'          => (new IncentiveProject())->options(true),
            'sponsors'          => $sponsors,
            'contracts'         => $this->contractFilterOptions(),
            'companies'         => (new Company())->activeOptions(),
            'contacts'          => $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [],
            'opportunities'     => $this->linkOptions('opportunities', 'title'),
            'proposals'         => $this->linkOptions('proposals', 'title'),
            'quotas'            => (new Quota())->activeOptions($projectId > 0 ? $projectId : null),
            'users'             => (new User())->activeList(),
            'documents'         => array_map(static fn ($d) => [
                'id'    => (int) $d['id'],
                'label' => (string) ($d['title'] ?? '') . ' (v' . (int) ($d['version_number'] ?? 1) . ')',
            ], $documents),
        ]);
    }
}
