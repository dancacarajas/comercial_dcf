<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Counterpart;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\User;

/**
 * Módulo Contrapartidas dos Patrocinadores (Etapa 13).
 */
final class CounterpartController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('counterparts.view');

        $model   = new Counterpart();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('counterparts/index', [
            'title'         => 'Contrapartidas',
            'items'         => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'       => $filters,
            'categories'    => $model->getCategories(),
            'deliveryTypes' => $model->getDeliveryTypes(),
            'statuses'      => $model->getStatuses(),
            'priorities'    => $model->getPriorities(),
            'model'         => $model,
            'sponsors'      => $this->sponsorFilterOptions($filters),
            'companies'     => $this->companyFilterOptions($filters),
            'contacts'      => $this->linkOptions('contacts', 'name'),
            'opportunities' => $this->linkOptions('opportunities', 'title'),
            'proposals'     => $this->linkOptions('proposals', 'title'),
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
        AuthMiddleware::requirePermission('counterparts.create');
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'category'            => 'divulgacao_marca',
            'delivery_type'       => 'entrega_unica',
            'priority'            => 'media',
            'status'              => 'planejada',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForSponsor(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id      = (int) ($params['id'] ?? 0);
        $sponsor = $id > 0 ? (new Sponsor())->findById($id) : null;
        if ($sponsor === null) {
            $this->abort(404, 'Patrocinador não encontrado.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->applyFromSponsor($this->prefillFromQuery([
            'sponsor_id'          => $id,
            'category'            => 'divulgacao_marca',
            'delivery_type'       => 'entrega_unica',
            'priority'            => 'media',
            'status'              => 'planejada',
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), $sponsor), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'company_id' => $id, 'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
            'priority' => 'media', 'status' => 'planejada', 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'company_id' => (int) $contact['company_id'], 'contact_id' => $id,
            'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
            'priority' => 'media', 'status' => 'planejada', 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'company_id' => (int) $opp['company_id'],
            'contact_id' => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id' => $id,
            'quota_id' => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
            'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
            'priority' => 'media', 'status' => 'planejada', 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id   = (int) ($params['id'] ?? 0);
        $prop = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($prop === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'company_id' => (int) $prop['company_id'],
            'contact_id' => $prop['contact_id'] ? (int) $prop['contact_id'] : null,
            'opportunity_id' => $prop['opportunity_id'] ? (int) $prop['opportunity_id'] : null,
            'proposal_id' => $id,
            'quota_id' => $prop['quota_id'] ? (int) $prop['quota_id'] : null,
            'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
            'priority' => 'media', 'status' => 'planejada', 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('counterparts/create', 'Nova contrapartida', $this->prefillFromQuery([
            'quota_id' => $id, 'category' => 'divulgacao_marca', 'delivery_type' => 'entrega_unica',
            'priority' => 'media', 'status' => 'planejada', 'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ]), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('counterparts.create');
        csrf_verify();

        $model = new Counterpart();
        $data  = $this->collectInput($model);
        $data  = $this->applyFromSponsor($data);

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('counterparts/create', 'Nova contrapartida', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('counterpart_created', $_SESSION['user_id'] ?? null, 'counterpart', (int) $id);

        flash('success', 'Contrapartida registrada com sucesso.');
        $this->redirect('/counterparts/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.view');
        $counterpart = $this->findOr404($params['id'] ?? null);
        $model       = new Counterpart();
        $cid         = (int) $counterpart['id'];

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByCounterpart($cid, 10);
            $documentSummary = $documentModel->summaryByCounterpart($cid);
        }

        $this->view('counterparts/show', [
            'title'           => $counterpart['title'] ?? 'Contrapartida',
            'counterpart'     => $counterpart,
            'model'           => $model,
            'categories'      => $model->getCategories(),
            'deliveryTypes'   => $model->getDeliveryTypes(),
            'statuses'        => $model->getStatuses(),
            'priorities'      => $model->getPriorities(),
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.edit');
        $counterpart = $this->findOr404($params['id'] ?? null);

        if (!empty($counterpart['archived_at'])) {
            flash('error', 'Esta contrapartida está arquivada. Restaure-a antes de editar.');
            $this->redirect('/counterparts/' . (int) $counterpart['id']);
            return;
        }

        $this->renderForm('counterparts/edit', 'Editar contrapartida', $counterpart, [], $counterpart);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.edit');
        csrf_verify();

        $counterpart = $this->findOr404($params['id'] ?? null);
        $id          = (int) $counterpart['id'];

        if (!empty($counterpart['archived_at'])) {
            flash('error', 'Esta contrapartida está arquivada. Restaure-a antes de editar.');
            $this->redirect('/counterparts/' . $id);
            return;
        }

        $model  = new Counterpart();
        $data   = $this->collectInput($model);
        $data   = $this->applyFromSponsor($data);
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('counterparts/edit', 'Editar contrapartida', $data, $errors, array_merge($counterpart, $data));
            return;
        }

        $statusChanged  = (string) $counterpart['status'] !== (string) ($data['status'] ?? '');
        $deliveredChanged = (float) ($counterpart['delivered_quantity'] ?? 0) !== (float) ($data['delivered_quantity'] ?? 0);
        $evidenceChanged = (int) ($counterpart['evidence_document_id'] ?? 0) !== (int) ($data['evidence_document_id'] ?? 0);

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('counterpart_updated', $_SESSION['user_id'] ?? null, 'counterpart', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('counterpart_status_changed', $_SESSION['user_id'] ?? null, 'counterpart', $id);
        }
        if ($deliveredChanged) {
            (new ActivityLog())->record('counterpart_delivery_progress_updated', $_SESSION['user_id'] ?? null, 'counterpart', $id);
        }
        if ($evidenceChanged) {
            (new ActivityLog())->record('counterpart_evidence_linked', $_SESSION['user_id'] ?? null, 'counterpart', $id);
        }

        flash('success', 'Contrapartida atualizada com sucesso.');
        $this->redirect('/counterparts/' . $id);
    }

    public function deliver(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.deliver');
        csrf_verify();

        $counterpart = $this->findOr404($params['id'] ?? null);
        $id          = (int) $counterpart['id'];
        $userId      = (int) ($_SESSION['user_id'] ?? 0);
        $model       = new Counterpart();

        $payload = [
            'delivered_quantity'   => input('delivered_quantity') !== null && input('delivered_quantity') !== ''
                ? $model->normalizeDecimal((string) input('delivered_quantity')) : null,
            'evidence_description' => trim((string) input('evidence_description', '')) ?: null,
            'evidence_url'         => trim((string) input('evidence_url', '')) ?: null,
            'evidence_document_id' => input('evidence_document_id') !== null && input('evidence_document_id') !== ''
                ? (int) input('evidence_document_id') : null,
            'notes'                => trim((string) input('notes', '')) ?: null,
        ];

        $model->deliver($id, $payload, $userId);

        $updated = $model->findById($id);
        (new ActivityLog())->record('counterpart_delivered', $userId ?: null, 'counterpart', $id);
        if (($updated['status'] ?? '') === 'entrega_parcial') {
            (new ActivityLog())->record('counterpart_partial_delivered', $userId ?: null, 'counterpart', $id);
        }

        flash('success', 'Entrega registrada com sucesso.');
        $this->redirect('/counterparts/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.status');
        csrf_verify();

        $counterpart = $this->findOr404($params['id'] ?? null);
        $id          = (int) $counterpart['id'];
        $model       = new Counterpart();
        $userId      = $_SESSION['user_id'] ?? null;

        $newStatus = clean((string) input('status', (string) ($counterpart['status'] ?? '')));
        $note      = trim((string) input('notes', ''));

        if (!array_key_exists($newStatus, $model->getStatuses())) {
            flash('error', 'Status inválido.');
            $this->redirect('/counterparts/' . $id);
            return;
        }

        $patch = [
            'status'     => $newStatus,
            'updated_by' => $userId,
        ];

        if ($note !== '') {
            $prev = trim((string) ($counterpart['notes'] ?? ''));
            $patch['notes'] = $prev === '' ? $note : ($prev . "\n[" . date('Y-m-d H:i') . "] " . $note);
        }

        if ($newStatus === 'entregue' && empty($counterpart['delivered_at'])) {
            $patch['delivered_at'] = date('Y-m-d H:i:s');
            $patch['delivered_by'] = $userId;
        }

        if ($newStatus === 'aprovada') {
            if (empty($counterpart['approved_at'])) {
                $patch['approved_at'] = date('Y-m-d H:i:s');
            }
            $patch['approved_by'] = $userId;
        }

        $model->updateStatus($id, $patch);
        (new ActivityLog())->record('counterpart_status_changed', $userId, 'counterpart', $id);

        flash('success', 'Status atualizado.');
        $this->redirect('/counterparts/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.archive');
        csrf_verify();

        $counterpart = $this->findOr404($params['id'] ?? null);
        $id          = (int) $counterpart['id'];

        (new Counterpart())->archive($id);
        (new ActivityLog())->record('counterpart_archived', $_SESSION['user_id'] ?? null, 'counterpart', $id);

        flash('success', 'Contrapartida arquivada.');
        $this->redirect('/counterparts/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('counterparts.archive');
        csrf_verify();

        $counterpart = $this->findOr404($params['id'] ?? null);
        $id          = (int) $counterpart['id'];

        (new Counterpart())->restore($id);
        (new ActivityLog())->record('counterpart_restored', $_SESSION['user_id'] ?? null, 'counterpart', $id);

        flash('success', 'Contrapartida restaurada.');
        $this->redirect('/counterparts/' . $id);
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
            'contact_id'          => (int) input('contact_id', 0),
            'opportunity_id'      => (int) input('opportunity_id', 0),
            'proposal_id'         => (int) input('proposal_id', 0),
            'quota_id'            => (int) input('quota_id', 0),
            'category'            => (string) input('category', ''),
            'delivery_type'       => (string) input('delivery_type', ''),
            'priority'            => (string) input('priority', ''),
            'status'              => (string) input('status', ''),
            'responsible_user_id' => (int) input('responsible_user_id', 0),
            'due_from'            => (string) input('due_from', ''),
            'due_to'              => (string) input('due_to', ''),
            'overdue'             => input('overdue') !== null ? 1 : 0,
            'delivered'           => input('delivered') !== null ? 1 : 0,
            'pending'             => input('pending') !== null ? 1 : 0,
            'show_archived'       => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Counterpart $model): array
    {
        return [
            'sponsor_id'           => (int) input('sponsor_id', 0),
            'company_id'           => input('company_id') !== null && input('company_id') !== '' ? (int) input('company_id') : null,
            'contact_id'           => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'       => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'proposal_id'          => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'quota_id'             => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'evidence_document_id' => input('evidence_document_id') !== null && input('evidence_document_id') !== '' ? (int) input('evidence_document_id') : null,
            'title'                => clean((string) input('title', '')),
            'category'             => clean((string) input('category', 'divulgacao_marca')),
            'delivery_type'        => clean((string) input('delivery_type', 'entrega_unica')),
            'description'          => trim((string) input('description', '')) ?: null,
            'promised_quantity'    => $model->normalizeDecimal((string) input('promised_quantity', '')),
            'delivered_quantity'   => $model->normalizeDecimal((string) input('delivered_quantity', '')),
            'unit'                 => clean((string) input('unit', '')) ?: null,
            'priority'             => clean((string) input('priority', 'media')),
            'status'               => clean((string) input('status', 'planejada')),
            'due_date'             => $model->normalizeDate((string) input('due_date', '')),
            'started_at'           => $model->normalizeDateTime((string) input('started_at', '')),
            'delivered_at'         => $model->normalizeDateTime((string) input('delivered_at', '')),
            'approved_at'          => $model->normalizeDateTime((string) input('approved_at', '')),
            'evidence_description' => trim((string) input('evidence_description', '')) ?: null,
            'evidence_url'         => trim((string) input('evidence_url', '')) ?: null,
            'responsible_user_id'  => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
            'notes'                => trim((string) input('notes', '')) ?: null,
            'internal_notes'       => trim((string) input('internal_notes', '')) ?: null,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach (['sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'evidence_document_id'] as $k) {
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

        $docId = (int) ($data['evidence_document_id'] ?? 0);
        if ($docId > 0 && (new Document())->findById($docId) === null) {
            $errors['evidence_document_id'] = 'Documento de evidência não encontrado.';
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
        $model = new Sponsor();
        $items = $model->paginate(['show_archived' => 0], 1, 200);

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
        $row = (new Counterpart())->findById((int) $id);
        if ($row === null) {
            $this->abort(404, 'Contrapartida não encontrada.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $counterpart
     */
    private function renderForm(string $view, string $title, array $old, array $errors, ?array $counterpart = null): void
    {
        $model     = new Counterpart();
        $sponsorId = (int) ($old['sponsor_id'] ?? 0);
        $companyId = (int) ($old['company_id'] ?? 0);

        $sponsors = (new Sponsor())->paginate(['show_archived' => 0], 1, 300);
        $documents = [];
        if (can('documents.view')) {
            $docFilters = array_filter(['company_id' => $companyId ?: null, 'sponsor_id' => $sponsorId ?: null]);
            $documents = (new Document())->paginate($docFilters, 1, 100);
        }

        $this->view($view, [
            'title'         => $title,
            'counterpart'   => $counterpart ?? $old,
            'old'           => $old,
            'errors'        => $errors,
            'categories'    => $model->getCategories(),
            'deliveryTypes' => $model->getDeliveryTypes(),
            'statuses'      => $model->getStatuses(),
            'priorities'    => $model->getPriorities(),
            'sponsors'      => $sponsors,
            'companies'     => (new Company())->activeOptions(),
            'contacts'      => $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [],
            'opportunities' => $this->linkOptions('opportunities', 'title'),
            'proposals'     => $this->linkOptions('proposals', 'title'),
            'quotas'        => (new Quota())->activeOptions(),
            'users'         => (new User())->activeList(),
            'documents'     => array_map(static fn ($d) => [
                'id'    => (int) $d['id'],
                'label' => (string) ($d['title'] ?? '') . ' (v' . (int) ($d['version_number'] ?? 1) . ')',
            ], $documents),
        ]);
    }
}
