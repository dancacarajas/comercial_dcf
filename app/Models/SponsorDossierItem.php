<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model Itens do Dossiê do Patrocinador (Etapa 16).
 */
final class SponsorDossierItem extends Model
{
    protected string $table = 'sponsor_dossier_items';

    /** @var list<string> */
    private const FILLABLE = [
        'dossier_id', 'sponsor_id', 'contract_id', 'counterpart_id', 'financial_entry_id', 'document_id',
        'item_type', 'source_module', 'title', 'description', 'status', 'evidence_status',
        'amount', 'date_ref', 'sort_order', 'created_by', 'updated_by',
    ];

    /** @var list<string> */
    private const LINK_COLUMNS = [
        'contract_id', 'counterpart_id', 'financial_entry_id', 'document_id',
    ];

    private const LIST_COLUMNS =
        'di.`id`, di.`dossier_id`, di.`sponsor_id`, di.`contract_id`, di.`counterpart_id`,
         di.`financial_entry_id`, di.`document_id`, di.`item_type`, di.`source_module`,
         di.`title`, di.`description`, di.`status`, di.`evidence_status`,
         di.`amount`, di.`date_ref`, di.`sort_order`,
         di.`created_by`, di.`updated_by`, di.`created_at`, di.`updated_at`, di.`archived_at`,
         sp.`sponsor_display_name` AS sponsor_name,
         cn.`title` AS contract_title,
         cn.`contract_number` AS contract_number,
         cp.`title` AS counterpart_title,
         fe.`title` AS financial_entry_title,
         fe.`entry_number` AS financial_entry_number,
         doc.`title` AS document_title,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name';

    /** @var array<string, string> */
    private const ITEM_TYPES = [
        'contrato'         => 'Contrato',
        'contrapartida'    => 'Contrapartida',
        'financeiro'       => 'Financeiro',
        'comprovante'      => 'Comprovante',
        'recibo'           => 'Recibo',
        'documento_fiscal' => 'Documento fiscal',
        'evidencia'        => 'Evidência',
        'clipping'         => 'Clipping',
        'foto'             => 'Foto',
        'video'            => 'Vídeo',
        'print'            => 'Print',
        'release'          => 'Release',
        'midia_social'     => 'Mídia social',
        'observacao'       => 'Observação',
        'manual'           => 'Manual',
        'outro'            => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'ativo'        => 'Ativo',
        'pendente'     => 'Pendente',
        'conferido'    => 'Conferido',
        'aprovado'     => 'Aprovado',
        'rejeitado'    => 'Rejeitado',
        'substituido'  => 'Substituído',
        'arquivado'    => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const EVIDENCE_STATUSES = [
        'nao_aplicavel' => 'Não aplicável',
        'pendente'      => 'Pendente',
        'anexada'       => 'Anexada',
        'conferida'     => 'Conferida',
        'insuficiente'  => 'Insuficiente',
        'substituida'   => 'Substituída',
    ];

    private const ORDER_BY =
        'di.`sort_order` ASC,
         di.`date_ref` DESC,
         di.`title` ASC,
         di.`id` ASC';

    /** @return array<string, string> */
    public function getItemTypes(): array
    {
        return self::ITEM_TYPES;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<string, string> */
    public function getEvidenceStatuses(): array
    {
        return self::EVIDENCE_STATUSES;
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

        return is_numeric($clean) ? (float) $clean : $value;
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        if ((int) ($data['dossier_id'] ?? 0) <= 0) {
            $errors['dossier_id'] = 'Selecione o dossiê.';
        }

        if ((int) ($data['sponsor_id'] ?? 0) <= 0) {
            $errors['sponsor_id'] = 'Selecione o patrocinador.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título do item.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }

        if (!array_key_exists((string) ($data['item_type'] ?? 'manual'), self::ITEM_TYPES)) {
            $errors['item_type'] = 'Tipo de item inválido.';
        }

        if (!array_key_exists((string) ($data['status'] ?? 'ativo'), self::STATUSES)) {
            $errors['status'] = 'Status do item inválido.';
        }

        if (!array_key_exists((string) ($data['evidence_status'] ?? 'nao_aplicavel'), self::EVIDENCE_STATUSES)) {
            $errors['evidence_status'] = 'Status de evidência inválido.';
        }

        if (array_key_exists('amount', $data) && $data['amount'] !== null && $data['amount'] !== '') {
            $amount = $this->normalizeMoney($data['amount']);
            if (!is_float($amount)) {
                $errors['amount'] = 'Informe um valor numérico válido.';
            }
        }

        if (trim((string) ($data['date_ref'] ?? '')) !== '' && $this->normalizeDate((string) $data['date_ref']) === null) {
            $errors['date_ref'] = 'Data de referência inválida.';
        }

        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null && $data['sort_order'] !== ''
            && !is_numeric($data['sort_order'])) {
            $errors['sort_order'] = 'Ordem de exibição inválida.';
        }

        return $errors;
    }

    /** @return array<int, array<string, mixed>> */
    public function findByDossier(int|string $dossierId, bool $includeArchived = false): array
    {
        $conditions = ['di.`dossier_id` = :dossier_id'];
        $params = ['dossier_id' => $dossierId];

        if (!$includeArchived) {
            $conditions[] = 'di.`archived_at` IS NULL';
        }

        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins()
            . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . self::ORDER_BY,
            $params
        )->fetchAll();
    }

    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE di.`id` = :id LIMIT 1',
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
            'INSERT INTO `sponsor_dossier_items` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
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
            'UPDATE `sponsor_dossier_items` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $row
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsor_dossier_items` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsor_dossier_items` SET `archived_at` = NULL, `status` = \'ativo\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function findExistingLinkedItem(
        int|string $dossierId,
        ?string $sourceModule,
        string $linkColumn,
        int|string $linkId
    ): ?array {
        if (!in_array($linkColumn, self::LINK_COLUMNS, true) || (int) $linkId <= 0) {
            return null;
        }

        $params = [
            'dossier_id' => $dossierId,
            'link_id'    => $linkId,
        ];

        $conditions = [
            'di.`dossier_id` = :dossier_id',
            'di.`' . $linkColumn . '` = :link_id',
            'di.`archived_at` IS NULL',
        ];

        if ($sourceModule !== null && $sourceModule !== '') {
            $conditions[] = 'di.`source_module` = :source_module';
            $params['source_module'] = $sourceModule;
        }

        $row = $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins()
            . ' WHERE ' . implode(' AND ', $conditions)
            . ' LIMIT 1',
            $params
        )->fetch();

        return $row !== false ? $row : null;
    }

    private function fromJoins(): string
    {
        return ' FROM `sponsor_dossier_items` di
                 INNER JOIN `sponsors` sp ON sp.`id` = di.`sponsor_id`
                 LEFT JOIN `contracts` cn ON cn.`id` = di.`contract_id`
                 LEFT JOIN `counterparts` cp ON cp.`id` = di.`counterpart_id`
                 LEFT JOIN `financial_entries` fe ON fe.`id` = di.`financial_entry_id`
                 LEFT JOIN `documents` doc ON doc.`id` = di.`document_id`
                 LEFT JOIN `users` cb ON cb.`id` = di.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = di.`updated_by`';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareRow(array $data, bool $isCreate = true): array
    {
        $row = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $row[$col] = $data[$col];
            }
        }

        if (array_key_exists('amount', $row)) {
            $n = $this->normalizeMoney($row['amount']);
            $row['amount'] = is_float($n) ? $n : null;
        }

        if (array_key_exists('date_ref', $row)) {
            $row['date_ref'] = $this->normalizeDate((string) ($row['date_ref'] ?? ''));
        }

        foreach (self::LINK_COLUMNS as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }

        foreach (['dossier_id', 'sponsor_id', 'created_by', 'updated_by'] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }

        if (array_key_exists('sort_order', $row)) {
            $row['sort_order'] = ($row['sort_order'] === null || $row['sort_order'] === '')
                ? 0
                : (int) $row['sort_order'];
        }

        if (array_key_exists('source_module', $row)) {
            $module = trim((string) ($row['source_module'] ?? ''));
            $row['source_module'] = $module === '' ? null : $module;
        }

        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }

        return $row;
    }
}
