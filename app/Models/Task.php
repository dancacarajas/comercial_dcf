<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Tarefas e Follow-ups (Etapa 8).
 *
 * Vínculos opcionais com empresa, contato e oportunidade (tarefas internas
 * são permitidas). Sem exclusão física (archived_at). Prepared statements,
 * colunas explícitas (sem SELECT *).
 */
final class Task extends Model
{
    protected string $table = 'tasks';

    /** Status "terminais" que não contam como abertos. */
    private const CLOSED_STATUSES = ['concluida', 'cancelada', 'arquivada'];

    private const FILLABLE = [
        'title', 'description', 'type',
        'company_id', 'contact_id', 'opportunity_id', 'assigned_user_id',
        'due_date', 'due_time', 'priority', 'status', 'result',
    ];

    /** Colunas da listagem (sem SELECT *). */
    private const LIST_COLUMNS =
        't.`id`, t.`title`, t.`type`, t.`company_id`, t.`contact_id`, t.`opportunity_id`,
         t.`assigned_user_id`, t.`due_date`, t.`due_time`, t.`priority`, t.`status`,
         t.`completed_at`, t.`updated_at`, t.`created_at`, t.`archived_at`,
         co.`name` AS company_name, ct.`name` AS contact_name,
         op.`title` AS opportunity_title, au.`name` AS assigned_name';

    // -----------------------------------------------------------------
    // Listas controladas
    // -----------------------------------------------------------------

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return [
            'ligacao'                    => 'Ligação',
            'whatsapp'                   => 'WhatsApp',
            'email'                      => 'E-mail',
            'reuniao'                    => 'Reunião',
            'envio_proposta'             => 'Envio de proposta',
            'follow_up'                  => 'Follow-up',
            'atualizacao_documentos'     => 'Atualização de documentos',
            'atualizacao_dados_oficiais' => 'Atualização pós-dados oficiais',
            'cobranca_retorno'           => 'Cobrança de retorno',
            'registro_reuniao'           => 'Registro de reunião',
            'envio_agradecimento'        => 'Envio de agradecimento',
            'outro'                      => 'Outro',
        ];
    }

    /** @return array<string, string> */
    public function getPriorities(): array
    {
        return [
            'baixa'   => 'Baixa',
            'normal'  => 'Normal',
            'alta'    => 'Alta',
            'critica' => 'Crítica',
        ];
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'pendente'     => 'Pendente',
            'em_andamento' => 'Em andamento',
            'concluida'    => 'Concluída',
            'atrasada'     => 'Atrasada',
            'cancelada'    => 'Cancelada',
            'reagendada'   => 'Reagendada',
            'arquivada'    => 'Arquivada',
        ];
    }

    // -----------------------------------------------------------------
    // Normalização / validação
    // -----------------------------------------------------------------

    public function normalizeDueDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? $value : date('Y-m-d', $ts);
    }

    public function normalizeDueTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $value)) {
            return $value;
        }

        return strlen($value) === 5 ? $value . ':00' : $value;
    }

    /**
     * Tarefa vencida (status calculado auxiliar).
     *
     * @param array<string, mixed> $task
     */
    public function isOverdue(array $task): bool
    {
        $status = (string) ($task['status'] ?? '');
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }
        $date = (string) ($task['due_date'] ?? '');
        if ($date === '' || $date === '0000-00-00') {
            return false;
        }

        $time = (string) ($task['due_time'] ?? '');
        $dueTs = strtotime($time !== '' ? ($date . ' ' . $time) : ($date . ' 23:59:59'));

        return $dueTs !== false && $dueTs < time();
    }

    /**
     * @param array<string, mixed> $data Dados já normalizados.
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título da tarefa.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter no mínimo 3 caracteres.';
        }

        $type = (string) ($data['type'] ?? '');
        if (!array_key_exists($type, $this->getTypes())) {
            $errors['type'] = 'Tipo de tarefa inválido.';
        }

        $priority = (string) ($data['priority'] ?? '');
        if (!array_key_exists($priority, $this->getPriorities())) {
            $errors['priority'] = 'Prioridade inválida.';
        }

        $status = (string) ($data['status'] ?? '');
        if (!array_key_exists($status, $this->getStatuses())) {
            $errors['status'] = 'Status inválido.';
        }

        $date = trim((string) ($data['due_date'] ?? ''));
        if ($date !== '' && strtotime($date) === false) {
            $errors['due_date'] = 'Data de vencimento inválida.';
        }

        $time = trim((string) ($data['due_time'] ?? ''));
        if ($time !== '' && !preg_match('/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time)) {
            $errors['due_time'] = 'Hora inválida.';
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
                  FROM `tasks` t
                  LEFT JOIN `companies` co ON co.`id` = t.`company_id`
                  LEFT JOIN `contacts` ct ON ct.`id` = t.`contact_id`
                  LEFT JOIN `opportunities` op ON op.`id` = t.`opportunity_id`
                  LEFT JOIN `users` au ON au.`id` = t.`assigned_user_id`'
            . $where .
            ' ORDER BY ' . $this->orderBy() . '
              LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query(
            'SELECT COUNT(*) AS c
               FROM `tasks` t
               LEFT JOIN `companies` co ON co.`id` = t.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = t.`contact_id`
               LEFT JOIN `opportunities` op ON op.`id` = t.`opportunity_id`
               LEFT JOIN `users` au ON au.`id` = t.`assigned_user_id`' . $where,
            $params
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT t.`id`, t.`title`, t.`description`, t.`type`,
                    t.`company_id`, t.`contact_id`, t.`opportunity_id`, t.`assigned_user_id`,
                    t.`due_date`, t.`due_time`, t.`priority`, t.`status`, t.`result`,
                    t.`completed_at`, t.`completed_by`,
                    t.`created_by`, t.`updated_by`, t.`created_at`, t.`updated_at`, t.`archived_at`,
                    co.`name` AS company_name, ct.`name` AS contact_name,
                    op.`title` AS opportunity_title,
                    au.`name` AS assigned_name, cpb.`name` AS completed_by_name,
                    cb.`name` AS created_by_name, ub.`name` AS updated_by_name
               FROM `tasks` t
               LEFT JOIN `companies` co ON co.`id` = t.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = t.`contact_id`
               LEFT JOIN `opportunities` op ON op.`id` = t.`opportunity_id`
               LEFT JOIN `users` au ON au.`id` = t.`assigned_user_id`
               LEFT JOIN `users` cpb ON cpb.`id` = t.`completed_by`
               LEFT JOIN `users` cb ON cb.`id` = t.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = t.`updated_by`
              WHERE t.`id` = :id
              LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lista resumida (não arquivadas) por empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCompany(int|string $companyId, int $limit = 10): array
    {
        return $this->linkedList('company_id', $companyId, $limit);
    }

    public function countByCompany(int|string $companyId): int
    {
        return $this->openCountBy('company_id', $companyId);
    }

    /**
     * @return array{open:int, overdue:int}
     */
    public function summaryByCompany(int|string $companyId): array
    {
        return $this->summaryBy('company_id', $companyId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByContact(int|string $contactId, int $limit = 10): array
    {
        return $this->linkedList('contact_id', $contactId, $limit);
    }

    public function countByContact(int|string $contactId): int
    {
        return $this->openCountBy('contact_id', $contactId);
    }

    /**
     * @return array{open:int, overdue:int}
     */
    public function summaryByContact(int|string $contactId): array
    {
        return $this->summaryBy('contact_id', $contactId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByOpportunity(int|string $opportunityId, int $limit = 10): array
    {
        return $this->linkedList('opportunity_id', $opportunityId, $limit);
    }

    public function countByOpportunity(int|string $opportunityId): int
    {
        return $this->openCountBy('opportunity_id', $opportunityId);
    }

    /**
     * @return array{open:int, overdue:int}
     */
    public function summaryByOpportunity(int|string $opportunityId): array
    {
        return $this->summaryBy('opportunity_id', $opportunityId);
    }

    public function countOpen(): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `tasks`
              WHERE `archived_at` IS NULL AND `status` NOT IN ('concluida','cancelada','arquivada')"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countOverdue(): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `tasks`
              WHERE `archived_at` IS NULL
                AND `status` NOT IN ('concluida','cancelada','arquivada')
                AND `due_date` IS NOT NULL
                AND (`due_date` < CURDATE()
                     OR (`due_date` = CURDATE() AND `due_time` IS NOT NULL AND `due_time` < CURTIME()))"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countDueToday(): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `tasks`
              WHERE `archived_at` IS NULL
                AND `status` NOT IN ('concluida','cancelada','arquivada')
                AND `due_date` = CURDATE()"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countMyOpen(int|string $userId): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `tasks`
              WHERE `archived_at` IS NULL
                AND `status` NOT IN ('concluida','cancelada','arquivada')
                AND `assigned_user_id` = :uid",
            ['uid' => $userId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
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
        foreach (['completed_at', 'completed_by', 'created_by'] as $extra) {
            if (array_key_exists($extra, $data)) {
                $payload[$extra] = $data[$extra];
            }
        }

        $columns      = array_keys($payload);
        $escaped      = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $escaped[]      = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `tasks` (%s) VALUES (%s)',
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
        foreach (['completed_at', 'completed_by', 'updated_by'] as $extra) {
            if (array_key_exists($extra, $data)) {
                $payload[$extra] = $data[$extra];
            }
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
            'UPDATE `tasks` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function complete(int|string $id, ?string $result, int|string|null $userId): void
    {
        $params = ['id' => $id, 'uid' => $userId];
        $resultSql = '';
        if ($result !== null && trim($result) !== '') {
            $resultSql = ', `result` = :result';
            $params['result'] = trim($result);
        }

        $this->query(
            "UPDATE `tasks`
                SET `status` = 'concluida', `completed_at` = NOW(), `completed_by` = :uid,
                    `updated_at` = NOW()" . $resultSql . '
              WHERE `id` = :id',
            $params
        );
    }

    public function reopen(int|string $id): void
    {
        $this->query(
            "UPDATE `tasks`
                SET `status` = 'pendente', `completed_at` = NULL, `completed_by` = NULL,
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `tasks` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `tasks` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Coluna de vínculo validada por allowlist. */
    private function safeLinkColumn(string $column): string
    {
        return in_array($column, ['company_id', 'contact_id', 'opportunity_id'], true)
            ? $column
            : 'company_id';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function linkedList(string $column, int|string $id, int $limit): array
    {
        $column = $this->safeLinkColumn($column);
        $limit  = max(1, $limit);

        return $this->query(
            'SELECT t.`id`, t.`title`, t.`type`, t.`due_date`, t.`due_time`,
                    t.`priority`, t.`status`, au.`name` AS assigned_name
               FROM `tasks` t
               LEFT JOIN `users` au ON au.`id` = t.`assigned_user_id`
              WHERE t.`' . $column . '` = :id AND t.`archived_at` IS NULL
              ORDER BY ' . $this->orderBy() . '
              LIMIT ' . $limit,
            ['id' => $id]
        )->fetchAll();
    }

    private function openCountBy(string $column, int|string $id): int
    {
        $column = $this->safeLinkColumn($column);
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `tasks`
              WHERE `' . $column . '` = :id AND `archived_at` IS NULL
                AND `status` NOT IN (\'concluida\',\'cancelada\',\'arquivada\')',
            ['id' => $id]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array{open:int, overdue:int}
     */
    private function summaryBy(string $column, int|string $id): array
    {
        $column = $this->safeLinkColumn($column);
        $row = $this->query(
            'SELECT
                SUM(CASE WHEN `status` NOT IN (\'concluida\',\'cancelada\',\'arquivada\') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN `status` NOT IN (\'concluida\',\'cancelada\',\'arquivada\')
                          AND `due_date` IS NOT NULL
                          AND (`due_date` < CURDATE()
                               OR (`due_date` = CURDATE() AND `due_time` IS NOT NULL AND `due_time` < CURTIME()))
                         THEN 1 ELSE 0 END) AS overdue_count
               FROM `tasks`
              WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();

        return [
            'open'    => (int) ($row['open_count'] ?? 0),
            'overdue' => (int) ($row['overdue_count'] ?? 0),
        ];
    }

    /** Ordenação padrão: vencidas, prioridade, vencimento, atualização, título. */
    private function orderBy(): string
    {
        return "(t.`status` NOT IN ('concluida','cancelada','arquivada')
                 AND t.`due_date` IS NOT NULL
                 AND (t.`due_date` < CURDATE()
                      OR (t.`due_date` = CURDATE() AND t.`due_time` IS NOT NULL AND t.`due_time` < CURTIME()))) DESC,
                FIELD(t.`priority`,'critica','alta','normal','baixa'),
                (t.`due_date` IS NULL), t.`due_date` ASC,
                (t.`due_time` IS NULL), t.`due_time` ASC,
                COALESCE(t.`updated_at`, t.`created_at`) DESC,
                t.`title` ASC";
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 't.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[]      = '(t.`title` LIKE :qt OR t.`description` LIKE :qd OR co.`name` LIKE :qc OR ct.`name` LIKE :qn OR op.`title` LIKE :qo)';
            $params['qt'] = '%' . $q . '%';
            $params['qd'] = '%' . $q . '%';
            $params['qc'] = '%' . $q . '%';
            $params['qn'] = '%' . $q . '%';
            $params['qo'] = '%' . $q . '%';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $conditions[]   = 't.`type` = :type';
            $params['type'] = $type;
        }

        $companyId = (int) ($filters['company_id'] ?? 0);
        if ($companyId > 0) {
            $conditions[]         = 't.`company_id` = :company_id';
            $params['company_id'] = $companyId;
        }

        $contactId = (int) ($filters['contact_id'] ?? 0);
        if ($contactId > 0) {
            $conditions[]         = 't.`contact_id` = :contact_id';
            $params['contact_id'] = $contactId;
        }

        $opportunityId = (int) ($filters['opportunity_id'] ?? 0);
        if ($opportunityId > 0) {
            $conditions[]             = 't.`opportunity_id` = :opportunity_id';
            $params['opportunity_id'] = $opportunityId;
        }

        $owner = (int) ($filters['assigned_user_id'] ?? 0);
        if ($owner > 0) {
            $conditions[]              = 't.`assigned_user_id` = :owner';
            $params['owner']           = $owner;
        }

        $priority = trim((string) ($filters['priority'] ?? ''));
        if ($priority !== '') {
            $conditions[]       = 't.`priority` = :priority';
            $params['priority'] = $priority;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[]     = 't.`status` = :status';
            $params['status'] = $status;
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = "(t.`status` NOT IN ('concluida','cancelada','arquivada')
                              AND t.`due_date` IS NOT NULL
                              AND (t.`due_date` < CURDATE()
                                   OR (t.`due_date` = CURDATE() AND t.`due_time` IS NOT NULL AND t.`due_time` < CURTIME())))";
        }

        if (!empty($filters['today'])) {
            $conditions[] = 't.`due_date` = CURDATE()';
        }

        if (!empty($filters['week'])) {
            $conditions[] = 'YEARWEEK(t.`due_date`, 1) = YEARWEEK(CURDATE(), 1)';
        }

        if (!empty($filters['mine']) && (int) ($filters['current_user_id'] ?? 0) > 0) {
            $conditions[]        = 't.`assigned_user_id` = :mine_uid';
            $params['mine_uid']  = (int) $filters['current_user_id'];
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
