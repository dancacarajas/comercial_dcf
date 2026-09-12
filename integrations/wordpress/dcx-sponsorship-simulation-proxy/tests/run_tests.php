<?php
/**
 * Harness CLI Etapa 22.1 — sem boot WordPress.
 * Uso: php tests/run_tests.php
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

ini_set('error_log', sys_get_temp_dir() . '/dcx_ssp_test.log');

$pass = 0;
$fail = 0;
$lines = [];

function assert_true(bool $cond, string $id, string $detail = ''): void
{
    global $pass, $fail, $lines;
    if ($cond) {
        $pass++;
        $lines[] = "PASS $id";
    } else {
        $fail++;
        $lines[] = "FAIL $id" . ($detail !== '' ? " — $detail" : '');
    }
}

function load_fixture(): array
{
    $path = __DIR__ . '/fixtures/sponsorship_simulation_valid.v1.json';
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "fixture invalida\n");
        exit(1);
    }

    return $data;
}

function fresh_payload(): array
{
    $p = load_fixture();
    $p['submission_uuid'] = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
    $p['contact_consent'] = true;
    $p['sponsorship_simulation']['confirmed'] = true;
    $p['sponsorship_simulation']['confirmed_at'] = '2026-09-12T15:00:00Z';

    return $p;
}

function mock_crm(int $http, ?array $body): callable
{
    return static function (string $url, array $args) use ($http, $body): array {
        return [
            'http' => $http,
            'body' => $body,
            'raw' => $body === null ? '' : (string) json_encode($body),
            'headers_sent' => $args['headers'] ?? [],
        ];
    };
}

function set_path(array &$p, string $path, mixed $value): void
{
    $parts = explode('.', $path);
    $ref = &$p;
    foreach ($parts as $i => $part) {
        if ($i === count($parts) - 1) {
            $ref[$part] = $value;
        } else {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
    }
}

$token = 'test-secret-token';
$settings = [
    'enabled' => true,
    'trusted_proxies' => ['10.0.0.1', '10.0.0.0/8', '2001:db8::/32'],
    'timeout_seconds' => 12,
];
$server = [
    'REMOTE_ADDR' => '203.0.113.50',
    'HTTP_USER_AGENT' => 'QA-Agent/1.0',
];

$big = str_repeat('a', 65537);
$r = Dcx_Ssp_Pipeline::handle($big, $server, mock_crm(201, ['success' => true, 'idempotent' => false]), $token, $settings);
assert_true($r['http'] === 413 && $r['crm_called'] === false, 'T01');

$r = Dcx_Ssp_Pipeline::handle('{not-json', $server, mock_crm(201, ['success' => true, 'idempotent' => false]), $token, $settings);
assert_true($r['http'] === 400 && $r['crm_called'] === false, 'T02');

$p = fresh_payload();
$p['website_url'] = 'http://spam.test';
$r = Dcx_Ssp_Pipeline::handle(json_encode($p), $server, mock_crm(201, ['success' => true, 'idempotent' => false]), $token, $settings);
assert_true(
    $r['http'] === 201
    && $r['crm_called'] === false
    && ($r['body']['submission_uuid'] ?? '') === $p['submission_uuid']
    && ($r['body']['status'] ?? '') === 'RECEIVED',
    'T03'
);

$p = fresh_payload();
unset($p['submission_uuid']);
$p['website'] = 'x';
$r = Dcx_Ssp_Pipeline::handle(json_encode($p), $server, mock_crm(201, ['success' => true, 'idempotent' => false]), $token, $settings);
assert_true($r['http'] === 422 && $r['crm_called'] === false, 'T03b');

$p = fresh_payload();
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'T10');
$p['contact_consent'] = 1;
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T11a');
$p['contact_consent'] = 'sim';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T11b');
$p['contact_consent'] = 'on';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T11c');
$p['contact_consent'] = 'true';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T11d');

$p = fresh_payload();
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'T12');
$p['sponsorship_simulation']['confirmed'] = 'true';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T13');

$p = fresh_payload();
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'T14');
unset($p['sponsorship_simulation']['confirmed_at']);
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T15');
$p = fresh_payload();
$p['sponsorship_simulation']['confirmed_at'] = 'tomorrow';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T16');
$p = fresh_payload();
unset($p['sponsorship_simulation']['confirmed_at']);
$v = Dcx_Ssp_Validator::validate($p);
assert_true($v['ok'] === false && !isset($v['data']['sponsorship_simulation']['confirmed_at']), 'T17');

$p = fresh_payload();
$p['evil_field'] = 1;
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T20');
$p = fresh_payload();
$p['sponsorship_simulation']['reason_codes'] = ['X'];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T21');
$p = fresh_payload();
$p['sponsorship_simulation']['display_snapshot']['investment_label'] = 'x';
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'T22');
$p = fresh_payload();
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'T23');

$p = fresh_payload();
$p['project'] = ['pronac_number' => '999', 'edition_year' => 1999];
$p['form_name'] = 'Outro Nome';
$p['email'] = '  Maria.EXEMPLO@Example.COM ';
$p['utm_source'] = str_repeat('u', 200);
$v = Dcx_Ssp_Validator::validate($p);
$b = Dcx_Ssp_Envelope_B::build($v['data']);
assert_true($b['project']['pronac_number'] === '265397' && $b['project']['edition_year'] === 2026, 'T30');
assert_true($b['form_name'] === 'Simular Patrocinio', 'T31');
assert_true($b['submission_type'] === 'SPONSORSHIP_SIMULATION', 'T32');
assert_true($b['submission_version'] === '1.0.0' && $b['sponsorship_simulation']['catalog_version'] === '2026-V2.1', 'T33');
assert_true(
    $b['submission_uuid'] === $p['submission_uuid']
    && $b['sponsorship_simulation']['confirmed_at'] === $p['sponsorship_simulation']['confirmed_at'],
    'T34'
);
assert_true($b['email'] === 'maria.exemplo@example.com', 'T35');
assert_true(strlen((string) $b['utm_source']) === 120, 'T36');

$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '203.0.113.50', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'],
    ['10.0.0.1']
);
assert_true($ip['ok'] && $ip['ip'] === '203.0.113.50', 'T40');
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'],
    ['10.0.0.1']
);
assert_true($ip['ok'] && $ip['ip'] === '203.0.113.10', 'T41');
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip'],
    ['10.0.0.1']
);
assert_true($ip['ok'] === false, 'T42');
$captured = [];
$r = Dcx_Ssp_Pipeline::handle(
    json_encode(fresh_payload()),
    ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'UA'],
    static function ($url, $args) use (&$captured) {
        $captured = $args['headers'];
        return ['http' => 201, 'body' => ['success' => true, 'idempotent' => false], 'raw' => '', 'headers_sent' => $args['headers']];
    },
    $token,
    $settings
);
assert_true(($captured['X-Forwarded-For'] ?? '') === '203.0.113.10', 'T43');
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 198.51.100.20, 10.0.0.5'],
    ['10.0.0.0/8']
);
assert_true($ip['ok'] && $ip['ip'] === '198.51.100.20', 'T44', (string) ($ip['ip'] ?? ''));
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.99, 203.0.113.10'],
    ['10.0.0.1']
);
assert_true($ip['ok'] && $ip['ip'] === '203.0.113.10', 'T45');
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '2001:db8::1', 'HTTP_X_FORWARDED_FOR' => '2001:db9::2, 2001:db8::1'],
    ['2001:db8::/32']
);
assert_true($ip['ok'] && $ip['ip'] === '2001:db9::2', 'T46', (string) ($ip['ip'] ?? ''));
$ip = Dcx_Ssp_Client_Ip::resolve(
    ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_FOR' => 'garbage, 203.0.113.10'],
    ['10.0.0.1']
);
assert_true($ip['ok'] && $ip['ip'] === '203.0.113.10', 'T47');

assert_true(!Dcx_Ssp_Crm_Transport::isAllowedEndpoint('http://evil.example/api/leads/site'), 'T50');
$pol = Dcx_Ssp_Crm_Transport::policy(12);
assert_true($pol['sslverify'] === true, 'T51');
assert_true($pol['redirection'] === 0, 'T52');
assert_true($pol['timeout'] === 12, 'T53');
assert_true($pol['endpoint'] === 'https://comercial.dancacarajas.com.br/api/leads/site', 'T57');

$hdr = [];
$r = Dcx_Ssp_Pipeline::handle(
    json_encode(fresh_payload()),
    $server,
    static function ($url, $args) use (&$hdr) {
        $hdr = $args['headers'];
        return ['http' => 201, 'body' => ['success' => true, 'idempotent' => false, 'lead_id' => 9, 'simulation_id' => 1], 'raw' => '', 'headers_sent' => $args['headers']];
    },
    $token,
    $settings
);
assert_true(($hdr['X-DCF-Lead-Token'] ?? '') === $token, 'T54');
$blob = json_encode($r['body']) . json_encode($r['logs'] ?? []);
assert_true(!str_contains($blob, $token) && !isset($r['body']['lead_id']) && !isset($r['body']['simulation_id']), 'T55');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => true, 'idempotent' => false]), '', $settings);
assert_true($r['http'] === 503 && $r['crm_called'] === false, 'T56');

$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => true, 'idempotent' => false, 'lead_id' => 1, 'simulation_id' => 2]), $token, $settings);
assert_true($r['http'] === 201 && $r['body']['status'] === 'RECEIVED' && !isset($r['body']['lead_id']), 'T60');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => true, 'idempotent' => true, 'lead_id' => 1, 'simulation_id' => 2]), $token, $settings);
assert_true($r['http'] === 201 && $r['body']['status'] === 'ALREADY_RECEIVED' && $r['body']['idempotent'] === true, 'T61');
$uuid = fresh_payload()['submission_uuid'];
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(409, ['success' => false]), $token, $settings);
assert_true($r['http'] === 409 && ($r['body']['submission_uuid'] ?? '') === $uuid, 'T62');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(403, ['message' => 'token inválido']), $token, $settings);
$blob = json_encode($r['body']);
assert_true(in_array($r['http'], [502, 503], true) && !str_contains(strtolower($blob), 'token'), 'T63');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(422, ['errors' => ['recommendation.primary.tier_id' => 'x', 'email' => 'bad']]), $token, $settings);
assert_true($r['http'] === 422 && isset($r['body']['errors']['email']) && !isset($r['body']['errors']['recommendation.primary.tier_id']), 'T64');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(503, null), $token, $settings);
assert_true(in_array($r['http'], [502, 503], true), 'T65');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, null), $token, $settings);
assert_true($r['http'] === 502, 'T66');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => false, 'idempotent' => false]), $token, $settings);
assert_true($r['http'] === 502, 'T67');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => true, 'idempotent' => 'false']), $token, $settings);
assert_true($r['http'] === 502, 'T68');
$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(204, ['success' => true, 'idempotent' => false]), $token, $settings);
assert_true($r['http'] === 502, 'T69');

$r = Dcx_Ssp_Pipeline::handle(json_encode(fresh_payload()), $server, mock_crm(201, ['success' => true, 'idempotent' => false]), $token, $settings);
$logJson = json_encode($r['logs'] ?? []);
assert_true(
    str_contains($logJson, 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee')
    && !str_contains($logJson, 'maria.exemplo@example.com')
    && !str_contains($logJson, $token),
    'T70'
);
$p = fresh_payload();
$r = Dcx_Ssp_Pipeline::handle(json_encode($p), $server, mock_crm(201, ['success' => true, 'idempotent' => false, 'lead_id' => 3, 'simulation_id' => 1]), $token, $settings);
assert_true(
    $r['http'] === 201
    && $r['body']['status'] === 'RECEIVED'
    && ($r['envelope_b']['project']['pronac_number'] ?? '') === '265397'
    && ($r['envelope_b']['form_name'] ?? '') === 'Simular Patrocinio',
    'T80'
);

$mutations = [
    ['path' => 'email', 'value' => 'not-an-email', 'id' => 'M_email'],
    ['path' => 'state', 'value' => 'PARA', 'id' => 'M_state'],
    ['path' => 'submission_uuid', 'value' => 'not-a-uuid', 'id' => 'M_uuid_bad'],
    ['path' => 'submission_uuid', 'value' => 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE', 'id' => 'M_uuid_case'],
    ['path' => 'name', 'value' => 'A', 'id' => 'M_name_short'],
    ['path' => 'company_name', 'value' => str_repeat('C', 200), 'id' => 'M_company_long'],
    ['path' => 'message', 'value' => str_repeat('m', 5001), 'id' => 'M_message_long'],
    ['path' => 'contact_consent', 'value' => null, 'id' => 'M_consent_missing'],
    ['path' => 'evil', 'value' => 1, 'id' => 'M_addl_top'],
    ['path' => 'sponsorship_simulation.passport', 'value' => ['x' => 1], 'id' => 'M_passport'],
    ['path' => 'sponsorship_simulation.reason_codes', 'value' => ['A'], 'id' => 'M_reason'],
    ['path' => 'sponsorship_simulation.internal_score', 'value' => 9, 'id' => 'M_score'],
    ['path' => 'sponsorship_simulation.recommendation.state', 'value' => 'PENDING', 'id' => 'M_state_rec'],
    ['path' => 'sponsorship_simulation.recommendation.primary.availability', 'value' => 'AVAILABLE', 'id' => 'M_avail'],
    ['path' => 'sponsorship_simulation.recommendation.primary.tier_id', 'value' => 'bad id', 'id' => 'M_tier_regex'],
    ['path' => 'sponsorship_simulation.briefing.objectives', 'value' => array_fill(0, 9, 'LEGACY'), 'id' => 'M_obj_card'],
    ['path' => 'sponsorship_simulation.recommendation.alternatives', 'value' => array_fill(0, 6, [
        'tier_id' => 'MOVIMENTO', 'axis_id' => null, 'activation_id' => null, 'property_id' => null,
        'fit_level' => 'LOW', 'availability' => 'NOT_CHECKED',
    ]), 'id' => 'M_alts'],
    ['path' => 'sponsorship_simulation.briefing.investment.status', 'value' => 'WEIRD', 'id' => 'M_inv'],
    ['path' => 'sponsorship_simulation.briefing.investment.currency', 'value' => 'USD', 'id' => 'M_currency'],
    ['path' => 'sponsorship_simulation.confirmed_at', 'value' => '2026-09-12', 'id' => 'M_confirmed_at'],
    ['path' => 'sponsorship_simulation.confirmed', 'value' => 1, 'id' => 'M_confirmed_coerced'],
    ['path' => 'sponsorship_simulation.display_snapshot.fit_label', 'value' => 'x', 'id' => 'M_display'],
    ['path' => 'sponsorship_simulation.interests.tier_interest', 'value' => ['bad id'], 'id' => 'M_interest_ref'],
    ['path' => 'sponsorship_simulation.extra_nested', 'value' => 1, 'id' => 'M_addl_nested'],
    ['path' => 'sponsorship_simulation.briefing.__unknown', 'value' => 1, 'id' => 'M_addl_briefing'],
    ['path' => 'sponsorship_simulation.briefing.investment.__unknown', 'value' => 1, 'id' => 'M_addl_investment'],
    ['path' => 'sponsorship_simulation.recommendation.__unknown', 'value' => 1, 'id' => 'M_addl_recommendation'],
    ['path' => 'sponsorship_simulation.interests.tier_interest', 'value' => ['A'], 'id' => 'M_ref_one_char_interest'],
    ['path' => 'sponsorship_simulation.recommendation.primary.tier_id', 'value' => 'A', 'id' => 'M_ref_one_char_primary'],
];

$schemaPass = 0;
foreach ($mutations as $m) {
    $p = fresh_payload();
    set_path($p, $m['path'], $m['value']);
    $ok = Dcx_Ssp_Validator::validate($p)['ok'] === false;
    assert_true($ok, $m['id']);
    if ($ok) {
        $schemaPass++;
    }
}

// Nested unknown keys must not leak into Envelope B even if somehow validated.
foreach (['M_addl_briefing' => 'sponsorship_simulation.briefing.__unknown', 'M_addl_investment' => 'sponsorship_simulation.briefing.investment.__unknown', 'M_addl_recommendation' => 'sponsorship_simulation.recommendation.__unknown'] as $id => $path) {
    $p = fresh_payload();
    set_path($p, $path, 1);
    $v = Dcx_Ssp_Validator::validate($p);
    assert_true($v['ok'] === false, $id . '_no_B');
}

// Investment state machine negatives
$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'OPEN', 'min' => 10000, 'max' => 20000, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'M_inv_open_with_amount');

$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'UNDEFINED', 'min' => 10000, 'max' => 10000, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === false, 'M_inv_undefined_with_amount');

// Investment state machine positives
$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'OPEN', 'min' => null, 'max' => null, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'M_inv_open_ok');

$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'UNDEFINED', 'min' => null, 'max' => null, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'M_inv_undefined_ok');

$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'DEFINED_AMOUNT', 'min' => 50000, 'max' => 50000, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'M_inv_amount_ok');

$p = fresh_payload();
$p['sponsorship_simulation']['briefing']['investment'] = [
    'status' => 'DEFINED_RANGE', 'min' => 10000, 'max' => 24999.99, 'currency' => 'BRL',
];
assert_true(Dcx_Ssp_Validator::validate($p)['ok'] === true, 'M_inv_range_ok');

assert_true(Dcx_Ssp_Validator::isCatalogRefId('AB') === true, 'M_ref_two_chars');
assert_true(Dcx_Ssp_Validator::isCatalogRefId('A') === false, 'M_ref_one_char_helper');

echo "=== ETAPA 22.1 PROXY TEST REPORT ===\n";
echo implode("\n", $lines) . "\n";
echo "----\nPASS=$pass FAIL=$fail TOTAL=" . ($pass + $fail) . "\n";
echo "SCHEMA_MUTATIONS PASS=$schemaPass TOTAL=" . count($mutations) . "\n";
exit($fail === 0 ? 0 : 1);
