<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Contatos (Etapa 5) — pessoas vinculadas a empresas.
 *
 * Todo contato pertence obrigatoriamente a uma empresa (company_id).
 * Sem exclusao fisica: arquivamento logico via archived_at.
 * Consultas com prepared statements e colunas explicitas (sem SELECT *).
 */
final class Contact extends Model
{
    protected string $table = 'contacts';

    /** Colunas preenchiveis via formulario (allowlist anti mass-assignment). */
    private const FILLABLE = [
        'company_id', 'name', 'position_title', 'department',
        'email', 'whatsapp', 'phone', 'linkedin',
        'decision_level', 'influence_level', 'preferred_channel',
        'last_interaction_at', 'next_contact_at',
        'status', 'notes', 'owner_user_id',
    ];

    /** Colunas da listagem (sem SELECT *). */
    private const LIST_COLUMNS =
        'ct.`id`, ct.`company_id`, ct.`name`, ct.`position_title`, ct.`department`,
         ct.`email`, ct.`whatsapp`, ct.`phone`,
         ct.`decision_level`, ct.`influence_level`, ct.`preferred_channel`,
         ct.`status`, ct.`next_contact_at`, ct.`last_interaction_at`,
         ct.`updated_at`, ct.`created_at`, ct.`archived_at`,
         co.`name` AS company_name, co.`archived_at` AS company_archived_at,
         ow.`name` AS owner_name';

    // -----------------------------------------------------------------
    // Listas controladas
    // -----------------------------------------------------------------

    /** @return array<int, string> */
    public function getDepartments(): array
    {
        return [
            'marketing', 'comunicação', 'ESG', 'sustentabilidade',
            'relações institucionais', 'responsabilidade social',
            'diretoria', 'presidência', 'financeiro', 'jurídico',
            'contabilidade', 'recursos humanos', 'instituto/fundação', 'outro',
        ];
    }

    /** @return array<string, string> */
    public function getDecisionLevels(): array
    {
        return [
            'decisor_final'  => 'Decisor final',
            'influenciador'  => 'Influenciador',
            'tecnico'        => 'Técnico / Analista',
            'operacional'    => 'Operacional',
            'intermediario'  => 'Intermediário',
            'nao_informado'  => 'Não informado',
        ];
    }

    /** @return array<string, string> */
    public function getInfluenceLevels(): array
    {
        return [
            'alta'        => 'Alta',
            'media'       => 'Média',
            'baixa'       => 'Baixa',
            'desconhecida'=> 'Desconhecida',
        ];
    }

    /** @return array<string, string> */
    public function getPreferredChannels(): array
    {
        return [
            'whatsapp'      => 'WhatsApp',
            'email'         => 'E-mail',
            'telefone'      => 'Telefone',
            'linkedin'      => 'LinkedIn',
            'reuniao'       => 'Reunião',
            'outro'         => 'Outro',
            'nao_informado' => 'Não informado',
        ];
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'ativo'              => 'Ativo',
            'em_aproximacao'     => 'Em aproximação',
            'respondeu'          => 'Respondeu',
            'aguardando_retorno' => 'Aguardando retorno',
            'sem_retorno'        => 'Sem retorno',
            'substituido'        => 'Substituído',
            'inativo'            => 'Inativo',
            'arquivado'          => 'Arquivado',
        ];
    }

    // -----------------------------------------------------------------
    // Normalizacao / validacao
    // -----------------------------------------------------------------

    /**
     * Normaliza o WhatsApp: mantem apenas digitos (preserva DDI/DDD).
     */
    public function normalizeWhatsapp(?string $whatsapp): string
    {
        return preg_replace('/\D+/', '', (string) $whatsapp) ?? '';
    }

    /**
     * Valida os dados de um contato.
     *
     * @param array<string, mixed> $data Dados ja normalizados.
     * @return array<string, string> Mapa campo => mensagem (vazio = ok).
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        if ((int) ($data['company_id'] ?? 0) <= 0) {
            $errors['company_id'] = 'Selecione a empresa vinculada.';
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome do contato.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter no mínimo 2 caracteres.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !is_email($email)) {
            $errors['email'] = 'E-mail inválido.';
        }

        $linkedin = trim((string) ($data['linkedin'] ?? ''));
        if ($linkedin !== '' && !preg_match('#^(https?://)?(www\.)?linkedin\.com/.+#i', $linkedin)) {
            $errors['linkedin'] = 'LinkedIn inválido: use uma URL do linkedin.com (ex.: linkedin.com/in/usuario).';
        }

        $maps = [
            'decision_level'    => $this->getDecisionLevels(),
            'influence_level'   => $this->getInfluenceLevels(),
            'preferred_channel' => $this->getPreferredChannels(),
            'status'            => $this->getStatuses(),
        ];
        $labels = [
            'decision_level'    => 'Nível de decisão',
            'influence_level'   => 'Influência',
            'preferred_channel' => 'Canal preferencial',
            'status'            => 'Status',
        ];
        foreach ($maps as $field => $allowed) {
            $value = (string) ($data[$field] ?? '');
            if (!array_key_exists($value, $allowed)) {
                $errors[$field] = $labels[$field] . ' inválido.';
            }
        }

        $department = trim((string) ($data['department'] ?? ''));
        if ($department !== '' && !in_array($department, $this->getDepartments(), true)) {
            $errors['department'] = 'Área inválida.';
        }

        foreach (['last_interaction_at', 'next_contact_at'] as $dt) {
            $value = trim((string) ($data[$dt] ?? ''));
            if ($value !== '' && strtotime($value) === false) {
                $errors[$dt] = 'Data/hora inválida.';
            }
        }

        return $errors;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /**
     * Lista paginada de contatos com filtros.
     *
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
                  FROM `contacts` ct
                  JOIN `companies` co ON co.`id` = ct.`company_id`
                  LEFT JOIN `users` ow ON ow.`id` = ct.`owner_user_id`'
            . $where .
            " ORDER BY (ct.`next_contact_at` IS NOT NULL AND ct.`next_contact_at` < NOW()) DESC,
                       FIELD(ct.`decision_level`,'decisor_final','influenciador','intermediario','tecnico','operacional','nao_informado'),
                       FIELD(ct.`influence_level`,'alta','media','baixa','desconhecida'),
                       COALESCE(ct.`updated_at`, ct.`created_at`) DESC,
                       ct.`name` ASC
              LIMIT " . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Conta contatos para os filtros informados.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query(
            'SELECT COUNT(*) AS c
               FROM `contacts` ct
               JOIN `companies` co ON co.`id` = ct.`company_id`
               LEFT JOIN `users` ow ON ow.`id` = ct.`owner_user_id`' . $where,
            $params
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Busca um contato por ID com nomes de empresa/responsavel/autor.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ct.`id`, ct.`company_id`, ct.`name`, ct.`position_title`, ct.`department`,
                    ct.`email`, ct.`whatsapp`, ct.`phone`, ct.`linkedin`,
                    ct.`decision_level`, ct.`influence_level`, ct.`preferred_channel`,
                    ct.`last_interaction_at`, ct.`next_contact_at`,
                    ct.`status`, ct.`notes`, ct.`owner_user_id`,
                    ct.`created_by`, ct.`updated_by`, ct.`created_at`, ct.`updated_at`, ct.`archived_at`,
                    co.`name` AS company_name, co.`archived_at` AS company_archived_at,
                    ow.`name` AS owner_name,
                    cb.`name` AS created_by_name,
                    ub.`name` AS updated_by_name
               FROM `contacts` ct
               JOIN `companies` co ON co.`id` = ct.`company_id`
               LEFT JOIN `users` ow ON ow.`id` = ct.`owner_user_id`
               LEFT JOIN `users` cb ON cb.`id` = ct.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = ct.`updated_by`
              WHERE ct.`id` = :id
              LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lista resumida de contatos ativos (nao arquivados) de uma empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCompany(int|string $companyId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT ct.`id`, ct.`name`, ct.`position_title`, ct.`department`,
                    ct.`email`, ct.`whatsapp`, ct.`status`
               FROM `contacts` ct
              WHERE ct.`company_id` = :id AND ct.`archived_at` IS NULL
              ORDER BY ct.`name` ASC
              LIMIT ' . $limit,
            ['id' => $companyId]
        )->fetchAll();
    }

    /**
     * Contatos visiveis no Portal do Captador: apenas os criados/pertencentes
     * ao proprio usuario do captador (nunca contatos internos da empresa).
     *
     * @return list<array<string,mixed>>
     */
    public function findByCompanyForPortal(int|string $companyId, int|string $userId, int $limit = 50): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT ct.`id`, ct.`name`, ct.`position_title`, ct.`department`,
                    ct.`email`, ct.`whatsapp`, ct.`status`
               FROM `contacts` ct
              WHERE ct.`company_id` = :id AND ct.`archived_at` IS NULL
                AND (ct.`owner_user_id` = :uid OR ct.`created_by` = :uid2)
              ORDER BY ct.`name` ASC
              LIMIT ' . $limit,
            ['id' => $companyId, 'uid' => $userId, 'uid2' => $userId]
        )->fetchAll();
    }

    /**
     * Conta contatos ativos (nao arquivados) de uma empresa.
     */
    public function countByCompany(int|string $companyId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `contacts`
              WHERE `company_id` = :id AND `archived_at` IS NULL',
            ['id' => $companyId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    // -----------------------------------------------------------------
    // Escrita (sem exclusao fisica)
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);

        $columns      = array_keys($payload);
        $escaped      = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $escaped[]      = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `contacts` (%s) VALUES (%s)',
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
        if ($payload === []) {
            return;
        }

        $sets = [];
        foreach (array_keys($payload) as $c) {
            $sets[] = '`' . $c . '` = :' . $c;
        }
        $sets[] = '`updated_at` = NOW()';

        $payload['id'] = $id;

        $this->query(
            'UPDATE `contacts` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            "UPDATE `contacts`
                SET `archived_at` = NOW(), `status` = 'arquivado', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    public function restore(int|string $id, string $status = 'ativo'): void
    {
        if (!array_key_exists($status, $this->getStatuses()) || $status === 'arquivado') {
            $status = 'ativo';
        }

        $this->query(
            'UPDATE `contacts`
                SET `archived_at` = NULL, `status` = :status, `updated_at` = NOW()
              WHERE `id` = :id',
            ['status' => $status, 'id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'ct.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[]      = '(ct.`name` LIKE :qn OR co.`name` LIKE :qc OR ct.`email` LIKE :qe OR ct.`whatsapp` LIKE :qw OR ct.`position_title` LIKE :qp)';
            $params['qn']      = '%' . $q . '%';
            $params['qc']      = '%' . $q . '%';
            $params['qe']      = '%' . $q . '%';
            $params['qw']      = '%' . (preg_replace('/\D+/', '', $q) ?? '') . '%';
            $params['qp']      = '%' . $q . '%';
        }

        $companyId = (int) ($filters['company_id'] ?? 0);
        if ($companyId > 0) {
            $conditions[]          = 'ct.`company_id` = :company_id';
            $params['company_id']  = $companyId;
        }

        $simpleEq = [
            'department'        => 'department',
            'decision_level'    => 'decision_level',
            'influence_level'   => 'influence_level',
            'preferred_channel' => 'preferred_channel',
            'status'            => 'status',
        ];
        foreach ($simpleEq as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $conditions[] = "ct.`{$column}` = :{$key}";
                $params[$key] = $value;
            }
        }

        $owner = (int) ($filters['owner'] ?? 0);
        if ($owner > 0) {
            $conditions[]    = 'ct.`owner_user_id` = :owner';
            $params['owner'] = $owner;
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = '(ct.`next_contact_at` IS NOT NULL AND ct.`next_contact_at` < NOW())';
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
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $payload[$column] = $data[$column];
        }

        return $payload;
    }
}
