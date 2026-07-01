<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\IncentiveProject;
use App\Models\IncentiveProjectBudgetItem;

/**
 * Projetos Incentivados / PRONACs / Plano de Captação (ETAPA 19).
 *
 * Cadastro-mãe do sistema. CRUD + rubricas orçamentárias + dashboard por projeto.
 * Permissões: incentive_projects.view/create/edit/archive/budget/activate_capture.
 */
final class IncentiveProjectController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        AuthMiddleware::requirePermission('incentive_projects.view');

        $model   = new IncentiveProject();
        $filters = [
            'q'             => (string) input('q', ''),
            'status'        => (string) input('status', ''),
            'year'          => (int) input('year', 0),
            'show_archived' => input('show_archived') !== null ? 1 : 0,
        ];

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('incentive_projects/index', [
            'title'        => 'Projetos Incentivados',
            'items'        => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'      => $filters,
            'statusLabels' => $model->getStatuses(),
            'page'         => $page,
            'pages'        => $pages,
            'total'        => $total,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('incentive_projects.create');
        $this->renderForm('incentive_projects/create', 'Novo projeto incentivado', [
            'project_status' => 'em_elaboracao',
            'law_framework'  => 'Lei Rouanet',
        ], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('incentive_projects.create');
        csrf_verify();

        $model = new IncentiveProject();
        $data  = $this->collectInput($model);
        $errors = $model->validate($data);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('incentive_projects/create', 'Novo projeto incentivado', $data, $errors);
            return;
        }

        $data['commission_factor'] = $model->computeFactor($data['capture_commission_budget'] ?? null, $data['approved_total_amount'] ?? null);
        $data['created_by'] = $_SESSION['user_id'] ?? null;

        $id = (int) $model->create($data);
        (new ActivityLog())->record('incentive_project_created', $_SESSION['user_id'] ?? null, 'incentive_project', $id);

        flash('success', 'Projeto incentivado cadastrado.');
        $this->redirect('/projects/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.view');
        $project = $this->findOr404($params['id'] ?? null);
        $model   = new IncentiveProject();

        $this->view('incentive_projects/show', [
            'title'        => (string) $project['project_name'],
            'project'      => $project,
            'statusLabels' => $model->getStatuses(),
            'budgetItems'  => (new IncentiveProjectBudgetItem())->forProject((int) $project['id']),
            'metrics'      => $model->dashboard((int) $project['id']),
            'captureStatuses' => $model->getCaptureStatuses(),
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.edit');
        $project = $this->findOr404($params['id'] ?? null);
        $this->renderForm('incentive_projects/edit', 'Editar projeto incentivado', $project, [], $project);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.edit');
        csrf_verify();

        $project = $this->findOr404($params['id'] ?? null);
        $id      = (int) $project['id'];
        $model   = new IncentiveProject();
        $data    = $this->collectInput($model);
        $errors  = $model->validate($data);

        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($project, $data);
            $this->renderForm('incentive_projects/edit', 'Editar projeto incentivado', $data, $errors, $merged);
            return;
        }

        $data['commission_factor'] = $model->computeFactor($data['capture_commission_budget'] ?? null, $data['approved_total_amount'] ?? null);
        $data['updated_by'] = $_SESSION['user_id'] ?? null;

        $model->update($id, $data);
        // Reconcilia rubrica/fator a partir das rubricas marcadas (se houver).
        $model->syncBudgetAndFactor($id);
        (new ActivityLog())->record('incentive_project_updated', $_SESSION['user_id'] ?? null, 'incentive_project', $id);

        flash('success', 'Projeto atualizado.');
        $this->redirect('/projects/' . $id);
    }

    public function budget(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.view');
        $project = $this->findOr404($params['id'] ?? null);
        $itemModel = new IncentiveProjectBudgetItem();

        $this->view('incentive_projects/budget', [
            'title'       => 'Orçamento — ' . (string) $project['project_name'],
            'project'     => $project,
            'budgetItems' => $itemModel->forProject((int) $project['id']),
            'budgetTotal' => $itemModel->totalForProject((int) $project['id']),
            'commissionBudget' => $itemModel->commissionBudgetForProject((int) $project['id']),
            'canEdit'     => can('incentive_projects.budget'),
        ]);
    }

    public function budgetStore(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.budget');
        csrf_verify();

        $project = $this->findOr404($params['id'] ?? null);
        $id      = (int) $project['id'];
        $itemModel = new IncentiveProjectBudgetItem();

        $data = [
            'incentive_project_id'       => $id,
            'item_number'                => (int) input('item_number', 0) ?: null,
            'product'                    => clean((string) input('product', '')),
            'stage'                      => clean((string) input('stage', '')),
            'budget_item_name'           => clean((string) input('budget_item_name', '')),
            'unit'                       => clean((string) input('unit', '')),
            'requested_amount'           => $itemModel->normalizeMoney((string) input('requested_amount', '')),
            'is_capture_commission_item' => input('is_capture_commission_item') !== null ? 1 : 0,
            'notes'                      => trim((string) input('notes', '')),
        ];

        $errors = $itemModel->validate($data);
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/projects/' . $id . '/budget');
            return;
        }

        $itemModel->create($data);
        (new IncentiveProject())->syncBudgetAndFactor($id);
        (new ActivityLog())->record('incentive_project_budget_item_added', $_SESSION['user_id'] ?? null, 'incentive_project', $id);

        flash('success', 'Rubrica cadastrada.');
        $this->redirect('/projects/' . $id . '/budget');
    }

    public function budgetArchive(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.budget');
        csrf_verify();

        $project = $this->findOr404($params['id'] ?? null);
        $id      = (int) $project['id'];
        $itemId  = (int) ($params['itemId'] ?? 0);

        (new IncentiveProjectBudgetItem())->archive($itemId);
        (new IncentiveProject())->syncBudgetAndFactor($id);
        flash('success', 'Rubrica arquivada.');
        $this->redirect('/projects/' . $id . '/budget');
    }

    public function dashboard(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.view');
        $project = $this->findOr404($params['id'] ?? null);
        $model   = new IncentiveProject();

        $this->view('incentive_projects/dashboard', [
            'title'   => 'Painel — ' . (string) $project['project_name'],
            'project' => $project,
            'metrics' => $model->dashboard((int) $project['id']),
            'commissionBudget' => (new IncentiveProjectBudgetItem())->commissionBudgetForProject((int) $project['id']),
        ]);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.archive');
        csrf_verify();
        $project = $this->findOr404($params['id'] ?? null);
        (new IncentiveProject())->archive((int) $project['id']);
        (new ActivityLog())->record('incentive_project_archived', $_SESSION['user_id'] ?? null, 'incentive_project', (int) $project['id']);
        flash('success', 'Projeto arquivado.');
        $this->redirect('/projects/' . (int) $project['id']);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.archive');
        csrf_verify();
        $project = $this->findOr404($params['id'] ?? null);
        (new IncentiveProject())->restore((int) $project['id']);
        (new ActivityLog())->record('incentive_project_restored', $_SESSION['user_id'] ?? null, 'incentive_project', (int) $project['id']);
        flash('success', 'Projeto restaurado.');
        $this->redirect('/projects/' . (int) $project['id']);
    }

    public function activateCapture(array $params): void
    {
        AuthMiddleware::requirePermission('incentive_projects.activate_capture');
        csrf_verify();
        $project = $this->findOr404($params['id'] ?? null);
        (new IncentiveProject())->update((int) $project['id'], [
            'project_status' => 'em_captacao',
            'updated_by'     => $_SESSION['user_id'] ?? null,
        ]);
        (new ActivityLog())->record('incentive_project_capture_activated', $_SESSION['user_id'] ?? null, 'incentive_project', (int) $project['id']);
        flash('success', 'Projeto liberado para captação.');
        $this->redirect('/projects/' . (int) $project['id']);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function collectInput(IncentiveProject $model): array
    {
        $year = (int) input('edition_year', 0);

        return [
            'project_name'              => clean((string) input('project_name', '')),
            'edition_year'              => $year > 0 ? $year : null,
            'pronac_number'             => clean((string) input('pronac_number', '')) ?: null,
            'salic_proposal_number'     => clean((string) input('salic_proposal_number', '')) ?: null,
            'law_framework'             => clean((string) input('law_framework', '')) ?: null,
            'proponent_name'            => clean((string) input('proponent_name', '')) ?: null,
            'proponent_document'        => clean((string) input('proponent_document', '')) ?: null,
            'project_status'            => clean((string) input('project_status', 'em_elaboracao')),
            'approved_total_amount'     => $model->normalizeMoney((string) input('approved_total_amount', '')),
            'authorized_capture_amount' => $model->normalizeMoney((string) input('authorized_capture_amount', '')),
            'capture_commission_budget' => $model->normalizeMoney((string) input('capture_commission_budget', '')),
            'capture_start_date'        => $this->normDate((string) input('capture_start_date', '')),
            'capture_end_date'          => $this->normDate((string) input('capture_end_date', '')),
            'bank_name'                 => clean((string) input('bank_name', '')) ?: null,
            'bank_agency'               => clean((string) input('bank_agency', '')) ?: null,
            'bank_account'              => clean((string) input('bank_account', '')) ?: null,
            'bank_account_digit'        => clean((string) input('bank_account_digit', '')) ?: null,
            'bank_account_type'         => clean((string) input('bank_account_type', '')) ?: null,
            'notes'                     => trim((string) input('notes', '')) ?: null,
        ];
    }

    private function normDate(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $project
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $project = []): void
    {
        $model = new IncentiveProject();
        $this->view($view, [
            'title'             => $title,
            'old'               => $old,
            'errors'            => $errors,
            'project'           => $project,
            'statusLabels'      => $model->getStatuses(),
            'bankAccountTypes'  => $model->getBankAccountTypes(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $project = is_numeric($id) ? (new IncentiveProject())->findById((int) $id) : null;
        if ($project === null) {
            $this->abort(404, 'Projeto incentivado não encontrado.');
        }

        return $project;
    }
}
