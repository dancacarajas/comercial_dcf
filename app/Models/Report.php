<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Serviço de agregação de relatórios gerenciais (Etapa 17).
 * Não representa tabela — consolida dados dos demais models.
 */
final class Report extends Model
{
    /** @var array<string, string> */
    private const REPORT_KEYS = [
        'executive'    => 'Executivo / consolidado',
        'pipeline'     => 'Funil comercial',
        'proposals'    => 'Propostas',
        'sponsors'     => 'Patrocinadores',
        'financials'   => 'Financeiro',
        'contracts'    => 'Contratos',
        'counterparts' => 'Contrapartidas',
        'dossiers'     => 'Dossiês / prestação de contas',
        'tasks'        => 'Tarefas e pendências',
        'leads'        => 'Leads do site',
    ];

    /** @return array<string, string> */
    public function getReportKeys(): array
    {
        return self::REPORT_KEYS;
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function normalizeFilters(array $input): array
    {
        $normalized = [
            'period_start'         => $this->normalizeDate((string) ($input['period_start'] ?? '')),
            'period_end'           => $this->normalizeDate((string) ($input['period_end'] ?? '')),
            'responsible_user_id'  => max(0, (int) ($input['responsible_user_id'] ?? 0)),
            'company_id'           => max(0, (int) ($input['company_id'] ?? 0)),
            'sponsor_id'           => max(0, (int) ($input['sponsor_id'] ?? 0)),
            'quota_id'             => max(0, (int) ($input['quota_id'] ?? 0)),
            'status'               => trim((string) ($input['status'] ?? '')),
            'source'               => trim((string) ($input['source'] ?? '')),
            'only_pending'         => !empty($input['only_pending']) ? 1 : 0,
            'only_overdue'         => !empty($input['only_overdue']) ? 1 : 0,
        ];

        if ($normalized['period_start'] === null && trim((string) ($input['period_start'] ?? '')) !== '') {
            $normalized['period_start'] = '';
        }
        if ($normalized['period_end'] === null && trim((string) ($input['period_end'] ?? '')) !== '') {
            $normalized['period_end'] = '';
        }

        return $normalized;
    }

    /** @param array<string, mixed> $filters @return array<string, string> */
    public function validateFilters(array $filters): array
    {
        $errors = [];
        $filters = $this->normalizeFilters($filters);

        if ($filters['period_start'] === '') {
            $errors['period_start'] = 'Data inicial inválida.';
        }
        if ($filters['period_end'] === '') {
            $errors['period_end'] = 'Data final inválida.';
        }
        if (is_string($filters['period_start']) || is_string($filters['period_end'])) {
            return $errors;
        }
        if ($filters['period_start'] !== null && $filters['period_end'] !== null && $filters['period_end'] < $filters['period_start']) {
            $errors['period_end'] = 'A data final não pode ser anterior à data inicial.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:?string,1:?string}
     */
    public function getDateRange(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $start = is_string($filters['period_start']) ? null : $filters['period_start'];
        $end = is_string($filters['period_end']) ? null : $filters['period_end'];

        return [$start, $end];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function buildCommonOptions(): array
    {
        return [
            'companies' => (new Company())->activeOptions(),
            'sponsors'  => $this->query(
                'SELECT `id`, `sponsor_display_name` AS `name`
                   FROM `sponsors`
                  WHERE `archived_at` IS NULL
                  ORDER BY `sponsor_display_name` ASC'
            )->fetchAll(),
            'quotas'    => (new Quota())->activeOptions(),
            'users'     => (new User())->activeList(),
        ];
    }

    public function formatMoney(float|int|null $value): string
    {
        return 'R$ ' . number_format((float) ($value ?? 0), 2, ',', '.');
    }

    public function percentage(float|int $part, float|int $total, int $decimals = 1): string
    {
        if ((float) $total <= 0) {
            return number_format(0, $decimals, ',', '.') . '%';
        }

        return number_format(((float) $part / (float) $total) * 100, $decimals, ',', '.') . '%';
    }

    /** @param array<string, mixed> $filters */
    public function getExecutiveReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        [$periodStart, $periodEnd] = $this->getDateRange($filters);

        $companyModel = new Company();
        $contactModel = new Contact();
        $oppModel = new Opportunity();
        $proposalModel = new Proposal();
        $sponsorModel = new Sponsor();
        $financialModel = new FinancialEntry();
        $contractModel = new Contract();
        $counterpartModel = new Counterpart();
        $dossierModel = new SponsorDossier();
        $taskModel = new Task();
        $leadModel = new Lead();

        $oppFilters = $this->moduleFilters($filters, 'opportunity');
        $oppFilters['open'] = 1;
        $pipeline = $oppModel->pipelineSummary($oppFilters);
        $pipelineValue = 0.0;
        foreach ($oppModel->getOpenStatuses() as $slug) {
            $pipelineValue += (float) ($pipeline[$slug]['total'] ?? 0);
        }

        $scoped = $this->hasScopedFilters($filters);
        $planned = $scoped ? $this->financialSum($filters, 'planned_amount') : $financialModel->sumPlanned();
        $received = $scoped ? $this->financialSum($filters, 'received_amount') : $financialModel->sumReceived();
        $remaining = $scoped ? $this->financialSum($filters, 'remaining_amount') : $financialModel->sumRemaining();
        $committed = $scoped ? $this->sponsorSum($filters, 'committed_amount') : $sponsorModel->sumCommitted();
        $confirmed = $scoped ? $this->sponsorSum($filters, 'confirmed_amount') : $sponsorModel->sumConfirmed();

        $metrics = [
            $this->metric('Empresas ativas', $companyModel->count($this->moduleFilters($filters, 'company')), 'number'),
            $this->metric('Contatos ativos', $contactModel->count($this->moduleFilters($filters, 'contact')), 'number'),
            $this->metric('Oportunidades abertas', $oppModel->count($oppFilters), 'number'),
            $this->metric('Valor em pipeline', $this->formatMoney($pipelineValue), 'money'),
            $this->metric('Propostas abertas', $scoped ? $this->proposalCountOpen($this->moduleFilters($filters, 'proposal')) : $proposalModel->countOpen(), 'number'),
            $this->metric('Patrocinadores ativos', $scoped ? $this->sponsorCount($this->moduleFilters($filters, 'sponsor')) : $sponsorModel->countActive(), 'number'),
            $this->metric('Valor comprometido', $this->formatMoney($committed), 'money'),
            $this->metric('Valor confirmado', $this->formatMoney($confirmed), 'money'),
            $this->metric('Previsto financeiro', $this->formatMoney($planned), 'money'),
            $this->metric('Recebido financeiro', $this->formatMoney($received), 'money'),
            $this->metric('Saldo financeiro', $this->formatMoney($remaining), 'money'),
            $this->metric('Taxa de recebimento', $this->percentage($received, $planned), 'percent'),
            $this->metric('Contratos ativos', $scoped ? $contractModel->count($this->moduleFilters($filters, 'contract')) : $contractModel->countActive(), 'number'),
            $this->metric('Contrapartidas pendentes', $scoped ? $counterpartModel->count(array_merge($this->moduleFilters($filters, 'counterpart'), ['pending' => 1])) : $counterpartModel->countPending(), 'number'),
            $this->metric('Dossiês pendentes', $scoped ? $dossierModel->count(array_merge($this->moduleFilters($filters, 'dossier'), ['pending' => 1])) : $dossierModel->countPending(), 'number'),
            $this->metric('Tarefas em aberto', $scoped ? $this->taskCountOpen($this->moduleFilters($filters, 'task')) : $taskModel->countOpen(), 'number'),
            $this->metric('Leads no período', $leadModel->count($this->moduleFilters($filters, 'lead')), 'number'),
        ];

        $alerts = $this->buildCrossModuleAlerts($filters);
        $periodLabel = $this->periodLabel($periodStart, $periodEnd);

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Resumo por módulo',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(
                    static fn (array $m): array => [$m['label'], (string) $m['value']],
                    $metrics
                ),
            ]],
            'alerts' => array_merge(
                $periodLabel !== '' ? [['type' => 'info', 'message' => 'Período analisado: ' . $periodLabel]] : [],
                $alerts
            ),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getPipelineReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $oppModel = new Opportunity();
        $oppFilters = $this->moduleFilters($filters, 'opportunity');
        $pipeline = $oppModel->pipelineSummary($oppFilters);
        $labels = $oppModel->getStatusLabels();

        $totalCount = 0;
        $totalValue = 0.0;
        $rows = [];
        foreach ($oppModel->getStatuses() as $slug) {
            $count = (int) ($pipeline[$slug]['count'] ?? 0);
            $value = (float) ($pipeline[$slug]['total'] ?? 0);
            $totalCount += $count;
            $totalValue += $value;
            $rows[] = [
                $labels[$slug] ?? $slug,
                (string) $count,
                $this->formatMoney($value),
                $this->percentage($value, $totalValue > 0 ? $totalValue : 1),
            ];
        }

        $openFilters = array_merge($oppFilters, ['open' => 1]);
        $metrics = [
            $this->metric('Oportunidades abertas', $oppModel->count($openFilters), 'number'),
            $this->metric('Valor total aberto', $this->formatMoney($totalValue), 'money'),
            $this->metric('Oportunidades no funil', $totalCount, 'number'),
            $this->metric('Fechadas', $oppModel->count(array_merge($oppFilters, ['closed' => 1])), 'number'),
            $this->metric('Perdidas', $oppModel->count(array_merge($oppFilters, ['lost' => 1])), 'number'),
        ];

        $alerts = [];
        if ($oppModel->count(array_merge($oppFilters, ['overdue' => 1, 'open' => 1])) > 0) {
            $alerts[] = ['type' => 'warning', 'message' => 'Existem oportunidades abertas com próxima ação vencida.'];
        }

        $rankings = $this->topCompaniesByPipeline($oppFilters, 5);

        return [
            'metrics'  => $metrics,
            'tables'   => [[
                'title'   => 'Funil por status',
                'headers' => ['Status', 'Quantidade', 'Valor estimado', '% do total'],
                'rows'    => $rows,
            ]],
            'alerts'   => $alerts,
            'rankings' => $rankings,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getProposalReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Proposal();
        $mf = $this->moduleFilters($filters, 'proposal');
        $scoped = $this->hasScopedFilters($filters);

        $open = $scoped ? $this->proposalCountOpen($mf) : $model->countOpen();
        $sent = $scoped
            ? $model->count(array_merge($mf, ['sent' => 1]))
            : $model->countSent();
        $expired = $scoped
            ? $model->count(array_merge($mf, ['expired' => 1]))
            : $model->countExpired();
        $openValue = $scoped
            ? $this->proposalSumOpenValue($mf)
            : $model->sumOpenValue();
        $total = $model->count($mf);

        $metrics = [
            $this->metric('Total de propostas', $total, 'number'),
            $this->metric('Abertas', $open, 'number'),
            $this->metric('Enviadas', $sent, 'number'),
            $this->metric('Expiradas', $expired, 'number'),
            $this->metric('Valor em aberto', $this->formatMoney($openValue), 'money'),
            $this->metric('Taxa de envio', $this->percentage($sent, $total), 'percent'),
        ];

        $alerts = [];
        if ($expired > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $expired . ' proposta(s) com validade expirada.'];
        }

        $rows = $this->query(
            'SELECT p.`title`, co.`name` AS company_name, p.`status`, p.`proposed_value`, p.`valid_until`
               FROM `proposals` p
               JOIN `companies` co ON co.`id` = p.`company_id`
              WHERE p.`archived_at` IS NULL
                AND p.`status` NOT IN (\'fechada\',\'recusada\',\'expirada\',\'arquivada\')'
            . $this->proposalExtraWhere($mf)
            . ' ORDER BY p.`valid_until` ASC, p.`updated_at` DESC LIMIT 15',
            $this->proposalExtraParams($mf)
        )->fetchAll();

        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                (string) ($row['title'] ?? ''),
                (string) ($row['company_name'] ?? ''),
                $model->getStatuses()[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
                $this->formatMoney((float) ($row['proposed_value'] ?? 0)),
                (string) ($row['valid_until'] ?? '—'),
            ];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Propostas abertas (top 15)',
                'headers' => ['Título', 'Empresa', 'Status', 'Valor', 'Validade'],
                'rows'    => $tableRows,
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getSponsorReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Sponsor();
        $mf = $this->moduleFilters($filters, 'sponsor');
        $scoped = $this->hasScopedFilters($filters);

        $total = $scoped ? $this->sponsorCount($mf) : $model->countActive();
        $confirmed = $scoped
            ? $this->sponsorCount(array_merge($mf, ['status' => 'confirmado']))
            : $model->countConfirmed();
        $awaiting = $scoped
            ? $this->sponsorCount(array_merge($mf, ['awaiting_contribution' => 1]))
            : $model->countAwaitingContribution();
        $overdue = $scoped
            ? $this->sponsorCount(array_merge($mf, ['overdue' => 1]))
            : $model->countOverdue();
        $committed = $scoped ? $this->sponsorSum($filters, 'committed_amount') : $model->sumCommitted();
        $confirmedAmount = $scoped ? $this->sponsorSum($filters, 'confirmed_amount') : $model->sumConfirmed();

        $metrics = [
            $this->metric('Patrocinadores', $total, 'number'),
            $this->metric('Confirmados', $confirmed, 'number'),
            $this->metric('Aguardando aporte', $awaiting, 'number'),
            $this->metric('Em atraso', $overdue, 'number'),
            $this->metric('Valor comprometido', $this->formatMoney($committed), 'money'),
            $this->metric('Valor confirmado', $this->formatMoney($confirmedAmount), 'money'),
            $this->metric('Confirmação sobre comprometido', $this->percentage($confirmedAmount, $committed), 'percent'),
        ];

        $alerts = [];
        if ($overdue > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $overdue . ' patrocinador(es) com pagamento em atraso.'];
        }
        if ($awaiting > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $awaiting . ' patrocinador(es) aguardando aporte.'];
        }

        return [
            'metrics'  => $metrics,
            'tables'   => [[
                'title'   => 'Indicadores de patrocínio',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(static fn (array $m): array => [$m['label'], (string) $m['value']], $metrics),
            ]],
            'alerts'   => $alerts,
            'rankings' => $this->topSponsorsByAmount($mf, 5),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getFinancialReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new FinancialEntry();
        $mf = $this->moduleFilters($filters, 'financial');
        $scoped = $this->hasScopedFilters($filters);

        $total = $scoped ? $model->count($mf) : $model->countActive();
        $planned = $scoped ? $this->financialSum($filters, 'planned_amount') : $model->sumPlanned();
        $received = $scoped ? $this->financialSum($filters, 'received_amount') : $model->sumReceived();
        $remaining = $scoped ? $this->financialSum($filters, 'remaining_amount') : $model->sumRemaining();
        $overdue = $scoped
            ? $model->count(array_merge($mf, ['overdue' => 1]))
            : $model->countOverdue();
        $pending = $scoped
            ? $model->count(array_merge($mf, ['pending' => 1]))
            : $model->countPending();
        $partial = $scoped
            ? $model->count(array_merge($mf, ['partial' => 1]))
            : $model->countPartial();
        $reconciled = $scoped
            ? $model->count(array_merge($mf, ['reconciled' => 1]))
            : $model->countReconciled();

        $metrics = [
            $this->metric('Lançamentos', $total, 'number'),
            $this->metric('Previsto', $this->formatMoney($planned), 'money'),
            $this->metric('Recebido', $this->formatMoney($received), 'money'),
            $this->metric('Saldo', $this->formatMoney($remaining), 'money'),
            $this->metric('Recebimento', $this->percentage($received, $planned), 'percent'),
            $this->metric('Pendentes', $pending, 'number'),
            $this->metric('Parciais', $partial, 'number'),
            $this->metric('Conciliados', $reconciled, 'number'),
            $this->metric('Em atraso', $overdue, 'number'),
        ];

        $alerts = [];
        if ($overdue > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $overdue . ' lançamento(s) financeiro(s) em atraso.'];
        }
        if ($remaining > 0) {
            $alerts[] = ['type' => 'warning', 'message' => 'Saldo pendente de recebimento: ' . $this->formatMoney($remaining) . '.'];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Resumo financeiro',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(static fn (array $m): array => [$m['label'], (string) $m['value']], $metrics),
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getContractReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Contract();
        $mf = $this->moduleFilters($filters, 'contract');
        $scoped = $this->hasScopedFilters($filters);

        $total = $scoped ? $model->count($mf) : $model->countActive();
        $signed = $scoped
            ? $model->count(array_merge($mf, ['signed' => 1]))
            : $model->countSigned();
        $awaiting = $scoped
            ? $model->count(array_merge($mf, ['awaiting_signature' => 1]))
            : $model->countAwaitingSignature();
        $vigente = $scoped
            ? $model->count(array_merge($mf, ['active_vigente' => 1]))
            : $model->countVigente();
        $expired = $scoped
            ? $model->count(array_merge($mf, ['expired' => 1]))
            : $model->countExpired();
        $formalized = $scoped ? $this->contractSumFormalized($mf) : $model->sumFormalized();

        $metrics = [
            $this->metric('Contratos', $total, 'number'),
            $this->metric('Assinados', $signed, 'number'),
            $this->metric('Aguardando assinatura', $awaiting, 'number'),
            $this->metric('Vigentes', $vigente, 'number'),
            $this->metric('Expirados / vencidos', $expired, 'number'),
            $this->metric('Valor formalizado', $this->formatMoney($formalized), 'money'),
            $this->metric('Taxa de assinatura', $this->percentage($signed, $total), 'percent'),
        ];

        $alerts = [];
        if ($awaiting > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $awaiting . ' contrato(s) aguardando assinatura.'];
        }
        if ($expired > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $expired . ' contrato(s) expirado(s) ou vencido(s).'];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Resumo de contratos',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(static fn (array $m): array => [$m['label'], (string) $m['value']], $metrics),
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getCounterpartReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Counterpart();
        $mf = $this->moduleFilters($filters, 'counterpart');
        $scoped = $this->hasScopedFilters($filters);

        $total = $scoped ? $model->count($mf) : $model->countActive();
        $delivered = $scoped
            ? $model->count(array_merge($mf, ['delivered' => 1]))
            : $model->countDelivered();
        $partial = $scoped
            ? $model->count(array_merge($mf, ['partial' => 1]))
            : $model->countPartial();
        $pending = $scoped
            ? $model->count(array_merge($mf, ['pending' => 1]))
            : $model->countPending();
        $overdue = $scoped
            ? $model->count(array_merge($mf, ['overdue' => 1]))
            : $model->countOverdue();

        $metrics = [
            $this->metric('Contrapartidas', $total, 'number'),
            $this->metric('Entregues / aprovadas', $delivered, 'number'),
            $this->metric('Parciais', $partial, 'number'),
            $this->metric('Pendentes', $pending, 'number'),
            $this->metric('Atrasadas', $overdue, 'number'),
            $this->metric('Taxa de entrega', $this->percentage($delivered, $total), 'percent'),
        ];

        $alerts = [];
        if ($overdue > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $overdue . ' contrapartida(s) atrasada(s).'];
        }
        if ($pending > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $pending . ' contrapartida(s) ainda pendentes.'];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Resumo de contrapartidas',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(static fn (array $m): array => [$m['label'], (string) $m['value']], $metrics),
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getDossierReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new SponsorDossier();
        $mf = $this->moduleFilters($filters, 'dossier');
        $scoped = $this->hasScopedFilters($filters);

        $total = $scoped ? $model->count($mf) : $model->countActive();
        $approved = $scoped
            ? $model->count(array_merge($mf, ['approved' => 1]))
            : $model->countApproved();
        $delivered = $scoped
            ? $model->count(array_merge($mf, ['delivered' => 1]))
            : $model->countDelivered();
        $pending = $scoped
            ? $model->count(array_merge($mf, ['pending' => 1]))
            : $model->countPending();
        $pendingCounterparts = $scoped
            ? $model->count(array_merge($mf, ['pending_counterparts' => 1]))
            : $model->countWithPendingCounterparts();
        $withBalance = $scoped
            ? $model->count(array_merge($mf, ['with_balance' => 1]))
            : $model->countWithFinancialBalance();

        $metrics = [
            $this->metric('Dossiês', $total, 'number'),
            $this->metric('Aprovados', $approved, 'number'),
            $this->metric('Entregues', $delivered, 'number'),
            $this->metric('Pendentes', $pending, 'number'),
            $this->metric('Com contrapartidas pendentes', $pendingCounterparts, 'number'),
            $this->metric('Com saldo financeiro', $withBalance, 'number'),
            $this->metric('Taxa de entrega', $this->percentage($delivered, $total), 'percent'),
        ];

        $alerts = [];
        if ($pending > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $pending . ' dossiê(s) pendente(s) de conclusão.'];
        }
        if ($withBalance > 0) {
            $alerts[] = ['type' => 'info', 'message' => $withBalance . ' dossiê(s) com saldo financeiro em aberto.'];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Resumo de dossiês',
                'headers' => ['Indicador', 'Valor'],
                'rows'    => array_map(static fn (array $m): array => [$m['label'], (string) $m['value']], $metrics),
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getTaskReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Task();
        $mf = $this->moduleFilters($filters, 'task');
        $scoped = $this->hasScopedFilters($filters);

        $open = $scoped ? $this->taskCountOpen($mf) : $model->countOpen();
        $overdue = $scoped
            ? $model->count(array_merge($mf, ['overdue' => 1]))
            : $model->countOverdue();
        $today = $scoped
            ? $model->count(array_merge($mf, ['today' => 1]))
            : $model->countDueToday();
        $total = $model->count($mf);

        $metrics = [
            $this->metric('Tarefas filtradas', $total, 'number'),
            $this->metric('Em aberto', $open, 'number'),
            $this->metric('Vencidas', $overdue, 'number'),
            $this->metric('Vencem hoje', $today, 'number'),
            $this->metric('Taxa de atraso (abertas)', $this->percentage($overdue, max(1, $open)), 'percent'),
        ];

        $alerts = [];
        if ($overdue > 0) {
            $alerts[] = ['type' => 'danger', 'message' => $overdue . ' tarefa(s) vencida(s).'];
        }

        $rows = $this->query(
            'SELECT t.`title`, co.`name` AS company_name, t.`due_date`, t.`priority`, t.`status`
               FROM `tasks` t
               LEFT JOIN `companies` co ON co.`id` = t.`company_id`
              WHERE t.`archived_at` IS NULL
                AND t.`status` NOT IN (\'concluida\',\'cancelada\',\'arquivada\')'
            . $this->taskExtraWhere($mf)
            . ' ORDER BY (t.`due_date` IS NULL), t.`due_date` ASC LIMIT 15',
            $this->taskExtraParams($mf)
        )->fetchAll();

        $statusLabels = $model->getStatuses();
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                (string) ($row['title'] ?? ''),
                (string) ($row['company_name'] ?? '—'),
                (string) ($row['due_date'] ?? '—'),
                (string) ($row['priority'] ?? ''),
                $statusLabels[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
            ];
        }

        return [
            'metrics' => $metrics,
            'tables'  => [[
                'title'   => 'Próximas tarefas em aberto',
                'headers' => ['Título', 'Empresa', 'Vencimento', 'Prioridade', 'Status'],
                'rows'    => $tableRows,
            ]],
            'alerts'  => $alerts,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getLeadReport(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);
        $model = new Lead();
        $mf = $this->moduleFilters($filters, 'lead');
        $statuses = $model->getStatuses();

        $total = $model->count($mf);
        $novos = $model->count(array_merge($mf, ['status' => 'novo']));
        $triagem = $model->count(array_merge($mf, ['status' => 'em_triagem']));
        $convertidos = $model->count(array_merge($mf, ['converted' => 1]));
        $descartados = $model->count(array_merge($mf, ['status' => 'descartado']));

        $metrics = [
            $this->metric('Leads no filtro', $total, 'number'),
            $this->metric('Novos', $novos, 'number'),
            $this->metric('Em triagem', $triagem, 'number'),
            $this->metric('Convertidos', $convertidos, 'number'),
            $this->metric('Descartados', $descartados, 'number'),
            $this->metric('Taxa de conversão', $this->percentage($convertidos, $total), 'percent'),
        ];

        $alerts = [];
        if ($novos > 0) {
            $alerts[] = ['type' => 'info', 'message' => $novos . ' lead(s) aguardando primeira triagem.'];
        }

        $rows = [];
        foreach ($statuses as $slug => $label) {
            $rows[] = [$label, (string) $model->count(array_merge($mf, ['status' => $slug]))];
        }

        return [
            'metrics'  => $metrics,
            'tables'   => [[
                'title'   => 'Leads por status',
                'headers' => ['Status', 'Quantidade'],
                'rows'    => $rows,
            ]],
            'alerts'   => $alerts,
            'rankings' => $this->topLeadOrigins($mf, 5),
        ];
    }

    // -----------------------------------------------------------------
    // Helpers internos
    // -----------------------------------------------------------------

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

    /** @return array{label:string,value:mixed,type:string} */
    private function metric(string $label, mixed $value, string $type = 'number'): array
    {
        return ['label' => $label, 'value' => $value, 'type' => $type];
    }

    /** @param array<string, mixed> $filters */
    private function hasScopedFilters(array $filters): bool
    {
        $filters = $this->normalizeFilters($filters);
        foreach ([
            'period_start', 'period_end', 'responsible_user_id',
            'company_id', 'sponsor_id', 'quota_id', 'status', 'source',
            'only_pending', 'only_overdue',
        ] as $key) {
            if ($key === 'period_start' || $key === 'period_end') {
                if ($filters[$key] !== null && $filters[$key] !== '') {
                    return true;
                }
                continue;
            }
            if (!empty($filters[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converte filtros comuns do relatório para filtros de cada módulo.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function moduleFilters(array $filters, string $module): array
    {
        $filters = $this->normalizeFilters($filters);
        [$start, $end] = $this->getDateRange($filters);

        $base = ['show_archived' => 0];

        if ($filters['company_id'] > 0 && in_array($module, ['company', 'contact', 'opportunity', 'proposal', 'task'], true)) {
            $base['company_id'] = $filters['company_id'];
        }
        if ($filters['sponsor_id'] > 0 && in_array($module, ['sponsor', 'contract', 'counterpart', 'financial', 'dossier'], true)) {
            $base['sponsor_id'] = $filters['sponsor_id'];
        }
        if ($filters['quota_id'] > 0 && $module !== 'company' && $module !== 'contact' && $module !== 'lead') {
            $base['quota_id'] = $filters['quota_id'];
        }
        if ($filters['status'] !== '') {
            $base['status'] = $filters['status'];
        }
        if ($filters['source'] !== '' && in_array($module, ['company', 'opportunity', 'lead'], true)) {
            if ($module === 'lead') {
                $base['origin_page'] = $filters['source'];
            } else {
                $base['source'] = $filters['source'];
            }
        }

        if ($filters['responsible_user_id'] > 0) {
            match ($module) {
                'company'       => $base['owner'] = $filters['responsible_user_id'],
                'contact'       => $base['owner'] = $filters['responsible_user_id'],
                'opportunity'   => $base['owner'] = $filters['responsible_user_id'],
                'task', 'lead'  => $base['assigned_user_id'] = $filters['responsible_user_id'],
                default         => $base['responsible_user_id'] = $filters['responsible_user_id'],
            };
        }

        if ($filters['only_pending']) {
            match ($module) {
                'financial'   => $base['pending'] = 1,
                'counterpart' => $base['pending'] = 1,
                'dossier'     => $base['pending'] = 1,
                'opportunity' => $base['open'] = 1,
                default       => null,
            };
        }

        if ($filters['only_overdue']) {
            $base['overdue'] = 1;
        }

        if ($start !== null || $end !== null) {
            match ($module) {
                'lead'        => $this->applyDateRange($base, 'date_from', 'date_to', $start, $end),
                'sponsor'     => $this->applyDateRange($base, 'closed_from', 'closed_to', $start, $end),
                'financial'   => $this->applyDateRange($base, 'due_from', 'due_to', $start, $end),
                'counterpart' => $this->applyDateRange($base, 'due_from', 'due_to', $start, $end),
                'contract'    => $this->applyDateRange($base, 'start_from', 'end_to', $start, $end),
                'dossier'     => $this->applyDateRange($base, 'period_from', 'period_to', $start, $end),
                'proposal'    => $this->applyDateRange($base, 'valid_from', 'valid_to', $start, $end),
                default       => null,
            };
        }

        return $base;
    }

    /** @param array<string, mixed> $target */
    private function applyDateRange(array &$target, string $fromKey, string $toKey, ?string $start, ?string $end): void
    {
        if ($start !== null) {
            $target[$fromKey] = $start;
        }
        if ($end !== null) {
            $target[$toKey] = $end;
        }
    }

    private function periodLabel(?string $start, ?string $end): string
    {
        if ($start === null && $end === null) {
            return '';
        }
        if ($start !== null && $end !== null) {
            return date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end));
        }
        if ($start !== null) {
            return 'a partir de ' . date('d/m/Y', strtotime($start));
        }

        return 'até ' . date('d/m/Y', strtotime((string) $end));
    }

    /** @param array<string, mixed> $filters @return list<array{type:string,message:string}> */
    private function buildCrossModuleAlerts(array $filters): array
    {
        $alerts = [];
        $taskModel = new Task();
        $financialModel = new FinancialEntry();
        $counterpartModel = new Counterpart();

        if ($taskModel->count(array_merge($this->moduleFilters($filters, 'task'), ['overdue' => 1])) > 0) {
            $alerts[] = ['type' => 'warning', 'message' => 'Há tarefas vencidas no escopo filtrado.'];
        }
        if ($financialModel->count(array_merge($this->moduleFilters($filters, 'financial'), ['overdue' => 1])) > 0) {
            $alerts[] = ['type' => 'danger', 'message' => 'Há lançamentos financeiros em atraso.'];
        }
        if ($counterpartModel->count(array_merge($this->moduleFilters($filters, 'counterpart'), ['overdue' => 1])) > 0) {
            $alerts[] = ['type' => 'warning', 'message' => 'Há contrapartidas atrasadas.'];
        }

        return $alerts;
    }

    /** @param array<string, mixed> $filters */
    private function financialSum(array $filters, string $column): float
    {
        $allowed = ['planned_amount', 'received_amount', 'remaining_amount'];
        if (!in_array($column, $allowed, true)) {
            return 0.0;
        }

        $mf = $this->moduleFilters($filters, 'financial');
        if ((new FinancialEntry())->count($mf) === 0) {
            return 0.0;
        }

        $sql = 'SELECT COALESCE(SUM(fe.`' . $column . '`), 0)
                  FROM `financial_entries` fe
                  INNER JOIN `sponsors` sp ON sp.`id` = fe.`sponsor_id`
                  LEFT JOIN `companies` co ON co.`id` = fe.`company_id`';
        [$extraWhere, $params] = $this->financialExtraWhere($mf);

        return (float) $this->query($sql . ' WHERE fe.`archived_at` IS NULL' . $extraWhere, $params)->fetchColumn();
    }

    /** @param array<string, mixed> $filters */
    private function sponsorSum(array $filters, string $column): float
    {
        $allowed = ['committed_amount', 'confirmed_amount'];
        if (!in_array($column, $allowed, true)) {
            return 0.0;
        }

        $mf = $this->moduleFilters($filters, 'sponsor');
        if ($this->sponsorCount($mf) === 0) {
            return 0.0;
        }

        $sql = 'SELECT COALESCE(SUM(s.`' . $column . '`), 0)
                  FROM `sponsors` s
                  LEFT JOIN `companies` co ON co.`id` = s.`company_id`';
        [$extraWhere, $params] = $this->sponsorExtraWhere($mf);

        return (float) $this->query($sql . ' WHERE s.`archived_at` IS NULL' . $extraWhere, $params)->fetchColumn();
    }

    /** @param array<string, mixed> $mf */
    private function contractSumFormalized(array $mf): float
    {
        $model = new Contract();
        if ($model->count($mf) === 0) {
            return 0.0;
        }

        $sql = 'SELECT COALESCE(SUM(ct.`formalized_value`), 0)
                  FROM `contracts` ct
                  INNER JOIN `sponsors` sp ON sp.`id` = ct.`sponsor_id`
                  LEFT JOIN `companies` co ON co.`id` = ct.`company_id`';
        [$extraWhere, $params] = $this->contractExtraWhere($mf);

        return (float) $this->query($sql . ' WHERE ct.`archived_at` IS NULL' . $extraWhere, $params)->fetchColumn();
    }

    /** @param array<string, mixed> $mf */
    private function sponsorCount(array $mf): int
    {
        $sql = 'SELECT COUNT(*) FROM `sponsors` s LEFT JOIN `companies` co ON co.`id` = s.`company_id`';
        [$extraWhere, $params] = $this->sponsorExtraWhere($mf);

        return (int) $this->query($sql . ' WHERE s.`archived_at` IS NULL' . $extraWhere, $params)->fetchColumn();
    }

    /** @param array<string, mixed> $mf */
    private function proposalCountOpen(array $mf): int
    {
        $openStatuses = (new Proposal())->getOpenStatuses();
        $in = implode(',', array_map(static fn ($s) => "'" . $s . "'", $openStatuses));

        $sql = 'SELECT COUNT(*) FROM `proposals` p
                 JOIN `companies` co ON co.`id` = p.`company_id`
                WHERE p.`archived_at` IS NULL AND p.`status` IN (' . $in . ')';

        return (int) $this->query($sql . $this->proposalExtraWhere($mf), $this->proposalExtraParams($mf))->fetchColumn();
    }

    /** @param array<string, mixed> $mf */
    private function taskCountOpen(array $mf): int
    {
        $sql = 'SELECT COUNT(*) FROM `tasks` t
                 LEFT JOIN `companies` co ON co.`id` = t.`company_id`
                WHERE t.`archived_at` IS NULL
                  AND t.`status` NOT IN (\'concluida\',\'cancelada\',\'arquivada\')';

        return (int) $this->query($sql . $this->taskExtraWhere($mf), $this->taskExtraParams($mf))->fetchColumn();
    }

    /** @param array<string, mixed> $mf */
    private function proposalSumOpenValue(array $mf): float
    {
        $model = new Proposal();
        $openFilters = array_merge($mf, ['open' => 1]);
        if ($model->count($openFilters) === 0) {
            return 0.0;
        }

        $openStatuses = (new Proposal())->getOpenStatuses();
        $in = implode(',', array_map(static fn ($s) => "'" . $s . "'", $openStatuses));

        $sql = 'SELECT COALESCE(SUM(p.`proposed_value`), 0)
                  FROM `proposals` p
                  JOIN `companies` co ON co.`id` = p.`company_id`
                 WHERE p.`archived_at` IS NULL AND p.`status` IN (' . $in . ')';

        return (float) $this->query($sql . $this->proposalExtraWhere($mf), $this->proposalExtraParams($mf))->fetchColumn();
    }

    /**
     * @param array<string, mixed> $mf
     * @return array{0:string,1:array<string,mixed>}
     */
    private function financialExtraWhere(array $mf): array
    {
        return $this->genericFkWhere($mf, 'fe', [
            'sponsor_id', 'company_id', 'quota_id', 'responsible_user_id',
        ], [
            'pending'  => "fe.`status` IN ('previsto','aguardando_pagamento')",
            'overdue'  => "(fe.`status` IN ('previsto','aguardando_pagamento','recebido_parcial')
                AND fe.`due_date` IS NOT NULL AND fe.`due_date` < CURDATE())",
            'due_from' => ['col' => 'fe.`due_date`', 'op' => '>=', 'param' => 'due_from'],
            'due_to'   => ['col' => 'fe.`due_date`', 'op' => '<=', 'param' => 'due_to'],
        ]);
    }

    /**
     * @param array<string, mixed> $mf
     * @return array{0:string,1:array<string,mixed>}
     */
    private function sponsorExtraWhere(array $mf): array
    {
        return $this->genericFkWhere($mf, 's', [
            'company_id', 'quota_id', 'responsible_user_id', 'sponsor_id',
        ], [
            'status'               => ['col' => 's.`status`', 'param' => 'status'],
            'awaiting_contribution'=> "s.`status` = 'aguardando_aporte'",
            'overdue'              => "(s.`payment_status` = 'em_atraso'
                OR (s.`expected_payment_date` IS NOT NULL AND s.`expected_payment_date` < CURDATE()
                    AND s.`payment_status` NOT IN ('recebido','nao_aplicavel','cancelado')))",
            'closed_from'          => ['col' => 'DATE(s.`closed_at`)', 'op' => '>=', 'param' => 'closed_from'],
            'closed_to'            => ['col' => 'DATE(s.`closed_at`)', 'op' => '<=', 'param' => 'closed_to'],
        ]);
    }

    /**
     * @param array<string, mixed> $mf
     * @return array{0:string,1:array<string,mixed>}
     */
    private function contractExtraWhere(array $mf): array
    {
        return $this->genericFkWhere($mf, 'ct', [
            'sponsor_id', 'company_id', 'quota_id', 'responsible_user_id',
        ], [
            'status'              => ['col' => 'ct.`status`', 'param' => 'status'],
            'signed'              => "(ct.`status` IN ('assinado','vigente') OR ct.`signature_status` = 'assinado')",
            'awaiting_signature'  => "(ct.`status` IN ('enviado_para_assinatura','aguardando_assinatura')
                OR ct.`signature_status` IN ('enviado_manual','aguardando_assinatura','parcialmente_assinado'))",
            'active_vigente'      => "ct.`status` = 'vigente'",
            'expired'             => "(ct.`end_date` IS NOT NULL AND ct.`end_date` < CURDATE()
                AND ct.`status` NOT IN ('encerrado','cancelado','arquivado','substituido','assinado','vigente'))",
            'start_from'          => ['col' => 'ct.`start_date`', 'op' => '>=', 'param' => 'start_from'],
            'end_to'              => ['col' => 'ct.`end_date`', 'op' => '<=', 'param' => 'end_to'],
        ]);
    }

    /** @param array<string, mixed> $mf */
    private function proposalExtraWhere(array $mf): string
    {
        [$where] = $this->genericFkWhere($mf, 'p', [
            'company_id', 'quota_id', 'responsible_user_id',
        ], [
            'status'     => ['col' => 'p.`status`', 'param' => 'status'],
            'sent'       => 'p.`sent_at` IS NOT NULL',
            'expired'    => "(p.`valid_until` IS NOT NULL AND p.`valid_until` < CURDATE()
                AND p.`status` NOT IN ('fechada','recusada','expirada','arquivada'))",
            'valid_from' => ['col' => 'p.`valid_until`', 'op' => '>=', 'param' => 'valid_from'],
            'valid_to'   => ['col' => 'p.`valid_until`', 'op' => '<=', 'param' => 'valid_to'],
        ]);

        return $where;
    }

    /** @param array<string, mixed> $mf @return array<string, mixed> */
    private function proposalExtraParams(array $mf): array
    {
        [, $params] = $this->genericFkWhere($mf, 'p', [
            'company_id', 'quota_id', 'responsible_user_id',
        ], [
            'status'     => ['col' => 'p.`status`', 'param' => 'status'],
            'sent'       => 'p.`sent_at` IS NOT NULL',
            'expired'    => "(p.`valid_until` IS NOT NULL AND p.`valid_until` < CURDATE()
                AND p.`status` NOT IN ('fechada','recusada','expirada','arquivada'))",
            'valid_from' => ['col' => 'p.`valid_until`', 'op' => '>=', 'param' => 'valid_from'],
            'valid_to'   => ['col' => 'p.`valid_until`', 'op' => '<=', 'param' => 'valid_to'],
        ]);

        return $params;
    }

    /** @param array<string, mixed> $mf */
    private function taskExtraWhere(array $mf): string
    {
        [$where] = $this->genericFkWhere($mf, 't', [
            'company_id', 'assigned_user_id',
        ], [
            'status'  => ['col' => 't.`status`', 'param' => 'status'],
            'overdue' => "(t.`status` NOT IN ('concluida','cancelada','arquivada')
                AND t.`due_date` IS NOT NULL
                AND (t.`due_date` < CURDATE()
                     OR (t.`due_date` = CURDATE() AND t.`due_time` IS NOT NULL AND t.`due_time` < CURTIME())))",
            'today'   => 't.`due_date` = CURDATE()',
            'open'    => "t.`status` NOT IN ('concluida','cancelada','arquivada')",
        ]);

        return $where;
    }

    /** @param array<string, mixed> $mf @return array<string, mixed> */
    private function taskExtraParams(array $mf): array
    {
        [, $params] = $this->genericFkWhere($mf, 't', [
            'company_id', 'assigned_user_id',
        ], [
            'status'  => ['col' => 't.`status`', 'param' => 'status'],
            'overdue' => "(t.`status` NOT IN ('concluida','cancelada','arquivada')
                AND t.`due_date` IS NOT NULL
                AND (t.`due_date` < CURDATE()
                     OR (t.`due_date` = CURDATE() AND t.`due_time` IS NOT NULL AND t.`due_time` < CURTIME())))",
            'today'   => 't.`due_date` = CURDATE()',
            'open'    => "t.`status` NOT IN ('concluida','cancelada','arquivada')",
        ]);

        return $params;
    }

    /**
     * @param array<string, mixed> $mf
     * @param list<string> $fkColumns
     * @param array<string, mixed> $rules
     * @return array{0:string,1:array<string,mixed>}
     */
    private function genericFkWhere(array $mf, string $alias, array $fkColumns, array $rules): array
    {
        $conditions = [];
        $params = [];

        foreach ($fkColumns as $col) {
            if ($col === 'sponsor_id' && $alias === 's') {
                $v = (int) ($mf[$col] ?? 0);
                if ($v > 0) {
                    $conditions[] = $alias . '.`id` = :' . $alias . '_id';
                    $params[$alias . '_id'] = $v;
                }
                continue;
            }
            $v = (int) ($mf[$col] ?? 0);
            if ($v > 0) {
                $conditions[] = $alias . '.`' . $col . '` = :' . $alias . '_' . $col;
                $params[$alias . '_' . $col] = $v;
            }
        }

        foreach ($rules as $key => $rule) {
            if (empty($mf[$key])) {
                continue;
            }
            if (is_string($rule)) {
                $conditions[] = $rule;
                continue;
            }
            if (isset($rule['col'], $rule['op'], $rule['param'])) {
                $param = $alias . '_' . $rule['param'];
                $conditions[] = $rule['col'] . ' ' . $rule['op'] . ' :' . $param;
                $params[$param] = is_numeric($mf[$key] ?? null) ? $mf[$key] : (string) ($mf[$key] ?? '');
            } elseif (isset($rule['col'], $rule['param'])) {
                $param = $alias . '_' . $rule['param'];
                $conditions[] = $rule['col'] . ' = :' . $param;
                $params[$param] = (string) ($mf[$key] ?? '');
            }
        }

        $where = $conditions === [] ? '' : ' AND ' . implode(' AND ', $conditions);

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $mf
     * @return list<array{title:string,items:list<array{label:string,value:string}>}>
     */
    private function topCompaniesByPipeline(array $mf, int $limit): array
    {
        $limit = max(1, $limit);
        $oppModel = new Opportunity();
        $openStatuses = $oppModel->getOpenStatuses();
        $in = implode(',', array_map(static fn ($s) => "'" . $s . "'", $openStatuses));

        $conditions = ['o.`archived_at` IS NULL', "o.`status` IN ($in)"];
        $params = [];
        if ((int) ($mf['company_id'] ?? 0) > 0) {
            $conditions[] = 'o.`company_id` = :company_id';
            $params['company_id'] = (int) $mf['company_id'];
        }
        if ((int) ($mf['owner'] ?? 0) > 0) {
            $conditions[] = 'o.`owner_user_id` = :owner';
            $params['owner'] = (int) $mf['owner'];
        }
        if ((int) ($mf['quota_id'] ?? 0) > 0) {
            $conditions[] = 'o.`quota_id` = :quota_id';
            $params['quota_id'] = (int) $mf['quota_id'];
        }

        $rows = $this->query(
            'SELECT co.`name` AS label, COUNT(*) AS cnt, COALESCE(SUM(o.`estimated_value`), 0) AS total
               FROM `opportunities` o
               JOIN `companies` co ON co.`id` = o.`company_id`
              WHERE ' . implode(' AND ', $conditions) . '
              GROUP BY co.`id`, co.`name`
              ORDER BY total DESC, cnt DESC
              LIMIT ' . $limit,
            $params
        )->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'label' => (string) ($row['label'] ?? ''),
                'value' => $this->formatMoney((float) ($row['total'] ?? 0)) . ' (' . (int) ($row['cnt'] ?? 0) . ' opp.)',
            ];
        }

        return $items === [] ? [] : [['title' => 'Top empresas por valor em pipeline', 'items' => $items]];
    }

    /**
     * @param array<string, mixed> $mf
     * @return list<array{title:string,items:list<array{label:string,value:string}>}>
     */
    private function topSponsorsByAmount(array $mf, int $limit): array
    {
        $limit = max(1, $limit);
        [, $params] = $this->sponsorExtraWhere($mf);

        $rows = $this->query(
            'SELECT s.`sponsor_display_name` AS label,
                    COALESCE(s.`confirmed_amount`, s.`committed_amount`, 0) AS total
               FROM `sponsors` s
               LEFT JOIN `companies` co ON co.`id` = s.`company_id`
              WHERE s.`archived_at` IS NULL'
            . ($this->sponsorExtraWhere($mf)[0])
            . ' ORDER BY total DESC, s.`sponsor_display_name` ASC
              LIMIT ' . $limit,
            $params
        )->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'label' => (string) ($row['label'] ?? ''),
                'value' => $this->formatMoney((float) ($row['total'] ?? 0)),
            ];
        }

        return $items === [] ? [] : [['title' => 'Top patrocinadores por valor', 'items' => $items]];
    }

    /**
     * @param array<string, mixed> $mf
     * @return list<array{title:string,items:list<array{label:string,value:string}>}>
     */
    private function topLeadOrigins(array $mf, int $limit): array
    {
        $limit = max(1, $limit);
        $conditions = ['l.`archived_at` IS NULL'];
        $params = [];

        if (!empty($mf['status'])) {
            $conditions[] = 'l.`status` = :status';
            $params['status'] = (string) $mf['status'];
        }
        if (!empty($mf['date_from'])) {
            $conditions[] = 'DATE(l.`created_at`) >= :df';
            $params['df'] = (string) $mf['date_from'];
        }
        if (!empty($mf['date_to'])) {
            $conditions[] = 'DATE(l.`created_at`) <= :dt';
            $params['dt'] = (string) $mf['date_to'];
        }
        if ((int) ($mf['assigned_user_id'] ?? 0) > 0) {
            $conditions[] = 'l.`assigned_user_id` = :owner';
            $params['owner'] = (int) $mf['assigned_user_id'];
        }

        $rows = $this->query(
            'SELECT COALESCE(NULLIF(l.`origin_page`, \'\'), l.`utm_source`, \'Não informado\') AS label,
                    COUNT(*) AS cnt
               FROM `leads` l
              WHERE ' . implode(' AND ', $conditions) . '
              GROUP BY label
              ORDER BY cnt DESC, label ASC
              LIMIT ' . $limit,
            $params
        )->fetchAll();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ((int) ($row['cnt'] ?? 0)),
            ];
        }

        return $items === [] ? [] : [['title' => 'Principais origens de leads', 'items' => $items]];
    }
}
