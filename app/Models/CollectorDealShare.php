<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Rateio de captacao compartilhada por collector_deal (Etapa 20B-3).
 */
final class CollectorDealShare extends Model
{
    protected string $table = 'collector_deal_shares';

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'rascunho' => 'Rascunho',
            'aprovado' => 'Aprovado',
            'arquivado' => 'Arquivado',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function findByDeal(int|string $dealId, bool $onlyActive = false): array
    {
        $sql = 'SELECT s.*, c.`name` AS collector_name, c.`collector_code`
                  FROM `collector_deal_shares` s
                  LEFT JOIN `collectors` c ON c.`id` = s.`collector_id`
                 WHERE s.`collector_deal_id` = :did';
        if ($onlyActive) {
            $sql .= ' AND s.`archived_at` IS NULL';
        }
        $sql .= ' ORDER BY s.`status` ASC, s.`id` ASC';

        return $this->query($sql, ['did' => $dealId])->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function approvedByDeal(int|string $dealId): array
    {
        return $this->query(
            "SELECT s.*, c.`name` AS collector_name, c.`collector_code`
               FROM `collector_deal_shares` s
               LEFT JOIN `collectors` c ON c.`id` = s.`collector_id`
              WHERE s.`collector_deal_id` = :did
                AND s.`status` = 'aprovado'
                AND s.`archived_at` IS NULL
              ORDER BY s.`id` ASC",
            ['did' => $dealId]
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT s.*, d.`attribution_type`, d.`financial_entry_id`, d.`collector_id` AS deal_collector_id
               FROM `collector_deal_shares` s
               JOIN `collector_deals` d ON d.`id` = s.`collector_deal_id`
              WHERE s.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function createForDeal(array $deal, array $data, int|string|null $userId): int
    {
        $this->assertSharedDeal($deal);
        $this->assertEditable((int) $deal['id']);
        $collectorId = (int) ($data['collector_id'] ?? 0);
        $percent = $this->normalizePercent($data['share_percent'] ?? null);
        if ($collectorId <= 0) {
            throw new \RuntimeException('Informe o captador do rateio.');
        }
        if ((new Collector())->findById($collectorId) === null) {
            throw new \RuntimeException('Captador do rateio nao encontrado.');
        }
        $exists = $this->query(
            'SELECT COUNT(*) FROM `collector_deal_shares`
              WHERE `collector_deal_id` = :did AND `collector_id` = :cid AND `archived_at` IS NULL',
            ['did' => (int) $deal['id'], 'cid' => $collectorId]
        )->fetchColumn();
        if ((int) $exists > 0) {
            throw new \RuntimeException('Captador ja esta no rateio ativo.');
        }

        $this->query(
            'INSERT INTO `collector_deal_shares`
                (`collector_deal_id`, `incentive_project_id`, `collector_id`, `share_percent`, `status`, `notes`, `created_by`, `created_at`)
             VALUES
                (:deal_id, :project_id, :collector_id, :share_percent, :status, :notes, :created_by, NOW())',
            [
                'deal_id' => (int) $deal['id'],
                'project_id' => (int) $deal['incentive_project_id'],
                'collector_id' => $collectorId,
                'share_percent' => $percent,
                'status' => 'rascunho',
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => $userId !== null ? (int) $userId : null,
            ]
        );

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateShare(int|string $id, array $data, int|string|null $userId): void
    {
        $share = $this->findById($id);
        if ($share === null) {
            throw new \RuntimeException('Rateio nao encontrado.');
        }
        $this->assertEditable((int) $share['collector_deal_id']);
        if ((string) ($share['status'] ?? '') === 'aprovado') {
            throw new \RuntimeException('Rateio aprovado nao pode ser alterado; arquive e recrie antes do calculo.');
        }
        $percent = $this->normalizePercent($data['share_percent'] ?? null);
        $this->query(
            'UPDATE `collector_deal_shares`
                SET `share_percent` = :percent,
                    `notes` = :notes,
                    `updated_by` = :uid,
                    `updated_at` = NOW()
              WHERE `id` = :id',
            [
                'percent' => $percent,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'uid' => $userId !== null ? (int) $userId : null,
                'id' => $id,
            ]
        );
    }

    public function archiveShare(int|string $id, int|string|null $userId): void
    {
        $share = $this->findById($id);
        if ($share === null) {
            throw new \RuntimeException('Rateio nao encontrado.');
        }
        $this->assertEditable((int) $share['collector_deal_id']);
        $this->query(
            "UPDATE `collector_deal_shares`
                SET `status` = 'arquivado',
                    `archived_at` = NOW(),
                    `updated_by` = :uid,
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['uid' => $userId !== null ? (int) $userId : null, 'id' => $id]
        );
    }

    public function approveDealShares(int|string $dealId, int|string|null $userId): void
    {
        $deal = (new CollectorDeal())->findById($dealId);
        if ($deal === null) {
            throw new \RuntimeException('Captacao nao encontrada.');
        }
        $this->assertSharedDeal($deal);
        $this->assertEditable((int) $deal['id']);
        $shares = $this->findByDeal($dealId, true);
        if ($shares === []) {
            throw new \RuntimeException('Cadastre ao menos um rateio.');
        }
        $sum = 0.0;
        foreach ($shares as $share) {
            if ((string) ($share['status'] ?? '') !== 'rascunho') {
                throw new \RuntimeException('Aprovacao exige rateios ativos em rascunho.');
            }
            $sum += (float) $share['share_percent'];
        }
        if (abs(round($sum, 4) - 100.0) > 0.0001) {
            throw new \RuntimeException('A soma dos percentuais ativos deve ser exatamente 100%.');
        }
        $this->query(
            "UPDATE `collector_deal_shares`
                SET `status` = 'aprovado',
                    `approved_by` = :uid,
                    `approved_at` = NOW(),
                    `updated_by` = :uid2,
                    `updated_at` = NOW()
              WHERE `collector_deal_id` = :did
                AND `archived_at` IS NULL",
            ['uid' => $userId !== null ? (int) $userId : null, 'uid2' => $userId !== null ? (int) $userId : null, 'did' => $dealId]
        );
    }

    /** @param array<string, mixed> $deal */
    private function assertSharedDeal(array $deal): void
    {
        if ((string) ($deal['attribution_type'] ?? '') !== 'compartilhada') {
            throw new \RuntimeException('Rateio permitido somente para captacao compartilhada.');
        }
        if ((int) ($deal['incentive_project_id'] ?? 0) <= 0) {
            throw new \RuntimeException('Captacao sem projeto nao pode receber rateio.');
        }
    }

    public function assertEditable(int $dealId): void
    {
        $row = $this->query(
            "SELECT COUNT(*) FROM `collector_commissions`
              WHERE `collector_deal_id` = :did
                AND `archived_at` IS NULL
                AND (
                    `approval_status` = 'aprovada'
                    OR `payment_status` IN ('parcialmente_pago','pago')
                    OR `payment_total_amount` > 0
                )",
            ['did' => $dealId]
        )->fetchColumn();
        if ((int) $row > 0) {
            throw new \RuntimeException('Rateio nao pode ser alterado com comissao aprovada ou paga.');
        }
    }

    private function normalizePercent(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new \RuntimeException('Informe o percentual do rateio.');
        }
        $normalized = str_replace([' ', '%'], '', $raw);
        if (str_contains($normalized, ',')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        }
        $percent = round((float) $normalized, 4);
        if ($percent <= 0 || $percent > 100) {
            throw new \RuntimeException('Percentual do rateio deve estar entre 0 e 100.');
        }

        return $percent;
    }
}
