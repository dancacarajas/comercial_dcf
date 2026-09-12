<?php
declare(strict_types=1);

final class Dcx_Ssp_Envelope_C
{
    /**
     * @param array<string, mixed>|null $crmBody
     * @return array{http:int,body:array<string,mixed>}
     */
    public static function fromCrm(int $crmHttp, ?array $crmBody, string $submissionUuid): array
    {
        if ($crmHttp === 201) {
            if (!is_array($crmBody)
                || !array_key_exists('success', $crmBody)
                || $crmBody['success'] !== true
                || !array_key_exists('idempotent', $crmBody)
                || !is_bool($crmBody['idempotent'])
            ) {
                return self::reject(502, 'Servico temporariamente indisponivel.', $submissionUuid);
            }

            $idem = $crmBody['idempotent'];

            return [
                'http' => 201,
                'body' => [
                    'success' => true,
                    'status' => $idem ? 'ALREADY_RECEIVED' : 'RECEIVED',
                    'submission_uuid' => $submissionUuid,
                    'idempotent' => $idem,
                ],
            ];
        }

        if ($crmHttp === 409) {
            return self::reject(409, 'Conflito de submissao.', $submissionUuid);
        }

        if ($crmHttp === 429) {
            return self::reject(429, 'Muitas tentativas. Tente novamente mais tarde.', $submissionUuid);
        }

        if ($crmHttp === 422) {
            $errors = [];
            if (is_array($crmBody) && isset($crmBody['errors']) && is_array($crmBody['errors'])) {
                foreach ($crmBody['errors'] as $k => $v) {
                    if (in_array((string) $k, Dcx_Ssp_Constants::PUBLIC_ERROR_KEYS, true)) {
                        $errors[(string) $k] = is_string($v) ? $v : 'Campo invalido.';
                    }
                }
            }

            $body = [
                'success' => false,
                'status' => 'REJECTED',
                'message' => 'Nao foi possivel enviar. Verifique os dados e tente novamente.',
                'submission_uuid' => $submissionUuid,
            ];
            if ($errors !== []) {
                $body['errors'] = $errors;
            }

            return ['http' => 422, 'body' => $body];
        }

        // 403 and anything else → infrastructure
        return self::reject(502, 'Servico temporariamente indisponivel.', $submissionUuid);
    }

    /**
     * @return array{http:int,body:array<string,mixed>}
     */
    public static function honeypotReceived(string $submissionUuid): array
    {
        return [
            'http' => 201,
            'body' => [
                'success' => true,
                'status' => 'RECEIVED',
                'submission_uuid' => $submissionUuid,
                'idempotent' => false,
            ],
        ];
    }

    /**
     * @param array<string, string> $errors
     * @return array{http:int,body:array<string,mixed>}
     */
    public static function rejected(int $http, string $message, ?string $submissionUuid = null, array $errors = []): array
    {
        $body = [
            'success' => false,
            'status' => 'REJECTED',
            'message' => $message,
        ];
        if ($submissionUuid !== null && $submissionUuid !== '') {
            $body['submission_uuid'] = $submissionUuid;
        }
        if ($errors !== []) {
            $public = [];
            foreach ($errors as $k => $v) {
                if (in_array($k, Dcx_Ssp_Constants::PUBLIC_ERROR_KEYS, true)) {
                    $public[$k] = $v;
                }
            }
            if ($public !== []) {
                $body['errors'] = $public;
            }
        }

        return ['http' => $http, 'body' => $body];
    }

    /**
     * @return array{http:int,body:array<string,mixed>}
     */
    private static function reject(int $http, string $message, string $submissionUuid): array
    {
        return [
            'http' => $http,
            'body' => [
                'success' => false,
                'status' => 'REJECTED',
                'message' => $message,
                'submission_uuid' => $submissionUuid,
            ],
        ];
    }
}
