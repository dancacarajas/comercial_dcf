<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\CollectorApplication;
use Throwable;

/**
 * Endpoint público POST /api/collectors/site — manifestação inicial do site.
 */
final class CollectorApplicationApiController extends Controller
{
    public function site(): void
    {
        $this->applyCorsHeaders();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'POST')) === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $config = require dirname(__DIR__, 3) . '/config/app.php';

        if (empty($config['collector_endpoint_enabled'])) {
            $this->apiReject('Endpoint desabilitado.', 503);
        }

        $secret = (string) ($config['collector_endpoint_secret'] ?? '');
        if ($secret === '') {
            $this->apiReject('Endpoint não configurado.', 503);
        }

        if (!$this->validateToken($secret)) {
            try {
                (new ActivityLog())->record('blocked_access_attempt', null, 'collector_application', null);
            } catch (Throwable) {
            }
            $this->apiReject('Token inválido.', 403);
        }

        $raw = $this->parseBody();
        if ($this->isHoneypotFilled($raw)) {
            $this->json(['success' => true, 'message' => 'Manifestação recebida com sucesso.', 'application_id' => null], 201);
            return;
        }

        $ip = $this->clientIp();
        if ($this->isRateLimited($ip, $config)) {
            $this->apiReject('Muitas tentativas. Tente novamente mais tarde.', 429);
        }

        $model  = new CollectorApplication();
        $mapped = $model->mapIncoming($raw);

        if (empty($mapped['source_page'])) {
            $mapped['source_page'] = 'patrocinio/captadores-de-recursos';
        }
        if (empty($mapped['source_url'])) {
            $mapped['source_url'] = (string) ($raw['source_url'] ?? ($_SERVER['HTTP_REFERER'] ?? ''));
        }

        $mapped['status']          = 'manifestacao_recebida';
        $mapped['document_status'] = 'nao_solicitado';
        $mapped['review_status']   = 'pendente';
        $mapped['access_status']   = 'nao_liberado';
        $mapped['ip_address']      = $ip;
        $mapped['user_agent']      = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        $errors = $model->validate($mapped, 'api');
        if ($errors !== []) {
            $this->json(['success' => false, 'message' => 'Dados inválidos.', 'errors' => $errors], 422);
            return;
        }

        try {
            $id = $model->create($mapped);
            $this->registerRateAttempt($ip);
            (new ActivityLog())->record('collector_application_received', null, 'collector_application', $id);
            $this->json([
                'success'        => true,
                'message'        => 'Manifestação recebida com sucesso.',
                'application_id' => null,
            ], 201);
        } catch (Throwable $e) {
            error_log('[COLLECTOR API] ' . $e->getMessage());
            $this->apiReject('Erro ao registrar manifestação.', 500);
        }
    }

    /** @return array<string, mixed> */
    private function parseBody(): array
    {
        $ct = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_POST;
    }

    private function validateToken(string $secret): bool
    {
        $header = (string) ($_SERVER['HTTP_X_DCF_COLLECTOR_TOKEN'] ?? '');
        $field  = (string) (input('collector_token', '') ?? '');

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

    /** @param array<string, mixed> $config */
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
        $window = (int) ($config['collector_rate_limit_minutes'] ?? 10) * 60;
        $max    = (int) ($config['collector_rate_limit_max_attempts'] ?? 5);
        $recent = array_filter(
            (array) ($data['attempts'] ?? []),
            static fn ($ts) => (time() - (int) $ts) < $window
        );

        return count($recent) >= $max;
    }

    private function registerRateAttempt(string $ip): void
    {
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
        return dirname(__DIR__, 3) . '/storage/ratelimit/collector_' . md5($ip) . '.json';
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
        header('Access-Control-Allow-Headers: Content-Type, X-DCF-Collector-Token');
        header('Access-Control-Max-Age: 86400');
    }

    /** @param array<string, mixed> $raw */
    private function isHoneypotFilled(array $raw): bool
    {
        foreach (['website_url', 'website', 'url'] as $field) {
            if (!empty($raw[$field])) {
                return true;
            }
        }

        return false;
    }
}
