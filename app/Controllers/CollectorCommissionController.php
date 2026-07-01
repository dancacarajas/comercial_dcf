<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorCommission;
use App\Models\CommissionPool;
use App\Models\IncentiveProject;
use App\Services\CollectorCommissionCalculator;

/**
 * Motor interno de comissoes dos captadores (Etapa 20A).
 */
final class CollectorCommissionController extends Controller
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        AuthMiddleware::requirePermission('commissions.view');

        $model = new CollectorCommission();
        $filters = $this->collectFilters();
        $page = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $this->view('collector_commissions/index', [
            'title' => 'Comissoes dos Captadores',
            'items' => $model->paginate($filters, $page, self::PER_PAGE),
            'filters' => $filters,
            'projects' => (new IncentiveProject())->options(false),
            'collectors' => $this->collectorOptions(),
            'calculationStatuses' => $model->getCalculationStatuses(),
            'approvalStatuses' => $model->getApprovalStatuses(),
            'paymentStatuses' => $model->getPaymentStatuses(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function pools(): void
    {
        AuthMiddleware::requirePermission('commissions.view');

        $filters = ['incentive_project_id' => (int) input('incentive_project_id', 0)];
        $model = new CommissionPool();
        $page = max(1, (int) input('page', 1));
        $total = $model->count($filters);
        $pages = (int) max(1, ceil($total / self::PER_PAGE));
        $page = min($page, $pages);

        $this->view('collector_commissions/pools', [
            'title' => 'Pools de Comissao',
            'items' => $model->paginate($filters, $page, self::PER_PAGE),
            'filters' => $filters,
            'projects' => (new IncentiveProject())->options(false),
            'statuses' => $model->getStatuses(),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function show(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.view');

        $id = (int) ($params['id'] ?? 0);
        $commission = $id > 0 ? (new CollectorCommission())->findById($id) : null;
        if ($commission === null) {
            $this->abort(404, 'Comissao nao encontrada.');
        }

        $this->view('collector_commissions/show', [
            'title' => 'Comissao #' . $id,
            'commission' => $commission,
            'snapshot' => json_decode((string) ($commission['calculation_snapshot_json'] ?? ''), true) ?: [],
            'model' => new CollectorCommission(),
        ]);
    }

    public function recalculateFinancial(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.calculate');
        csrf_verify();

        $financialEntryId = (int) ($params['id'] ?? 0);
        $result = (new CollectorCommissionCalculator())->syncForFinancialEntry($financialEntryId, $_SESSION['user_id'] ?? null);
        flash($result['status'] === 'calculated' ? 'success' : 'warning', $result['message']);
        $this->redirect('/financials/' . $financialEntryId);
    }

    public function approve(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.approve');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        try {
            (new CollectorCommission())->approve($id, $_SESSION['user_id'] ?? 0, trim((string) input('approval_notes', '')));
            (new ActivityLog())->record('collector_commission_approved', $_SESSION['user_id'] ?? null, 'collector_commission', $id);
            flash('success', 'Comissao aprovada e marcada como a pagar.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/commissions/' . $id);
    }

    public function block(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.block');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        try {
            (new CollectorCommission())->blockManual($id, $_SESSION['user_id'] ?? 0, trim((string) input('block_reason', '')));
            (new ActivityLog())->record('collector_commission_blocked', $_SESSION['user_id'] ?? null, 'collector_commission', $id);
            flash('success', 'Comissao bloqueada.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/commissions/' . $id);
    }

    public function reopen(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.reopen');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        try {
            (new CollectorCommission())->reopen($id, $_SESSION['user_id'] ?? 0, trim((string) input('reopen_reason', '')));
            (new ActivityLog())->record('collector_commission_reopened', $_SESSION['user_id'] ?? null, 'collector_commission', $id);
            flash('success', 'Comissao reaberta para aprovacao.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/commissions/' . $id);
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'incentive_project_id' => (int) input('incentive_project_id', 0),
            'collector_id' => (int) input('collector_id', 0),
            'financial_entry_id' => (int) input('financial_entry_id', 0),
            'calculation_status' => trim((string) input('calculation_status', '')),
            'approval_status' => trim((string) input('approval_status', '')),
            'payment_status' => trim((string) input('payment_status', '')),
        ];
    }

    /** @return array<int, array{id:int,label:string}> */
    private function collectorOptions(): array
    {
        $items = (new Collector())->paginate(['show_archived' => 0], 1, 300);
        return array_map(static fn (array $c): array => [
            'id' => (int) $c['id'],
            'label' => trim((string) ($c['name'] ?? '')) . (!empty($c['collector_code']) ? ' - ' . (string) $c['collector_code'] : ''),
        ], $items);
    }
}
