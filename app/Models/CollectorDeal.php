<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Trilha de origem comercial da captação (Etapa 18C — Fase 2).
 *
 * Liga um captador credenciado às entidades do funil (empresa, contato,
 * oportunidade, proposta, patrocinador, lançamento financeiro). É a fonte
 * de verdade para responder "qual captação nasceu de qual captador" e a
 * base para o cálculo de comissão na Fase 3 (não implementado aqui).
 */
final class CollectorDeal extends Model
{
    protected string $table = 'collector_deals';

    /** @var list<string> */
    private const FILLABLE = [
        'collector_id', 'collector_assignment_id', 'company_id', 'contact_id',
        'opportunity_id', 'proposal_id', 'sponsor_id', 'financial_entry_id',
        'deal_status', 'attribution_type', 'source', 'notes',
        'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'lead_indicado'       => 'Lead indicado',
            'empresa_em_analise'  => 'Empresa em análise',
            'abordagem_autorizada'=> 'Abordagem autorizada',
            'oportunidade_criada' => 'Oportunidade criada',
            'proposta_enviada'    => 'Proposta enviada',
            'negociacao'          => 'Negociação',
            'fechado'             => 'Fechado',
            'perdido'             => 'Perdido',
            'cancelado'           => 'Cancelado',
        ];
    }

    /** @return array<string, string> */
    public function getAttributionTypes(): array
    {
        return [
            'direta'        => 'Direta',
            'indicacao'     => 'Indicação',
            'compartilhada' => 'Compartilhada',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT d.*, c.`name` AS collector_name, c.`collector_code`,
                    co.`name` AS company_name, ct.`name` AS contact_name,
                    o.`title` AS opportunity_title, p.`title` AS proposal_title,
                    s.`sponsor_display_name` AS sponsor_name
               FROM `collector_deals` d
               LEFT JOIN `collectors` c    ON c.`id`  = d.`collector_id`
               LEFT JOIN `companies` co    ON co.`id` = d.`company_id`
               LEFT JOIN `contacts` ct     ON ct.`id` = d.`contact_id`
               LEFT JOIN `opportunities` o ON o.`id`  = d.`opportunity_id`
               LEFT JOIN `proposals` p     ON p.`id`  = d.`proposal_id`
               LEFT JOIN `sponsors` s      ON s.`id`  = d.`sponsor_id`
              WHERE d.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forCollector(int|string $collectorId, bool $includeArchived = false): array
    {
        $sql = 'SELECT d.*, co.`name` AS company_name, o.`title` AS opportunity_title,
                       p.`title` AS proposal_title, s.`sponsor_display_name` AS sponsor_name
                  FROM `collector_deals` d
                  LEFT JOIN `companies` co    ON co.`id` = d.`company_id`
                  LEFT JOIN `opportunities` o ON o.`id`  = d.`opportunity_id`
                  LEFT JOIN `proposals` p     ON p.`id`  = d.`proposal_id`
                  LEFT JOIN `sponsors` s      ON s.`id`  = d.`sponsor_id`
                 WHERE d.`collector_id` = :cid';
        if (!$includeArchived) {
            $sql .= ' AND d.`archived_at` IS NULL';
        }
        $sql .= ' ORDER BY d.`created_at` DESC, d.`id` DESC';

        return $this->query($sql, ['cid' => $collectorId])->fetchAll();
    }

    /**
     * Retorna o deal (com captador) vinculado a uma entidade do funil.
     *
     * @return array<string, mixed>|null
     */
    public function findByFunnelEntity(string $entity, int|string $id): ?array
    {
        $column = match ($entity) {
            'opportunity'      => 'opportunity_id',
            'proposal'         => 'proposal_id',
            'sponsor'          => 'sponsor_id',
            'financial_entry'  => 'financial_entry_id',
            'company'          => 'company_id',
            default            => null,
        };
        if ($column === null) {
            return null;
        }

        $row = $this->query(
            "SELECT d.*, c.`name` AS collector_name, c.`collector_code`
               FROM `collector_deals` d
               LEFT JOIN `collectors` c ON c.`id` = d.`collector_id`
              WHERE d.`{$column}` = :id AND d.`archived_at` IS NULL
              ORDER BY d.`id` DESC LIMIT 1",
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    public function hasDealForFunnelEntity(string $entity, int|string $id): bool
    {
        return $this->findByFunnelEntity($entity, $id) !== null;
    }

    /** @return array<int, array<string, mixed>> */
    public function opportunityOptions(int $limit = 200): array
    {
        return $this->query(
            'SELECT o.`id`, o.`title`, co.`name` AS company_name
               FROM `opportunities` o
               LEFT JOIN `companies` co ON co.`id` = o.`company_id`
              WHERE o.`archived_at` IS NULL
              ORDER BY o.`id` DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function proposalOptions(int $limit = 200): array
    {
        return $this->query(
            'SELECT p.`id`, p.`title`, co.`name` AS company_name
               FROM `proposals` p
               LEFT JOIN `companies` co ON co.`id` = p.`company_id`
              WHERE p.`archived_at` IS NULL
              ORDER BY p.`id` DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function sponsorOptions(int $limit = 200): array
    {
        return $this->query(
            'SELECT s.`id`, s.`sponsor_display_name` AS title, co.`name` AS company_name
               FROM `sponsors` s
               LEFT JOIN `companies` co ON co.`id` = s.`company_id`
              WHERE s.`archived_at` IS NULL
              ORDER BY s.`id` DESC LIMIT ' . (int) $limit
        )->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        if ((int) ($data['collector_id'] ?? 0) <= 0) {
            $errors['collector_id'] = 'Captador é obrigatório.';
        }
        if ((int) ($data['company_id'] ?? 0) <= 0) {
            $errors['company_id'] = 'Empresa é obrigatória.';
        }
        if (!array_key_exists((string) ($data['deal_status'] ?? ''), $this->getStatuses())) {
            $errors['deal_status'] = 'Status da captação inválido.';
        }
        if (!array_key_exists((string) ($data['attribution_type'] ?? ''), $this->getAttributionTypes())) {
            $errors['attribution_type'] = 'Tipo de atribuição inválido.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->filterFillable($data);
        $cols = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `collector_deals` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')',
            $payload
        );

        return (string) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->filterFillable($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        if ($payload === []) {
            return;
        }
        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $payload['id'] = $id;
        $this->query(
            'UPDATE `collector_deals` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function archive(int|string $id): void
    {
        $this->query(
            'UPDATE `collector_deals` SET `archived_at` = NOW(), `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $out[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }
        foreach (['collector_id', 'company_id', 'deal_status', 'attribution_type'] as $req) {
            if (array_key_exists($req, $out) && $out[$req] === null) {
                unset($out[$req]);
            }
        }

        return $out;
    }

    /**
     * Retorna o deal mais recente (nao arquivado) de uma empresa para um captador.
     *
     * @return array<string, mixed>|null
     */
    public function findByCompanyForCollector(int|string $companyId, int|string $collectorId): ?array
    {
        $row = $this->query(
            'SELECT * FROM `collector_deals`
              WHERE `company_id` = :co AND `collector_id` = :cl AND `archived_at` IS NULL
              ORDER BY `id` DESC LIMIT 1',
            ['co' => $companyId, 'cl' => $collectorId]
        )->fetch();

        return $row !== false ? $row : null;
    }
}
