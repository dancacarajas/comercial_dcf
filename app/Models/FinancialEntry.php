<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model Financeiro Detalhado / Parcelas (Etapa 15).
 */
final class FinancialEntry extends Model
{
    protected string $table = 'financial_entries';

    /** @var list<string> */
    private const FILLABLE = [
        'sponsor_id', 'contract_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
        'proof_document_id', 'receipt_document_id', 'fiscal_document_id',
        'entry_number', 'title', 'entry_type', 'funding_mechanism', 'payment_method',
        'status', 'fiscal_document_status',
        'installment_number', 'installments_total',
        'planned_amount', 'received_amount', 'remaining_amount',
        'due_date', 'expected_payment_date', 'received_at', 'reconciled_at', 'cancelled_at',
        'payer_name', 'payer_document', 'bank_reference', 'transaction_reference',
        'proof_notes', 'receipt_notes', 'fiscal_notes', 'reconciliation_notes', 'notes', 'internal_notes',
        'responsible_user_id', 'confirmed_by', 'reconciled_by', 'cancelled_by',
        'created_by', 'updated_by',
    ];

    private const LIST_COLUMNS =
        'fe.`id`, fe.`sponsor_id`, fe.`contract_id`, fe.`company_id`, fe.`contact_id`, fe.`opportunity_id`, fe.`proposal_id`, fe.`quota_id`,
         fe.`proof_document_id`, fe.`receipt_document_id`, fe.`fiscal_document_id`,
         fe.`entry_number`, fe.`title`, fe.`entry_type`, fe.`funding_mechanism`, fe.`payment_method`,
         fe.`status`, fe.`fiscal_document_status`,
         fe.`installment_number`, fe.`installments_total`,
         fe.`planned_amount`, fe.`received_amount`, fe.`remaining_amount`,
         fe.`due_date`, fe.`expected_payment_date`, fe.`received_at`, fe.`reconciled_at`, fe.`cancelled_at`,
         fe.`payer_name`, fe.`payer_document`, fe.`bank_reference`, fe.`transaction_reference`,
         fe.`proof_notes`, fe.`receipt_notes`, fe.`fiscal_notes`, fe.`reconciliation_notes`, fe.`notes`, fe.`internal_notes`,
         fe.`responsible_user_id`, fe.`confirmed_by`, fe.`reconciled_by`, fe.`cancelled_by`,
         fe.`created_by`, fe.`updated_by`, fe.`created_at`, fe.`updated_at`, fe.`archived_at`,
         sp.`sponsor_display_name` AS sponsor_name,
         cn.`title` AS contract_title,
         cn.`contract_number` AS contract_number,
         co.`name` AS company_name,
         ctt.`name` AS contact_name,
         o.`title` AS opportunity_title,
         pr.`title` AS proposal_title,
         q.`name` AS quota_name,
         pd.`title` AS proof_document_title,
         rd.`title` AS receipt_document_title,
         fd.`title` AS fiscal_document_title,
         ru.`name` AS responsible_name,
         cfb.`name` AS confirmed_by_name,
         rcb.`name` AS reconciled_by_name,
         cab.`name` AS cancelled_by_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name';

    /** @var array<string, string> */
    private const ENTRY_TYPES = [
        'parcela_patrocinio'     => 'Parcela de patrocínio',
        'aporte_unico'           => 'Aporte único',
        'patrocinio_incentivado' => 'Patrocínio incentivado',
        'patrocinio_direto'      => 'Patrocínio direto',
        'permuta_valorizada'     => 'Permuta valorizada',
        'apoio_financeiro'       => 'Apoio financeiro',
        'complemento'            => 'Complemento',
        'ajuste'                 => 'Ajuste',
        'desconto'               => 'Desconto',
        'cancelamento'           => 'Cancelamento',
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
    private const PAYMENT_METHODS = [
        'transferencia'  => 'Transferência',
        'pix'            => 'PIX',
        'deposito'       => 'Depósito',
        'boleto_externo' => 'Boleto externo',
        'cheque'         => 'Cheque',
        'dinheiro'       => 'Dinheiro',
        'permuta'        => 'Permuta',
        'compensacao'    => 'Compensação',
        'nao_definido'   => 'Não definido',
        'outro'          => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'previsto'              => 'Previsto',
        'aguardando_pagamento'  => 'Aguardando pagamento',
        'em_atraso'             => 'Em atraso',
        'recebido_parcial'      => 'Recebido parcial',
        'recebido'              => 'Recebido',
        'conciliado'            => 'Conciliado',
        'cancelado'             => 'Cancelado',
        'suspenso'              => 'Suspenso',
        'inadimplente'          => 'Inadimplente',
        'arquivado'             => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const FISCAL_DOCUMENT_STATUSES = [
        'nao_aplicavel' => 'Não aplicável',
        'pendente'      => 'Pendente',
        'solicitado'    => 'Solicitado',
        'recebido'      => 'Recebido',
        'anexado'       => 'Anexado',
        'cancelado'     => 'Cancelado',
    ];

    /** @var list<string> */
    private const TERMINAL_STATUSES = [
        'recebido', 'conciliado', 'cancelado', 'arquivado',
    ];

    private const ORDER_BY =
        'CASE WHEN fe.`archived_at` IS NULL AND (
              fe.`status` IN (\'em_atraso\',\'inadimplente\')
              OR (fe.`due_date` IS NOT NULL AND fe.`due_date` < CURDATE()
                  AND fe.`status` NOT IN (\'recebido\',\'conciliado\',\'cancelado\',\'arquivado\'))
         ) THEN 0 ELSE 1 END ASC,
         fe.`due_date` ASC,
         fe.`planned_amount` DESC,
         fe.`status` ASC,
         fe.`updated_at` DESC,
         fe.`title` ASC';

    /** @return array<string, string> */
    public function getEntryTypes(): array { return self::ENTRY_TYPES; }

    /** @return array<string, string> */
    public function getFundingMechanisms(): array { return self::FUNDING_MECHANISMS; }

    /** @return array<string, string> */
    public function getPaymentMethods(): array { return self::PAYMENT_METHODS; }

    /** @return array<string, string> */
    public function getStatuses(): array { return self::STATUSES; }

    /** @return array<string, string> */
    public function getFiscalDocumentStatuses(): array { return self::FISCAL_DOCUMENT_STATUSES; }

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

    public function calculateRemaining(float|int|string|null $planned, float|int|string|null $received): float
    {
        $plannedVal = is_numeric($planned) ? (float) $planned : 0.0;
        $receivedVal = is_numeric($received) ? (float) $received : 0.0;
        return max(0.0, round($plannedVal - $receivedVal, 2));
    }

    /** @param array<string, mixed> $entry */
    public function isOverdue(array $entry): bool
    {
        if (!empty($entry['archived_at'])) {
            return false;
        }
        $status = (string) ($entry['status'] ?? '');
        if (in_array($status, ['em_atraso', 'inadimplente'], true)) {
            return true;
        }
        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            return false;
        }
        $due = $entry['due_date'] ?? null;
        if ($due === null || $due === '') {
            return false;
        }
        return $due < date('Y-m-d');
    }

    /** @param array<string, mixed> $data @return array<string, string> */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];
        if ((int) ($data['sponsor_id'] ?? 0) <= 0) {
            $errors['sponsor_id'] = 'Selecione o patrocinador / fechamento comercial.';
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título do lançamento financeiro.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }
        if (!array_key_exists((string) ($data['entry_type'] ?? ''), self::ENTRY_TYPES)) {
            $errors['entry_type'] = 'Tipo de registro financeiro inválido.';
        }
        if (!array_key_exists((string) ($data['funding_mechanism'] ?? 'nao_definido'), self::FUNDING_MECHANISMS)) {
            $errors['funding_mechanism'] = 'Mecanismo de fomento inválido.';
        }
        if (!array_key_exists((string) ($data['payment_method'] ?? 'nao_definido'), self::PAYMENT_METHODS)) {
            $errors['payment_method'] = 'Forma de pagamento inválida.';
        }
        if (!array_key_exists((string) ($data['status'] ?? 'previsto'), self::STATUSES)) {
            $errors['status'] = 'Status financeiro inválido.';
        }
        if (!array_key_exists((string) ($data['fiscal_document_status'] ?? 'nao_aplicavel'), self::FISCAL_DOCUMENT_STATUSES)) {
            $errors['fiscal_document_status'] = 'Status do documento fiscal inválido.';
        }

        $planned = $this->normalizeMoney($data['planned_amount'] ?? null);
        if (!is_float($planned) || $planned < 0) {
            $errors['planned_amount'] = 'Informe um valor previsto numérico válido (zero ou positivo).';
        }

        $received = $this->normalizeMoney($data['received_amount'] ?? 0);
        if (!is_float($received) || $received < 0) {
            $errors['received_amount'] = 'Informe um valor recebido numérico válido (zero ou positivo).';
        } elseif (is_float($planned) && is_float($received)) {
            $entryType = (string) ($data['entry_type'] ?? 'parcela_patrocinio');
            if ($received > $planned && !in_array($entryType, ['ajuste', 'complemento'], true)) {
                $errors['received_amount'] = 'O valor recebido não pode ser maior que o valor previsto.';
            }
        }

        $installmentNumber = $data['installment_number'] ?? null;
        if ($installmentNumber !== null && $installmentNumber !== '' && (int) $installmentNumber < 1) {
            $errors['installment_number'] = 'O número da parcela deve ser maior ou igual a 1.';
        }
        $installmentsTotal = $data['installments_total'] ?? null;
        if ($installmentsTotal !== null && $installmentsTotal !== '' && (int) $installmentsTotal < 1) {
            $errors['installments_total'] = 'O total de parcelas deve ser maior ou igual a 1.';
        }
        if ($installmentNumber !== null && $installmentNumber !== ''
            && $installmentsTotal !== null && $installmentsTotal !== ''
            && (int) $installmentNumber > (int) $installmentsTotal) {
            $errors['installment_number'] = 'O número da parcela não pode ser maior que o total de parcelas.';
        }

        foreach (['due_date', 'expected_payment_date'] as $f) {
            if (trim((string) ($data[$f] ?? '')) !== '' && $this->normalizeDate((string) $data[$f]) === null) {
                $errors[$f] = 'Data inválida.';
            }
        }
        foreach (['received_at', 'reconciled_at', 'cancelled_at'] as $f) {
            if (trim((string) ($data[$f] ?? '')) !== '' && $this->normalizeDateTime((string) $data[$f]) === null) {
                $errors[$f] = 'Data/hora inválida.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];
        if (empty($filters['show_archived'])) {
            $conditions[] = 'fe.`archived_at` IS NULL';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(fe.`title` LIKE :q1 OR fe.`entry_number` LIKE :q2 OR sp.`sponsor_display_name` LIKE :q3 OR cn.`title` LIKE :q4 OR cn.`contract_number` LIKE :q5 OR co.`name` LIKE :q6 OR fe.`bank_reference` LIKE :q7 OR fe.`transaction_reference` LIKE :q8 OR fe.`notes` LIKE :q9 OR fe.`internal_notes` LIKE :q10 OR fe.`reconciliation_notes` LIKE :q11)';
            for ($i = 1; $i <= 11; $i++) {
                $params['q' . $i] = $like;
            }
        }
        foreach (['sponsor_id', 'contract_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'fe.`' . $fk . '` = :' . $fk;
                $params[$fk] = $v;
            }
        }
        foreach (['entry_type', 'funding_mechanism', 'payment_method', 'status', 'fiscal_document_status'] as $field) {
            $v = trim((string) ($filters[$field] ?? ''));
            if ($v !== '') {
                $conditions[] = 'fe.`' . $field . '` = :' . $field;
                $params[$field] = $v;
            }
        }
        if (!empty($filters['overdue'])) {
            $conditions[] = $this->overdueCondition();
        }
        if (!empty($filters['received'])) {
            $conditions[] = 'fe.`status` = \'recebido\'';
        }
        if (!empty($filters['partial'])) {
            $conditions[] = 'fe.`status` = \'recebido_parcial\'';
        }
        if (!empty($filters['reconciled'])) {
            $conditions[] = 'fe.`status` = \'conciliado\'';
        }
        if (!empty($filters['pending'])) {
            $conditions[] = 'fe.`status` IN (\'previsto\',\'aguardando_pagamento\')';
        }
        $dueFrom = $this->normalizeDate((string) ($filters['due_from'] ?? ''));
        if ($dueFrom !== null) {
            $conditions[] = 'fe.`due_date` >= :due_from';
            $params['due_from'] = $dueFrom;
        }
        $dueTo = $this->normalizeDate((string) ($filters['due_to'] ?? ''));
        if ($dueTo !== null) {
            $conditions[] = 'fe.`due_date` <= :due_to';
            $params['due_to'] = $dueTo;
        }
        $receivedFrom = $this->normalizeDate((string) ($filters['received_from'] ?? ''));
        if ($receivedFrom !== null) {
            $conditions[] = 'DATE(fe.`received_at`) >= :received_from';
            $params['received_from'] = $receivedFrom;
        }
        $receivedTo = $this->normalizeDate((string) ($filters['received_to'] ?? ''));
        if ($receivedTo !== null) {
            $conditions[] = 'DATE(fe.`received_at`) <= :received_to';
            $params['received_to'] = $receivedTo;
        }
        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function overdueCondition(): string
    {
        return '(fe.`status` IN (\'em_atraso\',\'inadimplente\')
            OR (fe.`due_date` IS NOT NULL AND fe.`due_date` < CURDATE()
                AND fe.`status` NOT IN (\'recebido\',\'conciliado\',\'cancelado\',\'arquivado\')))';
    }

    private function fromJoins(): string
    {
        return ' FROM `financial_entries` fe
                 INNER JOIN `sponsors` sp ON sp.`id` = fe.`sponsor_id`
                 LEFT JOIN `contracts` cn ON cn.`id` = fe.`contract_id`
                 LEFT JOIN `companies` co ON co.`id` = fe.`company_id`
                 LEFT JOIN `contacts` ctt ON ctt.`id` = fe.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = fe.`opportunity_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = fe.`proposal_id`
                 LEFT JOIN `quotas` q ON q.`id` = fe.`quota_id`
                 LEFT JOIN `documents` pd ON pd.`id` = fe.`proof_document_id`
                 LEFT JOIN `documents` rd ON rd.`id` = fe.`receipt_document_id`
                 LEFT JOIN `documents` fd ON fd.`id` = fe.`fiscal_document_id`
                 LEFT JOIN `users` ru ON ru.`id` = fe.`responsible_user_id`
                 LEFT JOIN `users` cfb ON cfb.`id` = fe.`confirmed_by`
                 LEFT JOIN `users` rcb ON rcb.`id` = fe.`reconciled_by`
                 LEFT JOIN `users` cab ON cab.`id` = fe.`cancelled_by`
                 LEFT JOIN `users` cb ON cb.`id` = fe.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = fe.`updated_by`';
    }

    /** @param array<string, mixed> $filters @return array<int, array<string, mixed>> */
    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;
        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . $where . ' ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $perPage . ' OFFSET ' . $offset,
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
        $row = $this->query('SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE fe.`id` = :id LIMIT 1', ['id' => $id])->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query('INSERT INTO `financial_entries` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')', $row);
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
        $this->query('UPDATE `financial_entries` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $row);
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int|string $id, array $data): void
    {
        $allowed = [
            'status', 'fiscal_document_status', 'notes', 'internal_notes',
            'received_at', 'reconciled_at', 'cancelled_at',
            'confirmed_by', 'reconciled_by', 'cancelled_by',
            'updated_by', 'updated_at',
        ];
        $patch = array_intersect_key($data, array_flip($allowed));
        $status = (string) ($patch['status'] ?? '');
        if ($status === 'recebido' && empty($patch['received_at'])) {
            $patch['received_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'conciliado' && empty($patch['reconciled_at'])) {
            $patch['reconciled_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'cancelado') {
            if (empty($patch['cancelled_at'])) {
                $patch['cancelled_at'] = date('Y-m-d H:i:s');
            }
            if (empty($patch['cancelled_by']) && !empty($data['cancelled_by'])) {
                $patch['cancelled_by'] = (int) $data['cancelled_by'];
            }
        }
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($patch) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $patch['id'] = $id;
        $this->query('UPDATE `financial_entries` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $patch);
    }

    /** @param array<string, mixed> $data */
    public function confirmPayment(int|string $id, array $data, int|string $userId): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        $planned = (float) ($current['planned_amount'] ?? 0);
        $entryType = (string) ($current['entry_type'] ?? 'parcela_patrocinio');
        $receivedRaw = $data['received_amount'] ?? null;
        $received = $receivedRaw === null || $receivedRaw === ''
            ? $planned
            : (float) ($this->normalizeMoney($receivedRaw) ?? 0);
        if ($received > $planned && !in_array($entryType, ['ajuste', 'complemento'], true)) {
            $received = $planned;
        }
        $status = ($received > 0 && $received < $planned) ? 'recebido_parcial' : 'recebido';
        $patch = [
            'received_amount' => $received,
            'remaining_amount' => $this->calculateRemaining($planned, $received),
            'status' => $status,
            'received_at' => $this->normalizeDateTime((string) ($data['received_at'] ?? '')) ?? date('Y-m-d H:i:s'),
            'confirmed_by' => (int) $userId,
            'updated_by' => (int) $userId,
        ];
        if (!empty($data['payment_method']) && array_key_exists((string) $data['payment_method'], self::PAYMENT_METHODS)) {
            $patch['payment_method'] = (string) $data['payment_method'];
        }
        if (!empty($data['transaction_reference'])) {
            $patch['transaction_reference'] = trim((string) $data['transaction_reference']);
        }
        if (!empty($data['proof_document_id'])) {
            $patch['proof_document_id'] = (int) $data['proof_document_id'];
        }
        if (!empty($data['receipt_document_id'])) {
            $patch['receipt_document_id'] = (int) $data['receipt_document_id'];
        }
        if (!empty($data['proof_notes'])) {
            $patch['proof_notes'] = trim((string) $data['proof_notes']);
        }
        if (!empty($data['receipt_notes'])) {
            $patch['receipt_notes'] = trim((string) $data['receipt_notes']);
        }
        if (!empty($data['notes'])) {
            $patch['notes'] = trim((string) $data['notes']);
        }
        $this->update($id, $patch);
    }

    /** @param array<string, mixed> $data */
    public function reconcile(int|string $id, array $data, int|string $userId): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        $status = (string) ($current['status'] ?? '');
        $patch = [
            'reconciled_at' => $this->normalizeDateTime((string) ($data['reconciled_at'] ?? '')) ?? date('Y-m-d H:i:s'),
            'reconciled_by' => (int) $userId,
            'updated_by' => (int) $userId,
        ];
        if ($status !== 'cancelado') {
            $patch['status'] = 'conciliado';
        }
        if (!empty($data['reconciliation_notes'])) {
            $patch['reconciliation_notes'] = trim((string) $data['reconciliation_notes']);
        }
        if (!empty($data['bank_reference'])) {
            $patch['bank_reference'] = trim((string) $data['bank_reference']);
        }
        if (!empty($data['transaction_reference'])) {
            $patch['transaction_reference'] = trim((string) $data['transaction_reference']);
        }
        $this->update($id, $patch);
    }

    public function archive(int|string $id): void
    {
        $this->query('UPDATE `financial_entries` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function restore(int|string $id): void
    {
        $this->query('UPDATE `financial_entries` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function countActive(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `financial_entries` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    public function countReceived(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `financial_entries` WHERE `archived_at` IS NULL AND `status` = 'recebido'")->fetchColumn();
    }

    public function countPartial(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `financial_entries` WHERE `archived_at` IS NULL AND `status` = 'recebido_parcial'")->fetchColumn();
    }

    public function countOverdue(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `financial_entries` fe WHERE fe.`archived_at` IS NULL AND ' . $this->overdueCondition())->fetchColumn();
    }

    public function countPending(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `financial_entries` WHERE `archived_at` IS NULL AND `status` IN ('previsto','aguardando_pagamento')")->fetchColumn();
    }

    public function countReconciled(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `financial_entries` WHERE `archived_at` IS NULL AND `status` = 'conciliado'")->fetchColumn();
    }

    public function sumPlanned(): float
    {
        return (float) $this->query('SELECT COALESCE(SUM(`planned_amount`), 0) FROM `financial_entries` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    public function sumReceived(): float
    {
        return (float) $this->query('SELECT COALESCE(SUM(`received_amount`), 0) FROM `financial_entries` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    public function sumRemaining(): float
    {
        return (float) $this->query('SELECT COALESCE(SUM(`remaining_amount`), 0) FROM `financial_entries` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function findBySponsor(int|string $sponsorId, int $limit = 10): array { return $this->findByFk('sponsor_id', $sponsorId, $limit); }
    public function countBySponsor(int|string $sponsorId): int { return $this->countByFk('sponsor_id', $sponsorId); }
    /** @return array<string, int|float> */
    public function summaryBySponsor(int|string $sponsorId): array { return $this->summaryByFk('sponsor_id', $sponsorId); }

    /** @return array<int, array<string, mixed>> */
    public function findByContract(int|string $contractId, int $limit = 10): array { return $this->findByFk('contract_id', $contractId, $limit); }
    public function countByContract(int|string $contractId): int { return $this->countByFk('contract_id', $contractId); }
    /** @return array<string, int|float> */
    public function summaryByContract(int|string $contractId): array { return $this->summaryByFk('contract_id', $contractId); }

    /** @return array<int, array<string, mixed>> */
    public function findByCompany(int|string $companyId, int $limit = 10): array { return $this->findByFk('company_id', $companyId, $limit); }
    public function countByCompany(int|string $companyId): int { return $this->countByFk('company_id', $companyId); }
    /** @return array<string, int|float> */
    public function summaryByCompany(int|string $companyId): array { return $this->summaryByFk('company_id', $companyId); }

    /** @return array<int, array<string, mixed>> */
    public function findByContact(int|string $contactId, int $limit = 10): array { return $this->findByFk('contact_id', $contactId, $limit); }
    public function countByContact(int|string $contactId): int { return $this->countByFk('contact_id', $contactId); }
    /** @return array<string, int|float> */
    public function summaryByContact(int|string $contactId): array { return $this->summaryByFk('contact_id', $contactId); }

    /** @return array<int, array<string, mixed>> */
    public function findByOpportunity(int|string $opportunityId, int $limit = 10): array { return $this->findByFk('opportunity_id', $opportunityId, $limit); }
    public function countByOpportunity(int|string $opportunityId): int { return $this->countByFk('opportunity_id', $opportunityId); }
    /** @return array<string, int|float> */
    public function summaryByOpportunity(int|string $opportunityId): array { return $this->summaryByFk('opportunity_id', $opportunityId); }

    /** @return array<int, array<string, mixed>> */
    public function findByProposal(int|string $proposalId, int $limit = 10): array { return $this->findByFk('proposal_id', $proposalId, $limit); }
    public function countByProposal(int|string $proposalId): int { return $this->countByFk('proposal_id', $proposalId); }
    /** @return array<string, int|float> */
    public function summaryByProposal(int|string $proposalId): array { return $this->summaryByFk('proposal_id', $proposalId); }

    /** @return array<int, array<string, mixed>> */
    public function findByQuota(int|string $quotaId, int $limit = 10): array { return $this->findByFk('quota_id', $quotaId, $limit); }
    public function countByQuota(int|string $quotaId): int { return $this->countByFk('quota_id', $quotaId); }
    /** @return array<string, int|float> */
    public function summaryByQuota(int|string $quotaId): array { return $this->summaryByFk('quota_id', $quotaId); }

    /** @return array<int, array<string, mixed>> */
    private function findByFk(string $column, int|string $id, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE fe.`' . $column . '` = :id AND fe.`archived_at` IS NULL ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $limit,
            ['id' => $id]
        )->fetchAll();
    }

    private function countByFk(string $column, int|string $id): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) AS c FROM `financial_entries` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetchColumn();
    }

    /** @return array<string, int|float> */
    private function summaryByFk(string $column, int|string $id): array
    {
        $row = $this->query(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN `status` = \'recebido\' THEN 1 ELSE 0 END) AS received,
                SUM(CASE WHEN `status` = \'recebido_parcial\' THEN 1 ELSE 0 END) AS partial,
                SUM(CASE WHEN `status` IN (\'previsto\',\'aguardando_pagamento\') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN `status` = \'conciliado\' THEN 1 ELSE 0 END) AS reconciled,
                SUM(CASE WHEN `status` IN (\'em_atraso\',\'inadimplente\')
                    OR (`due_date` IS NOT NULL AND `due_date` < CURDATE()
                        AND `status` NOT IN (\'recebido\',\'conciliado\',\'cancelado\',\'arquivado\')) THEN 1 ELSE 0 END) AS overdue,
                COALESCE(SUM(`planned_amount`), 0) AS planned_total,
                COALESCE(SUM(`received_amount`), 0) AS received_total,
                COALESCE(SUM(`remaining_amount`), 0) AS remaining_total
             FROM `financial_entries` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();
        return [
            'total' => (int) ($row['total'] ?? 0),
            'received' => (int) ($row['received'] ?? 0),
            'partial' => (int) ($row['partial'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'reconciled' => (int) ($row['reconciled'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'planned_total' => (float) ($row['planned_total'] ?? 0),
            'received_total' => (float) ($row['received_total'] ?? 0),
            'remaining_total' => (float) ($row['remaining_total'] ?? 0),
        ];
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
        foreach (['planned_amount', 'received_amount', 'remaining_amount'] as $money) {
            if (array_key_exists($money, $row)) {
                $n = $this->normalizeMoney($row[$money]);
                $row[$money] = is_float($n) ? $n : null;
            }
        }
        foreach (['due_date', 'expected_payment_date'] as $date) {
            if (array_key_exists($date, $row)) {
                $row[$date] = $this->normalizeDate((string) ($row[$date] ?? ''));
            }
        }
        foreach (['received_at', 'reconciled_at', 'cancelled_at'] as $dt) {
            if (array_key_exists($dt, $row)) {
                $row[$dt] = $this->normalizeDateTime((string) ($row[$dt] ?? ''));
            }
        }
        foreach ([
            'sponsor_id', 'contract_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
            'proof_document_id', 'receipt_document_id', 'fiscal_document_id',
            'responsible_user_id', 'confirmed_by', 'reconciled_by', 'cancelled_by', 'created_by', 'updated_by',
            'installment_number', 'installments_total',
        ] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }

        $planned = is_numeric($row['planned_amount'] ?? null) ? (float) $row['planned_amount'] : 0.0;
        $received = is_numeric($row['received_amount'] ?? null) ? (float) $row['received_amount'] : 0.0;
        $row['remaining_amount'] = $this->calculateRemaining($planned, $received);

        $status = (string) ($row['status'] ?? 'previsto');
        if ($received > 0 && $received < $planned && !in_array($status, ['recebido', 'conciliado', 'cancelado'], true)) {
            $row['status'] = 'recebido_parcial';
        } elseif ($received >= $planned && $planned > 0 && !in_array($status, ['conciliado', 'cancelado'], true)) {
            $row['status'] = 'recebido';
        }
        if ($status === 'recebido' && empty($row['received_at'])) {
            $row['received_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'conciliado' && empty($row['reconciled_at'])) {
            $row['reconciled_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'cancelado' && empty($row['cancelled_at'])) {
            $row['cancelled_at'] = date('Y-m-d H:i:s');
        }
        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        return $row;
    }
}
