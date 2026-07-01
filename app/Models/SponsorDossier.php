<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model Prestação de Contas Comercial / Dossiê do Patrocinador (Etapa 16).
 */
final class SponsorDossier extends Model
{
    protected string $table = 'sponsor_dossiers';

    /** @var list<string> */
    private const FILLABLE = [
        'incentive_project_id',
        'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'main_contract_id',
        'main_document_id', 'final_document_id', 'delivery_receipt_document_id',
        'dossier_number', 'title', 'dossier_type', 'status', 'delivery_status',
        'period_start', 'period_end',
        'include_contracts', 'include_counterparts', 'include_financials', 'include_documents',
        'include_evidence', 'include_clipping', 'include_media',
        'contracts_count', 'signed_contracts_count',
        'counterparts_count', 'counterparts_delivered_count', 'counterparts_partial_count',
        'counterparts_pending_count', 'counterparts_overdue_count',
        'financial_entries_count', 'financial_planned_amount', 'financial_received_amount',
        'financial_remaining_amount', 'financial_overdue_count',
        'documents_count', 'evidence_documents_count',
        'executive_summary', 'commercial_summary', 'counterparts_summary', 'financial_summary',
        'documents_summary', 'pending_notes', 'approval_notes', 'delivery_notes', 'notes', 'internal_notes',
        'generated_at', 'approved_at', 'delivered_at',
        'responsible_user_id', 'generated_by', 'approved_by', 'delivered_by', 'created_by', 'updated_by',
    ];

    private const LIST_COLUMNS =
        'sd.`id`, sd.`incentive_project_id`, sd.`sponsor_id`, sd.`company_id`, sd.`contact_id`, sd.`opportunity_id`, sd.`proposal_id`, sd.`quota_id`, sd.`main_contract_id`,
         sd.`main_document_id`, sd.`final_document_id`, sd.`delivery_receipt_document_id`,
         sd.`dossier_number`, sd.`title`, sd.`dossier_type`, sd.`status`, sd.`delivery_status`,
         sd.`period_start`, sd.`period_end`,
         sd.`include_contracts`, sd.`include_counterparts`, sd.`include_financials`, sd.`include_documents`,
         sd.`include_evidence`, sd.`include_clipping`, sd.`include_media`,
         sd.`contracts_count`, sd.`signed_contracts_count`,
         sd.`counterparts_count`, sd.`counterparts_delivered_count`, sd.`counterparts_partial_count`,
         sd.`counterparts_pending_count`, sd.`counterparts_overdue_count`,
         sd.`financial_entries_count`, sd.`financial_planned_amount`, sd.`financial_received_amount`,
         sd.`financial_remaining_amount`, sd.`financial_overdue_count`,
         sd.`documents_count`, sd.`evidence_documents_count`,
         sd.`executive_summary`, sd.`commercial_summary`, sd.`counterparts_summary`, sd.`financial_summary`,
         sd.`documents_summary`, sd.`pending_notes`, sd.`approval_notes`, sd.`delivery_notes`, sd.`notes`, sd.`internal_notes`,
         sd.`generated_at`, sd.`approved_at`, sd.`delivered_at`,
         sd.`responsible_user_id`, sd.`generated_by`, sd.`approved_by`, sd.`delivered_by`, sd.`created_by`, sd.`updated_by`,
         sd.`created_at`, sd.`updated_at`, sd.`archived_at`,
         ip.`project_name` AS project_name,
         sp.`sponsor_display_name` AS sponsor_name,
         co.`name` AS company_name,
         ctt.`name` AS contact_name,
         o.`title` AS opportunity_title,
         pr.`title` AS proposal_title,
         q.`name` AS quota_name,
         cn.`title` AS contract_title,
         cn.`contract_number` AS contract_number,
         md.`title` AS main_document_title,
         fnd.`title` AS final_document_title,
         drd.`title` AS delivery_document_title,
         ru.`name` AS responsible_name,
         gb.`name` AS generated_by_name,
         ab.`name` AS approved_by_name,
         db.`name` AS delivered_by_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name';

    /** @var array<string, string> */
    private const DOSSIER_TYPES = [
        'prestacao_comercial'    => 'Prestação de contas comercial',
        'relatorio_patrocinador' => 'Relatório do patrocinador',
        'dossie_evidencias'      => 'Dossiê de evidências',
        'dossie_final'           => 'Dossiê final',
        'dossie_parcial'         => 'Dossiê parcial',
        'encerramento_patrocinio'=> 'Encerramento do patrocínio',
        'outro'                  => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'rascunho'              => 'Rascunho',
        'em_preparacao'         => 'Em preparação',
        'aguardando_evidencias' => 'Aguardando evidências',
        'em_revisao'            => 'Em revisão',
        'aprovado'              => 'Aprovado',
        'entregue'              => 'Entregue',
        'pendente'              => 'Pendente',
        'cancelado'             => 'Cancelado',
        'suspenso'              => 'Suspenso',
        'arquivado'             => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const DELIVERY_STATUSES = [
        'nao_entregue'           => 'Não entregue',
        'preparando_entrega'     => 'Preparando entrega',
        'entregue_internamente'  => 'Entregue internamente',
        'entregue_patrocinador'  => 'Entregue ao patrocinador',
        'recebido_confirmado'    => 'Recebimento confirmado',
        'pendente_retorno'       => 'Pendente retorno',
        'nao_aplicavel'          => 'Não aplicável',
    ];

    /** @var list<string> */
    private const EVIDENCE_DOCUMENT_CATEGORIES = [
        'comprovante_envio', 'imagem_institucional', 'documento_comercial', 'ata_reuniao',
    ];

    private const ORDER_BY =
        'CASE WHEN sd.`archived_at` IS NULL AND sd.`status` IN (\'pendente\',\'aguardando_evidencias\') THEN 0 ELSE 1 END ASC,
         sd.`counterparts_overdue_count` DESC,
         sd.`financial_remaining_amount` DESC,
         sd.`updated_at` DESC,
         sd.`title` ASC';

    /** @return array<string, string> */
    public function getDossierTypes(): array { return self::DOSSIER_TYPES; }

    /** @return array<string, string> */
    public function getStatuses(): array { return self::STATUSES; }

    /** @return array<string, string> */
    public function getDeliveryStatuses(): array { return self::DELIVERY_STATUSES; }

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

    /** @param array<string, mixed> $data @return array<string, string> */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];
        if ((int) ($data['incentive_project_id'] ?? 0) <= 0) {
            $errors['incentive_project_id'] = 'Selecione o projeto incentivado do dossiê.';
        }
        if ((int) ($data['sponsor_id'] ?? 0) <= 0) {
            $errors['sponsor_id'] = 'Selecione o patrocinador / fechamento comercial.';
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título do dossiê.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }
        if (!array_key_exists((string) ($data['dossier_type'] ?? 'prestacao_comercial'), self::DOSSIER_TYPES)) {
            $errors['dossier_type'] = 'Tipo de dossiê inválido.';
        }
        if (!array_key_exists((string) ($data['status'] ?? 'rascunho'), self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }
        if (!array_key_exists((string) ($data['delivery_status'] ?? 'nao_entregue'), self::DELIVERY_STATUSES)) {
            $errors['delivery_status'] = 'Status de entrega inválido.';
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
        foreach (['generated_at', 'approved_at', 'delivered_at'] as $f) {
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
            $conditions[] = 'sd.`archived_at` IS NULL';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(sd.`title` LIKE :q1 OR sd.`dossier_number` LIKE :q2 OR sp.`sponsor_display_name` LIKE :q3
                OR co.`name` LIKE :q4 OR sd.`executive_summary` LIKE :q5 OR sd.`commercial_summary` LIKE :q6
                OR sd.`counterparts_summary` LIKE :q7 OR sd.`financial_summary` LIKE :q8 OR sd.`documents_summary` LIKE :q9
                OR sd.`pending_notes` LIKE :q10 OR sd.`notes` LIKE :q11 OR sd.`internal_notes` LIKE :q12
                OR cn.`title` LIKE :q13 OR cn.`contract_number` LIKE :q14)';
            for ($i = 1; $i <= 14; $i++) {
                $params['q' . $i] = $like;
            }
        }
        foreach (['incentive_project_id', 'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'sd.`' . $fk . '` = :' . $fk;
                $params[$fk] = $v;
            }
        }
        $mainContractId = (int) ($filters['main_contract_id'] ?? 0);
        if ($mainContractId > 0) {
            $conditions[] = 'sd.`main_contract_id` = :main_contract_id';
            $params['main_contract_id'] = $mainContractId;
        }
        foreach (['dossier_type', 'status', 'delivery_status'] as $field) {
            $v = trim((string) ($filters[$field] ?? ''));
            if ($v !== '') {
                $conditions[] = 'sd.`' . $field . '` = :' . $field;
                $params[$field] = $v;
            }
        }
        if (!empty($filters['approved'])) {
            $conditions[] = 'sd.`status` = \'aprovado\'';
        }
        if (!empty($filters['delivered'])) {
            $conditions[] = '(sd.`status` = \'entregue\' OR sd.`delivery_status` IN (\'entregue_internamente\',\'entregue_patrocinador\',\'recebido_confirmado\'))';
        }
        if (!empty($filters['pending'])) {
            $conditions[] = 'sd.`status` IN (\'pendente\',\'aguardando_evidencias\')';
        }
        if (!empty($filters['with_balance'])) {
            $conditions[] = 'sd.`financial_remaining_amount` > 0';
        }
        if (!empty($filters['pending_counterparts'])) {
            $conditions[] = 'sd.`counterparts_pending_count` > 0';
        }
        if (!empty($filters['overdue_counterparts'])) {
            $conditions[] = 'sd.`counterparts_overdue_count` > 0';
        }
        $periodFrom = $this->normalizeDate((string) ($filters['period_from'] ?? ''));
        if ($periodFrom !== null) {
            $conditions[] = '(sd.`period_start` IS NULL OR sd.`period_start` >= :period_from OR sd.`period_end` >= :period_from2)';
            $params['period_from'] = $periodFrom;
            $params['period_from2'] = $periodFrom;
        }
        $periodTo = $this->normalizeDate((string) ($filters['period_to'] ?? ''));
        if ($periodTo !== null) {
            $conditions[] = '(sd.`period_end` IS NULL OR sd.`period_end` <= :period_to OR sd.`period_start` <= :period_to2)';
            $params['period_to'] = $periodTo;
            $params['period_to2'] = $periodTo;
        }
        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `sponsor_dossiers` sd
                 LEFT JOIN `incentive_projects` ip ON ip.`id` = sd.`incentive_project_id`
                 INNER JOIN `sponsors` sp ON sp.`id` = sd.`sponsor_id`
                 LEFT JOIN `companies` co ON co.`id` = sd.`company_id`
                 LEFT JOIN `contacts` ctt ON ctt.`id` = sd.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = sd.`opportunity_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = sd.`proposal_id`
                 LEFT JOIN `quotas` q ON q.`id` = sd.`quota_id`
                 LEFT JOIN `contracts` cn ON cn.`id` = sd.`main_contract_id`
                 LEFT JOIN `documents` md ON md.`id` = sd.`main_document_id`
                 LEFT JOIN `documents` fnd ON fnd.`id` = sd.`final_document_id`
                 LEFT JOIN `documents` drd ON drd.`id` = sd.`delivery_receipt_document_id`
                 LEFT JOIN `users` ru ON ru.`id` = sd.`responsible_user_id`
                 LEFT JOIN `users` gb ON gb.`id` = sd.`generated_by`
                 LEFT JOIN `users` ab ON ab.`id` = sd.`approved_by`
                 LEFT JOIN `users` db ON db.`id` = sd.`delivered_by`
                 LEFT JOIN `users` cb ON cb.`id` = sd.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = sd.`updated_by`';
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
        $row = $this->query('SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE sd.`id` = :id LIMIT 1', ['id' => $id])->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query('INSERT INTO `sponsor_dossiers` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')', $row);
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
        $this->query('UPDATE `sponsor_dossiers` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $row);
    }

    public function generate(int|string $id, int|string $userId): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        $sponsorId = (int) ($current['sponsor_id'] ?? 0);
        if ($sponsorId <= 0) {
            return;
        }
        $snapshot = $this->buildSnapshotFromSponsor($sponsorId);
        $patch = array_intersect_key($snapshot, array_flip([
            'contracts_count', 'signed_contracts_count',
            'counterparts_count', 'counterparts_delivered_count', 'counterparts_partial_count',
            'counterparts_pending_count', 'counterparts_overdue_count',
            'financial_entries_count', 'financial_planned_amount', 'financial_received_amount',
            'financial_remaining_amount', 'financial_overdue_count',
            'documents_count', 'evidence_documents_count',
        ]));
        if (trim((string) ($current['counterparts_summary'] ?? '')) === '') {
            $patch['counterparts_summary'] = $this->buildCounterpartsSummaryText($snapshot);
        }
        if (trim((string) ($current['financial_summary'] ?? '')) === '') {
            $patch['financial_summary'] = $this->buildFinancialSummaryText($snapshot);
        }
        if (trim((string) ($current['documents_summary'] ?? '')) === '') {
            $patch['documents_summary'] = $this->buildDocumentsSummaryText($snapshot);
        }
        if (trim((string) ($current['executive_summary'] ?? '')) === '') {
            $patch['executive_summary'] = $this->buildExecutiveSummaryText($snapshot, $current);
        }
        $status = (string) ($current['status'] ?? 'rascunho');
        if ($status === 'rascunho') {
            $patch['status'] = 'em_preparacao';
        }
        $patch['generated_at'] = date('Y-m-d H:i:s');
        $patch['generated_by'] = (int) $userId;
        $patch['updated_by'] = (int) $userId;
        $this->update($id, $patch);
        $this->syncItemsFromLinkedData($id, $snapshot, $userId);
    }

    /** @param array<string, mixed> $data */
    public function approve(int|string $id, array $data, int|string $userId): void
    {
        $patch = [
            'status' => 'aprovado',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => (int) $userId,
            'updated_by' => (int) $userId,
        ];
        if (!empty($data['approval_notes'])) {
            $patch['approval_notes'] = trim((string) $data['approval_notes']);
        }
        $this->update($id, $patch);
    }

    /** @param array<string, mixed> $data */
    public function deliver(int|string $id, array $data, int|string $userId): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        $currentStatus = (string) ($current['status'] ?? '');
        $deliveryStatus = trim((string) ($data['delivery_status'] ?? ''));
        if ($deliveryStatus === '' || !array_key_exists($deliveryStatus, self::DELIVERY_STATUSES)) {
            $deliveryStatus = 'entregue_patrocinador';
        }
        $patch = [
            'delivery_status' => $deliveryStatus,
            'delivered_at' => $this->normalizeDateTime((string) ($data['delivered_at'] ?? '')) ?? date('Y-m-d H:i:s'),
            'delivered_by' => (int) $userId,
            'updated_by' => (int) $userId,
        ];
        if (!in_array($currentStatus, ['cancelado', 'suspenso'], true)) {
            $patch['status'] = 'entregue';
        }
        if (!empty($data['delivery_receipt_document_id'])) {
            $patch['delivery_receipt_document_id'] = (int) $data['delivery_receipt_document_id'];
        }
        if (!empty($data['delivery_notes'])) {
            $patch['delivery_notes'] = trim((string) $data['delivery_notes']);
        }
        $this->update($id, $patch);
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int|string $id, array $data, int|string $userId): void
    {
        $allowed = ['status', 'delivery_status', 'notes', 'internal_notes', 'approved_at', 'approved_by', 'delivered_at', 'delivered_by', 'updated_by', 'updated_at'];
        $patch = array_intersect_key($data, array_flip($allowed));
        $status = (string) ($patch['status'] ?? '');
        if ($status === 'aprovado' && empty($patch['approved_at'])) {
            $patch['approved_at'] = date('Y-m-d H:i:s');
            $patch['approved_by'] = (int) $userId;
        }
        if ($status === 'entregue' && empty($patch['delivered_at'])) {
            $patch['delivered_at'] = date('Y-m-d H:i:s');
            $patch['delivered_by'] = (int) $userId;
        }
        $patch['updated_by'] = (int) $userId;
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($patch) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $patch['id'] = $id;
        $this->query('UPDATE `sponsor_dossiers` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $patch);
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsor_dossiers` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `sponsor_dossiers` SET `archived_at` = NULL, `status` = \'rascunho\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function countActive(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    public function countApproved(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `status` = 'aprovado'")->fetchColumn();
    }

    public function countDelivered(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `status` = 'entregue'")->fetchColumn();
    }

    public function countPending(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `status` IN ('pendente','aguardando_evidencias')")->fetchColumn();
    }

    public function countWithPendingCounterparts(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `counterparts_pending_count` > 0')->fetchColumn();
    }

    public function countWithOverdueCounterparts(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `counterparts_overdue_count` > 0')->fetchColumn();
    }

    public function countWithFinancialBalance(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `sponsor_dossiers` WHERE `archived_at` IS NULL AND `financial_remaining_amount` > 0')->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function findBySponsor(int|string $sponsorId, int $limit = 10): array { return $this->findByFk('sponsor_id', $sponsorId, $limit); }
    public function countBySponsor(int|string $sponsorId): int { return $this->countByFk('sponsor_id', $sponsorId); }
    /** @return array<string, int|float> */
    public function summaryBySponsor(int|string $sponsorId): array { return $this->summaryByFk('sponsor_id', $sponsorId); }

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
    public function findByContract(int|string $contractId, int $limit = 10): array { return $this->findByFk('main_contract_id', $contractId, $limit); }
    public function countByContract(int|string $contractId): int { return $this->countByFk('main_contract_id', $contractId); }
    /** @return array<string, int|float> */
    public function summaryByContract(int|string $contractId): array { return $this->summaryByFk('main_contract_id', $contractId); }

    /** @return array<string, mixed> */
    public function buildSnapshotFromSponsor(int|string $sponsorId): array
    {
        $sponsorId = (int) $sponsorId;
        $contractModel = new Contract();
        $counterpartModel = new Counterpart();
        $financialModel = new FinancialEntry();
        $documentModel = new Document();

        $contractSummary = $contractModel->summaryBySponsor($sponsorId);
        $counterpartSummary = $counterpartModel->summaryBySponsor($sponsorId);
        $financialSummary = $financialModel->summaryBySponsor($sponsorId);
        $documentSummary = $documentModel->summaryBySponsor($sponsorId);

        $contracts = $this->query(
            'SELECT `id`, `title`, `contract_number`, `status`, `signature_status`, `formalized_value`, `start_date`, `end_date`, `signed_at`
             FROM `contracts` WHERE `sponsor_id` = :sid AND `archived_at` IS NULL ORDER BY `updated_at` DESC',
            ['sid' => $sponsorId]
        )->fetchAll();

        $counterparts = $this->query(
            'SELECT `id`, `title`, `category`, `status`, `due_date`, `delivered_at`, `evidence_document_id`, `description`
             FROM `counterparts` WHERE `sponsor_id` = :sid AND `archived_at` IS NULL ORDER BY `updated_at` DESC',
            ['sid' => $sponsorId]
        )->fetchAll();

        $financialEntries = $this->query(
            'SELECT `id`, `title`, `entry_number`, `entry_type`, `status`, `planned_amount`, `received_amount`, `remaining_amount`, `due_date`, `received_at`
             FROM `financial_entries` WHERE `sponsor_id` = :sid AND `archived_at` IS NULL ORDER BY `due_date` ASC, `updated_at` DESC',
            ['sid' => $sponsorId]
        )->fetchAll();

        $documents = $this->query(
            'SELECT `id`, `title`, `category`, `status`, `document_date`, `description`, `counterpart_id`, `contract_id`, `financial_entry_id`
             FROM `documents` WHERE `sponsor_id` = :sid AND `archived_at` IS NULL ORDER BY `updated_at` DESC',
            ['sid' => $sponsorId]
        )->fetchAll();

        $evidenceCount = 0;
        foreach ($documents as $doc) {
            $category = (string) ($doc['category'] ?? '');
            $hasCounterpart = !empty($doc['counterpart_id']);
            if ($hasCounterpart || in_array($category, self::EVIDENCE_DOCUMENT_CATEGORIES, true)) {
                $evidenceCount++;
            }
        }

        return [
            'contracts_count' => (int) ($contractSummary['total'] ?? count($contracts)),
            'signed_contracts_count' => (int) ($contractSummary['signed'] ?? 0),
            'counterparts_count' => (int) ($counterpartSummary['total'] ?? count($counterparts)),
            'counterparts_delivered_count' => (int) ($counterpartSummary['delivered'] ?? 0),
            'counterparts_partial_count' => (int) ($counterpartSummary['partial'] ?? 0),
            'counterparts_pending_count' => (int) ($counterpartSummary['pending'] ?? 0),
            'counterparts_overdue_count' => (int) ($counterpartSummary['overdue'] ?? 0),
            'financial_entries_count' => (int) ($financialSummary['total'] ?? count($financialEntries)),
            'financial_planned_amount' => (float) ($financialSummary['planned_total'] ?? 0),
            'financial_received_amount' => (float) ($financialSummary['received_total'] ?? 0),
            'financial_remaining_amount' => (float) ($financialSummary['remaining_total'] ?? 0),
            'financial_overdue_count' => (int) ($financialSummary['overdue'] ?? 0),
            'documents_count' => (int) ($documentSummary['total'] ?? count($documents)),
            'evidence_documents_count' => $evidenceCount,
            'contracts' => $contracts,
            'counterparts' => $counterparts,
            'financial_entries' => $financialEntries,
            'documents' => $documents,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function syncItemsFromLinkedData(int|string $dossierId, array $snapshot, int|string $userId): void
    {
        $dossier = $this->find($dossierId);
        if ($dossier === null) {
            return;
        }
        $sponsorId = (int) ($dossier['sponsor_id'] ?? 0);
        if ($sponsorId <= 0) {
            return;
        }
        $itemModel = new SponsorDossierItem();
        $sortOrder = 0;

        if (!empty($dossier['include_contracts'])) {
            foreach ($snapshot['contracts'] ?? [] as $row) {
                $this->upsertLinkedItem($itemModel, $dossierId, $sponsorId, $userId, 'contracts', 'contract_id', (int) $row['id'], [
                    'item_type' => 'contrato',
                    'title' => (string) ($row['title'] ?? 'Contrato'),
                    'description' => trim((string) (($row['contract_number'] ?? '') !== '' ? 'Nº ' . $row['contract_number'] : '')),
                    'status' => 'ativo',
                    'evidence_status' => 'nao_aplicavel',
                    'amount' => is_numeric($row['formalized_value'] ?? null) ? (float) $row['formalized_value'] : null,
                    'date_ref' => $this->normalizeDate((string) ($row['signed_at'] ?? $row['start_date'] ?? '')),
                    'contract_id' => (int) $row['id'],
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        if (!empty($dossier['include_counterparts'])) {
            foreach ($snapshot['counterparts'] ?? [] as $row) {
                $status = (string) ($row['status'] ?? 'planejada');
                $itemStatus = in_array($status, ['entregue', 'aprovada'], true) ? 'conferido' : 'ativo';
                $evidenceStatus = !empty($row['evidence_document_id']) ? 'anexada' : 'pendente';
                $this->upsertLinkedItem($itemModel, $dossierId, $sponsorId, $userId, 'counterparts', 'counterpart_id', (int) $row['id'], [
                    'item_type' => 'contrapartida',
                    'title' => (string) ($row['title'] ?? 'Contrapartida'),
                    'description' => trim((string) ($row['description'] ?? '')),
                    'status' => $itemStatus,
                    'evidence_status' => $evidenceStatus,
                    'date_ref' => $this->normalizeDate((string) ($row['delivered_at'] ?? $row['due_date'] ?? '')),
                    'counterpart_id' => (int) $row['id'],
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        if (!empty($dossier['include_financials'])) {
            foreach ($snapshot['financial_entries'] ?? [] as $row) {
                $entryType = (string) ($row['entry_type'] ?? 'parcela_patrocinio');
                $itemType = match ($entryType) {
                    'parcela_patrocinio', 'aporte_unico', 'patrocinio_incentivado', 'patrocinio_direto' => 'financeiro',
                    default => 'financeiro',
                };
                $this->upsertLinkedItem($itemModel, $dossierId, $sponsorId, $userId, 'financial_entries', 'financial_entry_id', (int) $row['id'], [
                    'item_type' => $itemType,
                    'title' => (string) ($row['title'] ?? 'Lançamento financeiro'),
                    'description' => trim((string) ($row['entry_number'] ?? '')),
                    'status' => in_array((string) ($row['status'] ?? ''), ['recebido', 'conciliado'], true) ? 'conferido' : 'ativo',
                    'evidence_status' => 'nao_aplicavel',
                    'amount' => is_numeric($row['planned_amount'] ?? null) ? (float) $row['planned_amount'] : null,
                    'date_ref' => $this->normalizeDate((string) ($row['received_at'] ?? $row['due_date'] ?? '')),
                    'financial_entry_id' => (int) $row['id'],
                    'sort_order' => $sortOrder++,
                ]);
            }
        }

        if (!empty($dossier['include_documents'])) {
            foreach ($snapshot['documents'] ?? [] as $row) {
                $category = (string) ($row['category'] ?? 'outro');
                $itemType = $this->mapDocumentCategoryToItemType($category);
                if ($itemType === 'clipping' && empty($dossier['include_clipping'])) {
                    continue;
                }
                if (in_array($itemType, ['foto', 'video', 'print', 'midia_social'], true) && empty($dossier['include_media'])) {
                    continue;
                }
                if ($itemType === 'evidencia' && empty($dossier['include_evidence'])) {
                    continue;
                }
                $this->upsertLinkedItem($itemModel, $dossierId, $sponsorId, $userId, 'documents', 'document_id', (int) $row['id'], [
                    'item_type' => $itemType,
                    'title' => (string) ($row['title'] ?? 'Documento'),
                    'description' => trim((string) ($row['description'] ?? '')),
                    'status' => 'ativo',
                    'evidence_status' => in_array($itemType, ['evidencia', 'clipping', 'foto', 'video', 'print'], true) ? 'anexada' : 'nao_aplicavel',
                    'date_ref' => $this->normalizeDate((string) ($row['document_date'] ?? '')),
                    'document_id' => (int) $row['id'],
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }

    /** @param array<string, mixed> $itemData */
    private function upsertLinkedItem(
        SponsorDossierItem $itemModel,
        int|string $dossierId,
        int $sponsorId,
        int|string $userId,
        string $sourceModule,
        string $linkColumn,
        int $linkId,
        array $itemData
    ): void {
        if ($linkId <= 0) {
            return;
        }
        $existing = $itemModel->findExistingLinkedItem($dossierId, $sourceModule, $linkColumn, $linkId);
        $payload = array_merge($itemData, [
            'dossier_id' => (int) $dossierId,
            'sponsor_id' => $sponsorId,
            'source_module' => $sourceModule,
            'updated_by' => (int) $userId,
        ]);
        if ($existing !== null) {
            $itemModel->update((int) $existing['id'], $payload);
            return;
        }
        $payload['created_by'] = (int) $userId;
        $itemModel->create($payload);
    }

    private function mapDocumentCategoryToItemType(string $category): string
    {
        return match ($category) {
            'comprovante_envio' => 'comprovante',
            'proposta_pdf', 'documento_comercial', 'dados_oficiais' => 'documento_fiscal',
            'imagem_institucional' => 'foto',
            'midia_kit', 'deck_patrocinio' => 'release',
            'ata_reuniao' => 'print',
            default => 'evidencia',
        };
    }

    /** @return array<int, array<string, mixed>> */
    private function findByFk(string $column, int|string $id, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins()
            . ' WHERE sd.`' . $column . '` = :id AND sd.`archived_at` IS NULL ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $limit,
            ['id' => $id]
        )->fetchAll();
    }

    private function countByFk(string $column, int|string $id): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) AS c FROM `sponsor_dossiers` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetchColumn();
    }

    /** @return array<string, int|float> */
    private function summaryByFk(string $column, int|string $id): array
    {
        $row = $this->query(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN `status` = \'aprovado\' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN `status` = \'entregue\' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN `status` IN (\'pendente\',\'aguardando_evidencias\') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN `counterparts_pending_count` > 0 THEN 1 ELSE 0 END) AS with_pending_counterparts,
                SUM(CASE WHEN `counterparts_overdue_count` > 0 THEN 1 ELSE 0 END) AS with_overdue_counterparts,
                SUM(CASE WHEN `financial_remaining_amount` > 0 THEN 1 ELSE 0 END) AS with_balance
             FROM `sponsor_dossiers` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();
        return [
            'total' => (int) ($row['total'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'with_pending_counterparts' => (int) ($row['with_pending_counterparts'] ?? 0),
            'with_overdue_counterparts' => (int) ($row['with_overdue_counterparts'] ?? 0),
            'with_balance' => (int) ($row['with_balance'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function buildCounterpartsSummaryText(array $snapshot): string
    {
        $total = (int) ($snapshot['counterparts_count'] ?? 0);
        $delivered = (int) ($snapshot['counterparts_delivered_count'] ?? 0);
        $pending = (int) ($snapshot['counterparts_pending_count'] ?? 0);
        $overdue = (int) ($snapshot['counterparts_overdue_count'] ?? 0);
        return sprintf(
            'Contrapartidas: %d total, %d entregues/aprovadas, %d pendentes, %d atrasadas.',
            $total,
            $delivered,
            $pending,
            $overdue
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function buildFinancialSummaryText(array $snapshot): string
    {
        return sprintf(
            'Financeiro: %d lançamentos. Previsto R$ %s, recebido R$ %s, saldo R$ %s. %d em atraso.',
            (int) ($snapshot['financial_entries_count'] ?? 0),
            number_format((float) ($snapshot['financial_planned_amount'] ?? 0), 2, ',', '.'),
            number_format((float) ($snapshot['financial_received_amount'] ?? 0), 2, ',', '.'),
            number_format((float) ($snapshot['financial_remaining_amount'] ?? 0), 2, ',', '.'),
            (int) ($snapshot['financial_overdue_count'] ?? 0)
        );
    }

    /** @param array<string, mixed> $snapshot */
    private function buildDocumentsSummaryText(array $snapshot): string
    {
        return sprintf(
            'Documentos: %d vinculados, %d de evidência/comprovação.',
            (int) ($snapshot['documents_count'] ?? 0),
            (int) ($snapshot['evidence_documents_count'] ?? 0)
        );
    }

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $dossier */
    private function buildExecutiveSummaryText(array $snapshot, array $dossier): string
    {
        $sponsor = (string) ($dossier['sponsor_name'] ?? 'Patrocinador');
        return sprintf(
            'Dossiê consolidado de %s: %d contrato(s) (%d assinado(s)), %d contrapartida(s), %d lançamento(s) financeiro(s), %d documento(s).',
            $sponsor,
            (int) ($snapshot['contracts_count'] ?? 0),
            (int) ($snapshot['signed_contracts_count'] ?? 0),
            (int) ($snapshot['counterparts_count'] ?? 0),
            (int) ($snapshot['financial_entries_count'] ?? 0),
            (int) ($snapshot['documents_count'] ?? 0)
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
        foreach (['financial_planned_amount', 'financial_received_amount', 'financial_remaining_amount'] as $money) {
            if (array_key_exists($money, $row)) {
                $n = $this->normalizeMoney($row[$money]);
                $row[$money] = is_float($n) ? $n : null;
            }
        }
        foreach (['period_start', 'period_end'] as $date) {
            if (array_key_exists($date, $row)) {
                $row[$date] = $this->normalizeDate((string) ($row[$date] ?? ''));
            }
        }
        foreach (['generated_at', 'approved_at', 'delivered_at'] as $dt) {
            if (array_key_exists($dt, $row)) {
                $row[$dt] = $this->normalizeDateTime((string) ($row[$dt] ?? ''));
            }
        }
        foreach ([
            'include_contracts', 'include_counterparts', 'include_financials', 'include_documents',
            'include_evidence', 'include_clipping', 'include_media',
        ] as $flag) {
            if (array_key_exists($flag, $row)) {
                $row[$flag] = !empty($row[$flag]) ? 1 : 0;
            }
        }
        foreach ([
            'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'main_contract_id',
            'main_document_id', 'final_document_id', 'delivery_receipt_document_id',
            'responsible_user_id', 'generated_by', 'approved_by', 'delivered_by', 'created_by', 'updated_by',
            'contracts_count', 'signed_contracts_count', 'counterparts_count', 'counterparts_delivered_count',
            'counterparts_partial_count', 'counterparts_pending_count', 'counterparts_overdue_count',
            'financial_entries_count', 'financial_overdue_count', 'documents_count', 'evidence_documents_count',
        ] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] < 0) ? null : (int) $row[$fk];
            }
        }
        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        return $row;
    }
}
