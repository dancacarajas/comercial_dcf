<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de snapshots de relatórios gerenciais (Etapa 17).
 */
final class ReportSnapshot extends Model
{
    protected string $table = 'report_snapshots';

    /** @var list<string> */
    private const FILLABLE = [
        'report_key', 'title', 'description',
        'period_start', 'period_end',
        'filters_json', 'metrics_json', 'summary_json',
        'notes', 'internal_notes', 'status',
        'generated_by', 'created_by', 'updated_by', 'generated_at',
    ];

    private const LIST_COLUMNS =
        'rs.`id`, rs.`report_key`, rs.`title`, rs.`description`,
         rs.`period_start`, rs.`period_end`,
         rs.`filters_json`, rs.`metrics_json`, rs.`summary_json`,
         rs.`notes`, rs.`internal_notes`, rs.`status`,
         rs.`generated_by`, rs.`created_by`, rs.`updated_by`,
         rs.`generated_at`, rs.`created_at`, rs.`updated_at`, rs.`archived_at`,
         gb.`name` AS generated_by_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name';

    /** @var array<string, string> */
    private const STATUSES = [
        'gerado'    => 'Gerado',
        'revisado'  => 'Revisado',
        'arquivado' => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const REPORT_KEYS = [
        'executive'    => 'Executivo / consolidado',
        'pipeline'     => 'Funil comercial',
        'proposals'    => 'Propostas',
        'sponsors'     => 'Patrocinadores',
        'financials'   => 'Financeiro',
        'contracts'    => 'Contratos',
        'counterparts' => 'Contrapartidas',
        'dossiers'     => 'Dossiês / prestação de contas',
        'tasks'        => 'Tarefas e pendências',
        'leads'        => 'Leads do site',
    ];

    private const ORDER_BY =
        'CASE WHEN rs.`archived_at` IS NULL THEN 0 ELSE 1 END ASC,
         rs.`generated_at` DESC,
         rs.`updated_at` DESC,
         rs.`title` ASC';

    /** @return array<string, string> */
    public function getReportKeys(): array
    {
        return self::REPORT_KEYS;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    public function encodeJson(mixed $data): ?string
    {
        if ($data === null) {
            return null;
        }
        if (is_string($data)) {
            return $data;
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    public function decodeJson(?string $json): mixed
    {
        $json = trim((string) $json);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
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

    /** @param array<string, mixed> $data @return array<string, string> */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $reportKey = trim((string) ($data['report_key'] ?? ''));
        if ($reportKey === '') {
            $errors['report_key'] = 'Selecione o tipo de relatório.';
        } elseif (!array_key_exists($reportKey, self::REPORT_KEYS)) {
            $errors['report_key'] = 'Tipo de relatório inválido.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título do snapshot.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }

        $status = (string) ($data['status'] ?? 'gerado');
        if (!array_key_exists($status, self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }

        $periodStart = $this->normalizeDate((string) ($data['period_start'] ?? ''));
        $periodEnd = $this->normalizeDate((string) ($data['period_end'] ?? ''));
        if (trim((string) ($data['period_start'] ?? '')) !== '' && $periodStart === null) {
            $errors['period_start'] = 'Data inicial inválida.';
        }
        if (trim((string) ($data['period_end'] ?? '')) !== '' && $periodEnd === null) {
            $errors['period_end'] = 'Data final inválida.';
        }
        if ($periodStart !== null && $periodEnd !== null && $periodEnd < $periodStart) {
            $errors['period_end'] = 'A data final não pode ser anterior à data inicial.';
        }

        foreach (['filters_json', 'metrics_json', 'summary_json'] as $jsonField) {
            if (!array_key_exists($jsonField, $data) || $data[$jsonField] === null || $data[$jsonField] === '') {
                continue;
            }
            if (is_string($data[$jsonField]) && $this->decodeJson($data[$jsonField]) === null && trim($data[$jsonField]) !== '') {
                $errors[$jsonField] = 'JSON inválido.';
            }
        }

        if (trim((string) ($data['generated_at'] ?? '')) !== '' && $this->normalizeDateTime((string) $data['generated_at']) === null) {
            $errors['generated_at'] = 'Data/hora de geração inválida.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'rs.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(rs.`title` LIKE :q1 OR rs.`description` LIKE :q2 OR rs.`notes` LIKE :q3 OR rs.`internal_notes` LIKE :q4 OR rs.`report_key` LIKE :q5)';
            for ($i = 1; $i <= 5; $i++) {
                $params['q' . $i] = $like;
            }
        }

        $reportKey = trim((string) ($filters['report_key'] ?? ''));
        if ($reportKey !== '') {
            $conditions[] = 'rs.`report_key` = :report_key';
            $params['report_key'] = $reportKey;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = 'rs.`status` = :status';
            $params['status'] = $status;
        }

        $generatedBy = (int) ($filters['generated_by'] ?? 0);
        if ($generatedBy > 0) {
            $conditions[] = 'rs.`generated_by` = :generated_by';
            $params['generated_by'] = $generatedBy;
        }

        $periodFrom = $this->normalizeDate((string) ($filters['period_from'] ?? ''));
        if ($periodFrom !== null) {
            $conditions[] = '(rs.`period_start` IS NULL OR rs.`period_start` >= :period_from OR rs.`period_end` >= :period_from2)';
            $params['period_from'] = $periodFrom;
            $params['period_from2'] = $periodFrom;
        }

        $periodTo = $this->normalizeDate((string) ($filters['period_to'] ?? ''));
        if ($periodTo !== null) {
            $conditions[] = '(rs.`period_end` IS NULL OR rs.`period_end` <= :period_to OR rs.`period_start` <= :period_to2)';
            $params['period_to'] = $periodTo;
            $params['period_to2'] = $periodTo;
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `report_snapshots` rs
                 LEFT JOIN `users` gb ON gb.`id` = rs.`generated_by`
                 LEFT JOIN `users` cb ON cb.`id` = rs.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = rs.`updated_by`';
    }

    /** @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . $where
            . ' ORDER BY ' . self::ORDER_BY
            . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        return (int) $this->query('SELECT COUNT(*) AS c' . $this->fromJoins() . $where, $params)->fetchColumn();
    }

    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE rs.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `report_snapshots` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
            $row
        );

        return (string) $this->db->lastInsertId();
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `report_snapshots` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `report_snapshots` SET `archived_at` = NULL, `status` = \'gerado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prepareRow(array $data, bool $isCreate = true): array
    {
        $row = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $row[$col] = $data[$col];
            }
        }

        foreach (['period_start', 'period_end'] as $date) {
            if (array_key_exists($date, $row)) {
                $row[$date] = $this->normalizeDate((string) ($row[$date] ?? ''));
            }
        }

        if (array_key_exists('generated_at', $row)) {
            $row['generated_at'] = $this->normalizeDateTime((string) ($row['generated_at'] ?? ''));
        }

        foreach (['filters_json', 'metrics_json', 'summary_json'] as $jsonField) {
            if (!array_key_exists($jsonField, $row)) {
                continue;
            }
            if (is_array($row[$jsonField])) {
                $row[$jsonField] = $this->encodeJson($row[$jsonField]);
            }
        }

        foreach (['generated_by', 'created_by', 'updated_by'] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }

        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        if ($isCreate && !isset($row['generated_at']) && isset($row['generated_by'])) {
            $row['generated_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($row['updated_at'])) {
            $row['updated_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }
}
