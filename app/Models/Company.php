<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Empresas / Prospects (Etapa 4).
 *
 * Sem exclusao fisica: empresas sao arquivadas (archived_at) e podem
 * ser restauradas. Todas as consultas usam prepared statements e
 * colunas explicitas (sem SELECT *).
 */
final class Company extends Model
{
    protected string $table = 'companies';

    /** Colunas preenchiveis via formulario (allowlist anti mass-assignment). */
    private const FILLABLE = [
        'name', 'trade_name', 'cnpj', 'segment', 'city', 'state',
        'website', 'linkedin', 'general_email', 'general_phone',
        'operates_para', 'operates_carajas', 'operates_parauapebas',
        'tax_regime_guess', 'has_cultural_sponsorship_history',
        'has_rouanet_history', 'has_esg_alignment',
        'priority', 'source', 'status', 'owner_user_id', 'notes',
    ];

    /** Colunas retornadas na listagem (sem SELECT *). */
    private const LIST_COLUMNS =
        'c.`id`, c.`name`, c.`trade_name`, c.`cnpj`, c.`segment`, c.`city`, c.`state`,
         c.`priority`, c.`status`, c.`owner_user_id`,
         c.`operates_para`, c.`operates_carajas`, c.`operates_parauapebas`,
         c.`created_at`, c.`updated_at`, c.`archived_at`,
         o.`name` AS owner_name';

    // -----------------------------------------------------------------
    // Listas controladas
    // -----------------------------------------------------------------

    /** @return array<int, string> */
    public function getSegments(): array
    {
        return [
            'mineração', 'energia', 'bancos', 'indústria', 'logística',
            'construção', 'educação', 'saúde', 'varejo', 'tecnologia',
            'telecomunicações', 'alimentos', 'serviços', 'instituições',
            'pessoa física', 'outros',
        ];
    }

    /** @return array<string, string> codigo => rotulo */
    public function getPriorities(): array
    {
        return [
            'A' => 'A — Alta prioridade',
            'B' => 'B — Média prioridade',
            'C' => 'C — Baixa prioridade',
            'D' => 'D — Manter monitoramento',
        ];
    }

    /** @return array<string, string> codigo => rotulo */
    public function getStatuses(): array
    {
        return [
            'prospect'        => 'Prospect identificado',
            'em_qualificacao' => 'Em qualificação',
            'prioritario'     => 'Prioritário',
            'monitoramento'   => 'Manter monitoramento',
            'sem_aderencia'   => 'Sem aderência no momento',
            'arquivado'       => 'Arquivado',
        ];
    }

    /** @return array<string, string> codigo => rotulo */
    public function getTaxRegimes(): array
    {
        return [
            'lucro_real_provavel'       => 'Lucro real (provável)',
            'lucro_presumido_provavel'  => 'Lucro presumido (provável)',
            'simples_nacional_provavel' => 'Simples Nacional (provável)',
            'desconhecido'              => 'Desconhecido',
            'nao_aplicavel'             => 'Não aplicável',
        ];
    }

    /** @return array<int, string> */
    public function getSources(): array
    {
        return [
            'pesquisa ativa', 'indicação interna', 'site', 'evento',
            'reunião', 'lista Radar Rouanet', 'relacionamento institucional',
            'outro',
        ];
    }

    /** @return array<int, string> Unidades federativas (UF). */
    public function getStates(): array
    {
        return [
            'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
            'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
            'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
        ];
    }

    /**
     * Lista enxuta de empresas para selects/filtros (id, name, archived_at).
     * Empresas ativas primeiro, depois arquivadas; ordenadas por nome.
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        return $this->query(
            'SELECT `id`, `name`, `archived_at`
               FROM `companies`
              ORDER BY (`archived_at` IS NOT NULL) ASC, `name` ASC'
        )->fetchAll();
    }

    // -----------------------------------------------------------------
    // CNPJ
    // -----------------------------------------------------------------

    /**
     * Normaliza o CNPJ deixando apenas digitos. String vazia => ''.
     */
    public function normalizeCnpj(?string $cnpj): string
    {
        return preg_replace('/\D+/', '', (string) $cnpj) ?? '';
    }

    /**
     * Indica se ja existe outra empresa com o mesmo CNPJ (normalizado).
     */
    public function existsCnpj(string $cnpj, int|string|null $ignoreId = null): bool
    {
        $cnpj = $this->normalizeCnpj($cnpj);
        if ($cnpj === '') {
            return false;
        }

        if ($ignoreId === null) {
            $row = $this->query(
                'SELECT `id` FROM `companies` WHERE `cnpj` = :cnpj LIMIT 1',
                ['cnpj' => $cnpj]
            )->fetch();
        } else {
            $row = $this->query(
                'SELECT `id` FROM `companies` WHERE `cnpj` = :cnpj AND `id` <> :id LIMIT 1',
                ['cnpj' => $cnpj, 'id' => $ignoreId]
            )->fetch();
        }

        return $row !== false;
    }

    // -----------------------------------------------------------------
    // Validacao
    // -----------------------------------------------------------------

    /**
     * Valida os dados de uma empresa.
     *
     * @param array<string, mixed> $data Dados ja normalizados (cnpj em digitos).
     * @return array<string, string> Mapa campo => mensagem (vazio = ok).
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome da empresa.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter no mínimo 2 caracteres.';
        }

        $cnpj = $this->normalizeCnpj((string) ($data['cnpj'] ?? ''));
        if ($cnpj !== '' && strlen($cnpj) !== 14) {
            $errors['cnpj'] = 'CNPJ inválido: deve conter 14 dígitos.';
        }

        $email = trim((string) ($data['general_email'] ?? ''));
        if ($email !== '' && !is_email($email)) {
            $errors['general_email'] = 'E-mail geral inválido.';
        }

        $state = trim((string) ($data['state'] ?? ''));
        if ($state !== '' && !preg_match('/^[A-Za-z]{2}$/', $state)) {
            $errors['state'] = 'UF deve ter 2 letras.';
        }

        $priority = (string) ($data['priority'] ?? '');
        if (!array_key_exists($priority, $this->getPriorities())) {
            $errors['priority'] = 'Prioridade inválida.';
        }

        $status = (string) ($data['status'] ?? '');
        if (!array_key_exists($status, $this->getStatuses())) {
            $errors['status'] = 'Status inválido.';
        }

        $segment = trim((string) ($data['segment'] ?? ''));
        if ($segment !== '' && !in_array($segment, $this->getSegments(), true)) {
            $errors['segment'] = 'Segmento inválido.';
        }

        $regime = trim((string) ($data['tax_regime_guess'] ?? ''));
        if ($regime !== '' && !array_key_exists($regime, $this->getTaxRegimes())) {
            $errors['tax_regime_guess'] = 'Regime tributário inválido.';
        }

        $source = trim((string) ($data['source'] ?? ''));
        if ($source !== '' && !in_array($source, $this->getSources(), true)) {
            $errors['source'] = 'Origem da indicação inválida.';
        }

        return $errors;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /**
     * Lista paginada de empresas com filtros.
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
                  FROM `companies` c
                  LEFT JOIN `users` o ON o.`id` = c.`owner_user_id`'
            . $where .
            ' ORDER BY c.`priority` ASC, COALESCE(c.`updated_at`, c.`created_at`) DESC, c.`name` ASC
              LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Conta o total de empresas para os filtros informados.
     *
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `companies` c' . $where,
            $params
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Busca uma empresa por ID com nomes de responsavel/autor.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT c.`id`, c.`name`, c.`trade_name`, c.`cnpj`, c.`segment`,
                    c.`city`, c.`state`, c.`website`, c.`linkedin`,
                    c.`general_email`, c.`general_phone`,
                    c.`operates_para`, c.`operates_carajas`, c.`operates_parauapebas`,
                    c.`tax_regime_guess`, c.`has_cultural_sponsorship_history`,
                    c.`has_rouanet_history`, c.`has_esg_alignment`,
                    c.`priority`, c.`source`, c.`status`, c.`owner_user_id`, c.`notes`,
                    c.`created_by`, c.`updated_by`, c.`created_at`, c.`updated_at`, c.`archived_at`,
                    o.`name`  AS owner_name,
                    cb.`name` AS created_by_name,
                    ub.`name` AS updated_by_name
               FROM `companies` c
               LEFT JOIN `users` o  ON o.`id`  = c.`owner_user_id`
               LEFT JOIN `users` cb ON cb.`id` = c.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = c.`updated_by`
              WHERE c.`id` = :id
              LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    // -----------------------------------------------------------------
    // Escrita (sem exclusao fisica)
    // -----------------------------------------------------------------

    /**
     * Cria uma empresa e retorna o ID gerado.
     *
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
            'INSERT INTO `companies` (%s) VALUES (%s)',
            implode(', ', $escaped),
            implode(', ', $placeholders)
        );

        $this->query($sql, $payload);

        return $this->db->lastInsertId();
    }

    /**
     * Atualiza uma empresa (define updated_at/updated_by).
     *
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
            'UPDATE `companies` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    /**
     * Arquiva uma empresa (logico): preenche archived_at e marca status.
     */
    public function archive(int|string $id): void
    {
        $this->query(
            "UPDATE `companies`
                SET `archived_at` = NOW(), `status` = 'arquivado', `updated_at` = NOW()
              WHERE `id` = :id",
            ['id' => $id]
        );
    }

    /**
     * Restaura uma empresa arquivada: limpa archived_at e define status.
     */
    public function restore(int|string $id, string $status = 'prospect'): void
    {
        if (!array_key_exists($status, $this->getStatuses()) || $status === 'arquivado') {
            $status = 'prospect';
        }

        $this->query(
            'UPDATE `companies`
                SET `archived_at` = NULL, `status` = :status, `updated_at` = NOW()
              WHERE `id` = :id',
            ['status' => $status, 'id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * Monta o WHERE dinamico a partir dos filtros (com binds nomeados).
     *
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params     = [];

        // Arquivadas ocultas por padrao.
        if (empty($filters['show_archived'])) {
            $conditions[] = 'c.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Placeholders distintos: a conexao usa prepares reais (sem emulacao),
            // que nao permitem reutilizar o mesmo nome de placeholder.
            $conditions[]  = '(c.`name` LIKE :qname OR c.`trade_name` LIKE :qtrade OR c.`cnpj` LIKE :qcnpj)';
            $params['qname']  = '%' . $q . '%';
            $params['qtrade'] = '%' . $q . '%';
            $params['qcnpj']  = '%' . (preg_replace('/\D+/', '', $q) ?? '') . '%';
        }

        $simpleEq = [
            'segment'  => 'segment',
            'priority' => 'priority',
            'status'   => 'status',
            'state'    => 'state',
        ];
        foreach ($simpleEq as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $conditions[]     = "c.`{$column}` = :{$key}";
                $params[$key]     = $value;
            }
        }

        $owner = (int) ($filters['owner'] ?? 0);
        if ($owner > 0) {
            $conditions[]     = 'c.`owner_user_id` = :owner';
            $params['owner']  = $owner;
        }

        foreach (['operates_para', 'operates_carajas', 'operates_parauapebas'] as $flag) {
            if (!empty($filters[$flag])) {
                $conditions[] = "c.`{$flag}` = 1";
            }
        }

        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * Filtra somente colunas preenchiveis e normaliza tipos.
     *
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
