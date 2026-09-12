<?php
declare(strict_types=1);

final class Dcx_Ssp_Validator
{
    /**
     * @param array<string, mixed> $raw
     * @return array{ok:bool,data?:array<string,mixed>,errors?:array<string,string>}
     */
    public static function validate(array $raw, bool $uuidOnly = false): array
    {
        $errors = [];

        $uuidRaw = trim((string) ($raw['submission_uuid'] ?? ''));
        $uuid = strtolower($uuidRaw);
        // Contrato: UUID v4 canônico lowercase — uppercase não é aceito (sem coerção).
        if ($uuidRaw !== $uuid || !self::isValidUuidV4($uuid)) {
            $errors['submission_uuid'] = 'submission_uuid deve ser UUID v4 lowercase valido.';
        }

        // Pré-honeypot: apenas UUID canônico.
        if ($uuidOnly) {
            if ($errors !== []) {
                return ['ok' => false, 'errors' => $errors];
            }

            return ['ok' => true, 'data' => ['submission_uuid' => $uuid]];
        }

        if (self::containsHardDeny($raw)) {
            $errors['_deny'] = 'Campo nao autorizado.';
        }

        foreach (array_keys($raw) as $key) {
            $k = (string) $key;
            if (!in_array($k, Dcx_Ssp_Constants::TOP_LEVEL_KEYS, true)) {
                $errors[$k] = 'Campo nao permitido.';
            }
        }

        if (($raw['contact_consent'] ?? null) !== true) {
            $errors['contact_consent'] = 'contact_consent deve ser boolean true.';
        }

        $name = self::trimStr($raw['name'] ?? null, 2, 180, 'name', $errors);
        $company = self::trimStr($raw['company_name'] ?? null, 2, 180, 'company_name', $errors);
        $email = self::email($raw['email'] ?? null, $errors);

        $role = self::optionalTrim($raw['role_title'] ?? null, 160);
        $whatsapp = self::optionalTrim($raw['whatsapp'] ?? null, 40);
        $city = self::optionalTrim($raw['city'] ?? null, 120);
        $state = self::optionalState($raw['state'] ?? null, $errors);
        $segment = self::optionalTrim($raw['segment'] ?? null, 80);
        $message = self::optionalMessage($raw['message'] ?? null, $errors);

        $utms = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $utm) {
            if (!array_key_exists($utm, $raw) || $raw[$utm] === null) {
                $utms[$utm] = null;
                continue;
            }
            $utms[$utm] = self::clip((string) $raw[$utm], 120);
        }

        $simRaw = $raw['sponsorship_simulation'] ?? null;
        if (!is_array($simRaw)) {
            $errors['sponsorship_simulation'] = 'sponsorship_simulation obrigatorio.';
            $sim = null;
        } else {
            $sim = self::validateSimulation($simRaw, $errors);
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $data = [
            'submission_uuid' => $uuid,
            'name' => $name,
            'company_name' => $company,
            'email' => $email,
            'contact_consent' => true,
            'sponsorship_simulation' => $sim,
        ];
        if ($role !== null) {
            $data['role_title'] = $role;
        }
        if ($whatsapp !== null) {
            $data['whatsapp'] = $whatsapp;
        }
        if ($city !== null) {
            $data['city'] = $city;
        }
        if ($state !== null) {
            $data['state'] = $state;
        }
        if ($segment !== null) {
            $data['segment'] = $segment;
        }
        if ($message !== null) {
            $data['message'] = $message;
        }
        foreach ($utms as $k => $v) {
            $data[$k] = $v;
        }

        return ['ok' => true, 'data' => $data];
    }

    /**
     * @param array<string, mixed> $sim
     * @param array<string, string> $errors
     * @return array<string, mixed>|null
     */
    private static function validateSimulation(array $sim, array &$errors): ?array
    {
        $simAllowed = [
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
        foreach (array_keys($sim) as $key) {
            $k = (string) $key;
            if (in_array($k, Dcx_Ssp_Constants::HARD_DENY_KEYS, true)
                || in_array($k, Dcx_Ssp_Constants::DISPLAY_FORBIDDEN, true)
            ) {
                $errors['sponsorship_simulation.' . $k] = 'Campo nao autorizado.';
            } elseif (!in_array($k, $simAllowed, true)) {
                $errors['sponsorship_simulation.' . $k] = 'Campo nao permitido.';
            }
        }

        if (($sim['confirmed'] ?? null) !== true) {
            $errors['confirmed'] = 'confirmed deve ser boolean true.';
        }

        $confirmedAt = $sim['confirmed_at'] ?? null;
        if (!is_string($confirmedAt) || !self::isRfc3339($confirmedAt)) {
            $errors['confirmed_at'] = 'confirmed_at deve ser RFC3339.';
        }

        foreach (['engine_version', 'policy_version'] as $ver) {
            if (!isset($sim[$ver]) || !is_string($sim[$ver]) || trim($sim[$ver]) === '' || strlen($sim[$ver]) > 20) {
                $errors[$ver] = $ver . ' invalido.';
            }
        }
        if (isset($sim['presenter_version']) && $sim['presenter_version'] !== null) {
            if (!is_string($sim['presenter_version']) || strlen($sim['presenter_version']) > 20) {
                $errors['presenter_version'] = 'presenter_version invalido.';
            }
        }

        $briefing = $sim['briefing'] ?? null;
        if (!is_array($briefing)) {
            $errors['briefing'] = 'briefing obrigatorio.';
        } else {
            self::validateBriefing($briefing, $errors);
        }

        $interests = $sim['interests'] ?? [];
        if ($interests === null) {
            $interests = [];
        }
        if (!is_array($interests)) {
            $errors['interests'] = 'interests invalido.';
        } else {
            $interests = self::validateInterests($interests, $errors);
        }

        $rec = $sim['recommendation'] ?? null;
        if (!is_array($rec)) {
            $errors['recommendation'] = 'recommendation obrigatoria.';
        } else {
            $rec = self::validateRecommendation($rec, $errors);
        }

        $display = $sim['display_snapshot'] ?? null;
        if ($display !== null) {
            if (!is_array($display)) {
                $errors['display_snapshot'] = 'display_snapshot invalido.';
            } else {
                $display = self::validateDisplay($display, $errors);
            }
        }

        if ($errors !== []) {
            return null;
        }

        $out = [
            'engine_version' => trim((string) $sim['engine_version']),
            'policy_version' => trim((string) $sim['policy_version']),
            'confirmed' => true,
            'confirmed_at' => (string) $confirmedAt,
            'briefing' => $briefing,
            'interests' => $interests,
            'recommendation' => $rec,
        ];
        if (isset($sim['presenter_version']) && is_string($sim['presenter_version'])) {
            $out['presenter_version'] = $sim['presenter_version'];
        }
        if (is_array($display)) {
            $out['display_snapshot'] = $display;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $b
     * @param array<string, string> $errors
     */
    private static function validateBriefing(array &$b, array &$errors): void
    {
        $allowed = [
            'area_decision',
            'objectives',
            'audiences',
            'depth_intent',
            'investment',
            'proof_needs',
        ];
        foreach (array_keys($b) as $key) {
            if (!in_array((string) $key, $allowed, true)) {
                $errors['briefing.' . $key] = 'Campo nao permitido.';
            }
        }

        $clean = [];

        $ad = strtoupper(trim((string) ($b['area_decision'] ?? '')));
        if (!in_array($ad, Dcx_Ssp_Constants::AREA_DECISIONS, true)) {
            $errors['briefing.area_decision'] = 'area_decision invalida.';
        } else {
            $clean['area_decision'] = $ad;
        }

        $depth = strtoupper(trim((string) ($b['depth_intent'] ?? '')));
        if (!in_array($depth, Dcx_Ssp_Constants::DEPTH_INTENTS, true)) {
            $errors['briefing.depth_intent'] = 'depth_intent invalido.';
        } else {
            $clean['depth_intent'] = $depth;
        }

        $clean['objectives'] = self::enumList(
            $b['objectives'] ?? null,
            Dcx_Ssp_Constants::OBJECTIVES,
            8,
            'briefing.objectives',
            $errors,
            true
        );
        if (array_key_exists('audiences', $b)) {
            $clean['audiences'] = self::enumList(
                $b['audiences'],
                Dcx_Ssp_Constants::AUDIENCES,
                8,
                'briefing.audiences',
                $errors,
                false
            );
        }
        $clean['proof_needs'] = self::enumList(
            $b['proof_needs'] ?? null,
            Dcx_Ssp_Constants::PROOF_NEEDS,
            10,
            'briefing.proof_needs',
            $errors,
            true
        );

        $inv = $b['investment'] ?? null;
        if (!is_array($inv)) {
            $errors['briefing.investment'] = 'investment obrigatorio.';
        } else {
            $clean['investment'] = self::validateInvestment($inv, $errors);
        }

        $b = $clean;
    }

    /**
     * @param array<string, mixed> $inv
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private static function validateInvestment(array $inv, array &$errors): array
    {
        foreach (array_keys($inv) as $key) {
            if (!in_array((string) $key, ['status', 'min', 'max', 'currency'], true)) {
                $errors['briefing.investment.' . $key] = 'Campo nao permitido.';
            }
        }

        $status = strtoupper(trim((string) ($inv['status'] ?? '')));
        if (!in_array($status, Dcx_Ssp_Constants::INVESTMENT_STATUSES, true)) {
            $errors['briefing.investment.status'] = 'status de investment invalido.';
        }
        $currency = strtoupper(trim((string) ($inv['currency'] ?? 'BRL')));
        if ($currency !== 'BRL') {
            $errors['briefing.investment.currency'] = 'currency deve ser BRL.';
        }

        $min = array_key_exists('min', $inv) ? $inv['min'] : null;
        $max = array_key_exists('max', $inv) ? $inv['max'] : null;

        if ($status === 'UNDEFINED' || $status === 'OPEN') {
            if ($min !== null || $max !== null) {
                $errors['briefing.investment'] = $status . ' exige min e max null.';
            }
            return [
                'status' => $status !== '' ? $status : 'UNDEFINED',
                'min' => null,
                'max' => null,
                'currency' => 'BRL',
            ];
        }

        if ($status === 'DEFINED_AMOUNT') {
            if (!is_numeric($min) || !is_numeric($max) || abs((float) $min - (float) $max) > 0.001) {
                $errors['briefing.investment'] = 'DEFINED_AMOUNT exige min == max numericos.';
            }
        } elseif ($status === 'DEFINED_RANGE') {
            if (!is_numeric($min) || !is_numeric($max) || (float) $min > (float) $max) {
                $errors['briefing.investment'] = 'DEFINED_RANGE invalido.';
            }
        }

        return [
            'status' => $status,
            'min' => is_numeric($min) ? (float) $min : null,
            'max' => is_numeric($max) ? (float) $max : null,
            'currency' => 'BRL',
        ];
    }

    /**
     * @param array<string, mixed> $interests
     * @param array<string, string> $errors
     * @return array<string, list<string>>
     */
    private static function validateInterests(array $interests, array &$errors): array
    {
        $limits = [
            'tier_interest' => 5,
            'axis_interest' => 4,
            'activation_interest' => 8,
            'property_interest' => 8,
            'asset_interest' => 15,
        ];
        $out = [];
        foreach ($limits as $key => $max) {
            $list = $interests[$key] ?? [];
            if ($list === null) {
                $list = [];
            }
            if (!is_array($list)) {
                $errors['interests.' . $key] = 'lista invalida.';
                $out[$key] = [];
                continue;
            }
            if (count($list) > $max) {
                $errors['interests.' . $key] = 'excede maximo.';
            }
            $clean = [];
            foreach ($list as $item) {
                $ref = strtoupper(trim((string) $item));
                if (!self::isCatalogRefId($ref)) {
                    $errors['interests.' . $key] = 'catalog_ref_id invalido.';
                    break;
                }
                $clean[] = $ref;
            }
            $out[$key] = $clean;
        }
        foreach (array_keys($interests) as $extra) {
            if (!isset($limits[(string) $extra])) {
                $errors['interests.' . $extra] = 'chave nao permitida.';
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $rec
     * @param array<string, string> $errors
     * @return array<string, mixed>|null
     */
    private static function validateRecommendation(array $rec, array &$errors): ?array
    {
        foreach (array_keys($rec) as $key) {
            if (!in_array((string) $key, ['state', 'primary', 'alternatives'], true)) {
                $errors['recommendation.' . $key] = 'Campo nao permitido.';
            }
        }

        if (($rec['state'] ?? null) !== 'READY') {
            $errors['recommendation.state'] = 'state deve ser READY.';
        }
        $primary = $rec['primary'] ?? null;
        if (!is_array($primary)) {
            $errors['recommendation.primary'] = 'primary obrigatorio.';
            $primaryOut = null;
        } else {
            $primaryOut = self::validateCandidate($primary, 'recommendation.primary', $errors);
        }
        $alts = $rec['alternatives'] ?? [];
        if (!is_array($alts)) {
            $errors['recommendation.alternatives'] = 'alternatives invalido.';
            $altsOut = [];
        } else {
            if (count($alts) > 5) {
                $errors['recommendation.alternatives'] = 'maximo 5 alternatives.';
            }
            $altsOut = [];
            foreach ($alts as $i => $alt) {
                if (!is_array($alt)) {
                    $errors['recommendation.alternatives.' . $i] = 'invalido.';
                    continue;
                }
                $c = self::validateCandidate($alt, 'recommendation.alternatives.' . $i, $errors);
                if ($c !== null) {
                    $altsOut[] = $c;
                }
            }
        }

        if ($errors !== [] || $primaryOut === null) {
            return null;
        }

        return [
            'state' => 'READY',
            'primary' => $primaryOut,
            'alternatives' => $altsOut,
        ];
    }

    /**
     * @param array<string, mixed> $c
     * @param array<string, string> $errors
     * @return array<string, mixed>|null
     */
    private static function validateCandidate(array $c, string $prefix, array &$errors): ?array
    {
        foreach (array_keys($c) as $key) {
            if (!in_array((string) $key, ['tier_id', 'axis_id', 'activation_id', 'property_id', 'fit_level', 'availability'], true)) {
                $errors[$prefix . '.' . $key] = 'campo nao permitido.';
            }
        }
        $tier = strtoupper(trim((string) ($c['tier_id'] ?? '')));
        if (!self::isCatalogRefId($tier)) {
            $errors[$prefix . '.tier_id'] = 'tier_id invalido.';
        }
        $fit = strtoupper(trim((string) ($c['fit_level'] ?? '')));
        if (!in_array($fit, Dcx_Ssp_Constants::FIT_LEVELS, true)) {
            $errors[$prefix . '.fit_level'] = 'fit_level invalido.';
        }
        if (($c['availability'] ?? null) !== 'NOT_CHECKED') {
            $errors[$prefix . '.availability'] = 'availability deve ser NOT_CHECKED.';
        }

        $axis = $c['axis_id'] ?? null;
        $activation = $c['activation_id'] ?? null;
        $property = $c['property_id'] ?? null;
        foreach (['axis_id' => $axis, 'activation_id' => $activation, 'property_id' => $property] as $k => $v) {
            if ($v === null) {
                continue;
            }
            $ref = strtoupper(trim((string) $v));
            if (!self::isCatalogRefId($ref)) {
                $errors[$prefix . '.' . $k] = 'catalog_ref_id invalido.';
            }
        }

        return [
            'tier_id' => $tier,
            'axis_id' => $axis === null ? null : strtoupper(trim((string) $axis)),
            'activation_id' => $activation === null ? null : strtoupper(trim((string) $activation)),
            'property_id' => $property === null ? null : strtoupper(trim((string) $property)),
            'fit_level' => $fit,
            'availability' => 'NOT_CHECKED',
        ];
    }

    /**
     * @param array<string, mixed> $display
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private static function validateDisplay(array $display, array &$errors): array
    {
        $out = [];
        foreach ($display as $k => $v) {
            $key = (string) $k;
            if (in_array($key, Dcx_Ssp_Constants::DISPLAY_FORBIDDEN, true)) {
                $errors['display_snapshot.' . $key] = 'label comercial proibido.';
                continue;
            }
            if (!in_array($key, Dcx_Ssp_Constants::DISPLAY_KEYS, true)) {
                $errors['display_snapshot.' . $key] = 'chave nao permitida.';
                continue;
            }
            $out[$key] = $v;
        }

        return $out;
    }

    /**
     * @param mixed $list
     * @param list<string> $allowed
     * @param array<string, string> $errors
     * @return list<string>
     */
    private static function enumList(mixed $list, array $allowed, int $max, string $path, array &$errors, bool $required): array
    {
        if ($list === null) {
            if ($required) {
                $errors[$path] = 'obrigatorio.';
            }

            return [];
        }
        if (!is_array($list)) {
            $errors[$path] = 'deve ser array.';

            return [];
        }
        if (count($list) > $max) {
            $errors[$path] = 'excede maximo.';
        }
        $out = [];
        foreach ($list as $item) {
            $v = strtoupper(trim((string) $item));
            if (!in_array($v, $allowed, true)) {
                $errors[$path] = 'valor nao permitido.';
                break;
            }
            $out[] = $v;
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private static function containsHardDeny(array $data): bool
    {
        $stack = [$data];
        while ($stack !== []) {
            $cur = array_pop($stack);
            if (!is_array($cur)) {
                continue;
            }
            foreach ($cur as $k => $v) {
                $key = (string) $k;
                if (in_array($key, Dcx_Ssp_Constants::HARD_DENY_KEYS, true)
                    || str_starts_with($key, 'commission_')
                ) {
                    return true;
                }
                if (is_array($v)) {
                    $stack[] = $v;
                }
            }
        }

        return false;
    }

    public static function isValidUuidV4(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    /** catalog_ref_id: 2..80 chars, ^[A-Z][A-Z0-9_]*$ */
    public static function isCatalogRefId(string $ref): bool
    {
        return (bool) preg_match('/^[A-Z][A-Z0-9_]{1,79}$/', $ref);
    }

    public static function isRfc3339(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $value) !== 1) {
            return false;
        }
        try {
            new DateTimeImmutable($value);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /** @param array<string, string> $errors */
    private static function trimStr(mixed $v, int $min, int $max, string $field, array &$errors): string
    {
        if (!is_string($v) && !is_numeric($v)) {
            $errors[$field] = $field . ' obrigatorio.';

            return '';
        }
        $s = trim((string) $v);
        $len = strlen($s);
        if ($len < $min || $len > $max) {
            $errors[$field] = $field . ' fora dos limites.';
        }

        return $s;
    }

    private static function optionalTrim(mixed $v, int $max): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }

        return self::clip($s, $max);
    }

    /** @param array<string, string> $errors */
    private static function optionalState(mixed $v, array &$errors): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = strtoupper(trim((string) $v));
        if (strlen($s) !== 2 || !ctype_alpha($s)) {
            $errors['state'] = 'state deve ser UF com 2 letras.';

            return $s;
        }

        return $s;
    }

    /** @param array<string, string> $errors */
    private static function optionalMessage(mixed $v, array &$errors): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_string($v)) {
            $errors['message'] = 'message invalida.';

            return null;
        }
        $s = trim(strip_tags($v));
        if (strlen($s) > 5000) {
            $errors['message'] = 'message excede 5000.';
        }

        return $s;
    }

    /** @param array<string, string> $errors */
    private static function email(mixed $v, array &$errors): string
    {
        $s = strtolower(trim((string) $v));
        if ($s === '' || !filter_var($s, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'email invalido.';
        }

        return $s;
    }

    private static function clip(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max);
    }
}
