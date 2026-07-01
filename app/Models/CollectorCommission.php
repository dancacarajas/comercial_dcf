<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Comissao calculada para captadores a partir de recebimentos confirmados.
 */
final class CollectorCommission extends Model
{
    protected string $table = 'collector_commissions';

    /** @return array<string, string> */
    public function getCalculationStatuses(): array
    {
        return [
            'calculada'          => 'Calculada',
            'limitada_por_teto'  => 'Limitada pelo teto',
            'bloqueada'          => 'Bloqueada',
            'recalculada'        => 'Recalculada',
            'cancelada'          => 'Cancelada',
        ];
    }

    /** @return array<string, string> */
    public function getApprovalStatuses(): array
    {
        return [
            'pendente_aprovacao' => 'Pendente de aprovacao',
            'aprovada'           => 'Aprovada',
            'bloqueada'          => 'Bloqueada',
            'cancelada'          => 'Cancelada',
        ];
    }

    /** @return array<string, string> */
    public function getPaymentStatuses(): array
    {
        return [
            'nao_iniciado' => 'Nao iniciado',
            'a_pagar'      => 'A pagar',
            'parcialmente_pago' => 'Parcialmente pago',
            'pago'         => 'Pago',
            'estornado'    => 'Estornado',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT cc.*, ip.`project_name`, c.`name` AS collector_name, c.`collector_code`,
                    co.`name` AS company_name, sp.`sponsor_display_name` AS sponsor_name,
                    fe.`title` AS financial_title
               FROM `collector_commissions` cc
               LEFT JOIN `incentive_projects` ip ON ip.`id` = cc.`incentive_project_id`
               LEFT JOIN `collectors` c ON c.`id` = cc.`collector_id`
               LEFT JOIN `companies` co ON co.`id` = cc.`company_id`
               LEFT JOIN `sponsors` sp ON sp.`id` = cc.`sponsor_id`
               LEFT JOIN `financial_entries` fe ON fe.`id` = cc.`financial_entry_id`
              WHERE cc.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByFinancialAndDeal(int|string $financialEntryId, int|string $collectorDealId): ?array
    {
        $row = $this->query(
            'SELECT * FROM `collector_commissions`
              WHERE `financial_entry_id` = :fid
                AND `collector_deal_id` = :did
                AND `archived_at` IS NULL
              LIMIT 1',
            ['fid' => $financialEntryId, 'did' => $collectorDealId]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function upsertForFinancialDeal(array $data): int
    {
        $existing = $this->findByFinancialAndDeal((int) $data['financial_entry_id'], (int) $data['collector_deal_id']);
        $data['payment_balance_amount'] = max(0, round((float) ($data['capped_commission_amount'] ?? 0), 2));
        if ($existing !== null) {
            $id = (int) $existing['id'];
            if (!$this->canRecalculate($existing)) {
                return $id;
            }
            $sets = [];
            $payload = $data;
            $payload['id'] = $id;
            foreach (array_keys($data) as $col) {
                $sets[] = '`' . $col . '` = :' . $col;
            }
            $sets[] = '`updated_at` = NOW()';
            $this->query('UPDATE `collector_commissions` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $payload);
            return $id;
        }

        $cols = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `collector_commissions` (`' . implode('`, `', $cols) . '`, `created_at`, `updated_at`)
             VALUES (' . implode(', ', $placeholders) . ', NOW(), NOW())',
            $data
        );

        return (int) $this->db->lastInsertId();
    }

    public function blockByFinancialEntry(int|string $financialEntryId, string $reason): void
    {
        $this->query(
            "UPDATE `collector_commissions`
                SET `calculation_status` = 'bloqueada',
                    `approval_status` = 'bloqueada',
                    `block_reason` = :reason,
                    `blocked_at` = NOW(),
                    `updated_at` = NOW()
              WHERE `financial_entry_id` = :fid
                AND `approval_status` <> 'aprovada'
                AND `payment_status` IN ('nao_iniciado','a_pagar')
                AND `archived_at` IS NULL",
            ['reason' => $reason, 'fid' => $financialEntryId]
        );
    }

    /** @param array<string, mixed> $commission */
    public function canRecalculate(array $commission): bool
    {
        return (string) ($commission['approval_status'] ?? '') === 'pendente_aprovacao'
            && (string) ($commission['payment_status'] ?? '') === 'nao_iniciado'
            && (float) ($commission['payment_total_amount'] ?? 0) <= 0;
    }

    /** @return array<string, string> */
    public function validateApproval(array $commission): array
    {
        $errors = [];
        if (!in_array((string) ($commission['calculation_status'] ?? ''), ['calculada', 'limitada_por_teto'], true)) {
            $errors['calculation_status'] = 'Comissao precisa estar calculada para aprovacao.';
        }
        if ((string) ($commission['approval_status'] ?? '') !== 'pendente_aprovacao') {
            $errors['approval_status'] = 'Somente comissao pendente pode ser aprovada.';
        }
        if ((string) ($commission['payment_status'] ?? '') !== 'nao_iniciado') {
            $errors['payment_status'] = 'Comissao com pagamento iniciado nao pode ser aprovada por este fluxo.';
        }
        if ((float) ($commission['capped_commission_amount'] ?? 0) <= 0) {
            $errors['capped_commission_amount'] = 'Comissao sem saldo calculado nao pode ser aprovada.';
        }

        return $errors;
    }

    public function approve(int|string $id, int|string $userId, string $notes = ''): void
    {
        $commission = $this->findById($id);
        if ($commission === null) {
            return;
        }
        $errors = $this->validateApproval($commission);
        if ($errors !== []) {
            throw new \RuntimeException(implode(' ', $errors));
        }
        $amount = round((float) ($commission['capped_commission_amount'] ?? 0), 2);
        $this->query(
            "UPDATE `collector_commissions`
                SET `approval_status` = 'aprovada',
                    `payment_status` = 'a_pagar',
                    `approved_by` = :uid,
                    `approved_at` = NOW(),
                    `approval_notes` = :notes,
                    `payment_balance_amount` = :balance,
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['uid' => $userId, 'notes' => $notes !== '' ? $notes : null, 'balance' => $amount, 'id' => $id]
        );
    }

    public function blockManual(int|string $id, int|string $userId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Informe o motivo do bloqueio.');
        }
        $commission = $this->findById($id);
        if ($commission === null) {
            return;
        }
        if (!in_array((string) ($commission['payment_status'] ?? ''), ['nao_iniciado', 'a_pagar'], true)) {
            throw new \RuntimeException('Comissao com pagamento iniciado nao pode ser bloqueada por este fluxo.');
        }
        $this->query(
            "UPDATE `collector_commissions`
                SET `approval_status` = 'bloqueada',
                    `payment_status` = 'nao_iniciado',
                    `block_reason` = :reason,
                    `block_notes` = :notes,
                    `approved_by` = NULL,
                    `approved_at` = NULL,
                    `approval_notes` = NULL,
                    `blocked_by` = :uid,
                    `blocked_at` = NOW(),
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['reason' => $reason, 'notes' => $reason, 'uid' => $userId, 'id' => $id]
        );
    }

    public function reopen(int|string $id, int|string $userId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Informe o motivo da reabertura.');
        }
        $commission = $this->findById($id);
        if ($commission === null) {
            return;
        }
        if ((string) ($commission['approval_status'] ?? '') !== 'bloqueada'
            || (string) ($commission['payment_status'] ?? '') !== 'nao_iniciado') {
            throw new \RuntimeException('Somente comissao bloqueada e sem pagamento pode ser reaberta.');
        }
        $this->query(
            "UPDATE `collector_commissions`
                SET `approval_status` = 'pendente_aprovacao',
                    `calculation_status` = CASE WHEN `calculation_status` = 'bloqueada' THEN 'calculada' ELSE `calculation_status` END,
                    `block_reason` = NULL,
                    `block_notes` = NULL,
                    `blocked_by` = NULL,
                    `blocked_at` = NULL,
                    `reopened_by` = :uid,
                    `reopened_at` = NOW(),
                    `reopen_reason` = :reason,
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['uid' => $userId, 'reason' => $reason, 'id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return $this->query(
            'SELECT cc.*, ip.`project_name`, c.`name` AS collector_name, c.`collector_code`,
                    co.`name` AS company_name, sp.`sponsor_display_name` AS sponsor_name,
                    fe.`title` AS financial_title
               FROM `collector_commissions` cc
               LEFT JOIN `incentive_projects` ip ON ip.`id` = cc.`incentive_project_id`
               LEFT JOIN `collectors` c ON c.`id` = cc.`collector_id`
               LEFT JOIN `companies` co ON co.`id` = cc.`company_id`
               LEFT JOIN `sponsors` sp ON sp.`id` = cc.`sponsor_id`
               LEFT JOIN `financial_entries` fe ON fe.`id` = cc.`financial_entry_id`' .
            $where . ' ORDER BY cc.`calculated_at` DESC, cc.`id` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        return (int) $this->query('SELECT COUNT(*) FROM `collector_commissions` cc' . $where, $params)->fetchColumn();
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $where = ' WHERE cc.`archived_at` IS NULL';
        $params = [];
        foreach (['incentive_project_id', 'collector_id', 'financial_entry_id'] as $field) {
            $value = (int) ($filters[$field] ?? 0);
            if ($value > 0) {
                $where .= ' AND cc.`' . $field . '` = :' . $field;
                $params[$field] = $value;
            }
        }
        foreach (['calculation_status', 'approval_status', 'payment_status'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));
            if ($value !== '') {
                $where .= ' AND cc.`' . $field . '` = :' . $field;
                $params[$field] = $value;
            }
        }

        return [$where, $params];
    }
}
