<?php

declare(strict_types=1);

/**
 * Harness Etapa 22.3-A — Snapshot V2 validator + intake HTTP.
 *
 * Uso: php tools/validate_etapa223a_snapshot_v2.php
 * Env: TEST_BASE (default http://127.0.0.1:8089), LEAD_TOKEN / LEAD_ENDPOINT_SECRET
 */

$root = dirname(__DIR__);
$base = rtrim((string) (getenv('TEST_BASE') ?: 'http://127.0.0.1:8089'), '/');
$token = getenv('LEAD_TOKEN') ?: '';

require_once $root . '/app/Helpers/env.php';
load_env($root . '/.env');
if ($token === '') {
    $token = (string) (getenv('LEAD_ENDPOINT_SECRET')
        ?: ($_ENV['LEAD_ENDPOINT_SECRET'] ?? 'dcf-local-lead-token-2026-trocar-em-producao'));
}
if (PHP_SAPI === 'cli') {
    $host = (string) (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ''));
    if ($host === 'db' || $host === 'localhost' || $host === '127.0.0.1') {
        putenv('DB_HOST=127.0.0.1');
        putenv('DB_PORT=3307');
        $_ENV['DB_HOST'] = '127.0.0.1';
        $_ENV['DB_PORT'] = '3307';
    }
}

require_once $root . '/app/Core/Database.php';
require_once $root . '/app/Core/Model.php';
require_once $root . '/app/Models/SponsorshipSimulation.php';
require_once $root . '/app/Services/SponsorshipLeadIntake.php';
require_once $root . '/app/Services/Sponsorship/SnapshotV2Contract.php';
require_once $root . '/app/Services/Sponsorship/SnapshotV2Validator.php';
require_once $root . '/app/Services/SponsorshipLeadIntakeV2.php';

use App\Models\SponsorshipSimulation;
use App\Services\Sponsorship\SnapshotV2Contract;
use App\Services\Sponsorship\SnapshotV2Validator;
use App\Services\SponsorshipLeadIntakeV2;

$pdo = \App\Core\Database::connection();
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$results = [];

function pass(string $label, bool $ok, string $detail = ''): void
{
    global $results;
    $results[] = ['ok' => $ok, 'label' => $label];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $label . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function uuidV4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function definedAmount(int $cents): array
{
    return [
        'status' => 'DEFINED_AMOUNT',
        'min_cents' => $cents,
        'max_cents' => $cents,
        'currency' => 'BRL',
    ];
}

function baseSim(array $scenario, ?array $briefInv = null): array
{
    $briefInv ??= $scenario['investment'] ?? definedAmount(1000000);

    return [
        'snapshot_version' => SnapshotV2Contract::SNAPSHOT_VERSION,
        'scenario_version' => SnapshotV2Contract::SCENARIO_VERSION,
        'catalog_version' => SnapshotV2Contract::CATALOG_VERSION,
        'briefing_schema_version' => SnapshotV2Contract::BRIEFING_SCHEMA_VERSION,
        'engine_version' => '2.2.0',
        'policy_version' => '2.2.0',
        'confirmed' => true,
        'confirmed_at' => '2026-09-14T12:00:00-03:00',
        'briefing' => [
            'area_decision' => 'INSTITUTIONAL_RELATIONS',
            'objectives' => ['INSTITUTIONAL_ASSOCIATION'],
            'audiences' => ['CLIENTS'],
            'depth_intent' => 'OWN_AXIS',
            'investment' => $briefInv,
            'proof_needs' => ['EXECUTIVE_REPORT'],
        ],
        'interests' => [
            'tier_interest' => [],
            'axis_interest' => [],
            'activation_interest' => [],
            'property_interest' => [],
            'asset_interest' => [],
        ],
        'scenario_result' => $scenario,
        'display_snapshot' => [
            'objective_labels' => ['Associacao institucional'],
            'depth_label' => 'Territorio',
        ],
    ];
}

function envelope(array $sim, ?string $uuid = null): array
{
    return [
        'submission_type' => 'SPONSORSHIP_SIMULATION',
        'submission_version' => '1.0.0',
        'submission_uuid' => $uuid ?? uuidV4(),
        'name' => 'Teste Snapshot V2',
        'company_name' => 'Empresa V2 Ltda',
        'email' => 'snapshot.v2.' . substr(md5((string) microtime(true)), 0, 8) . '@example.com',
        'contact_consent' => true,
        'origin_page' => 'patrocinio/seja-patrocinador',
        'form_id' => 'dcx-sponsorship-simulation-v2',
        'form_name' => 'Simular Patrocinio V2',
        'project' => [
            'pronac_number' => '265397',
            'edition_year' => 2026,
        ],
        'sponsorship_simulation' => $sim,
    ];
}

function clearRateLimit(): void
{
    global $root;
    $rlDir = $root . '/storage/ratelimit';
    if (!is_dir($rlDir)) {
        return;
    }
    foreach (glob($rlDir . '/*.json') ?: [] as $f) {
        @unlink($f);
    }
}

function req(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $opts['headers'] ?? [],
        CURLOPT_POSTFIELDS => $opts['body'] ?? null,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
}

function postLead(array $payload): array
{
    global $base, $token;
    clearRateLimit();

    return req('POST', "{$base}/api/leads/site", [
        'headers' => [
            'Content-Type: application/json',
            'X-DCF-Lead-Token: ' . $token,
        ],
        'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function assertValid(string $label, array $scenario, bool $expectOk = true): void
{
    $validator = new SnapshotV2Validator();
    [$out, $errors] = $validator->validate(baseSim($scenario));
    $ok = $expectOk ? ($errors === [] && $out !== []) : ($errors !== []);
    pass($label, $ok, $expectOk
        ? ($errors === [] ? '' : json_encode($errors, JSON_UNESCAPED_UNICODE))
        : ($errors === [] ? 'esperava REJECT' : ''));
}

echo "== 22.3-A Snapshot V2 — validator ==\n";

// PASS cases
assertValid('18k SINGLE INCENTIVA', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(1800000),
    'total_amount_cents' => 1800000,
    'primary_tier_id' => 'INCENTIVA',
    'units' => [['tier_id' => 'INCENTIVA', 'amount_cents' => 1800000]],
    'availability' => 'NOT_CHECKED',
]);

assertValid('25k SINGLE MOVIMENTO', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(2500000),
    'total_amount_cents' => 2500000,
    'primary_tier_id' => 'MOVIMENTO',
    'units' => [['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000]],
    'availability' => 'NOT_CHECKED',
]);

assertValid('50k SINGLE EXPERIENCE', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(5000000),
    'total_amount_cents' => 5000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000]],
    'availability' => 'NOT_CHECKED',
]);

assertValid('100k Carajas Formacao SINGLE', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(10000000),
    'total_amount_cents' => 10000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO']],
    'availability' => 'NOT_CHECKED',
]);

assertValid('75k COMPOSITION E+M', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(7500000),
    'total_amount_cents' => 7500000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('125k COMPOSITION C+M', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(12500000),
    'total_amount_cents' => 12500000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('150k COMPOSITION C+E', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(15000000),
    'total_amount_cents' => 15000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MOSTRA'],
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('175k COMPOSITION C+E+M', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(17500000),
    'total_amount_cents' => 17500000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('200k 2 Carajas eixos distintos', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(20000000),
    'total_amount_cents' => 20000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MOSTRA'],
    ],
    'availability' => 'NOT_CHECKED',
]);

$scenario300 = [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(30000000),
    'total_amount_cents' => 30000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MOSTRA'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'ECONOMIA_CRIATIVA'],
    ],
    'availability' => 'NOT_CHECKED',
];
assertValid('300k 3 Carajas eixos distintos', $scenario300);

assertValid('300k axis parcialmente ausente', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(30000000),
    'total_amount_cents' => 30000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MOSTRA'],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('38k NO_EXACT + near 25/50', [
    'result_type' => 'NO_EXACT_COMPOSITION',
    'investment' => definedAmount(3800000),
    'near_options' => [
        ['tier_id' => 'MOVIMENTO', 'total_amount_cents' => 2500000],
        ['tier_id' => 'EXPERIENCE', 'total_amount_cents' => 5000000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('range 30-40 NO_EXACT_RANGE', [
    'result_type' => 'NO_EXACT_COMPOSITION_IN_RANGE',
    'investment' => [
        'status' => 'DEFINED_RANGE',
        'min_cents' => 3000000,
        'max_cents' => 4000000,
        'currency' => 'BRL',
    ],
    'near_options' => [
        ['tier_id' => 'MOVIMENTO', 'total_amount_cents' => 2500000],
        ['tier_id' => 'EXPERIENCE', 'total_amount_cents' => 5000000],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('range 30-60 SINGLE Experience', [
    'result_type' => 'SINGLE',
    'investment' => [
        'status' => 'DEFINED_RANGE',
        'min_cents' => 3000000,
        'max_cents' => 6000000,
        'currency' => 'BRL',
    ],
    'total_amount_cents' => 5000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000]],
    'availability' => 'NOT_CHECKED',
]);

assertValid('OPEN STRATEGIC_ONLY', [
    'result_type' => 'STRATEGIC_ONLY',
    'investment' => ['status' => 'OPEN', 'min_cents' => null, 'max_cents' => null, 'currency' => 'BRL'],
    'strategic_options' => [
        ['tier_id' => 'CARAJAS', 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'EXPERIENCE'],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('UNDEFINED STRATEGIC_ONLY', [
    'result_type' => 'STRATEGIC_ONLY',
    'investment' => ['status' => 'UNDEFINED', 'min_cents' => null, 'max_cents' => null, 'currency' => 'BRL'],
    'strategic_options' => [
        ['tier_id' => 'MOVIMENTO'],
    ],
    'availability' => 'NOT_CHECKED',
]);

assertValid('8k NO_CANONICAL_CONFIGURATION', [
    'result_type' => 'NO_CANONICAL_CONFIGURATION',
    'investment' => definedAmount(800000),
    'minimum_canonical_amount_cents' => 1000000,
    'availability' => 'NOT_CHECKED',
]);

assertValid('600k ABOVE_AUTHORIZED_CAP', [
    'result_type' => 'ABOVE_AUTHORIZED_CAP',
    'investment' => definedAmount(60000000),
    'authorized_cap_cents' => 51559248,
    'availability' => 'NOT_CHECKED',
]);

// REJECT cases
assertValid('REJECT 2 Carajas mesmo eixo', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(20000000),
    'total_amount_cents' => 20000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Movimento preco incorreto', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(2400000),
    'total_amount_cents' => 2400000,
    'primary_tier_id' => 'MOVIMENTO',
    'units' => [['tier_id' => 'MOVIMENTO', 'amount_cents' => 2400000]],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Experience preco incorreto', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(4900000),
    'total_amount_cents' => 4900000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 4900000]],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Carajas preco incorreto', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(9000000),
    'total_amount_cents' => 9000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [['tier_id' => 'CARAJAS', 'amount_cents' => 9000000, 'axis_id' => 'FORMACAO']],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Incentiva + Movimento', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(4300000),
    'total_amount_cents' => 4300000,
    'primary_tier_id' => 'INCENTIVA',
    'units' => [
        ['tier_id' => 'INCENTIVA', 'amount_cents' => 1800000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Apresenta + tier', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(54059248),
    'total_amount_cents' => 54059248,
    'primary_tier_id' => 'APRESENTA',
    'units' => [
        ['tier_id' => 'APRESENTA', 'amount_cents' => 51559248],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT Apresenta preco incorreto', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(50000000),
    'total_amount_cents' => 50000000,
    'primary_tier_id' => 'APRESENTA',
    'units' => [['tier_id' => 'APRESENTA', 'amount_cents' => 50000000]],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT total != soma units', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(7500000),
    'total_amount_cents' => 8000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT 5 Carajas', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(50000000),
    'total_amount_cents' => 50000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MOSTRA'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'ECONOMIA_CRIATIVA'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'MEMORIAS'],
        ['tier_id' => 'CARAJAS', 'amount_cents' => 10000000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT 3 Experience', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(15000000),
    'total_amount_cents' => 15000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
        ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT 5 Movimento', [
    'result_type' => 'COMPOSITION',
    'investment' => definedAmount(12500000),
    'total_amount_cents' => 12500000,
    'primary_tier_id' => 'MOVIMENTO',
    'units' => [
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
        ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000],
    ],
    'availability' => 'NOT_CHECKED',
], false);

assertValid('REJECT availability != NOT_CHECKED', [
    'result_type' => 'SINGLE',
    'investment' => definedAmount(5000000),
    'total_amount_cents' => 5000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000]],
    'availability' => 'AVAILABLE',
], false);

echo "\n== 22.3-A Snapshot V2 — HTTP intake / round-trip ==\n";

$colCheck = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'sponsorship_simulations'
        AND COLUMN_NAME = 'scenario_result_snapshot'"
)->fetchColumn();
pass('migration scenario_result_snapshot presente', (int) $colCheck > 0);

$casesHttp = [
    '38k' => [
        'result_type' => 'NO_EXACT_COMPOSITION',
        'investment' => definedAmount(3800000),
        'near_options' => [
            ['tier_id' => 'MOVIMENTO', 'total_amount_cents' => 2500000],
            ['tier_id' => 'EXPERIENCE', 'total_amount_cents' => 5000000],
        ],
        'availability' => 'NOT_CHECKED',
    ],
    '75k' => [
        'result_type' => 'COMPOSITION',
        'investment' => definedAmount(7500000),
        'total_amount_cents' => 7500000,
        'primary_tier_id' => 'EXPERIENCE',
        'units' => [
            ['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000, 'axis_id' => null],
            ['tier_id' => 'MOVIMENTO', 'amount_cents' => 2500000, 'axis_id' => null],
        ],
        'availability' => 'NOT_CHECKED',
    ],
    '300k' => $scenario300,
    'OPEN' => [
        'result_type' => 'STRATEGIC_ONLY',
        'investment' => ['status' => 'OPEN', 'min_cents' => null, 'max_cents' => null, 'currency' => 'BRL'],
        'strategic_options' => [
            ['tier_id' => 'CARAJAS', 'axis_id' => 'FORMACAO'],
            ['tier_id' => 'EXPERIENCE'],
        ],
        'availability' => 'NOT_CHECKED',
    ],
];

foreach ($casesHttp as $name => $scenario) {
    $payload = envelope(baseSim($scenario));
    $r = postLead($payload);
    $body = json_decode($r['body'], true) ?: [];
    $okCreate = $r['code'] === 201 && !empty($body['success']) && empty($body['idempotent']);
    pass("HTTP create {$name}", $okCreate, "code={$r['code']} body=" . substr($r['body'], 0, 200));

    if (!$okCreate) {
        continue;
    }

    $simId = (int) ($body['simulation_id'] ?? 0);
    $row = (new SponsorshipSimulation())->findById($simId);
    $stored = is_array($row['scenario_result_snapshot'] ?? null) ? $row['scenario_result_snapshot'] : [];
    pass("round-trip {$name} result_type", ($stored['result_type'] ?? null) === $scenario['result_type']);
    pass("round-trip {$name} availability NOT_CHECKED", ($stored['availability'] ?? null) === 'NOT_CHECKED'
        && ($row['availability_status'] ?? null) === 'NOT_CHECKED');

    if ($name === '300k') {
        $units = $stored['units'] ?? [];
        pass('round-trip 300k 3 units', is_array($units) && count($units) === 3);
        pass('round-trip 300k total 30000000', (int) ($stored['total_amount_cents'] ?? 0) === 30000000);
        $axes = array_map(static fn ($u) => $u['axis_id'] ?? null, $units);
        pass('round-trip 300k eixos preservados', $axes === ['FORMACAO', 'MOSTRA', 'ECONOMIA_CRIATIVA']);
        pass('round-trip 300k primary_tier nao colapsa sozinho', ($row['primary_tier_ref'] ?? '') === 'CARAJAS'
            && count($units) === 3);
    }
    if ($name === '75k') {
        $units = $stored['units'] ?? [];
        pass('round-trip 75k 2 units', is_array($units) && count($units) === 2);
        $tiers = array_map(static fn ($u) => $u['tier_id'] ?? null, $units);
        pass('round-trip 75k E+M', $tiers === ['EXPERIENCE', 'MOVIMENTO']);
    }
    if ($name === '38k') {
        pass('round-trip 38k sem primary selecionado', empty($stored['primary_tier_id'])
            && ($row['primary_tier_ref'] ?? '') === 'NONE');
        $near = $stored['near_options'] ?? [];
        pass('round-trip 38k near_options', is_array($near) && count($near) === 2
            && ($near[0]['tier_id'] ?? '') === 'MOVIMENTO'
            && (int) ($near[0]['total_amount_cents'] ?? 0) === 2500000
            && ($near[1]['tier_id'] ?? '') === 'EXPERIENCE'
            && (int) ($near[1]['total_amount_cents'] ?? 0) === 5000000);
    }
    if ($name === 'OPEN') {
        pass('round-trip OPEN sem total inventado', !array_key_exists('total_amount_cents', $stored)
            || $stored['total_amount_cents'] === null);
        pass('round-trip OPEN strategic_options', is_array($stored['strategic_options'] ?? null)
            && count($stored['strategic_options']) === 2);
    }

    // idempotency same body
    $r2 = postLead($payload);
    $b2 = json_decode($r2['body'], true) ?: [];
    pass("idempotent replay {$name}", $r2['code'] === 201 && !empty($b2['idempotent']));
}

// conflict on material change
$uuidConflict = uuidV4();
$payloadA = envelope(baseSim([
    'result_type' => 'SINGLE',
    'investment' => definedAmount(5000000),
    'total_amount_cents' => 5000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000]],
    'availability' => 'NOT_CHECKED',
]), $uuidConflict);
$rA = postLead($payloadA);
pass('HTTP create conflict base', $rA['code'] === 201);

$payloadB = $payloadA;
$payloadB['sponsorship_simulation']['scenario_result']['units'][0]['amount_cents'] = 2500000;
$payloadB['sponsorship_simulation']['scenario_result']['total_amount_cents'] = 2500000;
$payloadB['sponsorship_simulation']['scenario_result']['primary_tier_id'] = 'MOVIMENTO';
$payloadB['sponsorship_simulation']['scenario_result']['units'][0]['tier_id'] = 'MOVIMENTO';
$payloadB['sponsorship_simulation']['scenario_result']['investment'] = definedAmount(2500000);
$payloadB['sponsorship_simulation']['briefing']['investment'] = definedAmount(2500000);
$rB = postLead($payloadB);
pass('mesmo UUID unit alterada → 409', $rB['code'] === 409);

$uuidAxis = uuidV4();
$payloadC = envelope(baseSim([
    'result_type' => 'SINGLE',
    'investment' => definedAmount(10000000),
    'total_amount_cents' => 10000000,
    'primary_tier_id' => 'CARAJAS',
    'units' => [['tier_id' => 'CARAJAS', 'amount_cents' => 10000000, 'axis_id' => 'FORMACAO']],
    'availability' => 'NOT_CHECKED',
]), $uuidAxis);
postLead($payloadC);
$payloadD = $payloadC;
$payloadD['sponsorship_simulation']['scenario_result']['units'][0]['axis_id'] = 'MOSTRA';
$rD = postLead($payloadD);
pass('mesmo UUID axis alterado → 409', $rD['code'] === 409);

$uuidNear = uuidV4();
$payloadE = envelope(baseSim([
    'result_type' => 'NO_EXACT_COMPOSITION',
    'investment' => definedAmount(3800000),
    'near_options' => [
        ['tier_id' => 'MOVIMENTO', 'total_amount_cents' => 2500000],
        ['tier_id' => 'EXPERIENCE', 'total_amount_cents' => 5000000],
    ],
    'availability' => 'NOT_CHECKED',
]), $uuidNear);
postLead($payloadE);
$payloadF = $payloadE;
$payloadF['sponsorship_simulation']['scenario_result']['near_options'] = [
    ['tier_id' => 'EXPERIENCE', 'total_amount_cents' => 5000000],
    ['tier_id' => 'MOVIMENTO', 'total_amount_cents' => 2500000],
];
$rF = postLead($payloadF);
pass('mesmo UUID near_options alterado → 409', $rF['code'] === 409);

// hard deny quota_id
$deny = envelope(baseSim([
    'result_type' => 'SINGLE',
    'investment' => definedAmount(5000000),
    'total_amount_cents' => 5000000,
    'primary_tier_id' => 'EXPERIENCE',
    'units' => [['tier_id' => 'EXPERIENCE', 'amount_cents' => 5000000]],
    'availability' => 'NOT_CHECKED',
]));
$deny['quota_id'] = 99;
$rDeny = postLead($deny);
pass('quota_id intake publica → HARD DENY', $rDeny['code'] === 422);

$passN = count(array_filter($results, static fn ($r) => $r['ok']));
$failN = count($results) - $passN;
echo "\n== RESUMO 22.3-A ==\n";
echo "PASS: {$passN}\nFAIL: {$failN}\nTOTAL: " . count($results) . "\n";
exit($failN > 0 ? 1 : 0);
