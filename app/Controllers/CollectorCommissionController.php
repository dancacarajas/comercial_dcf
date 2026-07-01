<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Middlewares\AuthMiddleware;
use App\Models\ActivityLog;
use App\Models\Collector;
use App\Models\CollectorCommission;
use App\Models\CollectorCommissionPayment;
use App\Models\CommissionPool;
use App\Models\IncentiveProject;
use App\Services\CollectorCommissionCalculator;
use App\Services\CollectorCommissionPaymentService;

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
            'statusGroups' => $this->statusGroups(),
            'attributionTypes' => $this->attributionTypes(),
            'summary' => $model->summary($filters),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
    }

    public function dashboard(): void
    {
        AuthMiddleware::requirePermission('commissions.view');

        $model = new CollectorCommission();
        $filters = $this->collectFilters();

        $this->view('collector_commissions/dashboard', [
            'title' => 'Dashboard de Comissoes',
            'filters' => $filters,
            'projects' => (new IncentiveProject())->options(false),
            'collectors' => $this->collectorOptions(),
            'statusGroups' => $this->statusGroups(),
            'summary' => $model->summary($filters),
            'byProject' => $model->reportByProject($filters),
            'byCollector' => $model->reportByCollector($filters),
            'byFinancial' => $model->reportByFinancial($filters),
            'byStatus' => $model->reportByStatus($filters),
            'alerts' => $model->operationalAlerts($filters),
        ]);
    }

    public function export(): void
    {
        AuthMiddleware::requirePermission('commissions.view');

        $model = new CollectorCommission();
        $rows = $model->exportRows($this->collectFilters(), 5000);
        $filename = 'comissoes-captadores-' . date('Ymd-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }
        fputcsv($out, [
            'id',
            'projeto',
            'captador',
            'codigo_captador',
            'financeiro',
            'status_financeiro',
            'empresa',
            'patrocinador',
            'tipo_atribuicao',
            'valor_recebido',
            'comissao_bruta',
            'comissao_limitada',
            'valor_pago',
            'saldo_pagamento',
            'status_calculo',
            'status_aprovacao',
            'status_pagamento',
            'calculado_em',
        ], ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                (int) ($row['id'] ?? 0),
                (string) ($row['project_name'] ?? ''),
                (string) ($row['collector_name'] ?? ''),
                (string) ($row['collector_code'] ?? ''),
                (string) ($row['financial_title'] ?? ''),
                (string) ($row['financial_status'] ?? ''),
                (string) ($row['company_name'] ?? ''),
                (string) ($row['sponsor_name'] ?? ''),
                (string) ($row['attribution_type'] ?? ''),
                number_format((float) ($row['financial_received_amount'] ?? 0), 2, ',', ''),
                number_format((float) ($row['gross_commission_amount'] ?? 0), 2, ',', ''),
                number_format((float) ($row['capped_commission_amount'] ?? 0), 2, ',', ''),
                number_format((float) ($row['payment_total_amount'] ?? 0), 2, ',', ''),
                number_format((float) ($row['payment_balance_amount'] ?? 0), 2, ',', ''),
                (string) ($row['calculation_status'] ?? ''),
                (string) ($row['approval_status'] ?? ''),
                (string) ($row['payment_status'] ?? ''),
                (string) ($row['calculated_at'] ?? ''),
            ], ';');
        }
        fclose($out);
        exit;
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
            'payments' => (new CollectorCommissionPayment())->findByCommission($id),
            'paymentMethods' => (new CollectorCommissionPayment())->getMethods(),
            'paymentStatuses' => (new CollectorCommissionPayment())->getStatuses(),
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

    public function pay(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.pay');
        csrf_verify();

        $id = (int) ($params['id'] ?? 0);
        try {
            $paymentId = (new CollectorCommissionPaymentService())->register($id, [
                'amount' => input('amount', ''),
                'payment_date' => input('payment_date', ''),
                'payment_method' => input('payment_method', ''),
                'proof_document_id' => input('proof_document_id', 0),
                'notes' => input('notes', ''),
            ], $_SESSION['user_id'] ?? null);
            (new ActivityLog())->record('collector_commission_payment_registered', $_SESSION['user_id'] ?? null, 'collector_commission_payment', $paymentId);
            flash('success', 'Pagamento de comissao registrado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/commissions/' . $id);
    }

    public function cancelPayment(array $params): void
    {
        AuthMiddleware::requirePermission('commissions.cancel_payment');
        csrf_verify();

        $paymentId = (int) ($params['id'] ?? 0);
        $commissionId = (int) input('collector_commission_id', 0);
        try {
            $commissionId = (new CollectorCommissionPaymentService())->cancel(
                $paymentId,
                $_SESSION['user_id'] ?? null,
                trim((string) input('cancel_reason', '')),
                trim((string) input('cancel_status', 'cancelado'))
            );
            (new ActivityLog())->record('collector_commission_payment_cancelled', $_SESSION['user_id'] ?? null, 'collector_commission_payment', $paymentId);
            flash('success', 'Pagamento de comissao cancelado/estornado.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/commissions/' . $commissionId);
    }

    /** @return array<string, mixed> */
    private function collectFilters(): array
    {
        return [
            'incentive_project_id' => (int) input('incentive_project_id', 0),
            'collector_id' => (int) input('collector_id', 0),
            'financial_entry_id' => (int) input('financial_entry_id', 0),
            'company_id' => (int) input('company_id', 0),
            'sponsor_id' => (int) input('sponsor_id', 0),
            'calculation_status' => trim((string) input('calculation_status', '')),
            'approval_status' => trim((string) input('approval_status', '')),
            'payment_status' => trim((string) input('payment_status', '')),
            'status_group' => trim((string) input('status_group', '')),
            'attribution_type' => trim((string) input('attribution_type', '')),
            'date_from' => trim((string) input('date_from', '')),
            'date_to' => trim((string) input('date_to', '')),
            'q' => trim((string) input('q', '')),
        ];
    }

    /** @return array<string, string> */
    private function statusGroups(): array
    {
        return [
            'pendente' => 'Pendente',
            'aprovada' => 'Aprovada',
            'a_pagar' => 'A pagar',
            'parcialmente_paga' => 'Parcialmente paga',
            'paga' => 'Paga',
            'bloqueada' => 'Bloqueada',
        ];
    }

    /** @return array<string, string> */
    private function attributionTypes(): array
    {
        return [
            'direta' => 'Direta',
            'indicacao' => 'Indicacao',
            'compartilhada' => 'Compartilhada',
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
