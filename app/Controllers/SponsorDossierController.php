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
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\SponsorDossier;
use App\Models\SponsorDossierItem;
use App\Models\User;

/**
 * Módulo Prestação de Contas Comercial / Dossiê do Patrocinador (Etapa 16).
 */
final class SponsorDossierController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('dossiers.view');

        $model   = new SponsorDossier();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('sponsor_dossiers/index', [
            'title'           => 'Dossiês',
            'items'           => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'         => $filters,
            'dossierTypes'    => $model->getDossierTypes(),
            'statuses'        => $model->getStatuses(),
            'deliveryStatuses'=> $model->getDeliveryStatuses(),
            'model'           => $model,
            'sponsors'        => $this->sponsorFilterOptions(),
            'companies'       => (new Company())->activeOptions(),
            'contracts'       => $this->contractFilterOptions(),
            'users'           => (new User())->activeList(),
            'page'            => $page,
            'pages'           => $pages,
            'total'           => $total,
            'perPage'         => self::PER_PAGE,
            'hasFilters'      => $this->hasActiveFilters($filters),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery($this->defaultFormData()), []);
    }

    public function createForSponsor(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id      = (int) ($params['id'] ?? 0);
        $sponsor = $id > 0 ? (new Sponsor())->findById($id) : null;
        if ($sponsor === null) {
            $this->abort(404, 'Patrocinador não encontrado.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->applyFromSponsor($this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'sponsor_id' => $id,
        ])), $sponsor), []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'company_id' => $id,
        ])), []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id      = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'company_id' => (int) $contact['company_id'],
            'contact_id' => $id,
        ])), []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id  = (int) ($params['id'] ?? 0);
        $opp = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opp === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'company_id'     => (int) $opp['company_id'],
            'contact_id'     => $opp['contact_id'] ? (int) $opp['contact_id'] : null,
            'opportunity_id' => $id,
            'quota_id'       => $opp['quota_id'] ? (int) $opp['quota_id'] : null,
        ])), []);
    }

    public function createForProposal(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id   = (int) ($params['id'] ?? 0);
        $prop = $id > 0 ? (new Proposal())->findById($id) : null;
        if ($prop === null) {
            $this->abort(404, 'Proposta não encontrada.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'company_id'     => (int) $prop['company_id'],
            'contact_id'     => $prop['contact_id'] ? (int) $prop['contact_id'] : null,
            'opportunity_id' => $prop['opportunity_id'] ? (int) $prop['opportunity_id'] : null,
            'proposal_id'    => $id,
            'quota_id'       => $prop['quota_id'] ? (int) $prop['quota_id'] : null,
        ])), []);
    }

    public function createForQuota(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id    = (int) ($params['id'] ?? 0);
        $quota = $id > 0 ? (new Quota())->findById($id) : null;
        if ($quota === null) {
            $this->abort(404, 'Cota não encontrada.');
        }
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'quota_id' => $id,
        ])), []);
    }

    public function createForContract(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        $id       = (int) ($params['id'] ?? 0);
        $contract = $id > 0 ? (new Contract())->findById($id) : null;
        if ($contract === null) {
            $this->abort(404, 'Contrato não encontrado.');
        }
        $data = $this->applyFromContract($this->applyFromSponsor($this->prefillFromQuery(array_merge($this->defaultFormData(), [
            'main_contract_id' => $id,
        ]))), $contract);
        $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $data, []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('dossiers.create');
        csrf_verify();

        $model = new SponsorDossier();
        $data  = $this->collectInput($model);
        $data  = $this->applyFromContract($this->applyFromSponsor($data));

        if (trim((string) ($data['title'] ?? '')) === '') {
            $sponsorId = (int) ($data['sponsor_id'] ?? 0);
            $sponsor   = $sponsorId > 0 ? (new Sponsor())->findById($sponsorId) : null;
            $data['title'] = 'Dossiê — ' . (string) ($sponsor['sponsor_display_name'] ?? 'Patrocinador');
        }

        $errors = $model->validate($data, 'create');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('sponsor_dossiers/create', 'Novo dossiê', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = (int) $model->create($data);

        (new ActivityLog())->record('sponsor_dossier_created', $_SESSION['user_id'] ?? null, 'sponsor_dossier', $id);

        flash('success', 'Dossiê registrado com sucesso.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.view');
        $dossier    = $this->findOr404($params['id'] ?? null);
        $model      = new SponsorDossier();
        $itemModel  = new SponsorDossierItem();
        $did        = (int) $dossier['id'];
        $sid        = (int) ($dossier['sponsor_id'] ?? 0);

        $items = $itemModel->findByDossier($did);

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->paginate(['sponsor_id' => $sid], 1, 50);
            $documentSummary = $documentModel->summaryBySponsor($sid);
        }

        $this->view('sponsor_dossiers/show', [
            'title'            => $dossier['title'] ?? 'Dossiê',
            'dossier'          => $dossier,
            'model'            => $model,
            'itemModel'        => $itemModel,
            'items'            => $items,
            'dossierTypes'     => $model->getDossierTypes(),
            'statuses'         => $model->getStatuses(),
            'deliveryStatuses' => $model->getDeliveryStatuses(),
            'itemTypes'        => $itemModel->getItemTypes(),
            'itemStatuses'     => $itemModel->getStatuses(),
            'evidenceStatuses' => $itemModel->getEvidenceStatuses(),
            'documents'        => $documents,
            'documentSummary'  => $documentSummary,
            'documentModel'    => $documentModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        $dossier = $this->findOr404($params['id'] ?? null);

        if (!empty($dossier['archived_at'])) {
            flash('error', 'Este dossiê está arquivado. Restaure-o antes de editar.');
            $this->redirect('/sponsor-dossiers/' . (int) $dossier['id']);
            return;
        }

        $this->renderForm('sponsor_dossiers/edit', 'Editar dossiê', $dossier, [], $dossier);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];

        if (!empty($dossier['archived_at'])) {
            flash('error', 'Este dossiê está arquivado. Restaure-o antes de editar.');
            $this->redirect('/sponsor-dossiers/' . $id);
            return;
        }

        $model  = new SponsorDossier();
        $data   = $this->collectInput($model);
        $data   = $this->applyFromContract($this->applyFromSponsor($data));
        $errors = $model->validate($data, 'update');
        $this->validateLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('sponsor_dossiers/edit', 'Editar dossiê', $data, $errors, array_merge($dossier, $data));
            return;
        }

        $statusChanged         = (string) $dossier['status'] !== (string) ($data['status'] ?? '');
        $deliveryStatusChanged = (string) ($dossier['delivery_status'] ?? '') !== (string) ($data['delivery_status'] ?? '');
        $mainDocChanged        = (int) ($dossier['main_document_id'] ?? 0) !== (int) ($data['main_document_id'] ?? 0);
        $finalDocChanged       = (int) ($dossier['final_document_id'] ?? 0) !== (int) ($data['final_document_id'] ?? 0);
        $deliveryDocChanged    = (int) ($dossier['delivery_receipt_document_id'] ?? 0) !== (int) ($data['delivery_receipt_document_id'] ?? 0);

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        $userId = $_SESSION['user_id'] ?? null;
        (new ActivityLog())->record('sponsor_dossier_updated', $userId, 'sponsor_dossier', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('sponsor_dossier_status_changed', $userId, 'sponsor_dossier', $id);
        }
        if ($deliveryStatusChanged) {
            (new ActivityLog())->record('sponsor_dossier_delivery_status_changed', $userId, 'sponsor_dossier', $id);
        }
        if ($mainDocChanged || $finalDocChanged || $deliveryDocChanged) {
            (new ActivityLog())->record('sponsor_dossier_document_linked', $userId, 'sponsor_dossier', $id);
        }

        flash('success', 'Dossiê atualizado com sucesso.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function generate(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.generate');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];
        $userId  = (int) ($_SESSION['user_id'] ?? 0);

        (new SponsorDossier())->generate($id, $userId);
        (new ActivityLog())->record('sponsor_dossier_generated', $userId ?: null, 'sponsor_dossier', $id);

        flash('success', 'Consolidação gerada/atualizada com sucesso.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function approve(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.approve');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];
        $userId  = (int) ($_SESSION['user_id'] ?? 0);

        (new SponsorDossier())->approve($id, [
            'approval_notes' => trim((string) input('approval_notes', '')),
        ], $userId);

        (new ActivityLog())->record('sponsor_dossier_approved', $userId ?: null, 'sponsor_dossier', $id);

        flash('success', 'Dossiê aprovado internamente.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function deliver(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.deliver');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $model   = new SponsorDossier();

        $receiptDocId = input('delivery_receipt_document_id') !== null && input('delivery_receipt_document_id') !== ''
            ? (int) input('delivery_receipt_document_id') : null;

        if ($receiptDocId > 0 && (new Document())->findById($receiptDocId) === null) {
            flash('error', 'Comprovante de entrega não encontrado.');
            $this->redirect('/sponsor-dossiers/' . $id);
            return;
        }

        $deliveryStatus = clean((string) input('delivery_status', ''));
        if ($deliveryStatus !== '' && !array_key_exists($deliveryStatus, $model->getDeliveryStatuses())) {
            flash('error', 'Status de entrega inválido.');
            $this->redirect('/sponsor-dossiers/' . $id);
            return;
        }

        $model->deliver($id, [
            'delivered_at'                   => input('delivered_at') !== null ? (string) input('delivered_at') : '',
            'delivery_status'                => $deliveryStatus,
            'delivery_receipt_document_id'   => $receiptDocId,
            'delivery_notes'                 => trim((string) input('delivery_notes', '')),
        ], $userId);

        (new ActivityLog())->record('sponsor_dossier_delivered', $userId ?: null, 'sponsor_dossier', $id);

        flash('success', 'Entrega registrada com sucesso.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.status');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];
        $model   = new SponsorDossier();
        $userId  = (int) ($_SESSION['user_id'] ?? 0);
        $patch   = [];

        $statusChanged         = false;
        $deliveryStatusChanged = false;

        if (input('status') !== null && input('status') !== '') {
            $newStatus = clean((string) input('status'));
            if (!array_key_exists($newStatus, $model->getStatuses())) {
                flash('error', 'Status inválido.');
                $this->redirect('/sponsor-dossiers/' . $id);
                return;
            }
            if ((string) ($dossier['status'] ?? '') !== $newStatus) {
                $statusChanged = true;
            }
            $patch['status'] = $newStatus;
        }

        if (input('delivery_status') !== null && input('delivery_status') !== '') {
            $newDelivery = clean((string) input('delivery_status'));
            if (!array_key_exists($newDelivery, $model->getDeliveryStatuses())) {
                flash('error', 'Status de entrega inválido.');
                $this->redirect('/sponsor-dossiers/' . $id);
                return;
            }
            if ((string) ($dossier['delivery_status'] ?? '') !== $newDelivery) {
                $deliveryStatusChanged = true;
            }
            $patch['delivery_status'] = $newDelivery;
        }

        $note = trim((string) input('notes', ''));
        if ($note !== '') {
            $prev = trim((string) ($dossier['notes'] ?? ''));
            $patch['notes'] = $prev === '' ? $note : ($prev . "\n[" . date('Y-m-d H:i') . "] " . $note);
        }

        $model->updateStatus($id, $patch, $userId);

        if ($statusChanged) {
            (new ActivityLog())->record('sponsor_dossier_status_changed', $userId ?: null, 'sponsor_dossier', $id);
        }
        if ($deliveryStatusChanged) {
            (new ActivityLog())->record('sponsor_dossier_delivery_status_changed', $userId ?: null, 'sponsor_dossier', $id);
        }

        flash('success', 'Status atualizado.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.archive');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];

        (new SponsorDossier())->archive($id);
        (new ActivityLog())->record('sponsor_dossier_archived', $_SESSION['user_id'] ?? null, 'sponsor_dossier', $id);

        flash('success', 'Dossiê arquivado.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.archive');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $id      = (int) $dossier['id'];

        (new SponsorDossier())->restore($id);
        (new ActivityLog())->record('sponsor_dossier_restored', $_SESSION['user_id'] ?? null, 'sponsor_dossier', $id);

        flash('success', 'Dossiê restaurado.');
        $this->redirect('/sponsor-dossiers/' . $id);
    }

    public function print(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.view');
        $dossier   = $this->findOr404($params['id'] ?? null);
        $model     = new SponsorDossier();
        $itemModel = new SponsorDossierItem();
        $did       = (int) $dossier['id'];

        (new ActivityLog())->record('sponsor_dossier_print_viewed', $_SESSION['user_id'] ?? null, 'sponsor_dossier', $did);

        $this->view('sponsor_dossiers/print', [
            'title'            => ($dossier['title'] ?? 'Dossiê') . ' — Impressão',
            'dossier'          => $dossier,
            'model'            => $model,
            'items'            => $itemModel->findByDossier($did, true),
            'dossierTypes'     => $model->getDossierTypes(),
            'statuses'         => $model->getStatuses(),
            'deliveryStatuses' => $model->getDeliveryStatuses(),
            'itemTypes'        => $itemModel->getItemTypes(),
            'itemStatuses'     => $itemModel->getStatuses(),
            'evidenceStatuses' => $itemModel->getEvidenceStatuses(),
        ]);
    }

    public function storeItem(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $did     = (int) $dossier['id'];

        if (!empty($dossier['archived_at'])) {
            flash('error', 'Este dossiê está arquivado. Restaure-o antes de adicionar itens.');
            $this->redirect('/sponsor-dossiers/' . $did);
            return;
        }

        $itemModel = new SponsorDossierItem();
        $data      = $this->collectItemInput($did, (int) ($dossier['sponsor_id'] ?? 0));
        $errors    = $itemModel->validate($data, 'create');
        $this->validateItemLinks($data, $errors);

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/sponsor-dossiers/' . $did);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $itemId = $itemModel->create($data);

        (new ActivityLog())->record('sponsor_dossier_item_created', $_SESSION['user_id'] ?? null, 'sponsor_dossier_item', $itemId);

        flash('success', 'Item adicionado ao dossiê.');
        $this->redirect('/sponsor-dossiers/' . $did);
    }

    public function updateItem(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $did     = (int) $dossier['id'];
        $item    = $this->findItemOr404($params['itemId'] ?? null, $did);

        if (!empty($dossier['archived_at'])) {
            flash('error', 'Este dossiê está arquivado. Restaure-o antes de editar itens.');
            $this->redirect('/sponsor-dossiers/' . $did);
            return;
        }

        $itemModel = new SponsorDossierItem();
        $itemId    = (int) $item['id'];
        $data      = $this->collectItemInput($did, (int) ($dossier['sponsor_id'] ?? 0));
        $errors    = $itemModel->validate($data, 'update');
        $this->validateItemLinks($data, $errors);

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/sponsor-dossiers/' . $did);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $itemModel->update($itemId, $data);

        (new ActivityLog())->record('sponsor_dossier_item_updated', $_SESSION['user_id'] ?? null, 'sponsor_dossier_item', $itemId);

        flash('success', 'Item atualizado.');
        $this->redirect('/sponsor-dossiers/' . $did);
    }

    public function archiveItem(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $did     = (int) $dossier['id'];
        $item    = $this->findItemOr404($params['itemId'] ?? null, $did);
        $itemId  = (int) $item['id'];

        (new SponsorDossierItem())->archive($itemId);
        (new ActivityLog())->record('sponsor_dossier_item_archived', $_SESSION['user_id'] ?? null, 'sponsor_dossier_item', $itemId);

        flash('success', 'Item arquivado.');
        $this->redirect('/sponsor-dossiers/' . $did);
    }

    public function restoreItem(array $params): void
    {
        AuthMiddleware::requirePermission('dossiers.edit');
        csrf_verify();

        $dossier = $this->findOr404($params['id'] ?? null);
        $did     = (int) $dossier['id'];
        $item    = $this->findItemOr404($params['itemId'] ?? null, $did);
        $itemId  = (int) $item['id'];

        (new SponsorDossierItem())->restore($itemId);
        (new ActivityLog())->record('sponsor_dossier_item_restored', $_SESSION['user_id'] ?? null, 'sponsor_dossier_item', $itemId);

        flash('success', 'Item restaurado.');
        $this->redirect('/sponsor-dossiers/' . $did);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function defaultFormData(): array
    {
        return [
            'dossier_type'        => 'prestacao_comercial',
            'status'              => 'rascunho',
            'delivery_status'     => 'nao_entregue',
            'include_contracts'   => 1,
            'include_counterparts'=> 1,
            'include_financials'  => 1,
            'include_documents'   => 1,
            'include_evidence'    => 1,
            'include_clipping'    => 1,
            'include_media'       => 1,
            'responsible_user_id' => $_SESSION['user_id'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        $contractFilter = (int) input('main_contract_id', 0);
        if ($contractFilter <= 0) {
            $contractFilter = (int) input('contract_id', 0);
        }

        return [
            'q'                    => (string) input('q', ''),
            'sponsor_id'           => (int) input('sponsor_id', 0),
            'company_id'           => (int) input('company_id', 0),
            'contact_id'           => (int) input('contact_id', 0),
            'opportunity_id'       => (int) input('opportunity_id', 0),
            'proposal_id'          => (int) input('proposal_id', 0),
            'quota_id'             => (int) input('quota_id', 0),
            'main_contract_id'     => $contractFilter,
            'dossier_type'         => (string) input('dossier_type', ''),
            'status'               => (string) input('status', ''),
            'delivery_status'      => (string) input('delivery_status', ''),
            'responsible_user_id'  => (int) input('responsible_user_id', 0),
            'period_from'          => (string) input('period_from', ''),
            'period_to'            => (string) input('period_to', ''),
            'approved'             => input('approved') !== null ? 1 : 0,
            'delivered'            => input('delivered') !== null ? 1 : 0,
            'pending'              => input('pending') !== null ? 1 : 0,
            'with_balance'         => input('with_balance') !== null ? 1 : 0,
            'pending_counterparts' => input('pending_counterparts') !== null ? 1 : 0,
            'overdue_counterparts' => input('overdue_counterparts') !== null ? 1 : 0,
            'show_archived'        => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(SponsorDossier $model): array
    {
        return [
            'sponsor_id'                     => (int) input('sponsor_id', 0),
            'company_id'                     => input('company_id') !== null && input('company_id') !== '' ? (int) input('company_id') : null,
            'contact_id'                     => input('contact_id') !== null && input('contact_id') !== '' ? (int) input('contact_id') : null,
            'opportunity_id'                 => input('opportunity_id') !== null && input('opportunity_id') !== '' ? (int) input('opportunity_id') : null,
            'proposal_id'                    => input('proposal_id') !== null && input('proposal_id') !== '' ? (int) input('proposal_id') : null,
            'quota_id'                       => input('quota_id') !== null && input('quota_id') !== '' ? (int) input('quota_id') : null,
            'main_contract_id'               => input('main_contract_id') !== null && input('main_contract_id') !== '' ? (int) input('main_contract_id') : null,
            'main_document_id'               => input('main_document_id') !== null && input('main_document_id') !== '' ? (int) input('main_document_id') : null,
            'final_document_id'              => input('final_document_id') !== null && input('final_document_id') !== '' ? (int) input('final_document_id') : null,
            'delivery_receipt_document_id'   => input('delivery_receipt_document_id') !== null && input('delivery_receipt_document_id') !== '' ? (int) input('delivery_receipt_document_id') : null,
            'dossier_number'                 => clean((string) input('dossier_number', '')) ?: null,
            'title'                          => clean((string) input('title', '')),
            'dossier_type'                   => clean((string) input('dossier_type', 'prestacao_comercial')),
            'status'                         => clean((string) input('status', 'rascunho')),
            'delivery_status'                => clean((string) input('delivery_status', 'nao_entregue')),
            'period_start'                   => $model->normalizeDate((string) input('period_start', '')),
            'period_end'                     => $model->normalizeDate((string) input('period_end', '')),
            'include_contracts'              => input('include_contracts') !== null ? 1 : 0,
            'include_counterparts'           => input('include_counterparts') !== null ? 1 : 0,
            'include_financials'             => input('include_financials') !== null ? 1 : 0,
            'include_documents'              => input('include_documents') !== null ? 1 : 0,
            'include_evidence'               => input('include_evidence') !== null ? 1 : 0,
            'include_clipping'               => input('include_clipping') !== null ? 1 : 0,
            'include_media'                  => input('include_media') !== null ? 1 : 0,
            'executive_summary'              => trim((string) input('executive_summary', '')) ?: null,
            'commercial_summary'             => trim((string) input('commercial_summary', '')) ?: null,
            'counterparts_summary'           => trim((string) input('counterparts_summary', '')) ?: null,
            'financial_summary'              => trim((string) input('financial_summary', '')) ?: null,
            'documents_summary'              => trim((string) input('documents_summary', '')) ?: null,
            'pending_notes'                  => trim((string) input('pending_notes', '')) ?: null,
            'approval_notes'                 => trim((string) input('approval_notes', '')) ?: null,
            'delivery_notes'                 => trim((string) input('delivery_notes', '')) ?: null,
            'notes'                          => trim((string) input('notes', '')) ?: null,
            'internal_notes'                 => trim((string) input('internal_notes', '')) ?: null,
            'responsible_user_id'            => input('responsible_user_id') !== null && input('responsible_user_id') !== '' ? (int) input('responsible_user_id') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function collectItemInput(int $dossierId, int $sponsorId): array
    {
        $itemModel = new SponsorDossierItem();

        return [
            'dossier_id'         => $dossierId,
            'sponsor_id'         => $sponsorId,
            'contract_id'        => input('contract_id') !== null && input('contract_id') !== '' ? (int) input('contract_id') : null,
            'counterpart_id'     => input('counterpart_id') !== null && input('counterpart_id') !== '' ? (int) input('counterpart_id') : null,
            'financial_entry_id' => input('financial_entry_id') !== null && input('financial_entry_id') !== '' ? (int) input('financial_entry_id') : null,
            'document_id'        => input('document_id') !== null && input('document_id') !== '' ? (int) input('document_id') : null,
            'item_type'          => clean((string) input('item_type', 'manual')),
            'source_module'      => clean((string) input('source_module', 'manual')) ?: 'manual',
            'title'              => clean((string) input('title', '')),
            'description'        => trim((string) input('description', '')) ?: null,
            'status'             => clean((string) input('status', 'ativo')),
            'evidence_status'    => clean((string) input('evidence_status', 'nao_aplicavel')),
            'amount'             => $itemModel->normalizeMoney((string) input('amount', '')),
            'date_ref'           => $itemModel->normalizeDate((string) input('date_ref', '')),
            'sort_order'         => input('sort_order') !== null && input('sort_order') !== '' ? (int) input('sort_order') : 0,
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prefillFromQuery(array $data): array
    {
        foreach ([
            'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
            'main_contract_id', 'main_document_id', 'final_document_id', 'delivery_receipt_document_id',
        ] as $k) {
            $q = input($k);
            if ($q !== null && $q !== '') {
                $data[$k] = (int) $q;
            }
        }

        $contractId = input('contract_id');
        if ($contractId !== null && $contractId !== '') {
            $data['main_contract_id'] = (int) $contractId;
        }

        $mainContractId = (int) ($data['main_contract_id'] ?? 0);
        if ($mainContractId > 0) {
            $contract = (new Contract())->findById($mainContractId);
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

        if (empty($data['main_contract_id'])) {
            $contracts = (new Contract())->findBySponsor($sponsorId, 1);
            if ($contracts !== []) {
                $data['main_contract_id'] = (int) $contracts[0]['id'];
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
        $contractId = (int) ($data['main_contract_id'] ?? 0);
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

        return $data;
    }

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function validateLinks(array $data, array &$errors): void
    {
        $sponsorId = (int) ($data['sponsor_id'] ?? 0);
        if ($sponsorId > 0 && (new Sponsor())->findById($sponsorId) === null) {
            $errors['sponsor_id'] = 'Patrocinador não encontrado.';
        }

        $contractId = (int) ($data['main_contract_id'] ?? 0);
        if ($contractId > 0 && (new Contract())->findById($contractId) === null) {
            $errors['main_contract_id'] = 'Contrato não encontrado.';
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId > 0 && (new Company())->findById($companyId) === null) {
            $errors['company_id'] = 'Empresa não encontrada.';
        }

        $docModel = new Document();
        foreach ([
            'main_document_id'             => 'Documento principal',
            'final_document_id'            => 'Documento final',
            'delivery_receipt_document_id' => 'Comprovante de entrega',
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

    /** @param array<string, mixed> $data @param array<string, string> $errors */
    private function validateItemLinks(array $data, array &$errors): void
    {
        $contractId = (int) ($data['contract_id'] ?? 0);
        if ($contractId > 0 && (new Contract())->findById($contractId) === null) {
            $errors['contract_id'] = 'Contrato não encontrado.';
        }

        $counterpartId = (int) ($data['counterpart_id'] ?? 0);
        if ($counterpartId > 0 && (new Counterpart())->findById($counterpartId) === null) {
            $errors['counterpart_id'] = 'Contrapartida não encontrada.';
        }

        $financialId = (int) ($data['financial_entry_id'] ?? 0);
        if ($financialId > 0 && (new FinancialEntry())->findById($financialId) === null) {
            $errors['financial_entry_id'] = 'Lançamento financeiro não encontrado.';
        }

        $docId = (int) ($data['document_id'] ?? 0);
        if ($docId > 0 && (new Document())->findById($docId) === null) {
            $errors['document_id'] = 'Documento não encontrado.';
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
        $row = (new SponsorDossier())->findById((int) $id);
        if ($row === null) {
            $this->abort(404, 'Dossiê não encontrado.');
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function findItemOr404(mixed $itemId, int $dossierId): array
    {
        $row = (new SponsorDossierItem())->findById((int) $itemId);
        if ($row === null || (int) ($row['dossier_id'] ?? 0) !== $dossierId) {
            $this->abort(404, 'Item do dossiê não encontrado.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $dossier
     */
    private function renderForm(string $view, string $title, array $old, array $errors, ?array $dossier = null): void
    {
        $model          = new SponsorDossier();
        $sponsorId      = (int) ($old['sponsor_id'] ?? 0);
        $companyId      = (int) ($old['company_id'] ?? 0);
        $mainContractId = (int) ($old['main_contract_id'] ?? 0);

        $sponsors  = (new Sponsor())->paginate(['show_archived' => 0], 1, 300);
        $documents = [];
        if (can('documents.view')) {
            $docFilters = array_filter([
                'company_id'  => $companyId ?: null,
                'sponsor_id'  => $sponsorId ?: null,
                'contract_id' => $mainContractId ?: null,
            ]);
            $documents = (new Document())->paginate($docFilters, 1, 100);
        }

        $this->view($view, [
            'title'            => $title,
            'dossier'          => $dossier ?? $old,
            'old'              => $old,
            'errors'           => $errors,
            'dossierTypes'     => $model->getDossierTypes(),
            'statuses'         => $model->getStatuses(),
            'deliveryStatuses' => $model->getDeliveryStatuses(),
            'sponsors'         => $sponsors,
            'contracts'        => $this->contractFilterOptions(),
            'companies'        => (new Company())->activeOptions(),
            'contacts'         => $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [],
            'opportunities'    => $this->linkOptions('opportunities', 'title'),
            'proposals'        => $this->linkOptions('proposals', 'title'),
            'quotas'           => (new Quota())->activeOptions(),
            'users'            => (new User())->activeList(),
            'documents'        => array_map(static fn ($d) => [
                'id'    => (int) $d['id'],
                'label' => (string) ($d['title'] ?? '') . ' (v' . (int) ($d['version_number'] ?? 1) . ')',
            ], $documents),
        ]);
    }
}
