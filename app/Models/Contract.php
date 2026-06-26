<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model Contratos / Instrumentos de Formalização (Etapa 14).
 */
final class Contract extends Model
{
    protected string $table = 'contracts';

    /** @var list<string> */
    private const FILLABLE = [
        'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
        'draft_document_id', 'final_document_id', 'signed_document_id',
        'contract_number', 'title', 'contract_type', 'formalized_value', 'funding_mechanism',
        'status', 'review_status', 'signature_status',
        'start_date', 'end_date', 'sent_for_signature_at', 'signed_at', 'effective_at', 'ended_at',
        'sponsor_signatory_name', 'sponsor_signatory_email', 'sponsor_signatory_position', 'sponsor_signatory_document',
        'organization_signatory_name', 'organization_signatory_email', 'organization_signatory_position',
        'approval_notes', 'signature_notes', 'legal_notes', 'notes', 'internal_notes',
        'responsible_user_id', 'approved_by', 'signed_registered_by',
        'created_by', 'updated_by', 'approved_at',
    ];

    private const LIST_COLUMNS =
        'ct.`id`, ct.`sponsor_id`, ct.`company_id`, ct.`contact_id`, ct.`opportunity_id`, ct.`proposal_id`, ct.`quota_id`,
         ct.`draft_document_id`, ct.`final_document_id`, ct.`signed_document_id`,
         ct.`contract_number`, ct.`title`, ct.`contract_type`, ct.`formalized_value`, ct.`funding_mechanism`,
         ct.`status`, ct.`review_status`, ct.`signature_status`,
         ct.`start_date`, ct.`end_date`, ct.`sent_for_signature_at`, ct.`signed_at`, ct.`effective_at`, ct.`ended_at`,
         ct.`sponsor_signatory_name`, ct.`sponsor_signatory_email`, ct.`sponsor_signatory_position`, ct.`sponsor_signatory_document`,
         ct.`organization_signatory_name`, ct.`organization_signatory_email`, ct.`organization_signatory_position`,
         ct.`approval_notes`, ct.`signature_notes`, ct.`legal_notes`, ct.`notes`, ct.`internal_notes`,
         ct.`responsible_user_id`, ct.`approved_by`, ct.`signed_registered_by`,
         ct.`created_by`, ct.`updated_by`, ct.`created_at`, ct.`updated_at`, ct.`approved_at`, ct.`archived_at`,
         sp.`sponsor_display_name` AS sponsor_name,
         co.`name` AS company_name,
         ctt.`name` AS contact_name,
         o.`title` AS opportunity_title,
         pr.`title` AS proposal_title,
         q.`name` AS quota_name,
         dd.`title` AS draft_document_title,
         fd.`title` AS final_document_title,
         sd.`title` AS signed_document_title,
         ru.`name` AS responsible_name,
         ab.`name` AS approved_by_name,
         srb.`name` AS signed_registered_by_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name';

    /** @var array<string, string> */
    private const CONTRACT_TYPES = [
        'termo_patrocinio'         => 'Termo de patrocínio',
        'contrato_patrocinio'      => 'Contrato de patrocínio',
        'termo_apoio'              => 'Termo de apoio',
        'termo_permuta'            => 'Termo de permuta',
        'termo_cooperacao'         => 'Termo de cooperação',
        'carta_intencao'           => 'Carta de intenção',
        'instrumento_formalizacao' => 'Instrumento de formalização',
        'aditivo'                  => 'Aditivo',
        'distrato'                 => 'Distrato',
        'outro'                    => 'Outro',
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
        'minuta'                   => 'Minuta',
        'em_elaboracao'            => 'Em elaboração',
        'em_revisao'               => 'Em revisão',
        'aprovado_internamente'    => 'Aprovado internamente',
        'enviado_para_assinatura'  => 'Enviado para assinatura',
        'aguardando_assinatura'    => 'Aguardando assinatura',
        'assinado'                 => 'Assinado',
        'vigente'                  => 'Vigente',
        'encerrado'                => 'Encerrado',
        'cancelado'                => 'Cancelado',
        'suspenso'                 => 'Suspenso',
        'substituido'              => 'Substituído',
        'arquivado'                => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const REVIEW_STATUSES = [
        'nao_revisado'        => 'Não revisado',
        'em_revisao'          => 'Em revisão',
        'ajustes_solicitados' => 'Ajustes solicitados',
        'aprovado_comercial'  => 'Aprovado comercialmente',
        'aprovado_juridico'   => 'Aprovado juridicamente',
        'aprovado_final'      => 'Aprovado final',
        'reprovado'           => 'Reprovado',
        'nao_aplicavel'       => 'Não aplicável',
    ];

    /** @var array<string, string> */
    private const SIGNATURE_STATUSES = [
        'nao_enviado'           => 'Não enviado',
        'enviado_manual'        => 'Enviado manualmente',
        'aguardando_assinatura' => 'Aguardando assinatura',
        'parcialmente_assinado' => 'Parcialmente assinado',
        'assinado'              => 'Assinado',
        'recusado'              => 'Recusado',
        'cancelado'             => 'Cancelado',
        'nao_aplicavel'         => 'Não aplicável',
    ];

    private const ORDER_BY =
        'CASE WHEN ct.`status` IN (\'enviado_para_assinatura\',\'aguardando_assinatura\')
              OR ct.`signature_status` IN (\'enviado_manual\',\'aguardando_assinatura\',\'parcialmente_assinado\') THEN 0 ELSE 1 END ASC,
         CASE WHEN ct.`end_date` IS NOT NULL AND ct.`end_date` < CURDATE()
              AND ct.`status` NOT IN (\'encerrado\',\'cancelado\',\'arquivado\',\'substituido\',\'assinado\',\'vigente\') THEN 0 ELSE 1 END ASC,
         ct.`end_date` ASC,
         ct.`status` ASC,
         ct.`updated_at` DESC,
         ct.`title` ASC';

    /** @return array<string, string> */
    public function getContractTypes(): array { return self::CONTRACT_TYPES; }

    /** @return array<string, string> */
    public function getFundingMechanisms(): array { return self::FUNDING_MECHANISMS; }

    /** @return array<string, string> */
    public function getStatuses(): array { return self::STATUSES; }

    /** @return array<string, string> */
    public function getReviewStatuses(): array { return self::REVIEW_STATUSES; }

    /** @return array<string, string> */
    public function getSignatureStatuses(): array { return self::SIGNATURE_STATUSES; }

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

    /** @param array<string, mixed> $contract */
    public function isExpired(array $contract): bool
    {
        $end = $contract['end_date'] ?? null;
        if ($end === null || $end === '') {
            return false;
        }
        $status = (string) ($contract['status'] ?? '');
        if (in_array($status, ['assinado', 'vigente', 'encerrado', 'cancelado', 'arquivado', 'substituido'], true)) {
            return false;
        }
        return $end < date('Y-m-d');
    }

    /** @param array<string, mixed> $contract */
    public function isExpiringSoon(array $contract, int $days = 30): bool
    {
        if ($this->isExpired($contract)) {
            return false;
        }
        $end = $contract['end_date'] ?? null;
        if ($end === null || $end === '') {
            return false;
        }
        $limit = (new \DateTimeImmutable('today'))->modify('+' . max(1, $days) . ' days')->format('Y-m-d');
        return $end <= $limit;
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
            $errors['title'] = 'Informe o título do contrato / instrumento.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }
        if (!array_key_exists((string) ($data['contract_type'] ?? ''), self::CONTRACT_TYPES)) {
            $errors['contract_type'] = 'Tipo de instrumento inválido.';
        }
        if (!array_key_exists((string) ($data['funding_mechanism'] ?? 'nao_definido'), self::FUNDING_MECHANISMS)) {
            $errors['funding_mechanism'] = 'Mecanismo de formalização inválido.';
        }
        if (!array_key_exists((string) ($data['status'] ?? 'minuta'), self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }
        if (!array_key_exists((string) ($data['review_status'] ?? 'nao_revisado'), self::REVIEW_STATUSES)) {
            $errors['review_status'] = 'Status de revisão inválido.';
        }
        if (!array_key_exists((string) ($data['signature_status'] ?? 'nao_enviado'), self::SIGNATURE_STATUSES)) {
            $errors['signature_status'] = 'Status de assinatura inválido.';
        }
        if (array_key_exists('formalized_value', $data) && $data['formalized_value'] !== null && $data['formalized_value'] !== '') {
            $money = $this->normalizeMoney($data['formalized_value']);
            if (!is_float($money) || $money < 0) {
                $errors['formalized_value'] = 'Informe um valor formalizado numérico válido (zero ou positivo).';
            }
        }
        foreach (['start_date', 'end_date', 'effective_at', 'ended_at'] as $f) {
            if (trim((string) ($data[$f] ?? '')) !== '' && $this->normalizeDate((string) $data[$f]) === null) {
                $errors[$f] = 'Data inválida.';
            }
        }
        foreach (['sent_for_signature_at', 'signed_at'] as $f) {
            if (trim((string) ($data[$f] ?? '')) !== '' && $this->normalizeDateTime((string) $data[$f]) === null) {
                $errors[$f] = 'Data/hora inválida.';
            }
        }
        $start = $this->normalizeDate((string) ($data['start_date'] ?? ''));
        $end = $this->normalizeDate((string) ($data['end_date'] ?? ''));
        if ($start !== null && $end !== null && $end < $start) {
            $errors['end_date'] = 'A data final não pode ser anterior à data inicial.';
        }
        $sent = $this->normalizeDateTime((string) ($data['sent_for_signature_at'] ?? ''));
        $signed = $this->normalizeDateTime((string) ($data['signed_at'] ?? ''));
        if ($sent !== null && $signed !== null && $signed < $sent) {
            $errors['signed_at'] = 'A data de assinatura não pode ser anterior ao envio para assinatura.';
        }
        foreach (['sponsor_signatory_email' => 'E-mail do signatário do patrocinador inválido.', 'organization_signatory_email' => 'E-mail do signatário da organização inválido.'] as $f => $msg) {
            $email = trim((string) ($data[$f] ?? ''));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$f] = $msg;
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
            $conditions[] = 'ct.`archived_at` IS NULL';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(ct.`title` LIKE :q1 OR ct.`contract_number` LIKE :q2 OR sp.`sponsor_display_name` LIKE :q3 OR co.`name` LIKE :q4 OR ct.`notes` LIKE :q5 OR ct.`internal_notes` LIKE :q6 OR ct.`legal_notes` LIKE :q7 OR ct.`approval_notes` LIKE :q8 OR ct.`signature_notes` LIKE :q9)';
            for ($i = 1; $i <= 9; $i++) {
                $params['q' . $i] = $like;
            }
        }
        foreach (['sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'ct.`' . $fk . '` = :' . $fk;
                $params[$fk] = $v;
            }
        }
        foreach (['contract_type', 'funding_mechanism', 'status', 'review_status', 'signature_status'] as $field) {
            $v = trim((string) ($filters[$field] ?? ''));
            if ($v !== '') {
                $conditions[] = 'ct.`' . $field . '` = :' . $field;
                $params[$field] = $v;
            }
        }
        if (!empty($filters['expired'])) {
            $conditions[] = '(ct.`end_date` IS NOT NULL AND ct.`end_date` < CURDATE() AND ct.`status` NOT IN (\'encerrado\',\'cancelado\',\'arquivado\',\'substituido\',\'assinado\',\'vigente\'))';
        }
        if (!empty($filters['active_vigente'])) {
            $conditions[] = 'ct.`status` = \'vigente\'';
        }
        if (!empty($filters['awaiting_signature'])) {
            $conditions[] = '(ct.`status` IN (\'enviado_para_assinatura\',\'aguardando_assinatura\') OR ct.`signature_status` IN (\'enviado_manual\',\'aguardando_assinatura\',\'parcialmente_assinado\'))';
        }
        if (!empty($filters['signed'])) {
            $conditions[] = '(ct.`status` IN (\'assinado\',\'vigente\') OR ct.`signature_status` = \'assinado\')';
        }
        $startFrom = $this->normalizeDate((string) ($filters['start_from'] ?? ''));
        if ($startFrom !== null) {
            $conditions[] = 'ct.`start_date` >= :start_from';
            $params['start_from'] = $startFrom;
        }
        $endTo = $this->normalizeDate((string) ($filters['end_to'] ?? ''));
        if ($endTo !== null) {
            $conditions[] = 'ct.`end_date` <= :end_to';
            $params['end_to'] = $endTo;
        }
        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `contracts` ct
                 INNER JOIN `sponsors` sp ON sp.`id` = ct.`sponsor_id`
                 LEFT JOIN `companies` co ON co.`id` = ct.`company_id`
                 LEFT JOIN `contacts` ctt ON ctt.`id` = ct.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = ct.`opportunity_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = ct.`proposal_id`
                 LEFT JOIN `quotas` q ON q.`id` = ct.`quota_id`
                 LEFT JOIN `documents` dd ON dd.`id` = ct.`draft_document_id`
                 LEFT JOIN `documents` fd ON fd.`id` = ct.`final_document_id`
                 LEFT JOIN `documents` sd ON sd.`id` = ct.`signed_document_id`
                 LEFT JOIN `users` ru ON ru.`id` = ct.`responsible_user_id`
                 LEFT JOIN `users` ab ON ab.`id` = ct.`approved_by`
                 LEFT JOIN `users` srb ON srb.`id` = ct.`signed_registered_by`
                 LEFT JOIN `users` cb ON cb.`id` = ct.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = ct.`updated_by`';
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
        $row = $this->query('SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE ct.`id` = :id LIMIT 1', ['id' => $id])->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $row = $this->prepareRow($data);
        $cols = array_keys($row);
        $ph = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query('INSERT INTO `contracts` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')', $row);
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
        $this->query('UPDATE `contracts` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $row);
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int|string $id, array $data): void
    {
        $allowed = ['status', 'review_status', 'signature_status', 'notes', 'internal_notes', 'sent_for_signature_at', 'signed_at', 'effective_at', 'ended_at', 'approved_at', 'approved_by', 'signed_registered_by', 'updated_by', 'updated_at'];
        $patch = array_intersect_key($data, array_flip($allowed));
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($patch) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $patch['id'] = $id;
        $this->query('UPDATE `contracts` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $patch);
    }

    /** @param array<string, mixed> $data */
    public function approve(int|string $id, array $data, int|string $userId): void
    {
        $current = $this->findById($id);
        if ($current === null) {
            return;
        }
        $status = (string) ($current['status'] ?? '');
        $newStatus = in_array($status, ['assinado', 'vigente', 'encerrado'], true) ? $status : 'aprovado_internamente';
        $patch = ['status' => $newStatus, 'review_status' => 'aprovado_final', 'approved_at' => date('Y-m-d H:i:s'), 'approved_by' => (int) $userId, 'updated_by' => (int) $userId, 'updated_at' => date('Y-m-d H:i:s')];
        if (!empty($data['approval_notes'])) {
            $patch['approval_notes'] = trim((string) $data['approval_notes']);
        }
        $this->updateStatus($id, $patch);
    }

    /** @param array<string, mixed> $data */
    public function markSigned(int|string $id, array $data, int|string $userId): void
    {
        $status = trim((string) ($data['status'] ?? ''));
        if ($status === '' || !array_key_exists($status, self::STATUSES)) {
            $status = 'assinado';
        }
        $patch = [
            'status' => $status,
            'signature_status' => 'assinado',
            'signed_at' => $this->normalizeDateTime((string) ($data['signed_at'] ?? '')) ?? date('Y-m-d H:i:s'),
            'signed_registered_by' => (int) $userId,
            'updated_by' => (int) $userId,
        ];
        if (!empty($data['signed_document_id'])) {
            $patch['signed_document_id'] = (int) $data['signed_document_id'];
        }
        if (!empty($data['signature_notes'])) {
            $patch['signature_notes'] = trim((string) $data['signature_notes']);
        }
        $this->update($id, $patch);
    }

    public function archive(int|string $id): void
    {
        $this->query('UPDATE `contracts` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function restore(int|string $id): void
    {
        $this->query('UPDATE `contracts` SET `archived_at` = NULL, `status` = \'minuta\', `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function countActive(): int
    {
        return (int) $this->query('SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL')->fetchColumn();
    }

    public function countSigned(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL AND (`status` IN ('assinado','vigente') OR `signature_status` = 'assinado')")->fetchColumn();
    }

    public function countAwaitingSignature(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL AND (`status` IN ('enviado_para_assinatura','aguardando_assinatura') OR `signature_status` IN ('enviado_manual','aguardando_assinatura','parcialmente_assinado'))")->fetchColumn();
    }

    public function countVigente(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL AND `status` = 'vigente'")->fetchColumn();
    }

    public function countExpired(): int
    {
        return (int) $this->query("SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL AND `end_date` IS NOT NULL AND `end_date` < CURDATE() AND `status` NOT IN ('encerrado','cancelado','arquivado','substituido','assinado','vigente')")->fetchColumn();
    }

    public function countExpiringSoon(int $days = 30): int
    {
        $limit = (new \DateTimeImmutable('today'))->modify('+' . max(1, $days) . ' days')->format('Y-m-d');
        return (int) $this->query("SELECT COUNT(*) FROM `contracts` WHERE `archived_at` IS NULL AND `end_date` IS NOT NULL AND `end_date` >= CURDATE() AND `end_date` <= :limit AND `status` NOT IN ('encerrado','cancelado','arquivado','substituido')", ['limit' => $limit])->fetchColumn();
    }

    public function sumFormalized(): float
    {
        return (float) $this->query('SELECT COALESCE(SUM(`formalized_value`), 0) FROM `contracts` WHERE `archived_at` IS NULL')->fetchColumn();
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
    private function findByFk(string $column, int|string $id, int $limit): array
    {
        $limit = max(1, $limit);
        return $this->query('SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE ct.`' . $column . '` = :id AND ct.`archived_at` IS NULL ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $limit, ['id' => $id])->fetchAll();
    }

    private function countByFk(string $column, int|string $id): int
    {
        return (int) $this->query('SELECT COUNT(*) AS c FROM `contracts` WHERE `' . $column . '` = :id AND `archived_at` IS NULL', ['id' => $id])->fetchColumn();
    }

    /** @return array<string, int|float> */
    private function summaryByFk(string $column, int|string $id): array
    {
        $row = $this->query(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN `status` IN (\'assinado\',\'vigente\') OR `signature_status` = \'assinado\' THEN 1 ELSE 0 END) AS signed,
                SUM(CASE WHEN `status` IN (\'enviado_para_assinatura\',\'aguardando_assinatura\') OR `signature_status` IN (\'enviado_manual\',\'aguardando_assinatura\',\'parcialmente_assinado\') THEN 1 ELSE 0 END) AS awaiting_signature,
                SUM(CASE WHEN `status` = \'vigente\' THEN 1 ELSE 0 END) AS vigente,
                SUM(CASE WHEN `end_date` IS NOT NULL AND `end_date` < CURDATE() AND `status` NOT IN (\'encerrado\',\'cancelado\',\'arquivado\',\'substituido\',\'assinado\',\'vigente\') THEN 1 ELSE 0 END) AS expired,
                COALESCE(SUM(`formalized_value`), 0) AS formalized_total
             FROM `contracts` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();
        return [
            'total' => (int) ($row['total'] ?? 0),
            'signed' => (int) ($row['signed'] ?? 0),
            'awaiting_signature' => (int) ($row['awaiting_signature'] ?? 0),
            'vigente' => (int) ($row['vigente'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
            'formalized_total' => (float) ($row['formalized_total'] ?? 0),
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
        if (array_key_exists('formalized_value', $row)) {
            $n = $this->normalizeMoney($row['formalized_value']);
            $row['formalized_value'] = is_float($n) ? $n : null;
        }
        foreach (['start_date', 'end_date', 'effective_at', 'ended_at'] as $date) {
            if (array_key_exists($date, $row)) {
                $row[$date] = $this->normalizeDate((string) ($row[$date] ?? ''));
            }
        }
        foreach (['sent_for_signature_at', 'signed_at', 'approved_at'] as $dt) {
            if (array_key_exists($dt, $row)) {
                $row[$dt] = $this->normalizeDateTime((string) ($row[$dt] ?? ''));
            }
        }
        foreach (['sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'draft_document_id', 'final_document_id', 'signed_document_id', 'responsible_user_id', 'approved_by', 'signed_registered_by', 'created_by', 'updated_by'] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }
        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        $status = (string) ($row['status'] ?? 'minuta');
        $signatureStatus = (string) ($row['signature_status'] ?? 'nao_enviado');
        if ($status === 'enviado_para_assinatura' && empty($row['sent_for_signature_at'])) {
            $row['sent_for_signature_at'] = date('Y-m-d H:i:s');
        }
        if (($status === 'assinado' || $signatureStatus === 'assinado') && empty($row['signed_at'])) {
            $row['signed_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'aprovado_internamente' && empty($row['approved_at'])) {
            $row['approved_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'vigente' && empty($row['effective_at'])) {
            $row['effective_at'] = date('Y-m-d');
        }
        if ($status === 'encerrado' && empty($row['ended_at'])) {
            $row['ended_at'] = date('Y-m-d');
        }
        return $row;
    }
}
