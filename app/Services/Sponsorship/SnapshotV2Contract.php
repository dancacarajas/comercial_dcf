<?php
declare(strict_types=1);

namespace App\Services\Sponsorship;

/**
 * Constantes do Snapshot V2.0.0 (Etapa 22.3-A) — centavos inteiros.
 */
final class SnapshotV2Contract
{
    public const SNAPSHOT_VERSION = '2.0.0';
    public const SCENARIO_VERSION = '2.2.0';
    public const CATALOG_VERSION = '2026-V2.1';
    public const BRIEFING_SCHEMA_VERSION = '1.0.0';

    public const CENTS_MOVIMENTO = 2500000;
    public const CENTS_EXPERIENCE = 5000000;
    public const CENTS_CARAJAS = 10000000;
    public const CENTS_APRESENTA = 51559248;
    public const CENTS_INCENTIVA_MIN = 1000000;
    public const CENTS_INCENTIVA_MAX_EXCL = 2500000;
    public const CENTS_AUTHORIZED_CAP = 51559248;

    public const MAX_MOVIMENTO = 4;
    public const MAX_EXPERIENCE = 2;
    public const MAX_CARAJAS = 4;

    public const RESULT_TYPES = [
        'SINGLE',
        'COMPOSITION',
        'NO_EXACT_COMPOSITION',
        'NO_EXACT_COMPOSITION_IN_RANGE',
        'STRATEGIC_ONLY',
        'NO_CANONICAL_CONFIGURATION',
        'ABOVE_AUTHORIZED_CAP',
    ];

    public const AXES = [
        'FORMACAO',
        'MOSTRA',
        'ECONOMIA_CRIATIVA',
        'MEMORIAS',
    ];

    public const TIERS = [
        'INCENTIVA',
        'MOVIMENTO',
        'EXPERIENCE',
        'CARAJAS',
        'APRESENTA',
    ];

    public const INVESTMENT_STATUSES = [
        'UNDEFINED',
        'OPEN',
        'DEFINED_AMOUNT',
        'DEFINED_RANGE',
    ];

    public const SIM_KEYS = [
        'snapshot_version',
        'scenario_version',
        'catalog_version',
        'briefing_schema_version',
        'engine_version',
        'policy_version',
        'presenter_version',
        'confirmed',
        'confirmed_at',
        'briefing',
        'interests',
        'scenario_result',
        'display_snapshot',
    ];

    public const SCENARIO_KEYS = [
        'result_type',
        'investment',
        'total_amount_cents',
        'primary_tier_id',
        'units',
        'near_options',
        'strategic_options',
        'availability',
        'authorized_cap_cents',
        'minimum_canonical_amount_cents',
    ];

    public const UNIT_KEYS = [
        'tier_id',
        'amount_cents',
        'axis_id',
    ];

    public const INVESTMENT_KEYS = [
        'status',
        'min_cents',
        'max_cents',
        'currency',
    ];
}
