<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Módulo Documentos e Arquivos (Etapa 11).
 */
final class Document extends Model
{
    protected string $table = 'documents';

    /** @var array<string, string> */
    private const CATEGORIES = [
        'one_page'                  => 'One-page',
        'deck_patrocinio'           => 'Deck de patrocínio',
        'midia_kit'                 => 'Mídia kit',
        'proposta_pdf'              => 'Proposta em PDF',
        'apresentacao_institucional'=> 'Apresentação institucional',
        'dados_oficiais'            => 'Dados oficiais do projeto',
        'lei_rouanet_pronac'        => 'Lei Rouanet / PRONAC',
        'plano_cotas'               => 'Plano de cotas',
        'briefing_empresa'          => 'Briefing de empresa',
        'ata_reuniao'               => 'Ata ou registro de reunião',
        'comprovante_envio'         => 'Comprovante de envio',
        'imagem_institucional'      => 'Imagem institucional',
        'planilha_apoio'            => 'Planilha de apoio',
        'documento_comercial'       => 'Documento comercial',
        'outro'                     => 'Outro',
    ];

    /** @var array<string, string> */
    private const STATUSES = [
        'ativo'       => 'Ativo',
        'em_revisao'  => 'Em revisão',
        'aprovado'    => 'Aprovado',
        'enviado'     => 'Enviado',
        'substituido' => 'Substituído',
        'expirado'    => 'Expirado',
        'arquivado'   => 'Arquivado',
    ];

    /** @var array<string, string> */
    private const ACCESS_LEVELS = [
        'publico_comercial' => 'Público comercial',
        'interno'           => 'Interno',
        'restrito'          => 'Restrito',
        'confidencial'      => 'Confidencial',
    ];

    /** @var list<string> */
    private const ACTIVE_STATUSES = ['ativo', 'em_revisao', 'aprovado', 'enviado'];

    /** @var list<string> */
    private const CLOSED_STATUSES = ['substituido', 'expirado', 'arquivado'];

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'phar', 'exe', 'js', 'html', 'htm', 'svg', 'sh', 'bat', 'cmd',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_MIMES = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
        'txt'  => ['text/plain'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
    ];

    /** @var list<string> */
    private const FILLABLE = [
        'company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id',
        'title', 'description', 'category', 'status', 'access_level',
        'file_path', 'original_name', 'stored_name', 'extension', 'mime_type', 'size_bytes', 'checksum_sha256',
        'version_number', 'parent_document_id', 'document_date', 'valid_until',
        'responsible_user_id', 'notes', 'created_by', 'updated_by',
    ];

    private const LIST_COLUMNS = '
        d.`id`, d.`company_id`, d.`contact_id`, d.`opportunity_id`, d.`quota_id`, d.`proposal_id`, d.`lead_id`,
        d.`title`, d.`description`, d.`category`, d.`status`, d.`access_level`,
        d.`original_name`, d.`extension`, d.`mime_type`, d.`size_bytes`, d.`checksum_sha256`,
        d.`version_number`, d.`parent_document_id`, d.`document_date`, d.`valid_until`,
        d.`responsible_user_id`, d.`notes`, d.`created_by`, d.`updated_by`,
        d.`created_at`, d.`updated_at`, d.`archived_at`,
        co.`name` AS company_name,
        ct.`name` AS contact_name,
        o.`title` AS opportunity_title,
        q.`name` AS quota_name,
        pr.`title` AS proposal_title,
        l.`name` AS lead_name,
        ru.`name` AS responsible_name,
        cb.`name` AS created_by_name,
        ub.`name` AS updated_by_name,
        pd.`title` AS parent_title
    ';

    private const ORDER_BY = '
        (CASE WHEN d.`valid_until` IS NOT NULL AND d.`valid_until` < CURDATE()
              AND d.`status` NOT IN (\'substituido\',\'expirado\',\'arquivado\') THEN 0 ELSE 1 END) ASC,
        d.`valid_until` ASC,
        d.`updated_at` DESC,
        d.`created_at` DESC
    ';

    /** @return array<string, string> */
    public function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    /** @return array<string, string> */
    public function getAccessLevels(): array
    {
        return self::ACCESS_LEVELS;
    }

    public function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        $dt = \DateTimeImmutable::createFromFormat('d/m/Y', $value);

        return $dt !== false ? $dt->format('Y-m-d') : null;
    }

    public function formatSize(int|string $bytes): string
    {
        $b = (int) $bytes;
        if ($b < 1024) {
            return $b . ' B';
        }
        if ($b < 1048576) {
            return number_format($b / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($b / 1048576, 2, ',', '.') . ' MB';
    }

    /** @param array<string, mixed> $document */
    public function isExpired(array $document): bool
    {
        $valid = (string) ($document['valid_until'] ?? '');
        if ($valid === '') {
            return false;
        }
        $status = (string) ($document['status'] ?? '');
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            return false;
        }

        return $valid < date('Y-m-d');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título do documento.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter ao menos 3 caracteres.';
        }

        $category = (string) ($data['category'] ?? '');
        if ($category === '' || !array_key_exists($category, self::CATEGORIES)) {
            $errors['category'] = 'Categoria inválida.';
        }

        $status = (string) ($data['status'] ?? 'ativo');
        if (!array_key_exists($status, self::STATUSES)) {
            $errors['status'] = 'Status inválido.';
        }

        $access = (string) ($data['access_level'] ?? 'interno');
        if (!array_key_exists($access, self::ACCESS_LEVELS)) {
            $errors['access_level'] = 'Nível de acesso inválido.';
        }

        if (trim((string) ($data['document_date'] ?? '')) !== ''
            && $this->normalizeDate((string) $data['document_date']) === null) {
            $errors['document_date'] = 'Data do documento inválida.';
        }

        if (trim((string) ($data['valid_until'] ?? '')) !== ''
            && $this->normalizeDate((string) $data['valid_until']) === null) {
            $errors['valid_until'] = 'Data de validade inválida.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, string>
     */
    public function validateUpload(array $file, bool $required = true): array
    {
        $errors = [];
        $errNo  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errNo === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                $errors['document_file'] = 'Selecione um arquivo.';
            }

            return $errors;
        }

        if ($errNo !== UPLOAD_ERR_OK) {
            $errors['document_file'] = 'Falha no upload do arquivo.';
            return $errors;
        }

        $maxBytes = 25 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            $errors['document_file'] = 'O arquivo deve ter no máximo 25 MB.';
        }

        $name = (string) ($file['name'] ?? '');
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext === '' || in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            $errors['document_file'] = 'Tipo de arquivo não permitido.';
            return $errors;
        }

        if (!array_key_exists($ext, self::ALLOWED_MIMES)) {
            $errors['document_file'] = 'Extensão não permitida. Envie PDF, Office, imagens, CSV, TXT ou ZIP.';
            return $errors;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp !== '' && is_uploaded_file($tmp)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo !== false ? (string) finfo_file($finfo, $tmp) : '';
            if ($finfo !== false) {
                finfo_close($finfo);
            }
            $allowed = self::ALLOWED_MIMES[$ext];
            if ($mime !== '' && !in_array($mime, $allowed, true)) {
                $errors['document_file'] = 'O conteúdo do arquivo não corresponde à extensão informada.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path:string,original_name:string,stored_name:string,extension:string,mime_type:string,size_bytes:int,checksum_sha256:string}
     */
    public function storeUpload(array $file): array
    {
        $baseDir = dirname(__DIR__, 2) . '/storage/uploads/documents';
        $subDir  = date('Y') . '/' . date('m');
        $dir     = $baseDir . '/' . $subDir;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $original = basename((string) ($file['name'] ?? 'documento.bin'));
        $original = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $original) ?? 'documento.bin';
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $stored   = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = $dir . '/' . $stored;

        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            throw new \RuntimeException('Não foi possível salvar o arquivo.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo !== false ? (string) finfo_file($finfo, $dest) : 'application/octet-stream';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        $checksum = hash_file('sha256', $dest) ?: '';

        return [
            'path'             => $dest,
            'original_name'    => $original,
            'stored_name'      => $stored,
            'extension'        => $ext,
            'mime_type'        => $mime,
            'size_bytes'       => (int) filesize($dest),
            'checksum_sha256'  => $checksum,
        ];
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
            $conditions[] = 'd.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(d.`title` LIKE :q OR d.`description` LIKE :q OR d.`original_name` LIKE :q
                OR co.`name` LIKE :q OR o.`title` LIKE :q OR pr.`title` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id', 'responsible_user_id'] as $fk) {
            $v = (int) ($filters[$fk] ?? 0);
            if ($v > 0) {
                $conditions[] = 'd.`' . $fk . '` = :' . $fk;
                $params[$fk]  = $v;
            }
        }

        foreach (['category', 'status', 'access_level'] as $col) {
            if (!empty($filters[$col])) {
                $conditions[] = 'd.`' . $col . '` = :' . $col;
                $params[$col]  = (string) $filters[$col];
            }
        }

        if (!empty($filters['expired'])) {
            $conditions[] = 'd.`valid_until` IS NOT NULL AND d.`valid_until` < CURDATE()
                AND d.`status` NOT IN (\'substituido\',\'expirado\',\'arquivado\')';
        }

        if (!empty($filters['valid_from'])) {
            $d = $this->normalizeDate((string) $filters['valid_from']);
            if ($d !== null) {
                $conditions[] = 'd.`valid_until` >= :valid_from';
                $params['valid_from'] = $d;
            }
        }

        if (!empty($filters['valid_to'])) {
            $d = $this->normalizeDate((string) $filters['valid_to']);
            if ($d !== null) {
                $conditions[] = 'd.`valid_until` <= :valid_to';
                $params['valid_to'] = $d;
            }
        }

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    private function fromJoins(): string
    {
        return ' FROM `documents` d
                 LEFT JOIN `companies` co ON co.`id` = d.`company_id`
                 LEFT JOIN `contacts` ct ON ct.`id` = d.`contact_id`
                 LEFT JOIN `opportunities` o ON o.`id` = d.`opportunity_id`
                 LEFT JOIN `quotas` q ON q.`id` = d.`quota_id`
                 LEFT JOIN `proposals` pr ON pr.`id` = d.`proposal_id`
                 LEFT JOIN `leads` l ON l.`id` = d.`lead_id`
                 LEFT JOIN `users` ru ON ru.`id` = d.`responsible_user_id`
                 LEFT JOIN `users` cb ON cb.`id` = d.`created_by`
                 LEFT JOIN `users` ub ON ub.`id` = d.`updated_by`
                 LEFT JOIN `documents` pd ON pd.`id` = d.`parent_document_id`';
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
            'SELECT ' . self::LIST_COLUMNS . ', d.`file_path`' . $this->fromJoins() . ' WHERE d.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    public function countActive(): int
    {
        $active = implode(',', array_map(static fn ($s) => "'" . $s . "'", self::ACTIVE_STATUSES));
        $row    = $this->query(
            "SELECT COUNT(*) AS c FROM `documents` WHERE `archived_at` IS NULL AND `status` IN ({$active})"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countExpired(): int
    {
        $closed = implode(',', array_map(static fn ($s) => "'" . $s . "'", self::CLOSED_STATUSES));
        $row    = $this->query(
            "SELECT COUNT(*) AS c FROM `documents`
              WHERE `archived_at` IS NULL
                AND `valid_until` IS NOT NULL AND `valid_until` < CURDATE()
                AND `status` NOT IN ({$closed})"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countExpiringSoon(int $days = 30): int
    {
        $closed = implode(',', array_map(static fn ($s) => "'" . $s . "'", self::CLOSED_STATUSES));
        $row    = $this->query(
            "SELECT COUNT(*) AS c FROM `documents`
              WHERE `archived_at` IS NULL
                AND `valid_until` IS NOT NULL
                AND `valid_until` >= CURDATE()
                AND `valid_until` <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                AND `status` NOT IN ({$closed})",
            ['days' => max(1, $days)]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function filterProposalOptions(): array
    {
        return $this->query(
            'SELECT `id`, `title` AS label FROM `proposals` WHERE `archived_at` IS NULL ORDER BY `title` ASC LIMIT 300'
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function filterLeadOptions(): array
    {
        return $this->query(
            'SELECT `id`, `name` AS label FROM `leads` WHERE `archived_at` IS NULL ORDER BY `name` ASC LIMIT 300'
        )->fetchAll();
    }

    /** @return array<string, int> */
    public function countByCategory(): array
    {
        $rows = $this->query(
            "SELECT `category`, COUNT(*) AS c FROM `documents`
              WHERE `archived_at` IS NULL GROUP BY `category` ORDER BY c DESC"
        )->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['category']] = (int) ($row['c'] ?? 0);
        }

        return $out;
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

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
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

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
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

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
    public function summaryByOpportunity(int|string $opportunityId): array
    {
        return $this->summaryByFk('opportunity_id', $opportunityId);
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

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
    public function summaryByQuota(int|string $quotaId): array
    {
        return $this->summaryByFk('quota_id', $quotaId);
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

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
    public function summaryByProposal(int|string $proposalId): array
    {
        return $this->summaryByFk('proposal_id', $proposalId);
    }

    /** @return array<int, array<string, mixed>> */
    public function findByLead(int|string $leadId, int $limit = 10): array
    {
        return $this->findByFk('lead_id', $leadId, $limit);
    }

    public function countByLead(int|string $leadId): int
    {
        return $this->countByFk('lead_id', $leadId);
    }

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
    public function summaryByLead(int|string $leadId): array
    {
        return $this->summaryByFk('lead_id', $leadId);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $file
     */
    public function insertWithFile(array $data, array $file): string
    {
        $stored = $this->storeUpload($file);
        $row    = $this->prepareRow(array_merge($data, [
            'file_path'         => $stored['path'],
            'original_name'     => $stored['original_name'],
            'stored_name'       => $stored['stored_name'],
            'extension'         => $stored['extension'],
            'mime_type'         => $stored['mime_type'],
            'size_bytes'        => $stored['size_bytes'],
            'checksum_sha256'   => $stored['checksum_sha256'],
        ]));

        $cols = array_keys($row);
        $ph   = array_map(static fn ($c) => ':' . $c, $cols);

        $this->query(
            'INSERT INTO `documents` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $ph) . ')',
            $row
        );

        return (string) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $file
     */
    public function update(int|string $id, array $data, ?array $file = null): void
    {
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $stored = $this->storeUpload($file);
            $data   = array_merge($data, [
                'file_path'       => $stored['path'],
                'original_name'   => $stored['original_name'],
                'stored_name'     => $stored['stored_name'],
                'extension'       => $stored['extension'],
                'mime_type'       => $stored['mime_type'],
                'size_bytes'      => $stored['size_bytes'],
                'checksum_sha256' => $stored['checksum_sha256'],
            ]);
        }

        $row = $this->prepareRow($data, false);
        $row['updated_at'] = date('Y-m-d H:i:s');
        $sets = [];
        foreach (array_keys($row) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $row['id'] = $id;

        $this->query(
            'UPDATE `documents` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $row
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $file
     */
    public function createVersion(int|string $baseId, array $data, array $file): string
    {
        $base = $this->findById($baseId);
        if ($base === null) {
            throw new \InvalidArgumentException('Documento base não encontrado.');
        }

        $merged = array_merge([
            'company_id'          => $base['company_id'],
            'contact_id'          => $base['contact_id'],
            'opportunity_id'      => $base['opportunity_id'],
            'quota_id'            => $base['quota_id'],
            'proposal_id'         => $base['proposal_id'],
            'lead_id'             => $base['lead_id'],
            'title'               => $base['title'],
            'description'         => $base['description'],
            'category'            => $base['category'],
            'access_level'        => $base['access_level'],
            'version_number'      => (int) $base['version_number'] + 1,
            'parent_document_id'  => (int) $baseId,
            'status'              => $data['status'] ?? 'ativo',
            'document_date'       => $base['document_date'],
            'valid_until'         => $base['valid_until'],
            'responsible_user_id' => $base['responsible_user_id'],
            'notes'               => $base['notes'],
        ], $data);

        return $this->insertWithFile($merged, $file);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateStatus(int|string $id, array $data): void
    {
        $fields = ['status' => (string) $data['status'], 'updated_at' => date('Y-m-d H:i:s')];
        $sets   = ['`status` = :status', '`updated_at` = :updated_at'];

        if (!empty($data['notes_append'])) {
            $sets[] = '`notes` = CONCAT(COALESCE(`notes`, \'\'), :notes_append)';
            $fields['notes_append'] = $data['notes_append'];
        }

        if (!empty($data['updated_by'])) {
            $sets[] = '`updated_by` = :updated_by';
            $fields['updated_by'] = $data['updated_by'];
        }

        $fields['id'] = $id;
        $this->query('UPDATE `documents` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $fields);
    }

    public function archive(int|string $id): void
    {
        $this->query(
            "UPDATE `documents`
                SET `archived_at` = NOW(), `status` = 'arquivado', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            "UPDATE `documents`
                SET `archived_at` = NULL, `status` = 'ativo', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    /**
     * @return array{path:string,original_name:string,mime_type:string,size_bytes:int}|null
     */
    public function downloadInfo(int|string $id): ?array
    {
        $doc = $this->findById($id);
        if ($doc === null) {
            return null;
        }

        $path = (string) ($doc['file_path'] ?? '');
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $baseReal = realpath(dirname(__DIR__, 2) . '/storage/uploads/documents');
        $fileReal = realpath($path);
        if ($baseReal === false || $fileReal === false || !str_starts_with($fileReal, $baseReal)) {
            return null;
        }

        return [
            'path'          => $fileReal,
            'original_name' => (string) ($doc['original_name'] ?? 'documento'),
            'mime_type'     => (string) ($doc['mime_type'] ?? 'application/octet-stream'),
            'size_bytes'    => (int) ($doc['size_bytes'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function findByFk(string $column, int|string $value, int $limit): array
    {
        if (!in_array($column, ['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id'], true)) {
            return [];
        }

        $limit = max(1, $limit);

        return $this->query(
            'SELECT d.`id`, d.`title`, d.`category`, d.`status`, d.`access_level`, d.`version_number`,
                    d.`valid_until`, d.`size_bytes`, d.`original_name`, d.`created_at`
               FROM `documents` d
              WHERE d.`' . $column . '` = :id AND d.`archived_at` IS NULL
              ORDER BY ' . self::ORDER_BY . '
              LIMIT ' . $limit,
            ['id' => $value]
        )->fetchAll();
    }

    private function countByFk(string $column, int|string $value): int
    {
        if (!in_array($column, ['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id'], true)) {
            return 0;
        }

        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `documents` WHERE `' . $column . '` = :id AND `archived_at` IS NULL',
            ['id' => $value]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /** @return array{total:int,active:int,expired:int,expiring_soon:int} */
    private function summaryByFk(string $column, int|string $value): array
    {
        if (!in_array($column, ['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id'], true)) {
            return ['total' => 0, 'active' => 0, 'expired' => 0, 'expiring_soon' => 0];
        }

        $active = implode(',', array_map(static fn ($s) => "'" . $s . "'", self::ACTIVE_STATUSES));
        $closed = implode(',', array_map(static fn ($s) => "'" . $s . "'", self::CLOSED_STATUSES));

        $row = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `status` IN ({$active}) THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN `valid_until` IS NOT NULL AND `valid_until` < CURDATE()
                              AND `status` NOT IN ({$closed}) THEN 1 ELSE 0 END) AS expired_count,
                    SUM(CASE WHEN `valid_until` IS NOT NULL
                              AND `valid_until` >= CURDATE()
                              AND `valid_until` <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                              AND `status` NOT IN ({$closed}) THEN 1 ELSE 0 END) AS expiring_count
               FROM `documents`
              WHERE `{$column}` = :id AND `archived_at` IS NULL",
            ['id' => $value]
        )->fetch();

        return [
            'total'         => (int) ($row['total'] ?? 0),
            'active'        => (int) ($row['active_count'] ?? 0),
            'expired'       => (int) ($row['expired_count'] ?? 0),
            'expiring_soon' => (int) ($row['expiring_count'] ?? 0),
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

        foreach (['document_date', 'valid_until'] as $d) {
            if (isset($row[$d])) {
                $row[$d] = $this->normalizeDate((string) $row[$d]);
            }
        }

        foreach (['company_id', 'contact_id', 'opportunity_id', 'quota_id', 'proposal_id', 'lead_id',
            'version_number', 'parent_document_id', 'responsible_user_id', 'created_by', 'updated_by', 'size_bytes'] as $intCol) {
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
