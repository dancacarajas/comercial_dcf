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
use App\Models\SponsorDossier;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Sponsor;
use App\Models\Task;
use App\Models\User;

/**
 * Módulo Empresas / Prospects (Etapa 4).
 *
 * Cadastro, listagem, visualização, edição e arquivamento lógico de
 * empresas potenciais patrocinadoras. SEM exclusão física.
 *
 * Permissões: companies.view, companies.create, companies.edit.
 * Arquivar/restaurar exigem companies.edit.
 */
final class CompanyController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('companies.view');

        $model = new Company();

        $filters = [
            'q'                    => (string) input('q', ''),
            'segment'              => (string) input('segment', ''),
            'priority'             => (string) input('priority', ''),
            'status'               => (string) input('status', ''),
            'state'                => (string) input('state', ''),
            'owner'                => (int) input('owner', 0),
            'operates_para'        => input('operates_para') !== null ? 1 : 0,
            'operates_carajas'     => input('operates_carajas') !== null ? 1 : 0,
            'operates_parauapebas' => input('operates_parauapebas') !== null ? 1 : 0,
            'show_archived'        => input('show_archived') !== null ? 1 : 0,
        ];

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $companies = $model->paginate($filters, $page, self::PER_PAGE);

        $this->view('companies/index', [
            'title'      => 'Empresas',
            'companies'  => $companies,
            'filters'    => $filters,
            'segments'   => $model->getSegments(),
            'priorities' => $model->getPriorities(),
            'statuses'   => $model->getStatuses(),
            'states'     => $model->getStates(),
            'owners'     => (new User())->activeList(),
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'perPage'    => self::PER_PAGE,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('companies.create');

        $this->renderForm('companies/create', 'Nova empresa', [], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('companies.create');
        csrf_verify();

        $model = new Company();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'create');
        $this->checkUnique($model, $data, $errors, null);
        $this->checkOwner($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('companies/create', 'Nova empresa', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('company_created', $_SESSION['user_id'] ?? null, 'company', $id);

        flash('success', 'Empresa cadastrada com sucesso.');
        $this->redirect('/companies/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('companies.view');

        $company = $this->findOr404($params['id'] ?? null);
        $model   = new Company();
        $id      = (int) $company['id'];

        // Bloco "Contatos da empresa" (somente para quem tem contacts.view).
        $contacts      = [];
        $contactsCount = 0;
        if (can('contacts.view')) {
            $contactModel  = new Contact();
            $contacts      = $contactModel->findByCompany($id, 10);
            $contactsCount = $contactModel->countByCompany($id);
        }

        // Bloco "Oportunidades da empresa" (somente para quem tem opportunities.view).
        $opportunities      = [];
        $opportunitySummary = ['open' => 0, 'closed' => 0, 'lost' => 0, 'open_value' => 0.0];
        if (can('opportunities.view')) {
            $oppModel           = new Opportunity();
            $opportunities      = $oppModel->findByCompany($id, 10);
            $opportunitySummary = $oppModel->summaryByCompany($id);
            $opportunityLabels  = $oppModel->getStatusLabels();
        }

        // Bloco "Tarefas da empresa" (somente para quem tem tasks.view).
        $tasks        = [];
        $taskSummary  = ['open' => 0, 'overdue' => 0];
        $taskModel    = null;
        if (can('tasks.view')) {
            $taskModel   = new Task();
            $tasks       = $taskModel->findByCompany($id, 6);
            $taskSummary = $taskModel->summaryByCompany($id);
        }

        $proposals        = [];
        $proposalSummary  = ['total' => 0, 'sent' => 0, 'open' => 0, 'total_value' => 0.0];
        $proposalModel    = null;
        if (can('proposals.view')) {
            $proposalModel   = new Proposal();
            $proposals       = $proposalModel->findByCompany($id, 6);
            $proposalSummary = $proposalModel->summaryByCompany($id);
        }

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByCompany($id, 6);
            $documentSummary = $documentModel->summaryByCompany($id);
        }

        $sponsors       = [];
        $sponsorSummary = ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
        $sponsorModel   = null;
        if (can('sponsors.view')) {
            $sponsorModel   = new Sponsor();
            $sponsors       = $sponsorModel->findByCompany($id, 6);
            $sponsorSummary = $sponsorModel->summaryByCompany($id);
        }

        $counterparts        = [];
        $counterpartSummary  = ['total' => 0, 'delivered' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0];
        $counterpartModel    = null;
        if (can('counterparts.view')) {
            $counterpartModel   = new Counterpart();
            $counterparts       = $counterpartModel->findByCompany($id, 6);
            $counterpartSummary = $counterpartModel->summaryByCompany($id);
        }

        $contracts       = [];
        $contractSummary = ['total' => 0, 'signed' => 0, 'awaiting_signature' => 0, 'vigente' => 0, 'expired' => 0, 'formalized_total' => 0.0];
        $contractModel   = null;
        if (can('contracts.view')) {
            $contractModel   = new Contract();
            $contracts       = $contractModel->findByCompany($id, 6);
            $contractSummary = $contractModel->summaryByCompany($id);
        }

        $financials       = [];
        $financialSummary = ['total' => 0, 'planned_total' => 0.0, 'received_total' => 0.0, 'remaining_total' => 0.0, 'received' => 0, 'partial' => 0, 'overdue' => 0, 'pending' => 0, 'reconciled' => 0];
        $financialModel   = null;
        if (can('financials.view')) {
            $financialModel   = new FinancialEntry();
            $financials       = $financialModel->findByCompany($id, 6);
            $financialSummary = $financialModel->summaryByCompany($id);
        }

        $dossiers       = [];
        $dossierSummary = ['total' => 0, 'approved' => 0, 'delivered' => 0, 'pending' => 0, 'with_pending_counterparts' => 0, 'with_overdue_counterparts' => 0, 'with_balance' => 0];
        $dossierModel   = null;
        if (can('dossiers.view')) {
            $dossierModel   = new SponsorDossier();
            $dossiers       = $dossierModel->findByCompany($id, 6);
            $dossierSummary = $dossierModel->summaryByCompany($id);
        }

        $this->view('companies/show', [
            'title'              => $company['name'] ?? 'Empresa',
            'company'            => $company,
            'priorities'         => $model->getPriorities(),
            'statuses'           => $model->getStatuses(),
            'taxRegimes'         => $model->getTaxRegimes(),
            'contacts'           => $contacts,
            'contactsCount'      => $contactsCount,
            'opportunities'      => $opportunities,
            'opportunitySummary' => $opportunitySummary,
            'opportunityLabels'  => $opportunityLabels ?? [],
            'tasks'              => $tasks,
            'taskSummary'        => $taskSummary,
            'taskModel'          => $taskModel,
            'proposals'          => $proposals,
            'proposalSummary'    => $proposalSummary,
            'proposalModel'      => $proposalModel,
            'documents'          => $documents,
            'documentSummary'    => $documentSummary,
            'documentModel'      => $documentModel,
            'sponsors'           => $sponsors,
            'sponsorSummary'     => $sponsorSummary,
            'sponsorModel'       => $sponsorModel,
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
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('companies.edit');

        $company = $this->findOr404($params['id'] ?? null);

        if (!empty($company['archived_at'])) {
            flash('error', 'Esta empresa está arquivada. Restaure-a antes de editar.');
            $this->redirect('/companies/' . (int) $company['id']);
            return;
        }

        $this->renderForm('companies/edit', 'Editar empresa', $company, [], $company);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('companies.edit');
        csrf_verify();

        $company = $this->findOr404($params['id'] ?? null);
        $id      = (int) $company['id'];

        if (!empty($company['archived_at'])) {
            flash('error', 'Esta empresa está arquivada. Restaure-a antes de editar.');
            $this->redirect('/companies/' . $id);
            return;
        }

        $model = new Company();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'update');
        $this->checkUnique($model, $data, $errors, $id);
        $this->checkOwner($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($company, $data);
            $this->renderForm('companies/edit', 'Editar empresa', $data, $errors, $merged);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('company_updated', $_SESSION['user_id'] ?? null, 'company', $id);

        flash('success', 'Empresa atualizada com sucesso.');
        $this->redirect('/companies/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('companies.edit');
        csrf_verify();

        $company = $this->findOr404($params['id'] ?? null);
        $id      = (int) $company['id'];

        if (empty($company['archived_at'])) {
            (new Company())->archive($id);
            (new ActivityLog())->record('company_archived', $_SESSION['user_id'] ?? null, 'company', $id);
            flash('success', 'Empresa arquivada.');
        }

        $this->redirect('/companies/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('companies.edit');
        csrf_verify();

        $company = $this->findOr404($params['id'] ?? null);
        $id      = (int) $company['id'];

        if (!empty($company['archived_at'])) {
            $status = (string) input('status', 'prospect');
            (new Company())->restore($id, $status);
            (new ActivityLog())->record('company_restored', $_SESSION['user_id'] ?? null, 'company', $id);
            flash('success', 'Empresa restaurada.');
        }

        $this->redirect('/companies/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * Coleta e normaliza os dados do formulario.
     *
     * @return array<string, mixed>
     */
    private function collectInput(Company $model): array
    {
        $checkbox = static fn (string $k): int => input($k) !== null ? 1 : 0;

        $state = strtoupper(clean((string) input('state', '')));

        return [
            'name'                             => clean((string) input('name', '')),
            'trade_name'                       => clean((string) input('trade_name', '')),
            'cnpj'                             => $model->normalizeCnpj((string) input('cnpj', '')),
            'segment'                          => clean((string) input('segment', '')),
            'city'                             => clean((string) input('city', '')),
            'state'                            => $state,
            'website'                          => clean((string) input('website', '')),
            'linkedin'                         => clean((string) input('linkedin', '')),
            'general_email'                    => clean((string) input('general_email', '')),
            'general_phone'                    => clean((string) input('general_phone', '')),
            'operates_para'                    => $checkbox('operates_para'),
            'operates_carajas'                 => $checkbox('operates_carajas'),
            'operates_parauapebas'             => $checkbox('operates_parauapebas'),
            'tax_regime_guess'                 => clean((string) input('tax_regime_guess', '')),
            'has_cultural_sponsorship_history' => $checkbox('has_cultural_sponsorship_history'),
            'has_rouanet_history'              => $checkbox('has_rouanet_history'),
            'has_esg_alignment'                => $checkbox('has_esg_alignment'),
            'priority'                         => strtoupper(clean((string) input('priority', 'C'))),
            'source'                           => clean((string) input('source', '')),
            'status'                           => clean((string) input('status', 'prospect')),
            'owner_user_id'                    => (int) input('owner_user_id', 0) > 0 ? (int) input('owner_user_id', 0) : null,
            'notes'                            => trim((string) input('notes', '')),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $errors
     */
    private function checkUnique(Company $model, array $data, array &$errors, int|string|null $ignoreId): void
    {
        $cnpj = (string) ($data['cnpj'] ?? '');
        if (!isset($errors['cnpj']) && $cnpj !== '' && $model->existsCnpj($cnpj, $ignoreId)) {
            $errors['cnpj'] = 'Já existe uma empresa cadastrada com este CNPJ.';
        }
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
     * Renderiza o formulario de cadastro/edicao com listas controladas.
     *
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed> $company
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $company = []): void
    {
        $model = new Company();

        $this->view($view, [
            'title'      => $title,
            'old'        => $old,
            'errors'     => $errors,
            'company'    => $company,
            'segments'   => $model->getSegments(),
            'priorities' => $model->getPriorities(),
            'statuses'   => $model->getStatuses(),
            'taxRegimes' => $model->getTaxRegimes(),
            'sources'    => $model->getSources(),
            'states'     => $model->getStates(),
            'owners'     => (new User())->activeList(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $company = is_numeric($id) ? (new Company())->findById((int) $id) : null;

        if ($company === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }

        return $company;
    }
}
