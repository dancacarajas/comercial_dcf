<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\SponsorshipSimulation;
use PDO;
use PDOException;
use Throwable;

/**
 * Intake de Lead originado de Simulação de Patrocínio (Guide) — Etapa 21.1.
 *
 * Valida contrato canônico, sanitiza por allowlist, cria Lead + Simulation
 * na mesma transação e aplica idempotência por submission_uuid + snapshot_hash.
 *
 * O CRM NÃO executa Recommendation Engine — apenas valida, armazena e apresenta.
 */
final class SponsorshipLeadIntake
{
    public const SUBMISSION_TYPE = 'SPONSORSHIP_SIMULATION';

    public const SUPPORTED_SUBMISSION_VERSIONS = ['1.0.0'];

    public const SUPPORTED_SNAPSHOT_VERSIONS = ['1.0.0'];

    public const SUPPORTED_BRIEFING_SCHEMA_VERSIONS = ['1.0.0'];

    public const EXPECTED_CATALOG_VERSION = '2026-V2.1';

    public const MAX_ALTERNATIVES = 5;

    public const MAX_MESSAGE_LENGTH = 5000;

    public const AREA_DECISIONS = [
        'MARKETING_COMMUNICATION',
        'ESG_IMPACT',
        'HR_PEOPLE',
        'INSTITUTIONAL_RELATIONS',
        'CLIENTS_STAKEHOLDERS',
        'EXECUTIVE_LEADERSHIP',
        'OTHER',
        'UNDEFINED',
    ];

    public const DEPTH_INTENTS = [
        'BELONG',
        'OWN_ACTION',
        'AUTHORIAL_CASE',
        'OWN_AXIS',
        'PRESENT_EDITION',
        'UNDEFINED',
    ];

    public const OBJECTIVES = [
        'INSTITUTIONAL_ASSOCIATION',
        'BRAND_PRESENCE',
        'CULTURAL_EXPERIENCE',
        'CONTENT_RELATIONSHIP',
        'ESG_CULTURAL_IMPACT',
        'EMPLOYEE_ENGAGEMENT',
        'CLIENT_STAKEHOLDER_RELATIONSHIP',
        'OWN_CULTURAL_TERRITORY',
        'EDITION_PROTAGONISM',
        'LEGACY',
        'UNDEFINED',
    ];

    public const AUDIENCES = [
        'GENERAL_PUBLIC',
        'COMMUNITY',
        'ARTISTS_GROUPS',
        'EMPLOYEES',
        'CLIENTS',
        'STAKEHOLDERS',
        'LEADERSHIP',
        'PARTNERS',
        'MULTIPLE',
        'UNDEFINED',
    ];

    public const PROOF_NEEDS = [
        'INSTITUTIONAL_DELIVERY',
        'PARTICIPATION_EVIDENCE',
        'CONTENT_CASE',
        'PORTAL',
        'AXIS_REPORT',
        'EXECUTIVE_REPORT',
        'AUTHORIZED_ARCHIVE',
        'LEGACY_CONTINUITY',
        'UNDEFINED',
    ];

    public const TIER_REFS = [
        'INCENTIVA',
        'MOVIMENTO',
        'EXPERIENCE',
        'CARAJAS',
        'APRESENTA',
    ];

    /** maxItems canônicos por array (contrato V1). */
    public const MAX_OBJECTIVES = 8;
    public const MAX_AUDIENCES = 8;
    public const MAX_PROOF_NEEDS = 10;
    public const MAX_TIER_INTEREST = 5;
    public const MAX_AXIS_INTEREST = 4;
    public const MAX_ACTIVATION_INTEREST = 8;
    public const MAX_PROPERTY_INTEREST = 8;
    public const MAX_ASSET_INTEREST = 15;

    /** Top-level allowlist (additionalProperties=false). */
    private const TOP_LEVEL_KEYS = [
        'submission_type',
        'submission_version',
        'submission_uuid',
        'name',
        'company_name',
        'role_title',
        'email',
        'whatsapp',
        'city',
        'state',
        'segment',
        'contact_consent',
        'message',
        'origin_page',
        'source_url',
        'form_id',
        'form_name',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'project',
        'sponsorship_simulation',
        // honeypot técnico — não persiste no envelope sanitizado
        'website',
        'website_url',
    ];

    private const PROJECT_KEYS = ['pronac_number', 'edition_year'];

    private const SIM_KEYS = [
        'snapshot_version',
        'engine_version',
        'policy_version',
        'catalog_version',
        'briefing_schema_version',
        'presenter_version',
        'confirmed',
        'confirmed_at',
        'briefing',
        'interests',
        'recommendation',
        'display_snapshot',
    ];

    private const BRIEFING_KEYS = [
        'area_decision',
        'objectives',
        'audiences',
        'depth_intent',
        'investment',
        'proof_needs',
    ];

    private const INTEREST_KEYS = [
        'tier_interest',
        'axis_interest',
        'activation_interest',
        'property_interest',
        'asset_interest',
    ];

    private const REC_KEYS = ['state', 'primary', 'alternatives'];

    private const CANDIDATE_KEYS = [
        'tier_id',
        'axis_id',
        'activation_id',
        'property_id',
        'fit_level',
        'availability',
    ];

    /** Apenas labels editoriais — nunca estado comercial (tier/fit/availability/investment). */
    private const DISPLAY_KEYS = [
        'objective_labels',
        'depth_label',
        'experience_labels',
        'proof_labels',
        'axis_label',
        'activation_label',
    ];

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
        [$sanitized, $contractErrors] = $this->sanitizeAndValidate($raw);
        if ($contractErrors !== []) {
            try {
                (new ActivityLog())->record(
                    'sponsorship_simulation_rejected_contract',
                    null,
                    'lead',
                    null
                );
            } catch (Throwable) {
            }

            return $this->fail(422, 'Contrato de simulação inválido.', $contractErrors);
        }

        $uuid = strtolower(trim((string) $sanitized['submission_uuid']));
        $hash = $this->computeSnapshotHash($sanitized);

        // Replay rápido (fora da transação). Corrida tratada no INSERT.
        $existing = (new SponsorshipSimulation())->findBySubmissionUuid($uuid);
        if ($existing !== null) {
            return $this->resolveIdempotentReplay($existing, $hash);
        }

        $project = $this->resolveProject($sanitized['project']);
        if ($project === null) {
            return $this->fail(422, 'Projeto/PRONAC não encontrado.', [
                'project' => 'PRONAC/edição não resolvidos no CRM.',
            ]);
        }

        $projectId = (int) $project['id'];
        $sim = $sanitized['sponsorship_simulation'];
        $primaryTier = (string) $sim['recommendation']['primary']['tier_id'];
        $catalogVersion = (string) $sim['catalog_version'];

        if (!$this->tierExistsInCatalog($projectId, $primaryTier, $catalogVersion)) {
            return $this->fail(422, 'Tier recomendado ausente no catálogo do projeto.', [
                'recommendation.primary.tier_id' => 'Cota canônica não encontrada ou arquivada.',
            ]);
        }

        foreach ($sim['recommendation']['alternatives'] as $i => $alt) {
            $altTier = (string) ($alt['tier_id'] ?? '');
            if ($altTier !== '' && !$this->tierExistsInCatalog($projectId, $altTier, $catalogVersion)) {
                return $this->fail(422, 'Alternativa com tier ausente no catálogo.', [
                    "recommendation.alternatives.$i.tier_id" => 'Cota canônica não encontrada.',
                ]);
            }
        }

        $leadModel = new Lead();
        $mapped = $leadModel->mapIncoming($sanitized);
        $mapped['status'] = 'novo';
        $mapped['ip_address'] = $ip;
        $mapped['user_agent'] = substr($userAgent, 0, 255);
        $mapped['referrer'] = substr($referrer, 0, 255);
        $mapped['integration_payload'] = $sanitized;
        $mapped['incentive_project_id'] = $projectId;
        $mapped['submission_type'] = self::SUBMISSION_TYPE;

        if (empty($mapped['origin_page'])) {
            $mapped['origin_page'] = (string) ($sanitized['origin_page'] ?? 'patrocinio/seja-patrocinador');
        }
        if (empty($mapped['form_id'])) {
            $mapped['form_id'] = (string) ($sanitized['form_id'] ?? 'dcx-sponsorship-simulation-v1');
        }
        if (empty($mapped['form_name'])) {
            $mapped['form_name'] = (string) ($sanitized['form_name'] ?? 'Simular Patrocinio');
        }

        $mapped['interest'] = $this->buildInterestHint($sim);

        $leadErrors = $leadModel->validate($mapped, 'api');
        if ($leadErrors !== []) {
            return $this->fail(422, 'Dados do lead inválidos.', $leadErrors);
        }

        if ((int) ($mapped['contact_consent'] ?? 0) !== 1) {
            return $this->fail(422, 'Consentimento obrigatório para simulação.', [
                'contact_consent' => 'contact_consent deve ser verdadeiro.',
            ]);
        }

        $investment = $sim['briefing']['investment'];
        $primary = $sim['recommendation']['primary'];
        // sanitizeAndValidate já normalizou para DATETIME UTC `Y-m-d H:i:s`.
        $confirmedAt = (string) ($sim['confirmed_at'] ?? '');
        if ($confirmedAt === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $confirmedAt)) {
            return $this->fail(422, 'confirmed_at ISO-8601 obrigatorio.', [
                'confirmed_at' => 'confirmed_at ISO-8601 obrigatorio.',
            ]);
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $leadId = $leadModel->create($mapped);
            $simId = (new SponsorshipSimulation())->create([
                'lead_id' => (int) $leadId,
                'incentive_project_id' => $projectId,
                'submission_uuid' => $uuid,
                'submission_version' => (string) $sanitized['submission_version'],
                'snapshot_version' => (string) $sim['snapshot_version'],
                'catalog_version' => $catalogVersion,
                'briefing_schema_version' => (string) $sim['briefing_schema_version'],
                'policy_version' => (string) $sim['policy_version'],
                'engine_version' => (string) $sim['engine_version'],
                'presenter_version' => isset($sim['presenter_version']) ? (string) $sim['presenter_version'] : null,
                'confirmed' => 1,
                'confirmed_at' => $confirmedAt,
                'investment_status' => strtoupper((string) $investment['status']),
                'investment_min' => $investment['min'] ?? null,
                'investment_max' => $investment['max'] ?? null,
                'currency' => strtoupper((string) ($investment['currency'] ?? 'BRL')),
                'primary_tier_ref' => $primaryTier,
                'primary_axis_ref' => $primary['axis_id'] ?? null,
                'primary_activation_ref' => $primary['activation_id'] ?? null,
                'primary_property_ref' => $primary['property_id'] ?? null,
                'fit_level' => strtoupper((string) $primary['fit_level']),
                'availability_status' => 'NOT_CHECKED',
                'briefing_snapshot' => $sim['briefing'],
                'interests_snapshot' => $sim['interests'],
                'recommendation_snapshot' => $sim['recommendation'],
                'display_snapshot' => $sim['display_snapshot'] ?? [],
                'snapshot_hash' => $hash,
            ]);

            (new ActivityLog())->record(
                'sponsorship_simulation_received',
                null,
                'sponsorship_simulation',
                (int) $simId
            );

            $pdo->commit();

            return [
                'ok' => true,
                'http' => 201,
                'message' => 'Lead e simulação registrados com sucesso.',
                'lead_id' => (int) $leadId,
                'simulation_id' => (int) $simId,
                'idempotent' => false,
            ];
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // UNIQUE(submission_uuid) — corrida idempotente
            if ($this->isDuplicateKey($e)) {
                $race = (new SponsorshipSimulation())->findBySubmissionUuid($uuid);
                if ($race !== null) {
                    return $this->resolveIdempotentReplay($race, $hash);
                }
            }
            error_log('[SponsorshipLeadIntake] ' . $e->getMessage());

            return $this->fail(500, 'Erro ao registrar simulação.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[SponsorshipLeadIntake] ' . $e->getMessage());

            return $this->fail(500, 'Erro ao registrar simulação.');
        }
    }

    /**
     * @param array<string, mixed> $existing
     * @return array{ok:bool,http:int,message:string,lead_id:?int,simulation_id:?int,idempotent?:bool,errors?:array<string,string>}
     */
    private function resolveIdempotentReplay(array $existing, string $hash): array
    {
        $existingHash = (string) ($existing['snapshot_hash'] ?? '');
        if (hash_equals($existingHash, $hash)) {
            try {
                (new ActivityLog())->record(
                    'sponsorship_simulation_idempotent_replay',
                    null,
                    'sponsorship_simulation',
                    (int) $existing['id']
                );
            } catch (Throwable) {
            }

            return [
                'ok' => true,
                'http' => 201,
                'message' => 'Lead ja registrado (replay idempotente).',
                'lead_id' => (int) $existing['lead_id'],
                'simulation_id' => (int) $existing['id'],
                'idempotent' => true,
            ];
        }

        return $this->fail(409, 'Conflito de idempotencia: mesmo UUID com payload diferente.');
    }

    /**
     * Sanitize + validate. Returns [sanitizedEnvelope, errors].
     *
     * @param array<string, mixed> $raw
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function sanitizeAndValidate(array $raw): array
    {
        $errors = [];

        foreach (array_keys($raw) as $key) {
            if (!in_array((string) $key, self::TOP_LEVEL_KEYS, true)) {
                $errors['contract.' . $key] = 'Campo top-level desconhecido.';
            }
        }
        if ($errors !== []) {
            return [[], $errors];
        }

        $type = strtoupper(trim((string) ($raw['submission_type'] ?? '')));
        if ($type !== self::SUBMISSION_TYPE) {
            $errors['submission_type'] = 'submission_type invalido.';
        }

        $uuid = strtolower(trim((string) ($raw['submission_uuid'] ?? '')));
        if (!$this->isValidUuid($uuid)) {
            $errors['submission_uuid'] = 'submission_uuid deve ser UUID valido.';
        }

        $subVer = (string) ($raw['submission_version'] ?? '');
        if (!in_array($subVer, self::SUPPORTED_SUBMISSION_VERSIONS, true)) {
            $errors['submission_version'] = 'submission_version nao suportada.';
        }

        if (!array_key_exists('contact_consent', $raw)
            || !(new Lead())->mapIncoming(['contact_consent' => $raw['contact_consent']])['contact_consent']
        ) {
            $errors['contact_consent'] = 'contact_consent deve ser verdadeiro.';
        }

        $name = trim((string) ($raw['name'] ?? ''));
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 180) {
            $errors['name'] = 'Nome obrigatorio (2-180).';
        }

        $company = trim((string) ($raw['company_name'] ?? ''));
        if ($company === '' || mb_strlen($company) < 2 || mb_strlen($company) > 180) {
            $errors['company_name'] = 'Empresa obrigatoria (2-180).';
        }

        $email = strtolower(trim((string) ($raw['email'] ?? '')));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'E-mail corporativo obrigatorio e valido.';
        }

        if (array_key_exists('message', $raw) && $raw['message'] !== null && $raw['message'] !== '') {
            if (!is_string($raw['message']) && !is_numeric($raw['message'])) {
                $errors['message'] = 'message deve ser texto.';
            } else {
                $msg = trim((string) $raw['message']);
                if (str_contains($msg, '<') || mb_strlen($msg) > self::MAX_MESSAGE_LENGTH) {
                    $errors['message'] = 'message invalida ou longa demais.';
                }
            }
        }

        $project = $raw['project'] ?? null;
        if (!is_array($project)) {
            $errors['project'] = 'project obrigatorio.';
        } else {
            foreach (array_keys($project) as $k) {
                if (!in_array((string) $k, self::PROJECT_KEYS, true)) {
                    $errors['project.' . $k] = 'Campo project desconhecido.';
                }
            }
            $pronac = preg_replace('/\D+/', '', (string) ($project['pronac_number'] ?? '')) ?? '';
            if ($pronac === '') {
                $errors['project.pronac_number'] = 'PRONAC obrigatorio.';
            }
        }

        $simRaw = $raw['sponsorship_simulation'] ?? null;
        if (!is_array($simRaw)) {
            $errors['sponsorship_simulation'] = 'sponsorship_simulation obrigatorio.';

            return [[], $errors];
        }

        foreach (array_keys($simRaw) as $k) {
            if (!in_array((string) $k, self::SIM_KEYS, true)) {
                $errors['sponsorship_simulation.' . $k] = 'Campo simulation desconhecido.';
            }
        }

        foreach (['snapshot_version', 'engine_version', 'policy_version', 'catalog_version', 'briefing_schema_version', 'confirmed', 'confirmed_at', 'briefing', 'interests', 'recommendation'] as $req) {
            if (!array_key_exists($req, $simRaw)) {
                $errors['sponsorship_simulation.' . $req] = 'Campo obrigatorio ausente.';
            }
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        $snapVer = (string) ($simRaw['snapshot_version'] ?? '');
        if (!in_array($snapVer, self::SUPPORTED_SNAPSHOT_VERSIONS, true)) {
            $errors['snapshot_version'] = 'snapshot_version nao suportada.';
        }
        if ((string) ($simRaw['catalog_version'] ?? '') !== self::EXPECTED_CATALOG_VERSION) {
            $errors['catalog_version'] = 'catalog_version deve ser ' . self::EXPECTED_CATALOG_VERSION . '.';
        }
        $briefingSchema = (string) ($simRaw['briefing_schema_version'] ?? '');
        if (!in_array($briefingSchema, self::SUPPORTED_BRIEFING_SCHEMA_VERSIONS, true)) {
            $errors['briefing_schema_version'] = 'briefing_schema_version deve ser 1.0.0.';
        }
        $engineVer = trim((string) ($simRaw['engine_version'] ?? ''));
        if ($engineVer === '' || mb_strlen($engineVer) > 20) {
            $errors['engine_version'] = 'engine_version obrigatorio (1-20).';
        }
        $policyVer = trim((string) ($simRaw['policy_version'] ?? ''));
        if ($policyVer === '' || mb_strlen($policyVer) > 20) {
            $errors['policy_version'] = 'policy_version obrigatorio (1-20).';
        }
        // confirmed: boolean true real (JSON). Rejeita "true", 1, "1", "false", etc.
        if (!array_key_exists('confirmed', $simRaw) || $simRaw['confirmed'] !== true) {
            $errors['confirmed'] = 'confirmed deve ser boolean true.';
        }
        $confirmedAt = $this->normalizeDateTime($simRaw['confirmed_at'] ?? null);
        if ($confirmedAt === null) {
            $errors['confirmed_at'] = 'confirmed_at ISO-8601 obrigatorio.';
        }

        $briefing = $simRaw['briefing'] ?? null;
        if (!is_array($briefing)) {
            $errors['briefing'] = 'briefing obrigatorio.';
        } else {
            foreach (array_keys($briefing) as $k) {
                if (!in_array((string) $k, self::BRIEFING_KEYS, true)) {
                    $errors['briefing.' . $k] = 'Campo briefing desconhecido.';
                }
            }
            // Cinco dimensoes obrigatorias do Guide (audiences NAO e obrigatorio)
            foreach (['area_decision', 'objectives', 'depth_intent', 'investment', 'proof_needs'] as $req) {
                if (!array_key_exists($req, $briefing)) {
                    $errors['briefing.' . $req] = 'Campo canonico ausente.';
                }
            }
            if (isset($briefing['area_decision'])) {
                $ad = strtoupper(trim((string) $briefing['area_decision']));
                if (!in_array($ad, self::AREA_DECISIONS, true)) {
                    $errors['briefing.area_decision'] = 'area_decision invalido.';
                }
            }
            if (isset($briefing['depth_intent'])) {
                $di = strtoupper(trim((string) $briefing['depth_intent']));
                if (!in_array($di, self::DEPTH_INTENTS, true)) {
                    $errors['briefing.depth_intent'] = 'depth_intent invalido.';
                }
            }
            $objErr = $this->validateEnumArray(
                $briefing['objectives'] ?? null,
                self::OBJECTIVES,
                'briefing.objectives',
                true,
                self::MAX_OBJECTIVES
            );
            $errors = array_merge($errors, $objErr);
            $proofErr = $this->validateEnumArray(
                $briefing['proof_needs'] ?? null,
                self::PROOF_NEEDS,
                'briefing.proof_needs',
                true,
                self::MAX_PROOF_NEEDS
            );
            $errors = array_merge($errors, $proofErr);
            // audiences opcional
            if (array_key_exists('audiences', $briefing)) {
                $audErr = $this->validateEnumArray(
                    $briefing['audiences'],
                    self::AUDIENCES,
                    'briefing.audiences',
                    true,
                    self::MAX_AUDIENCES
                );
                $errors = array_merge($errors, $audErr);
            }
            $invErr = $this->validateInvestment(is_array($briefing['investment'] ?? null) ? $briefing['investment'] : null);
            foreach ($invErr as $k => $msg) {
                $errors['briefing.investment.' . $k] = $msg;
            }
        }

        $interests = $simRaw['interests'] ?? null;
        if (!is_array($interests)) {
            $errors['interests'] = 'interests obrigatorio.';
        } else {
            foreach (array_keys($interests) as $k) {
                if (!in_array((string) $k, self::INTEREST_KEYS, true)) {
                    $errors['interests.' . $k] = 'Campo interests desconhecido.';
                }
            }
            $interestMax = [
                'tier_interest' => self::MAX_TIER_INTEREST,
                'axis_interest' => self::MAX_AXIS_INTEREST,
                'activation_interest' => self::MAX_ACTIVATION_INTEREST,
                'property_interest' => self::MAX_PROPERTY_INTEREST,
                'asset_interest' => self::MAX_ASSET_INTEREST,
            ];
            // Chaves de interests são opcionais; ausência ≠ rejeição.
            foreach (self::INTEREST_KEYS as $key) {
                if (!array_key_exists($key, $interests)) {
                    continue;
                }
                if (!is_array($interests[$key])) {
                    $errors['interests.' . $key] = 'Deve ser array.';
                    continue;
                }
                $max = $interestMax[$key];
                if (count($interests[$key]) > $max) {
                    $errors['interests.' . $key] = 'Maximo ' . $max . ' itens.';
                }
                $normalized = [];
                foreach ($interests[$key] as $i => $item) {
                    if (!is_string($item)) {
                        $errors["interests.$key.$i"] = 'catalog_ref_id invalido.';
                        continue;
                    }
                    $ref = strtoupper(trim($item));
                    if (!$this->isValidCatalogRefId($ref)) {
                        $errors["interests.$key.$i"] = 'catalog_ref_id invalido.';
                        continue;
                    }
                    if ($key === 'tier_interest' && !in_array($ref, self::TIER_REFS, true)) {
                        $errors["interests.$key.$i"] = 'tier_interest invalido.';
                    }
                    $normalized[] = $ref;
                }
                if (count($normalized) !== count(array_unique($normalized))) {
                    $errors['interests.' . $key] = 'Itens devem ser unicos.';
                }
            }
        }

        $rec = $simRaw['recommendation'] ?? null;
        if (!is_array($rec)) {
            $errors['recommendation'] = 'recommendation obrigatorio.';
        } else {
            foreach (array_keys($rec) as $k) {
                if (!in_array((string) $k, self::REC_KEYS, true)) {
                    $errors['recommendation.' . $k] = 'Campo recommendation desconhecido.';
                }
            }
            if (strtoupper((string) ($rec['state'] ?? '')) !== 'READY') {
                $errors['recommendation.state'] = 'state deve ser READY.';
            }
            $primary = $rec['primary'] ?? null;
            if (!is_array($primary)) {
                $errors['recommendation.primary'] = 'primary obrigatorio.';
            } else {
                $errors = array_merge($errors, $this->validateCandidate($primary, 'recommendation.primary'));
            }
            $alts = $rec['alternatives'] ?? null;
            if (!is_array($alts)) {
                $errors['recommendation.alternatives'] = 'Deve ser array.';
            } elseif (count($alts) > self::MAX_ALTERNATIVES) {
                $errors['recommendation.alternatives'] = 'Maximo ' . self::MAX_ALTERNATIVES . ' alternativas.';
            } else {
                foreach ($alts as $i => $alt) {
                    if (!is_array($alt)) {
                        $errors["recommendation.alternatives.$i"] = 'Alternativa invalida.';
                        continue;
                    }
                    $errors = array_merge($errors, $this->validateCandidate($alt, "recommendation.alternatives.$i"));
                }
            }
        }

        $display = $simRaw['display_snapshot'] ?? [];
        if ($display !== [] && !is_array($display)) {
            $errors['display_snapshot'] = 'display_snapshot deve ser objeto.';
        } elseif (is_array($display)) {
            foreach (array_keys($display) as $k) {
                if (!in_array((string) $k, self::DISPLAY_KEYS, true)) {
                    $errors['display_snapshot.' . $k] = 'Campo display desconhecido.';
                }
            }
            $errors = array_merge($errors, $this->validateDisplaySnapshot($display));
        }

        if ($errors !== []) {
            return [[], $errors];
        }

        // Envelope sanitizado (allowlist) — SEM honeypot, SEM campos desconhecidos
        $sanitized = [
            'submission_type' => self::SUBMISSION_TYPE,
            'submission_version' => $subVer,
            'submission_uuid' => $uuid,
            'name' => $this->clip((string) $raw['name'], 180),
            'company_name' => $this->clip($company, 180),
            'role_title' => $this->nullableClip($raw['role_title'] ?? null, 160),
            'email' => $this->clip($email, 180),
            'whatsapp' => $this->nullableClip($raw['whatsapp'] ?? null, 40),
            'city' => $this->nullableClip($raw['city'] ?? null, 120),
            'state' => isset($raw['state']) && $raw['state'] !== null && $raw['state'] !== ''
                ? strtoupper(substr((string) $raw['state'], 0, 2)) : null,
            'segment' => $this->nullableClip($raw['segment'] ?? null, 80),
            'contact_consent' => true,
            'message' => $this->nullableClip($raw['message'] ?? null, self::MAX_MESSAGE_LENGTH),
            'origin_page' => $this->nullableClip($raw['origin_page'] ?? null, 255),
            'source_url' => $this->nullableClip($raw['source_url'] ?? null, 255),
            'form_id' => $this->nullableClip($raw['form_id'] ?? null, 120),
            'form_name' => $this->nullableClip($raw['form_name'] ?? null, 180),
            'utm_source' => $this->nullableClip($raw['utm_source'] ?? null, 120),
            'utm_medium' => $this->nullableClip($raw['utm_medium'] ?? null, 120),
            'utm_campaign' => $this->nullableClip($raw['utm_campaign'] ?? null, 120),
            'utm_content' => $this->nullableClip($raw['utm_content'] ?? null, 120),
            'utm_term' => $this->nullableClip($raw['utm_term'] ?? null, 120),
            'project' => [
                'pronac_number' => preg_replace('/\D+/', '', (string) $project['pronac_number']) ?? '',
                'edition_year' => isset($project['edition_year']) ? (int) $project['edition_year'] : null,
            ],
            'sponsorship_simulation' => [
                'snapshot_version' => $snapVer,
                'engine_version' => $this->clip($engineVer, 20),
                'policy_version' => $this->clip($policyVer, 20),
                'catalog_version' => self::EXPECTED_CATALOG_VERSION,
                'briefing_schema_version' => $briefingSchema,
                'presenter_version' => isset($simRaw['presenter_version'])
                    ? $this->clip((string) $simRaw['presenter_version'], 20) : null,
                'confirmed' => true,
                'confirmed_at' => $confirmedAt,
                'briefing' => $this->sanitizeBriefing($briefing),
                'interests' => $this->sanitizeInterests($interests),
                'recommendation' => $this->sanitizeRecommendation($rec),
                'display_snapshot' => is_array($display) ? $this->sanitizeDisplay($display) : [],
            ],
        ];

        return [$sanitized, []];
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, string>
     */
    private function validateCandidate(array $candidate, string $prefix): array
    {
        $errors = [];
        foreach (array_keys($candidate) as $k) {
            if (!in_array((string) $k, self::CANDIDATE_KEYS, true)) {
                $errors[$prefix . '.' . $k] = 'Campo desconhecido.';
            }
        }
        $tier = strtoupper(trim((string) ($candidate['tier_id'] ?? '')));
        if ($tier === '' || !in_array($tier, self::TIER_REFS, true)) {
            $errors[$prefix . '.tier_id'] = 'tier_id invalido.';
        }
        $fit = strtoupper(trim((string) ($candidate['fit_level'] ?? '')));
        if (!array_key_exists($fit, SponsorshipSimulation::FIT_LEVELS)) {
            $errors[$prefix . '.fit_level'] = 'fit_level invalido.';
        }
        if (strtoupper(trim((string) ($candidate['availability'] ?? ''))) !== 'NOT_CHECKED') {
            $errors[$prefix . '.availability'] = 'availability deve ser NOT_CHECKED.';
        }
        foreach (['axis_id', 'activation_id', 'property_id'] as $opt) {
            if (!array_key_exists($opt, $candidate)) {
                continue;
            }
            $v = $candidate[$opt];
            if ($v === null || $v === '') {
                continue;
            }
            if (!is_string($v) || !$this->isValidCatalogRefId(strtoupper(trim($v)))) {
                $errors[$prefix . '.' . $opt] = 'catalog_ref_id invalido.';
            }
        }

        return $errors;
    }

    public function isValidCatalogRefId(string $value): bool
    {
        $len = strlen($value);

        return $len >= 2
            && $len <= 80
            && (bool) preg_match('/^[A-Z][A-Z0-9_]*$/', $value);
    }

    /**
     * @param mixed $value
     * @param list<string> $allowed
     * @return array<string, string>
     */
    private function validateEnumArray(mixed $value, array $allowed, string $prefix, bool $required, int $maxItems): array
    {
        $errors = [];
        if ($value === null) {
            if ($required) {
                $errors[$prefix] = 'Array obrigatorio.';
            }

            return $errors;
        }
        if (!is_array($value)) {
            $errors[$prefix] = 'Deve ser array.';

            return $errors;
        }
        if (count($value) > $maxItems) {
            $errors[$prefix] = 'Maximo ' . $maxItems . ' itens.';
        }
        $normalized = [];
        foreach ($value as $i => $item) {
            $v = strtoupper(trim((string) $item));
            if (!in_array($v, $allowed, true)) {
                $errors[$prefix . '.' . $i] = 'Enum invalido: ' . $v;
            }
            $normalized[] = $v;
        }
        if (count($normalized) !== count(array_unique($normalized))) {
            $errors[$prefix] = 'Itens devem ser unicos.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed>|null $investment
     * @return array<string, string>
     */
    private function validateInvestment(?array $investment): array
    {
        $errors = [];
        if ($investment === null) {
            return ['status' => 'investment obrigatorio.'];
        }
        foreach (array_keys($investment) as $k) {
            if (!in_array((string) $k, ['status', 'min', 'max', 'currency'], true)) {
                $errors[(string) $k] = 'Campo investment desconhecido.';
            }
        }
        $status = strtoupper(trim((string) ($investment['status'] ?? '')));
        if (!in_array($status, SponsorshipSimulation::INVESTMENT_STATUSES, true)) {
            $errors['status'] = 'status de investimento invalido.';

            return $errors;
        }
        $currency = strtoupper(trim((string) ($investment['currency'] ?? 'BRL')));
        if ($currency !== 'BRL') {
            $errors['currency'] = 'currency deve ser BRL.';
        }
        $min = $investment['min'] ?? null;
        $max = $investment['max'] ?? null;
        if ($status === 'UNDEFINED' || $status === 'OPEN') {
            if ($min !== null && $min !== '') {
                $errors['min'] = 'min deve ser null para ' . $status . '.';
            }
            if ($max !== null && $max !== '') {
                $errors['max'] = 'max deve ser null para ' . $status . '.';
            }

            return $errors;
        }
        if (!is_numeric($min) || !is_numeric($max)) {
            $errors['min'] = 'min/max numericos obrigatorios.';

            return $errors;
        }
        $minF = (float) $min;
        $maxF = (float) $max;
        if ($minF < 0 || $maxF < 0) {
            $errors['min'] = 'Valores nao podem ser negativos.';
        }
        if ($status === 'DEFINED_AMOUNT' && abs($minF - $maxF) > 0.001) {
            $errors['max'] = 'DEFINED_AMOUNT exige min == max.';
        }
        if ($status === 'DEFINED_RANGE' && $minF > $maxF) {
            $errors['max'] = 'DEFINED_RANGE exige min <= max.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $display
     * @return array<string, string>
     */
    private function validateDisplaySnapshot(array $display): array
    {
        $errors = [];
        foreach ($display as $key => $value) {
            if (is_array($value)) {
                if (count($value) > 20) {
                    $errors[(string) $key] = 'Maximo 20 labels.';
                }
                foreach ($value as $item) {
                    if (!is_scalar($item) && $item !== null) {
                        $errors[(string) $key] = 'Apenas texto puro.';
                        break;
                    }
                    if (is_string($item) && (str_contains($item, '<') || mb_strlen($item) > 200)) {
                        $errors[(string) $key] = 'Texto invalido ou longo demais.';
                        break;
                    }
                }
                continue;
            }
            if (!is_scalar($value) && $value !== null) {
                $errors[(string) $key] = 'Apenas texto puro.';
                continue;
            }
            if (is_string($value) && (str_contains($value, '<') || mb_strlen($value) > 240)) {
                $errors[(string) $key] = 'Texto invalido ou longo demais.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $briefing
     * @return array<string, mixed>
     */
    private function sanitizeBriefing(array $briefing): array
    {
        $out = [
            'area_decision' => strtoupper(trim((string) $briefing['area_decision'])),
            'objectives' => array_values(array_map(
                static fn ($v) => strtoupper(trim((string) $v)),
                $briefing['objectives']
            )),
            'depth_intent' => strtoupper(trim((string) $briefing['depth_intent'])),
            'investment' => [
                'status' => strtoupper((string) $briefing['investment']['status']),
                'min' => $briefing['investment']['min'] ?? null,
                'max' => $briefing['investment']['max'] ?? null,
                'currency' => 'BRL',
            ],
            'proof_needs' => array_values(array_map(
                static fn ($v) => strtoupper(trim((string) $v)),
                $briefing['proof_needs']
            )),
        ];
        if (array_key_exists('audiences', $briefing) && is_array($briefing['audiences'])) {
            $out['audiences'] = array_values(array_map(
                static fn ($v) => strtoupper(trim((string) $v)),
                $briefing['audiences']
            ));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $interests
     * @return array<string, mixed>
     */
    private function sanitizeInterests(array $interests): array
    {
        $out = [];
        foreach (self::INTEREST_KEYS as $key) {
            $out[$key] = array_values(array_map(
                static fn ($v) => strtoupper(trim((string) $v)),
                is_array($interests[$key] ?? null) ? $interests[$key] : []
            ));
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $rec
     * @return array<string, mixed>
     */
    private function sanitizeRecommendation(array $rec): array
    {
        $norm = static function (array $c): array {
            return [
                'tier_id' => strtoupper(trim((string) $c['tier_id'])),
                'axis_id' => isset($c['axis_id']) && $c['axis_id'] !== null && $c['axis_id'] !== ''
                    ? strtoupper(trim((string) $c['axis_id'])) : null,
                'activation_id' => isset($c['activation_id']) && $c['activation_id'] !== null && $c['activation_id'] !== ''
                    ? strtoupper(trim((string) $c['activation_id'])) : null,
                'property_id' => isset($c['property_id']) && $c['property_id'] !== null && $c['property_id'] !== ''
                    ? strtoupper(trim((string) $c['property_id'])) : null,
                'fit_level' => strtoupper(trim((string) $c['fit_level'])),
                'availability' => 'NOT_CHECKED',
            ];
        };

        return [
            'state' => 'READY',
            'primary' => $norm($rec['primary']),
            'alternatives' => array_values(array_map(
                static fn ($a) => $norm($a),
                is_array($rec['alternatives'] ?? null) ? $rec['alternatives'] : []
            )),
        ];
    }

    /**
     * @param array<string, mixed> $display
     * @return array<string, mixed>
     */
    private function sanitizeDisplay(array $display): array
    {
        $out = [];
        foreach (self::DISPLAY_KEYS as $key) {
            if (!array_key_exists($key, $display)) {
                continue;
            }
            $val = $display[$key];
            if (is_array($val)) {
                $out[$key] = array_values(array_map(
                    fn ($v) => $this->clip((string) $v, 200),
                    $val
                ));
            } elseif ($val !== null) {
                $out[$key] = $this->clip((string) $val, 240);
            }
        }

        return $out;
    }

    /**
     * Hash SHA-256 do envelope sanitizado integral (chaves ordenadas recursivamente).
     *
     * @param array<string, mixed> $sanitized
     */
    public function computeSnapshotHash(array $sanitized): string
    {
        $canonical = $this->canonicalizeForHash($sanitized);
        $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{}';
        }

        return hash('sha256', $json);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function canonicalizeForHash(array $data): array
    {
        $data = $this->normalizeSetArrays($data);
        /** @var array<string, mixed> $sorted */
        $sorted = $this->sortKeysRecursive($data);

        return $sorted;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeSetArrays(array $data): array
    {
        $sim = $data['sponsorship_simulation'] ?? null;
        if (!is_array($sim)) {
            return $data;
        }
        $briefing = $sim['briefing'] ?? null;
        if (is_array($briefing)) {
            foreach (['objectives', 'audiences', 'proof_needs'] as $key) {
                if (isset($briefing[$key]) && is_array($briefing[$key])) {
                    $vals = array_values(array_map(
                        static fn ($v) => strtoupper(trim((string) $v)),
                        $briefing[$key]
                    ));
                    sort($vals, SORT_STRING);
                    $briefing[$key] = $vals;
                }
            }
            $sim['briefing'] = $briefing;
        }
        $interests = $sim['interests'] ?? null;
        if (is_array($interests)) {
            foreach (self::INTEREST_KEYS as $key) {
                if (isset($interests[$key]) && is_array($interests[$key])) {
                    $vals = array_values(array_map(
                        static fn ($v) => strtoupper(trim((string) $v)),
                        $interests[$key]
                    ));
                    sort($vals, SORT_STRING);
                    $interests[$key] = $vals;
                }
            }
            $sim['interests'] = $interests;
        }
        $data['sponsorship_simulation'] = $sim;

        return $data;
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function sortKeysRecursive(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }
        $isList = array_keys($data) === range(0, count($data) - 1);
        if ($isList) {
            // NÃO reordenar listas genéricas aqui — recommendation.alternatives
            // preserva ranking. Conjuntos já foram ordenados em normalizeSetArrays.
            return array_map(fn ($v) => $this->sortKeysRecursive($v), $data);
        }
        ksort($data);
        foreach ($data as $k => $v) {
            $data[$k] = $this->sortKeysRecursive($v);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $project
     * @return array<string, mixed>|null
     */
    private function resolveProject(array $project): ?array
    {
        $pronac = preg_replace('/\D+/', '', (string) ($project['pronac_number'] ?? '')) ?? '';
        $year = (int) ($project['edition_year'] ?? 0);

        $sql = 'SELECT `id`, `project_name`, `edition_year`, `pronac_number`, `authorized_capture_amount`
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

    private function tierExistsInCatalog(int $projectId, string $tier, string $catalogVersion): bool
    {
        if ($tier === '' || $projectId <= 0) {
            return false;
        }
        $row = Database::run(
            'SELECT `id` FROM `quotas`
              WHERE `incentive_project_id` = :pid
                AND `catalog_ref_id` = :ref
                AND `catalog_version` = :ver
                AND `archived_at` IS NULL
              LIMIT 1',
            ['pid' => $projectId, 'ref' => $tier, 'ver' => $catalogVersion]
        )->fetch(PDO::FETCH_ASSOC);

        return $row !== false;
    }

    /**
     * Hint estrutural — NUNCA usa display_snapshot.
     *
     * @param array<string, mixed> $sim
     */
    private function buildInterestHint(array $sim): string
    {
        $tier = (string) ($sim['recommendation']['primary']['tier_id'] ?? '');

        return $tier !== '' ? 'Simulacao: ' . $tier : 'Simulacao de Patrocinio';
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        // ISO-8601 / RFC3339 estrito (Z ou offset ±HH:MM). Rejeita "tomorrow", datas BR, etc.
        if (!preg_match(
            '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.\d+)?(Z|[+-]\d{2}:\d{2})$/',
            $raw,
            $m
        )) {
            return null;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        if ((int) $m[4] > 23 || (int) $m[5] > 59 || (int) $m[6] > 59) {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $code = (string) ($e->errorInfo[1] ?? '');
        $msg = $e->getMessage();

        return $code === '1062' || str_contains($msg, 'Duplicate') || str_contains($msg, 'uniq_sponsorship_sim_uuid');
    }

    private function clip(string $value, int $max): string
    {
        $value = trim($value);

        return mb_substr($value, 0, $max);
    }

    private function nullableClip(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->clip((string) $value, $max);
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
