<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Projeto Incentivado / PRONAC (ETAPA 19).
 *
 * Cadastro-mãe do sistema: cada edição/projeto SALIC com seu PRONAC, orçamento
 * aprovado, valor autorizado para captação e rubrica de remuneração de captação.
 * Todos os módulos operacionais (cotas, oportunidades, propostas, patrocinadores,
 * financeiro e carteira de captadores) passam a se vincular a um projeto.
 *
 * Regra do fator de comissão:
 *   commission_factor = capture_commission_budget / approved_total_amount
 */
final class IncentiveProject extends Model
{
    protected string $table = 'incentive_projects';

    /** @var list<string> */
    private const FILLABLE = [
        'project_name', 'edition_year', 'pronac_number', 'salic_proposal_number',
        'law_framework', 'proponent_name', 'proponent_document', 'project_status',
        'approved_total_amount', 'authorized_capture_amount', 'capture_commission_budget',
        'commission_factor', 'capture_start_date', 'capture_end_date',
        'bank_name', 'bank_agency', 'bank_account', 'bank_account_digit', 'bank_account_type',
        'notes',
    ];

    /** @return array<string, string> slug => rótulo. */
    public function getStatuses(): array
    {
        return [
            'em_elaboracao'        => 'Em elaboração',
            'enviado_salic'        => 'Enviado ao SALIC',
            'aprovado_sem_pronac'  => 'Aprovado sem PRONAC',
            'pronac_emitido'       => 'PRONAC emitido',
            'em_captacao'          => 'Em captação',
            'captado_parcial'      => 'Captado parcial',
            'captado_total'        => 'Captado total',
            'em_execucao'          => 'Em execução',
            'prestacao_contas'     => 'Prestação de contas',
            'encerrado'            => 'Encerrado',
            'cancelado'            => 'Cancelado',
        ];
    }

    /** Status nos quais o projeto está liberado para captação (portal). */
    public function getCaptureStatuses(): array
    {
        return ['em_captacao', 'captado_parcial'];
    }

    public function getBankAccountTypes(): array
    {
        return ['corrente' => 'Conta corrente', 'poupanca' => 'Poupança', 'bloqueada' => 'Conta bloqueada/captação'];
    }

    // -----------------------------------------------------------------
    // Normalização / cálculo
    // -----------------------------------------------------------------

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
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }
        if (!is_numeric($clean)) {
            return $value;
        }

        return (float) $clean;
    }

    /**
     * Calcula o fator de comissão a partir da rubrica e do total aprovado.
     */
    public function computeFactor(float|int|null $commissionBudget, float|int|null $approvedTotal): ?float
    {
        $budget = (float) ($commissionBudget ?? 0);
        $total  = (float) ($approvedTotal ?? 0);
        if ($total <= 0) {
            return null;
        }

        return round($budget / $total, 10);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        $name = trim((string) ($data['project_name'] ?? ''));
        if ($name === '' || mb_strlen($name) < 3) {
            $errors['project_name'] = 'Informe o nome do projeto (mínimo 3 caracteres).';
        }

        $year = (string) ($data['edition_year'] ?? '');
        if ($year !== '' && (!ctype_digit($year) || (int) $year < 2000 || (int) $year > 2100)) {
            $errors['edition_year'] = 'Ano da edição inválido.';
        }

        if (!array_key_exists((string) ($data['project_status'] ?? ''), $this->getStatuses())) {
            $errors['project_status'] = 'Status do projeto inválido.';
        }

        foreach (['approved_total_amount', 'authorized_capture_amount', 'capture_commission_budget'] as $f) {
            $v = $data[$f] ?? null;
            if (is_string($v)) {
                $errors[$f] = 'Valor monetário inválido.';
            } elseif ($v !== null && (float) $v < 0) {
                $errors[$f] = 'O valor deve ser positivo.';
            }
        }

        foreach (['capture_start_date', 'capture_end_date'] as $d) {
            $v = trim((string) ($data[$d] ?? ''));
            if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                $errors[$d] = 'Data inválida.';
            }
        }

        return $errors;
    }

    // -----------------------------------------------------------------
    // Leitura
    // -----------------------------------------------------------------

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT p.*, cb.`name` AS created_by_name, ub.`name` AS updated_by_name
               FROM `incentive_projects` p
               LEFT JOIN `users` cb ON cb.`id` = p.`created_by`
               LEFT JOIN `users` ub ON ub.`id` = p.`updated_by`
              WHERE p.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        return $this->query(
            'SELECT p.* FROM `incentive_projects` p' . $where .
            ' ORDER BY p.`edition_year` DESC, p.`id` DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        )->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function count(array $filters): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->query('SELECT COUNT(*) AS c FROM `incentive_projects` p' . $where, $params)->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Opções para selects (id => "Projeto (ano)").
     *
     * @return array<int, array{id:int,label:string,project_status:string}>
     */
    public function options(bool $onlyCapture = false): array
    {
        $sql = 'SELECT `id`, `project_name`, `edition_year`, `project_status`
                  FROM `incentive_projects` WHERE `archived_at` IS NULL';
        $params = [];
        if ($onlyCapture) {
            $in = "'" . implode("','", $this->getCaptureStatuses()) . "'";
            $sql .= " AND `project_status` IN ({$in})";
        }
        $sql .= ' ORDER BY `edition_year` DESC, `project_name` ASC';

        $out = [];
        foreach ($this->query($sql, $params)->fetchAll() as $r) {
            $label = (string) $r['project_name'];
            if (!empty($r['edition_year'])) {
                $label .= ' (' . (int) $r['edition_year'] . ')';
            }
            $out[] = [
                'id'             => (int) $r['id'],
                'label'          => $label,
                'project_status' => (string) $r['project_status'],
            ];
        }

        return $out;
    }

    // -----------------------------------------------------------------
    // Escrita
    // -----------------------------------------------------------------

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->fillablePayload($data);
        if (isset($data['created_by'])) {
            $payload['created_by'] = $data['created_by'];
        }
        $cols = array_map(static fn ($c) => '`' . $c . '`', array_keys($payload));
        $ph   = array_map(static fn ($c) => ':' . $c, array_keys($payload));
        $cols[] = '`created_at`';
        $ph[]   = 'NOW()';

        $this->query(
            'INSERT INTO `incentive_projects` (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')',
            $payload
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
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

        $this->query('UPDATE `incentive_projects` SET ' . implode(', ', $sets) . ' WHERE `id` = :id', $payload);
    }

    public function archive(int|string $id): void
    {
        $this->query('UPDATE `incentive_projects` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    public function restore(int|string $id): void
    {
        $this->query('UPDATE `incentive_projects` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id', ['id' => $id]);
    }

    /**
     * Recalcula e grava capture_commission_budget (soma das rubricas marcadas)
     * e o commission_factor.
     */
    public function syncBudgetAndFactor(int|string $id): void
    {
        $project = $this->find($id);
        if ($project === null) {
            return;
        }
        $sum = $this->query(
            'SELECT COALESCE(SUM(`requested_amount`), 0) AS s
               FROM `incentive_project_budget_items`
              WHERE `incentive_project_id` = :p AND `is_capture_commission_item` = 1 AND `archived_at` IS NULL',
            ['p' => $id]
        )->fetch();
        $budget = (float) ($sum['s'] ?? 0);
        $factor = $this->computeFactor($budget, $project['approved_total_amount'] ?? null);

        $this->query(
            'UPDATE `incentive_projects` SET `capture_commission_budget` = :b, `commission_factor` = :f, `updated_at` = NOW() WHERE `id` = :id',
            ['b' => $budget > 0 ? $budget : null, 'f' => $factor, 'id' => $id]
        );
    }

    // -----------------------------------------------------------------
    // Dashboard do projeto
    // -----------------------------------------------------------------

    /**
     * Agrega indicadores do projeto a partir dos módulos vinculados.
     *
     * @return array<string, mixed>
     */
    public function dashboard(int|string $projectId): array
    {
        $p = ['p' => $projectId];

        $opp = $this->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN `status` NOT IN ('fechado','perdido') THEN 1 ELSE 0 END) AS open_count,
                COALESCE(SUM(CASE WHEN `status` NOT IN ('fechado','perdido') THEN `estimated_value` ELSE 0 END),0) AS open_value
               FROM `opportunities`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL",
            $p
        )->fetch() ?: [];

        $proposals = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `sent_at` IS NOT NULL THEN 1 ELSE 0 END) AS sent_count,
                    COALESCE(SUM(`proposed_value`),0) AS total_value
               FROM `proposals`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL",
            $p
        )->fetch() ?: [];

        $sponsors = $this->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN `status` <> 'cancelado' THEN 1 ELSE 0 END) AS active_count
               FROM `sponsors`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL",
            $p
        )->fetch() ?: [];

        $fin = $this->query(
            "SELECT
                COALESCE(SUM(`planned_amount`),0) AS planned_total,
                COALESCE(SUM(`received_amount`),0) AS received_total
               FROM `financial_entries`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL",
            $p
        )->fetch() ?: [];

        $companies = $this->query(
            'SELECT COUNT(DISTINCT `company_id`) AS c FROM `collector_assignments`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL',
            $p
        )->fetch() ?: [];

        $collectors = $this->query(
            'SELECT COUNT(DISTINCT `collector_id`) AS c FROM `collector_deals`
              WHERE `incentive_project_id` = :p AND `archived_at` IS NULL',
            $p
        )->fetch() ?: [];

        $quotas = $this->query(
            'SELECT COUNT(*) AS c FROM `quotas` WHERE `incentive_project_id` = :p AND `archived_at` IS NULL',
            $p
        )->fetch() ?: [];

        return [
            'opportunities_total' => (int) ($opp['total'] ?? 0),
            'opportunities_open'  => (int) ($opp['open_count'] ?? 0),
            'pipeline_value'      => (float) ($opp['open_value'] ?? 0),
            'proposals_total'     => (int) ($proposals['total'] ?? 0),
            'proposals_sent'      => (int) ($proposals['sent_count'] ?? 0),
            'proposals_value'     => (float) ($proposals['total_value'] ?? 0),
            'sponsors_total'      => (int) ($sponsors['active_count'] ?? 0),
            'financial_planned'   => (float) ($fin['planned_total'] ?? 0),
            'financial_received'  => (float) ($fin['received_total'] ?? 0),
            'companies_count'     => (int) ($companies['c'] ?? 0),
            'collectors_count'    => (int) ($collectors['c'] ?? 0),
            'quotas_count'        => (int) ($quotas['c'] ?? 0),
        ];
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'p.`archived_at` IS NULL';
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(p.`project_name` LIKE :q OR p.`pronac_number` LIKE :q OR p.`proponent_name` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $conditions[] = 'p.`project_status` = :st';
            $params['st'] = $status;
        }
        $year = (int) ($filters['year'] ?? 0);
        if ($year > 0) {
            $conditions[] = 'p.`edition_year` = :yr';
            $params['yr'] = $year;
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
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $payload[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }

        return $payload;
    }
}
