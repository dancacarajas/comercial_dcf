<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\CollectorCommission;
use App\Models\CommissionPool;
use PDO;

/**
 * Motor idempotente de comissao dos captadores (Etapa 20A).
 */
final class CollectorCommissionCalculator
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Calcula ou atualiza a comissao de um recebimento financeiro.
     *
     * @return array{status:string,message:string,commission_id?:int}
     */
    public function syncForFinancialEntry(int|string $financialEntryId, int|string|null $userId = null): array
    {
        $entry = $this->financialEntry((int) $financialEntryId);
        if ($entry === null) {
            return ['status' => 'ignored', 'message' => 'Lancamento financeiro nao encontrado.'];
        }

        if (!$this->isEligibleFinancialEntry($entry)) {
            (new CollectorCommission())->blockByFinancialEntry((int) $entry['id'], 'Lancamento financeiro sem recebimento confirmado elegivel.');
            return ['status' => 'blocked', 'message' => 'Lancamento financeiro nao elegivel para comissao.'];
        }

        $project = $this->project((int) $entry['incentive_project_id']);
        if ($project === null || (float) ($project['capture_commission_budget'] ?? 0) <= 0 || (float) ($project['commission_factor'] ?? 0) <= 0) {
            return ['status' => 'blocked', 'message' => 'Projeto sem rubrica/fator de comissao configurado.'];
        }

        $deal = $this->findCollectorDeal($entry);
        if ($deal === null || (int) ($deal['collector_id'] ?? 0) <= 0) {
            return ['status' => 'blocked', 'message' => 'Nenhum collector_deal elegivel encontrado para o recebimento.'];
        }

        if ((int) ($deal['incentive_project_id'] ?? 0) !== (int) $entry['incentive_project_id']) {
            return ['status' => 'blocked', 'message' => 'Collector deal pertence a outro projeto.'];
        }

        $commissionModel = new CollectorCommission();
        $existingCommission = $commissionModel->findByFinancialAndDeal((int) $entry['id'], (int) $deal['id']);
        if ($existingCommission !== null && !$commissionModel->canRecalculate($existingCommission)) {
            return [
                'status' => 'locked',
                'message' => 'Comissao aprovada, bloqueada ou com pagamento iniciado nao pode ser recalculada automaticamente.',
                'commission_id' => (int) $existingCommission['id'],
            ];
        }

        $collector = $this->collector((int) $deal['collector_id']);
        if (!$this->collectorEligible($collector, (string) ($entry['received_at'] ?? ''))) {
            return ['status' => 'blocked', 'message' => 'Captador sem cadastro/contrato liberado para comissao.'];
        }

        if ((string) ($deal['attribution_type'] ?? '') === 'compartilhada') {
            return ['status' => 'blocked', 'message' => 'Captacao compartilhada requer rateio antes do calculo automatico.'];
        }

        $poolModel = new CommissionPool();
        $pool = $poolModel->ensureForProject($project);
        $budget = (float) ($project['capture_commission_budget'] ?? 0);
        $factor = (float) ($project['commission_factor'] ?? 0);
        $received = round((float) ($entry['received_amount'] ?? 0), 2);
        $gross = round($received * $factor, 2);
        $used = $this->usedCommissionAmount((int) $project['id'], (int) $entry['id'], (int) $deal['id']);
        $availableBefore = max(0, round($budget - $used, 2));
        $capped = min($gross, $availableBefore);
        $availableAfter = max(0, round($availableBefore - $capped, 2));
        $calculationStatus = $capped < $gross ? 'limitada_por_teto' : 'calculada';

        if ($capped <= 0) {
            $calculationStatus = 'limitada_por_teto';
        }

        $snapshot = [
            'project_id' => (int) $project['id'],
            'project_name' => (string) ($project['project_name'] ?? ''),
            'approved_total_amount' => (float) ($project['approved_total_amount'] ?? 0),
            'capture_commission_budget' => $budget,
            'commission_factor' => $factor,
            'financial_entry_id' => (int) $entry['id'],
            'received_amount' => $received,
            'received_at' => (string) ($entry['received_at'] ?? ''),
            'collector_deal_id' => (int) $deal['id'],
            'collector_id' => (int) $deal['collector_id'],
            'collector_name' => (string) ($collector['name'] ?? ''),
            'attribution_type' => (string) ($deal['attribution_type'] ?? ''),
            'available_before' => $availableBefore,
            'gross_commission_amount' => $gross,
            'capped_commission_amount' => $capped,
            'available_after' => $availableAfter,
            'calculated_by' => $userId !== null ? (int) $userId : null,
            'calculated_at' => date('Y-m-d H:i:s'),
            'engine_version' => '20A.1',
        ];

        $commissionId = $commissionModel->upsertForFinancialDeal([
            'commission_pool_id' => (int) $pool['id'],
            'incentive_project_id' => (int) $project['id'],
            'collector_id' => (int) $deal['collector_id'],
            'collector_deal_id' => (int) $deal['id'],
            'financial_entry_id' => (int) $entry['id'],
            'company_id' => !empty($entry['company_id']) ? (int) $entry['company_id'] : null,
            'sponsor_id' => !empty($entry['sponsor_id']) ? (int) $entry['sponsor_id'] : null,
            'contract_id' => !empty($entry['contract_id']) ? (int) $entry['contract_id'] : null,
            'opportunity_id' => !empty($entry['opportunity_id']) ? (int) $entry['opportunity_id'] : null,
            'proposal_id' => !empty($entry['proposal_id']) ? (int) $entry['proposal_id'] : null,
            'quota_id' => !empty($entry['quota_id']) ? (int) $entry['quota_id'] : null,
            'attribution_type' => (string) ($deal['attribution_type'] ?? 'direta'),
            'source' => (string) ($deal['source'] ?? ''),
            'financial_received_amount' => $received,
            'commission_factor_snapshot' => $factor,
            'gross_commission_amount' => $gross,
            'capped_commission_amount' => $capped,
            'available_before' => $availableBefore,
            'available_after' => $availableAfter,
            'calculation_status' => $calculationStatus,
            'approval_status' => 'pendente_aprovacao',
            'payment_status' => 'nao_iniciado',
            'block_reason' => null,
            'calculation_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'calculated_at' => date('Y-m-d H:i:s'),
        ]);

        $poolModel->refreshForProject($project);
        (new ActivityLog())->record('collector_commission_calculated', $userId, 'collector_commission', $commissionId);

        return ['status' => 'calculated', 'message' => 'Comissao calculada.', 'commission_id' => $commissionId];
    }

    /** @return array<string, mixed>|null */
    private function financialEntry(int $id): ?array
    {
        $row = $this->db->prepare('SELECT * FROM `financial_entries` WHERE `id` = :id LIMIT 1');
        $row->execute(['id' => $id]);
        $data = $row->fetch();
        return $data !== false ? $data : null;
    }

    /** @param array<string, mixed> $entry */
    private function isEligibleFinancialEntry(array $entry): bool
    {
        return empty($entry['archived_at'])
            && (int) ($entry['incentive_project_id'] ?? 0) > 0
            && in_array((string) ($entry['status'] ?? ''), ['recebido', 'recebido_parcial', 'conciliado'], true)
            && (float) ($entry['received_amount'] ?? 0) > 0
            && !empty($entry['received_at']);
    }

    /** @return array<string, mixed>|null */
    private function project(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `incentive_projects` WHERE `id` = :id AND `archived_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $entry @return array<string, mixed>|null */
    private function findCollectorDeal(array $entry): ?array
    {
        foreach ([
            ['financial_entry_id', (int) $entry['id']],
            ['sponsor_id', (int) ($entry['sponsor_id'] ?? 0)],
            ['proposal_id', (int) ($entry['proposal_id'] ?? 0)],
            ['opportunity_id', (int) ($entry['opportunity_id'] ?? 0)],
            ['company_id', (int) ($entry['company_id'] ?? 0)],
        ] as [$column, $value]) {
            if ($value <= 0) {
                continue;
            }
            $stmt = $this->db->prepare(
                "SELECT * FROM `collector_deals`
                  WHERE `{$column}` = :value
                    AND `incentive_project_id` = :pid
                    AND `archived_at` IS NULL
                    AND `deal_status` NOT IN ('perdido','cancelado')
                  ORDER BY CASE WHEN `financial_entry_id` = :fid THEN 0 ELSE 1 END, `id` DESC
                  LIMIT 1"
            );
            $stmt->execute([
                'value' => $value,
                'pid' => (int) $entry['incentive_project_id'],
                'fid' => (int) $entry['id'],
            ]);
            $deal = $stmt->fetch();
            if ($deal !== false) {
                if ((int) ($deal['financial_entry_id'] ?? 0) <= 0) {
                    $this->linkDealToFinancialEntry((int) $deal['id'], (int) $entry['id']);
                    $deal['financial_entry_id'] = (int) $entry['id'];
                }
                return $deal;
            }
        }

        return null;
    }

    private function linkDealToFinancialEntry(int $dealId, int $financialEntryId): void
    {
        $stmt = $this->db->prepare('UPDATE `collector_deals` SET `financial_entry_id` = :fid, `updated_at` = NOW() WHERE `id` = :id');
        $stmt->execute(['fid' => $financialEntryId, 'id' => $dealId]);
    }

    /** @return array<string, mixed>|null */
    private function collector(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `collectors` WHERE `id` = :id AND `archived_at` IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed>|null $collector */
    private function collectorEligible(?array $collector, string $receivedAt): bool
    {
        if ($collector === null) {
            return false;
        }
        if ((string) ($collector['status'] ?? '') !== 'ativo' || (string) ($collector['registration_status'] ?? '') !== 'validado') {
            return false;
        }
        $date = substr($receivedAt !== '' ? $receivedAt : date('Y-m-d'), 0, 10);
        $start = (string) ($collector['contract_start_date'] ?? '');
        $end = (string) ($collector['contract_end_date'] ?? '');

        return $start !== '' && $end !== '' && $start <= $date && $end >= $date;
    }

    private function usedCommissionAmount(int $projectId, int $financialEntryId, int $collectorDealId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(`capped_commission_amount`), 0)
               FROM `collector_commissions`
              WHERE `incentive_project_id` = :pid
                AND `archived_at` IS NULL
                AND `calculation_status` IN ('calculada','limitada_por_teto')
                AND `approval_status` NOT IN ('cancelada')
                AND NOT (`financial_entry_id` = :fid AND `collector_deal_id` = :did)"
        );
        $stmt->execute(['pid' => $projectId, 'fid' => $financialEntryId, 'did' => $collectorDealId]);

        return (float) $stmt->fetchColumn();
    }
}
