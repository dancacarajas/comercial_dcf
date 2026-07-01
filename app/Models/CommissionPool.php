<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Pool da rubrica de captacao por projeto incentivado (Etapa 20A).
 */
final class CommissionPool extends Model
{
    protected string $table = 'commission_pools';

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'ativo'       => 'Ativo',
            'bloqueado'   => 'Bloqueado',
            'esgotado'    => 'Esgotado',
            'encerrado'   => 'Encerrado',
            'recalculando'=> 'Recalculando',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByProject(int|string $projectId): ?array
    {
        $row = $this->query(
            'SELECT cp.*, ip.`project_name`, ip.`edition_year`
               FROM `commission_pools` cp
               LEFT JOIN `incentive_projects` ip ON ip.`id` = cp.`incentive_project_id`
              WHERE cp.`incentive_project_id` = :pid
              LIMIT 1',
            ['pid' => $projectId]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>
     */
    public function ensureForProject(array $project): array
    {
        $projectId = (int) ($project['id'] ?? 0);
        $existing = $this->findByProject($projectId);
        if ($existing !== null) {
            return $existing;
        }

        $budget = (float) ($project['capture_commission_budget'] ?? 0);
        $this->query(
            'INSERT INTO `commission_pools`
                (`incentive_project_id`, `approved_total_amount_snapshot`, `capture_commission_budget_snapshot`,
                 `commission_factor_snapshot`, `commission_available_balance`, `status`, `calculated_at`, `created_at`, `updated_at`)
             VALUES
                (:pid, :approved, :budget, :factor, :balance, :status, NOW(), NOW(), NOW())',
            [
                'pid'      => $projectId,
                'approved' => (float) ($project['approved_total_amount'] ?? 0),
                'budget'   => $budget,
                'factor'   => $project['commission_factor'] !== null ? (float) $project['commission_factor'] : null,
                'balance'  => $budget,
                'status'   => $budget > 0 ? 'ativo' : 'bloqueado',
            ]
        );

        return $this->findByProject($projectId) ?? [];
    }

    /** @param array<string, mixed> $project */
    public function refreshForProject(array $project): void
    {
        $projectId = (int) ($project['id'] ?? 0);
        if ($projectId <= 0) {
            return;
        }
        $this->ensureForProject($project);

        $row = $this->query(
            "SELECT
                COALESCE(SUM(CASE WHEN cc.`calculation_status` IN ('calculada','limitada_por_teto')
                    AND cc.`approval_status` NOT IN ('cancelada') THEN cc.`capped_commission_amount` ELSE 0 END), 0) AS generated_total,
                COALESCE(SUM(CASE WHEN cc.`approval_status` = 'aprovada' THEN cc.`capped_commission_amount` ELSE 0 END), 0) AS approved_total,
                COALESCE(SUM(CASE WHEN cc.`approval_status` = 'bloqueada' THEN cc.`capped_commission_amount` ELSE 0 END), 0) AS blocked_total,
                COALESCE(SUM(fe.`received_amount`), 0) AS received_total
               FROM `commission_pools` cp
               LEFT JOIN `collector_commissions` cc ON cc.`commission_pool_id` = cp.`id` AND cc.`archived_at` IS NULL
               LEFT JOIN `financial_entries` fe ON fe.`id` = cc.`financial_entry_id`
              WHERE cp.`incentive_project_id` = :pid",
            ['pid' => $projectId]
        )->fetch() ?: [];

        $budget = (float) ($project['capture_commission_budget'] ?? 0);
        $generated = (float) ($row['generated_total'] ?? 0);
        $balance = max(0, round($budget - $generated, 2));
        $status = $budget <= 0 ? 'bloqueado' : ($balance <= 0.0 ? 'esgotado' : 'ativo');

        $this->query(
            'UPDATE `commission_pools`
                SET `approved_total_amount_snapshot` = :approved,
                    `capture_commission_budget_snapshot` = :budget,
                    `commission_factor_snapshot` = :factor,
                    `gross_received_total` = :received,
                    `commission_generated_total` = :generated,
                    `commission_approved_total` = :approved_total,
                    `commission_blocked_total` = :blocked_total,
                    `commission_available_balance` = :balance,
                    `status` = :status,
                    `calculated_at` = NOW(),
                    `updated_at` = NOW()
              WHERE `incentive_project_id` = :pid',
            [
                'approved'       => (float) ($project['approved_total_amount'] ?? 0),
                'budget'         => $budget,
                'factor'         => $project['commission_factor'] !== null ? (float) $project['commission_factor'] : null,
                'received'       => (float) ($row['received_total'] ?? 0),
                'generated'      => $generated,
                'approved_total' => (float) ($row['approved_total'] ?? 0),
                'blocked_total'  => (float) ($row['blocked_total'] ?? 0),
                'balance'        => $balance,
                'status'         => $status,
                'pid'            => $projectId,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        $where = ' WHERE 1=1';
        $params = [];
        if ((int) ($filters['incentive_project_id'] ?? 0) > 0) {
            $where .= ' AND cp.`incentive_project_id` = :pid';
            $params['pid'] = (int) $filters['incentive_project_id'];
        }

        return $this->query(
            'SELECT cp.*, ip.`project_name`, ip.`edition_year`
               FROM `commission_pools` cp
               LEFT JOIN `incentive_projects` ip ON ip.`id` = cp.`incentive_project_id`' .
            $where . ' ORDER BY ip.`edition_year` DESC, ip.`project_name` ASC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where = ' WHERE 1=1';
        $params = [];
        if ((int) ($filters['incentive_project_id'] ?? 0) > 0) {
            $where .= ' AND `incentive_project_id` = :pid';
            $params['pid'] = (int) $filters['incentive_project_id'];
        }

        return (int) $this->query('SELECT COUNT(*) FROM `commission_pools`' . $where, $params)->fetchColumn();
    }
}
