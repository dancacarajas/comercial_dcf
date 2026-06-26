<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Report;
use App\Models\ReportSnapshot;
use App\Models\User;

/**
 * Módulo Relatórios Avançados / Indicadores Gerenciais (Etapa 17).
 */
final class ReportController extends Controller
{
    private const PER_PAGE = 15;

    /** @var array<string, array{title:string, description:string, method:string, view:string}> */
    private const REPORTS = [
        'executive' => [
            'title'       => 'Relatório Executivo Geral',
            'description' => 'Visão consolidada dos principais indicadores comerciais, financeiros e operacionais.',
            'method'      => 'getExecutiveReport',
            'view'        => 'reports/index',
        ],
        'pipeline' => [
            'title'       => 'Funil Comercial',
            'description' => 'Oportunidades por status, conversões e gargalos do pipeline.',
            'method'      => 'getPipelineReport',
            'view'        => 'reports/pipeline',
        ],
        'proposals' => [
            'title'       => 'Propostas',
            'description' => 'Status, valores e taxa de fechamento das propostas comerciais.',
            'method'      => 'getProposalReport',
            'view'        => 'reports/proposals',
        ],
        'sponsors' => [
            'title'       => 'Patrocinadores',
            'description' => 'Patrocinadores por status, tipo, cota e valores comprometidos.',
            'method'      => 'getSponsorReport',
            'view'        => 'reports/sponsors',
        ],
        'financials' => [
            'title'       => 'Financeiro',
            'description' => 'Previsto, recebido, saldo e recebimentos em atraso.',
            'method'      => 'getFinancialReport',
            'view'        => 'reports/financials',
        ],
        'contracts' => [
            'title'       => 'Contratos',
            'description' => 'Contratos por status, assinatura e valor formalizado.',
            'method'      => 'getContractReport',
            'view'        => 'reports/contracts',
        ],
        'counterparts' => [
            'title'       => 'Contrapartidas',
            'description' => 'Entregas, pendências e atrasos de contrapartidas.',
            'method'      => 'getCounterpartReport',
            'view'        => 'reports/counterparts',
        ],
        'dossiers' => [
            'title'       => 'Dossiês',
            'description' => 'Prestação de contas e status de entrega dos dossiês.',
            'method'      => 'getDossierReport',
            'view'        => 'reports/dossiers',
        ],
        'tasks' => [
            'title'       => 'Tarefas e Pendências',
            'description' => 'Tarefas abertas, vencidas e pendências críticas.',
            'method'      => 'getTaskReport',
            'view'        => 'reports/tasks',
        ],
        'leads' => [
            'title'       => 'Leads do Site',
            'description' => 'Leads recebidos, origem, conversão e evolução.',
            'method'      => 'getLeadReport',
            'view'        => 'reports/leads',
        ],
    ];

    public function index(): void
    {
        $this->renderReport('executive');
    }

    public function pipeline(): void
    {
        $this->renderReport('pipeline');
    }

    public function proposals(): void
    {
        $this->renderReport('proposals');
    }

    public function sponsors(): void
    {
        $this->renderReport('sponsors');
    }

    public function financials(): void
    {
        $this->renderReport('financials');
    }

    public function contracts(): void
    {
        $this->renderReport('contracts');
    }

    public function counterparts(): void
    {
        $this->renderReport('counterparts');
    }

    public function dossiers(): void
    {
        $this->renderReport('dossiers');
    }

    public function tasks(): void
    {
        $this->renderReport('tasks');
    }

    public function leads(): void
    {
        $this->renderReport('leads');
    }

    public function snapshots(): void
    {
        AuthMiddleware::requirePermission('reports.view');

        $model   = new ReportSnapshot();
        $filters = $this->collectSnapshotFilters();

        $page  = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $this->view('reports/snapshots', [
            'title'       => 'Snapshots de relatórios',
            'items'         => $model->paginate($filters, $page, self::PER_PAGE),
            'filters'     => $filters,
            'reportKeys'  => $model->getReportKeys(),
            'statuses'    => $model->getStatuses(),
            'users'       => (new User())->activeList(),
            'page'        => $page,
            'pages'       => $pages,
            'total'       => $total,
            'perPage'     => self::PER_PAGE,
            'hasFilters'  => $this->hasSnapshotFilters($filters),
        ]);
    }

    public function snapshotShow(array $params): void
    {
        AuthMiddleware::requirePermission('reports.view');

        $id   = (int) ($params['id'] ?? 0);
        $model = new ReportSnapshot();
        $item = $id > 0 ? $model->findById($id) : null;
        if ($item === null) {
            $this->abort(404, 'Snapshot não encontrado.');
        }

        $this->view('reports/snapshot_show', [
            'title'      => 'Snapshot: ' . ($item['title'] ?? ''),
            'item'       => $item,
            'reportKeys' => $model->getReportKeys(),
            'statuses'   => $model->getStatuses(),
            'filters'    => $model->decodeJson((string) ($item['filters_json'] ?? '')) ?? [],
            'metrics'    => $model->decodeJson((string) ($item['metrics_json'] ?? '')) ?? [],
            'summary'    => $model->decodeJson((string) ($item['summary_json'] ?? '')) ?? [],
        ]);
    }

    public function storeSnapshot(): void
    {
        AuthMiddleware::requirePermission('reports.snapshots');
        csrf_verify();

        $model  = new Report();
        $snap   = new ReportSnapshot();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $reportKey = trim((string) input('report_key', ''));
        $filters   = $this->collectFilters();
        $filterErrors = $model->validateFilters($filters);

        $payload = [
            'report_key'   => $reportKey,
            'title'        => trim((string) input('title', '')),
            'description'  => trim((string) input('description', '')),
            'period_start' => input('period_start'),
            'period_end'   => input('period_end'),
            'notes'        => trim((string) input('notes', '')),
            'status'       => 'gerado',
            'generated_by' => $userId > 0 ? $userId : null,
            'created_by'   => $userId > 0 ? $userId : null,
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        if (!isset(self::REPORTS[$reportKey])) {
            flash('error', 'Tipo de relatório inválido.');
            $this->redirect('/reports/snapshots');
        }

        $reportData = $this->fetchReportData($reportKey, $filters);
        $payload['filters_json'] = $filters;
        $payload['metrics_json'] = $reportData['metrics'] ?? [];
        $payload['summary_json'] = [
            'tables'   => $reportData['tables'] ?? [],
            'alerts'   => $reportData['alerts'] ?? [],
            'rankings' => $reportData['rankings'] ?? [],
        ];

        $errors = $snap->validate($payload);
        if ($filterErrors !== []) {
            $errors = array_merge($errors, $filterErrors);
        }
        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            $this->redirect('/reports/snapshots');
        }

        $id = $snap->create($payload);
        (new ActivityLog())->record('report_snapshot_created', $userId ?: null, 'report_snapshot', $id);

        flash('success', 'Snapshot gerado com sucesso.');
        $this->redirect('/reports/snapshots/' . $id);
    }

    public function archiveSnapshot(array $params): void
    {
        AuthMiddleware::requirePermission('reports.archive');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        $model = new ReportSnapshot();
        if ($id <= 0 || $model->findById($id) === null) {
            $this->abort(404, 'Snapshot não encontrado.');
        }

        $model->archive($id);
        (new ActivityLog())->record('report_snapshot_archived', $_SESSION['user_id'] ?? null, 'report_snapshot', $id);

        flash('success', 'Snapshot arquivado.');
        $this->redirect('/reports/snapshots/' . $id);
    }

    public function restoreSnapshot(array $params): void
    {
        AuthMiddleware::requirePermission('reports.archive');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        $model = new ReportSnapshot();
        if ($id <= 0 || $model->findById($id) === null) {
            $this->abort(404, 'Snapshot não encontrado.');
        }

        $model->restore($id);
        (new ActivityLog())->record('report_snapshot_restored', $_SESSION['user_id'] ?? null, 'report_snapshot', $id);

        flash('success', 'Snapshot restaurado.');
        $this->redirect('/reports/snapshots/' . $id);
    }

    public function printGeneral(): void
    {
        $this->printReport(['reportKey' => 'executive']);
    }

    public function printReport(array $params): void
    {
        AuthMiddleware::requirePermission('reports.print');

        $reportKey = trim((string) ($params['reportKey'] ?? 'executive'));
        if (!isset(self::REPORTS[$reportKey])) {
            $this->abort(404, 'Relatório não encontrado.');
        }

        $model   = new Report();
        $filters = $this->collectFilters();
        $data    = $this->fetchReportData($reportKey, $filters);
        $meta    = self::REPORTS[$reportKey];

        (new ActivityLog())->record('report_print_viewed', $_SESSION['user_id'] ?? null, 'report', $reportKey);

        $this->view('reports/print', [
            'title'       => $meta['title'] . ' — Impressão',
            'reportKey'   => $reportKey,
            'reportTitle' => $meta['title'],
            'description' => $meta['description'],
            'filters'     => $filters,
            'filterErrors'=> $model->validateFilters($filters),
            'data'        => $data,
            'generatedAt' => date('d/m/Y H:i'),
            'printLayout' => true,
        ], 'layouts/print');
    }

    private function renderReport(string $reportKey): void
    {
        AuthMiddleware::requirePermission('reports.view');

        if (!isset(self::REPORTS[$reportKey])) {
            $this->abort(404, 'Relatório não encontrado.');
        }

        $model   = new Report();
        $filters = $this->collectFilters();
        $errors  = $model->validateFilters($filters);
        $data    = $this->fetchReportData($reportKey, $filters);
        $meta    = self::REPORTS[$reportKey];

        (new ActivityLog())->record('report_viewed', $_SESSION['user_id'] ?? null, 'report', $reportKey);

        $this->view($meta['view'], array_merge($this->reportViewData($reportKey, $meta, $filters, $errors, $data), [
            'reportKey' => $reportKey,
        ]));
    }

    /** @return array<string, mixed> */
    private function reportViewData(string $reportKey, array $meta, array $filters, array $errors, array $data): array
    {
        $model = new Report();

        return [
            'title'        => $meta['title'],
            'description'  => $meta['description'],
            'filters'      => $filters,
            'filterErrors' => $errors,
            'metrics'      => $data['metrics'] ?? [],
            'tables'       => $data['tables'] ?? [],
            'alerts'       => $data['alerts'] ?? [],
            'rankings'     => $data['rankings'] ?? [],
            'options'      => $model->buildCommonOptions(),
            'reportKeys'   => $model->getReportKeys(),
            'hasFilters'   => $this->hasReportFilters($filters),
            'isEmpty'      => ($data['metrics'] ?? []) === [] && ($data['tables'] ?? []) === [],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function fetchReportData(string $reportKey, array $filters): array
    {
        $model  = new Report();
        $method = self::REPORTS[$reportKey]['method'];

        return $model->{$method}($filters);
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        $model = new Report();

        return $model->normalizeFilters([
            'period_start'        => input('period_start'),
            'period_end'          => input('period_end'),
            'responsible_user_id' => input('responsible_user_id'),
            'company_id'          => input('company_id'),
            'sponsor_id'          => input('sponsor_id'),
            'quota_id'            => input('quota_id'),
            'status'              => input('status'),
            'source'              => input('source'),
            'only_pending'        => input('only_pending'),
            'only_overdue'        => input('only_overdue'),
        ]);
    }

    /** @return array<string, mixed> */
    private function collectSnapshotFilters(): array
    {
        return [
            'q'            => trim((string) input('q', '')),
            'report_key'   => trim((string) input('report_key', '')),
            'status'       => trim((string) input('status', '')),
            'generated_by' => (int) input('generated_by', 0),
            'period_from'  => trim((string) input('period_from', '')),
            'period_to'    => trim((string) input('period_to', '')),
            'show_archived'=> !empty(input('show_archived')) ? 1 : 0,
        ];
    }

    /** @param array<string, mixed> $filters */
    private function hasReportFilters(array $filters): bool
    {
        foreach (['period_start', 'period_end', 'status', 'source'] as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }
        foreach (['responsible_user_id', 'company_id', 'sponsor_id', 'quota_id'] as $k) {
            if ((int) ($filters[$k] ?? 0) > 0) {
                return true;
            }
        }

        return !empty($filters['only_pending']) || !empty($filters['only_overdue']);
    }

    /** @param array<string, mixed> $filters */
    private function hasSnapshotFilters(array $filters): bool
    {
        foreach (['q', 'report_key', 'status', 'period_from', 'period_to'] as $k) {
            if (!empty($filters[$k])) {
                return true;
            }
        }

        return (int) ($filters['generated_by'] ?? 0) > 0 || !empty($filters['show_archived']);
    }
}
