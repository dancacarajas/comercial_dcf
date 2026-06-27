<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\ContractTemplate;
use App\Services\ContractTemplateRenderer;

final class ContractTemplateController extends Controller
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        AuthMiddleware::requirePermission('contract_templates.view');

        $model   = new ContractTemplate();
        $filters = [
            'q'              => trim((string) input('q', '')),
            'status'         => trim((string) input('status', '')),
            'template_type'  => trim((string) input('template_type', '')),
            'show_archived'  => input('show_archived') === '1',
        ];
        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('contract_templates/index', [
            'title'   => 'Modelos de contrato',
            'items'   => $model->paginate($filters, $page, self::PER_PAGE),
            'filters' => $filters,
            'types'   => $model->getTypes(),
            'statuses'=> $model->getStatuses(),
            'page'    => $page,
            'pages'   => $pages,
            'total'   => $total,
        ]);
    }

    public function create(): void
    {
        AuthMiddleware::requirePermission('contract_templates.create');
        $this->renderForm('contract_templates/create', 'Novo modelo', $this->defaultForm(), []);
    }

    public function store(): void
    {
        AuthMiddleware::requirePermission('contract_templates.create');
        csrf_verify();

        $model = new ContractTemplate();
        $data  = $this->collectInput();
        $errors = $model->validate($data);

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('contract_templates/create', 'Novo modelo', $data, $errors);
            return;
        }

        $data['created_by'] = $_SESSION['user_id'] ?? null;
        $data['content_text'] = (new ContractTemplateRenderer())->toPlainText((string) ($data['content_html'] ?? ''));
        $id = (int) $model->create($data);
        (new ActivityLog())->record('contract_template_created', $_SESSION['user_id'] ?? null, 'contract_template', $id);
        flash('success', 'Modelo criado.');
        $this->redirect('/contract-templates/' . $id);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.view');
        $item = $this->findOr404($params['id'] ?? null);
        $model = new ContractTemplate();

        $this->view('contract_templates/show', [
            'title'        => $item['title'] ?? 'Modelo',
            'item'         => $item,
            'types'        => $model->getTypes(),
            'statuses'     => $model->getStatuses(),
            'placeholders' => ContractTemplateRenderer::defaultPlaceholders(),
        ]);
    }

    public function edit(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.edit');
        $item = $this->findOr404($params['id'] ?? null);
        $this->renderForm('contract_templates/edit', 'Editar modelo', $item, [], $item);
    }

    public function update(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.edit');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        $model  = new ContractTemplate();
        $data   = $this->collectInput();
        $errors = $model->validate($data, 'update');

        if ($errors !== []) {
            http_response_code(422);
            $this->renderForm('contract_templates/edit', 'Editar modelo', array_merge($item, $data), $errors, $item);
            return;
        }

        $data['updated_by'] = $_SESSION['user_id'] ?? null;
        $data['content_text'] = (new ContractTemplateRenderer())->toPlainText((string) ($data['content_html'] ?? ''));
        $model->update($id, $data);
        (new ActivityLog())->record('contract_template_updated', $_SESSION['user_id'] ?? null, 'contract_template', $id);
        flash('success', 'Modelo atualizado.');
        $this->redirect('/contract-templates/' . $id);
    }

    public function archive(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.archive');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        $model = new ContractTemplate();
        if (!$model->canArchive($id)) {
            flash('error', 'Não é possível excluir: este modelo está vinculado a solicitações de assinatura.');
            $this->redirect('/contract-templates/' . $id);
        }

        $model->archive($id, $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('contract_template_archived', $_SESSION['user_id'] ?? null, 'contract_template', $id);
        flash('success', 'Modelo excluído.');
        $this->redirect('/contract-templates');
    }

    public function restore(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.archive');
        csrf_verify();
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];
        (new ContractTemplate())->restore($id, $_SESSION['user_id'] ?? null);
        (new ActivityLog())->record('contract_template_restored', $_SESSION['user_id'] ?? null, 'contract_template', $id);
        flash('success', 'Modelo restaurado.');
        $this->redirect('/contract-templates/' . $id);
    }

    public function preview(array $params): void
    {
        AuthMiddleware::requirePermission('contract_templates.preview');
        $item = $this->findOr404($params['id'] ?? null);
        $id   = (int) $item['id'];

        $sampleContext = [
            'collector' => [
                'name'                => 'Maria Silva Exemplo',
                'document_number'     => '123.456.789-00',
                'email'               => 'maria@exemplo.com',
                'phone_whatsapp'      => '94999990000',
                'city_state'          => 'Parauapebas/PA',
                'company_or_activity' => 'Consultoria cultural',
                'rouanet_experience'  => 'intermediaria',
                'segments'            => 'Cultura, Educação',
            ],
            'application' => [
                'application_number' => 'CAP-2026-DEMO',
                'approved_at'        => date('d/m/Y'),
            ],
            'organization' => [
                'name'                => 'Dança Carajás Festival',
                'document'            => '',
                'representative_name' => 'Direção Executiva',
            ],
            'date' => ['today' => date('d/m/Y')],
        ];

        $rendered = (new ContractTemplateRenderer())->render((string) ($item['content_html'] ?? ''), $sampleContext);
        (new ActivityLog())->record('contract_template_previewed', $_SESSION['user_id'] ?? null, 'contract_template', $id);

        $this->view('contract_templates/preview', [
            'title'    => 'Pré-visualização — ' . ($item['title'] ?? ''),
            'item'     => $item,
            'rendered' => $rendered,
        ]);
    }

    /** @return array<string, mixed> */
    private function defaultForm(): array
    {
        return [
            'template_type' => 'autorizacao_captador',
            'status'        => 'rascunho',
            'content_html'  => '<p>Conteúdo do contrato...</p>',
        ];
    }

    /** @return array<string, mixed> */
    private function collectInput(): array
    {
        return [
            'template_key'        => trim((string) input('template_key', '')),
            'title'               => trim((string) input('title', '')),
            'description'         => trim((string) input('description', '')),
            'template_type'       => trim((string) input('template_type', 'autorizacao_captador')),
            'status'              => trim((string) input('status', 'rascunho')),
            'content_html'        => (string) input('content_html', ''),
            'default_signer_role' => trim((string) input('default_signer_role', 'captador')),
            'is_default'          => input('is_default') === '1' ? 1 : 0,
            'collector_signature_stage_enabled' => input('collector_signature_stage_enabled') === '1' ? 1 : 0,
            'collector_signature_required' => input('collector_signature_required') === '1' ? 1 : 0,
            'collector_signature_order' => (int) input('collector_signature_order', 0),
        ];
    }

    /** @return array<string, mixed> */
    private function findOr404(mixed $id): array
    {
        $row = (new ContractTemplate())->findById($id ?? 0);
        if ($row === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Não encontrado']);
            exit;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, string> $errors
     * @param array<string, mixed>|null $item
     */
    private function renderForm(string $view, string $title, array $old, array $errors, ?array $item = null): void
    {
        $model = new ContractTemplate();
        $this->view($view, [
            'title'        => $title,
            'old'          => $old,
            'errors'       => $errors,
            'types'        => $model->getTypes(),
            'statuses'     => $model->getStatuses(),
            'placeholders' => ContractTemplateRenderer::defaultPlaceholders(),
            'item'         => $item,
        ]);
    }
}
