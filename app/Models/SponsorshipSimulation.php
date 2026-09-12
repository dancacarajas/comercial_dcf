<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

/**
 * Snapshot imutável de Simulação de Patrocínio (Guide → CRM).
 *
 * Representa o que o visitante declarou e recebeu naquele momento.
 * Negociação posterior ocorre na Opportunity — não reescreve este registro.
 */
final class SponsorshipSimulation extends Model
{
    protected string $table = 'sponsorship_simulations';

    public const FIT_LEVELS = [
        'VERY_HIGH' => 'Muito alta',
        'HIGH' => 'Alta',
        'MODERATE' => 'Moderada',
        'LOW' => 'Baixa',
        'INCOMPATIBLE' => 'Incompatível',
        'NOT_CALCULATED' => 'Não calculada',
    ];

    public const INVESTMENT_STATUSES = [
        'UNDEFINED',
        'OPEN',
        'DEFINED_AMOUNT',
        'DEFINED_RANGE',
    ];

    private const CREATE_COLUMNS = [
        'lead_id',
        'incentive_project_id',
        'submission_uuid',
        'submission_version',
        'snapshot_version',
        'catalog_version',
        'briefing_schema_version',
        'policy_version',
        'engine_version',
        'presenter_version',
        'confirmed',
        'confirmed_at',
        'investment_status',
        'investment_min',
        'investment_max',
        'currency',
        'primary_tier_ref',
        'primary_axis_ref',
        'primary_activation_ref',
        'primary_property_ref',
        'fit_level',
        'availability_status',
        'briefing_snapshot',
        'interests_snapshot',
        'recommendation_snapshot',
        'display_snapshot',
        'snapshot_hash',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT s.*, p.`project_name`, p.`pronac_number`, p.`edition_year`
               FROM `sponsorship_simulations` s
               LEFT JOIN `incentive_projects` p ON p.`id` = s.`incentive_project_id`
              WHERE s.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByLead(int|string $leadId): ?array
    {
        $row = $this->query(
            'SELECT s.*, p.`project_name`, p.`pronac_number`, p.`edition_year`
               FROM `sponsorship_simulations` s
               LEFT JOIN `incentive_projects` p ON p.`id` = s.`incentive_project_id`
              WHERE s.`lead_id` = :lead_id LIMIT 1',
            ['lead_id' => $leadId]
        )->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySubmissionUuid(string $uuid): ?array
    {
        $row = $this->query(
            'SELECT s.*, p.`project_name`, p.`pronac_number`, p.`edition_year`
               FROM `sponsorship_simulations` s
               LEFT JOIN `incentive_projects` p ON p.`id` = s.`incentive_project_id`
              WHERE s.`submission_uuid` = :uuid LIMIT 1',
            ['uuid' => $uuid]
        )->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Cria registro imutável. Sem update genérico de snapshot.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): string
    {
        $payload = [];
        foreach (self::CREATE_COLUMNS as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $payload[$col] = $data[$col];
        }

        foreach (['briefing_snapshot', 'interests_snapshot', 'recommendation_snapshot', 'display_snapshot'] as $jsonCol) {
            if (isset($payload[$jsonCol]) && is_array($payload[$jsonCol])) {
                $payload[$jsonCol] = $this->encodeSnapshot($payload[$jsonCol]);
            }
        }

        if (!isset($payload['confirmed'])) {
            $payload['confirmed'] = 0;
        }
        $payload['confirmed'] = (int) ((bool) $payload['confirmed']);

        $columns = array_keys($payload);
        $escaped = array_map(static fn (string $c): string => '`' . $c . '`', $columns);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);
        $escaped[] = '`created_at`';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO `sponsorship_simulations` (%s) VALUES (%s)',
            implode(', ', $escaped),
            implode(', ', $placeholders)
        );
        $this->query($sql, $payload);

        return $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encodeSnapshot(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new InvalidArgumentException('Falha ao serializar snapshot.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeSnapshot(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function fitLabel(?string $fit): string
    {
        $fit = strtoupper(trim((string) $fit));

        return self::FIT_LEVELS[$fit] ?? ($fit !== '' ? $fit : '—');
    }

    public function availabilityLabel(?string $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match ($status) {
            'AVAILABLE' => 'Disponível',
            'UNDER_CONFIRMATION' => 'Em confirmação',
            'UNAVAILABLE' => 'Indisponível',
            'NOT_CHECKED' => 'Ainda não verificada',
            default => $status !== '' ? $status : '—',
        };
    }

    /**
     * Nome comercial do tier a partir do ref estrutural (mapa canônico V2.1).
     * Nunca usa display_snapshot.
     */
    public function tierLabel(?string $ref): string
    {
        $ref = strtoupper(trim((string) $ref));
        if ($ref === '') {
            return '—';
        }

        $map = [
            'INCENTIVA' => 'Incentiva',
            'MOVIMENTO' => 'Movimento',
            'EXPERIENCE' => 'Experience',
            'CARAJAS' => 'Carajás',
            'APRESENTA' => 'Apresenta',
        ];

        return $map[$ref] ?? $ref;
    }

    /**
     * Rótulo de investimento a partir dos campos estruturais apenas.
     * display_snapshot NÃO é fonte de verdade comercial.
     *
     * @param array<string, mixed> $simulation
     */
    public function investmentDisplayLabel(array $simulation): string
    {
        $status = strtoupper((string) ($simulation['investment_status'] ?? ''));
        $min = $simulation['investment_min'] ?? null;
        $max = $simulation['investment_max'] ?? null;

        return match ($status) {
            'DEFINED_AMOUNT' => $this->moneyBrl($min),
            'DEFINED_RANGE' => $this->moneyBrl($min) . ' a ' . $this->moneyBrl($max),
            'OPEN' => 'Investimento em aberto',
            'UNDEFINED' => 'Investimento não definido',
            default => '—',
        };
    }

    private function moneyBrl(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrate(array $row): array
    {
        $row['briefing_snapshot'] = $this->decodeSnapshot(
            is_string($row['briefing_snapshot'] ?? null) ? $row['briefing_snapshot'] : null
        );
        $row['interests_snapshot'] = $this->decodeSnapshot(
            is_string($row['interests_snapshot'] ?? null) ? $row['interests_snapshot'] : null
        );
        $row['recommendation_snapshot'] = $this->decodeSnapshot(
            is_string($row['recommendation_snapshot'] ?? null) ? $row['recommendation_snapshot'] : null
        );
        $row['display_snapshot'] = $this->decodeSnapshot(
            is_string($row['display_snapshot'] ?? null) ? $row['display_snapshot'] : null
        );
        $row['confirmed'] = (int) ($row['confirmed'] ?? 0);

        return $row;
    }
}
