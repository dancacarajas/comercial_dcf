<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Task;
use App\Models\User;

/**
 * Módulo Leads do Site (Etapa 9) — CRUD, triagem e conversão.
 */
final class LeadController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('leads.view');
        $model   = new Lead();
        $filters = $this->collectFilters();
        $page    = max(1, (int) input('page', 1));
        $total   = $model->count($filters);
        $pages   = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page    = min($page, $pages);

        $this->view('leads/index', [
            'title'    => 'Leads do site',
            'items'    => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'  => $filters,
            'statuses' => $model->getStatuses(),
            'origins'  => $model->originOptions(),
            'owners'   => (new User())->activeList(),
            'page'     => $page,
            'pages'    => $pages,
            'total'    => $total,
            'perPage'  => self::PER_PAGE,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('leads.create');
        $this->renderForm('leads/create', 'Novo lead', ['status' => 'novo'], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('leads.create');
        csrf_verify();

        $model = new Lead();
        $data  = $this->collectInput($model);
        $errors = $model->validate($data, 'create');

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('leads/create', 'Novo lead', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);
        (new ActivityLog())->record('lead_created_manual', $_SESSION['user_id'] ?? null, 'lead', $id);

        flash('success', 'Lead cadastrado com sucesso.');
        $this->redirect('/leads/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('leads.view');
        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByLead($id, 6);
            $documentSummary = $documentModel->summaryByLead($id);
        }

        $this->view('leads/show', [
            'title'    => $lead['name'] ?? 'Lead',
            'lead'     => $lead,
            'statuses' => (new Lead())->getStatuses(),
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('leads.edit');
        $lead = $this->findOr404($params['id'] ?? null);
        if (!empty($lead['archived_at'])) {
            flash('error', 'Lead arquivado. Restaure antes de editar.');
            $this->redirect('/leads/' . (int) $lead['id']);
            return;
        }
        $this->renderForm('leads/edit', 'Editar lead', $lead, [], $lead);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('leads.edit');
        csrf_verify();

        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        if (!empty($lead['archived_at'])) {
            flash('error', 'Lead arquivado. Restaure antes de editar.');
            $this->redirect('/leads/' . $id);
            return;
        }

        $model  = new Lead();
        $data   = $this->collectInput($model);
        $errors = $model->validate($data, 'update');

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('leads/edit', 'Editar lead', array_merge($lead, $data), $errors, $lead);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);
        (new ActivityLog())->record('lead_updated', $_SESSION['user_id'] ?? null, 'lead', $id);

        flash('success', 'Lead atualizado.');
        $this->redirect('/leads/' . $id);
    }

    public function convertForm(array $params): void
    {
        AuthMiddleware::requirePermission('leads.convert');
        $lead = $this->findOr404($params['id'] ?? null);

        $this->view('leads/convert', [
            'title'         => 'Converter lead',
            'lead'          => $lead,
            'companies'     => (new Company())->options(),
            'companyContacts' => !empty($lead['company_id'])
                ? (new Contact())->findByCompany((int) $lead['company_id'], 200) : [],
            'owners'        => (new User())->activeList(),
        ]);
    }

    public function convert(array $params): void
    {
        AuthMiddleware::requirePermission('leads.convert');
        csrf_verify();

        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        $uid  = (int) ($_SESSION['user_id'] ?? 0);

        $updates = ['updated_by' => $uid];
        $logs    = [];
        $didCompany = false;
        $didContact = false;
        $didOpp     = false;
        $didTask    = false;

        // Empresa
        if (input('do_company') !== null) {
            $existingCo = (int) input('existing_company_id', 0);
            if ($existingCo > 0) {
                $updates['company_id'] = $existingCo;
            } elseif (input('create_company') !== null) {
                $coId = (new Company())->create([
                    'name'     => clean((string) input('company_name', $lead['company_name'] ?? $lead['name'] ?? '')),
                    'status'   => 'prospect',
                    'priority' => 'B',
                    'source'   => 'site',
                ]);
                $updates['company_id'] = (int) $coId;
                $logs[] = 'lead_converted_company';
            }
            $didCompany = !empty($updates['company_id']);
        }

        $companyId = (int) ($updates['company_id'] ?? $lead['company_id'] ?? 0);

        // Contato
        if (input('do_contact') !== null && $companyId > 0) {
            $existingCt = (int) input('existing_contact_id', 0);
            if ($existingCt > 0) {
                $updates['contact_id'] = $existingCt;
            } elseif (input('create_contact') !== null) {
                $ctId = (new Contact())->create([
                    'company_id'      => $companyId,
                    'name'            => clean((string) input('contact_name', $lead['name'] ?? '')),
                    'email'           => $lead['email'] ?? null,
                    'whatsapp'        => $lead['whatsapp'] ?? null,
                    'position_title'  => $lead['role_title'] ?? null,
                    'status'          => 'ativo',
                ]);
                $updates['contact_id'] = (int) $ctId;
                $logs[] = 'lead_converted_contact';
            }
            $didContact = !empty($updates['contact_id']);
        }

        $contactId = (int) ($updates['contact_id'] ?? $lead['contact_id'] ?? 0);

        // Oportunidade
        if (input('do_opportunity') !== null && $companyId > 0) {
            $existingOp = (int) input('existing_opportunity_id', 0);
            if ($existingOp > 0) {
                $updates['opportunity_id'] = $existingOp;
            } elseif (input('create_opportunity') !== null) {
                $title = clean((string) input('opportunity_title', 'Oportunidade — ' . ($lead['company_name'] ?? $lead['name'] ?? 'Lead')));
                $opId = (new Opportunity())->create([
                    'company_id'      => $companyId,
                    'contact_id'      => $contactId > 0 ? $contactId : null,
                    'title'           => $title,
                    'status'          => 'prospect_identificado',
                    'source'          => 'site',
                    'estimated_value' => null,
                    'opened_at'       => date('Y-m-d'),
                    'created_by'      => $uid,
                ]);
                $updates['opportunity_id'] = (int) $opId;
                $logs[] = 'lead_converted_opportunity';
            }
            $didOpp = !empty($updates['opportunity_id']);
        }

        $opportunityId = (int) ($updates['opportunity_id'] ?? $lead['opportunity_id'] ?? 0);

        // Tarefa
        if (input('do_task') !== null) {
            if (input('create_task') !== null) {
                $taskId = (new Task())->create([
                    'title'            => clean((string) input('task_title', 'Follow-up lead — ' . ($lead['name'] ?? ''))),
                    'type'             => 'follow_up',
                    'company_id'       => $companyId > 0 ? $companyId : null,
                    'contact_id'       => $contactId > 0 ? $contactId : null,
                    'opportunity_id'   => $opportunityId > 0 ? $opportunityId : null,
                    'assigned_user_id' => (int) input('task_assigned_user_id', $uid) ?: null,
                    'priority'         => 'alta',
                    'status'           => 'pendente',
                    'description'      => $lead['message'] ?? null,
                    'created_by'       => $uid,
                ]);
                $updates['task_id'] = (int) $taskId;
                $logs[] = 'lead_converted_task';
            }
            $didTask = !empty($updates['task_id']);
        }

        // Status final
        if ($didCompany && $didContact && $didOpp && $didTask) {
            $updates['status'] = 'convertido_completo';
            $logs[] = 'lead_converted_complete';
        } elseif ($didTask) {
            $updates['status'] = 'convertido_tarefa';
        } elseif ($didOpp) {
            $updates['status'] = 'convertido_oportunidade';
        } elseif ($didContact) {
            $updates['status'] = 'convertido_contato';
        } elseif ($didCompany) {
            $updates['status'] = 'convertido_empresa';
        }

        if ($didCompany || $didContact || $didOpp || $didTask) {
            $updates['converted_at'] = date('Y-m-d H:i:s');
            $updates['converted_by'] = $uid;
        }

        (new Lead())->update($id, $updates);
        $activity = new ActivityLog();
        foreach (array_unique($logs) as $action) {
            $activity->record($action, $uid, 'lead', $id);
        }

        flash('success', 'Conversão registrada com sucesso.');
        $this->redirect('/leads/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('leads.archive');
        csrf_verify();
        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        if (empty($lead['archived_at'])) {
            (new Lead())->archive($id);
            (new ActivityLog())->record('lead_archived', $_SESSION['user_id'] ?? null, 'lead', $id);
        }
        flash('success', 'Lead arquivado.');
        $this->redirect('/leads/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('leads.edit');
        csrf_verify();
        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        if (!empty($lead['archived_at'])) {
            (new Lead())->restore($id);
            (new ActivityLog())->record('lead_restored', $_SESSION['user_id'] ?? null, 'lead', $id);
        }
        flash('success', 'Lead restaurado.');
        $this->redirect('/leads/' . $id);
    }

    public function markDuplicate(array $params): void
    {
        AuthMiddleware::requirePermission('leads.edit');
        csrf_verify();
        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        (new Lead())->markDuplicate($id);
        (new ActivityLog())->record('lead_marked_duplicate', $_SESSION['user_id'] ?? null, 'lead', $id);
        flash('success', 'Lead marcado como duplicado.');
        $this->redirect('/leads/' . $id);
    }

    public function discard(array $params): void
    {
        AuthMiddleware::requirePermission('leads.edit');
        csrf_verify();
        $lead = $this->findOr404($params['id'] ?? null);
        $id   = (int) $lead['id'];
        (new Lead())->discard($id);
        (new ActivityLog())->record('lead_discarded', $_SESSION['user_id'] ?? null, 'lead', $id);
        flash('success', 'Lead descartado.');
        $this->redirect('/leads/' . $id);
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'q'                => (string) input('q', ''),
            'status'           => (string) input('status', ''),
            'origin_page'      => (string) input('origin_page', ''),
            'interest'         => (string) input('interest', ''),
            'assigned_user_id' => (int) input('assigned_user_id', 0),
            'contact_consent'  => input('contact_consent') !== null && input('contact_consent') !== ''
                ? (int) input('contact_consent') : '',
            'converted'        => input('converted') !== null ? 1 : 0,
            'not_converted'    => input('not_converted') !== null ? 1 : 0,
            'show_archived'    => input('show_archived') !== null ? 1 : 0,
            'date_from'        => (string) input('date_from', ''),
            'date_to'          => (string) input('date_to', ''),
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(Lead $model): array
    {
        $owner = (int) input('assigned_user_id', 0);

        return [
            'name'            => clean((string) input('name', '')),
            'company_name'    => clean((string) input('company_name', '')) ?: null,
            'role_title'      => clean((string) input('role_title', '')) ?: null,
            'email'           => strtolower(trim((string) input('email', ''))) ?: null,
            'whatsapp'        => $model->normalizeWhatsapp((string) input('whatsapp', '')),
            'city'            => clean((string) input('city', '')) ?: null,
            'state'           => strtoupper(substr(clean((string) input('state', '')), 0, 2)) ?: null,
            'segment'         => clean((string) input('segment', '')) ?: null,
            'origin_page'     => clean((string) input('origin_page', '')) ?: null,
            'interest'        => clean((string) input('interest', '')) ?: null,
            'message'         => trim((string) input('message', '')) ?: null,
            'contact_consent' => input('contact_consent') !== null ? 1 : 0,
            'status'          => clean((string) input('status', 'novo')),
            'assigned_user_id'=> $owner > 0 ? $owner : null,
        ];
    }

    /** @param array<string, mixed> $old @param array<string, string> $errors */
    private function renderForm(string $view, string $title, array $old, array $errors, array $lead = []): void
    {
        $model = new Lead();
        $this->view($view, [
            'title'    => $title,
            'old'      => $old,
            'errors'   => $errors,
            'lead'     => $lead,
            'statuses' => $model->getStatuses(),
            'owners'   => (new User())->activeList(),
        ]);
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $lead = is_numeric($id) ? (new Lead())->findById((int) $id) : null;
        if ($lead === null) {
            $this->abort(404, 'Lead não encontrado.');
        }

        return $lead;
    }
}
