<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Atribuição/reserva comercial de uma empresa a um captador (Etapa 18C — Fase 2).
 *
 * Formaliza, ANTES da abordagem, qual captador está autorizado a trabalhar uma
 * empresa. É a barreira contra dois captadores disputarem a mesma conta e a
 * base para a rastreabilidade comercial (collector_deals) e, futuramente, a
 * comissão (Fase 3).
 */
final class CollectorAssignment extends Model
{
    protected string $table = 'collector_assignments';

    /** @var list<string> */
    private const FILLABLE = [
        'collector_id', 'company_id', 'assignment_type', 'status', 'exclusive_until',
        'authorized_by', 'authorized_at', 'cancelled_at', 'notes',
        'created_by', 'updated_by',
    ];

    /** Status ativos (ocupam a empresa para fins de exclusividade). */
    public const ACTIVE_STATUSES = ['solicitada', 'autorizada'];

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return [
            'exclusiva'     => 'Exclusiva',
            'nao_exclusiva' => 'Não exclusiva',
        ];
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'solicitada'               => 'Solicitada',
            'autorizada'               => 'Autorizada',
            'negada'                   => 'Negada',
            'expirada'                 => 'Expirada',
            'cancelada'                => 'Cancelada',
            'convertida_em_oportunidade' => 'Convertida em oportunidade',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT a.*, c.`name` AS collector_name, c.`collector_code`,
                    co.`name` AS company_name, au.`name` AS authorized_by_name
               FROM `collector_assignments` a
               LEFT JOIN `collectors` c  ON c.`id`  = a.`collector_id`
               LEFT JOIN `companies` co  ON co.`id` = a.`company_id`
               LEFT JOIN `users` au      ON au.`id` = a.`authorized_by`
              WHERE a.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Lista as atribuições de um captador.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCollector(int|string $collectorId, bool $includeArchived = false): array
    {
        $sql = 'SELECT a.*, co.`name` AS company_name, au.`name` AS authorized_by_name
                  FROM `collector_assignments` a
                  LEFT JOIN `companies` co ON co.`id` = a.`company_id`
                  LEFT JOIN `users` au     ON au.`id` = a.`authorized_by`
                 WHERE a.`collector_id` = :cid';
        if (!$includeArchived) {
            $sql .= ' AND a.`archived_at` IS NULL';
        }
        $sql .= ' ORDER BY a.`created_at` DESC, a.`id` DESC';

        return $this->query($sql, ['cid' => $collectorId])->fetchAll();
    }

    /**
     * Lista as atribuições de uma empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forCompany(int|string $companyId, bool $includeArchived = false): array
    {
        $sql = 'SELECT a.*, c.`name` AS collector_name, c.`collector_code`
                  FROM `collector_assignments` a
                  LEFT JOIN `collectors` c ON c.`id` = a.`collector_id`
                 WHERE a.`company_id` = :coid';
        if (!$includeArchived) {
            $sql .= ' AND a.`archived_at` IS NULL';
        }
        $sql .= ' ORDER BY a.`created_at` DESC, a.`id` DESC';

        return $this->query($sql, ['coid' => $companyId])->fetchAll();
    }

    /**
     * Detecta conflito de exclusividade: já existe uma atribuição EXCLUSIVA ativa
     * para a empresa (de outro captador) cujo período (exclusive_until) ainda vigora.
     *
     * Regra: não permite duas exclusivas ativas e sobrepostas para a mesma empresa.
     * Atribuições não exclusivas nunca geram conflito.
     *
     * @return array<string, mixed>|null A atribuição conflitante, ou null se livre.
     */
    public function findExclusiveConflict(
        int|string $companyId,
        string $assignmentType,
        ?string $exclusiveUntil,
        int|string|null $ignoreId = null
    ): ?array {
        if ($assignmentType !== 'exclusiva') {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUSES), '?'));
        $params = [(int) $companyId];
        foreach (self::ACTIVE_STATUSES as $st) {
            $params[] = $st;
        }

        // Conflito apenas com outras EXCLUSIVAS ativas e não arquivadas.
        $sql = "SELECT a.*, c.`name` AS collector_name
                  FROM `collector_assignments` a
                  LEFT JOIN `collectors` c ON c.`id` = a.`collector_id`
                 WHERE a.`company_id` = ?
                   AND a.`assignment_type` = 'exclusiva'
                   AND a.`archived_at` IS NULL
                   AND a.`status` IN ({$placeholders})";

        if ($ignoreId !== null) {
            $sql .= ' AND a.`id` <> ?';
            $params[] = (int) $ignoreId;
        }

        // Sobreposição de período: uma exclusiva sem data (exclusive_until NULL) é
        // tratada como vigente por tempo indeterminado e sempre conflita; com data,
        // conflita enquanto a vigência existente não tiver terminado.
        if ($exclusiveUntil !== null && $exclusiveUntil !== '') {
            $sql .= ' AND (a.`exclusive_until` IS NULL OR a.`exclusive_until` >= CURDATE())';
        }

        $sql .= ' ORDER BY a.`id` DESC LIMIT 1';

        $row = $this->query($sql, $params)->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        if ((int) ($data['collector_id'] ?? 0) <= 0) {
            $errors['collector_id'] = 'Captador é obrigatório.';
        }
        if ((int) ($data['company_id'] ?? 0) <= 0) {
            $errors['company_id'] = 'Empresa é obrigatória.';
        }
        if (!array_key_exists((string) ($data['assignment_type'] ?? ''), $this->getTypes())) {
            $errors['assignment_type'] = 'Tipo de atribuição inválido.';
        }
        $until = trim((string) ($data['exclusive_until'] ?? ''));
        if ($until !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
            $errors['exclusive_until'] = 'Data de exclusividade inválida.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->filterFillable($data);
        $cols = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `collector_assignments` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')',
            $payload
        );

        return (string) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->filterFillable($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        if ($payload === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $payload['id'] = $id;
        $this->query(
            'UPDATE `collector_assignments` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function authorize(int|string $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `collector_assignments`
                SET `status` = :st, `authorized_by` = :uid, `authorized_at` = NOW(), `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => 'autorizada', 'uid' => $userId, 'id' => $id]
        );
    }

    public function cancel(int|string $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `collector_assignments`
                SET `status` = :st, `cancelled_at` = NOW(), `updated_by` = :uid, `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => 'cancelada', 'uid' => $userId, 'id' => $id]
        );
    }

    public function markConverted(int|string $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `collector_assignments`
                SET `status` = :st, `updated_by` = :uid, `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => 'convertida_em_oportunidade', 'uid' => $userId, 'id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $out[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }
        foreach (['collector_id', 'company_id', 'assignment_type', 'status'] as $req) {
            if (array_key_exists($req, $out) && $out[$req] === null) {
                unset($out[$req]);
            }
        }

        return $out;
    }
}
