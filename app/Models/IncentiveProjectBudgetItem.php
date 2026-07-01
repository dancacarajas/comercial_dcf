<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Rubricas orçamentárias de um Projeto Incentivado (ETAPA 19 / Fase 19B).
 *
 * Espelha o plano de trabalho/orçamento aprovado no SALIC. A soma das rubricas
 * marcadas como is_capture_commission_item alimenta o capture_commission_budget
 * do projeto (base do fator de comissão).
 */
final class IncentiveProjectBudgetItem extends Model
{
    protected string $table = 'incentive_project_budget_items';

    /** @var list<string> */
    private const FILLABLE = [
        'incentive_project_id', 'item_number', 'source', 'product', 'stage',
        'uf', 'city', 'budget_item_name', 'unit', 'quantity', 'occurrence',
        'unit_amount', 'requested_amount', 'is_capture_commission_item', 'notes',
    ];

    public function normalizeMoney(?string $value): float|string|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $clean = preg_replace('/[^\d,.\-]/', '', $value) ?? '';
        if ($clean === '' || $clean === '-') {
            return $value;
        }
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forProject(int|string $projectId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM `incentive_project_budget_items` WHERE `incentive_project_id` = :p';
        if (!$includeArchived) {
            $sql .= ' AND `archived_at` IS NULL';
        }
        $sql .= ' ORDER BY `item_number` IS NULL, `item_number` ASC, `id` ASC';

        return $this->query($sql, ['p' => $projectId])->fetchAll();
    }

    /** Soma das rubricas marcadas como comissão de captação. */
    public function commissionBudgetForProject(int|string $projectId): float
    {
        $row = $this->query(
            'SELECT COALESCE(SUM(`requested_amount`),0) AS s
               FROM `incentive_project_budget_items`
              WHERE `incentive_project_id` = :p AND `is_capture_commission_item` = 1 AND `archived_at` IS NULL',
            ['p' => $projectId]
        )->fetch();

        return (float) ($row['s'] ?? 0);
    }

    /** Soma total das rubricas (requested_amount). */
    public function totalForProject(int|string $projectId): float
    {
        $row = $this->query(
            'SELECT COALESCE(SUM(`requested_amount`),0) AS s
               FROM `incentive_project_budget_items`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL',
            ['p' => $projectId]
        )->fetch();

        return (float) ($row['s'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        if ((int) ($data['incentive_project_id'] ?? 0) <= 0) {
            $errors['incentive_project_id'] = 'Projeto é obrigatório.';
        }
        $name = trim((string) ($data['budget_item_name'] ?? ''));
        if ($name === '') {
            $errors['budget_item_name'] = 'Informe o nome da rubrica.';
        }
        $amount = $data['requested_amount'] ?? null;
        if (is_string($amount)) {
            $errors['requested_amount'] = 'Valor solicitado inválido.';
        } elseif ($amount !== null && (float) $amount < 0) {
            $errors['requested_amount'] = 'O valor deve ser positivo.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);
        $cols = array_map(static fn ($c) => '`' . $c . '`', array_keys($payload));
        $ph   = array_map(static fn ($c) => ':' . $c, array_keys($payload));
        $cols[] = '`created_at`';
        $ph[]   = 'NOW()';

        $this->query(
            'INSERT INTO `incentive_project_budget_items` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')',
            $payload
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->fillablePayload($data);
        if ($payload === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($payload) as $c) {
            $sets[] = '`' . $c . '` = :' . $c;
        }
        $sets[] = '`updated_at` = NOW()';
        $payload['id'] = $id;

        $this->query('UPDATE `incentive_project_budget_items` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $payload);
    }

    public function archive(int|string $id): void
    {
        $this->query('UPDATE `incentive_project_budget_items` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function restore(int|string $id): void
    {
        $this->query('UPDATE `incentive_project_budget_items` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fillablePayload(array $data): array
    {
        $payload = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $payload[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }
        if (array_key_exists('is_capture_commission_item', $data)) {
            $payload['is_capture_commission_item'] = !empty($data['is_capture_commission_item']) ? 1 : 0;
        }

        return $payload;
    }
}
