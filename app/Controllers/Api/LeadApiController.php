<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Services\SponsorshipLeadIntake;
use Throwable;

/**
 * Endpoint público POST /api/leads/site (Etapa 9).
 * Sem login/CSRF interno; protegido por token, honeypot e rate limit.
 */
final class LeadApiController extends Controller
{
    public function site(): void
    {
        $this->applyCorsHeaders();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $config = require dirname(__DIR__, 3) . '/config/app.php';

        if (empty($config['lead_endpoint_enabled'])) {
            $this->apiReject('Endpoint desabilitado.', 503);
        }

        $secret = (string) ($config['lead_endpoint_secret'] ?? '');
        if ($secret === '') {
            $this->apiReject('Endpoint não configurado.', 503);
        }

        if (!$this->validateToken($secret)) {
            try {
                (new ActivityLog())->record('lead_api_rejected', null, 'lead', null);
            } catch (Throwable) {
            }
            $this->apiReject('Token inválido.', 403);
        }

        $raw = $this->parseBody();
        if ($this->isHoneypotFilled($raw)) {
            // Honeypot — rejeição silenciosa (spam).
            $this->json(['success' => true, 'message' => 'Lead recebido com sucesso.', 'lead_id' => 0], 201);
            return;
        }

        $ip = $this->clientIp();
        $submissionType = strtoupper(trim((string) ($raw['submission_type'] ?? Lead::SUBMISSION_GENERIC)));

        // Decisão 21.1: replay idempotente de SPONSORSHIP_SIMULATION já persistido
        // NÃO é bloqueado pelo rate limit (não cria Lead novo; preserva semântica 201/409).
        // Novas submissões e GENERIC_LEAD continuam sujeitos ao rate limit anti-spam.
        $isKnownSimulationReplay = false;
        if ($submissionType === SponsorshipLeadIntake::SUBMISSION_TYPE) {
            $uuid = strtolower(trim((string) ($raw['submission_uuid'] ?? '')));
            // Só bypass com UUID canônico — evita lookup no banco com lixo arbitrário.
            if ($uuid !== ''
                && (bool) preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                    $uuid
                )
                && (new \App\Models\SponsorshipSimulation())->findBySubmissionUuid($uuid) !== null
            ) {
                $isKnownSimulationReplay = true;
            }
        }

        if (!$isKnownSimulationReplay && $this->isRateLimited($ip, $config)) {
            $this->apiReject('Muitas tentativas. Tente novamente mais tarde.', 429);
        }

        if ($submissionType === '' || $submissionType === Lead::SUBMISSION_GENERIC) {
            $this->handleGenericLead($raw, $ip);
            return;
        }

        if ($submissionType === SponsorshipLeadIntake::SUBMISSION_TYPE) {
            $this->handleSponsorshipSimulation($raw, $ip);
            return;
        }

        $this->json([
            'success' => false,
            'message' => 'submission_type não suportado.',
            'errors' => ['submission_type' => 'Use GENERIC_LEAD ou SPONSORSHIP_SIMULATION.'],
        ], 422);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function handleGenericLead(array $raw, string $ip): void
    {
        $model  = new Lead();
        $mapped = $model->mapIncoming($raw);

        if (empty($mapped['origin_page']) && !empty($raw['origin_page'])) {
            $mapped['origin_page'] = (string) $raw['origin_page'];
        }
        if (empty($mapped['source_url'])) {
            $mapped['source_url'] = (string) ($raw['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
        }
        if (empty($mapped['form_id'])) {
            $mapped['form_id'] = (string) ($raw['form_id'] ?? '');
        }
        if (empty($mapped['form_name'])) {
            $mapped['form_name'] = (string) ($raw['form_name'] ?? '');
        }

        $mapped['status'] = 'novo';
        $mapped['submission_type'] = Lead::SUBMISSION_GENERIC;
        $mapped['ip_address'] = $ip;
        $mapped['user_agent'] = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $mapped['referrer'] = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
        $mapped['integration_payload'] = $raw;

        $errors = $model->validate($mapped, 'api');
        if ($errors !== []) {
            $this->json(['success' => false, 'message' => 'Dados inválidos.', 'errors' => $errors], 422);
            return;
        }

        try {
            $id = $model->create($mapped);
            $this->registerRateAttempt($ip);
            (new ActivityLog())->record('lead_received_site', null, 'lead', $id);
            $this->json([
                'success' => true,
                'message' => 'Lead recebido com sucesso.',
                'lead_id' => (int) $id,
            ], 201);
        } catch (Throwable $e) {
            error_log('[LEAD API] ' . $e->getMessage());
            $this->apiReject('Erro ao registrar lead.', 500);
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function handleSponsorshipSimulation(array $raw, string $ip): void
    {
        $result = (new SponsorshipLeadIntake())->intake(
            $raw,
            $ip,
            (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            (string) ($_SERVER['HTTP_REFERER'] ?? '')
        );

        if (!$result['ok']) {
            $payload = [
                'success' => false,
                'message' => $result['message'],
            ];
            if (!empty($result['errors'])) {
                $payload['errors'] = $result['errors'];
            }
            $this->json($payload, (int) $result['http']);
            return;
        }

        $this->registerRateAttempt($ip, !empty($result['idempotent']));
        $this->json([
            'success' => true,
            'message' => $result['message'],
            'lead_id' => (int) $result['lead_id'],
            'simulation_id' => (int) ($result['simulation_id'] ?? 0),
            'idempotent' => !empty($result['idempotent']),
        ], (int) $result['http']);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseBody(): array
    {
        $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return array_merge($_GET, $_POST);
    }

    private function validateToken(string $secret): bool
    {
        $header = (string) ($_SERVER['HTTP_X_DCF_LEAD_TOKEN'] ?? '');
        $field  = (string) (input('lead_token', '') ?? '');

        return ($header !== '' && hash_equals($secret, $header))
            || ($field !== '' && hash_equals($secret, $field));
    }

    private function clientIp(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            $v = trim((string) ($_SERVER[$k] ?? ''));
            if ($v !== '') {
                if ($k === 'HTTP_X_FORWARDED_FOR') {
                    $v = trim(explode(',', $v)[0]);
                }

                return substr($v, 0, 80);
            }
        }

        return '0.0.0.0';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function isRateLimited(string $ip, array $config): bool
    {
        $file = $this->rateFile($ip);
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return false;
        }
        $window = (int) ($config['lead_rate_limit_minutes'] ?? 10) * 60;
        $max    = (int) ($config['lead_rate_limit_max_attempts'] ?? 5);
        $recent = array_filter(
            (array) ($data['attempts'] ?? []),
            static fn ($ts) => (time() - (int) $ts) < $window
        );

        return count($recent) >= $max;
    }

    private function registerRateAttempt(string $ip, bool $skip = false): void
    {
        if ($skip) {
            // Replay idempotente: nao consome cota do rate limit.
            return;
        }
        $dir = dirname(__DIR__, 3) . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $this->rateFile($ip);
        $data = ['attempts' => []];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $data['attempts'][] = time();
        $data['attempts'] = array_slice($data['attempts'], -20);
        file_put_contents($file, json_encode($data));
    }

    private function rateFile(string $ip): string
    {
        return dirname(__DIR__, 3) . '/storage/ratelimit/' . md5($ip) . '.json';
    }

    private function apiReject(string $message, int $code): void
    {
        $this->json(['success' => false, 'message' => $message], $code);
        exit;
    }

    private function applyCorsHeaders(): void
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $allowed = [
            'https://dancacarajas.com.br',
            'https://www.dancacarajas.com.br',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
        ];

        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-DCF-Lead-Token');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function isHoneypotFilled(array $raw): bool
    {
        foreach (['website_url', 'website'] as $field) {
            if (!empty($raw[$field])) {
                return true;
            }
        }

        return false;
    }
}
