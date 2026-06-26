<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Leads do Site (Etapa 9).
 */
final class Lead extends Model
{
    protected string $table = 'leads';

    private const FILLABLE = [
        'name', 'company_name', 'role_title', 'email', 'whatsapp',
        'city', 'state', 'segment', 'origin_page', 'source_url',
        'form_id', 'form_name', 'interest', 'message', 'contact_consent',
        'ip_address', 'user_agent', 'referrer',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
        'status', 'assigned_user_id',
        'company_id', 'contact_id', 'opportunity_id', 'task_id',
        'integration_payload',
    ];

    private const LIST_COLUMNS =
        'l.`id`, l.`name`, l.`company_name`, l.`email`, l.`whatsapp`, l.`interest`,
         l.`origin_page`, l.`status`, l.`contact_consent`, l.`created_at`, l.`archived_at`,
         l.`company_id`, l.`contact_id`, l.`opportunity_id`, l.`task_id`,
         au.`name` AS assigned_name';

    /** Aliases de campos vindos do WordPress / formulários HTML. */
    private const FIELD_ALIASES = [
        'name'            => ['name', 'nome', 'your-name', 'full_name', 'responsavel'],
        'company_name'    => ['company_name', 'empresa', 'company', 'nome_empresa'],
        'role_title'      => ['role_title', 'cargo', 'role', 'position'],
        'email'           => ['email', 'your-email', 'e-mail'],
        'whatsapp'        => ['whatsapp', 'telefone', 'phone', 'celular'],
        'city'            => ['city', 'cidade'],
        'state'           => ['state', 'estado', 'uf'],
        'segment'         => ['segment', 'segmento'],
        'interest'        => ['interest', 'interesse', 'assunto', 'subject', 'cota', 'objetivo', 'perfil', 'faixa'],
        'message'         => ['message', 'mensagem', 'observacoes', 'observacao', 'valor_estimado', 'regime'],
        'origin_page'     => ['origin_page', 'origem'],
        'source_url'      => ['source_url'],
        'form_id'         => ['form_id'],
        'form_name'       => ['form_name'],
        'contact_consent' => ['contact_consent', 'consent', 'consentimento', 'lgpd', 'aceite', 'autorizacao', 'autorizacao_contato', 'aceite_contato', 'aceite_privacidade'],
        'utm_source'      => ['utm_source'],
        'utm_medium'      => ['utm_medium'],
        'utm_campaign'    => ['utm_campaign'],
        'utm_content'     => ['utm_content'],
        'utm_term'        => ['utm_term'],
        'referrer'        => ['referrer'],
    ];

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'novo'                  => 'Novo',
            'em_triagem'            => 'Em triagem',
            'convertido_empresa'    => 'Convertido em empresa',
            'convertido_contato'    => 'Convertido em contato',
            'convertido_oportunidade' => 'Convertido em oportunidade',
            'convertido_tarefa'     => 'Convertido em tarefa',
            'convertido_completo'   => 'Convertido completo',
            'duplicado'             => 'Duplicado',
            'descartado'            => 'Descartado',
            'respondido'            => 'Respondido',
            'aguardando_retorno'    => 'Aguardando retorno',
            'arquivado'             => 'Arquivado',
        ];
    }

    /**
     * Normaliza payload bruto (JSON ou form) para campos do lead.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function mapIncoming(array $raw): array
    {
        $flat = [];
        foreach ($raw as $k => $v) {
            if (is_string($k)) {
                $flat[strtolower(trim($k))] = $v;
            }
        }

        $mapped = [];
        foreach (self::FIELD_ALIASES as $target => $aliases) {
            if ($target === 'contact_consent') {
                $mapped[$target] = $this->normalizeConsentFromFlat($flat, $aliases);
                continue;
            }
            foreach ($aliases as $alias) {
                $key = strtolower($alias);
                if (array_key_exists($key, $flat) && $flat[$key] !== '' && $flat[$key] !== null) {
                    $mapped[$target] = $flat[$key];
                    break;
                }
            }
        }

        // cidade_uf combinado "Parauapebas/PA"
        if (empty($mapped['city']) && !empty($flat['cidade_uf'])) {
            $parts = preg_split('/[\/,-]/', (string) $flat['cidade_uf'], 2);
            $mapped['city'] = trim($parts[0] ?? '');
            if (empty($mapped['state']) && isset($parts[1])) {
                $mapped['state'] = strtoupper(substr(trim($parts[1]), 0, 2));
            }
        }

        if (empty($mapped['name']) && !empty($mapped['company_name'])) {
            $mapped['name'] = (string) $mapped['company_name'];
        }

        $mapped['contact_consent'] = $this->normalizeConsent($mapped['contact_consent'] ?? null);
        $mapped['whatsapp'] = $this->normalizeWhatsapp((string) ($mapped['whatsapp'] ?? ''));
        if (!empty($mapped['email'])) {
            $mapped['email'] = strtolower(trim((string) $mapped['email']));
        }
        if (!empty($mapped['state'])) {
            $mapped['state'] = strtoupper(substr((string) $mapped['state'], 0, 2));
        }

        return $mapped;
    }

    public function normalizeWhatsapp(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            return $value !== '' ? $value : null;
        }

        return $digits;
    }

    private function normalizeConsent(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'true', 'sim', 'yes', 'on'], true) ? 1 : 0;
    }

    /**
     * @param array<string, mixed> $flat
     * @param array<int, string> $aliases
     */
    private function normalizeConsentFromFlat(array $flat, array $aliases): int
    {
        foreach ($aliases as $alias) {
            $key = strtolower($alias);
            if (!array_key_exists($key, $flat)) {
                continue;
            }
            if ($this->normalizeConsent($flat[$key]) === 1) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome do lead.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter ao menos 2 caracteres.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        $status = (string) ($data['status'] ?? 'novo');
        if (!array_key_exists($status, $this->getStatuses())) {
            $errors['status'] = 'Status inválido.';
        }

        return $errors;
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

        $sql = 'SELECT ' . self::LIST_COLUMNS . '
                  FROM `leads` l
                  LEFT JOIN `users` au ON au.`id` = l.`assigned_user_id`' .
            $where .
            ' ORDER BY (l.`status` = \'novo\') DESC, l.`created_at` DESC
              LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->query('SELECT COUNT(*) AS c FROM `leads` l' . $where, $params)->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT l.`id`, l.`name`, l.`company_name`, l.`role_title`, l.`email`, l.`whatsapp`,
                    l.`city`, l.`state`, l.`segment`, l.`origin_page`, l.`source_url`,
                    l.`form_id`, l.`form_name`, l.`interest`, l.`message`, l.`contact_consent`,
                    l.`ip_address`, l.`user_agent`, l.`referrer`,
                    l.`utm_source`, l.`utm_medium`, l.`utm_campaign`, l.`utm_content`, l.`utm_term`,
                    l.`status`, l.`assigned_user_id`, l.`company_id`, l.`contact_id`,
                    l.`opportunity_id`, l.`task_id`, l.`integration_payload`,
                    l.`converted_at`, l.`converted_by`, l.`created_by`, l.`updated_by`,
                    l.`created_at`, l.`updated_at`, l.`archived_at`,
                    au.`name` AS assigned_name,
                    cb.`name` AS created_by_name, ub.`name` AS updated_by_name,
                    cv.`name` AS converted_by_name,
                    co.`name` AS linked_company_name, ct.`name` AS linked_contact_name,
                    op.`title` AS linked_opportunity_title, tk.`title` AS linked_task_title
               FROM `leads` l
               LEFT JOIN `users` au ON au.`id` = l.`assigned_user_id`
               LEFT JOIN `users` cb ON cb.`id` = l.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = l.`updated_by`
               LEFT JOIN `users` cv ON cv.`id` = l.`converted_by`
               LEFT JOIN `companies` co ON co.`id` = l.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = l.`contact_id`
               LEFT JOIN `opportunities` op ON op.`id` = l.`opportunity_id`
               LEFT JOIN `tasks` tk ON tk.`id` = l.`task_id`
              WHERE l.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    public function countByStatus(string $status): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `leads` WHERE `archived_at` IS NULL AND `status` = :st',
            ['st' => $status]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);
        foreach (['created_by', 'converted_at', 'converted_by'] as $extra) {
            if (array_key_exists($extra, $data)) {
                $payload[$extra] = $data[$extra];
            }
        }

        if (isset($payload['integration_payload']) && is_array($payload['integration_payload'])) {
            $payload['integration_payload'] = json_encode(
                $payload['integration_payload'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $columns      = array_keys($payload);
        $escaped      = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
        $escaped[] = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `leads` (%s) VALUES (%s)',
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
        foreach (['updated_by', 'converted_at', 'converted_by'] as $extra) {
            if (array_key_exists($extra, $data)) {
                $payload[$extra] = $data[$extra];
            }
        }
        if ($payload === []) {
            return;
        }

        if (isset($payload['integration_payload']) && is_array($payload['integration_payload'])) {
            $payload['integration_payload'] = json_encode(
                $payload['integration_payload'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        $sets = [];
        foreach (array_keys($payload) as $c) {
            $sets[] = '`' . $c . '` = :' . $c;
        }
        $sets[] = '`updated_at` = NOW()';
        $payload['id'] = $id;

        $this->query('UPDATE `leads` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $payload);
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `leads` SET `archived_at` = NOW(), `status` = \'arquivado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `leads` SET `archived_at` = NULL, `status` = \'novo\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function markDuplicate(int|string $id): void
    {
        $this->query(
            'UPDATE `leads` SET `status` = \'duplicado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function discard(int|string $id): void
    {
        $this->query(
            'UPDATE `leads` SET `status` = \'descartado\', `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, string> */
    public function originOptions(): array
    {
        $rows = $this->query(
            'SELECT DISTINCT `origin_page` FROM `leads`
              WHERE `origin_page` IS NOT NULL AND `origin_page` != \'\'
              ORDER BY `origin_page` ASC LIMIT 100'
        )->fetchAll();

        return array_values(array_filter(array_map(
            static fn ($r) => (string) ($r['origin_page'] ?? ''),
            $rows
        )));
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
            $conditions[] = 'l.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(l.`name` LIKE :q OR l.`company_name` LIKE :q OR l.`email` LIKE :q OR l.`whatsapp` LIKE :q OR l.`message` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        foreach (['status', 'origin_page', 'interest'] as $f) {
            $v = trim((string) ($filters[$f] ?? ''));
            if ($v !== '') {
                $conditions[] = 'l.`' . $f . '` = :' . $f;
                $params[$f] = $v;
            }
        }

        $owner = (int) ($filters['assigned_user_id'] ?? 0);
        if ($owner > 0) {
            $conditions[] = 'l.`assigned_user_id` = :owner';
            $params['owner'] = $owner;
        }

        if (isset($filters['contact_consent']) && $filters['contact_consent'] !== '') {
            $conditions[] = 'l.`contact_consent` = :consent';
            $params['consent'] = (int) $filters['contact_consent'];
        }

        if (!empty($filters['converted'])) {
            $conditions[] = 'l.`company_id` IS NOT NULL OR l.`contact_id` IS NOT NULL OR l.`opportunity_id` IS NOT NULL OR l.`task_id` IS NOT NULL';
        }
        if (!empty($filters['not_converted'])) {
            $conditions[] = 'l.`company_id` IS NULL AND l.`contact_id` IS NULL AND l.`opportunity_id` IS NULL AND l.`task_id` IS NULL';
        }

        $from = trim((string) ($filters['date_from'] ?? ''));
        $to   = trim((string) ($filters['date_to'] ?? ''));
        if ($from !== '') {
            $conditions[] = 'DATE(l.`created_at`) >= :df';
            $params['df'] = $from;
        }
        if ($to !== '') {
            $conditions[] = 'DATE(l.`created_at`) <= :dt';
            $params['dt'] = $to;
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
