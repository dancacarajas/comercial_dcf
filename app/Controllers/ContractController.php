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
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\User;

/**
 * Módulo Contratos / Instrumentos de Formalização (Etapa 14).
 */
final class ContractController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('contracts.view');

        $model   = new Contract();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('contracts/index', [
            'title'             => 'Contratos',
            'items'             => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'           => $filters,
            'contractTypes'     => $model->getContractTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'statuses'          => $model->getStatuses(),
            'reviewStatuses'    => $model->getReviewStatuses(),
            'signatureStatuses' => $model->getSignatureStatuses(),
            'model'             => $model,
            'sponsors'          => $this->sponsorFilterOptions($filters),
            'companies'         => $this->companyFilterOptions($filters),
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
        AuthMiddleware::requirePermission('contracts.create');
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForSponsor(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id      = (int) ($params['id'] ?? 0);
        $sponsor = $id > 0 ? (new Sponsor())->findById($id) : null;
        if ($sponsor === null) {
            $this->abort(404, 'Patrocinador não encontrado.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->applyFromSponsor($this->prefillFromQuery([
            'sponsor_id'          => $id,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), $sponsor), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'company_id'          => $id,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'company_id'          => (int) $contact['company_id'],
            'contact_id'          => $id,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'company_id'          => (int) $opp['company_id'],
            'contact_id'          => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id'      => $id,
            'quota_id'            => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id   = (int) ($params['id'] ?? 0);
        $prop = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($prop === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'company_id'          => (int) $prop['company_id'],
            'contact_id'          => $prop['contact_id'] ? (int) $prop['contact_id'] : null,
            'opportunity_id'      => $prop['opportunity_id'] ? (int) $prop['opportunity_id'] : null,
            'proposal_id'         => $id,
            'quota_id'            => $prop['quota_id'] ? (int) $prop['quota_id'] : null,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('contracts/create', 'Novo contrato', $this->prefillFromQuery([
            'quota_id'            => $id,
            'contract_type'       => 'termo_patrocinio',
            'funding_mechanism'   => 'nao_definido',
            'status'              => 'minuta',
            'review_status'       => 'nao_revisado',
            'signature_status'    => 'nao_enviado',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('contracts.create');
        csrf_verify();

        $model = new Contract();
        $data  = $this->collectInput($model);
        $data  = $this->applyFromSponsor($data);

        if (trim((string) ($data['title'] ?? '')) === '') {
            $sponsorId = (int) ($data['sponsor_id'] ?? 0);
            $sponsor   = $sponsorId > 0 ? (new Sponsor())->findById($sponsorId) : null;
            $data['title'] = 'Contrato — ' . (string) ($sponsor['sponsor_display_name'] ?? 'Patrocinador');
        }

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('contracts/create', 'Novo contrato', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('contract_created', $_SESSION['user_id'] ?? null, 'contract', (int) $id);

        flash('success', 'Contrato registrado com sucesso.');
        $this->redirect('/contracts/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.view');
        $contract = $this->findOr404($params['id'] ?? null);
        $model    = new Contract();
        $cid      = (int) $contract['id'];
        $sid      = (int) ($contract['sponsor_id'] ?? 0);

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->paginate(['sponsor_id' => $sid], 1, 10);
            $documentSummary = $documentModel->summaryBySponsor($sid);
        }

        $this->view('contracts/show', [
            'title'             => $contract['title'] ?? 'Contrato',
            'contract'          => $contract,
            'model'             => $model,
            'contractTypes'     => $model->getContractTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'statuses'          => $model->getStatuses(),
            'reviewStatuses'    => $model->getReviewStatuses(),
            'signatureStatuses' => $model->getSignatureStatuses(),
            'documents'         => $documents,
            'documentSummary'   => $documentSummary,
            'documentModel'     => $documentModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.edit');
        $contract = $this->findOr404($params['id'] ?? null);

        if (!empty($contract['archived_at'])) {
            flash('error', 'Este contrato está arquivado. Restaure-o antes de editar.');
            $this->redirect('/contracts/' . (int) $contract['id']);
            return;
        }

        $this->renderForm('contracts/edit', 'Editar contrato', $contract, [], $contract);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.edit');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];

        if (!empty($contract['archived_at'])) {
            flash('error', 'Este contrato está arquivado. Restaure-o antes de editar.');
            $this->redirect('/contracts/' . $id);
            return;
        }

        $model  = new Contract();
        $data   = $this->collectInput($model);
        $data   = $this->applyFromSponsor($data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('contracts/edit', 'Editar contrato', $data, $errors, array_merge($contract, $data));
            return;
        }

        $statusChanged    = (string) $contract['status'] !== (string) ($data['status'] ?? '');
        $reviewChanged    = (string) $contract['review_status'] !== (string) ($data['review_status'] ?? '');
        $signatureChanged = (string) $contract['signature_status'] !== (string) ($data['signature_status'] ?? '');
        $signedDocChanged = (int) ($contract['signed_document_id'] ?? 0) !== (int) ($data['signed_document_id'] ?? 0);

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('contract_updated', $_SESSION['user_id'] ?? null, 'contract', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('contract_status_changed', $_SESSION['user_id'] ?? null, 'contract', $id);
        }
        if ($reviewChanged) {
            (new ActivityLog())->record('contract_review_status_changed', $_SESSION['user_id'] ?? null, 'contract', $id);
        }
        if ($signatureChanged) {
            (new ActivityLog())->record('contract_signature_status_changed', $_SESSION['user_id'] ?? null, 'contract', $id);
        }
        if ($signedDocChanged) {
            (new ActivityLog())->record('contract_signed_document_linked', $_SESSION['user_id'] ?? null, 'contract', $id);
        }

        flash('success', 'Contrato atualizado com sucesso.');
        $this->redirect('/contracts/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.status');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];
        $model    = new Contract();
        $userId   = $_SESSION['user_id'] ?? null;
        $patch    = ['updated_by' => $userId];

        $statusChanged    = false;
        $reviewChanged    = false;
        $signatureChanged = false;

        if (input('status') !== null && input('status') !== '') {
            $newStatus = clean((string) input('status'));
            if (!array_key_exists($newStatus, $model->getStatuses())) {
                flash('error', 'Status inválido.');
                $this->redirect('/contracts/' . $id);
                return;
            }
            if ((string) ($contract['status'] ?? '') !== $newStatus) {
                $statusChanged = true;
            }
            $patch['status'] = $newStatus;
        }

        if (input('review_status') !== null && input('review_status') !== '') {
            $newReview = clean((string) input('review_status'));
            if (!array_key_exists($newReview, $model->getReviewStatuses())) {
                flash('error', 'Status de revisão inválido.');
                $this->redirect('/contracts/' . $id);
                return;
            }
            if ((string) ($contract['review_status'] ?? '') !== $newReview) {
                $reviewChanged = true;
            }
            $patch['review_status'] = $newReview;
        }

        if (input('signature_status') !== null && input('signature_status') !== '') {
            $newSig = clean((string) input('signature_status'));
            if (!array_key_exists($newSig, $model->getSignatureStatuses())) {
                flash('error', 'Status de assinatura inválido.');
                $this->redirect('/contracts/' . $id);
                return;
            }
            if ((string) ($contract['signature_status'] ?? '') !== $newSig) {
                $signatureChanged = true;
            }
            $patch['signature_status'] = $newSig;
        }

        $note = trim((string) input('notes', ''));
        if ($note !== '') {
            $prev = trim((string) ($contract['notes'] ?? ''));
            $patch['notes'] = $prev === '' ? $note : ($prev . "\n[" . date('Y-m-d H:i') . "] " . $note);
        }

        $newStatus = (string) ($patch['status'] ?? $contract['status'] ?? '');
        $newSig    = (string) ($patch['signature_status'] ?? $contract['signature_status'] ?? '');

        if (in_array($newStatus, ['enviado_para_assinatura', 'aguardando_assinatura'], true)
            || in_array($newSig, ['enviado_manual', 'aguardando_assinatura'], true)) {
            if (empty($contract['sent_for_signature_at'])) {
                $patch['sent_for_signature_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($newStatus === 'assinado' || $newSig === 'assinado') {
            if (empty($contract['signed_at'])) {
                $patch['signed_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($newStatus === 'vigente' && empty($contract['effective_at'])) {
            $patch['effective_at'] = date('Y-m-d');
        }

        if ($newStatus === 'encerrado' && empty($contract['ended_at'])) {
            $patch['ended_at'] = date('Y-m-d');
        }

        if ($newStatus === 'aprovado_internamente') {
            if (empty($contract['approved_at'])) {
                $patch['approved_at'] = date('Y-m-d H:i:s');
            }
            $patch['approved_by'] = $userId;
        }

        $model->updateStatus($id, $patch);

        if ($statusChanged) {
            (new ActivityLog())->record('contract_status_changed', $userId, 'contract', $id);
        }
        if ($reviewChanged) {
            (new ActivityLog())->record('contract_review_status_changed', $userId, 'contract', $id);
        }
        if ($signatureChanged) {
            (new ActivityLog())->record('contract_signature_status_changed', $userId, 'contract', $id);
        }

        flash('success', 'Status atualizado.');
        $this->redirect('/contracts/' . $id);
    }

    public function approve(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.approve');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];
        $userId   = (int) ($_SESSION['user_id'] ?? 0);

        (new Contract())->approve($id, [
            'approval_notes' => trim((string) input('approval_notes', '')),
        ], $userId);

        (new ActivityLog())->record('contract_approved', $userId ?: null, 'contract', $id);

        flash('success', 'Contrato aprovado internamente.');
        $this->redirect('/contracts/' . $id);
    }

    public function markSigned(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.mark_signed');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];
        $userId   = (int) ($_SESSION['user_id'] ?? 0);
        $model    = new Contract();

        $payload = [
            'status'             => clean((string) input('status', 'assinado')),
            'signed_at'          => input('signed_at') !== null ? (string) input('signed_at') : '',
            'signed_document_id' => input('signed_document_id') !== null && input('signed_document_id') !== ''
                ? (int) input('signed_document_id') : null,
            'signature_notes'    => trim((string) input('signature_notes', '')),
        ];

        $prevDocId = (int) ($contract['signed_document_id'] ?? 0);
        $model->markSigned($id, $payload, $userId);

        (new ActivityLog())->record('contract_marked_signed', $userId ?: null, 'contract', $id);

        $newDocId = (int) ($payload['signed_document_id'] ?? 0);
        if ($newDocId > 0 && $newDocId !== $prevDocId) {
            (new ActivityLog())->record('contract_signed_document_linked', $userId ?: null, 'contract', $id);
        }

        flash('success', 'Assinatura registrada com sucesso.');
        $this->redirect('/contracts/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.archive');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];

        (new Contract())->archive($id);
        (new ActivityLog())->record('contract_archived', $_SESSION['user_id'] ?? null, 'contract', $id);

        flash('success', 'Contrato arquivado.');
        $this->redirect('/contracts/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('contracts.archive');
        csrf_verify();

        $contract = $this->findOr404($params['id'] ?? null);
        $id       = (int) $contract['id'];

        (new Contract())->restore($id);
        (new ActivityLog())->record('contract_restored', $_SESSION['user_id'] ?? null, 'contract', $id);

        flash('success', 'Contrato restaurado.');
        $this->redirect('/contracts/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                   => (string) input('q', ''),
            'sponsor_id'          => (int) input('sponsor_id', 0),
            'company_id'          => (int) input('company_id', 0),
            'contract_type'       => (string) input('contract_type', ''),
            'funding_mechanism'   => (string) input('funding_mechanism', ''),
            'status'              => (string) input('status', ''),
            'review_status'       => (string) input('review_status', ''),
            'signature_status'    => (string) input('signature_status', ''),
            'responsible_user_id' => (int) input('responsible_user_id', 0),
            'start_from'          => (string) input('start_from', ''),
            'end_to'              => (string) input('end_to', ''),
            'expired'             => input('expired') !== null ? 1 : 0,
            'active_vigente'      => input('active_vigente') !== null ? 1 : 0,
            'awaiting_signature'  => input('awaiting_signature') !== null ? 1 : 0,
            'signed'              => input('signed') !== null ? 1 : 0,
            'show_archived'       => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Contract $model): array
    {
        return [
            'sponsor_id'                     => (int) input('sponsor_id', 0),
            'company_id'                     => input('company_id') !== null && input('company_id') !== '' ? (int) input('company_id') : null,
            'contact_id'                     => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'                 => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'proposal_id'                    => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'quota_id'                       => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'draft_document_id'              => input('draft_document_id') !== null && input('draft_document_id') !== '' ? (int) input('draft_document_id') : null,
            'final_document_id'              => input('final_document_id') !== null && input('final_document_id') !== '' ? (int) input('final_document_id') : null,
            'signed_document_id'             => input('signed_document_id') !== null && input('signed_document_id') !== '' ? (int) input('signed_document_id') : null,
            'contract_number'                => clean((string) input('contract_number', '')) ?: null,
            'title'                          => clean((string) input('title', '')),
            'contract_type'                  => clean((string) input('contract_type', 'termo_patrocinio')),
            'formalized_value'               => $model->normalizeMoney((string) input('formalized_value', '')),
            'funding_mechanism'              => clean((string) input('funding_mechanism', 'nao_definido')),
            'status'                         => clean((string) input('status', 'minuta')),
            'review_status'                  => clean((string) input('review_status', 'nao_revisado')),
            'signature_status'               => clean((string) input('signature_status', 'nao_enviado')),
            'start_date'                     => $model->normalizeDate((string) input('start_date', '')),
            'end_date'                       => $model->normalizeDate((string) input('end_date', '')),
            'sent_for_signature_at'          => $model->normalizeDateTime((string) input('sent_for_signature_at', '')),
            'signed_at'                      => $model->normalizeDateTime((string) input('signed_at', '')),
            'effective_at'                   => $model->normalizeDate((string) input('effective_at', '')),
            'ended_at'                       => $model->normalizeDate((string) input('ended_at', '')),
            'sponsor_signatory_name'         => clean((string) input('sponsor_signatory_name', '')) ?: null,
            'sponsor_signatory_email'        => trim((string) input('sponsor_signatory_email', '')) ?: null,
            'sponsor_signatory_position'     => clean((string) input('sponsor_signatory_position', '')) ?: null,
            'sponsor_signatory_document'     => clean((string) input('sponsor_signatory_document', '')) ?: null,
            'organization_signatory_name'    => clean((string) input('organization_signatory_name', '')) ?: null,
            'organization_signatory_email'   => trim((string) input('organization_signatory_email', '')) ?: null,
            'organization_signatory_position'=> clean((string) input('organization_signatory_position', '')) ?: null,
            'approval_notes'                 => trim((string) input('approval_notes', '')) ?: null,
            'signature_notes'                => trim((string) input('signature_notes', '')) ?: null,
            'legal_notes'                    => trim((string) input('legal_notes', '')) ?: null,
            'notes'                          => trim((string) input('notes', '')) ?: null,
            'internal_notes'                 => trim((string) input('internal_notes', '')) ?: null,
            'responsible_user_id'            => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach (['sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'draft_document_id', 'final_document_id', 'signed_document_id'] as $k) {
            $q = input($k);
            if ($q !== null && $q !== '') {
                $data[$k] = (int) $q;
            }
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

        if (($data['formalized_value'] ?? null) === null || $data['formalized_value'] === '') {
            if ($sponsor['confirmed_amount'] !== null && $sponsor['confirmed_amount'] !== '') {
                $data['formalized_value'] = $sponsor['confirmed_amount'];
            } elseif ($sponsor['committed_amount'] !== null && $sponsor['committed_amount'] !== '') {
                $data['formalized_value'] = $sponsor['committed_amount'];
            }
        }

        if (($data['formalized_value'] ?? null) === null || $data['formalized_value'] === '') {
            $proposalId = (int) ($data['proposal_id'] ?? $sponsor['proposal_id'] ?? 0);
            if ($proposalId > 0) {
                $prop = (new Proposal())->findById($proposalId);
                if ($prop !== null && $prop['proposed_value'] !== null) {
                    $data['formalized_value'] = $prop['proposed_value'];
                }
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

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && (new Company())->findById($companyId) === null) {
            $errors['company_id'] = 'Empresa não encontrada.';
        }

        $docModel = new Document();
        foreach (['draft_document_id' => 'Documento minuta', 'final_document_id' => 'Documento final', 'signed_document_id' => 'Documento assinado'] as $field => $label) {
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
    private function sponsorFilterOptions(array $filters): array
    {
        $items = (new Sponsor())->paginate(['show_archived' => 0], 1, 200);

        return array_map(static fn ($s) => [
            'id'   => (int) $s['id'],
            'name' => (string) ($s['sponsor_display_name'] ?? ''),
        ], $items);
    }

    /** @return array<int, array<string, mixed>> */
    private function companyFilterOptions(array $filters): array
    {
        return (new Company())->activeOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function linkOptions(string $table, string $labelCol): array
    {
        return (new Proposal())->filterLinkOptions($table, $labelCol);
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = (new Contract())->findById((int) $id);
        if ($row === null) {
            $this->abort(404, 'Contrato não encontrado.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $contract
     */
    private function renderForm(string $view, string $title, array $old, array $errors, ?array $contract = null): void
    {
        $model     = new Contract();
        $sponsorId = (int) ($old['sponsor_id'] ?? 0);
        $companyId = (int) ($old['company_id'] ?? 0);

        $sponsors  = (new Sponsor())->paginate(['show_archived' => 0], 1, 300);
        $documents = [];
        if (can('documents.view')) {
            $docFilters = array_filter(['company_id' => $companyId ?: null, 'sponsor_id' => $sponsorId ?: null]);
            $documents  = (new Document())->paginate($docFilters, 1, 100);
        }

        $this->view($view, [
            'title'             => $title,
            'contract'          => $contract ?? $old,
            'old'               => $old,
            'errors'            => $errors,
            'contractTypes'     => $model->getContractTypes(),
            'fundingMechanisms' => $model->getFundingMechanisms(),
            'statuses'          => $model->getStatuses(),
            'reviewStatuses'    => $model->getReviewStatuses(),
            'signatureStatuses' => $model->getSignatureStatuses(),
            'sponsors'          => $sponsors,
            'companies'         => (new Company())->activeOptions(),
            'contacts'          => $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [],
            'opportunities'     => $this->linkOptions('opportunities', 'title'),
            'proposals'         => $this->linkOptions('proposals', 'title'),
            'quotas'            => (new Quota())->activeOptions(),
            'users'             => (new User())->activeList(),
            'documents'         => array_map(static fn ($d) => [
                'id'    => (int) $d['id'],
                'label' => (string) ($d['title'] ?? '') . ' (v' . (int) ($d['version_number'] ?? 1) . ')',
            ], $documents),
        ]);
    }
}
