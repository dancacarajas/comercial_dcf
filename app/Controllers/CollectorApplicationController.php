<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\CollectorApplication;
use App\Models\CollectorApplicationDocument;
use App\Models\ContractTemplate;
use App\Models\SignatureRequest;
use App\Models\User;

final class CollectorApplicationController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('collector_applications.view');
        $model   = new CollectorApplication();
        $filters = $this->collectFilters();
        $page    = max(1, (int) input('page', 1));
        $total   = $model->count($filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page    = min($page, $pages);

        $this->view('collector_applications/index', [
            'title'           => 'Credenciamento de captadores',
            'items'           => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'         => $filters,
            'statuses'        => $model->getStatuses(),
            'documentStatuses'=> $model->getDocumentStatuses(),
            'reviewStatuses'  => $model->getReviewStatuses(),
            'accessStatuses'  => $model->getAccessStatuses(),
            'users'           => (new User())->activeList(),
            'page'            => $page,
            'pages'           => $pages,
            'total'           => $total,
            'hasFilters'      => $this->hasActiveFilters($filters),
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('collector_applications.create');
        $this->renderForm('collector_applications/create', 'Nova candidatura', $this->defaultFormData(), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('collector_applications.create');
        csrf_verify();

        $model  = new CollectorApplication();
        $data   = $this->collectInput();
        $errors = $model->validate($data, 'create');

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('collector_applications/create', 'Nova candidatura', $data, $errors);
            return;
        }

        $data['source']      = 'manual';
        $data['created_by']  = $_SESSION['user_id'] ?? null;
        if (!empty($data['consent_contact'])) {
            $data['consent_lgpd_at'] = date('Y-m-d H:i:s');
        }

        $id = $model->create($data);
        (new ActivityLog())->record('collector_application_created', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Candidatura registrada.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.view');
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];
        $model = new CollectorApplication();
        $docModel = new CollectorApplicationDocument();

        $publicUrl = null;
        if (!empty($app['public_token'])) {
            $publicUrl = app_url('/captadores/credenciamento/' . rawurlencode((string) $app['public_token']));
        }

        $sigModel = new SignatureRequest();
        $signatureProgress = $model->signatureStageProgress($id);
        $signatureStageItems = $signatureProgress['items'];
        $activeSignatures = $sigModel->activeForCollectorApplicationList($id);
        $activeSignature = $activeSignatures[0] ?? null;
        $signatureSigners = $activeSignature ? $sigModel->signersForRequest((int) $activeSignature['id']) : [];
        $signatureLink = $this->captadorSignatureLink($signatureSigners);
        $contractTemplates = (new ContractTemplate())->paginate(['status' => 'ativo'], 1, 50);
        $defaultContractTemplate = (new ContractTemplate())->findDefaultForType('contrato_captador')
            ?? (new ContractTemplate())->findDefaultForType('autorizacao_captador');
        $collectorStageTemplatesConfigured = (new ContractTemplate())->hasCollectorSignatureStageConfigured();
        $hasAllRequiredSignatures = $model->hasCompletedRequiredCollectorSignatures($app);

        $this->view('collector_applications/show', [
            'title'            => $app['name'] ?? 'Candidatura',
            'application'      => $app,
            'documents'        => $docModel->findByApplication($id),
            'docTypes'         => $docModel->getAllTypes(),
            'docStatuses'      => $docModel->getStatuses(),
            'statuses'         => $model->getStatuses(),
            'documentStatuses' => $model->getDocumentStatuses(),
            'reviewStatuses'   => $model->getReviewStatuses(),
            'accessStatuses'   => $model->getAccessStatuses(),
            'rouanetOptions'   => $model->getRouanetExperienceOptions(),
            'publicUrl'        => $publicUrl,
            'defaultDocTypes'  => $model->defaultDocumentTypesFor($app),
            'optionalDocTypes' => $model->optionalDocumentTypesFor($app),
            'entityTypeLabel'  => $model->entityTypeLabel($app),
            'isLegalEntity'    => $model->isLegalEntity($app),
            'docProgress'      => $model->documentProgress($id),
            'allDocumentsSubmitted' => $model->allDocumentsSubmitted($id),
            'linkedUser'       => !empty($app['user_created_id']) ? (new User())->find((int) $app['user_created_id']) : null,
            'activeSignature'  => $activeSignature,
            'activeSignatures' => $activeSignatures,
            'signatureSigners' => $signatureSigners,
            'signatureLink'    => $signatureLink,
            'signatureStageItems' => $signatureStageItems,
            'signatureProgress' => $signatureProgress,
            'collectorStageTemplatesConfigured' => $collectorStageTemplatesConfigured,
            'hasAllRequiredSignatures' => $hasAllRequiredSignatures,
            'contractTemplates'=> $contractTemplates,
            'defaultContractTemplateId' => (int) ($defaultContractTemplate['id'] ?? 0),
            'hasSignedContract'=> $model->hasSignedContract($app),
            'allDocumentsSubmitted' => $model->allDocumentsSubmitted($id),
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.edit');
        $app = $this->findOr404($params['id'] ?? null);
        $this->renderForm('collector_applications/edit', 'Editar candidatura', $app, [], $app);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.edit');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];

        $model  = new CollectorApplication();
        $data   = $this->collectInput();
        $errors = $model->validate($data, 'update');

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('collector_applications/edit', 'Editar candidatura', array_merge($app, $data), $errors, $app);
            return;
        }

        $newStatus = (string) ($data['status'] ?? '');
        if (in_array($newStatus, ['em_analise_documental', 'aprovado'], true) && !$model->allDocumentsSubmitted($id)) {
            flash('error', 'Só é possível avançar após o envio completo de todos os documentos solicitados.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);
        (new ActivityLog())->record('collector_application_updated', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Candidatura atualizada.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.review');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];
        $status = (string) input('status', '');
        $model = new CollectorApplication();

        if (!array_key_exists($status, $model->getStatuses())) {
            flash('error', 'Status inválido.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        if (in_array($status, ['em_analise_documental', 'aprovado'], true) && !$model->allDocumentsSubmitted($id)) {
            flash('error', 'Só é possível avançar após o envio completo de todos os documentos solicitados.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        $model->update($id, [
            'status'      => $status,
            'updated_by'  => $_SESSION['user_id'] ?? null,
            'reviewed_by' => $_SESSION['user_id'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes'=> trim((string) input('review_notes', '')),
        ]);
        (new ActivityLog())->record('collector_application_status_changed', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Status atualizado.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function requestDocuments(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.request_documents');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];
        $model = new CollectorApplication();
        $docModel = new CollectorApplicationDocument();

        $types = input('document_types', []);
        if (!is_array($types) || $types === []) {
            $types = array_keys($model->defaultDocumentTypesFor($app));
        }

        $docModel->createSlots($id, $types);
        $token = !empty($app['public_token']) ? (string) $app['public_token'] : $model->generatePublicToken($id, (int) input('token_days', 30));

        $model->update($id, [
            'status'                  => 'documentos_solicitados',
            'document_status'         => 'solicitado',
            'documents_requested_at'  => date('Y-m-d H:i:s'),
            'updated_by'              => $_SESSION['user_id'] ?? null,
        ]);

        (new ActivityLog())->record('collector_documents_requested', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        (new ActivityLog())->record('collector_public_token_generated', $_SESSION['user_id'] ?? null, 'collector_application', $id);

        $url = app_url('/captadores/credenciamento/' . rawurlencode($token));
        flash('success', 'Documentos solicitados. Link público: ' . $url);
        $this->redirect('/collector-applications/' . $id);
    }

    public function reviewDocument(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.review');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $slotId = (int) input('document_id', 0);
        $status = (string) input('document_status', '');
        $notes  = trim((string) input('review_notes', ''));

        $docModel = new CollectorApplicationDocument();
        $slot = $docModel->findById($slotId);
        if ($slot === null || (int) ($slot['collector_application_id'] ?? 0) !== (int) $app['id']) {
            flash('error', 'Documento inválido.');
            $this->redirect('/collector-applications/' . (int) $app['id']);
            return;
        }

        $docModel->review($slotId, $status, $notes, $_SESSION['user_id'] ?? null);
        (new CollectorApplication())->syncDocumentStatus((int) $app['id']);
        (new ActivityLog())->record('collector_document_reviewed', $_SESSION['user_id'] ?? null, 'collector_application_document', $slotId);
        flash('success', 'Documento atualizado.');
        $this->redirect('/collector-applications/' . (int) $app['id']);
    }

    public function approve(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.approve');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];

        if (!(new CollectorApplication())->allDocumentsSubmitted($id)) {
            flash('error', 'A candidatura só pode ser aprovada após o envio completo de todos os documentos.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        (new CollectorApplication())->update($id, [
            'status'          => 'aprovado',
            'review_status'   => 'aprovado',
            'approval_notes'  => trim((string) input('approval_notes', '')),
            'approved_by'     => $_SESSION['user_id'] ?? null,
            'approved_at'     => date('Y-m-d H:i:s'),
            'updated_by'      => $_SESSION['user_id'] ?? null,
        ]);
        (new ActivityLog())->record('collector_application_approved', $_SESSION['user_id'] ?? null, 'collector_application', $id);

        $updated = (new CollectorApplication())->findById($id) ?? $app;
        if (can('signature_requests.create')) {
            $result = $this->createRequiredCollectorSignatures($id, $updated);
            if ($result['created'] !== []) {
                flash('success', 'Candidatura aprovada. Documentos de assinatura gerados — JA Produções já assinou automaticamente.');
            } elseif ($result['skipped'] !== []) {
                flash('success', 'Candidatura aprovada. Documentos de assinatura já gerados.');
            } else {
                flash('success', 'Candidatura aprovada.');
                flash('warning', 'Nenhum modelo obrigatório configurado para a Etapa 5 dos captadores.');
            }
        } else {
            flash('success', 'Candidatura aprovada. Gere os documentos de assinatura na seção Contrato e assinatura.');
        }

        $this->redirect('/collector-applications/' . $id);
    }

    public function reject(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.approve');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];

        (new CollectorApplication())->update($id, [
            'status'           => 'reprovado',
            'review_status'    => 'reprovado',
            'rejection_reason' => trim((string) input('rejection_reason', '')),
            'rejected_by'      => $_SESSION['user_id'] ?? null,
            'rejected_at'      => date('Y-m-d H:i:s'),
            'updated_by'       => $_SESSION['user_id'] ?? null,
        ]);
        (new ActivityLog())->record('collector_application_rejected', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Candidatura reprovada.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function generateContract(array $params): void
    {
        AuthMiddleware::requirePermission('signature_requests.create');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];

        if (!in_array((string) ($app['status'] ?? ''), ['aprovado', 'aguardando_assinatura_contratual'], true)) {
            flash('error', 'Só é possível gerar documentos de assinatura após aprovação da candidatura.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        $updated = (new CollectorApplication())->findById($id) ?? $app;
        $result = $this->createRequiredCollectorSignatures($id, $updated);
        if ($result['created'] === [] && $result['skipped'] === []) {
            flash('warning', 'Nenhum modelo obrigatório configurado para a Etapa 5 dos captadores.');
        } elseif ($result['created'] === []) {
            flash('info', 'Documentos de assinatura já gerados.');
        } else {
            flash('success', 'Documentos de assinatura gerados com assinatura automática da JA Produções.');
        }
        $this->redirect('/collector-applications/' . $id);
    }

    public function generateSignatures(array $params): void
    {
        $this->generateContract($params);
    }

    public function prepareAccess(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.release_access');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];
        $model = new CollectorApplication();

        if (!$model->hasCompletedRequiredCollectorSignatures($app)) {
            $pending = $model->signatureStageProgress($id);
            $titles = implode(', ', $pending['pending_required_titles']);
            $detail = $titles !== '' ? ' Pendentes: ' . $titles . '.' : '';
            (new ActivityLog())->record('collector_access_blocked_pending_signature', $_SESSION['user_id'] ?? null, 'collector_application', $id);
            flash('error', 'O acesso só poderá ser preparado após assinatura de todos os documentos contratuais obrigatórios.' . $detail);
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        if (!in_array((string) ($app['status'] ?? ''), ['contrato_assinado', 'acesso_preparado'], true)) {
            flash('error', 'A candidatura precisa ter contrato assinado antes de preparar acesso.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        (new CollectorApplication())->update($id, [
            'access_status' => 'pendente_criacao',
            'status'        => 'acesso_preparado',
            'updated_by'    => $_SESSION['user_id'] ?? null,
        ]);

        (new ActivityLog())->record('collector_access_prepared', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Acesso preparado. Libere o acesso para o captador concluir o cadastro pelo link público.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function releaseAccess(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.release_access');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        $id  = (int) $app['id'];
        $model = new CollectorApplication();

        if (!$model->hasCompletedRequiredCollectorSignatures($app)) {
            $pending = $model->signatureStageProgress($id);
            $titles = implode(', ', $pending['pending_required_titles']);
            $detail = $titles !== '' ? ' Pendentes: ' . $titles . '.' : '';
            (new ActivityLog())->record('collector_access_blocked_pending_signature', $_SESSION['user_id'] ?? null, 'collector_application', $id);
            flash('error', 'O acesso só poderá ser liberado após assinatura de todos os documentos contratuais obrigatórios.' . $detail);
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        if (!in_array((string) ($app['status'] ?? ''), ['contrato_assinado', 'acesso_preparado'], true)) {
            flash('error', 'A candidatura precisa ter contrato assinado (ou acesso preparado) antes de liberar.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        if ((new CollectorApplication())->hasCompletedOnboarding($app)) {
            flash('warning', 'Este captador já concluiu o cadastro de acesso.');
            $this->redirect('/collector-applications/' . $id);
            return;
        }

        (new CollectorApplication())->update($id, [
            'access_status'      => 'acesso_liberado',
            'status'             => 'acesso_liberado',
            'access_released_at' => date('Y-m-d H:i:s'),
            'updated_by'         => $_SESSION['user_id'] ?? null,
        ]);
        (new ActivityLog())->record('collector_access_released', $_SESSION['user_id'] ?? null, 'collector_application', $id);
        flash('success', 'Acesso liberado. O captador deve concluir o cadastro (usuário e senha) pelo link público.');
        $this->redirect('/collector-applications/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.archive');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        (new CollectorApplication())->archive((int) $app['id'], $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('collector_application_archived', $_SESSION['user_id'] ?? null, 'collector_application', (int) $app['id']);
        flash('success', 'Candidatura arquivada.');
        $this->redirect('/collector-applications');
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('collector_applications.archive');
        csrf_verify();
        $app = $this->findOr404($params['id'] ?? null);
        (new CollectorApplication())->restore((int) $app['id'], $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('collector_application_restored', $_SESSION['user_id'] ?? null, 'collector_application', (int) $app['id']);
        flash('success', 'Candidatura restaurada.');
        $this->redirect('/collector-applications/' . (int) $app['id']);
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = (new CollectorApplication())->findById($id ?? 0);
        if ($row === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Não encontrado']);
            exit;
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'               => trim((string) input('q', '')),
            'status'          => trim((string) input('status', '')),
            'document_status' => trim((string) input('document_status', '')),
            'review_status'   => trim((string) input('review_status', '')),
            'access_status'   => trim((string) input('access_status', '')),
            'source'          => trim((string) input('source', '')),
            'assigned_user_id'=> (int) input('assigned_user_id', 0) ?: '',
            'show_archived'   => !empty(input('show_archived', '')),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function hasActiveFilters(array $filters): bool
    {
        foreach ($filters as $k => $v) {
            if ($k === 'show_archived') {
                continue;
            }
            if ($v !== '' && $v !== null) {
                return true;
            }
        }

        return !empty($filters['show_archived']);
    }

    /** @return array<string, mixed> */
    private function defaultFormData(): array
    {
        return [
            'status'            => 'manifestacao_recebida',
            'document_status'   => 'nao_solicitado',
            'review_status'     => 'pendente',
            'access_status'     => 'nao_liberado',
            'consent_contact'   => 0,
            'source'            => 'manual',
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(): array
    {
        return [
            'name'                        => trim((string) input('name', '')),
            'company_or_activity'         => trim((string) input('company_or_activity', '')),
            'document_number'             => trim((string) input('document_number', '')),
            'email'                       => trim((string) input('email', '')),
            'phone_whatsapp'              => trim((string) input('phone_whatsapp', '')),
            'city_state'                  => trim((string) input('city_state', '')),
            'rouanet_experience'          => trim((string) input('rouanet_experience', '')),
            'segments'                    => trim((string) input('segments', '')),
            'sponsor_network_description' => trim((string) input('sponsor_network_description', '')),
            'message'                     => trim((string) input('message', '')),
            'status'                      => trim((string) input('status', 'manifestacao_recebida')),
            'document_status'             => trim((string) input('document_status', 'nao_solicitado')),
            'review_status'               => trim((string) input('review_status', 'pendente')),
            'access_status'               => trim((string) input('access_status', 'nao_liberado')),
            'assigned_user_id'            => (int) input('assigned_user_id', 0) ?: null,
            'internal_notes'              => trim((string) input('internal_notes', '')),
            'review_notes'                => trim((string) input('review_notes', '')),
            'consent_contact'             => !empty(input('consent_contact', '')) ? 1 : 0,
            'source_page'                 => trim((string) input('source_page', '')),
            'source_url'                  => trim((string) input('source_url', '')),
        ];
    }

    /**
     * @param array<string, mixed> $application
     * @return array{created: list<int>, skipped: list<int>}
     */
    private function createRequiredCollectorSignatures(int $applicationId, array $application, int|string|null $userId = null): array
    {
        $userId ??= $_SESSION['user_id'] ?? null;
        $templateModel = new ContractTemplate();
        $sigModel = new SignatureRequest();
        $templates = $templateModel->findForCollectorSignatureStage();
        $created = [];
        $skipped = [];

        foreach ($templates as $template) {
            $templateId = (int) ($template['id'] ?? 0);
            if ($templateId <= 0) {
                continue;
            }

            $existing = $sigModel->activeForCollectorApplicationByTemplate($applicationId, $templateId);
            if ($existing !== null) {
                $skipped[] = (int) ($existing['id'] ?? 0);
                continue;
            }

            $requestId = $sigModel->createForCollectorApplication($application, $template, $userId);
            $created[] = $requestId;
            (new ActivityLog())->record('collector_contract_generated', $userId, 'collector_application', $applicationId);
            (new ActivityLog())->record('collector_signature_requested', $userId, 'collector_application', $applicationId);
            (new ActivityLog())->record('signature_request_created', $userId, 'signature_request', $requestId);
        }

        if ($created !== [] || $skipped !== []) {
            $sigModel->syncCollectorApplicationSignatureStage($applicationId);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array<string, mixed>|null */
    private function resolveContractTemplate(int $templateId): ?array
    {
        $templateModel = new ContractTemplate();
        $template = $templateId > 0 ? $templateModel->findById($templateId) : $templateModel->findDefaultForType('contrato_captador');
        if ($template === null) {
            $template = $templateModel->findDefaultForType('autorizacao_captador');
        }

        return $template;
    }

    /** @param list<array<string, mixed>> $signers */
    private function captadorSignatureLink(array $signers): ?string
    {
        foreach ($signers as $signerRow) {
            if (($signerRow['signer_role'] ?? '') === 'captador' && !empty($signerRow['public_token'])) {
                return app_url('/assinatura/' . rawurlencode((string) $signerRow['public_token']));
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $existing
     */
    private function renderForm(string $view, string $title, array $data, array $errors, ?array $existing = null): void
    {
        $model = new CollectorApplication();
        $this->view($view, [
            'title'            => $title,
            'data'             => $data,
            'errors'           => $errors,
            'existing'         => $existing,
            'statuses'         => $model->getStatuses(),
            'documentStatuses' => $model->getDocumentStatuses(),
            'reviewStatuses'   => $model->getReviewStatuses(),
            'accessStatuses'   => $model->getAccessStatuses(),
            'rouanetOptions'   => $model->getRouanetExperienceOptions(),
            'users'            => (new User())->activeList(),
        ]);
    }
}
