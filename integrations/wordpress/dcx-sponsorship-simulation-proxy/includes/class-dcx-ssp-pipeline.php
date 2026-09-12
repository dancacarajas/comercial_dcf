<?php
declare(strict_types=1);

final class Dcx_Ssp_Pipeline
{
    /**
     * @param array<string, mixed> $server
     * @param array{enabled?:bool,trusted_proxies?:list<string>,timeout_seconds?:int}|null $settingsOverride
     * @return array{http:int,body:array<string,mixed>,crm_called:bool,envelope_b?:array<string,mixed>,headers_sent?:array<string,string>,logs?:list<array<string,mixed>>}
     */
    public static function handle(
        string $rawBody,
        array $server,
        ?callable $httpClient = null,
        ?string $tokenOverride = null,
        ?array $settingsOverride = null,
        ?int $contentLength = null
    ): array {
        Dcx_Ssp_Logger::$buffer = [];
        $correlation = bin2hex(random_bytes(8));
        $settings = $settingsOverride ?? Dcx_Ssp_Settings::all();

        if (!($settings['enabled'] ?? true)) {
            return self::out(503, Dcx_Ssp_Envelope_C::rejected(503, 'Servico indisponivel.')['body'], false);
        }

        $token = $tokenOverride;
        if ($token === null) {
            $token = Dcx_Ssp_Secrets::resolveToken();
        }
        if ($token === null || $token === '') {
            Dcx_Ssp_Logger::info('secret_missing', ['correlation_id' => $correlation]);

            return self::out(503, Dcx_Ssp_Envelope_C::rejected(503, 'Servico temporariamente indisponivel.')['body'], false);
        }

        $parsed = Dcx_Ssp_Envelope_A::parse($rawBody, $contentLength);
        if (!$parsed['ok']) {
            return self::out(
                (int) $parsed['http'],
                Dcx_Ssp_Envelope_C::rejected((int) $parsed['http'], (string) ($parsed['message'] ?? 'Erro'))['body'],
                false
            );
        }

        /** @var array<string, mixed> $data */
        $data = $parsed['data'];

        // UUID gate before honeypot.
        $uuidGate = Dcx_Ssp_Validator::validate($data, true);
        if (!$uuidGate['ok']) {
            return self::out(
                422,
                Dcx_Ssp_Envelope_C::rejected(422, 'Nao foi possivel enviar. Verifique os dados e tente novamente.', null, $uuidGate['errors'] ?? [])['body'],
                false
            );
        }
        $uuid = (string) $uuidGate['data']['submission_uuid'];

        if (Dcx_Ssp_Envelope_A::honeypotFilled($data)) {
            $c = Dcx_Ssp_Envelope_C::honeypotReceived($uuid);
            Dcx_Ssp_Logger::info('honeypot', ['correlation_id' => $correlation, 'submission_uuid' => $uuid]);

            return self::out($c['http'], $c['body'], false);
        }

        $validated = Dcx_Ssp_Validator::validate($data, false);
        if (!$validated['ok']) {
            return self::out(
                422,
                Dcx_Ssp_Envelope_C::rejected(
                    422,
                    'Nao foi possivel enviar. Verifique os dados e tente novamente.',
                    $uuid,
                    $validated['errors'] ?? []
                )['body'],
                false
            );
        }

        $ipRes = Dcx_Ssp_Client_Ip::resolve($server, $settings['trusted_proxies'] ?? []);
        if (!$ipRes['ok'] || $ipRes['ip'] === null) {
            return self::out(
                422,
                Dcx_Ssp_Envelope_C::rejected(422, 'Nao foi possivel enviar. Verifique os dados e tente novamente.', $uuid)['body'],
                false
            );
        }

        $envelopeB = Dcx_Ssp_Envelope_B::build($validated['data']);
        $ua = (string) ($server['HTTP_USER_AGENT'] ?? '');
        $crm = Dcx_Ssp_Crm_Transport::post(
            $envelopeB,
            $token,
            $ipRes['ip'],
            $ua,
            (int) ($settings['timeout_seconds'] ?? 12),
            $httpClient
        );

        Dcx_Ssp_Logger::info('crm_response', [
            'correlation_id' => $correlation,
            'submission_uuid' => $uuid,
            'http_crm' => $crm['http'],
            'duration_ms' => $crm['duration_ms'] ?? null,
        ]);

        $mapped = Dcx_Ssp_Envelope_C::fromCrm((int) $crm['http'], $crm['body'] ?? null, $uuid);
        $out = self::out($mapped['http'], $mapped['body'], true, $envelopeB, $crm['headers_sent'] ?? []);
        $out['logs'] = Dcx_Ssp_Logger::$buffer;

        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed>|null $envelopeB
     * @param array<string, string> $headersSent
     * @return array{http:int,body:array<string,mixed>,crm_called:bool,envelope_b?:array<string,mixed>,headers_sent?:array<string,string>,logs?:list<array<string,mixed>>}
     */
    private static function out(
        int $http,
        array $body,
        bool $crmCalled,
        ?array $envelopeB = null,
        array $headersSent = []
    ): array {
        $r = [
            'http' => $http,
            'body' => $body,
            'crm_called' => $crmCalled,
            'logs' => Dcx_Ssp_Logger::$buffer,
        ];
        if ($envelopeB !== null) {
            $r['envelope_b'] = $envelopeB;
        }
        if ($headersSent !== []) {
            $r['headers_sent'] = $headersSent;
        }

        return $r;
    }
}
