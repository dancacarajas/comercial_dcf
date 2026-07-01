<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\FinancialEntry;
use App\Models\IncentiveProject;
use App\Models\SponsorDossier;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Quota;
use App\Models\Sponsor;
use App\Models\Contract;
use App\Models\Counterpart;
use App\Models\Task;
use App\Models\User;

/**
 * Módulo Oportunidades / CRM de Captação (Etapa 6).
 *
 * Núcleo do funil: empresa + contato + status + valor + probabilidade +
 * próxima ação. Sem exclusão física (arquivamento lógico).
 * Permissões: opportunities.view/create/edit (edit cobre status/archive/restore).
 */
final class OpportunityController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('opportunities.view');

        $model   = new Opportunity();
        $filters = $this->collectFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $items = $model->paginate($filters, $page, self::PER_PAGE);

        $this->view('opportunities/index', array_merge($this->lists($model), [
            'title'   => 'Oportunidades',
            'items'   => $items,
            'filters' => $filters,
            'owners'  => (new User())->activeList(),
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
            'perPage' => self::PER_PAGE,
        ]));
    }

    public function pipeline(): void
    {
        AuthMiddleware::requirePermission('opportunities.view');

        $model   = new Opportunity();
        $filters = $this->collectFilters();

        $this->view('opportunities/pipeline', array_merge($this->lists($model), [
            'title'   => 'Pipeline',
            'summary' => $model->pipelineSummary($filters),
            'columns' => $model->pipelineItems($filters, 10),
            'filters' => $filters,
        ]));
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('opportunities.create');

        $old = [];
        $companyId = (int) input('company_id', 0);
        $contactId = (int) input('contact_id', 0);
        if ($companyId > 0) { $old['company_id'] = $companyId; }
        if ($contactId > 0) { $old['contact_id'] = $contactId; }

        $this->renderForm('opportunities/create', 'Nova oportunidade', $old, []);
    }

    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.create');

        $companyId = (int) ($params['id'] ?? 0);
        if ($companyId <= 0 || (new Company())->findById($companyId) === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }

        $this->renderForm('opportunities/create', 'Nova oportunidade', ['company_id' => $companyId], []);
    }

    public function createForContact(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.create');

        $contactId = (int) ($params['id'] ?? 0);
        $contact   = $contactId > 0 ? (new Contact())->findById($contactId) : null;
        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }

        $this->renderForm('opportunities/create', 'Nova oportunidade', [
            'company_id' => (int) $contact['company_id'],
            'contact_id' => $contactId,
        ], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('opportunities.create');
        csrf_verify();

        $model = new Opportunity();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'create');
        // Etapa 19: toda oportunidade precisa pertencer a um projeto incentivado.
        if (empty($data['incentive_project_id'])) {
            $errors['incentive_project_id'] = 'Selecione o projeto incentivado da oportunidade.';
        }
        $this->checkCompany($data, $errors);
        $this->checkContact($data, $errors);
        $this->checkOwner($data, $errors);
        // Etapa 18C Fase 2: origem "captador" só pelo fluxo oficial de conversão de atribuição.
        if ((string) ($data['source'] ?? '') === 'captador') {
            $errors['source'] = 'Para origem captador, use a ação Converter atribuição em oportunidade.';
        }
        $warnings = $this->applyQuotaRules($model, $data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('opportunities/create', 'Nova oportunidade', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('opportunity_created', $_SESSION['user_id'] ?? null, 'opportunity', $id);
        if (($data['quota_id'] ?? null) !== null) {
            (new ActivityLog())->record('opportunity_quota_linked', $_SESSION['user_id'] ?? null, 'opportunity', $id);
        }

        flash('success', 'Oportunidade cadastrada com sucesso.');
        if ($warnings !== []) { flash('info', implode(' ', $warnings)); }
        $this->redirect('/opportunities/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.view');

        $opportunity = $this->findOr404($params['id'] ?? null);
        $model       = new Opportunity();
        $id          = (int) $opportunity['id'];

        // Bloco "Tarefas da oportunidade" (somente para quem tem tasks.view).
        $tasks       = [];
        $taskSummary = ['open' => 0, 'overdue' => 0];
        $taskModel   = null;
        if (can('tasks.view')) {
            $taskModel   = new Task();
            $tasks       = $taskModel->findByOpportunity($id, 6);
            $taskSummary = $taskModel->summaryByOpportunity($id);
        }

        $proposals       = [];
        $proposalSummary = ['total' => 0, 'sent' => 0, 'open' => 0, 'total_value' => 0.0];
        $proposalModel   = null;
        if (can('proposals.view')) {
            $proposalModel   = new Proposal();
            $proposals       = $proposalModel->findByOpportunity($id, 6);
            $proposalSummary = $proposalModel->summaryByOpportunity($id);
        }

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByOpportunity($id, 6);
            $documentSummary = $documentModel->summaryByOpportunity($id);
        }

        $sponsors       = [];
        $sponsorSummary = ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
        $sponsorModel   = null;
        if (can('sponsors.view')) {
            $sponsorModel   = new Sponsor();
            $sponsors       = $sponsorModel->findByOpportunity($id, 6);
            $sponsorSummary = $sponsorModel->summaryByOpportunity($id);
        }

        $counterparts        = [];
        $counterpartSummary  = ['total' => 0, 'delivered' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0];
        $counterpartModel    = null;
        if (can('counterparts.view')) {
            $counterpartModel   = new Counterpart();
            $counterparts       = $counterpartModel->findByOpportunity($id, 6);
            $counterpartSummary = $counterpartModel->summaryByOpportunity($id);
        }

        $contracts       = [];
        $contractSummary = ['total' => 0, 'signed' => 0, 'awaiting_signature' => 0, 'vigente' => 0, 'expired' => 0, 'formalized_total' => 0.0];
        $contractModel   = null;
        if (can('contracts.view')) {
            $contractModel   = new Contract();
            $contracts       = $contractModel->findByOpportunity($id, 6);
            $contractSummary = $contractModel->summaryByOpportunity($id);
        }

        $financials       = [];
        $financialSummary = ['total' => 0, 'planned_total' => 0.0, 'received_total' => 0.0, 'remaining_total' => 0.0, 'received' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0, 'reconciled' => 0];
        $financialModel   = null;
        if (can('financials.view')) {
            $financialModel   = new FinancialEntry();
            $financials       = $financialModel->findByOpportunity($id, 6);
            $financialSummary = $financialModel->summaryByOpportunity($id);
        }

        $dossiers       = [];
        $dossierSummary = ['total' => 0, 'approved' => 0, 'delivered' => 0, 'pending' => 0, 'with_pending_counterparts' => 0, 'with_overdue_counterparts' => 0, 'with_balance' => 0];
        $dossierModel   = null;
        if (can('dossiers.view')) {
            $dossierModel   = new SponsorDossier();
            $dossiers       = $dossierModel->findByOpportunity($id, 6);
            $dossierSummary = $dossierModel->summaryByOpportunity($id);
        }

        $this->view('opportunities/show', array_merge($this->lists($model), [
            'title'       => $opportunity['title'] ?? 'Oportunidade',
            'opportunity' => $opportunity,
            'tasks'       => $tasks,
            'taskSummary' => $taskSummary,
            'taskModel'   => $taskModel,
            'proposals'       => $proposals,
            'proposalSummary' => $proposalSummary,
            'proposalModel'   => $proposalModel,
            'documents'       => $documents,
            'documentSummary' => $documentSummary,
            'documentModel'   => $documentModel,
            'sponsors'        => $sponsors,
            'sponsorSummary'  => $sponsorSummary,
            'sponsorModel'    => $sponsorModel,
            'counterparts'       => $counterparts,
            'counterpartSummary' => $counterpartSummary,
            'counterpartModel'   => $counterpartModel,
            'contracts'          => $contracts,
            'contractSummary'    => $contractSummary,
            'contractModel'      => $contractModel,
            'financials'         => $financials,
            'financialSummary'   => $financialSummary,
            'financialModel'     => $financialModel,
            'dossiers'           => $dossiers,
            'dossierSummary'     => $dossierSummary,
            'dossierModel'       => $dossierModel,
            'collectorTrace'     => can('collector_deals.view')
                ? (new \App\Models\CollectorDeal())->findByFunnelEntity('opportunity', $id)
                : null,
        ]));
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.edit');

        $opportunity = $this->findOr404($params['id'] ?? null);

        if (!empty($opportunity['archived_at'])) {
            flash('error', 'Esta oportunidade está arquivada. Restaure-a antes de editar.');
            $this->redirect('/opportunities/' . (int) $opportunity['id']);
            return;
        }

        $this->renderForm('opportunities/edit', 'Editar oportunidade', $opportunity, [], $opportunity);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.edit');
        csrf_verify();

        $opportunity = $this->findOr404($params['id'] ?? null);
        $id          = (int) $opportunity['id'];

        if (!empty($opportunity['archived_at'])) {
            flash('error', 'Esta oportunidade está arquivada. Restaure-a antes de editar.');
            $this->redirect('/opportunities/' . $id);
            return;
        }

        $model = new Opportunity();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'update');
        // Etapa 19: toda oportunidade precisa manter um projeto incentivado.
        if (empty($data['incentive_project_id'])) {
            $errors['incentive_project_id'] = 'Selecione o projeto incentivado da oportunidade.';
        }
        $this->checkCompany($data, $errors);
        $this->checkContact($data, $errors);
        $this->checkOwner($data, $errors);
        // Etapa 18C Fase 2: origem "captador" exige rastreabilidade (collector_deal já vinculado).
        if ((string) ($data['source'] ?? '') === 'captador'
            && !(new \App\Models\CollectorDeal())->hasDealForFunnelEntity('opportunity', $id)) {
            $errors['source'] = 'Para origem captador, use a ação Converter atribuição em oportunidade.';
        }
        $warnings = $this->applyQuotaRules($model, $data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($opportunity, $data);
            $this->renderForm('opportunities/edit', 'Editar oportunidade', $data, $errors, $merged);
            return;
        }

        $statusChanged = (string) $opportunity['status'] !== (string) $data['status'];
        if ($statusChanged && ($data['last_interaction_at'] ?? null) === null) {
            $data['last_interaction_at'] = date('Y-m-d H:i:s');
        }

        $quotaChanged = (int) ($opportunity['quota_id'] ?? 0) !== (int) ($data['quota_id'] ?? 0);

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('opportunity_updated', $_SESSION['user_id'] ?? null, 'opportunity', $id);
        if ($statusChanged) {
            (new ActivityLog())->record('opportunity_status_changed', $_SESSION['user_id'] ?? null, 'opportunity', $id);
        }
        if ($quotaChanged && ($data['quota_id'] ?? null) !== null) {
            (new ActivityLog())->record('opportunity_quota_linked', $_SESSION['user_id'] ?? null, 'opportunity', $id);
        }

        flash('success', 'Oportunidade atualizada com sucesso.');
        if ($warnings !== []) { flash('info', implode(' ', $warnings)); }
        $this->redirect('/opportunities/' . $id);
    }

    public function status(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.edit');
        csrf_verify();

        $opportunity = $this->findOr404($params['id'] ?? null);
        $id          = (int) $opportunity['id'];
        $model       = new Opportunity();

        $status = clean((string) input('status', (string) $opportunity['status']));
        if (!array_key_exists($status, $model->getStatusLabels())) {
            flash('error', 'Status do funil inválido.');
            $this->redirect('/opportunities/' . $id);
            return;
        }

        $probRaw = (string) input('probability', '');
        $prob    = $probRaw === '' ? $model->suggestedProbability($status) : (int) $probRaw;
        if ($status === 'fechado') { $prob = 100; }
        if ($status === 'perdido') { $prob = 0; }
        $prob = max(0, min(100, $prob));

        $lost = clean((string) input('lost_reason', ''));
        if ($status === 'perdido' && ($lost === '' || !in_array($lost, $model->getLostReasons(), true))) {
            flash('error', 'Para marcar como Perdido, informe um motivo de perda válido.');
            $this->redirect('/opportunities/' . $id);
            return;
        }

        $data = [
            'status'              => $status,
            'probability'         => $prob,
            'next_action_at'      => $this->normalizeDateTime((string) input('next_action_at', '')),
            'lost_reason'         => $status === 'perdido' ? $lost : ($lost !== '' ? $lost : null),
            'last_interaction_at' => date('Y-m-d H:i:s'),
            'updated_by'          => $_SESSION['user_id'] ?? null,
        ];

        $extraNote = trim((string) input('notes', ''));
        if ($extraNote !== '') {
            $prev          = trim((string) ($opportunity['notes'] ?? ''));
            $stamp         = '[' . date('Y-m-d H:i') . '] ' . $extraNote;
            $data['notes'] = $prev === '' ? $stamp : ($prev . "\n" . $stamp);
        }

        $model->updateStatus($id, $data);

        (new ActivityLog())->record('opportunity_status_changed', $_SESSION['user_id'] ?? null, 'opportunity', $id);

        flash('success', 'Status atualizado para "' . $model->getStatusLabels()[$status] . '".');
        $this->redirect('/opportunities/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.edit');
        csrf_verify();

        $opportunity = $this->findOr404($params['id'] ?? null);
        $id          = (int) $opportunity['id'];

        if (empty($opportunity['archived_at'])) {
            (new Opportunity())->archive($id);
            (new ActivityLog())->record('opportunity_archived', $_SESSION['user_id'] ?? null, 'opportunity', $id);
            flash('success', 'Oportunidade arquivada.');
        }

        $this->redirect('/opportunities/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('opportunities.edit');
        csrf_verify();

        $opportunity = $this->findOr404($params['id'] ?? null);
        $id          = (int) $opportunity['id'];

        if (!empty($opportunity['archived_at'])) {
            (new Opportunity())->restore($id);
            (new ActivityLog())->record('opportunity_restored', $_SESSION['user_id'] ?? null, 'opportunity', $id);
            flash('success', 'Oportunidade restaurada.');
        }

        $this->redirect('/opportunities/' . $id);
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
            'q'              => (string) input('q', ''),
            'company_id'     => (int) input('company_id', 0),
            'contact_id'     => (int) input('contact_id', 0),
            'status'         => (string) input('status', ''),
            'prob_min'       => input('prob_min') !== null ? (string) input('prob_min') : '',
            'prob_max'       => input('prob_max') !== null ? (string) input('prob_max') : '',
            'quota_interest' => (string) input('quota_interest', ''),
            'quota_id'       => (int) input('quota_id', 0),
            'source'         => (string) input('source', ''),
            'urgency_level'  => (string) input('urgency_level', ''),
            'owner'          => (int) input('owner', 0),
            'overdue'        => input('overdue') !== null ? 1 : 0,
            'open'           => input('open') !== null ? 1 : 0,
            'closed'         => input('closed') !== null ? 1 : 0,
            'lost'           => input('lost') !== null ? 1 : 0,
            'show_archived'  => input('show_archived') !== null ? 1 : 0,
        ];
    }

    /**
     * Dados das listas controladas usados nas views.
     *
     * @return array<string, mixed>
     */
    private function lists(Opportunity $model, int|string|null $projectId = null): array
    {
        return [
            'statusLabels'        => $model->getStatusLabels(),
            'statusProbabilities' => $model->getStatusProbabilities(),
            'quotaInterests'      => $model->getQuotaInterests(),
            'sources'             => $model->getSources(),
            'urgencyLevels'       => $model->getUrgencyLevels(),
            'lostReasons'         => $model->getLostReasons(),
            'companies'           => (new Company())->options(),
            'quotas'              => (new Quota())->activeOptions($projectId),
            'projects'            => (new IncentiveProject())->options(true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(Opportunity $model): array
    {
        $status = clean((string) input('status', 'prospect_identificado'));

        $probRaw = (string) input('probability', '');
        $prob    = $probRaw === '' ? $model->suggestedProbability($status) : (int) $probRaw;
        if ($status === 'fechado') { $prob = 100; }
        if ($status === 'perdido') { $prob = 0; }

        $openedAt = $this->normalizeDateTime((string) input('opened_at', ''));
        if ($openedAt === null) {
            $openedAt = date('Y-m-d H:i:s');
        }

        $contactId = (int) input('contact_id', 0);
        $ownerId   = (int) input('owner_user_id', 0);
        $quotaId   = (int) input('quota_id', 0);
        $projectId = (int) input('incentive_project_id', 0);
        if ($projectId <= 0) {
            $capture = (new IncentiveProject())->options(true);
            if (count($capture) === 1) {
                $projectId = (int) $capture[0]['id'];
            }
        }

        return [
            'incentive_project_id' => $projectId > 0 ? $projectId : null,
            'company_id'           => (int) input('company_id', 0),
            'contact_id'           => $contactId > 0 ? $contactId : null,
            'title'                => clean((string) input('title', '')),
            'quota_interest'       => clean((string) input('quota_interest', '')) ?: null,
            'quota_id'             => $quotaId > 0 ? $quotaId : null,
            'quota_reserved_until' => $model->normalizeQuotaReservedUntil((string) input('quota_reserved_until', '')),
            'estimated_value'     => $model->normalizeMoney((string) input('estimated_value', '')),
            'probability'         => $prob,
            'status'              => $status,
            'source'              => clean((string) input('source', '')) ?: null,
            'owner_user_id'       => $ownerId > 0 ? $ownerId : null,
            'opened_at'           => $openedAt,
            'last_interaction_at' => $this->normalizeDateTime((string) input('last_interaction_at', '')),
            'next_action_at'      => $this->normalizeDateTime((string) input('next_action_at', '')),
            'urgency_level'       => clean((string) input('urgency_level', 'normal')),
            'lost_reason'         => clean((string) input('lost_reason', '')) ?: null,
            'notes'               => trim((string) input('notes', '')),
        ];
    }

    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        $ts    = strtotime($value);

        return $ts === false ? $value : date('Y-m-d H:i:s', $ts);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function checkCompany(array $data, array &$errors): void
    {
        if (isset($errors['company_id'])) {
            return;
        }
        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId <= 0 || (new Company())->findById($companyId) === null) {
            $errors['company_id'] = 'Empresa vinculada inválida ou inexistente.';
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function checkContact(array $data, array &$errors): void
    {
        $contactId = $data['contact_id'] ?? null;
        if ($contactId === null) {
            return;
        }

        $contact = (new Contact())->findById((int) $contactId);
        if ($contact === null) {
            $errors['contact_id'] = 'Contato principal inexistente.';
            return;
        }

        if ((int) $contact['company_id'] !== (int) ($data['company_id'] ?? 0)) {
            $errors['contact_id'] = 'O contato selecionado não pertence à empresa vinculada.';
        }
    }

    /**
     * Valida o vínculo com cota real e aplica regras de auto-preenchimento.
     * Retorna lista de avisos (não bloqueantes) para flash.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     * @return array<int, string>
     */
    private function applyQuotaRules(Opportunity $model, array &$data, array &$errors): array
    {
        $warnings = [];

        $quotaId = $data['quota_id'] ?? null;
        if ($quotaId !== null) {
            $check = $model->validateQuota($quotaId, $data['incentive_project_id'] ?? null);
            if ($check['error'] !== null) {
                $errors['quota_id'] = $check['error'];
            } else {
                if ($check['warning'] !== null) {
                    $warnings[] = $check['warning'];
                }
                // Auto-valor: usa amount da cota apenas se o usuário não informou valor.
                $quota = $check['quota'];
                if ($quota !== null
                    && ($data['estimated_value'] ?? null) === null
                    && $quota['amount'] !== null) {
                    $data['estimated_value'] = (float) $quota['amount'];
                }
            }
        }

        // Reserva de cota sem validade definida: aceitar, mas avisar.
        if (($data['status'] ?? '') === 'reserva_de_cota'
            && $quotaId !== null
            && ($data['quota_reserved_until'] ?? null) === null) {
            $warnings[] = 'Reserva de cota registrada sem data de validade definida.';
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function checkOwner(array $data, array &$errors): void
    {
        $ownerId = $data['owner_user_id'] ?? null;
        if ($ownerId !== null && (new User())->find((int) $ownerId) === null) {
            $errors['owner_user_id'] = 'Responsável interno inválido.';
        }
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $opportunity
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $opportunity = []): void
    {
        $model = new Opportunity();

        // Contatos da empresa selecionada (para o select de contato principal).
        $companyId = (int) ($old['company_id'] ?? ($opportunity['company_id'] ?? 0));
        $projectId = (int) ($old['incentive_project_id'] ?? ($opportunity['incentive_project_id'] ?? 0));
        $companyContacts = $companyId > 0 ? (new Contact())->findByCompany($companyId, 200) : [];

        $this->view($view, array_merge($this->lists($model, $projectId > 0 ? $projectId : null), [
            'title'           => $title,
            'old'             => $old,
            'errors'          => $errors,
            'opportunity'     => $opportunity,
            'owners'          => (new User())->activeList(),
            'companyContacts' => $companyContacts,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $opportunity = is_numeric($id) ? (new Opportunity())->findById((int) $id) : null;
        if ($opportunity === null) {
            $this->abort(404, 'Oportunidade não encontrada.');
        }

        return $opportunity;
    }
}
