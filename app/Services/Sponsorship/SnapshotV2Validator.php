<?php
declare(strict_types=1);

namespace App\Services\Sponsorship;

use App\Services\SponsorshipLeadIntake;

/**
 * Validador Snapshot V2.0.0 — allowlist + invariantes comerciais em centavos.
 * Não recalcula Recommendation Engine.
 */
final class SnapshotV2Validator
{
    /**
     * @param array<string, mixed> $simRaw
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function validate(array $simRaw): array
    {
        $errors = [];

        foreach (array_keys($simRaw) as $k) {
            if (!in_array((string) $k, SnapshotV2Contract::SIM_KEYS, true)) {
                $errors['sponsorship_simulation.' . $k] = 'Campo simulation desconhecido.';
            }
        }

        foreach (['snapshot_version', 'scenario_version', 'catalog_version', 'briefing_schema_version', 'confirmed', 'confirmed_at', 'briefing', 'interests', 'scenario_result'] as $req) {
            if (!array_key_exists($req, $simRaw)) {
                $errors['sponsorship_simulation.' . $req] = 'Campo obrigatorio ausente.';
            }
        }

        if (($simRaw['snapshot_version'] ?? null) !== SnapshotV2Contract::SNAPSHOT_VERSION) {
            $errors['snapshot_version'] = 'snapshot_version deve ser 2.0.0.';
        }
        if (($simRaw['scenario_version'] ?? null) !== SnapshotV2Contract::SCENARIO_VERSION) {
            $errors['scenario_version'] = 'scenario_version deve ser 2.2.0.';
        }
        if (($simRaw['catalog_version'] ?? null) !== SnapshotV2Contract::CATALOG_VERSION) {
            $errors['catalog_version'] = 'catalog_version deve ser 2026-V2.1.';
        }
        if (($simRaw['briefing_schema_version'] ?? null) !== SnapshotV2Contract::BRIEFING_SCHEMA_VERSION) {
            $errors['briefing_schema_version'] = 'briefing_schema_version deve ser 1.0.0.';
        }
        if (($simRaw['confirmed'] ?? null) !== true) {
            $errors['confirmed'] = 'confirmed deve ser boolean true.';
        }

        $confirmedAt = $this->normalizeRfc3339($simRaw['confirmed_at'] ?? null);
        if ($confirmedAt === null) {
            $errors['confirmed_at'] = 'confirmed_at RFC3339 obrigatorio.';
        }

        $engine = trim((string) ($simRaw['engine_version'] ?? '2.2.0'));
        $policy = trim((string) ($simRaw['policy_version'] ?? '2.2.0'));
        if ($engine === '' || mb_strlen($engine) > 20) {
            $errors['engine_version'] = 'engine_version invalido.';
        }
        if ($policy === '' || mb_strlen($policy) > 20) {
            $errors['policy_version'] = 'policy_version invalido.';
        }

        $briefing = is_array($simRaw['briefing'] ?? null) ? $simRaw['briefing'] : null;
        $briefingOut = null;
        if ($briefing === null) {
            $errors['briefing'] = 'briefing obrigatorio.';
        } else {
            [$briefingOut, $bErr] = $this->validateBriefing($briefing);
            $errors = array_merge($errors, $bErr);
        }

        $interests = is_array($simRaw['interests'] ?? null) ? $simRaw['interests'] : null;
        $interestsOut = null;
        if ($interests === null) {
            $errors['interests'] = 'interests obrigatorio.';
        } else {
            [$interestsOut, $iErr] = $this->validateInterests($interests);
            $errors = array_merge($errors, $iErr);
        }

        $scenario = is_array($simRaw['scenario_result'] ?? null) ? $simRaw['scenario_result'] : null;
        $scenarioOut = null;
        if ($scenario === null) {
            $errors['scenario_result'] = 'scenario_result obrigatorio.';
        } else {
            [$scenarioOut, $sErr] = $this->validateScenario($scenario);
            $errors = array_merge($errors, $sErr);
        }

        $display = $simRaw['display_snapshot'] ?? [];
        if ($display !== null && !is_array($display)) {
            $errors['display_snapshot'] = 'display_snapshot invalido.';
            $display = [];
        }
        $displayOut = $this->validateDisplay(is_array($display) ? $display : [], $errors);

        if ($errors !== []) {
            return [[], $errors];
        }

        $out = [
            'snapshot_version' => SnapshotV2Contract::SNAPSHOT_VERSION,
            'scenario_version' => SnapshotV2Contract::SCENARIO_VERSION,
            'catalog_version' => SnapshotV2Contract::CATALOG_VERSION,
            'briefing_schema_version' => SnapshotV2Contract::BRIEFING_SCHEMA_VERSION,
            'engine_version' => $engine,
            'policy_version' => $policy,
            'confirmed' => true,
            'confirmed_at' => $confirmedAt,
            'briefing' => $briefingOut,
            'interests' => $interestsOut,
            'scenario_result' => $scenarioOut,
            'display_snapshot' => $displayOut,
        ];
        if (isset($simRaw['presenter_version']) && is_string($simRaw['presenter_version'])) {
            $out['presenter_version'] = mb_substr($simRaw['presenter_version'], 0, 20);
        }

        return [$out, []];
    }

    /**
     * @param array<string, mixed> $b
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateBriefing(array $b): array
    {
        $errors = [];
        $allowed = ['area_decision', 'objectives', 'audiences', 'depth_intent', 'investment', 'proof_needs'];
        foreach (array_keys($b) as $k) {
            if (!in_array((string) $k, $allowed, true)) {
                $errors['briefing.' . $k] = 'Campo briefing desconhecido.';
            }
        }
        foreach (['area_decision', 'objectives', 'depth_intent', 'investment', 'proof_needs'] as $req) {
            if (!array_key_exists($req, $b)) {
                $errors['briefing.' . $req] = 'Campo canonico ausente.';
            }
        }

        $clean = [];
        $ad = strtoupper(trim((string) ($b['area_decision'] ?? '')));
        if (!in_array($ad, SponsorshipLeadIntake::AREA_DECISIONS, true)) {
            $errors['briefing.area_decision'] = 'area_decision invalido.';
        } else {
            $clean['area_decision'] = $ad;
        }
        $di = strtoupper(trim((string) ($b['depth_intent'] ?? '')));
        if (!in_array($di, SponsorshipLeadIntake::DEPTH_INTENTS, true)) {
            $errors['briefing.depth_intent'] = 'depth_intent invalido.';
        } else {
            $clean['depth_intent'] = $di;
        }

        $clean['objectives'] = $this->enumList($b['objectives'] ?? null, SponsorshipLeadIntake::OBJECTIVES, 8, 'briefing.objectives', $errors, true);
        if (array_key_exists('audiences', $b)) {
            $clean['audiences'] = $this->enumList($b['audiences'], SponsorshipLeadIntake::AUDIENCES, 8, 'briefing.audiences', $errors, false);
        }
        $clean['proof_needs'] = $this->enumList($b['proof_needs'] ?? null, SponsorshipLeadIntake::PROOF_NEEDS, 10, 'briefing.proof_needs', $errors, true);

        if (!is_array($b['investment'] ?? null)) {
            $errors['briefing.investment'] = 'investment obrigatorio.';
        } else {
            [$inv, $invErr] = $this->validateInvestmentCents($b['investment'], 'briefing.investment');
            $errors = array_merge($errors, $invErr);
            $clean['investment'] = $inv;
        }

        return [$clean, $errors];
    }

    /**
     * @param array<string, mixed> $inv
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateInvestmentCents(array $inv, string $prefix): array
    {
        $errors = [];
        foreach (array_keys($inv) as $k) {
            if (!in_array((string) $k, SnapshotV2Contract::INVESTMENT_KEYS, true)) {
                $errors[$prefix . '.' . $k] = 'Campo investment desconhecido.';
            }
        }
        $status = strtoupper(trim((string) ($inv['status'] ?? '')));
        if (!in_array($status, SnapshotV2Contract::INVESTMENT_STATUSES, true)) {
            $errors[$prefix . '.status'] = 'status invalido.';
        }
        $currency = strtoupper(trim((string) ($inv['currency'] ?? 'BRL')));
        if ($currency !== 'BRL') {
            $errors[$prefix . '.currency'] = 'currency deve ser BRL.';
        }
        $min = array_key_exists('min_cents', $inv) ? $inv['min_cents'] : null;
        $max = array_key_exists('max_cents', $inv) ? $inv['max_cents'] : null;

        if ($status === 'OPEN' || $status === 'UNDEFINED') {
            if ($min !== null || $max !== null) {
                $errors[$prefix] = $status . ' exige min_cents e max_cents null.';
            }
            return [[
                'status' => $status,
                'min_cents' => null,
                'max_cents' => null,
                'currency' => 'BRL',
            ], $errors];
        }

        if (!$this->isIntCents($min) || !$this->isIntCents($max)) {
            $errors[$prefix] = 'min_cents/max_cents devem ser inteiros.';
        } else {
            $minI = (int) $min;
            $maxI = (int) $max;
            if ($status === 'DEFINED_AMOUNT' && $minI !== $maxI) {
                $errors[$prefix] = 'DEFINED_AMOUNT exige min_cents == max_cents.';
            }
            if ($status === 'DEFINED_RANGE' && $minI > $maxI) {
                $errors[$prefix] = 'DEFINED_RANGE invalido.';
            }
        }

        return [[
            'status' => $status,
            'min_cents' => $this->isIntCents($min) ? (int) $min : null,
            'max_cents' => $this->isIntCents($max) ? (int) $max : null,
            'currency' => 'BRL',
        ], $errors];
    }

    /**
     * @param array<string, mixed> $scenario
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function validateScenario(array $scenario): array
    {
        $errors = [];
        foreach (array_keys($scenario) as $k) {
            if (!in_array((string) $k, SnapshotV2Contract::SCENARIO_KEYS, true)) {
                $errors['scenario_result.' . $k] = 'Campo scenario_result desconhecido.';
            }
        }

        $type = strtoupper(trim((string) ($scenario['result_type'] ?? '')));
        if (!in_array($type, SnapshotV2Contract::RESULT_TYPES, true)) {
            $errors['scenario_result.result_type'] = 'result_type invalido.';
        }

        if (($scenario['availability'] ?? null) !== 'NOT_CHECKED') {
            $errors['scenario_result.availability'] = 'availability deve ser NOT_CHECKED.';
        }

        $inv = is_array($scenario['investment'] ?? null) ? $scenario['investment'] : null;
        $invOut = null;
        if ($inv === null) {
            $errors['scenario_result.investment'] = 'investment obrigatorio.';
        } else {
            [$invOut, $invErr] = $this->validateInvestmentCents($inv, 'scenario_result.investment');
            $errors = array_merge($errors, $invErr);
        }

        $out = [
            'result_type' => $type,
            'availability' => 'NOT_CHECKED',
            'investment' => $invOut ?? ['status' => 'UNDEFINED', 'min_cents' => null, 'max_cents' => null, 'currency' => 'BRL'],
        ];

        if (isset($scenario['authorized_cap_cents'])) {
            if (!$this->isIntCents($scenario['authorized_cap_cents'])) {
                $errors['scenario_result.authorized_cap_cents'] = 'authorized_cap_cents invalido.';
            } else {
                $out['authorized_cap_cents'] = (int) $scenario['authorized_cap_cents'];
            }
        }
        if (isset($scenario['minimum_canonical_amount_cents'])) {
            if (!$this->isIntCents($scenario['minimum_canonical_amount_cents'])) {
                $errors['scenario_result.minimum_canonical_amount_cents'] = 'minimum_canonical_amount_cents invalido.';
            } else {
                $out['minimum_canonical_amount_cents'] = (int) $scenario['minimum_canonical_amount_cents'];
            }
        }

        $units = $scenario['units'] ?? null;
        $near = $scenario['near_options'] ?? null;
        $strategic = $scenario['strategic_options'] ?? null;
        $primary = $scenario['primary_tier_id'] ?? null;
        $total = $scenario['total_amount_cents'] ?? null;

        switch ($type) {
            case 'SINGLE':
            case 'COMPOSITION':
                [$unitsOut, $uErr] = $this->validateUnits($units, $type);
                $errors = array_merge($errors, $uErr);
                if (!$this->isIntCents($total)) {
                    $errors['scenario_result.total_amount_cents'] = 'total_amount_cents obrigatorio.';
                } else {
                    $out['total_amount_cents'] = (int) $total;
                }
                $primaryS = $primary === null ? null : strtoupper(trim((string) $primary));
                if ($primaryS === null || !in_array($primaryS, SnapshotV2Contract::TIERS, true)) {
                    $errors['scenario_result.primary_tier_id'] = 'primary_tier_id invalido.';
                } else {
                    $out['primary_tier_id'] = $primaryS;
                }
                $out['units'] = $unitsOut;
                if ($errors === [] && is_array($unitsOut) && isset($out['total_amount_cents'])) {
                    $sum = 0;
                    foreach ($unitsOut as $u) {
                        $sum += (int) $u['amount_cents'];
                    }
                    if ($sum !== (int) $out['total_amount_cents']) {
                        $errors['scenario_result.total_amount_cents'] = 'total_amount_cents deve igualar soma das units.';
                    }
                    if ($type === 'COMPOSITION' && count($unitsOut) < 2) {
                        $errors['scenario_result.units'] = 'COMPOSITION exige >= 2 units.';
                    }
                    if ($type === 'SINGLE' && count($unitsOut) !== 1) {
                        $errors['scenario_result.units'] = 'SINGLE exige exatamente 1 unit.';
                    }
                    $comboErr = $this->validateCompositionRules($unitsOut, $type);
                    $errors = array_merge($errors, $comboErr);
                    if ($sum > SnapshotV2Contract::CENTS_AUTHORIZED_CAP) {
                        $errors['scenario_result.total_amount_cents'] = 'excede teto autorizado.';
                    }
                    // investment consistency for DEFINED_AMOUNT
                    if (($invOut['status'] ?? '') === 'DEFINED_AMOUNT'
                        && (int) ($invOut['min_cents'] ?? -1) !== (int) $out['total_amount_cents']
                    ) {
                        $errors['scenario_result.investment'] = 'investment DEFINED_AMOUNT deve igualar total_amount_cents.';
                    }
                    if (($invOut['status'] ?? '') === 'DEFINED_RANGE'
                        && isset($out['total_amount_cents'])
                        && $this->isIntCents($invOut['min_cents'] ?? null)
                        && $this->isIntCents($invOut['max_cents'] ?? null)
                    ) {
                        $t = (int) $out['total_amount_cents'];
                        if ($t < (int) $invOut['min_cents'] || $t > (int) $invOut['max_cents']) {
                            $errors['scenario_result.total_amount_cents'] = 'total fora da faixa DEFINED_RANGE.';
                        }
                    }
                }
                break;

            case 'NO_EXACT_COMPOSITION':
            case 'NO_EXACT_COMPOSITION_IN_RANGE':
                if ($type === 'NO_EXACT_COMPOSITION'
                    && ($invOut['status'] ?? '') !== 'DEFINED_AMOUNT'
                ) {
                    $errors['scenario_result.investment.status'] = 'NO_EXACT_COMPOSITION exige DEFINED_AMOUNT.';
                }
                if ($type === 'NO_EXACT_COMPOSITION_IN_RANGE'
                    && ($invOut['status'] ?? '') !== 'DEFINED_RANGE'
                ) {
                    $errors['scenario_result.investment.status'] = 'NO_EXACT_COMPOSITION_IN_RANGE exige DEFINED_RANGE.';
                }
                if (!is_array($near)) {
                    $errors['scenario_result.near_options'] = 'near_options obrigatorio.';
                } else {
                    [$nearOut, $nErr] = $this->validateNearOptions($near);
                    $errors = array_merge($errors, $nErr);
                    $out['near_options'] = $nearOut;
                }
                if ($primary !== null && $primary !== '') {
                    $errors['scenario_result.primary_tier_id'] = 'NO_EXACT nao deve ter primary_tier_id selecionado.';
                }
                if ($units !== null) {
                    $errors['scenario_result.units'] = 'NO_EXACT nao deve ter units.';
                }
                break;

            case 'STRATEGIC_ONLY':
                if (($invOut['status'] ?? '') !== 'OPEN' && ($invOut['status'] ?? '') !== 'UNDEFINED') {
                    $errors['scenario_result.investment.status'] = 'STRATEGIC_ONLY exige OPEN ou UNDEFINED.';
                }
                if ($total !== null) {
                    $errors['scenario_result.total_amount_cents'] = 'STRATEGIC_ONLY nao inventa total.';
                }
                if ($units !== null) {
                    $errors['scenario_result.units'] = 'STRATEGIC_ONLY nao deve ter units.';
                }
                if ($strategic !== null) {
                    if (!is_array($strategic)) {
                        $errors['scenario_result.strategic_options'] = 'strategic_options invalido.';
                    } else {
                        [$stOut, $stErr] = $this->validateStrategicOptions($strategic);
                        $errors = array_merge($errors, $stErr);
                        $out['strategic_options'] = $stOut;
                    }
                }
                break;

            case 'NO_CANONICAL_CONFIGURATION':
                if (!$this->isIntCents($invOut['min_cents'] ?? null)) {
                    $errors['scenario_result.investment'] = 'investimento declarado obrigatorio.';
                }
                if (!isset($out['minimum_canonical_amount_cents'])) {
                    $out['minimum_canonical_amount_cents'] = SnapshotV2Contract::CENTS_INCENTIVA_MIN;
                }
                break;

            case 'ABOVE_AUTHORIZED_CAP':
                if (!$this->isIntCents($invOut['min_cents'] ?? null)
                    || (int) $invOut['min_cents'] <= SnapshotV2Contract::CENTS_AUTHORIZED_CAP
                ) {
                    $errors['scenario_result.investment'] = 'ABOVE_AUTHORIZED_CAP exige valor acima do teto.';
                }
                $out['authorized_cap_cents'] = SnapshotV2Contract::CENTS_AUTHORIZED_CAP;
                if ($units !== null) {
                    $errors['scenario_result.units'] = 'ABOVE_AUTHORIZED_CAP nao deve ter units.';
                }
                break;
        }

        return [$out, $errors];
    }

    /**
     * @param mixed $units
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private function validateUnits(mixed $units, string $type): array
    {
        $errors = [];
        if (!is_array($units) || $units === []) {
            return [[], ['scenario_result.units' => 'units obrigatorio.']];
        }
        $out = [];
        foreach ($units as $i => $u) {
            if (!is_array($u)) {
                $errors['scenario_result.units.' . $i] = 'unit invalida.';
                continue;
            }
            foreach (array_keys($u) as $k) {
                if (!in_array((string) $k, SnapshotV2Contract::UNIT_KEYS, true)) {
                    $errors['scenario_result.units.' . $i . '.' . $k] = 'campo unit desconhecido.';
                }
            }
            $tier = strtoupper(trim((string) ($u['tier_id'] ?? '')));
            if (!in_array($tier, SnapshotV2Contract::TIERS, true)) {
                $errors['scenario_result.units.' . $i . '.tier_id'] = 'tier_id invalido.';
            }
            if (!$this->isIntCents($u['amount_cents'] ?? null)) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'amount_cents invalido.';
                $amount = 0;
            } else {
                $amount = (int) $u['amount_cents'];
            }
            $axis = $u['axis_id'] ?? null;
            if ($axis !== null && $axis !== '') {
                $axis = strtoupper(trim((string) $axis));
                if (!in_array($axis, SnapshotV2Contract::AXES, true)) {
                    $errors['scenario_result.units.' . $i . '.axis_id'] = 'axis_id invalido.';
                }
            } else {
                $axis = null;
            }

            // price rules
            if ($tier === 'MOVIMENTO' && $amount !== SnapshotV2Contract::CENTS_MOVIMENTO) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'Movimento exige 2500000.';
            }
            if ($tier === 'EXPERIENCE' && $amount !== SnapshotV2Contract::CENTS_EXPERIENCE) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'Experience exige 5000000.';
            }
            if ($tier === 'CARAJAS' && $amount !== SnapshotV2Contract::CENTS_CARAJAS) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'Carajas exige 10000000.';
            }
            if ($tier === 'APRESENTA' && $amount !== SnapshotV2Contract::CENTS_APRESENTA) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'Apresenta exige 51559248.';
            }
            if ($tier === 'INCENTIVA'
                && ($amount < SnapshotV2Contract::CENTS_INCENTIVA_MIN || $amount >= SnapshotV2Contract::CENTS_INCENTIVA_MAX_EXCL)
            ) {
                $errors['scenario_result.units.' . $i . '.amount_cents'] = 'Incentiva fora da faixa.';
            }

            $out[] = [
                'tier_id' => $tier,
                'amount_cents' => $amount,
                'axis_id' => $axis,
            ];
        }

        return [$out, $errors];
    }

    /**
     * @param list<array<string, mixed>> $units
     * @return array<string, string>
     */
    private function validateCompositionRules(array $units, string $type): array
    {
        $errors = [];
        $counts = ['MOVIMENTO' => 0, 'EXPERIENCE' => 0, 'CARAJAS' => 0, 'INCENTIVA' => 0, 'APRESENTA' => 0];
        $axesUsed = [];
        foreach ($units as $u) {
            $t = (string) $u['tier_id'];
            $counts[$t] = ($counts[$t] ?? 0) + 1;
            if ($t === 'CARAJAS' && $u['axis_id'] !== null) {
                $ax = (string) $u['axis_id'];
                if (isset($axesUsed[$ax])) {
                    $errors['scenario_result.units'] = 'Carajas nao pode repetir o mesmo axis_id explicito.';
                }
                $axesUsed[$ax] = true;
            }
        }
        if ($counts['MOVIMENTO'] > SnapshotV2Contract::MAX_MOVIMENTO) {
            $errors['scenario_result.units'] = 'maximo 4 Movimento.';
        }
        if ($counts['EXPERIENCE'] > SnapshotV2Contract::MAX_EXPERIENCE) {
            $errors['scenario_result.units'] = 'maximo 2 Experience.';
        }
        if ($counts['CARAJAS'] > SnapshotV2Contract::MAX_CARAJAS) {
            $errors['scenario_result.units'] = 'maximo 4 Carajas.';
        }
        if ($counts['APRESENTA'] > 0 && count($units) > 1) {
            $errors['scenario_result.units'] = 'Apresenta nao pode coexistir com outras units.';
        }
        if ($counts['INCENTIVA'] > 0 && ($counts['MOVIMENTO'] + $counts['EXPERIENCE'] + $counts['CARAJAS'] + $counts['APRESENTA']) > 0) {
            $errors['scenario_result.units'] = 'Incentiva nao pode coexistir com M/E/C/A.';
        }
        if ($type === 'COMPOSITION' && $counts['INCENTIVA'] > 0) {
            $errors['scenario_result.units'] = 'Incentiva e somente SINGLE.';
        }
        if ($type === 'COMPOSITION' && $counts['APRESENTA'] > 0) {
            $errors['scenario_result.units'] = 'Apresenta e somente SINGLE.';
        }
        if ($type === 'SINGLE' && $counts['APRESENTA'] === 1 && count($units) !== 1) {
            $errors['scenario_result.units'] = 'Apresenta SINGLE invalido.';
        }

        return $errors;
    }

    /**
     * @param list<mixed> $near
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private function validateNearOptions(array $near): array
    {
        $errors = [];
        $out = [];
        foreach ($near as $i => $n) {
            if (!is_array($n)) {
                $errors['scenario_result.near_options.' . $i] = 'invalido.';
                continue;
            }
            $tier = strtoupper(trim((string) ($n['tier_id'] ?? '')));
            if (!in_array($tier, SnapshotV2Contract::TIERS, true)) {
                $errors['scenario_result.near_options.' . $i . '.tier_id'] = 'tier_id invalido.';
            }
            if (!$this->isIntCents($n['total_amount_cents'] ?? null)) {
                $errors['scenario_result.near_options.' . $i . '.total_amount_cents'] = 'total_amount_cents invalido.';
            }
            $out[] = [
                'tier_id' => $tier,
                'total_amount_cents' => $this->isIntCents($n['total_amount_cents'] ?? null) ? (int) $n['total_amount_cents'] : 0,
            ];
        }

        return [$out, $errors];
    }

    /**
     * @param list<mixed> $opts
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>}
     */
    private function validateStrategicOptions(array $opts): array
    {
        $errors = [];
        $out = [];
        foreach ($opts as $i => $o) {
            if (!is_array($o)) {
                $errors['scenario_result.strategic_options.' . $i] = 'invalido.';
                continue;
            }
            $tier = strtoupper(trim((string) ($o['tier_id'] ?? '')));
            if (!in_array($tier, SnapshotV2Contract::TIERS, true)) {
                $errors['scenario_result.strategic_options.' . $i . '.tier_id'] = 'tier_id invalido.';
            }
            $axis = $o['axis_id'] ?? null;
            if ($axis !== null && $axis !== '') {
                $axis = strtoupper(trim((string) $axis));
                if (!in_array($axis, SnapshotV2Contract::AXES, true)) {
                    $errors['scenario_result.strategic_options.' . $i . '.axis_id'] = 'axis_id invalido.';
                }
            } else {
                $axis = null;
            }
            if (isset($o['amount_cents'])) {
                $errors['scenario_result.strategic_options.' . $i . '.amount_cents'] = 'strategic_options nao inventa amount.';
            }
            $out[] = ['tier_id' => $tier, 'axis_id' => $axis];
        }

        return [$out, $errors];
    }

    /**
     * @param array<string, mixed> $interests
     * @return array{0: array<string, list<string>>, 1: array<string, string>}
     */
    private function validateInterests(array $interests): array
    {
        $errors = [];
        $keys = ['tier_interest', 'axis_interest', 'activation_interest', 'property_interest', 'asset_interest'];
        $max = [5, 4, 8, 8, 15];
        $out = [];
        foreach (array_keys($interests) as $k) {
            if (!in_array((string) $k, $keys, true)) {
                $errors['interests.' . $k] = 'Campo interests desconhecido.';
            }
        }
        foreach ($keys as $i => $key) {
            $list = $interests[$key] ?? [];
            if ($list === null) {
                $list = [];
            }
            if (!is_array($list)) {
                $errors['interests.' . $key] = 'lista invalida.';
                $out[$key] = [];
                continue;
            }
            if (count($list) > $max[$i]) {
                $errors['interests.' . $key] = 'excede maximo.';
            }
            $clean = [];
            foreach ($list as $item) {
                $ref = strtoupper(trim((string) $item));
                if (!preg_match('/^[A-Z][A-Z0-9_]{1,79}$/', $ref)) {
                    $errors['interests.' . $key] = 'catalog_ref_id invalido.';
                    break;
                }
                $clean[] = $ref;
            }
            $out[$key] = $clean;
        }

        return [$out, $errors];
    }

    /**
     * @param array<string, mixed> $display
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function validateDisplay(array $display, array &$errors): array
    {
        $allowed = [
            'objective_labels', 'depth_label', 'experience_labels', 'proof_labels', 'axis_label', 'activation_label',
        ];
        $forbidden = ['availability_label', 'fit_label', 'primary_tier_label', 'investment_label'];
        $out = [];
        foreach ($display as $k => $v) {
            $key = (string) $k;
            if (in_array($key, $forbidden, true)) {
                $errors['display_snapshot.' . $key] = 'label comercial proibido.';
                continue;
            }
            if (!in_array($key, $allowed, true)) {
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
    private function enumList(mixed $list, array $allowed, int $max, string $path, array &$errors, bool $required): array
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

    private function isIntCents(mixed $v): bool
    {
        if (is_int($v)) {
            return $v >= 0;
        }
        if (is_string($v) && preg_match('/^\d+$/', $v)) {
            return true;
        }

        return false;
    }

    private function normalizeRfc3339(mixed $v): ?string
    {
        if (!is_string($v) || $v === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/', $v) !== 1) {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($v);
        } catch (\Exception) {
            return null;
        }

        return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
