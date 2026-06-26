<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Proposal;
use App\Models\Sponsor;
use App\Models\Task;
use App\Models\User;

/**
 * Módulo Contatos (Etapa 5).
 *
 * Pessoas vinculadas OBRIGATORIAMENTE a uma empresa. Sem exclusão física
 * (arquivamento lógico). Permissões: contacts.view/create/edit.
 * Arquivar/restaurar exigem contacts.edit.
 */
final class ContactController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('contacts.view');

        $model = new Contact();

        $filters = [
            'q'                 => (string) input('q', ''),
            'company_id'        => (int) input('company_id', 0),
            'department'        => (string) input('department', ''),
            'decision_level'    => (string) input('decision_level', ''),
            'influence_level'   => (string) input('influence_level', ''),
            'preferred_channel' => (string) input('preferred_channel', ''),
            'status'            => (string) input('status', ''),
            'owner'             => (int) input('owner', 0),
            'overdue'           => input('overdue') !== null ? 1 : 0,
            'show_archived'     => input('show_archived') !== null ? 1 : 0,
        ];

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $contacts = $model->paginate($filters, $page, self::PER_PAGE);

        $this->view('contacts/index', [
            'title'          => 'Contatos',
            'contacts'       => $contacts,
            'filters'        => $filters,
            'companies'      => $this->companyOptions(),
            'departments'    => $model->getDepartments(),
            'decisionLevels' => $model->getDecisionLevels(),
            'influenceLevels'=> $model->getInfluenceLevels(),
            'channels'       => $model->getPreferredChannels(),
            'statuses'       => $model->getStatuses(),
            'owners'         => (new User())->activeList(),
            'page'           => $page,
            'pages'          => $pages,
            'total'          => $total,
            'perPage'        => self::PER_PAGE,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('contacts.create');

        $old = [];
        $companyId = (int) input('company_id', 0);
        if ($companyId > 0) {
            $old['company_id'] = $companyId;
        }

        $this->renderForm('contacts/create', 'Novo contato', $old, []);
    }

    /**
     * Formulário de contato com a empresa já selecionada (rota contextual).
     */
    public function createForCompany(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.create');

        $companyId = (int) ($params['id'] ?? 0);
        $company   = $companyId > 0 ? (new Company())->findById($companyId) : null;

        if ($company === null) {
            $this->abort(404, 'Empresa não encontrada.');
        }

        $this->renderForm('contacts/create', 'Novo contato', ['company_id' => $companyId], []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('contacts.create');
        csrf_verify();

        $model = new Contact();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'create');
        $this->checkCompany($data, $errors);
        $this->checkOwner($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('contacts/create', 'Novo contato', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $id = $model->create($data);

        (new ActivityLog())->record('contact_created', $_SESSION['user_id'] ?? null, 'contact', $id);

        flash('success', 'Contato cadastrado com sucesso.');
        $this->redirect('/contacts/' . (int) $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.view');

        $contact = $this->findOr404($params['id'] ?? null);
        $model   = new Contact();
        $id      = (int) $contact['id'];

        // Bloco "Oportunidades vinculadas" (somente para quem tem opportunities.view).
        $opportunities      = [];
        $opportunitiesCount = 0;
        $opportunityLabels  = [];
        if (can('opportunities.view')) {
            $oppModel           = new Opportunity();
            $opportunities      = $oppModel->findByContact($id, 10);
            $opportunitiesCount = $oppModel->countByContact($id);
            $opportunityLabels  = $oppModel->getStatusLabels();
        }

        // Bloco "Tarefas do contato" (somente para quem tem tasks.view).
        $tasks       = [];
        $taskSummary = ['open' => 0, 'overdue' => 0];
        $taskModel   = null;
        if (can('tasks.view')) {
            $taskModel   = new Task();
            $tasks       = $taskModel->findByContact($id, 6);
            $taskSummary = $taskModel->summaryByContact($id);
        }

        $proposals       = [];
        $proposalSummary = ['total' => 0, 'sent' => 0, 'open' => 0, 'total_value' => 0.0];
        $proposalModel   = null;
        if (can('proposals.view')) {
            $proposalModel   = new Proposal();
            $proposals       = $proposalModel->findByContact($id, 6);
            $proposalSummary = $proposalModel->summaryByContact($id);
        }

        $documents       = [];
        $documentSummary = ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        $documentModel   = null;
        if (can('documents.view')) {
            $documentModel   = new Document();
            $documents       = $documentModel->findByContact($id, 6);
            $documentSummary = $documentModel->summaryByContact($id);
        }

        $sponsors       = [];
        $sponsorSummary = ['total' => 0, 'confirmed' => 0, 'committed' => 0.0, 'confirmed_amount' => 0.0];
        $sponsorModel   = null;
        if (can('sponsors.view')) {
            $sponsorModel   = new Sponsor();
            $sponsors       = $sponsorModel->findByContact($id, 6);
            $sponsorSummary = $sponsorModel->summaryByContact($id);
        }

        $this->view('contacts/show', [
            'title'              => $contact['name'] ?? 'Contato',
            'contact'            => $contact,
            'decisionLevels'     => $model->getDecisionLevels(),
            'influenceLevels'    => $model->getInfluenceLevels(),
            'channels'           => $model->getPreferredChannels(),
            'statuses'           => $model->getStatuses(),
            'opportunities'      => $opportunities,
            'opportunitiesCount' => $opportunitiesCount,
            'opportunityLabels'  => $opportunityLabels,
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
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.edit');

        $contact = $this->findOr404($params['id'] ?? null);

        if (!empty($contact['archived_at'])) {
            flash('error', 'Este contato está arquivado. Restaure-o antes de editar.');
            $this->redirect('/contacts/' . (int) $contact['id']);
            return;
        }

        $this->renderForm('contacts/edit', 'Editar contato', $contact, [], $contact);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.edit');
        csrf_verify();

        $contact = $this->findOr404($params['id'] ?? null);
        $id      = (int) $contact['id'];

        if (!empty($contact['archived_at'])) {
            flash('error', 'Este contato está arquivado. Restaure-o antes de editar.');
            $this->redirect('/contacts/' . $id);
            return;
        }

        $model = new Contact();
        $data  = $this->collectInput($model);

        $errors = $model->validate($data, 'update');
        $this->checkCompany($data, $errors);
        $this->checkOwner($data, $errors);

        if ($errors !== []) {
            http_response_code(422);
            $merged = array_merge($contact, $data);
            $this->renderForm('contacts/edit', 'Editar contato', $data, $errors, $merged);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $model->update($id, $data);

        (new ActivityLog())->record('contact_updated', $_SESSION['user_id'] ?? null, 'contact', $id);

        flash('success', 'Contato atualizado com sucesso.');
        $this->redirect('/contacts/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.edit');
        csrf_verify();

        $contact = $this->findOr404($params['id'] ?? null);
        $id      = (int) $contact['id'];

        if (empty($contact['archived_at'])) {
            (new Contact())->archive($id);
            (new ActivityLog())->record('contact_archived', $_SESSION['user_id'] ?? null, 'contact', $id);
            flash('success', 'Contato arquivado.');
        }

        $this->redirect('/contacts/' . $id);
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('contacts.edit');
        csrf_verify();

        $contact = $this->findOr404($params['id'] ?? null);
        $id      = (int) $contact['id'];

        if (!empty($contact['archived_at'])) {
            $status = (string) input('status', 'ativo');
            (new Contact())->restore($id, $status);
            (new ActivityLog())->record('contact_restored', $_SESSION['user_id'] ?? null, 'contact', $id);
            flash('success', 'Contato restaurado.');
        }

        $this->redirect('/contacts/' . $id);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * Coleta e normaliza os dados do formulario.
     *
     * @return array<string, mixed>
     */
    private function collectInput(Contact $model): array
    {
        return [
            'company_id'          => (int) input('company_id', 0),
            'name'                => clean((string) input('name', '')),
            'position_title'      => clean((string) input('position_title', '')),
            'department'          => clean((string) input('department', '')),
            'email'               => clean((string) input('email', '')),
            'whatsapp'            => $model->normalizeWhatsapp((string) input('whatsapp', '')),
            'phone'               => clean((string) input('phone', '')),
            'linkedin'            => clean((string) input('linkedin', '')),
            'decision_level'      => clean((string) input('decision_level', 'nao_informado')),
            'influence_level'     => clean((string) input('influence_level', 'media')),
            'preferred_channel'   => clean((string) input('preferred_channel', 'nao_informado')),
            'last_interaction_at' => $this->normalizeDateTime((string) input('last_interaction_at', '')),
            'next_contact_at'     => $this->normalizeDateTime((string) input('next_contact_at', '')),
            'status'              => clean((string) input('status', 'ativo')),
            'owner_user_id'       => (int) input('owner_user_id', 0) > 0 ? (int) input('owner_user_id', 0) : null,
            'notes'               => trim((string) input('notes', '')),
        ];
    }

    /**
     * Normaliza datetime-local ("YYYY-MM-DDTHH:MM") para "YYYY-MM-DD HH:MM:SS".
     * Retorna null quando vazio/invalido (a validacao reporta invalido separadamente).
     */
    private function normalizeDateTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        $ts    = strtotime($value);
        if ($ts === false) {
            return $value; // mantem para a validacao sinalizar erro
        }

        return date('Y-m-d H:i:s', $ts);
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
     * @param array<string, mixed> $contact
     */
    private function renderForm(string $view, string $title, array $old, array $errors, array $contact = []): void
    {
        $model = new Contact();

        $this->view($view, [
            'title'           => $title,
            'old'             => $old,
            'errors'          => $errors,
            'contact'         => $contact,
            'companies'       => $this->companyOptions(),
            'departments'     => $model->getDepartments(),
            'decisionLevels'  => $model->getDecisionLevels(),
            'influenceLevels' => $model->getInfluenceLevels(),
            'channels'        => $model->getPreferredChannels(),
            'statuses'        => $model->getStatuses(),
            'owners'          => (new User())->activeList(),
        ]);
    }

    /**
     * Lista de empresas (id, name, archived_at) para selects/filtros.
     *
     * @return array<int, array<string, mixed>>
     */
    private function companyOptions(): array
    {
        return (new Company())->options();
    }

    /**
     * @return array<string, mixed>
     */
    private function findOr404(mixed $id): array
    {
        $contact = is_numeric($id) ? (new Contact())->findById((int) $id) : null;

        if ($contact === null) {
            $this->abort(404, 'Contato não encontrado.');
        }

        return $contact;
    }
}
