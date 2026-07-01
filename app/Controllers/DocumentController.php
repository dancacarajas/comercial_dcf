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
use App\Models\FinancialEntry;
use App\Models\IncentiveProject;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\SponsorDossier;
use App\Models\User;

/**
 * Módulo Documentos e Arquivos (Etapa 11).
 */
final class DocumentController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('documents.view');

        $model   = new Document();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('documents/index', [
            'title'         => 'Documentos e arquivos',
            'items'         => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'       => $filters,
            'categories'    => $model->getCategories(),
            'statuses'      => $model->getStatuses(),
            'accessLevels'  => $model->getAccessLevels(),
            'model'         => $model,
            'companies'     => $this->companyFilterOptions($filters),
            'contacts'      => $this->linkOptions('contacts', 'name'),
            'opportunities' => $this->linkOptions('opportunities', 'title'),
            'quotas'        => (new Quota())->activeOptions(),
            'proposals'     => $this->proposalFilterOptions(),
            'leads'         => $this->leadFilterOptions(),
            'sponsors'      => $this->sponsorFilterOptions(),
            'counterparts'  => $this->counterpartFilterOptions(),
            'contracts'     => $this->contractFilterOptions(),
            'financials'    => $this->financialFilterOptions(),
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
        AuthMiddleware::requirePermission('documents.create');
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'category'            => 'documento_comercial',
            'status'              => 'ativo',
            'access_level'        => 'interno',
            'version_number'      => 1,
            'document_date'       => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'company_id' => $id, 'category' => 'documento_comercial', 'status' => 'ativo',
            'access_level' => 'interno', 'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'company_id' => (int) $contact['company_id'], 'contact_id' => $id,
            'category' => 'documento_comercial', 'status' => 'ativo', 'access_level' => 'interno',
            'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'company_id' => (int) $opp['company_id'],
            'contact_id' => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id' => $id,
            'quota_id' => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'category' => 'documento_comercial', 'status' => 'ativo', 'access_level' => 'interno',
            'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'quota_id' => $id, 'category' => 'documento_comercial', 'status' => 'ativo',
            'access_level' => 'interno', 'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id       = (int) ($params['id'] ?? 0);
        $proposal = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($proposal === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'company_id' => (int) $proposal['company_id'],
            'contact_id' => $proposal['contact_id'] ? (int) $proposal['contact_id'] : null,
            'opportunity_id' => $proposal['opportunity_id'] ? (int) $proposal['opportunity_id'] : null,
            'quota_id' => $proposal['quota_id'] ? (int) $proposal['quota_id'] : null,
            'proposal_id' => $id,
            'category' => 'proposta_pdf', 'status' => 'ativo', 'access_level' => 'interno',
            'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForLead(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id   = (int) ($params['id'] ?? 0);
        $lead = $id > 0 ? (new Lead())->findById($id) : null;
        if ($lead === null) {
            $this->abort(404, 'Lead não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'lead_id' => $id, 'category' => 'briefing_empresa', 'status' => 'ativo',
            'access_level' => 'interno', 'version_number' => 1, 'document_date' => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForSponsor(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id      = (int) ($params['id'] ?? 0);
        $sponsor = $id > 0 ? (new Sponsor())->findById($id) : null;
        if ($sponsor === null) {
            $this->abort(404, 'Patrocinador não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'sponsor_id'      => $id,
            'company_id'      => (int) $sponsor['company_id'],
            'contact_id'      => $sponsor['contact_id'] ? (int) $sponsor['contact_id'] : null,
            'opportunity_id'  => $sponsor['opportunity_id'] ? (int) $sponsor['opportunity_id'] : null,
            'proposal_id'     => $sponsor['proposal_id'] ? (int) $sponsor['proposal_id'] : null,
            'quota_id'        => $sponsor['quota_id'] ? (int) $sponsor['quota_id'] : null,
            'category'        => 'documento_comercial',
            'status'          => 'ativo',
            'access_level'    => 'interno',
            'version_number'  => 1,
            'document_date'   => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForCounterpart(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id          = (int) ($params['id'] ?? 0);
        $counterpart = $id > 0 ? (new Counterpart())->findById($id) : null;
        if ($counterpart === null) {
            $this->abort(404, 'Contrapartida não encontrada.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'counterpart_id'      => $id,
            'sponsor_id'          => (int) $counterpart['sponsor_id'],
            'company_id'          => $counterpart['company_id'] ? (int) $counterpart['company_id'] : null,
            'contact_id'          => $counterpart['contact_id'] ? (int) $counterpart['contact_id'] : null,
            'opportunity_id'      => $counterpart['opportunity_id'] ? (int) $counterpart['opportunity_id'] : null,
            'proposal_id'         => $counterpart['proposal_id'] ? (int) $counterpart['proposal_id'] : null,
            'quota_id'            => $counterpart['quota_id'] ? (int) $counterpart['quota_id'] : null,
            'category'            => 'documento_comercial',
            'status'              => 'ativo',
            'access_level'        => 'interno',
            'version_number'      => 1,
            'document_date'       => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
            'use_as_evidence'     => 1,
        ]), []);
    }

    public function createForContract(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id       = (int) ($params['id'] ?? 0);
        $contract = $id > 0 ? (new Contract())->findById($id) : null;
        if ($contract === null) {
            $this->abort(404, 'Contrato não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'contract_id'         => $id,
            'sponsor_id'          => (int) $contract['sponsor_id'],
            'company_id'          => $contract['company_id'] ? (int) $contract['company_id'] : null,
            'contact_id'          => $contract['contact_id'] ? (int) $contract['contact_id'] : null,
            'opportunity_id'      => $contract['opportunity_id'] ? (int) $contract['opportunity_id'] : null,
            'proposal_id'         => $contract['proposal_id'] ? (int) $contract['proposal_id'] : null,
            'quota_id'            => $contract['quota_id'] ? (int) $contract['quota_id'] : null,
            'category'            => 'documento_comercial',
            'status'              => 'ativo',
            'access_level'        => 'interno',
            'version_number'      => 1,
            'document_date'       => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
            'use_as_draft'        => 1,
        ]), []);
    }

    public function createForFinancialEntry(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id    = (int) ($params['id'] ?? 0);
        $entry = $id > 0 ? (new FinancialEntry())->findById($id) : null;
        if ($entry === null) {
            $this->abort(404, 'Lançamento financeiro não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'financial_entry_id'  => $id,
            'sponsor_id'          => $entry['sponsor_id'] ? (int) $entry['sponsor_id'] : null,
            'contract_id'         => $entry['contract_id'] ? (int) $entry['contract_id'] : null,
            'company_id'          => $entry['company_id'] ? (int) $entry['company_id'] : null,
            'contact_id'          => $entry['contact_id'] ? (int) $entry['contact_id'] : null,
            'opportunity_id'      => $entry['opportunity_id'] ? (int) $entry['opportunity_id'] : null,
            'proposal_id'         => $entry['proposal_id'] ? (int) $entry['proposal_id'] : null,
            'quota_id'            => $entry['quota_id'] ? (int) $entry['quota_id'] : null,
            'category'            => 'comprovante_envio',
            'status'              => 'ativo',
            'access_level'        => 'interno',
            'version_number'      => 1,
            'document_date'       => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForSponsorDossier(array $params): void
    {
        AuthMiddleware::requirePermission('documents.create');
        $id      = (int) ($params['id'] ?? 0);
        $dossier = $id > 0 ? (new SponsorDossier())->findById($id) : null;
        if ($dossier === null) {
            $this->abort(404, 'Dossiê não encontrado.');
        }
        $this->renderForm('documents/create', 'Novo documento', $this->prefillFromQuery([
            'sponsor_dossier_id'  => $id,
            'sponsor_id'          => $dossier['sponsor_id'] ? (int) $dossier['sponsor_id'] : null,
            'contract_id'         => $dossier['main_contract_id'] ? (int) $dossier['main_contract_id'] : null,
            'company_id'          => $dossier['company_id'] ? (int) $dossier['company_id'] : null,
            'contact_id'          => $dossier['contact_id'] ? (int) $dossier['contact_id'] : null,
            'opportunity_id'      => $dossier['opportunity_id'] ? (int) $dossier['opportunity_id'] : null,
            'proposal_id'         => $dossier['proposal_id'] ? (int) $dossier['proposal_id'] : null,
            'quota_id'            => $dossier['quota_id'] ? (int) $dossier['quota_id'] : null,
            'category'            => 'documento_comercial',
            'status'              => 'ativo',
            'access_level'        => 'interno',
            'version_number'      => 1,
            'document_date'       => date('Y-m-d'),
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('documents.create');
        csrf_verify();

        $model = new Document();
        $data  = $this->collectInput($model);
        $data  = $this->applyAutofill($data);

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);
        $uploadErrors = $model->validateUpload($_FILES['document_file'] ?? [], true);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('documents/create', 'Novo documento', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->insertWithFile($data, $_FILES['document_file']);

        (new ActivityLog())->record('document_created', $_SESSION['user_id'] ?? null, 'document', $id);
        if (!empty($data['sponsor_id'])) {
            (new ActivityLog())->record('sponsor_document_linked', $_SESSION['user_id'] ?? null, 'sponsor', (int) $data['sponsor_id']);
        }
        if (!empty($data['counterpart_id'])) {
            (new ActivityLog())->record('counterpart_document_linked', $_SESSION['user_id'] ?? null, 'counterpart', (int) $data['counterpart_id']);
            if (!empty($data['use_as_evidence']) && can('counterparts.edit')) {
                (new Counterpart())->update((int) $data['counterpart_id'], [
                    'evidence_document_id' => $id,
                    'updated_by'           => $_SESSION['user_id'] ?? null,
                ]);
                (new ActivityLog())->record('counterpart_evidence_linked', $_SESSION['user_id'] ?? null, 'counterpart', (int) $data['counterpart_id']);
            }
        }
        $this->linkDocumentToContract($data, $id);
        $this->linkDocumentToFinancial($data, $id);
        $this->linkDocumentToDossier($data, $id);
        flash('success', 'Documento cadastrado com sucesso.');
        $this->redirect('/documents/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('documents.view');
        $document = $this->findOr404($params['id'] ?? null);
        $model    = new Document();

        $this->view('documents/show', [
            'title'        => $document['title'] ?? 'Documento',
            'document'     => $document,
            'model'        => $model,
            'categories'   => $model->getCategories(),
            'statuses'     => $model->getStatuses(),
            'accessLevels' => $model->getAccessLevels(),
        ]);
    }

    public function download(array $params): void
    {
        AuthMiddleware::requirePermission('documents.download');

        $document = $this->findOr404($params['id'] ?? null);
        $id       = (int) $document['id'];

        if (!empty($document['archived_at']) && !can('documents.archive')) {
            $this->abort(403, 'Documento arquivado. Restaure-o ou solicite permissão de arquivamento.');
        }

        $info = (new Document())->downloadInfo($id);
        if ($info === null) {
            $this->abort(404, 'Arquivo não encontrado.');
        }

        (new ActivityLog())->record('document_downloaded', $_SESSION['user_id'] ?? null, 'document', $id);

        $name = $info['original_name'];
        header('Content-Type: ' . $info['mime_type']);
        header('Content-Disposition: attachment; filename="' . rawurlencode($name) . '"');
        header('Content-Length: ' . (string) $info['size_bytes']);
        header('X-Content-Type-Options: nosniff');
        readfile($info['path']);
        exit;
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('documents.edit');
        $document = $this->findOr404($params['id'] ?? null);

        if (!empty($document['archived_at'])) {
            flash('error', 'Este documento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/documents/' . (int) $document['id']);
            return;
        }

        $this->renderForm('documents/edit', 'Editar documento', $document, [], $document);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('documents.edit');
        csrf_verify();

        $document = $this->findOr404($params['id'] ?? null);
        $id       = (int) $document['id'];

        if (!empty($document['archived_at'])) {
            flash('error', 'Este documento está arquivado. Restaure-o antes de editar.');
            $this->redirect('/documents/' . $id);
            return;
        }

        $model  = new Document();
        $data   = $this->collectInput($model);
        $data   = $this->applyAutofill($data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        $file     = $_FILES['document_file'] ?? [];
        $hasFile  = ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        $uploadErrors = $model->validateUpload($file, false);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('documents/edit', 'Editar documento', $data, $errors, array_merge($document, $data));
            return;
        }

        if ($hasFile) {
            $note = '[' . date('Y-m-d H:i') . '] Arquivo substituído.';
            $prev = trim((string) ($data['notes'] ?? $document['notes'] ?? ''));
            $data['notes'] = $prev === '' ? $note : ($prev . "\n" . $note);
        }

        $statusChanged = (string) $document['status'] !== (string) ($data['status'] ?? '');
        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data, $hasFile ? $file : null);

        (new ActivityLog())->record('document_updated', $_SESSION['user_id'] ?? null, 'document', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('document_status_changed', $_SESSION['user_id'] ?? null, 'document', $id);
        }
        $this->linkDocumentToContract($data, $id);
        $this->linkDocumentToFinancial($data, $id);
        $this->linkDocumentToDossier($data, $id);

        flash('success', 'Documento atualizado com sucesso.');
        $this->redirect('/documents/' . $id);
    }

    public function versionForm(array $params): void
    {
        AuthMiddleware::requirePermission('documents.version');
        $document = $this->findOr404($params['id'] ?? null);

        $this->view('documents/version', [
            'title'        => 'Nova versão — ' . ($document['title'] ?? ''),
            'document'     => $document,
            'old'          => [
                'title'       => $document['title'],
                'description' => $document['description'],
                'category'    => $document['category'],
                'valid_until' => $document['valid_until'],
                'notes'       => $document['notes'],
                'status'      => 'ativo',
            ],
            'errors'       => [],
            'categories'   => (new Document())->getCategories(),
            'statuses'     => (new Document())->getStatuses(),
        ]);
    }

    public function versionStore(array $params): void
    {
        AuthMiddleware::requirePermission('documents.version');
        csrf_verify();

        $base  = $this->findOr404($params['id'] ?? null);
        $model = new Document();

        $data = [
            'title'       => clean((string) input('title', (string) $base['title'])),
            'description' => trim((string) input('description', (string) ($base['description'] ?? ''))) ?: null,
            'category'    => clean((string) input('category', (string) $base['category'])),
            'status'      => clean((string) input('status', 'ativo')),
            'valid_until' => $model->normalizeDate((string) input('valid_until', (string) ($base['valid_until'] ?? ''))),
            'notes'       => trim((string) input('notes', '')) ?: null,
            'created_by'  => $_SESSION['user_id'] ?? null,
        ];

        $errors = $model->validate($data, 'create');
        $uploadErrors = $model->validateUpload($_FILES['document_file'] ?? [], true);
        $errors = array_merge($errors, $uploadErrors);

        if ($errors !== []) {
            http_response_code(422);
            $this->view('documents/version', [
                'title' => 'Nova versão — ' . ($base['title'] ?? ''),
                'document' => $base, 'old' => $data, 'errors' => $errors,
                'categories' => $model->getCategories(), 'statuses' => $model->getStatuses(),
            ]);
            return;
        }

        $newId = $model->createVersion((int) $base['id'], $data, $_FILES['document_file']);
        (new ActivityLog())->record('document_version_created', $_SESSION['user_id'] ?? null, 'document', $newId);

        if (input('mark_previous_substituted') !== null) {
            $model->updateStatus((int) $base['id'], [
                'status'       => 'substituido',
                'notes_append' => "\n[" . date('Y-m-d H:i') . '] Substituído pela versão #' . $newId . '.',
                'updated_by'   => $_SESSION['user_id'] ?? null,
            ]);
            (new ActivityLog())->record('document_status_changed', $_SESSION['user_id'] ?? null, 'document', (int) $base['id']);
        }

        flash('success', 'Nova versão do documento criada.');
        $this->redirect('/documents/' . $newId);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('documents.edit');
        csrf_verify();

        $document = $this->findOr404($params['id'] ?? null);
        $id       = (int) $document['id'];
        $model    = new Document();

        $status = clean((string) input('status', (string) $document['status']));
        if (!array_key_exists($status, $model->getStatuses())) {
            flash('error', 'Status inválido.');
            $this->redirect('/documents/' . $id);
            return;
        }

        $note = trim((string) input('notes', ''));
        $append = $note !== '' ? "\n[" . date('Y-m-d H:i') . '] ' . $note : '';

        $model->updateStatus($id, [
            'status'       => $status,
            'notes_append' => $append !== '' ? $append : null,
            'updated_by'   => $_SESSION['user_id'] ?? null,
        ]);

        (new ActivityLog())->record('document_status_changed', $_SESSION['user_id'] ?? null, 'document', $id);
        flash('success', 'Status atualizado.');
        $this->redirect('/documents/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('documents.archive');
        csrf_verify();

        $document = $this->findOr404($params['id'] ?? null);
        $id       = (int) $document['id'];

        if (empty($document['archived_at'])) {
            (new Document())->archive($id);
            (new ActivityLog())->record('document_archived', $_SESSION['user_id'] ?? null, 'document', $id);
            flash('success', 'Documento arquivado.');
        }

        $this->redirect('/documents/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('documents.archive');
        csrf_verify();

        $document = $this->findOr404($params['id'] ?? null);
        $id       = (int) $document['id'];

        if (!empty($document['archived_at'])) {
            (new Document())->restore($id);
            (new ActivityLog())->record('document_restored', $_SESSION['user_id'] ?? null, 'document', $id);
            flash('success', 'Documento restaurado.');
        }

        $this->redirect('/documents/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                   => (string) input('q', ''),
            'incentive_project_id'=> (int) input('incentive_project_id', 0),
            'company_id'          => (int) input('company_id', 0),
            'contact_id'          => (int) input('contact_id', 0),
            'opportunity_id'      => (int) input('opportunity_id', 0),
            'quota_id'            => (int) input('quota_id', 0),
            'proposal_id'         => (int) input('proposal_id', 0),
            'lead_id'             => (int) input('lead_id', 0),
            'sponsor_id'          => (int) input('sponsor_id', 0),
            'counterpart_id'      => (int) input('counterpart_id', 0),
            'contract_id'         => (int) input('contract_id', 0),
            'financial_entry_id'  => (int) input('financial_entry_id', 0),
            'sponsor_dossier_id'  => (int) input('sponsor_dossier_id', 0),
            'category'            => (string) input('category', ''),
            'status'              => (string) input('status', ''),
            'access_level'        => (string) input('access_level', ''),
            'responsible_user_id' => (int) input('responsible_user_id', 0),
            'expired'             => input('expired') !== null ? 1 : 0,
            'valid_from'          => (string) input('valid_from', ''),
            'valid_to'            => (string) input('valid_to', ''),
            'show_archived'       => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Document $model): array
    {
        return [
            'incentive_project_id'=> ($projectId = (int) input('incentive_project_id', 0)) > 0 ? $projectId : null,
            'company_id'          => input('company_id') !== null && input('company_id') !== '' ? (int) input('company_id') : null,
            'contact_id'          => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'      => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'quota_id'            => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'proposal_id'         => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'lead_id'             => input('lead_id') !== null && input('lead_id') !== '' ? (int) input('lead_id') : null,
            'sponsor_id'          => input('sponsor_id') !== null && input('sponsor_id') !== '' ? (int) input('sponsor_id') : null,
            'counterpart_id'      => input('counterpart_id') !== null && input('counterpart_id') !== '' ? (int) input('counterpart_id') : null,
            'contract_id'         => input('contract_id') !== null && input('contract_id') !== '' ? (int) input('contract_id') : null,
            'financial_entry_id'  => input('financial_entry_id') !== null && input('financial_entry_id') !== '' ? (int) input('financial_entry_id') : null,
            'sponsor_dossier_id'  => input('sponsor_dossier_id') !== null && input('sponsor_dossier_id') !== '' ? (int) input('sponsor_dossier_id') : null,
            'use_as_evidence'     => input('use_as_evidence') !== null ? 1 : 0,
            'use_as_draft'        => input('use_as_draft') !== null ? 1 : 0,
            'use_as_final'        => input('use_as_final') !== null ? 1 : 0,
            'use_as_signed'       => input('use_as_signed') !== null ? 1 : 0,
            'use_as_proof'        => input('use_as_proof') !== null ? 1 : 0,
            'use_as_receipt'      => input('use_as_receipt') !== null ? 1 : 0,
            'use_as_fiscal'       => input('use_as_fiscal') !== null ? 1 : 0,
            'use_as_dossier_main' => input('use_as_dossier_main') !== null ? 1 : 0,
            'use_as_dossier_final'=> input('use_as_dossier_final') !== null ? 1 : 0,
            'use_as_dossier_delivery_receipt' => input('use_as_dossier_delivery_receipt') !== null ? 1 : 0,
            'title'               => clean((string) input('title', '')),
            'description'         => trim((string) input('description', '')) ?: null,
            'category'            => clean((string) input('category', 'documento_comercial')),
            'status'              => clean((string) input('status', 'ativo')),
            'access_level'        => clean((string) input('access_level', 'interno')),
            'document_date'       => $model->normalizeDate((string) input('document_date', '')),
            'valid_until'         => $model->normalizeDate((string) input('valid_until', '')),
            'responsible_user_id' => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
            'notes'               => trim((string) input('notes', '')) ?: null,
            'version_number'      => (int) input('version_number', 1),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach (['incentive_project_id', 'company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id', 'sponsor_id', 'counterpart_id', 'contract_id', 'financial_entry_id', 'sponsor_dossier_id'] as $k) {
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
            }
        }

        if (!empty($data['opportunity_id']) && empty($data['company_id'])) {
            $opp = (new Opportunity())->findById((int) $data['opportunity_id']);
            if ($opp !== null) {
                $data['company_id'] = (int) $opp['company_id'];
                if (empty($data['contact_id']) && !empty($opp['contact_id'])) {
                    $data['contact_id'] = (int) $opp['contact_id'];
                }
                if (empty($data['quota_id']) && !empty($opp['quota_id'])) {
                    $data['quota_id'] = (int) $opp['quota_id'];
                }
            }
        }

        if (!empty($data['sponsor_id'])) {
            $sp = (new Sponsor())->findById((int) $data['sponsor_id']);
            if ($sp !== null) {
                if (empty($data['company_id'])) {
                    $data['company_id'] = (int) $sp['company_id'];
                }
                if (empty($data['contact_id']) && !empty($sp['contact_id'])) {
                    $data['contact_id'] = (int) $sp['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($sp['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $sp['opportunity_id'];
                }
                if (empty($data['proposal_id']) && !empty($sp['proposal_id'])) {
                    $data['proposal_id'] = (int) $sp['proposal_id'];
                }
                if (empty($data['quota_id']) && !empty($sp['quota_id'])) {
                    $data['quota_id'] = (int) $sp['quota_id'];
                }
            }
        }

        if (!empty($data['counterpart_id'])) {
            $cp = (new Counterpart())->findById((int) $data['counterpart_id']);
            if ($cp !== null) {
                if (empty($data['sponsor_id']) && !empty($cp['sponsor_id'])) {
                    $data['sponsor_id'] = (int) $cp['sponsor_id'];
                }
                if (empty($data['company_id']) && !empty($cp['company_id'])) {
                    $data['company_id'] = (int) $cp['company_id'];
                }
                if (empty($data['contact_id']) && !empty($cp['contact_id'])) {
                    $data['contact_id'] = (int) $cp['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($cp['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $cp['opportunity_id'];
                }
                if (empty($data['proposal_id']) && !empty($cp['proposal_id'])) {
                    $data['proposal_id'] = (int) $cp['proposal_id'];
                }
                if (empty($data['quota_id']) && !empty($cp['quota_id'])) {
                    $data['quota_id'] = (int) $cp['quota_id'];
                }
            }
        }

        if (!empty($data['contract_id'])) {
            $ct = (new Contract())->findById((int) $data['contract_id']);
            if ($ct !== null) {
                if (empty($data['sponsor_id']) && !empty($ct['sponsor_id'])) {
                    $data['sponsor_id'] = (int) $ct['sponsor_id'];
                }
                if (empty($data['company_id']) && !empty($ct['company_id'])) {
                    $data['company_id'] = (int) $ct['company_id'];
                }
                if (empty($data['contact_id']) && !empty($ct['contact_id'])) {
                    $data['contact_id'] = (int) $ct['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($ct['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $ct['opportunity_id'];
                }
                if (empty($data['proposal_id']) && !empty($ct['proposal_id'])) {
                    $data['proposal_id'] = (int) $ct['proposal_id'];
                }
                if (empty($data['quota_id']) && !empty($ct['quota_id'])) {
                    $data['quota_id'] = (int) $ct['quota_id'];
                }
            }
        }

        if (!empty($data['financial_entry_id'])) {
            $fe = (new FinancialEntry())->findById((int) $data['financial_entry_id']);
            if ($fe !== null) {
                if (empty($data['sponsor_id']) && !empty($fe['sponsor_id'])) {
                    $data['sponsor_id'] = (int) $fe['sponsor_id'];
                }
                if (empty($data['contract_id']) && !empty($fe['contract_id'])) {
                    $data['contract_id'] = (int) $fe['contract_id'];
                }
                if (empty($data['company_id']) && !empty($fe['company_id'])) {
                    $data['company_id'] = (int) $fe['company_id'];
                }
                if (empty($data['contact_id']) && !empty($fe['contact_id'])) {
                    $data['contact_id'] = (int) $fe['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($fe['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $fe['opportunity_id'];
                }
                if (empty($data['proposal_id']) && !empty($fe['proposal_id'])) {
                    $data['proposal_id'] = (int) $fe['proposal_id'];
                }
                if (empty($data['quota_id']) && !empty($fe['quota_id'])) {
                    $data['quota_id'] = (int) $fe['quota_id'];
                }
            }
        }

        if (!empty($data['sponsor_dossier_id'])) {
            $dossier = (new SponsorDossier())->findById((int) $data['sponsor_dossier_id']);
            if ($dossier !== null) {
                if (empty($data['sponsor_id']) && !empty($dossier['sponsor_id'])) {
                    $data['sponsor_id'] = (int) $dossier['sponsor_id'];
                }
                if (empty($data['contract_id']) && !empty($dossier['main_contract_id'])) {
                    $data['contract_id'] = (int) $dossier['main_contract_id'];
                }
                if (empty($data['company_id']) && !empty($dossier['company_id'])) {
                    $data['company_id'] = (int) $dossier['company_id'];
                }
                if (empty($data['contact_id']) && !empty($dossier['contact_id'])) {
                    $data['contact_id'] = (int) $dossier['contact_id'];
                }
                if (empty($data['opportunity_id']) && !empty($dossier['opportunity_id'])) {
                    $data['opportunity_id'] = (int) $dossier['opportunity_id'];
                }
                if (empty($data['proposal_id']) && !empty($dossier['proposal_id'])) {
                    $data['proposal_id'] = (int) $dossier['proposal_id'];
                }
                if (empty($data['quota_id']) && !empty($dossier['quota_id'])) {
                    $data['quota_id'] = (int) $dossier['quota_id'];
                }
            }
        }

        if (empty($data['document_date'])) {
            $data['document_date'] = date('Y-m-d');
        }

        foreach ([
            'opportunity_id' => new Opportunity(),
            'proposal_id' => new Proposal(),
            'sponsor_id' => new Sponsor(),
            'contract_id' => new Contract(),
            'financial_entry_id' => new FinancialEntry(),
            'sponsor_dossier_id' => new SponsorDossier(),
            'counterpart_id' => new Counterpart(),
            'quota_id' => new Quota(),
        ] as $field => $linkedModel) {
            if (!empty($data['incentive_project_id']) || empty($data[$field])) {
                continue;
            }
            $row = $linkedModel->findById((int) $data[$field]);
            if ($row !== null && !empty($row['incentive_project_id'])) {
                $data['incentive_project_id'] = (int) $row['incentive_project_id'];
            }
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
        if ($oppId > 0 && (new Opportunity())->findById($oppId) === null) {
            $errors['opportunity_id'] = 'Oportunidade não encontrada.';
        }

        $quotaId = (int) ($data['quota_id'] ?? 0);
        if ($quotaId > 0 && (new Quota())->findById($quotaId) === null) {
            $errors['quota_id'] = 'Cota não encontrada.';
        }

        $proposalId = (int) ($data['proposal_id'] ?? 0);
        if ($proposalId > 0) {
            $prop = (new Proposal())->findById($proposalId);
            if ($prop === null) {
                $errors['proposal_id'] = 'Proposta não encontrada.';
            } elseif ($companyId > 0 && (int) $prop['company_id'] !== $companyId) {
                $errors['proposal_id'] = 'A proposta não pertence à empresa selecionada.';
            }
        }

        $leadId = (int) ($data['lead_id'] ?? 0);
        if ($leadId > 0 && (new Lead())->findById($leadId) === null) {
            $errors['lead_id'] = 'Lead não encontrado.';
        }

        $sponsorId = (int) ($data['sponsor_id'] ?? 0);
        if ($sponsorId > 0 && (new Sponsor())->findById($sponsorId) === null) {
            $errors['sponsor_id'] = 'Patrocinador não encontrado.';
        }

        $counterpartId = (int) ($data['counterpart_id'] ?? 0);
        if ($counterpartId > 0 && (new Counterpart())->findById($counterpartId) === null) {
            $errors['counterpart_id'] = 'Contrapartida não encontrada.';
        }

        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId > 0 && (new Contract())->findById($contractId) === null) {
            $errors['contract_id'] = 'Contrato não encontrado.';
        }

        $financialEntryId = (int) ($data['financial_entry_id'] ?? 0);
        if ($financialEntryId > 0 && (new FinancialEntry())->findById($financialEntryId) === null) {
            $errors['financial_entry_id'] = 'Lançamento financeiro não encontrado.';
        }

        $dossierId = (int) ($data['sponsor_dossier_id'] ?? 0);
        if ($dossierId > 0 && (new SponsorDossier())->findById($dossierId) === null) {
            $errors['sponsor_dossier_id'] = 'Dossiê não encontrado.';
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

    /** @return array<int, array<string, mixed>> */
    private function proposalFilterOptions(): array
    {
        return (new Document())->filterProposalOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function leadFilterOptions(): array
    {
        return (new Document())->filterLeadOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function sponsorFilterOptions(): array
    {
        return (new Document())->filterSponsorOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function counterpartFilterOptions(): array
    {
        return (new Document())->filterCounterpartOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function contractFilterOptions(): array
    {
        return (new Document())->filterContractOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function financialFilterOptions(): array
    {
        return (new Document())->filterFinancialEntryOptions();
    }

    /** @return array<int, array<string, mixed>> */
    private function sponsorDossierFilterOptions(): array
    {
        if (!can('dossiers.view')) {
            return [];
        }

        $rows = (new SponsorDossier())->paginate(['show_archived' => 0], 1, 200);
        $out  = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['title'] ?? ''));
            if (!empty($row['dossier_number'])) {
                $label .= ' (' . $row['dossier_number'] . ')';
            }
            $out[] = ['id' => (int) $row['id'], 'label' => $label];
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private function linkDocumentToContract(array $data, int|string $documentId): void
    {
        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId <= 0) {
            return;
        }

        (new ActivityLog())->record('contract_document_linked', $_SESSION['user_id'] ?? null, 'contract', $contractId);

        if (!can('contracts.edit')) {
            return;
        }

        $patch = ['updated_by' => $_SESSION['user_id'] ?? null];
        if (!empty($data['use_as_draft'])) {
            $patch['draft_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_final'])) {
            $patch['final_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_signed'])) {
            $patch['signed_document_id'] = (int) $documentId;
        }

        if (count($patch) <= 1) {
            return;
        }

        (new Contract())->update($contractId, $patch);

        if (!empty($data['use_as_draft'])) {
            (new ActivityLog())->record('contract_draft_document_linked', $_SESSION['user_id'] ?? null, 'contract', $contractId);
        }
        if (!empty($data['use_as_final'])) {
            (new ActivityLog())->record('contract_final_document_linked', $_SESSION['user_id'] ?? null, 'contract', $contractId);
        }
        if (!empty($data['use_as_signed'])) {
            (new ActivityLog())->record('contract_signed_document_linked', $_SESSION['user_id'] ?? null, 'contract', $contractId);
        }
    }

    /** @param array<string, mixed> $data */
    private function linkDocumentToFinancial(array $data, int|string $documentId): void
    {
        $financialEntryId = (int) ($data['financial_entry_id'] ?? 0);
        if ($financialEntryId <= 0) {
            return;
        }

        (new ActivityLog())->record('financial_document_linked', $_SESSION['user_id'] ?? null, 'financial_entry', $financialEntryId);

        if (!can('financials.edit')) {
            return;
        }

        $patch = ['updated_by' => $_SESSION['user_id'] ?? null];
        if (!empty($data['use_as_proof'])) {
            $patch['proof_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_receipt'])) {
            $patch['receipt_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_fiscal'])) {
            $patch['fiscal_document_id']     = (int) $documentId;
            $patch['fiscal_document_status'] = 'anexado';
        }

        if (count($patch) <= 1) {
            return;
        }

        (new FinancialEntry())->update($financialEntryId, $patch);

        if (!empty($data['use_as_proof'])) {
            (new ActivityLog())->record('financial_proof_document_linked', $_SESSION['user_id'] ?? null, 'financial_entry', $financialEntryId);
        }
        if (!empty($data['use_as_receipt'])) {
            (new ActivityLog())->record('financial_receipt_document_linked', $_SESSION['user_id'] ?? null, 'financial_entry', $financialEntryId);
        }
        if (!empty($data['use_as_fiscal'])) {
            (new ActivityLog())->record('financial_fiscal_document_linked', $_SESSION['user_id'] ?? null, 'financial_entry', $financialEntryId);
        }
    }

    /** @param array<string, mixed> $data */
    private function linkDocumentToDossier(array $data, int|string $documentId): void
    {
        $dossierId = (int) ($data['sponsor_dossier_id'] ?? 0);
        if ($dossierId <= 0) {
            return;
        }

        (new ActivityLog())->record('sponsor_dossier_document_linked', $_SESSION['user_id'] ?? null, 'sponsor_dossier', $dossierId);

        if (!can('dossiers.edit')) {
            return;
        }

        $patch = ['updated_by' => $_SESSION['user_id'] ?? null];
        if (!empty($data['use_as_dossier_main'])) {
            $patch['main_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_dossier_final'])) {
            $patch['final_document_id'] = (int) $documentId;
        }
        if (!empty($data['use_as_dossier_delivery_receipt'])) {
            $patch['delivery_receipt_document_id'] = (int) $documentId;
        }

        if (count($patch) <= 1) {
            return;
        }

        (new SponsorDossier())->update($dossierId, $patch);
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $document
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $document = []): void
    {
        $model     = new Document();
        $companyId = (int) ($old['company_id'] ?? ($document['company_id'] ?? 0));
        $projectId = (int) ($old['incentive_project_id'] ?? ($document['incentive_project_id'] ?? 0));
        $companyContacts = $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [];

        $this->view($view, [
            'title'           => $title,
            'old'             => $old,
            'errors'          => $errors,
            'document'        => $document,
            'model'           => $model,
            'categories'      => $model->getCategories(),
            'statuses'        => $model->getStatuses(),
            'accessLevels'    => $model->getAccessLevels(),
            'projects'        => (new IncentiveProject())->options(true),
            'companies'       => (new Company())->activeOptions(),
            'opportunities'   => $this->linkOptions('opportunities', 'title'),
            'quotas'          => (new Quota())->activeOptions($projectId > 0 ? $projectId : null),
            'proposals'       => $this->proposalFilterOptions(),
            'leads'           => $this->leadFilterOptions(),
            'sponsors'        => $this->sponsorFilterOptions(),
            'counterparts'    => $this->counterpartFilterOptions(),
            'contracts'       => $this->contractFilterOptions(),
            'financials'      => $this->financialFilterOptions(),
            'sponsorDossiers' => $this->sponsorDossierFilterOptions(),
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
        foreach (['q', 'category', 'status', 'access_level', 'valid_from', 'valid_to'] as $k) {
            if (trim((string) ($filters[$k] ?? '')) !== '') {
                return true;
            }
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id', 'sponsor_id', 'counterpart_id', 'contract_id', 'financial_entry_id', 'responsible_user_id'] as $k) {
            if ((int) ($filters[$k] ?? 0) > 0) {
                return true;
            }
        }

        foreach (['expired', 'show_archived'] as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = is_numeric($id) ? (new Document())->findById((int) $id) : null;
        if ($row === null) {
            $this->abort(404, 'Documento não encontrado.');
        }

        return $row;
    }
}
