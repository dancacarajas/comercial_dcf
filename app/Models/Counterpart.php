<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model Contrapartidas dos Patrocinadores (Etapa 13).
 */
final class Counterpart extends Model
{
    protected string $table = 'counterparts';

    /** @var list<string> */
    private const FILLABLE = [
        'incentive_project_id',
        'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
        'evidence_document_id', 'title', 'category', 'delivery_type', 'description',
        'promised_quantity', 'delivered_quantity', 'unit', 'priority', 'status',
        'due_date', 'started_at', 'delivered_at', 'approved_at',
        'evidence_description', 'evidence_url', 'responsible_user_id', 'approved_by',
        'notes', 'internal_notes', 'created_by', 'updated_by', 'delivered_by',
    ];

    private const LIST_COLUMNS =
        'cp.`id`, cp.`incentive_project_id`, cp.`sponsor_id`, cp.`company_id`, cp.`contact_id`, cp.`opportunity_id`, cp.`proposal_id`, cp.`quota_id`,
         cp.`evidence_document_id`, cp.`title`, cp.`category`, cp.`delivery_type`, cp.`description`,
         cp.`promised_quantity`, cp.`delivered_quantity`, cp.`unit`, cp.`priority`, cp.`status`,
         cp.`due_date`, cp.`started_at`, cp.`delivered_at`, cp.`approved_at`,
         cp.`evidence_description`, cp.`evidence_url`, cp.`responsible_user_id`, cp.`approved_by`,
         cp.`notes`, cp.`internal_notes`, cp.`created_by`, cp.`updated_by`, cp.`delivered_by`,
         cp.`created_at`, cp.`updated_at`, cp.`archived_at`,
         ip.`project_name` AS project_name,
         sp.`sponsor_display_name` AS sponsor_name,
         co.`name` AS company_name,
         ct.`name` AS contact_name,
         o.`title` AS opportunity_title,
         pr.`title` AS proposal_title,
         q.`name` AS quota_name,
         ed.`title` AS evidence_document_title,
         ru.`name` AS responsible_name,
         ab.`name` AS approved_by_name,
         cb.`name` AS created_by_name,
         ub.`name` AS updated_by_name,
         db.`name` AS delivered_by_name';

    /** @var array<string, string> */
    private const CATEGORIES = [
        'divulgacao_marca'       => 'Divulgação de marca',
        'aplicacao_logomarca'    => 'Aplicação de logomarca',
        'site'                   => 'Site',
        'redes_sociais'          => 'Redes sociais',
        'release_imprensa'       => 'Release / imprensa',
        'midia_kit'              => 'Mídia kit',
        'telao_palco'            => 'Telão / palco',
        'banner_sinalizacao'     => 'Banner / sinalização',
        'credenciais_cortesias'  => 'Credenciais / cortesias',
        'ativacao_marca'         => 'Ativação de marca',
        'estande'                => 'Estande',
        'cerimonial'             => 'Cerimonial / menção oficial',
        'material_grafico'       => 'Material gráfico',
        'relatorio_visibilidade' => 'Relatório de visibilidade',
        'clipping'               => 'Clipping',
        'registro_fotografico'   => 'Registro fotográfico',
        'registro_audiovisual'   => 'Registro audiovisual',
        'documento_comprobatorio'=> 'Documento comprobatório',
        'outra'                  => 'Outra',
    ];

    /** @var array<string, string> */
    private const DELIVERY_TYPES = [
        'entrega_unica'       => 'Entrega única',
        'entrega_recorrente'  => 'Entrega recorrente',
        'entrega_por_evento'  => 'Entrega por evento',
        'entrega_por_postagem'=> 'Entrega por postagem',
        'entrega_por_material'=> 'Entrega por material',
        'entrega_documental'  => 'Entrega documental',
        'entrega_presencial'  => 'Entrega presencial',
        'entrega_digital'     => 'Entrega digital',
        'outro'               => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'planejada'             => 'Planejada',
        'em_execucao'           => 'Em execução',
        'aguardando_material'   => 'Aguardando material',
        'aguardando_aprovacao'  => 'Aguardando aprovação',
        'entrega_parcial'       => 'Entrega parcial',
        'entregue'              => 'Entregue',
        'aprovada'              => 'Aprovada',
        'atrasada'              => 'Atrasada',
        'cancelada'             => 'Cancelada',
        'suspensa'              => 'Suspensa',
        'substituida'           => 'Substituída',
        'arquivada'             => 'Arquivada',
    ];

    /** @var array<string, string> */
    private const PRIORITIES = [
        'baixa'   => 'Baixa',
        'media'   => 'Média',
        'alta'    => 'Alta',
        'critica' => 'Crítica',
    ];

    private const ORDER_BY =
        'CASE WHEN cp.`due_date` IS NOT NULL AND cp.`due_date` < CURDATE()
              AND cp.`status` NOT IN (\'entregue\',\'aprovada\',\'cancelada\',\'arquivada\',\'substituida\') THEN 0 ELSE 1 END ASC,
         CASE cp.`priority` WHEN \'critica\' THEN 0 WHEN \'alta\' THEN 1 WHEN \'media\' THEN 2 ELSE 3 END ASC,
         cp.`due_date` ASC,
         cp.`status` ASC,
         cp.`updated_at` DESC,
         cp.`title` ASC';

    /** @var list<string> */
    private const DELIVERED_STATUSES = ['entregue', 'aprovada'];

    /** @var list<string> */
    private const PENDING_STATUSES = ['planejada', 'em_execucao', 'aguardando_material', 'aguardando_aprovacao', 'atrasada'];

    /** @return array<string, string> */
    public function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /** @return array<string, string> */
    public function getDeliveryTypes(): array
    {
        return self::DELIVERY_TYPES;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<string, string> */
    public function getPriorities(): array
    {
        return self::PRIORITIES;
    }

    public function normalizeDecimal(mixed $value): float|string|null
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

    /**
     * @param array<string, mixed> $counterpart
     */
    public function isOverdue(array $counterpart): bool
    {
        if (($counterpart['status'] ?? '') === 'atrasada') {
            return true;
        }

        $due = $counterpart['due_date'] ?? null;
        if ($due === null || $due === '') {
            return false;
        }

        $status = (string) ($counterpart['status'] ?? '');
        if (in_array($status, ['entregue', 'aprovada', 'cancelada', 'arquivada', 'substituida'], true)) {
            return false;
        }

        return $due < date('Y-m-d');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        if ((int) ($data['incentive_project_id'] ?? 0) <= 0) {
            $errors['incentive_project_id'] = 'Selecione o projeto incentivado da contrapartida.';
        }

        $sponsorId = (int) ($data['sponsor_id'] ?? 0);
        if ($sponsorId <= 0) {
            $errors['sponsor_id'] = 'Selecione o patrocinador / fechamento comercial.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título da contrapartida.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }

        $category = (string) ($data['category'] ?? '');
        if ($category === '' || !array_key_exists($category, self::CATEGORIES)) {
            $errors['category'] = 'Categoria inválida.';
        }

        $deliveryType = (string) ($data['delivery_type'] ?? '');
        if ($deliveryType === '' || !array_key_exists($deliveryType, self::DELIVERY_TYPES)) {
            $errors['delivery_type'] = 'Tipo de entrega inválido.';
        }

        $priority = (string) ($data['priority'] ?? 'media');
        if (!array_key_exists($priority, self::PRIORITIES)) {
            $errors['priority'] = 'Prioridade inválida.';
        }

        $status = (string) ($data['status'] ?? 'planejada');
        if (!array_key_exists($status, self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }

        foreach (['promised_quantity', 'delivered_quantity'] as $qtyField) {
            if (!array_key_exists($qtyField, $data) || $data[$qtyField] === null || $data[$qtyField] === '') {
                continue;
            }
            $qty = $this->normalizeDecimal($data[$qtyField]);
            if (!is_float($qty) || $qty < 0) {
                $errors[$qtyField] = 'Informe uma quantidade numérica válida (zero ou positiva).';
            }
        }

        $promised  = $this->normalizeDecimal($data['promised_quantity'] ?? null);
        $delivered = $this->normalizeDecimal($data['delivered_quantity'] ?? null);
        if (is_float($promised) && is_float($delivered) && $delivered > $promised) {
            $errors['delivered_quantity'] = 'A quantidade entregue não pode ser maior que a prometida.';
        }

        foreach (['due_date'] as $dateField) {
            if (trim((string) ($data[$dateField] ?? '')) !== '' && $this->normalizeDate((string) $data[$dateField]) === null) {
                $errors[$dateField] = 'Data inválida.';
            }
        }

        foreach (['started_at', 'delivered_at', 'approved_at'] as $dtField) {
            if (trim((string) ($data[$dtField] ?? '')) !== '' && $this->normalizeDateTime((string) $data[$dtField]) === null) {
                $errors[$dtField] = 'Data/hora inválida.';
            }
        }

        $url = trim((string) ($data['evidence_url'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors['evidence_url'] = 'Informe uma URL válida para a evidência.';
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
            $conditions[] = 'cp.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $conditions[] = '(cp.`title` LIKE :q1 OR sp.`sponsor_display_name` LIKE :q2 OR co.`name` LIKE :q3
                OR cp.`description` LIKE :q4 OR cp.`evidence_description` LIKE :q5 OR cp.`notes` LIKE :q6 OR cp.`internal_notes` LIKE :q7)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
            $params['q6'] = $like;
            $params['q7'] = $like;
        }

        foreach (['incentive_project_id', 'sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'cp.`' . $fk . '` = :' . $fk;
                $params[$fk]    = $v;
            }
        }

        foreach (['category', 'delivery_type', 'priority', 'status'] as $field) {
            $v = trim((string) ($filters[$field] ?? ''));
            if ($v !== '') {
                $conditions[] = 'cp.`' . $field . '` = :' . $field;
                $params[$field] = $v;
            }
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = '(cp.`status` = \'atrasada\' OR (cp.`due_date` IS NOT NULL AND cp.`due_date` < CURDATE()
                AND cp.`status` NOT IN (\'entregue\',\'aprovada\',\'cancelada\',\'arquivada\',\'substituida\')))';
        }

        if (!empty($filters['delivered'])) {
            $conditions[] = 'cp.`status` IN (\'entregue\',\'aprovada\')';
        }

        if (!empty($filters['pending'])) {
            $conditions[] = 'cp.`status` IN (\'planejada\',\'em_execucao\',\'aguardando_material\',\'aguardando_aprovacao\',\'entrega_parcial\',\'atrasada\')';
        }

        $dueFrom = $this->normalizeDate((string) ($filters['due_from'] ?? ''));
        if ($dueFrom !== null) {
            $conditions[] = 'cp.`due_date` >= :due_from';
            $params['due_from'] = $dueFrom;
        }

        $dueTo = $this->normalizeDate((string) ($filters['due_to'] ?? ''));
        if ($dueTo !== null) {
            $conditions[] = 'cp.`due_date` <= :due_to';
            $params['due_to'] = $dueTo;
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `counterparts` cp
                 LEFT JOIN `incentive_projects` ip ON ip.`id` = cp.`incentive_project_id`
                 INNER JOIN `sponsors` sp ON sp.`id` = cp.`sponsor_id`
                 LEFT JOIN `companies` co ON co.`id` = cp.`company_id`
                 LEFT JOIN `contacts` ct ON ct.`id` = cp.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = cp.`opportunity_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = cp.`proposal_id`
                 LEFT JOIN `quotas` q ON q.`id` = cp.`quota_id`
                 LEFT JOIN `documents` ed ON ed.`id` = cp.`evidence_document_id`
                 LEFT JOIN `users` ru ON ru.`id` = cp.`responsible_user_id`
                 LEFT JOIN `users` ab ON ab.`id` = cp.`approved_by`
                 LEFT JOIN `users` cb ON cb.`id` = cp.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = cp.`updated_by`
                 LEFT JOIN `users` db ON db.`id` = cp.`delivered_by`';
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
            'SELECT ' . self::LIST_COLUMNS . $this->fromJoins() . ' WHERE cp.`id` = :id LIMIT 1',
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
            'INSERT INTO `counterparts` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
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
            'UPDATE `counterparts` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $row
        );
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(int|string $id, array $data): void
    {
        $allowed = ['status', 'notes', 'delivered_at', 'approved_at', 'approved_by', 'updated_by', 'updated_at'];
        $patch   = array_intersect_key($data, array_flip($allowed));
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($patch) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $patch['id'] = $id;

        $this->query(
            'UPDATE `counterparts` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $patch
        );
    }

    /** @param array<string, mixed> $data */
    public function deliver(int|string $id, array $data, int|string $userId): void
    {
        $cp = $this->findById($id);
        if ($cp === null) {
            return;
        }

        $delivered = $data['delivered_quantity'] ?? $cp['delivered_quantity'];
        if ($delivered === null || $delivered === '') {
            $delivered = $cp['promised_quantity'];
        }

        $delivered = $this->normalizeDecimal($delivered);
        $promised  = $this->normalizeDecimal($cp['promised_quantity'] ?? null);

        $status = 'entregue';
        if (is_float($delivered) && is_float($promised)) {
            if ($delivered > 0 && $delivered < $promised) {
                $status = 'entrega_parcial';
            }
        }

        $patch = [
            'delivered_quantity'   => is_float($delivered) ? $delivered : $cp['delivered_quantity'],
            'status'               => $status,
            'delivered_at'         => date('Y-m-d H:i:s'),
            'delivered_by'         => $userId,
            'updated_by'           => $userId,
            'updated_at'           => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['evidence_description'])) {
            $patch['evidence_description'] = trim((string) $data['evidence_description']);
        }
        if (!empty($data['evidence_url'])) {
            $patch['evidence_url'] = trim((string) $data['evidence_url']);
        }
        if (!empty($data['evidence_document_id'])) {
            $patch['evidence_document_id'] = (int) $data['evidence_document_id'];
        }
        if (!empty($data['notes'])) {
            $existing = trim((string) ($cp['notes'] ?? ''));
            $note     = trim((string) $data['notes']);
            $patch['notes'] = $existing !== '' ? $existing . "\n\n" . $note : $note;
        }

        $this->update($id, $patch);
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `counterparts` SET `archived_at` = NOW(), `status` = \'arquivada\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `counterparts` SET `archived_at` = NULL, `status` = \'planejada\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function countActive(): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) FROM `counterparts` WHERE `archived_at` IS NULL'
        )->fetchColumn();
    }

    public function countDelivered(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `counterparts` WHERE `archived_at` IS NULL AND `status` IN ('entregue','aprovada')"
        )->fetchColumn();
    }

    public function countPartial(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `counterparts` WHERE `archived_at` IS NULL AND `status` = 'entrega_parcial'"
        )->fetchColumn();
    }

    public function countOverdue(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `counterparts` WHERE `archived_at` IS NULL AND (
                `status` = 'atrasada'
                OR (`due_date` IS NOT NULL AND `due_date` < CURDATE()
                    AND `status` NOT IN ('entregue','aprovada','cancelada','arquivada','substituida'))
            )"
        )->fetchColumn();
    }

    public function countPending(): int
    {
        return (int) $this->query(
            "SELECT COUNT(*) FROM `counterparts` WHERE `archived_at` IS NULL AND `status` IN ('planejada','em_execucao','aguardando_material','aguardando_aprovacao','entrega_parcial','atrasada')"
        )->fetchColumn();
    }

    /** @return array<int, array<string, mixed>> */
    public function findBySponsor(int|string $sponsorId, int $limit = 10): array
    {
        return $this->findByFk('sponsor_id', $sponsorId, $limit);
    }

    public function countBySponsor(int|string $sponsorId): int
    {
        return $this->countByFk('sponsor_id', $sponsorId);
    }

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
    public function summaryBySponsor(int|string $sponsorId): array
    {
        return $this->summaryByFk('sponsor_id', $sponsorId);
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

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
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

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
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

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
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

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
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

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
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
            . ' WHERE cp.`' . $column . '` = :id AND cp.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . ' LIMIT ' . $limit,
            ['id' => $id]
        )->fetchAll();
    }

    private function countByFk(string $column, int|string $id): int
    {
        return (int) $this->query(
            'SELECT COUNT(*) AS c FROM `counterparts` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetchColumn();
    }

    /** @return array{total:int,delivered:int,partial:int,overdue:int,pending:int} */
    private function summaryByFk(string $column, int|string $id): array
    {
        $row = $this->query(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `status` IN (\'entregue\',\'aprovada\') THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN `status` = \'entrega_parcial\' THEN 1 ELSE 0 END) AS partial,
                SUM(CASE WHEN `status` = \'atrasada\' OR (`due_date` IS NOT NULL AND `due_date` < CURDATE()
                    AND `status` NOT IN (\'entregue\',\'aprovada\',\'cancelada\',\'arquivada\',\'substituida\')) THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN `status` IN (\'planejada\',\'em_execucao\',\'aguardando_material\',\'aguardando_aprovacao\',\'entrega_parcial\',\'atrasada\') THEN 1 ELSE 0 END) AS pending
             FROM `counterparts`
             WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $id]
        )->fetch();

        return [
            'total'    => (int) ($row['total'] ?? 0),
            'delivered'=> (int) ($row['delivered'] ?? 0),
            'partial'  => (int) ($row['partial'] ?? 0),
            'overdue'  => (int) ($row['overdue'] ?? 0),
            'pending'  => (int) ($row['pending'] ?? 0),
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

        foreach (['promised_quantity', 'delivered_quantity'] as $qty) {
            if (array_key_exists($qty, $row)) {
                $n = $this->normalizeDecimal($row[$qty]);
                $row[$qty] = is_float($n) ? $n : null;
            }
        }

        if (array_key_exists('due_date', $row)) {
            $row['due_date'] = $this->normalizeDate((string) ($row['due_date'] ?? ''));
        }

        foreach (['started_at', 'delivered_at', 'approved_at'] as $dt) {
            if (array_key_exists($dt, $row)) {
                $row[$dt] = $this->normalizeDateTime((string) ($row[$dt] ?? ''));
            }
        }

        foreach (['sponsor_id', 'company_id', 'contact_id', 'opportunity_id', 'proposal_id', 'quota_id',
            'evidence_document_id', 'responsible_user_id', 'approved_by', 'created_by', 'updated_by', 'delivered_by'] as $fk) {
            if (array_key_exists($fk, $row)) {
                $row[$fk] = ($row[$fk] === null || $row[$fk] === '' || (int) $row[$fk] <= 0) ? null : (int) $row[$fk];
            }
        }

        if ($isCreate && !isset($row['created_at'])) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }

        $status = (string) ($row['status'] ?? 'planejada');
        if ($status === 'entregue' && empty($row['delivered_at'])) {
            $row['delivered_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'aprovada' && empty($row['approved_at'])) {
            $row['approved_at'] = date('Y-m-d H:i:s');
        }

        $promised  = isset($row['promised_quantity']) ? (float) ($row['promised_quantity'] ?? 0) : null;
        $delivered = isset($row['delivered_quantity']) ? (float) ($row['delivered_quantity'] ?? 0) : null;
        if ($promised !== null && $delivered !== null && $delivered > 0 && $delivered < $promised
            && !in_array($status, ['entregue', 'aprovada', 'cancelada', 'arquivada'], true)) {
            $row['status'] = 'entrega_parcial';
        }

        return $row;
    }
}
