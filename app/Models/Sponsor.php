<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Patrocinadores / Fechamentos Comerciais (Etapa 12).
 */
final class Sponsor extends Model
{
    protected string $table = 'sponsors';

    /** @var list<string> */
    private const FILLABLE = [
        'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'primary_document_id',
        'sponsor_display_name', 'sponsorship_type', 'funding_mechanism',
        'project_year', 'festival_edition', 'quota_snapshot_name', 'quota_snapshot_amount',
        'committed_amount', 'confirmed_amount', 'in_kind_description', 'in_kind_estimated_value',
        'status', 'payment_status', 'closed_at', 'confirmed_at', 'expected_payment_date', 'received_at',
        'public_announcement_allowed', 'pronac_number', 'incentive_law', 'incentive_notes',
        'responsible_user_id', 'notes', 'internal_notes',
        'created_by', 'updated_by', 'confirmed_by',
    ];

    private const LIST_COLUMNS =
        's.`id`, s.`company_id`, s.`contact_id`, s.`opportunity_id`, s.`proposal_id`, s.`quota_id`,
         s.`primary_document_id`, s.`sponsor_display_name`, s.`sponsorship_type`, s.`funding_mechanism`,
         s.`project_year`, s.`festival_edition`, s.`quota_snapshot_name`, s.`quota_snapshot_amount`,
         s.`committed_amount`, s.`confirmed_amount`, s.`in_kind_description`, s.`in_kind_estimated_value`,
         s.`status`, s.`payment_status`, s.`closed_at`, s.`confirmed_at`, s.`expected_payment_date`, s.`received_at`,
         s.`public_announcement_allowed`, s.`pronac_number`, s.`incentive_law`, s.`incentive_notes`,
         s.`responsible_user_id`, s.`notes`, s.`internal_notes`,
         s.`created_by`, s.`updated_by`, s.`confirmed_by`, s.`created_at`, s.`updated_at`, s.`archived_at`,
         co.`name` AS company_name,
         ct.`name` AS contact_name,
         o.`title` AS opportunity_title,
         pr.`title` AS proposal_title,
         q.`name` AS quota_name,
         ru.`name` AS responsible_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name,
         cfb.`name` AS confirmed_by_name,
         pd.`title` AS primary_document_title';

    /** @var array<string, string> */
    private const SPONSORSHIP_TYPES = [
        'patrocinio_direto'      => 'Patrocínio direto',
        'patrocinio_incentivado' => 'Patrocínio incentivado',
        'apoio_institucional'    => 'Apoio institucional',
        'permuta'                => 'Permuta',
        'bens_servicos'          => 'Bens e serviços',
        'midia'                  => 'Apoio de mídia',
        'pessoa_fisica'          => 'Pessoa física',
        'misto'                  => 'Misto',
        'outro'                  => 'Outro',
    ];

    /** @var array<string, string> */
    private const FUNDING_MECHANISMS = [
        'lei_rouanet'           => 'Lei Rouanet',
        'recurso_direto'        => 'Recurso direto',
        'recurso_proprio'       => 'Recurso próprio',
        'permuta_bens_servicos' => 'Permuta / bens e serviços',
        'apoio_institucional'   => 'Apoio institucional',
        'misto'                 => 'Misto',
        'nao_definido'          => 'Não definido',
        'outro'                 => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'fechamento_registrado' => 'Fechamento registrado',
        'aguardando_documentos' => 'Aguardando documentos',
        'aguardando_assinatura' => 'Aguardando assinatura',
        'aguardando_aporte'     => 'Aguardando aporte',
        'confirmado'            => 'Confirmado',
        'anunciado'             => 'Anunciado',
        'cancelado'             => 'Cancelado',
        'suspenso'              => 'Suspenso',
        'arquivado'             => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const PAYMENT_STATUSES = [
        'nao_aplicavel' => 'Não aplicável',
        'pendente'      => 'Pendente',
        'parcial'       => 'Parcial',
        'recebido'      => 'Recebido',
        'em_atraso'     => 'Em atraso',
        'cancelado'     => 'Cancelado',
    ];

    private const ORDER_BY =
        'CASE WHEN s.`status` = \'aguardando_aporte\' THEN 0 ELSE 1 END ASC,
         CASE WHEN s.`payment_status` = \'em_atraso\' THEN 0 ELSE 1 END ASC,
         s.`expected_payment_date` ASC,
         s.`closed_at` DESC,
         s.`sponsor_display_name` ASC';

    /** @return array<string, string> */
    public function getSponsorshipTypes(): array
    {
        return self::SPONSORSHIP_TYPES;
    }

    /** @return array<string, string> */
    public function getFundingMechanisms(): array
    {
        return self::FUNDING_MECHANISMS;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<string, string> */
    public function getPaymentStatuses(): array
    {
        return self::PAYMENT_STATUSES;
    }

    public function normalizeMoney(mixed $value): float|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

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
     * @param array<string, mixed> $sponsor
     */
    public function isOverdue(array $sponsor): bool
    {
        if (($sponsor['payment_status'] ?? '') === 'em_atraso') {
            return true;
        }

        $expected = $sponsor['expected_payment_date'] ?? null;
        $payment  = (string) ($sponsor['payment_status'] ?? '');

        if ($expected === null || $expected === '') {
            return false;
        }

        if (in_array($payment, ['recebido', 'nao_aplicavel', 'cancelado'], true)) {
            return false;
        }

        return $expected < date('Y-m-d');
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

        $name = trim((string) ($data['sponsor_display_name'] ?? ''));
        if ($name === '') {
            $errors['sponsor_display_name'] = 'Informe o nome de exibição do patrocinador.';
        } elseif (mb_strlen($name) < 2) {
            $errors['sponsor_display_name'] = 'O nome deve ter ao menos 2 caracteres.';
        }

        $type = (string) ($data['sponsorship_type'] ?? '');
        if ($type === '' || !array_key_exists($type, self::SPONSORSHIP_TYPES)) {
            $errors['sponsorship_type'] = 'Tipo de patrocínio inválido.';
        }

        $funding = (string) ($data['funding_mechanism'] ?? '');
        if ($funding === '' || !array_key_exists($funding, self::FUNDING_MECHANISMS)) {
            $errors['funding_mechanism'] = 'Mecanismo de fomento inválido.';
        }

        $status = (string) ($data['status'] ?? 'fechamento_registrado');
        if (!array_key_exists($status, self::STATUSES)) {
            $errors['status'] = 'Status do fechamento inválido.';
        }

        $payment = (string) ($data['payment_status'] ?? 'pendente');
        if (!array_key_exists($payment, self::PAYMENT_STATUSES)) {
            $errors['payment_status'] = 'Status de pagamento inválido.';
        }

        $year = (int) ($data['project_year'] ?? 2026);
        if ($year < 2026) {
            $errors['project_year'] = 'O ano do projeto deve ser 2026 ou posterior.';
        }

        foreach (['committed_amount', 'confirmed_amount', 'in_kind_estimated_value', 'quota_snapshot_amount'] as $moneyField) {
            if (!array_key_exists($moneyField, $data) || $data[$moneyField] === null || $data[$moneyField] === '') {
                continue;
            }
            $money = $this->normalizeMoney($data[$moneyField]);
            if (!is_float($money) || $money < 0) {
                $errors[$moneyField] = 'Informe um valor numérico válido (zero ou positivo).';
            }
        }

        foreach (['expected_payment_date', 'received_at'] as $dateField) {
            if (trim((string) ($data[$dateField] ?? '')) !== '' && $this->normalizeDate((string) $data[$dateField]) === null) {
                $errors[$dateField] = 'Data inválida.';
            }
        }

        foreach (['closed_at', 'confirmed_at'] as $dtField) {
            if (trim((string) ($data[$dtField] ?? '')) !== '' && $this->normalizeDateTime((string) $data[$dtField]) === null) {
                $errors[$dtField] = 'Data/hora inválida.';
            }
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
            $conditions[] = 's.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(s.`sponsor_display_name` LIKE :q1 OR co.`name` LIKE :q2
                OR s.`notes` LIKE :q3 OR s.`internal_notes` LIKE :q4 OR s.`pronac_number` LIKE :q5
                OR s.`incentive_law` LIKE :q6 OR s.`incentive_notes` LIKE :q7)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
            $params['q7'] = $like;
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 's.`' . $fk . '` = :' . $fk;
                $params[$fk]  = $v;
            }
        }

        if (!empty($filters['sponsorship_type'])) {
            $conditions[] = 's.`sponsorship_type` = :sponsorship_type';
            $params['sponsorship_type'] = (string) $filters['sponsorship_type'];
        }

        if (!empty($filters['funding_mechanism'])) {
            $conditions[] = 's.`funding_mechanism` = :funding_mechanism';
            $params['funding_mechanism'] = (string) $filters['funding_mechanism'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = 's.`status` = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $conditions[] = 's.`payment_status` = :payment_status';
            $params['payment_status'] = (string) $filters['payment_status'];
        }

        if (!empty($filters['project_year'])) {
            $conditions[] = 's.`project_year` = :project_year';
            $params['project_year'] = (int) $filters['project_year'];
        }

        if (!empty($filters['awaiting_contribution'])) {
            $conditions[] = 's.`status` = \'aguardando_aporte\'';
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = '(s.`payment_status` = \'em_atraso\'
                OR (s.`expected_payment_date` IS NOT NULL AND s.`expected_payment_date` < CURDATE()
                    AND s.`payment_status` NOT IN (\'recebido\',\'nao_aplicavel\',\'cancelado\')))';
        }

        if (!empty($filters['closed_from'])) {
            $d = $this->normalizeDate((string) $filters['closed_from']);
            if ($d !== null) {
                $conditions[] = 'DATE(s.`closed_at`) >= :closed_from';
                $params['closed_from'] = $d;
            }
        }

        if (!empty($filters['closed_to'])) {
            $d = $this->normalizeDate((string) $filters['closed_to']);
            if ($d !== null) {
                $conditions[] = 'DATE(s.`closed_at`) <= :closed_to';
                $params['closed_to'] = $d;
            }
        }

        if (!empty($filters['confirmed_from'])) {
            $d = $this->normalizeDate((string) $filters['confirmed_from']);
            if ($d !== null) {
                $conditions[] = 'DATE(s.`confirmed_at`) >= :confirmed_from';
                $params['confirmed_from'] = $d;
            }
        }

        if (!empty($filters['confirmed_to'])) {
            $d = $this->normalizeDate((string) $filters['confirmed_to']);
            if ($d !== null) {
                $conditions[] = 'DATE(s.`confirmed_at`) <= :confirmed_to';
                $params['confirmed_to'] = $d;
            }
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `sponsors` s
                 JOIN `companies` co ON co.`id` = s.`company_id`
                 LEFT JOIN `contacts` ct ON ct.`id` = s.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = s.`opportunity_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = s.`proposal_id`
                 LEFT JOIN `quotas` q ON q.`id` = s.`quota_id`
                 LEFT JOIN `users` ru ON ru.`id` = s.`responsible_user_id`
                 LEFT JOIN `users` cb ON cb.`id` = s.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = s.`updated_by`
                 LEFT JOIN `users` cfb ON cfb.`id` = s.`confirmed_by`
                 LEFT JOIN `documents` pd ON pd.`id` = s.`primary_document_id`';
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

        return (int) $this->query(
            'SELECT COUNT(*) AS c' . $this->fromJoins() . $where,
            $params
        )->fetchColumn();
    }

    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE s.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph   = array_map(static fn ($c) => ':' . $c, $cols);

        $this->query(
            'INSERT INTO `sponsors` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
            $row
        );

        return (string) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
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
            'UPDATE `sponsors` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $row
        );
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int|string $id, array $data): void
    {
        $allowed = ['status', 'payment_status', 'notes', 'confirmed_at', 'confirmed_by', 'received_at', 'updated_by', 'updated_at'];
        $patch   = array_intersect_key($data, array_flip($allowed));
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($patch) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $patch['id'] = $id;

        $this->query(
            'UPDATE `sponsors` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $patch
        );
    }

    public function confirm(int|string $id, int|string $userId): void
    {
        $sponsor = $this->findById($id);
        if ($sponsor === null) {
            return;
        }

        $confirmedAmount = $sponsor['confirmed_amount'];
        if ($confirmedAmount === null && $sponsor['committed_amount'] !== null) {
            $confirmedAmount = $sponsor['committed_amount'];
        }

        $this->query(
            'UPDATE `sponsors` SET
                `status` = \'confirmado\',
                `confirmed_at` = COALESCE(`confirmed_at`, NOW()),
                `confirmed_by` = COALESCE(`confirmed_by`, :uid),
                `confirmed_amount` = COALESCE(`confirmed_amount`, :camt),
                `updated_at` = NOW(),
                `updated_by` = :uid2
             WHERE `id` = :id',
            [
                'id'   => $id,
                'uid'  => $userId,
                'uid2' => $userId,
                'camt' => $confirmedAmount,
            ]
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsors` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsors` SET `archived_at` = NULL, `status` = \'fechamento_registrado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function countActive(): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) FROM `sponsors` WHERE `archived_at` IS NULL'
        )->fetchColumn();
    }

    public function countConfirmed(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `sponsors` WHERE `archived_at` IS NULL AND `status` = 'confirmado'"
        )->fetchColumn();
    }

    public function countAwaitingContribution(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `sponsors` WHERE `archived_at` IS NULL AND `status` = 'aguardando_aporte'"
        )->fetchColumn();
    }

    public function countOverdue(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `sponsors` WHERE `archived_at` IS NULL AND (
                `payment_status` = 'em_atraso'
                OR (`expected_payment_date` IS NOT NULL AND `expected_payment_date` < CURDATE()
                    AND `payment_status` NOT IN ('recebido','nao_aplicavel','cancelado'))
            )"
        )->fetchColumn();
    }

    public function sumCommitted(): float
    {
        return (float) ($this->query(
            'SELECT COALESCE(SUM(`committed_amount`), 0) FROM `sponsors` WHERE `archived_at` IS NULL'
        )->fetchColumn() ?: 0);
    }

    public function sumConfirmed(): float
    {
        return (float) ($this->query(
            'SELECT COALESCE(SUM(`confirmed_amount`), 0) FROM `sponsors` WHERE `archived_at` IS NULL'
        )->fetchColumn() ?: 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByCompany(int|string $companyId, int $limit = 10): array
    {
        return $this->findByFk('company_id', $companyId, $limit);
    }

    public function countByCompany(int|string $companyId): int
    {
        return $this->countByFk('company_id', $companyId);
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    public function summaryByCompany(int|string $companyId): array
    {
        return $this->summaryByFk('company_id', $companyId);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByContact(int|string $contactId, int $limit = 10): array
    {
        return $this->findByFk('contact_id', $contactId, $limit);
    }

    public function countByContact(int|string $contactId): int
    {
        return $this->countByFk('contact_id', $contactId);
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    public function summaryByContact(int|string $contactId): array
    {
        return $this->summaryByFk('contact_id', $contactId);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByOpportunity(int|string $opportunityId, int $limit = 10): array
    {
        return $this->findByFk('opportunity_id', $opportunityId, $limit);
    }

    public function countByOpportunity(int|string $opportunityId): int
    {
        return $this->countByFk('opportunity_id', $opportunityId);
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    public function summaryByOpportunity(int|string $opportunityId): array
    {
        return $this->summaryByFk('opportunity_id', $opportunityId);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByProposal(int|string $proposalId, int $limit = 10): array
    {
        return $this->findByFk('proposal_id', $proposalId, $limit);
    }

    public function countByProposal(int|string $proposalId): int
    {
        return $this->countByFk('proposal_id', $proposalId);
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    public function summaryByProposal(int|string $proposalId): array
    {
        return $this->summaryByFk('proposal_id', $proposalId);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByQuota(int|string $quotaId, int $limit = 10): array
    {
        return $this->findByFk('quota_id', $quotaId, $limit);
    }

    public function countByQuota(int|string $quotaId): int
    {
        return $this->countByFk('quota_id', $quotaId);
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    public function summaryByQuota(int|string $quotaId): array
    {
        return $this->summaryByFk('quota_id', $quotaId);
    }

    /** @return array<int, array<string, mixed>> */
    private function findByFk(string $column, int|string $id, int $limit): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins()
            . ' WHERE s.`' . $column . '` = :id AND s.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $limit,
            ['id' => $id]
        )->fetchAll();
    }

    private function countByFk(string $column, int|string $id): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) AS c FROM `sponsors` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetchColumn();
    }

    /** @return array{total:int,confirmed:int,committed:float,confirmed_amount:float} */
    private function summaryByFk(string $column, int|string $id): array
    {
        $row = $this->query(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `status` = \'confirmado\' THEN 1 ELSE 0 END) AS confirmed,
                    COALESCE(SUM(`committed_amount`), 0) AS committed,
                    COALESCE(SUM(`confirmed_amount`), 0) AS confirmed_amount
               FROM `sponsors`
              WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();

        return [
            'total'             => (int) ($row['total'] ?? 0),
            'confirmed'         => (int) ($row['confirmed'] ?? 0),
            'committed'         => (float) ($row['committed'] ?? 0),
            'confirmed_amount'  => (float) ($row['confirmed_amount'] ?? 0),
        ];
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

        foreach (['committed_amount', 'confirmed_amount', 'in_kind_estimated_value', 'quota_snapshot_amount'] as $m) {
            if (array_key_exists($m, $row) && $row[$m] !== null && $row[$m] !== '') {
                $norm = $this->normalizeMoney($row[$m]);
                $row[$m] = is_float($norm) ? $norm : null;
            } else {
                $row[$m] = null;
            }
        }

        if (array_key_exists('expected_payment_date', $row)) {
            $row['expected_payment_date'] = $this->normalizeDate((string) ($row['expected_payment_date'] ?? ''));
        }
        if (array_key_exists('received_at', $row)) {
            $row['received_at'] = $this->normalizeDate((string) ($row['received_at'] ?? ''));
        }
        if (array_key_exists('closed_at', $row)) {
            $row['closed_at'] = $this->normalizeDateTime((string) ($row['closed_at'] ?? ''));
        }
        if (array_key_exists('confirmed_at', $row)) {
            $row['confirmed_at'] = $this->normalizeDateTime((string) ($row['confirmed_at'] ?? ''));
        }

        $row['public_announcement_allowed'] = !empty($data['public_announcement_allowed']) ? 1 : 0;
        $row['project_year'] = (int) ($row['project_year'] ?? 2026);

        if ($isCreate && empty($row['closed_at'])) {
            $row['closed_at'] = date('Y-m-d H:i:s');
        }

        if (($row['status'] ?? '') === 'confirmado') {
            if (empty($row['confirmed_at'])) {
                $row['confirmed_at'] = date('Y-m-d H:i:s');
            }
        }

        return $row;
    }

    /** @param array<string, mixed> $data */
    public function applyQuotaSnapshot(array &$data): void
    {
        $quotaId = (int) ($data['quota_id'] ?? 0);
        if ($quotaId <= 0) {
            return;
        }

        $quota = (new Quota())->findById($quotaId);
        if ($quota === null) {
            return;
        }

        $data['quota_snapshot_name']   = $quota['name'] ?? $quota['commercial_name'] ?? null;
        $data['quota_snapshot_amount'] = $quota['amount'] ?? null;
    }
}
