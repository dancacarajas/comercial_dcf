<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorApplication;
use App\Models\CollectorAssignment;
use App\Models\CollectorDeal;
use App\Models\User;

/**
 * Cadastro mestre de captadores credenciados (Etapa 18C).
 */
final class CollectorController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('collectors.view');
        $model = new Collector();
        $filters = [
            'q'                   => trim((string) input('q', '')),
            'status'              => trim((string) input('status', '')),
            'registration_status' => trim((string) input('registration_status', '')),
            'type'                => trim((string) input('type', '')),
            'show_archived'       => !empty(input('show_archived', '')),
        ];
        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('collectors/index', [
            'title'               => 'Captadores credenciados',
            'items'               => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'             => $filters,
            'types'               => $model->getTypes(),
            'statuses'            => $model->getStatuses(),
            'registrationStatuses'=> $model->getRegistrationStatuses(),
            'page'                => $page,
            'pages'               => $pages,
            'total'               => $total,
        ]);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.view');
        $model = new Collector();
        $collector = $model->findById((int) ($params['id'] ?? 0));
        if ($collector === null) {
            $this->abort(404, 'Captador não encontrado.');
        }

        $assignmentModel = new CollectorAssignment();
        $dealModel = new CollectorDeal();
        $collectorId = (int) $collector['id'];

        $this->view('collectors/show', [
            'title'                => $collector['name'] ?? 'Captador',
            'collector'            => $collector,
            'types'                => $model->getTypes(),
            'statuses'             => $model->getStatuses(),
            'registrationStatuses' => $model->getRegistrationStatuses(),
            'missing'              => $model->missingRequirements($collector),
            'assignments'          => $assignmentModel->forCollector($collectorId),
            'assignmentTypes'      => $assignmentModel->getTypes(),
            'assignmentStatuses'   => $assignmentModel->getStatuses(),
            'deals'                => $dealModel->forCollector($collectorId),
            'dealStatuses'         => $dealModel->getStatuses(),
        ]);
    }

    public function create(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.manage');
        $app = $this->findApplicationOr404($params['id'] ?? null);
        $model = new Collector();

        $existing = $model->findByApplication((int) $app['id']);
        if ($existing !== null) {
            $this->redirect('/collector-applications/' . (int) $app['id'] . '/collector/edit');
            return;
        }

        $this->renderForm($app, $model->defaultsFromApplication($app), [], false);
    }

    public function store(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.manage');
        csrf_verify();
        $app = $this->findApplicationOr404($params['id'] ?? null);
        $model = new Collector();

        if ($model->findByApplication((int) $app['id']) !== null) {
            flash('warning', 'Este captador já possui cadastro mestre.');
            $this->redirect('/collector-applications/' . (int) $app['id'] . '/collector/edit');
            return;
        }

        $data = $this->collectInput();
        $data['collector_application_id'] = (int) $app['id'];
        $data['user_id'] = !empty($app['user_created_id']) ? (int) $app['user_created_id'] : null;
        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $data['registration_status'] = 'incompleto';

        $errors = $model->validate($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($app, $data, $errors, false);
            return;
        }

        $id = (int) $model->create($data);
        $model->refreshRegistrationStatus($id);
        (new ActivityLog())->record('collector_master_created', $_SESSION['user_id'] ?? null, 'collector', $id);
        flash('success', 'Cadastro do captador criado.');
        $this->redirect('/collector-applications/' . (int) $app['id']);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.manage');
        $app = $this->findApplicationOr404($params['id'] ?? null);
        $model = new Collector();
        $collector = $model->findByApplication((int) $app['id']);
        if ($collector === null) {
            $this->redirect('/collector-applications/' . (int) $app['id'] . '/collector/create');
            return;
        }

        $this->renderForm($app, $collector, [], true);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.manage');
        csrf_verify();
        $app = $this->findApplicationOr404($params['id'] ?? null);
        $model = new Collector();
        $collector = $model->findByApplication((int) $app['id']);
        if ($collector === null) {
            $this->redirect('/collector-applications/' . (int) $app['id'] . '/collector/create');
            return;
        }

        $data = $this->collectInput();
        $data['updated_by'] = $_SESSION['user_id'] ?? null;

        $errors = $model->validate($data);
        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm($app, array_merge($collector, $data), $errors, true);
            return;
        }

        $cid = (int) $collector['id'];
        $model->update($cid, $data);
        $model->refreshRegistrationStatus($cid);
        (new ActivityLog())->record('collector_master_updated', $_SESSION['user_id'] ?? null, 'collector', $cid);
        flash('success', 'Cadastro do captador atualizado.');
        $this->redirect('/collector-applications/' . (int) $app['id']);
    }

    public function validateRegistration(array $params): void
    {
        AuthMiddleware::requirePermission('collectors.validate');
        csrf_verify();
        $app = $this->findApplicationOr404($params['id'] ?? null);
        $model = new Collector();
        $collector = $model->findByApplication((int) $app['id']);
        if ($collector === null) {
            flash('error', 'Crie o cadastro do captador antes de validar.');
            $this->redirect('/collector-applications/' . (int) $app['id']);
            return;
        }

        $missing = $model->missingRequirements($collector);
        if ($missing !== []) {
            flash('error', 'Cadastro incompleto. Pendências: ' . implode('; ', $missing) . '.');
            $this->redirect('/collector-applications/' . (int) $app['id'] . '/collector/edit');
            return;
        }

        $model->markValidated((int) $collector['id'], $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('collector_master_validated', $_SESSION['user_id'] ?? null, 'collector', (int) $collector['id']);
        flash('success', 'Cadastro do captador validado. Geração de documentos liberada.');
        $this->redirect('/collector-applications/' . (int) $app['id']);
    }

    /**
     * @param array<string, mixed> $app
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function renderForm(array $app, array $data, array $errors, bool $isEdit): void
    {
        $model = new Collector();
        $this->view('collectors/form', [
            'title'                => $isEdit ? 'Editar cadastro do captador' : 'Cadastro do captador',
            'application'          => $app,
            'data'                 => $data,
            'errors'               => $errors,
            'isEdit'               => $isEdit,
            'types'                => $model->getTypes(),
            'statuses'             => $model->getStatuses(),
            'accountTypes'         => $model->getAccountTypes(),
            'pixKeyTypes'          => $model->getPixKeyTypes(),
            'rouanetOptions'       => (new CollectorApplication())->getRouanetExperienceOptions(),
            'missing'              => $model->missingRequirements($data),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectInput(): array
    {
        $textFields = [
            'type', 'status', 'name', 'legal_name', 'trade_name', 'document_number',
            'state_registration', 'municipal_registration', 'birth_date', 'nationality',
            'marital_status', 'profession', 'email', 'phone_whatsapp', 'secondary_phone',
            'address_zipcode', 'address_street', 'address_number', 'address_complement',
            'address_district', 'address_city', 'address_state',
            'bank_name', 'bank_code', 'agency', 'account', 'account_digit', 'account_type',
            'pix_key', 'pix_key_type', 'bank_holder_name', 'bank_holder_document',
            'representative_name', 'representative_document', 'representative_email',
            'representative_phone', 'representative_role',
            'rouanet_experience', 'segments', 'sponsor_network_description', 'territory_scope',
            'portfolio_summary', 'commission_payment_rule', 'commission_limit_rule',
            'contract_start_date', 'contract_end_date', 'exclusivity_type', 'exclusivity_scope',
            'internal_notes',
        ];

        $data = [];
        foreach ($textFields as $field) {
            $data[$field] = trim((string) input($field, ''));
        }

        $data['address_state'] = strtoupper(substr($data['address_state'], 0, 2));

        $pct = trim((string) input('commission_percentage', ''));
        $data['commission_percentage'] = $pct === '' ? null : str_replace(',', '.', $pct);

        $data['has_rouanet_experience'] = !empty(input('has_rouanet_experience', '')) ? 1 : 0;
        $data['confidentiality_required'] = !empty(input('confidentiality_required', '')) ? 1 : 0;

        return $data;
    }

    /** @return array<string, mixed> */
    private function findApplicationOr404(mixed $id): array
    {
        $row = (new CollectorApplication())->findById($id ?? 0);
        if ($row === null) {
            $this->abort(404, 'Candidatura não encontrada.');
        }

        return $row;
    }
}
