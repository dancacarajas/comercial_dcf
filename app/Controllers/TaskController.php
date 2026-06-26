<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;

/**
 * Módulo Tarefas e Follow-ups (Etapa 8).
 *
 * Vínculos opcionais com empresa, contato e oportunidade. Sem exclusão física.
 * Permissões: tasks.view / tasks.create / tasks.edit / tasks.complete
 * (edit cobre edição, arquivamento e restauração; complete cobre concluir/reabrir).
 */
final class TaskController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('tasks.view');

        $model   = new Task();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $items = $model->paginate($filters, $page, self::PER_PAGE);

        $this->view('tasks/index', array_merge($this->lists($model), [
            'title'   => 'Tarefas e follow-ups',
            'items'   => $items,
            'filters' => $filters,
            'owners'  => (new User())->activeList(),
            'model'   => $model,
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'perPage' => self::PER_PAGE,
        ]));
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('tasks.create');

        $old = ['type' => 'follow_up', 'priority' => 'normal', 'status' => 'pendente'];
        foreach (['company_id', 'contact_id', 'opportunity_id'] as $k) {
            $v = (int) input($k, 0);
            if ($v > 0) { $old[$k] = $v; }
        }
        $old = $this->prefillFromOpportunity($old);

        $this->renderForm('tasks/create', 'Nova tarefa', $old, []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.create');
        $id = (int) ($params['id'] ?? 0);
        if ($id <= 0 || (new Company())->findById($id) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }
        $this->renderForm('tasks/create', 'Nova tarefa', ['type' => 'follow_up', 'priority' => 'normal', 'status' => 'pendente', 'company_id' => $id], []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.create');
        $id = (int) ($params['id'] ?? 0);
        $contact = $id > 0 ? (new Contact())->findById($id) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }
        $this->renderForm('tasks/create', 'Nova tarefa', [
            'type' => 'follow_up', 'priority' => 'normal', 'status' => 'pendente',
            'company_id' => (int) $contact['company_id'], 'contact_id' => $id,
        ], []);
    }

    public function createForOpportunity(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.create');
        $id = (int) ($params['id'] ?? 0);
        $opportunity = $id > 0 ? (new Opportunity())->findById($id) : null;
        if ($opportunity === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }
        $old = $this->prefillFromOpportunity([
            'type' => 'follow_up', 'priority' => 'normal', 'status' => 'pendente',
            'opportunity_id' => $id,
        ]);
        $this->renderForm('tasks/create', 'Nova tarefa', $old, []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('tasks.create');
        csrf_verify();

        $model = new Task();
        $data  = $this->prefillFromOpportunity($this->collectInput($model));

        $errors = $model->validate($data, 'create');
        $this->checkLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('tasks/create', 'Nova tarefa', $data, $errors);
            return;
        }

        $uid = $_SESSION['user_id'] ?? null;
        if ($data['status'] === 'concluida') {
            $data['completed_at'] = date('Y-m-d H:i:s');
            $data['completed_by'] = $uid;
        } else {
            $data['completed_at'] = null;
            $data['completed_by'] = null;
        }
        $data['created_by'] = $uid;

        $id = $model->create($data);
        (new ActivityLog())->record('task_created', $uid, 'task', $id);

        flash('success', 'Tarefa cadastrada com sucesso.');
        $this->redirect('/tasks/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.view');

        $task  = $this->findOr404($params['id'] ?? null);
        $model = new Task();

        $this->view('tasks/show', array_merge($this->lists($model), [
            'title' => $task['title'] ?? 'Tarefa',
            'task'  => $task,
            'model' => $model,
        ]));
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.edit');

        $task = $this->findOr404($params['id'] ?? null);

        if (!empty($task['archived_at'])) {
            flash('error', 'Esta tarefa está arquivada. Restaure-a antes de editar.');
            $this->redirect('/tasks/' . (int) $task['id']);
            return;
        }

        $this->renderForm('tasks/edit', 'Editar tarefa', $task, [], $task);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.edit');
        csrf_verify();

        $task = $this->findOr404($params['id'] ?? null);
        $id   = (int) $task['id'];

        if (!empty($task['archived_at'])) {
            flash('error', 'Esta tarefa está arquivada. Restaure-a antes de editar.');
            $this->redirect('/tasks/' . $id);
            return;
        }

        $model = new Task();
        $data  = $this->prefillFromOpportunity($this->collectInput($model));

        $errors = $model->validate($data, 'update');
        $this->checkLinks($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($task, $data);
            $this->renderForm('tasks/edit', 'Editar tarefa', $data, $errors, $merged);
            return;
        }

        $uid           = $_SESSION['user_id'] ?? null;
        $oldStatus     = (string) $task['status'];
        $newStatus     = (string) $data['status'];
        $statusChanged = $oldStatus !== $newStatus;

        if ($newStatus === 'concluida') {
            if (empty($task['completed_at']) || $oldStatus !== 'concluida') {
                $data['completed_at'] = date('Y-m-d H:i:s');
                $data['completed_by'] = $uid;
            }
        } else {
            $data['completed_at'] = null;
            $data['completed_by'] = null;
        }

        $data['updated_by'] = $uid;
        $model->update($id, $data);

        (new ActivityLog())->record('task_updated', $uid, 'task', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('task_status_changed', $uid, 'task', $id);
        }

        flash('success', 'Tarefa atualizada com sucesso.');
        $this->redirect('/tasks/' . $id);
    }

    public function complete(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.complete');
        csrf_verify();

        $task = $this->findOr404($params['id'] ?? null);
        $id   = (int) $task['id'];
        $uid  = $_SESSION['user_id'] ?? null;

        if (!empty($task['archived_at'])) {
            flash('error', 'Restaure a tarefa antes de concluí-la.');
            $this->redirect('/tasks/' . $id);
            return;
        }

        $result = (string) input('result', '');
        (new Task())->complete($id, $result, $uid);
        (new ActivityLog())->record('task_completed', $uid, 'task', $id);

        flash('success', 'Tarefa concluída.');
        $this->redirect('/tasks/' . $id);
    }

    public function reopen(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.complete');
        csrf_verify();

        $task = $this->findOr404($params['id'] ?? null);
        $id   = (int) $task['id'];

        (new Task())->reopen($id);
        (new ActivityLog())->record('task_reopened', $_SESSION['user_id'] ?? null, 'task', $id);

        flash('success', 'Tarefa reaberta.');
        $this->redirect('/tasks/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.edit');
        csrf_verify();

        $task = $this->findOr404($params['id'] ?? null);
        $id   = (int) $task['id'];

        if (empty($task['archived_at'])) {
            (new Task())->archive($id);
            (new ActivityLog())->record('task_archived', $_SESSION['user_id'] ?? null, 'task', $id);
            flash('success', 'Tarefa arquivada.');
        }

        $this->redirect('/tasks/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('tasks.edit');
        csrf_verify();

        $task = $this->findOr404($params['id'] ?? null);
        $id   = (int) $task['id'];

        if (!empty($task['archived_at'])) {
            (new Task())->restore($id);
            (new ActivityLog())->record('task_restored', $_SESSION['user_id'] ?? null, 'task', $id);
            flash('success', 'Tarefa restaurada.');
        }

        $this->redirect('/tasks/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function collectFilters(): array
    {
        return [
            'q'                => (string) input('q', ''),
            'type'             => (string) input('type', ''),
            'company_id'       => (int) input('company_id', 0),
            'contact_id'       => (int) input('contact_id', 0),
            'opportunity_id'   => (int) input('opportunity_id', 0),
            'assigned_user_id' => (int) input('assigned_user_id', 0),
            'priority'         => (string) input('priority', ''),
            'status'           => (string) input('status', ''),
            'overdue'          => input('overdue') !== null ? 1 : 0,
            'today'            => input('today') !== null ? 1 : 0,
            'week'             => input('week') !== null ? 1 : 0,
            'mine'             => input('mine') !== null ? 1 : 0,
            'current_user_id'  => (int) ($_SESSION['user_id'] ?? 0),
            'show_archived'    => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lists(Task $model): array
    {
        return [
            'types'      => $model->getTypes(),
            'priorities' => $model->getPriorities(),
            'statuses'   => $model->getStatuses(),
            'companies'  => (new Company())->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(Task $model): array
    {
        $companyId     = (int) input('company_id', 0);
        $contactId     = (int) input('contact_id', 0);
        $opportunityId = (int) input('opportunity_id', 0);
        $ownerId       = (int) input('assigned_user_id', 0);

        return [
            'title'            => clean((string) input('title', '')),
            'description'      => trim((string) input('description', '')) ?: null,
            'type'             => clean((string) input('type', 'follow_up')),
            'company_id'       => $companyId > 0 ? $companyId : null,
            'contact_id'       => $contactId > 0 ? $contactId : null,
            'opportunity_id'   => $opportunityId > 0 ? $opportunityId : null,
            'assigned_user_id' => $ownerId > 0 ? $ownerId : null,
            'due_date'         => $model->normalizeDueDate((string) input('due_date', '')),
            'due_time'         => $model->normalizeDueTime((string) input('due_time', '')),
            'priority'         => clean((string) input('priority', 'normal')),
            'status'           => clean((string) input('status', 'pendente')),
            'result'           => trim((string) input('result', '')) ?: null,
        ];
    }

    /**
     * Auto-preenche empresa/contato a partir da oportunidade vinculada.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prefillFromOpportunity(array $data): array
    {
        $opportunityId = (int) ($data['opportunity_id'] ?? 0);
        if ($opportunityId <= 0) {
            return $data;
        }

        $opportunity = (new Opportunity())->findById($opportunityId);
        if ($opportunity === null) {
            return $data;
        }

        if (empty($data['company_id']) && !empty($opportunity['company_id'])) {
            $data['company_id'] = (int) $opportunity['company_id'];
        }
        if (empty($data['contact_id']) && !empty($opportunity['contact_id'])) {
            $data['contact_id'] = (int) $opportunity['contact_id'];
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function checkLinks(array $data, array &$errors): void
    {
        $companyId     = (int) ($data['company_id'] ?? 0);
        $contactId     = (int) ($data['contact_id'] ?? 0);
        $opportunityId = (int) ($data['opportunity_id'] ?? 0);
        $ownerId       = (int) ($data['assigned_user_id'] ?? 0);

        $company = null;
        if ($companyId > 0) {
            $company = (new Company())->findById($companyId);
            if ($company === null) {
                $errors['company_id'] = 'Empresa vinculada inexistente.';
            }
        }

        $contact = null;
        if ($contactId > 0) {
            $contact = (new Contact())->findById($contactId);
            if ($contact === null) {
                $errors['contact_id'] = 'Contato vinculado inexistente.';
            } elseif ($companyId > 0 && !isset($errors['company_id'])
                && (int) $contact['company_id'] !== $companyId) {
                $errors['contact_id'] = 'O contato selecionado não pertence à empresa vinculada.';
            }
        }

        if ($opportunityId > 0) {
            $opportunity = (new Opportunity())->findById($opportunityId);
            if ($opportunity === null) {
                $errors['opportunity_id'] = 'Oportunidade vinculada inexistente.';
            } else {
                if ($companyId > 0 && !isset($errors['company_id'])
                    && (int) $opportunity['company_id'] !== $companyId) {
                    $errors['opportunity_id'] = 'A oportunidade não pertence à empresa selecionada.';
                }
                if ($contactId > 0 && !isset($errors['contact_id']) && !empty($opportunity['contact_id'])
                    && (int) $opportunity['contact_id'] !== $contactId
                    && $contact !== null && (int) $contact['company_id'] !== (int) $opportunity['company_id']) {
                    $errors['contact_id'] = 'O contato não está relacionado à oportunidade selecionada.';
                }
            }
        }

        if ($ownerId > 0 && (new User())->find($ownerId) === null) {
            $errors['assigned_user_id'] = 'Responsável interno inválido.';
        }
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $task
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $task = []): void
    {
        $model = new Task();

        $companyId = (int) ($old['company_id'] ?? ($task['company_id'] ?? 0));
        $companyContacts = $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [];
        $opportunities   = $companyId > 0 ? (new Opportunity())->findByCompany($companyId, 200) : [];

        $this->view($view, array_merge($this->lists($model), [
            'title'           => $title,
            'old'             => $old,
            'errors'          => $errors,
            'task'            => $task,
            'owners'          => (new User())->activeList(),
            'companyContacts' => $companyContacts,
            'companyOpps'     => $opportunities,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $task = is_numeric($id) ? (new Task())->findById((int) $id) : null;
        if ($task === null) {
            $this->abort(404, 'Tarefa não encontrada.');
        }

        return $task;
    }
}
