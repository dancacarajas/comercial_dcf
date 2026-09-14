<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\SponsorshipSimulation;
use App\Services\Sponsorship\SnapshotV2Contract;
use App\Services\Sponsorship\SnapshotV2Validator;
use PDO;
use PDOException;
use Throwable;

/**
 * Intake Snapshot V2.0.0 (Etapa 22.3-A) — aditivo ao V1.
 */
final class SponsorshipLeadIntakeV2
{
    /**
     * @param array<string, mixed> $raw
     * @return array{
     *   ok:bool,
     *   http:int,
     *   message:string,
     *   lead_id:?int,
     *   simulation_id:?int,
     *   idempotent?:bool,
     *   errors?:array<string,string>
     * }
     */
    public function intake(array $raw, string $ip, string $userAgent, string $referrer): array
    {
        foreach (['quota_id', 'opportunity_id', 'lead_id', 'simulation_id', 'token', 'api_key', 'secret'] as $deny) {
            if (array_key_exists($deny, $raw)) {
                return $this->fail(422, 'Campo nao autorizado.', [$deny => 'hard-deny']);
            }
        }

        [$sanitized, $errors] = $this->sanitizeAndValidate($raw);
        if ($errors !== []) {
            return $this->fail(422, 'Contrato Snapshot V2 invalido.', $errors);
        }

        $uuid = strtolower(trim((string) $sanitized['submission_uuid']));
        $hash = $this->computeSnapshotHash($sanitized);

        $existing = (new SponsorshipSimulation())->findBySubmissionUuid($uuid);
        if ($existing !== null) {
            return $this->resolveIdempotentReplay($existing, $hash);
        }

        $project = $this->resolveProject($sanitized['project']);
        if ($project === null) {
            return $this->fail(422, 'Projeto/PRONAC nao encontrado.', [
                'project' => 'PRONAC/edicao nao resolvidos no CRM.',
            ]);
        }
        $projectId = (int) $project['id'];

        $sim = $sanitized['sponsorship_simulation'];
        $scenario = $sim['scenario_result'];
        $resultType = (string) $scenario['result_type'];

        $leadModel = new Lead();
        $mapped = $leadModel->mapIncoming($sanitized);
        $mapped['status'] = 'novo';
        $mapped['submission_type'] = SponsorshipLeadIntake::SUBMISSION_TYPE;
        $mapped['incentive_project_id'] = $projectId;
        $mapped['ip_address'] = $ip;
        $mapped['user_agent'] = mb_substr($userAgent, 0, 512);
        if (empty($mapped['origin_page'])) {
            $mapped['origin_page'] = (string) ($sanitized['origin_page'] ?? 'patrocinio/seja-patrocinador');
        }
        if (empty($mapped['source_url'])) {
            $mapped['source_url'] = (string) ($sanitized['source_url'] ?? $referrer);
        }
        if (empty($mapped['form_id'])) {
            $mapped['form_id'] = (string) ($sanitized['form_id'] ?? 'dcx-sponsorship-simulation-v2');
        }
        if (empty($mapped['form_name'])) {
            $mapped['form_name'] = (string) ($sanitized['form_name'] ?? 'Simular Patrocinio V2');
        }
        $mapped['interest'] = 'Snapshot V2 · ' . $resultType;

        $leadErrors = $leadModel->validate($mapped, 'api');
        if ($leadErrors !== []) {
            return $this->fail(422, 'Dados do lead invalidos.', $leadErrors);
        }
        if ((int) ($mapped['contact_consent'] ?? 0) !== 1) {
            return $this->fail(422, 'Consentimento obrigatorio.', [
                'contact_consent' => 'contact_consent deve ser verdadeiro.',
            ]);
        }

        $confirmedAt = (string) $sim['confirmed_at'];
        $inv = $scenario['investment'];
        $primaryTier = (string) ($scenario['primary_tier_id'] ?? '');
        $primaryAxis = null;
        if ($resultType === 'SINGLE'
            && !empty($scenario['units'][0]['axis_id'])
            && is_string($scenario['units'][0]['axis_id'])
        ) {
            $primaryAxis = (string) $scenario['units'][0]['axis_id'];
        }

        // Colunas legado NOT NULL: mapear cents→BRL para display; recommendation stub.
        $minBrl = isset($inv['min_cents']) && $inv['min_cents'] !== null ? ((int) $inv['min_cents']) / 100 : null;
        $maxBrl = isset($inv['max_cents']) && $inv['max_cents'] !== null ? ((int) $inv['max_cents']) / 100 : null;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $leadId = $leadModel->create($mapped);
            $simId = (new SponsorshipSimulation())->create([
                'lead_id' => (int) $leadId,
                'incentive_project_id' => $projectId,
                'submission_uuid' => $uuid,
                'submission_version' => (string) $sanitized['submission_version'],
                'snapshot_version' => SnapshotV2Contract::SNAPSHOT_VERSION,
                'catalog_version' => SnapshotV2Contract::CATALOG_VERSION,
                'briefing_schema_version' => SnapshotV2Contract::BRIEFING_SCHEMA_VERSION,
                'policy_version' => (string) $sim['policy_version'],
                'engine_version' => (string) $sim['engine_version'],
                'presenter_version' => isset($sim['presenter_version']) ? (string) $sim['presenter_version'] : null,
                'confirmed' => 1,
                'confirmed_at' => $confirmedAt,
                'investment_status' => strtoupper((string) $inv['status']),
                'investment_min' => $minBrl,
                'investment_max' => $maxBrl,
                'currency' => 'BRL',
                'primary_tier_ref' => $primaryTier !== '' ? $primaryTier : 'NONE',
                'primary_axis_ref' => $primaryAxis,
                'primary_activation_ref' => null,
                'primary_property_ref' => null,
                'fit_level' => 'NOT_CALCULATED',
                'availability_status' => 'NOT_CHECKED',
                'briefing_snapshot' => $sim['briefing'],
                'interests_snapshot' => $sim['interests'],
                'recommendation_snapshot' => [
                    'state' => 'V2_SCENARIO',
                    'scenario_version' => SnapshotV2Contract::SCENARIO_VERSION,
                ],
                'display_snapshot' => $sim['display_snapshot'] ?? [],
                'scenario_result_snapshot' => $scenario,
                'result_type' => $resultType,
                'snapshot_hash' => $hash,
            ]);

            (new ActivityLog())->record(
                'sponsorship_simulation_v2_received',
                null,
                'sponsorship_simulation',
                (int) $simId
            );

            $pdo->commit();

            return [
                'ok' => true,
                'http' => 201,
                'message' => 'Lead e simulacao V2 registrados com sucesso.',
                'lead_id' => (int) $leadId,
                'simulation_id' => (int) $simId,
                'idempotent' => false,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($this->isDuplicateKey($e)) {
                $race = (new SponsorshipSimulation())->findBySubmissionUuid($uuid);
                if ($race !== null) {
                    return $this->resolveIdempotentReplay($race, $hash);
                }
            }
            error_log('[SponsorshipLeadIntakeV2] ' . $e->getMessage());

            return $this->fail(500, 'Erro ao registrar simulacao V2.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SponsorshipLeadIntakeV2] ' . $e->getMessage());

            return $this->fail(500, 'Erro ao registrar simulacao V2.');
        }
    }

    /**
     * @param array<string, mixed> $raw
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function sanitizeAndValidate(array $raw): array
    {
        $errors = [];
        $top = [
            'submission_type', 'submission_version', 'submission_uuid',
            'name', 'company_name', 'role_title', 'email', 'whatsapp', 'city', 'state', 'segment',
            'contact_consent', 'message', 'origin_page', 'source_url', 'form_id', 'form_name',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'project', 'sponsorship_simulation', 'website', 'website_url',
        ];
        foreach (array_keys($raw) as $k) {
            if (!in_array((string) $k, $top, true)) {
                $errors[(string) $k] = 'Campo top-level desconhecido.';
            }
        }

        if (($raw['submission_type'] ?? null) !== SponsorshipLeadIntake::SUBMISSION_TYPE) {
            $errors['submission_type'] = 'submission_type deve ser SPONSORSHIP_SIMULATION.';
        }
        if (($raw['submission_version'] ?? null) !== '1.0.0') {
            $errors['submission_version'] = 'submission_version deve ser 1.0.0.';
        }
        $uuid = strtolower(trim((string) ($raw['submission_uuid'] ?? '')));
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
            $errors['submission_uuid'] = 'submission_uuid UUID v4 lowercase invalido.';
        }

        $name = trim((string) ($raw['name'] ?? ''));
        $company = trim((string) ($raw['company_name'] ?? ''));
        $email = strtolower(trim((string) ($raw['email'] ?? '')));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180) {
            $errors['name'] = 'name invalido.';
        }
        if (mb_strlen($company) < 2 || mb_strlen($company) > 180) {
            $errors['company_name'] = 'company_name invalido.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'email invalido.';
        }
        if (($raw['contact_consent'] ?? null) !== true) {
            $errors['contact_consent'] = 'contact_consent deve ser true.';
        }

        $project = is_array($raw['project'] ?? null) ? $raw['project'] : [];
        $simRaw = is_array($raw['sponsorship_simulation'] ?? null) ? $raw['sponsorship_simulation'] : [];
        [$simOut, $simErr] = (new SnapshotV2Validator())->validate($simRaw);
        $errors = array_merge($errors, $simErr);

        if ($errors !== []) {
            return [[], $errors];
        }

        $out = [
            'submission_type' => SponsorshipLeadIntake::SUBMISSION_TYPE,
            'submission_version' => '1.0.0',
            'submission_uuid' => $uuid,
            'name' => $name,
            'company_name' => $company,
            'email' => $email,
            'contact_consent' => true,
            'project' => [
                'pronac_number' => preg_replace('/\D+/', '', (string) ($project['pronac_number'] ?? '265397')) ?: '265397',
                'edition_year' => (int) ($project['edition_year'] ?? 2026),
            ],
            'sponsorship_simulation' => $simOut,
            'origin_page' => trim((string) ($raw['origin_page'] ?? 'patrocinio/seja-patrocinador')),
            'source_url' => trim((string) ($raw['source_url'] ?? '')),
            'form_id' => trim((string) ($raw['form_id'] ?? 'dcx-sponsorship-simulation-v2')),
            'form_name' => trim((string) ($raw['form_name'] ?? 'Simular Patrocinio V2')),
        ];
        foreach (['role_title', 'whatsapp', 'city', 'segment', 'message'] as $opt) {
            if (isset($raw[$opt]) && $raw[$opt] !== '' && $raw[$opt] !== null) {
                $out[$opt] = is_string($raw[$opt]) ? trim($raw[$opt]) : $raw[$opt];
            }
        }
        if (isset($raw['state']) && $raw['state'] !== '' && $raw['state'] !== null) {
            $out['state'] = strtoupper(trim((string) $raw['state']));
        }
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm) {
            if (array_key_exists($utm, $raw)) {
                $out[$utm] = $raw[$utm] === null ? null : mb_substr(trim((string) $raw[$utm]), 0, 120);
            }
        }

        return [$out, []];
    }

    /**
     * @param array<string, mixed> $sanitized
     */
    public function computeSnapshotHash(array $sanitized): string
    {
        $data = $sanitized;
        $sim = $data['sponsorship_simulation'] ?? [];
        if (is_array($sim)) {
            $briefing = $sim['briefing'] ?? [];
            if (is_array($briefing)) {
                foreach (['objectives', 'audiences', 'proof_needs'] as $key) {
                    if (isset($briefing[$key]) && is_array($briefing[$key])) {
                        $vals = array_values(array_map(static fn ($v) => strtoupper(trim((string) $v)), $briefing[$key]));
                        sort($vals, SORT_STRING);
                        $briefing[$key] = $vals;
                    }
                }
                $sim['briefing'] = $briefing;
            }
            $interests = $sim['interests'] ?? [];
            if (is_array($interests)) {
                foreach (['tier_interest', 'axis_interest', 'activation_interest', 'property_interest', 'asset_interest'] as $key) {
                    if (isset($interests[$key]) && is_array($interests[$key])) {
                        $vals = array_values(array_map(static fn ($v) => strtoupper(trim((string) $v)), $interests[$key]));
                        sort($vals, SORT_STRING);
                        $interests[$key] = $vals;
                    }
                }
                $sim['interests'] = $interests;
            }
            // NÃO reordenar units / near_options / strategic_options
            $data['sponsorship_simulation'] = $sim;
        }
        $canonical = $this->sortKeysRecursive($data);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $json === false ? '{}' : $json);
    }

    private function sortKeysRecursive(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) {
            return array_map(fn ($v) => $this->sortKeysRecursive($v), $data);
        }
        ksort($data);
        foreach ($data as $k => $v) {
            $data[$k] = $this->sortKeysRecursive($v);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $existing
     * @return array{ok:bool,http:int,message:string,lead_id:?int,simulation_id:?int,idempotent?:bool}
     */
    private function resolveIdempotentReplay(array $existing, string $hash): array
    {
        $existingHash = (string) ($existing['snapshot_hash'] ?? '');
        if (!hash_equals($existingHash, $hash)) {
            return $this->fail(409, 'Conflito de idempotencia: mesmo UUID com payload diferente.');
        }

        return [
            'ok' => true,
            'http' => 201,
            'message' => 'Lead ja registrado (replay idempotente).',
            'lead_id' => (int) ($existing['lead_id'] ?? 0),
            'simulation_id' => (int) ($existing['id'] ?? 0),
            'idempotent' => true,
        ];
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>|null
     */
    private function resolveProject(array $project): ?array
    {
        $pronac = preg_replace('/\D+/', '', (string) ($project['pronac_number'] ?? '')) ?? '';
        $year = (int) ($project['edition_year'] ?? 0);
        $sql = 'SELECT `id`, `project_name`, `edition_year`, `pronac_number`
                  FROM `incentive_projects`
                 WHERE REPLACE(REPLACE(`pronac_number`, \'-\', \'\'), \' \', \'\') = :pronac
                   AND `archived_at` IS NULL';
        $params = ['pronac' => $pronac];
        if ($year > 0) {
            $sql .= ' AND `edition_year` = :year';
            $params['year'] = $year;
        }
        $sql .= ' ORDER BY `id` ASC LIMIT 1';
        $row = Database::run($sql, $params)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $msg = $e->getMessage();

        return $code === '1062' || str_contains($msg, 'Duplicate') || str_contains($msg, 'uniq_sponsorship_sim_uuid');
    }

    /**
     * @param array<string, string> $errors
     * @return array{ok:bool,http:int,message:string,lead_id:?int,simulation_id:?int,errors?:array<string,string>}
     */
    private function fail(int $http, string $message, array $errors = []): array
    {
        $out = [
            'ok' => false,
            'http' => $http,
            'message' => $message,
            'lead_id' => null,
            'simulation_id' => null,
        ];
        if ($errors !== []) {
            $out['errors'] = $errors;
        }

        return $out;
    }
}
