<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Model de Oportunidades / CRM de Captação (Etapa 6).
 *
 * Núcleo do funil comercial: liga empresa + contato + status + valor +
 * probabilidade + próxima ação. Sem exclusão física (archived_at).
 * Consultas com prepared statements e colunas explícitas (sem SELECT *).
 *
 * Observação: nesta etapa não há tabela de cotas. O interesse de cota é
 * registrado em `quota_interest` (texto controlado provisório).
 */
final class Opportunity extends Model
{
    protected string $table = 'opportunities';

    /** Colunas preenchíveis via formulário (allowlist anti mass-assignment). */
    private const FILLABLE = [
        'company_id', 'contact_id', 'title', 'quota_interest',
        'quota_id', 'quota_reserved_until',
        'estimated_value', 'probability', 'status', 'source',
        'owner_user_id', 'opened_at', 'last_interaction_at', 'next_action_at',
        'urgency_level', 'lost_reason', 'notes',
    ];

    /** Status do funil em ordem (slug => rótulo). */
    private const STATUS_LABELS = [
        'prospect_identificado'          => 'Prospect identificado',
        'contato_localizado'             => 'Contato localizado',
        'primeiro_contato_enviado'       => 'Primeiro contato enviado',
        'respondeu'                      => 'Respondeu',
        'one_page_enviado'               => 'One-page enviado',
        'reuniao_solicitada'             => 'Reunião solicitada',
        'reuniao_agendada'               => 'Reunião agendada',
        'reuniao_realizada'              => 'Reunião realizada',
        'deck_enviado'                   => 'Deck enviado',
        'em_analise_interna'             => 'Em análise interna',
        'aguardando_dados_oficiais'      => 'Aguardando dados oficiais',
        'proposta_personalizada_enviada' => 'Proposta personalizada enviada',
        'negociacao'                     => 'Negociação',
        'reserva_de_cota'                => 'Reserva de cota',
        'fechado'                        => 'Fechado',
        'perdido'                        => 'Perdido',
        'retomar_depois'                 => 'Retomar depois',
    ];

    /** Probabilidade sugerida por status (%). */
    private const STATUS_PROBABILITIES = [
        'prospect_identificado'          => 5,
        'contato_localizado'             => 10,
        'primeiro_contato_enviado'       => 15,
        'respondeu'                      => 25,
        'one_page_enviado'               => 30,
        'reuniao_solicitada'             => 35,
        'reuniao_agendada'               => 40,
        'reuniao_realizada'              => 50,
        'deck_enviado'                   => 60,
        'em_analise_interna'             => 70,
        'aguardando_dados_oficiais'      => 70,
        'proposta_personalizada_enviada' => 75,
        'negociacao'                     => 85,
        'reserva_de_cota'                => 90,
        'fechado'                        => 100,
        'perdido'                        => 0,
        'retomar_depois'                 => 10,
    ];

    /** Colunas da listagem (sem SELECT *). */
    private const LIST_COLUMNS =
        'o.`id`, o.`company_id`, o.`contact_id`, o.`title`, o.`quota_interest`,
         o.`quota_id`, o.`quota_reserved_until`,
         o.`estimated_value`, o.`probability`, o.`status`, o.`source`,
         o.`owner_user_id`, o.`opened_at`, o.`last_interaction_at`, o.`next_action_at`,
         o.`urgency_level`, o.`lost_reason`, o.`updated_at`, o.`created_at`, o.`archived_at`,
         co.`name` AS company_name, co.`archived_at` AS company_archived_at,
         ct.`name` AS contact_name,
         ow.`name` AS owner_name,
         q.`name` AS quota_name, q.`commercial_name` AS quota_commercial_name';

    // -----------------------------------------------------------------
    // Listas controladas
    // -----------------------------------------------------------------

    /** @return array<int, string> slugs em ordem de funil. */
    public function getStatuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    /** @return array<string, string> */
    public function getStatusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /** @return array<string, int> */
    public function getStatusProbabilities(): array
    {
        return self::STATUS_PROBABILITIES;
    }

    /** Status considerados "abertos" (nem fechado nem perdido). */
    public function getOpenStatuses(): array
    {
        return array_values(array_diff($this->getStatuses(), ['fechado', 'perdido']));
    }

    /** @return array<int, string> */
    public function getQuotaInterests(): array
    {
        return [
            'Cota Apresenta — R$ 200.000,00',
            'Cota Carajás — R$ 100.000,00',
            'Cota Movimento — R$ 50.000,00',
            'Cota Formação — R$ 25.000,00',
            'Cota Incentivador — R$ 10.448,00',
            'Círculo Dança Carajás — valor flexível',
            'A definir',
            'Não se aplica',
        ];
    }

    /** @return array<int, string> */
    public function getSources(): array
    {
        return [
            'pesquisa ativa', 'indicação interna', 'captador', 'site', 'evento', 'reunião',
            'lista Radar Rouanet', 'relacionamento institucional',
            'retorno espontâneo', 'outro',
        ];
    }

    /** @return array<string, string> */
    public function getUrgencyLevels(): array
    {
        return [
            'baixa'   => 'Baixa',
            'normal'  => 'Normal',
            'alta'    => 'Alta',
            'critica' => 'Crítica',
        ];
    }

    /** @return array<int, string> */
    public function getLostReasons(): array
    {
        return [
            'sem orçamento', 'sem aderência', 'não trabalha com Lei Rouanet',
            'sem retorno', 'prazo incompatível', 'cota indisponível futuramente',
            'conflito institucional', 'decidiu não apoiar', 'retomar em outro ciclo',
            'outro',
        ];
    }

    /** Probabilidade sugerida para um status (default 5). */
    public function suggestedProbability(string $status): int
    {
        return self::STATUS_PROBABILITIES[$status] ?? 5;
    }

    // -----------------------------------------------------------------
    // Normalização / validação
    // -----------------------------------------------------------------

    /**
     * Converte valor monetário (pt-BR ou ponto decimal) em float.
     * Retorna null quando vazio; string original quando inconversível
     * (para a validação sinalizar erro).
     */
    public function normalizeMoney(?string $value): float|string|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $clean = preg_replace('/[^\d,.\-]/', '', $value) ?? '';
        if ($clean === '' || $clean === '-') {
            return $value;
        }

        if (str_contains($clean, ',')) {
            // pt-BR: ponto como milhar, vírgula como decimal
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        if (!is_numeric($clean)) {
            return $value;
        }

        return (float) $clean;
    }

    /**
     * Valida os dados de uma oportunidade (já normalizados).
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Mapa campo => mensagem (vazio = ok).
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];

        if ((int) ($data['company_id'] ?? 0) <= 0) {
            $errors['company_id'] = 'Selecione a empresa vinculada.';
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = 'Informe o título da oportunidade.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'O título deve ter no mínimo 3 caracteres.';
        }

        $value = $data['estimated_value'] ?? null;
        if (is_string($value)) {
            $errors['estimated_value'] = 'Valor estimado inválido.';
        } elseif ($value !== null && (float) $value < 0) {
            $errors['estimated_value'] = 'O valor estimado deve ser positivo.';
        }

        $prob = $data['probability'] ?? null;
        if (!is_numeric($prob) || (int) $prob < 0 || (int) $prob > 100) {
            $errors['probability'] = 'A probabilidade deve ser um inteiro entre 0 e 100.';
        }

        $status = (string) ($data['status'] ?? '');
        if (!array_key_exists($status, self::STATUS_LABELS)) {
            $errors['status'] = 'Status do funil inválido.';
        }

        $urgency = (string) ($data['urgency_level'] ?? '');
        if ($urgency !== '' && !array_key_exists($urgency, $this->getUrgencyLevels())) {
            $errors['urgency_level'] = 'Nível de urgência inválido.';
        }

        $source = trim((string) ($data['source'] ?? ''));
        if ($source !== '' && !in_array($source, $this->getSources(), true)) {
            $errors['source'] = 'Origem inválida.';
        }

        $quota = trim((string) ($data['quota_interest'] ?? ''));
        if ($quota !== '' && !in_array($quota, $this->getQuotaInterests(), true)) {
            $errors['quota_interest'] = 'Interesse de cota inválido.';
        }

        $lost = trim((string) ($data['lost_reason'] ?? ''));
        if ($status === 'perdido') {
            if ($lost === '') {
                $errors['lost_reason'] = 'Informe o motivo da perda.';
            } elseif (!in_array($lost, $this->getLostReasons(), true)) {
                $errors['lost_reason'] = 'Motivo de perda inválido.';
            }
        } elseif ($lost !== '' && !in_array($lost, $this->getLostReasons(), true)) {
            $errors['lost_reason'] = 'Motivo de perda inválido.';
        }

        // Coerência de probabilidade com status terminal.
        if ($status === 'fechado' && (int) $prob !== 100) {
            $errors['probability'] = 'Status Fechado exige probabilidade 100%.';
        }
        if ($status === 'perdido' && (int) $prob !== 0) {
            $errors['probability'] = 'Status Perdido exige probabilidade 0%.';
        }

        foreach (['opened_at', 'last_interaction_at', 'next_action_at', 'quota_reserved_until'] as $dt) {
            $v = trim((string) ($data[$dt] ?? ''));
            if ($v !== '' && strtotime($v) === false) {
                $errors[$dt] = 'Data/hora inválida.';
            }
        }

        return $errors;
    }

    /**
     * Valida o vínculo com uma cota real (quota_id).
     * Retorna mapa com chaves: error (string|null) e warning (string|null).
     *
     * @return array{error: ?string, warning: ?string, quota: ?array<string, mixed>}
     */
    public function validateQuota(int|string|null $quotaId): array
    {
        $result = ['error' => null, 'warning' => null, 'quota' => null];

        $quotaId = (int) ($quotaId ?? 0);
        if ($quotaId <= 0) {
            return $result;
        }

        $row = $this->query(
            'SELECT `id`, `name`, `amount`, `status`, `archived_at` FROM `quotas` WHERE `id` = :id LIMIT 1',
            ['id' => $quotaId]
        )->fetch();

        if ($row === false) {
            $result['error'] = 'A cota selecionada não existe.';

            return $result;
        }

        if ($row['archived_at'] !== null) {
            $result['error'] = 'A cota selecionada está arquivada e não pode ser vinculada.';

            return $result;
        }

        if (in_array((string) $row['status'], ['suspensa', 'fechada'], true)) {
            $result['warning'] = 'Atenção: a cota selecionada está ' . $row['status']
                . '. O vínculo foi mantido, mas confira a disponibilidade.';
        }

        $result['quota'] = $row;

        return $result;
    }

    /**
     * Normaliza datetime-local de reserva de cota; '' vira null.
     */
    public function normalizeQuotaReservedUntil(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);

        return $ts === false ? $value : date('Y-m-d H:i:s', $ts);
    }

    /**
     * Rótulo da cota da oportunidade: nome real (quota_id) ou texto legado.
     *
     * @param array<string, mixed> $opportunity
     */
    public function getQuotaLabel(array $opportunity): ?string
    {
        $name = trim((string) ($opportunity['quota_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $legacy = trim((string) ($opportunity['quota_interest'] ?? ''));

        return $legacy === '' ? null : $legacy;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

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
                  FROM `opportunities` o
                  JOIN `companies` co ON co.`id` = o.`company_id`
                  LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
                  LEFT JOIN `users` ow ON ow.`id` = o.`owner_user_id`
                  LEFT JOIN `quotas` q ON q.`id` = o.`quota_id`'
            . $where .
            ' ORDER BY ' . $this->orderBy() . '
              LIMIT ' . $perPage . ' OFFSET ' . $offset;

        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);

        $row = $this->query(
            'SELECT COUNT(*) AS c
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
               LEFT JOIN `users` ow ON ow.`id` = o.`owner_user_id`' . $where,
            $params
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT o.`id`, o.`company_id`, o.`contact_id`, o.`title`, o.`quota_interest`,
                    o.`estimated_value`, o.`probability`, o.`status`, o.`source`,
                    o.`owner_user_id`, o.`opened_at`, o.`last_interaction_at`, o.`next_action_at`,
                    o.`urgency_level`, o.`lost_reason`, o.`notes`,
                    o.`created_by`, o.`updated_by`, o.`created_at`, o.`updated_at`, o.`archived_at`,
                    o.`quota_id`, o.`quota_reserved_until`,
                    co.`name` AS company_name, co.`archived_at` AS company_archived_at,
                    ct.`name` AS contact_name, ct.`company_id` AS contact_company_id,
                    ow.`name` AS owner_name,
                    q.`name` AS quota_name, q.`commercial_name` AS quota_commercial_name,
                    q.`amount` AS quota_amount, q.`status` AS quota_status, q.`archived_at` AS quota_archived_at,
                    cb.`name` AS created_by_name,
                    ub.`name` AS updated_by_name
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
               LEFT JOIN `users` ow ON ow.`id` = o.`owner_user_id`
               LEFT JOIN `quotas` q ON q.`id` = o.`quota_id`
               LEFT JOIN `users` cb ON cb.`id` = o.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = o.`updated_by`
              WHERE o.`id` = :id
              LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Lista resumida de oportunidades (não arquivadas) por empresa.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByCompany(int|string $companyId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT o.`id`, o.`title`, o.`status`, o.`estimated_value`, o.`probability`,
                    o.`next_action_at`, o.`quota_id`, o.`quota_interest`,
                    ct.`name` AS contact_name, q.`name` AS quota_name
               FROM `opportunities` o
               LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
               LEFT JOIN `quotas` q ON q.`id` = o.`quota_id`
              WHERE o.`company_id` = :id AND o.`archived_at` IS NULL
              ORDER BY ' . $this->orderBy() . '
              LIMIT ' . $limit,
            ['id' => $companyId]
        )->fetchAll();
    }

    public function countByCompany(int|string $companyId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `opportunities`
              WHERE `company_id` = :id AND `archived_at` IS NULL',
            ['id' => $companyId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Resumo da empresa: abertas, fechadas e soma de valor aberto.
     *
     * @return array{open:int, closed:int, lost:int, open_value:float}
     */
    public function summaryByCompany(int|string $companyId): array
    {
        $row = $this->query(
            "SELECT
                SUM(CASE WHEN `status` NOT IN ('fechado','perdido') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN `status` = 'fechado' THEN 1 ELSE 0 END) AS closed_count,
                SUM(CASE WHEN `status` = 'perdido' THEN 1 ELSE 0 END) AS lost_count,
                COALESCE(SUM(CASE WHEN `status` NOT IN ('fechado','perdido') THEN `estimated_value` ELSE 0 END), 0) AS open_value
               FROM `opportunities`
              WHERE `company_id` = :id AND `archived_at` IS NULL",
            ['id' => $companyId]
        )->fetch();

        return [
            'open'       => (int) ($row['open_count'] ?? 0),
            'closed'     => (int) ($row['closed_count'] ?? 0),
            'lost'       => (int) ($row['lost_count'] ?? 0),
            'open_value' => (float) ($row['open_value'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByContact(int|string $contactId, int $limit = 10): array
    {
        $limit = max(1, $limit);

        return $this->query(
            'SELECT o.`id`, o.`title`, o.`status`, o.`estimated_value`, o.`probability`,
                    o.`next_action_at`, o.`quota_id`, o.`quota_interest`,
                    co.`name` AS company_name, q.`name` AS quota_name
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
               LEFT JOIN `quotas` q ON q.`id` = o.`quota_id`
              WHERE o.`contact_id` = :id AND o.`archived_at` IS NULL
              ORDER BY ' . $this->orderBy() . '
              LIMIT ' . $limit,
            ['id' => $contactId]
        )->fetchAll();
    }

    public function countByContact(int|string $contactId): int
    {
        $row = $this->query(
            'SELECT COUNT(*) AS c FROM `opportunities`
              WHERE `contact_id` = :id AND `archived_at` IS NULL',
            ['id' => $contactId]
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Resumo do pipeline: por status, quantidade e soma de valor.
     *
     * @param array<string, mixed> $filters
     * @return array<string, array{count:int, total:float}>
     */
    public function pipelineSummary(array $filters = []): array
    {
        $filters['show_archived'] = 0;
        [$where, $params] = $this->buildWhere($filters);

        $rows = $this->query(
            'SELECT o.`status` AS s, COUNT(*) AS c, COALESCE(SUM(o.`estimated_value`),0) AS t
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
               LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
               LEFT JOIN `users` ow ON ow.`id` = o.`owner_user_id`'
            . $where .
            ' GROUP BY o.`status`',
            $params
        )->fetchAll();

        $summary = [];
        foreach ($this->getStatuses() as $slug) {
            $summary[$slug] = ['count' => 0, 'total' => 0.0];
        }
        foreach ($rows as $r) {
            $slug = (string) $r['s'];
            if (isset($summary[$slug])) {
                $summary[$slug] = ['count' => (int) $r['c'], 'total' => (float) $r['t']];
            }
        }

        return $summary;
    }

    /**
     * Cards do pipeline, limitados por status (evita carga excessiva).
     *
     * @param array<string, mixed> $filters
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function pipelineItems(array $filters = [], int $limitPerStatus = 10): array
    {
        $limitPerStatus = max(1, $limitPerStatus);
        $filters['show_archived'] = 0;

        $items = [];
        foreach ($this->getStatuses() as $slug) {
            $f = $filters;
            $f['status'] = $slug;
            [$where, $params] = $this->buildWhere($f);

            $items[$slug] = $this->query(
                'SELECT o.`id`, o.`title`, o.`estimated_value`, o.`probability`,
                        o.`next_action_at`, o.`urgency_level`, o.`quota_id`, o.`quota_interest`,
                        co.`name` AS company_name, ct.`name` AS contact_name, ow.`name` AS owner_name,
                        q.`name` AS quota_name
                   FROM `opportunities` o
                   JOIN `companies` co ON co.`id` = o.`company_id`
                   LEFT JOIN `contacts` ct ON ct.`id` = o.`contact_id`
                   LEFT JOIN `users` ow ON ow.`id` = o.`owner_user_id`
                   LEFT JOIN `quotas` q ON q.`id` = o.`quota_id`'
                . $where .
                ' ORDER BY (o.`next_action_at` IS NOT NULL AND o.`next_action_at` < NOW()) DESC,
                           o.`probability` DESC, o.`updated_at` DESC
                  LIMIT ' . $limitPerStatus,
                $params
            )->fetchAll();
        }

        return $items;
    }

    // -----------------------------------------------------------------
    // Escrita (sem exclusão física)
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);
        if (!array_key_exists('created_by', $payload) && isset($data['created_by'])) {
            $payload['created_by'] = $data['created_by'];
        }

        $columns      = array_keys($payload);
        $escaped      = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $escaped[]      = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `opportunities` (%s) VALUES (%s)',
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
        if (isset($data['updated_by'])) {
            $payload['updated_by'] = $data['updated_by'];
        }
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
            'UPDATE `opportunities` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    /**
     * Mudança rápida de status (subconjunto de campos).
     *
     * @param array<string, mixed> $data
     */
    public function updateStatus(int|string $id, array $data): void
    {
        $allowed = ['status', 'probability', 'next_action_at', 'lost_reason', 'notes', 'last_interaction_at', 'updated_by'];
        $payload = [];
        foreach ($allowed as $c) {
            if (array_key_exists($c, $data)) {
                $payload[$c] = $data[$c];
            }
        }
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
            'UPDATE `opportunities` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `opportunities` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    public function restore(int|string $id): void
    {
        $this->query(
            'UPDATE `opportunities` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Ordenação padrão do funil. */
    private function orderBy(): string
    {
        $statusOrder = "'" . implode("','", $this->getStatuses()) . "'";

        return "(o.`next_action_at` IS NOT NULL AND o.`next_action_at` < NOW()) DESC,
                FIELD(o.`urgency_level`,'critica','alta','normal','baixa'),
                FIELD(o.`status`, {$statusOrder}) DESC,
                o.`probability` DESC,
                COALESCE(o.`updated_at`, o.`created_at`) DESC,
                o.`title` ASC";
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
            $conditions[] = 'o.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[]  = '(o.`title` LIKE :qt OR co.`name` LIKE :qc OR ct.`name` LIKE :qn OR o.`notes` LIKE :qnotes)';
            $params['qt']     = '%' . $q . '%';
            $params['qc']     = '%' . $q . '%';
            $params['qn']     = '%' . $q . '%';
            $params['qnotes'] = '%' . $q . '%';
        }

        $companyId = (int) ($filters['company_id'] ?? 0);
        if ($companyId > 0) {
            $conditions[]         = 'o.`company_id` = :company_id';
            $params['company_id'] = $companyId;
        }

        $contactId = (int) ($filters['contact_id'] ?? 0);
        if ($contactId > 0) {
            $conditions[]         = 'o.`contact_id` = :contact_id';
            $params['contact_id'] = $contactId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[]      = 'o.`status` = :status';
            $params['status']  = $status;
        }

        if (isset($filters['prob_min']) && $filters['prob_min'] !== '') {
            $conditions[]        = 'o.`probability` >= :prob_min';
            $params['prob_min']  = (int) $filters['prob_min'];
        }
        if (isset($filters['prob_max']) && $filters['prob_max'] !== '') {
            $conditions[]        = 'o.`probability` <= :prob_max';
            $params['prob_max']  = (int) $filters['prob_max'];
        }

        $quota = trim((string) ($filters['quota_interest'] ?? ''));
        if ($quota !== '') {
            $conditions[]            = 'o.`quota_interest` = :quota_interest';
            $params['quota_interest']= $quota;
        }

        $quotaId = (int) ($filters['quota_id'] ?? 0);
        if ($quotaId > 0) {
            $conditions[]       = 'o.`quota_id` = :quota_id';
            $params['quota_id'] = $quotaId;
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $conditions[]     = 'o.`source` = :source';
            $params['source'] = $source;
        }

        $urgency = trim((string) ($filters['urgency_level'] ?? ''));
        if ($urgency !== '') {
            $conditions[]            = 'o.`urgency_level` = :urgency_level';
            $params['urgency_level'] = $urgency;
        }

        $owner = (int) ($filters['owner'] ?? 0);
        if ($owner > 0) {
            $conditions[]    = 'o.`owner_user_id` = :owner';
            $params['owner'] = $owner;
        }

        if (!empty($filters['overdue'])) {
            $conditions[] = '(o.`next_action_at` IS NOT NULL AND o.`next_action_at` < NOW())';
        }

        // Grupos de resultado (abertas / fechadas / perdidas) — combinados por OR.
        $statusGroup = [];
        if (!empty($filters['open'])) {
            $statusGroup = array_merge($statusGroup, $this->getOpenStatuses());
        }
        if (!empty($filters['closed'])) {
            $statusGroup[] = 'fechado';
        }
        if (!empty($filters['lost'])) {
            $statusGroup[] = 'perdido';
        }
        if ($statusGroup !== []) {
            $in = [];
            foreach (array_values(array_unique($statusGroup)) as $i => $slug) {
                $key          = 'sg' . $i;
                $in[]         = ':' . $key;
                $params[$key] = $slug;
            }
            $conditions[] = 'o.`status` IN (' . implode(', ', $in) . ')';
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
