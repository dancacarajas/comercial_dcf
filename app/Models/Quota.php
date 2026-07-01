<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Cotas de Patrocínio (Etapa 7).
 *
 * Quantidades manuais (available/reserved/closed) + resumo calculado a partir
 * das oportunidades vinculadas (apoio, não sobrescreve os campos manuais).
 * Sem exclusão física (archived_at). Prepared statements, sem SELECT *.
 */
final class Quota extends Model
{
    protected string $table = 'quotas';

    /** Nome da cota com quantidade flexível (não trava reservada+fechada). */
    public const FLEXIBLE_NAME = 'Círculo Dança Carajás';

    private const FILLABLE = [
        'incentive_project_id',
        'name', 'commercial_name', 'amount',
        'available_quantity', 'reserved_quantity', 'closed_quantity',
        'description', 'ideal_profile', 'status', 'display_order', 'notes',
    ];

    private const LIST_COLUMNS =
        'q.`id`, q.`name`, q.`commercial_name`, q.`amount`,
         q.`available_quantity`, q.`reserved_quantity`, q.`closed_quantity`,
         q.`status`, q.`display_order`, q.`archived_at`';

    // -----------------------------------------------------------------
    // Listas controladas
    // -----------------------------------------------------------------

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'disponivel'                   => 'Disponível',
            'em_negociacao'                => 'Em negociação',
            'reservada'                    => 'Reservada',
            'fechada'                      => 'Fechada',
            'suspensa'                     => 'Suspensa',
            'ajustada_apos_dados_oficiais' => 'Ajustada após dados oficiais',
            'arquivada'                    => 'Arquivada',
        ];
    }

    /** @return array<string, string> */
    public function getIdealProfiles(): array
    {
        return [
            'master_apresentacao'  => 'Master / Apresentação',
            'grande_patrocinador'  => 'Grande patrocinador',
            'patrocinador_medio'   => 'Patrocinador médio',
            'formacao_educacao'    => 'Formação / Educação',
            'incentivador_final'   => 'Incentivador final',
            'flexivel'             => 'Flexível',
            'institucional'        => 'Institucional',
            'outro'                => 'Outro',
        ];
    }

    // -----------------------------------------------------------------
    // Normalização / validação
    // -----------------------------------------------------------------

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
        if (!is_numeric($clean)) {
            return $value;
        }

        return (float) $clean;
    }

    /**
     * Saldo de quantidade = disponível - (reservada + fechada).
     *
     * @param array<string, mixed> $quota
     */
    public function remainingQuantity(array $quota): int
    {
        $available = (int) ($quota['available_quantity'] ?? 0);
        $reserved  = (int) ($quota['reserved_quantity'] ?? 0);
        $closed    = (int) ($quota['closed_quantity'] ?? 0);

        return $available - ($reserved + $closed);
    }

    /**
     * @param array<string, mixed> $data Dados já normalizados.
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome da cota.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter no mínimo 2 caracteres.';
        }

        $amount = $data['amount'] ?? null;
        if (is_string($amount)) {
            $errors['amount'] = 'Valor inválido.';
        } elseif ($amount !== null && (float) $amount < 0) {
            $errors['amount'] = 'O valor deve ser positivo ou zero.';
        }

        foreach (['available_quantity' => 'Quantidade disponível', 'reserved_quantity' => 'Quantidade reservada', 'closed_quantity' => 'Quantidade fechada'] as $field => $label) {
            $v = $data[$field] ?? 0;
            if (!is_numeric($v) || (int) $v < 0) {
                $errors[$field] = $label . ' deve ser um inteiro maior ou igual a zero.';
            }
        }

        // Reservada + fechada não pode ultrapassar disponível (exceto cota flexível).
        if (!isset($errors['available_quantity'], $errors['reserved_quantity'], $errors['closed_quantity'])
            && $name !== self::FLEXIBLE_NAME) {
            $available = (int) ($data['available_quantity'] ?? 0);
            $reserved  = (int) ($data['reserved_quantity'] ?? 0);
            $closed    = (int) ($data['closed_quantity'] ?? 0);
            if (($reserved + $closed) > $available) {
                $errors['reserved_quantity'] = 'Reservada + fechada não pode ultrapassar a quantidade disponível.';
            }
        }

        $status = (string) ($data['status'] ?? '');
        if (!array_key_exists($status, $this->getStatuses())) {
            $errors['status'] = 'Status inválido.';
        }

        $profile = (string) ($data['ideal_profile'] ?? '');
        if ($profile !== '' && !array_key_exists($profile, $this->getIdealProfiles())) {
            $errors['ideal_profile'] = 'Perfil indicado inválido.';
        }

        $order = $data['display_order'] ?? 0;
        if (!is_numeric($order) || (int) $order < 0) {
            $errors['display_order'] = 'A ordem deve ser um inteiro maior ou igual a zero.';
        }

        return $errors;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$where, $params] = $this->buildWhere($filters);

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $sql = 'SELECT ' . self::LIST_COLUMNS . '
                  FROM `quotas` q'
            . $where .
            ' ORDER BY q.`display_order` ASC, q.`amount` DESC, q.`name` ASC
              LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->query('SELECT COUNT(*) AS c FROM `quotas` q' . $where, $params)->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT q.`id`, q.`incentive_project_id`, q.`name`, q.`commercial_name`, q.`amount`,
                    q.`available_quantity`, q.`reserved_quantity`, q.`closed_quantity`,
                    q.`description`, q.`ideal_profile`, q.`status`, q.`display_order`, q.`notes`,
                    q.`created_by`, q.`updated_by`, q.`created_at`, q.`updated_at`, q.`archived_at`,
                    cb.`name` AS created_by_name, ub.`name` AS updated_by_name
               FROM `quotas` q
               LEFT JOIN `users` cb ON cb.`id` = q.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = q.`updated_by`
              WHERE q.`id` = :id
              LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Cotas ativas (não arquivadas) para selects de oportunidade.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeOptions(): array
    {
        return $this->query(
            "SELECT `id`, `name`, `commercial_name`, `amount`, `status`
               FROM `quotas`
              WHERE `archived_at` IS NULL
              ORDER BY `display_order` ASC, `amount` DESC, `name` ASC"
        )->fetchAll();
    }

    /**
     * Resumo calculado das oportunidades vinculadas (apoio aos campos manuais).
     *
     * @return array{total:int, open:int, reserved:int, closed:int, est_value:float}
     */
    public function linkedOpportunitiesSummary(int|string $quotaId): array
    {
        $row = $this->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `status` NOT IN ('fechado','perdido') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN `status` = 'reserva_de_cota' THEN 1 ELSE 0 END) AS reserved_count,
                SUM(CASE WHEN `status` = 'fechado' THEN 1 ELSE 0 END) AS closed_count,
                COALESCE(SUM(`estimated_value`), 0) AS est_value
               FROM `opportunities`
              WHERE `quota_id` = :id AND `archived_at` IS NULL",
            ['id' => $quotaId]
        )->fetch();

        return [
            'total'     => (int) ($row['total'] ?? 0),
            'open'      => (int) ($row['open_count'] ?? 0),
            'reserved'  => (int) ($row['reserved_count'] ?? 0),
            'closed'    => (int) ($row['closed_count'] ?? 0),
            'est_value' => (float) ($row['est_value'] ?? 0),
        ];
    }

    /**
     * Lista resumida de oportunidades vinculadas à cota.
     *
     * @return array<int, array<string, mixed>>
     */
    public function linkedOpportunities(int|string $quotaId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT o.`id`, o.`title`, o.`status`, o.`estimated_value`, o.`probability`,
                    o.`next_action_at`, co.`name` AS company_name, ct.`name` AS contact_name
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
              WHERE o.`quota_id` = :id AND o.`archived_at` IS NULL
              ORDER BY o.`probability` DESC, COALESCE(o.`updated_at`, o.`created_at`) DESC
              LIMIT ' . $limit,
            ['id' => $quotaId]
        )->fetchAll();
    }

    // -----------------------------------------------------------------
    // Escrita (sem exclusão física)
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);
        if (isset($data['created_by'])) {
            $payload['created_by'] = $data['created_by'];
        }

        $columns      = array_keys($payload);
        $escaped      = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $escaped[]      = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `quotas` (%s) VALUES (%s)',
            implode(', ', $escaped),
            implode(', ', $placeholders)
        );

        $this->query($sql, $payload);

        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->fillablePayload($data);
        if (isset($data['updated_by'])) {
            $payload['updated_by'] = $data['updated_by'];
        }
        if ($payload === []) {
            return;
        }

        $sets = [];
        foreach (array_keys($payload) as $c) {
            $sets[] = '`' . $c . '` = :' . $c;
        }
        $sets[] = '`updated_at` = NOW()';

        $payload['id'] = $id;

        $this->query(
            'UPDATE `quotas` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            "UPDATE `quotas`
                SET `archived_at` = NOW(), `status` = 'arquivada', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    public function restore(int|string $id, string $status = 'disponivel'): void
    {
        if (!array_key_exists($status, $this->getStatuses()) || $status === 'arquivada') {
            $status = 'disponivel';
        }

        $this->query(
            'UPDATE `quotas`
                SET `archived_at` = NULL, `status` = :status, `updated_at` = NOW()
              WHERE `id` = :id',
            ['status' => $status, 'id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'q.`archived_at` IS NULL';
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $conditions[]  = '(q.`name` LIKE :qn OR q.`commercial_name` LIKE :qc OR q.`description` LIKE :qd)';
            $params['qn']  = '%' . $search . '%';
            $params['qc']  = '%' . $search . '%';
            $params['qd']  = '%' . $search . '%';
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[]     = 'q.`status` = :status';
            $params['status'] = $status;
        }

        if (isset($filters['amount_min']) && $filters['amount_min'] !== '') {
            $conditions[]         = 'q.`amount` >= :amount_min';
            $params['amount_min'] = (float) $filters['amount_min'];
        }
        if (isset($filters['amount_max']) && $filters['amount_max'] !== '') {
            $conditions[]         = 'q.`amount` <= :amount_max';
            $params['amount_max'] = (float) $filters['amount_max'];
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function fillablePayload(array $data): array
    {
        $payload = [];
        foreach (self::FILLABLE as $column) {
            if (array_key_exists($column, $data)) {
                $payload[$column] = $data[$column];
            }
        }

        return $payload;
    }
}
