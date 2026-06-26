<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\User;

/**
 * Módulo Propostas Comerciais (Etapa 10).
 */
final class ProposalController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('proposals.view');

        $model   = new Proposal();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('proposals/index', [
            'title'         => 'Propostas comerciais',
            'items'         => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'       => $filters,
            'types'         => $model->getTypes(),
            'statuses'      => $model->getStatuses(),
            'model'         => $model,
            'companies'     => $this->companyFilterOptions($filters),
            'contacts'      => $this->linkOptions('contacts', 'name'),
            'opportunities' => $this->linkOptions('opportunities', 'title'),
            'quotas'        => (new Quota())->activeOptions(),
            'users'         => (new User())->activeList(),
            'page'          => $page,
            'pages'         => $pages,
            'total'         => $total,
            'perPage'       => self::PER_PAGE,
            'hasFilters'    => $this->hasActiveFilters($filters),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        $this->renderForm('proposals/create', 'Nova proposta', $this->prefillFromQuery([
            'type'            => 'proposta_por_cota',
            'status'          => 'rascunho',
            'version_number'  => 1,
            'created_on'      => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('proposals/create', 'Nova proposta', $this->prefillFromQuery([
            'company_id' => $id, 'type' => 'proposta_por_cota', 'status' => 'rascunho',
            'version_number' => 1, 'created_on' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('proposals/create', 'Nova proposta', $this->prefillFromQuery([
            'company_id' => (int) $contact['company_id'], 'contact_id' => $id,
            'type' => 'proposta_por_cota', 'status' => 'rascunho', 'version_number' => 1,
            'created_on' => date('Y-m-d'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('proposals/create', 'Nova proposta', $this->prefillFromQuery([
            'company_id' => (int) $opp['company_id'],
            'contact_id' => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id' => $id,
            'quota_id' => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'proposed_value' => $opp['estimated_value'],
            'type' => 'proposta_por_cota', 'status' => 'rascunho', 'version_number' => 1,
            'created_on' => date('Y-m-d'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('proposals/create', 'Nova proposta', $this->prefillFromQuery([
            'quota_id' => $id, 'proposed_value' => $quota['amount'],
            'type' => 'proposta_por_cota', 'status' => 'rascunho', 'version_number' => 1,
            'created_on' => date('Y-m-d'), 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('proposals.create');
        csrf_verify();

        $model = new Proposal();
        $data  = $this->collectInput($model);
        $data  = $this->applyAutofill($model, $data);

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);
        $uploadErrors = $model->validateUpload($_FILES['pdf_file'] ?? []);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('proposals/create', 'Nova proposta', $data, $errors);
            return;
        }

        if (($uploadErrors === []) && isset($_FILES['pdf_file']) && ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $stored = $model->storePdfUpload($_FILES['pdf_file']);
            $data['pdf_file_path']     = $stored['path'];
            $data['pdf_original_name'] = $stored['original_name'];
        }

        $data = $this->applySentDefaults($data);
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('proposal_created', $_SESSION['user_id'] ?? null, 'proposal', $id);
        flash('success', 'Proposta cadastrada com sucesso.');
        $this->redirect('/proposals/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.view');
        $proposal = $this->findOr404($params['id'] ?? null);
        $model    = new Proposal();
        $pid      = (int) $proposal['id'];

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByProposal($pid, 6);
            $documentSummary = $documentModel->summaryByProposal($pid);
        }

        $sponsors       = [];
        $sponsorSummary = ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
        $sponsorModel   = null;
        if (can('sponsors.view')) {
            $sponsorModel   = new Sponsor();
            $sponsors       = $sponsorModel->findByProposal($pid, 6);
            $sponsorSummary = $sponsorModel->summaryByProposal($pid);
        }

        $this->view('proposals/show', [
            'title'    => $proposal['title'] ?? 'Proposta',
            'proposal' => $proposal,
            'model'    => $model,
            'types'    => $model->getTypes(),
            'statuses' => $model->getStatuses(),
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
            'sponsors'        => $sponsors,
            'sponsorSummary'  => $sponsorSummary,
            'sponsorModel'    => $sponsorModel,
        ]);
    }

    public function pdf(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.view');
        $proposal = $this->findOr404($params['id'] ?? null);
        $path     = (string) ($proposal['pdf_file_path'] ?? '');

        if ($path === '' || !is_file($path)) {
            $this->abort(404, 'PDF não encontrado.');
        }

        $name = (string) ($proposal['pdf_original_name'] ?? 'proposta.pdf');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.edit');
        $proposal = $this->findOr404($params['id'] ?? null);

        if (!empty($proposal['archived_at'])) {
            flash('error', 'Esta proposta está arquivada. Restaure-a antes de editar.');
            $this->redirect('/proposals/' . (int) $proposal['id']);
            return;
        }

        $this->renderForm('proposals/edit', 'Editar proposta', $proposal, [], $proposal);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.edit');
        csrf_verify();

        $proposal = $this->findOr404($params['id'] ?? null);
        $id       = (int) $proposal['id'];

        if (!empty($proposal['archived_at'])) {
            flash('error', 'Esta proposta está arquivada. Restaure-a antes de editar.');
            $this->redirect('/proposals/' . $id);
            return;
        }

        $model  = new Proposal();
        $data   = $this->collectInput($model);
        $data   = $this->applyAutofill($model, $data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);
        $uploadErrors = $model->validateUpload($_FILES['pdf_file'] ?? []);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('proposals/edit', 'Editar proposta', $data, $errors, array_merge($proposal, $data));
            return;
        }

        if (($uploadErrors === []) && isset($_FILES['pdf_file']) && ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $stored = $model->storePdfUpload($_FILES['pdf_file']);
            $data['pdf_file_path']     = $stored['path'];
            $data['pdf_original_name'] = $stored['original_name'];
            if (!empty($proposal['pdf_file_path'])) {
                $note = '[' . date('Y-m-d H:i') . '] PDF substituído.';
                $prev = trim((string) ($data['revision_notes'] ?? $proposal['revision_notes'] ?? ''));
                $data['revision_notes'] = $prev === '' ? $note : ($prev . "\n" . $note);
            }
        }

        $statusChanged = (string) $proposal['status'] !== (string) ($data['status'] ?? '');
        $data = $this->applySentDefaults($data, $proposal);
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('proposal_updated', $_SESSION['user_id'] ?? null, 'proposal', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('proposal_status_changed', $_SESSION['user_id'] ?? null, 'proposal', $id);
        }

        flash('success', 'Proposta atualizada com sucesso.');
        $this->redirect('/proposals/' . $id);
    }

    public function versionForm(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.version');
        $proposal = $this->findOr404($params['id'] ?? null);

        $this->view('proposals/version', [
            'title'    => 'Nova versão — ' . ($proposal['title'] ?? ''),
            'proposal' => $proposal,
            'old'      => [
                'title'          => $proposal['title'],
                'proposed_value' => $proposal['proposed_value'],
                'valid_until'    => $proposal['valid_until'],
                'revision_notes' => $proposal['revision_notes'],
                'notes'          => $proposal['notes'],
            ],
            'errors'   => [],
            'types'    => (new Proposal())->getTypes(),
        ]);
    }

    public function versionStore(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.version');
        csrf_verify();

        $base = $this->findOr404($params['id'] ?? null);
        $model = new Proposal();

        $data = [
            'title'          => clean((string) input('title', (string) $base['title'])),
            'proposed_value' => $model->normalizeMoney((string) input('proposed_value', (string) ($base['proposed_value'] ?? ''))),
            'valid_until'    => $model->normalizeDate((string) input('valid_until', (string) ($base['valid_until'] ?? ''))),
            'revision_notes' => trim((string) input('revision_notes', '')) ?: null,
            'notes'          => trim((string) input('notes', '')) ?: null,
            'created_by'     => $_SESSION['user_id'] ?? null,
        ];

        $errors = $model->validate(array_merge($base, $data), 'create');
        unset($errors['company_id']);

        $uploadErrors = $model->validateUpload($_FILES['pdf_file'] ?? []);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->view('proposals/version', [
                'title' => 'Nova versão — ' . ($base['title'] ?? ''),
                'proposal' => $base, 'old' => $data, 'errors' => $errors,
                'types' => $model->getTypes(),
            ]);
            return;
        }

        if (($uploadErrors === []) && isset($_FILES['pdf_file']) && ($_FILES['pdf_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $stored = $model->storePdfUpload($_FILES['pdf_file']);
            $data['pdf_file_path']     = $stored['path'];
            $data['pdf_original_name'] = $stored['original_name'];
        }

        $newId = $model->createVersion((int) $base['id'], $data);
        (new ActivityLog())->record('proposal_version_created', $_SESSION['user_id'] ?? null, 'proposal', $newId);

        flash('success', 'Nova versão da proposta criada.');
        $this->redirect('/proposals/' . $newId);
    }

    public function markSent(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.send');
        csrf_verify();

        $proposal = $this->findOr404($params['id'] ?? null);
        $id       = (int) $proposal['id'];
        $uid      = (int) ($_SESSION['user_id'] ?? 0);

        (new Proposal())->markSent($id, $uid);
        (new ActivityLog())->record('proposal_marked_sent', $uid ?: null, 'proposal', $id);

        flash('success', 'Proposta marcada como enviada.');
        $this->redirect('/proposals/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.edit');
        csrf_verify();

        $proposal = $this->findOr404($params['id'] ?? null);
        $id       = (int) $proposal['id'];
        $model    = new Proposal();

        $status = clean((string) input('status', (string) $proposal['status']));
        if (!array_key_exists($status, $model->getStatuses())) {
            flash('error', 'Status inválido.');
            $this->redirect('/proposals/' . $id);
            return;
        }

        $note = trim((string) input('notes', ''));
        $append = '';
        if ($note !== '') {
            $append = "\n[" . date('Y-m-d H:i') . '] ' . $note;
        }

        $model->updateStatus($id, [
            'status'       => $status,
            'fill_sent'    => $status === 'enviada',
            'sent_by'      => $_SESSION['user_id'] ?? null,
            'notes_append' => $append !== '' ? $append : null,
        ]);

        (new ActivityLog())->record('proposal_status_changed', $_SESSION['user_id'] ?? null, 'proposal', $id);
        flash('success', 'Status atualizado.');
        $this->redirect('/proposals/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.archive');
        csrf_verify();

        $proposal = $this->findOr404($params['id'] ?? null);
        $id       = (int) $proposal['id'];

        if (empty($proposal['archived_at'])) {
            (new Proposal())->archive($id);
            (new ActivityLog())->record('proposal_archived', $_SESSION['user_id'] ?? null, 'proposal', $id);
            flash('success', 'Proposta arquivada.');
        }

        $this->redirect('/proposals/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('proposals.archive');
        csrf_verify();

        $proposal = $this->findOr404($params['id'] ?? null);
        $id       = (int) $proposal['id'];

        if (!empty($proposal['archived_at'])) {
            (new Proposal())->restore($id);
            (new ActivityLog())->record('proposal_restored', $_SESSION['user_id'] ?? null, 'proposal', $id);
            flash('success', 'Proposta restaurada.');
        }

        $this->redirect('/proposals/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                  => (string) input('q', ''),
            'company_id'         => (int) input('company_id', 0),
            'contact_id'         => (int) input('contact_id', 0),
            'opportunity_id'     => (int) input('opportunity_id', 0),
            'quota_id'           => (int) input('quota_id', 0),
            'type'               => (string) input('type', ''),
            'status'             => (string) input('status', ''),
            'responsible_user_id'=> (int) input('responsible_user_id', 0),
            'sent'               => input('sent') !== null ? 1 : 0,
            'not_sent'           => input('not_sent') !== null ? 1 : 0,
            'expired'            => input('expired') !== null ? 1 : 0,
            'valid_from'         => (string) input('valid_from', ''),
            'valid_to'           => (string) input('valid_to', ''),
            'show_archived'      => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Proposal $model): array
    {
        return [
            'company_id'          => (int) input('company_id', 0),
            'contact_id'          => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'      => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'quota_id'            => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'title'               => clean((string) input('title', '')),
            'type'                => clean((string) input('type', 'proposta_por_cota')),
            'proposed_value'      => $model->normalizeMoney((string) input('proposed_value', '')),
            'version_number'      => (int) input('version_number', 1),
            'status'              => clean((string) input('status', 'rascunho')),
            'created_on'          => $model->normalizeDate((string) input('created_on', '')),
            'sent_at'             => $model->normalizeDateTime((string) input('sent_at', '')),
            'valid_until'         => $model->normalizeDate((string) input('valid_until', '')),
            'responsible_user_id' => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
            'revision_notes'      => trim((string) input('revision_notes', '')) ?: null,
            'notes'               => trim((string) input('notes', '')) ?: null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id'] as $k) {
            $q = input($k);
            if ($q !== null && $q !== '') {
                $data[$k] = (int) $q;
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function applyAutofill(Proposal $model, array $data): array
    {
        if (!empty($data['opportunity_id']) && empty($data['company_id'])) {
            $opp = (new Opportunity())->findById((int) $data['opportunity_id']);
            if ($opp !== null) {
                $data['company_id'] = (int) $opp['company_id'];
                if (empty($data['contact_id']) && !empty($opp['contact_id'])) {
                    $data['contact_id'] = (int) $opp['contact_id'];
                }
            }
        }

        if (!empty($data['opportunity_id']) && ($data['proposed_value'] === null || $data['proposed_value'] === '')) {
            $opp = (new Opportunity())->findById((int) $data['opportunity_id']);
            if ($opp !== null && $opp['estimated_value'] !== null) {
                $data['proposed_value'] = $opp['estimated_value'];
            }
        }

        if (!empty($data['quota_id']) && ($data['proposed_value'] === null || $data['proposed_value'] === '')) {
            $quota = (new Quota())->findById((int) $data['quota_id']);
            if ($quota !== null && $quota['amount'] !== null) {
                $data['proposed_value'] = $quota['amount'];
            }
        }

        if (empty($data['created_on'])) {
            $data['created_on'] = date('Y-m-d');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function applySentDefaults(array $data, ?array $existing = null): array
    {
        if (($data['status'] ?? '') === 'enviada' && empty($data['sent_at']) && empty($existing['sent_at'] ?? null)) {
            $data['sent_at'] = date('Y-m-d H:i:s');
            $data['sent_by'] = $_SESSION['user_id'] ?? null;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
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

        $quotaId = (int) ($data['quota_id'] ?? 0);
        if ($quotaId > 0 && (new Quota())->findById($quotaId) === null) {
            $errors['quota_id'] = 'Cota não encontrada.';
        }

        $respId = (int) ($data['responsible_user_id'] ?? 0);
        if ($respId > 0 && (new User())->findBy('id', $respId) === null) {
            $errors['responsible_user_id'] = 'Responsável não encontrado.';
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkOptions(string $table, string $labelCol): array
    {
        $allowed = ['contacts' => true, 'opportunities' => true];
        if (!isset($allowed[$table])) {
            return [];
        }

        return (new Proposal())->filterLinkOptions($table, $labelCol);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $proposal
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $proposal = []): void
    {
        $model     = new Proposal();
        $companyId = (int) ($old['company_id'] ?? ($proposal['company_id'] ?? 0));
        $companyContacts = $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [];

        $this->view($view, [
            'title'           => $title,
            'old'             => $old,
            'errors'          => $errors,
            'proposal'        => $proposal,
            'types'           => $model->getTypes(),
            'statuses'        => $model->getStatuses(),
            'companies'       => (new Company())->activeOptions(),
            'opportunities'   => $this->linkOptions('opportunities', 'title'),
            'quotas'          => (new Quota())->activeOptions(),
            'users'           => (new User())->activeList(),
            'companyContacts' => $companyContacts,
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
        foreach (['q', 'type', 'status', 'valid_from', 'valid_to'] as $k) {
            if (trim((string) ($filters[$k] ?? '')) !== '') {
                return true;
            }
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'responsible_user_id'] as $k) {
            if ((int) ($filters[$k] ?? 0) > 0) {
                return true;
            }
        }

        foreach (['sent', 'not_sent', 'expired', 'show_archived'] as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = is_numeric($id) ? (new Proposal())->findById((int) $id) : null;
        if ($row === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }

        return $row;
    }
}
