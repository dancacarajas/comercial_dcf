<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Propostas Comerciais (Etapa 10).
 */
final class Proposal extends Model
{
    protected string $table = 'proposals';

    private const FILLABLE = [
        'incentive_project_id',
        'company_id', 'contact_id', 'opportunity_id', 'quota_id',
        'title', 'type', 'proposed_value', 'version_number', 'parent_proposal_id',
        'status', 'created_on', 'sent_at', 'valid_until', 'responsible_user_id',
        'pdf_file_path', 'pdf_original_name', 'revision_notes', 'notes',
    ];

    private const LIST_COLUMNS =
        'p.`id`, p.`incentive_project_id`, p.`company_id`, p.`contact_id`, p.`opportunity_id`, p.`quota_id`,
         p.`title`, p.`type`, p.`proposed_value`, p.`version_number`, p.`parent_proposal_id`,
         p.`status`, p.`created_on`, p.`sent_at`, p.`valid_until`, p.`responsible_user_id`,
         p.`pdf_file_path`, p.`pdf_original_name`, p.`revision_notes`, p.`notes`,
         p.`created_by`, p.`updated_by`, p.`sent_by`, p.`created_at`, p.`updated_at`, p.`archived_at`,
         co.`name` AS company_name,
         ct.`name` AS contact_name,
         o.`title` AS opportunity_title,
         q.`name` AS quota_name,
         ru.`name` AS responsible_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name,
         sb.`name` AS sent_by_name';

    private const TYPES = [
        'proposta_institucional_inicial' => 'Proposta institucional inicial',
        'proposta_por_cota'              => 'Proposta por cota',
        'proposta_personalizada'         => 'Proposta personalizada',
        'proposta_pos_reuniao'           => 'Proposta pós-reunião',
        'proposta_pos_dados_oficiais'    => 'Proposta pós-dados oficiais',
        'proposta_contrapartidas'        => 'Proposta de contrapartidas',
        'proposta_pessoa_fisica'         => 'Proposta para pessoa física',
    ];

    private const STATUSES = [
        'rascunho'                  => 'Rascunho',
        'pronta_para_envio'         => 'Pronta para envio',
        'enviada'                   => 'Enviada',
        'visualizada'               => 'Visualizada',
        'em_analise'                => 'Em análise',
        'ajustes_solicitados'       => 'Ajustes solicitados',
        'aprovada_internamente'     => 'Aprovada internamente',
        'aguardando_dados_oficiais' => 'Aguardando dados oficiais',
        'fechada'                   => 'Fechada',
        'recusada'                  => 'Recusada',
        'expirada'                  => 'Expirada',
        'arquivada'                 => 'Arquivada',
    ];

    private const CLOSED_STATUSES = ['fechada', 'recusada', 'expirada', 'arquivada'];

    private const ORDER_BY =
        'CASE WHEN p.`valid_until` IS NOT NULL AND p.`valid_until` < CURDATE()
              AND p.`status` NOT IN (\'fechada\',\'recusada\',\'expirada\',\'arquivada\') THEN 0 ELSE 1 END ASC,
         p.`status` ASC,
         p.`valid_until` ASC,
         p.`updated_at` DESC,
         p.`title` ASC';

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return self::TYPES;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<int, string> */
    public function getOpenStatuses(): array
    {
        return array_values(array_diff(array_keys(self::STATUSES), self::CLOSED_STATUSES));
    }

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

    public function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $dt = \DateTimeImmutable::createFromFormat('d/m/Y', $value);

        return $dt !== false ? $dt->format('Y-m-d') : null;
    }

    public function normalizeDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $value)) {
            return substr($value, 0, 16) . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            return str_replace('T', ' ', substr($value, 0, 16)) . ':00';
        }

        $dt = \DateTimeImmutable::createFromFormat('d/m/Y H:i', $value);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }

        $dt = \DateTimeImmutable::createFromFormat('d/m/Y', $value);

        return $dt !== false ? $dt->format('Y-m-d') . ' 00:00:00' : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId <= 0) {
            $errors['company_id'] = 'Selecione a empresa.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título da proposta.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }

        $type = (string) ($data['type'] ?? '');
        if ($type === '' || !array_key_exists($type, self::TYPES)) {
            $errors['type'] = 'Tipo de proposta inválido.';
        }

        $status = (string) ($data['status'] ?? 'rascunho');
        if (!array_key_exists($status, self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }

        $version = (int) ($data['version_number'] ?? 1);
        if ($version < 1) {
            $errors['version_number'] = 'A versão deve ser um inteiro maior ou igual a 1.';
        }

        if (array_key_exists('proposed_value', $data) && $data['proposed_value'] !== null && $data['proposed_value'] !== '') {
            $money = $this->normalizeMoney((string) $data['proposed_value']);
            if (!is_float($money) || $money < 0) {
                $errors['proposed_value'] = 'Informe um valor proposto válido (zero ou positivo).';
            }
        }

        $createdOn = $this->normalizeDate((string) ($data['created_on'] ?? ''));
        if (trim((string) ($data['created_on'] ?? '')) !== '' && $createdOn === null) {
            $errors['created_on'] = 'Data de criação inválida.';
        }

        $validUntil = $this->normalizeDate((string) ($data['valid_until'] ?? ''));
        if (trim((string) ($data['valid_until'] ?? '')) !== '' && $validUntil === null) {
            $errors['valid_until'] = 'Data de validade inválida.';
        }

        if ($createdOn !== null && $validUntil !== null && $validUntil < $createdOn) {
            $errors['valid_until'] = 'A validade não pode ser anterior à data de criação.';
        }

        if (trim((string) ($data['sent_at'] ?? '')) !== '' && $this->normalizeDateTime((string) $data['sent_at']) === null) {
            $errors['sent_at'] = 'Data de envio inválida.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'p.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(p.`title` LIKE :q OR co.`name` LIKE :q OR ct.`name` LIKE :q
                OR o.`title` LIKE :q OR p.`notes` LIKE :q OR p.`revision_notes` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'p.`' . $fk . '` = :' . $fk;
                $params[$fk]  = $v;
            }
        }

        if (!empty($filters['type'])) {
            $conditions[] = 'p.`type` = :type';
            $params['type'] = (string) $filters['type'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 'p.`status` = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (!empty($filters['sent'])) {
            $conditions[] = 'p.`sent_at` IS NOT NULL';
        }

        if (!empty($filters['not_sent'])) {
            $conditions[] = 'p.`sent_at` IS NULL';
        }

        if (!empty($filters['expired'])) {
            $conditions[] = 'p.`valid_until` IS NOT NULL AND p.`valid_until` < CURDATE()
                AND p.`status` NOT IN (\'fechada\',\'recusada\',\'expirada\',\'arquivada\')';
        }

        if (!empty($filters['valid_from'])) {
            $d = $this->normalizeDate((string) $filters['valid_from']);
            if ($d !== null) {
                $conditions[] = 'p.`valid_until` >= :valid_from';
                $params['valid_from'] = $d;
            }
        }

        if (!empty($filters['valid_to'])) {
            $d = $this->normalizeDate((string) $filters['valid_to']);
            if ($d !== null) {
                $conditions[] = 'p.`valid_until` <= :valid_to';
                $params['valid_to'] = $d;
            }
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `proposals` p
                 JOIN `companies` co ON co.`id` = p.`company_id`
                 LEFT JOIN `contacts` ct ON ct.`id` = p.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = p.`opportunity_id`
                 LEFT JOIN `quotas` q ON q.`id` = p.`quota_id`
                 LEFT JOIN `users` ru ON ru.`id` = p.`responsible_user_id`
                 LEFT JOIN `users` cb ON cb.`id` = p.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = p.`updated_by`
                 LEFT JOIN `users` sb ON sb.`id` = p.`sent_by`';
    }

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

        $sql = 'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . $where
            . ' ORDER BY ' . self::ORDER_BY
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query(
            'SELECT COUNT(*) AS c' . $this->fromJoins() . $where,
            $params
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE p.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    public function countOpen(): int
    {
        $open = implode(',', array_map(static fn ($s) => "'" . $s . "'", $this->getOpenStatuses()));
        $row  = $this->query(
            "SELECT COUNT(*) AS c FROM `proposals`
              WHERE `archived_at` IS NULL AND `status` IN ($open)"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countSent(): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `proposals`
              WHERE `archived_at` IS NULL AND `sent_at` IS NOT NULL"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countExpired(): int
    {
        return $this->count(['show_archived' => 0, 'expired' => 1]);
    }

    public function sumOpenValue(): float
    {
        $open = implode(',', array_map(static fn ($s) => "'" . $s . "'", $this->getOpenStatuses()));
        $row  = $this->query(
            "SELECT COALESCE(SUM(`proposed_value`), 0) AS total
               FROM `proposals`
              WHERE `archived_at` IS NULL AND `status` IN ($open)"
        )->fetch();

        return (float) ($row['total'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByCompany(int|string $companyId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT p.`id`, p.`title`, p.`type`, p.`status`, p.`proposed_value`, p.`version_number`,
                    p.`sent_at`, p.`valid_until`, ct.`name` AS contact_name
               FROM `proposals` p
               LEFT JOIN `contacts` ct ON ct.`id` = p.`contact_id`
              WHERE p.`company_id` = :id AND p.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . '
              LIMIT ' . $limit,
            ['id' => $companyId]
        )->fetchAll();
    }

    public function countByCompany(int|string $companyId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `proposals` WHERE `company_id` = :id AND `archived_at` IS NULL',
            ['id' => $companyId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{total:int,sent:int,open:int,total_value:float} */
    public function summaryByCompany(int|string $companyId): array
    {
        $row = $this->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `sent_at` IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN `status` NOT IN ('fechada','recusada','expirada','arquivada') THEN 1 ELSE 0 END) AS open_count,
                COALESCE(SUM(`proposed_value`), 0) AS total_value
               FROM `proposals`
              WHERE `company_id` = :id AND `archived_at` IS NULL",
            ['id' => $companyId]
        )->fetch();

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'sent'        => (int) ($row['sent'] ?? 0),
            'open'        => (int) ($row['open_count'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function findByContact(int|string $contactId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT p.`id`, p.`title`, p.`type`, p.`status`, p.`proposed_value`, p.`version_number`,
                    p.`sent_at`, p.`valid_until`, co.`name` AS company_name
               FROM `proposals` p
               JOIN `companies` co ON co.`id` = p.`company_id`
              WHERE p.`contact_id` = :id AND p.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . '
              LIMIT ' . $limit,
            ['id' => $contactId]
        )->fetchAll();
    }

    public function countByContact(int|string $contactId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `proposals` WHERE `contact_id` = :id AND `archived_at` IS NULL',
            ['id' => $contactId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{total:int,sent:int,open:int,total_value:float} */
    public function summaryByContact(int|string $contactId): array
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `sent_at` IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN `status` NOT IN ('fechada','recusada','expirada','arquivada') THEN 1 ELSE 0 END) AS open_count,
                    COALESCE(SUM(`proposed_value`), 0) AS total_value
               FROM `proposals` WHERE `contact_id` = :id AND `archived_at` IS NULL",
            ['id' => $contactId]
        )->fetch();

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'sent'        => (int) ($row['sent'] ?? 0),
            'open'        => (int) ($row['open_count'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function findByOpportunity(int|string $opportunityId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT p.`id`, p.`title`, p.`type`, p.`status`, p.`proposed_value`, p.`version_number`,
                    p.`sent_at`, p.`valid_until`, co.`name` AS company_name
               FROM `proposals` p
               JOIN `companies` co ON co.`id` = p.`company_id`
              WHERE p.`opportunity_id` = :id AND p.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . '
              LIMIT ' . $limit,
            ['id' => $opportunityId]
        )->fetchAll();
    }

    public function countByOpportunity(int|string $opportunityId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `proposals` WHERE `opportunity_id` = :id AND `archived_at` IS NULL',
            ['id' => $opportunityId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{total:int,sent:int,open:int,total_value:float} */
    public function summaryByOpportunity(int|string $opportunityId): array
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `sent_at` IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN `status` NOT IN ('fechada','recusada','expirada','arquivada') THEN 1 ELSE 0 END) AS open_count,
                    COALESCE(SUM(`proposed_value`), 0) AS total_value
               FROM `proposals` WHERE `opportunity_id` = :id AND `archived_at` IS NULL",
            ['id' => $opportunityId]
        )->fetch();

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'sent'        => (int) ($row['sent'] ?? 0),
            'open'        => (int) ($row['open_count'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function findByQuota(int|string $quotaId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT p.`id`, p.`title`, p.`type`, p.`status`, p.`proposed_value`, p.`version_number`,
                    p.`sent_at`, p.`valid_until`, co.`name` AS company_name
               FROM `proposals` p
               JOIN `companies` co ON co.`id` = p.`company_id`
              WHERE p.`quota_id` = :id AND p.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . '
              LIMIT ' . $limit,
            ['id' => $quotaId]
        )->fetchAll();
    }

    public function countByQuota(int|string $quotaId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `proposals` WHERE `quota_id` = :id AND `archived_at` IS NULL',
            ['id' => $quotaId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{total:int,sent:int,open:int,total_value:float} */
    public function summaryByQuota(int|string $quotaId): array
    {
        $row = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `sent_at` IS NOT NULL THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN `status` NOT IN ('fechada','recusada','expirada','arquivada') THEN 1 ELSE 0 END) AS open_count,
                    COALESCE(SUM(`proposed_value`), 0) AS total_value
               FROM `proposals` WHERE `quota_id` = :id AND `archived_at` IS NULL",
            ['id' => $quotaId]
        )->fetch();

        return [
            'total'       => (int) ($row['total'] ?? 0),
            'sent'        => (int) ($row['sent'] ?? 0),
            'open'        => (int) ($row['open_count'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function filterLinkOptions(string $table, string $labelCol): array
    {
        if (!in_array($table, ['contacts', 'opportunities', 'proposals'], true)) {
            return [];
        }

        $labelCol = preg_replace('/[^a-zA-Z0-9_]/', '', $labelCol) ?? 'name';

        return $this->query(
            "SELECT `id`, `{$labelCol}` AS label FROM `{$table}` WHERE `archived_at` IS NULL ORDER BY `{$labelCol}` ASC LIMIT 300"
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph   = array_map(static fn ($c) => ':' . $c, $cols);

        $this->query(
            'INSERT INTO `proposals` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
            $row
        );

        return (string) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int|string $id, array $data): void
    {
        $row = $this->prepareRow($data, false);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($row) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $row['id'] = $id;

        $this->query(
            'UPDATE `proposals` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $row
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createVersion(int|string $baseId, array $data): string
    {
        $base = $this->findById($baseId);
        if ($base === null) {
            throw new \InvalidArgumentException('Proposta base não encontrada.');
        }

        $merged = array_merge([
            'incentive_project_id' => $base['incentive_project_id'],
            'company_id'          => $base['company_id'],
            'contact_id'          => $base['contact_id'],
            'opportunity_id'      => $base['opportunity_id'],
            'quota_id'            => $base['quota_id'],
            'title'               => $base['title'],
            'type'                => $base['type'],
            'proposed_value'      => $base['proposed_value'],
            'version_number'      => (int) $base['version_number'] + 1,
            'parent_proposal_id'  => (int) $baseId,
            'status'              => 'rascunho',
            'created_on'          => date('Y-m-d'),
            'valid_until'         => $base['valid_until'],
            'responsible_user_id' => $base['responsible_user_id'],
            'revision_notes'      => $base['revision_notes'],
            'notes'               => $base['notes'],
        ], $data);

        $merged['sent_at'] = null;
        $merged['sent_by'] = null;

        return $this->create($merged);
    }

    public function markSent(int|string $id, int|string $userId): void
    {
        $this->query(
            "UPDATE `proposals`
                SET `status` = 'enviada',
                    `sent_at` = COALESCE(`sent_at`, NOW()),
                    `sent_by` = COALESCE(`sent_by`, :uid),
                    `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id, 'uid' => $userId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(int|string $id, array $data): void
    {
        $fields = ['status' => (string) $data['status'], 'updated_at' => date('Y-m-d H:i:s')];
        $sets   = ['`status` = :status', '`updated_at` = :updated_at'];

        if (($data['status'] ?? '') === 'enviada' && !empty($data['fill_sent'])) {
            $sets[] = '`sent_at` = COALESCE(`sent_at`, NOW())';
            if (!empty($data['sent_by'])) {
                $sets[]           = '`sent_by` = COALESCE(`sent_by`, :sent_by)';
                $fields['sent_by'] = $data['sent_by'];
            }
        }

        if (!empty($data['notes_append'])) {
            $sets[] = '`notes` = CONCAT(COALESCE(`notes`, \'\'), :notes_append)';
            $fields['notes_append'] = $data['notes_append'];
        }

        $fields['id'] = $id;
        $this->query('UPDATE `proposals` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $fields);
    }

    public function archive(int|string $id): void
    {
        $this->query(
            "UPDATE `proposals`
                SET `archived_at` = NOW(), `status` = 'arquivada', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            "UPDATE `proposals`
                SET `archived_at` = NULL, `status` = 'rascunho', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    public function validateUpload(array $file): array
    {
        $errors = [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $errors;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors['pdf_file'] = 'Falha no upload do PDF.';
            return $errors;
        }

        $maxBytes = 10 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            $errors['pdf_file'] = 'O PDF deve ter no máximo 10 MB.';
        }

        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            $errors['pdf_file'] = 'Envie somente arquivos PDF.';
            return $errors;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_uploaded_file($tmp)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo !== false ? (string) finfo_file($finfo, $tmp) : '';
            if ($finfo !== false) {
                finfo_close($finfo);
            }
            if ($mime !== '' && $mime !== 'application/pdf') {
                $errors['pdf_file'] = 'O arquivo enviado não é um PDF válido.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:string,original_name:string}
     */
    public function storePdfUpload(array $file): array
    {
        $dir = dirname(__DIR__, 2) . '/storage/uploads/proposals';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $original = basename((string) ($file['name'] ?? 'proposta.pdf'));
        $original = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original) ?? 'proposta.pdf';
        $stored   = bin2hex(random_bytes(16)) . '.pdf';
        $dest     = $dir . '/' . $stored;

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new \RuntimeException('Não foi possível salvar o PDF.');
        }

        return ['path' => $dest, 'original_name' => $original];
    }

    /** @param array<string, mixed> $proposal */
    public function isExpired(array $proposal): bool
    {
        $valid = (string) ($proposal['valid_until'] ?? '');
        if ($valid === '') {
            return false;
        }

        $status = (string) ($proposal['status'] ?? '');
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }

        return $valid < date('Y-m-d');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareRow(array $data, bool $isCreate = true): array
    {
        $row = [];
        foreach (self::FILLABLE as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $row[$col] = $data[$col];
        }

        if (isset($row['proposed_value']) && $row['proposed_value'] !== null && $row['proposed_value'] !== '') {
            $m = $this->normalizeMoney((string) $row['proposed_value']);
            $row['proposed_value'] = is_float($m) ? $m : null;
        } else {
            $row['proposed_value'] = null;
        }

        foreach (['created_on', 'valid_until'] as $d) {
            if (isset($row[$d])) {
                $row[$d] = $this->normalizeDate((string) $row[$d]);
            }
        }

        if (isset($row['sent_at'])) {
            $row['sent_at'] = $this->normalizeDateTime((string) $row['sent_at']);
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'version_number',
            'parent_proposal_id', 'responsible_user_id', 'created_by', 'updated_by', 'sent_by',
            'incentive_project_id'] as $intCol) {
            if (array_key_exists($intCol, $row)) {
                $row[$intCol] = $row[$intCol] !== null && $row[$intCol] !== '' ? (int) $row[$intCol] : null;
            }
        }

        if ($isCreate) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }
}
