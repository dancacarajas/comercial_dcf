<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Helpers\ContractDocumentHelper;
use App\Services\ContractTemplateRenderer;
use App\Services\SignaturePdfGenerator;
use RuntimeException;

final class SignatureRequest extends Model
{
    protected string $table = 'signature_requests';

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'rascunho'               => 'Rascunho',
            'aguardando_assinatura'  => 'Aguardando assinatura',
            'parcialmente_assinado'  => 'Parcialmente assinado',
            'assinado'               => 'Assinado',
            'cancelado'              => 'Cancelado',
            'expirado'               => 'Expirado',
            'arquivado'              => 'Arquivado',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query('SELECT * FROM `signature_requests` WHERE `id` = :id LIMIT 1', ['id' => $id])->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByPublicToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $row = $this->query(
            'SELECT * FROM `signature_requests` WHERE `public_token` = :t LIMIT 1',
            ['t' => $token]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function findBySource(string $sourceType, int $sourceId): array
    {
        return $this->query(
            'SELECT * FROM `signature_requests`
              WHERE `source_type` = :st AND `source_id` = :sid AND `archived_at` IS NULL
              ORDER BY `id` DESC',
            ['st' => $sourceType, 'sid' => $sourceId]
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function activeForCollectorApplication(int $applicationId): ?array
    {
        $rows = $this->activeForCollectorApplicationList($applicationId);

        return $rows[0] ?? null;
    }

    /** @return array<int, array<string, mixed>> */
    public function activeForCollectorApplicationList(int $applicationId): array
    {
        $rows = $this->findBySource('collector_application', $applicationId);
        $active = [];
        foreach ($rows as $row) {
            if (!in_array((string) ($row['status'] ?? ''), ['cancelado', 'arquivado', 'expirado'], true)) {
                $active[] = $row;
            }
        }

        return $active;
    }

    /** @return array<string, mixed>|null */
    public function activeForCollectorApplicationByTemplate(int $applicationId, int $templateId): ?array
    {
        foreach ($this->activeForCollectorApplicationList($applicationId) as $row) {
            if ((int) ($row['contract_template_id'] ?? 0) === $templateId) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function requiredCollectorSignaturesPending(int $applicationId): array
    {
        $pending = [];
        $templateModel = new ContractTemplate();
        foreach ($templateModel->findRequiredForCollectorSignatureStage() as $template) {
            $templateId = (int) ($template['id'] ?? 0);
            $request = $this->activeForCollectorApplicationByTemplate($applicationId, $templateId);
            if ($request === null || !$this->isFullySigned($request)) {
                $pending[] = [
                    'template' => $template,
                    'request'  => $request,
                ];
            }
        }

        return $pending;
    }

    public function syncCollectorApplicationSignatureStage(int $applicationId): void
    {
        $appModel = new CollectorApplication();
        $app = $appModel->findById($applicationId);
        if ($app === null) {
            return;
        }

        $requiredTemplates = (new ContractTemplate())->findRequiredForCollectorSignatureStage();
        if ($requiredTemplates === []) {
            return;
        }

        $allGenerated = true;
        $allSigned = true;
        foreach ($requiredTemplates as $template) {
            $request = $this->activeForCollectorApplicationByTemplate($applicationId, (int) ($template['id'] ?? 0));
            if ($request === null) {
                $allGenerated = false;
                $allSigned = false;
                continue;
            }
            if (!$this->isFullySigned($request)) {
                $allSigned = false;
            }
        }

        $currentStatus = (string) ($app['status'] ?? '');
        if (!$allGenerated || !$allSigned) {
            if (in_array($currentStatus, ['aprovado', 'aguardando_assinatura_contratual', 'contrato_assinado', 'acesso_preparado'], true)) {
                $appModel->update($applicationId, [
                    'status'        => 'aguardando_assinatura_contratual',
                    'access_status' => 'nao_liberado',
                ]);
            }

            return;
        }

        $appModel->update($applicationId, [
            'status'        => 'contrato_assinado',
            'access_status' => 'pendente_criacao',
        ]);
    }

    public function isFullySigned(array $request): bool
    {
        return (string) ($request['status'] ?? '') === 'assinado';
    }

    /** @return array{valid:bool, reason:?string} */
    public function validatePublicToken(array $request): array
    {
        if (empty($request['public_token'])) {
            return ['valid' => false, 'reason' => 'Link não disponível.'];
        }
        if (!empty($request['public_token_revoked_at'])) {
            return ['valid' => false, 'reason' => 'Este link foi revogado.'];
        }
        $expires = (string) ($request['public_token_expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            return ['valid' => false, 'reason' => 'Este link expirou.'];
        }
        if ((string) ($request['status'] ?? '') === 'cancelado') {
            return ['valid' => false, 'reason' => 'Este processo foi cancelado.'];
        }
        if ((string) ($request['status'] ?? '') === 'assinado') {
            return ['valid' => false, 'reason' => 'Este documento já foi assinado.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @param array<string, mixed> $application
     * @param array<string, mixed> $template
     */
    public function createForCollectorApplication(
        array $application,
        array $template,
        int|string|null $userId,
        int $daysValid = 30
    ): int {
        $appId = (int) ($application['id'] ?? 0);
        $templateId = (int) ($template['id'] ?? 0);
        $existing = $this->activeForCollectorApplicationByTemplate($appId, $templateId);
        if ($existing !== null) {
            return (int) ($existing['id'] ?? 0);
        }

        $template = ContractDocumentHelper::normalizeTemplate($template);
        $renderer = new ContractTemplateRenderer();
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $org = (array) ($config['organization'] ?? []);

        // Etapa 18C: prioriza o cadastro mestre do captador; candidatura é fallback legado.
        $collector = (new Collector())->findByApplication($appId);
        if ($collector !== null) {
            $context = ContractTemplateRenderer::contextFromCollector(
                $collector,
                $application,
                $org,
                ['contract_title' => (string) ($template['title'] ?? '')]
            );
        } else {
            $context = ContractTemplateRenderer::contextFromCollectorApplication(
                $application,
                $org,
                ['contract_title' => (string) ($template['title'] ?? '')]
            );
        }
        $rendered = $renderer->render((string) ($template['content_html'] ?? ''), $context);
        $hash = hash('sha256', $rendered);

        $token = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable())->modify('+' . $daysValid . ' days')->format('Y-m-d H:i:s');

        $this->query(
            'INSERT INTO `signature_requests`
                (`source_type`, `source_id`, `contract_template_id`, `title`, `status`,
                 `rendered_html`, `content_hash`, `public_token`, `public_token_expires_at`,
                 `sent_at`, `created_by`, `sent_by`, `created_at`)
             VALUES
                (:st, :sid, :tid, :title, :status, :html, :hash, :token, :exp, NOW(), :cuid, :suid, NOW())',
            [
                'st'     => 'collector_application',
                'sid'    => $appId,
                'tid'    => (int) ($template['id'] ?? 0),
                'title'  => (string) ($template['title'] ?? 'Autorização'),
                'status' => 'aguardando_assinatura',
                'html'   => $rendered,
                'hash'   => $hash,
                'token'  => $token,
                'exp'    => $expires,
                'cuid'   => $userId,
                'suid'   => $userId,
            ]
        );
        $requestId = (int) $this->db->lastInsertId();

        // Dados do signatário captador: usa representante legal (PJ) ou titular do cadastro mestre quando houver.
        $signerName = (string) ($application['name'] ?? '');
        $signerEmail = (string) ($application['email'] ?? '');
        $signerDoc = (string) ($application['document_number'] ?? '');
        if ($collector !== null) {
            $isPj = (string) ($collector['type'] ?? '') === 'pessoa_juridica';
            if ($isPj && trim((string) ($collector['representative_name'] ?? '')) !== '') {
                $signerName = (string) $collector['representative_name'];
                $signerEmail = (string) ($collector['representative_email'] ?? $collector['email'] ?? $signerEmail);
                $signerDoc = (string) ($collector['representative_document'] ?? $signerDoc);
            } else {
                $signerName = (string) ($collector['name'] ?? $signerName);
                $signerEmail = (string) ($collector['email'] ?? $signerEmail);
                $signerDoc = (string) ($collector['document_number'] ?? $signerDoc);
            }
        }

        $signerToken = bin2hex(random_bytes(32));
        $this->query(
            'INSERT INTO `signature_signers`
                (`signature_request_id`, `signer_name`, `signer_email`, `signer_document`, `signer_role`,
                 `status`, `public_token`, `created_at`)
             VALUES (:rid, :name, :email, :doc, :role, :status, :token, NOW())',
            [
                'rid'    => $requestId,
                'name'   => $signerName,
                'email'  => $signerEmail,
                'doc'    => $signerDoc,
                'role'   => 'captador',
                'status' => 'pendente',
                'token'  => $signerToken,
            ]
        );

        $legal = (array) ($org['legal_entity'] ?? []);
        $contratanteName = trim((string) ($legal['name'] ?? 'JA PRODUÇÕES ARTÍSTICAS LTDA'));
        $contratanteDoc = trim((string) ($legal['document'] ?? $org['document'] ?? '40.041.396/0001-30'));
        $contratanteEmail = trim((string) ($org['email'] ?? ''));
        if ($contratanteEmail === '') {
            $contratanteEmail = 'contratante@dancacarajas.com';
        }
        $this->query(
            'INSERT INTO `signature_signers`
                (`signature_request_id`, `signer_name`, `signer_email`, `signer_document`, `signer_role`,
                 `status`, `public_token`, `created_at`)
             VALUES (:rid, :name, :email, :doc, :role, :status, NULL, NOW())',
            [
                'rid'    => $requestId,
                'name'   => $contratanteName,
                'email'  => $contratanteEmail,
                'doc'    => $contratanteDoc,
                'role'   => 'contratante',
                'status' => 'pendente',
            ]
        );

        $this->autoSignContratante($requestId, $userId);
        $this->syncCollectorApplicationSignatureStage($appId);

        return $requestId;
    }

    /** Assina automaticamente em nome da contratante (JA Produções) ao gerar o contrato. */
    public function autoSignContratante(int $requestId, int|string|null $triggeredByUserId = null): void
    {
        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $contractConfig = (array) ($config['contract'] ?? []);
        if (!($contractConfig['auto_sign_contratante'] ?? true)) {
            return;
        }

        $contratante = $this->findContratanteSigner($requestId);
        if ($contratante === null || (string) ($contratante['status'] ?? '') === 'assinado') {
            return;
        }

        $legalName = trim((string) ($contratante['signer_name'] ?? 'JA PRODUÇÕES ARTÍSTICAS LTDA'));
        $acceptanceText = 'Assinatura eletrônica automática da CONTRATANTE (' . $legalName
            . '), registrada pelo sistema Dança Carajás Captação na geração do contrato, conforme política institucional da JA Produções Artísticas.';

        $this->sign(
            (int) $contratante['id'],
            $legalName,
            $acceptanceText,
            ip: '127.0.0.1',
            userAgent: 'DCC/automatica-japroducoes'
        );

        (new ActivityLog())->record('signature_contratante_auto_signed', $triggeredByUserId, 'signature_request', $requestId);
    }

    /** @return array<string, mixed>|null */
    public function findContratanteSigner(int $requestId): ?array
    {
        $row = $this->query(
            'SELECT * FROM `signature_signers`
              WHERE `signature_request_id` = :rid AND `signer_role` = :role LIMIT 1',
            ['rid' => $requestId, 'role' => 'contratante']
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function signedSignersForRequest(int $requestId): array
    {
        return array_values(array_filter(
            $this->signersForRequest($requestId),
            static fn (array $s): bool => (string) ($s['status'] ?? '') === 'assinado'
        ));
    }

    /** @return array<string, mixed>|null */
    public function findSignerByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $row = $this->query(
            'SELECT ss.*, sr.`rendered_html`, sr.`title` AS request_title, sr.`status` AS request_status,
                    sr.`content_hash`, sr.`public_token_revoked_at`, sr.`public_token_expires_at`, sr.`id` AS request_id
               FROM `signature_signers` ss
               JOIN `signature_requests` sr ON sr.`id` = ss.`signature_request_id`
              WHERE ss.`public_token` = :t LIMIT 1',
            ['t' => $token]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array{valid:bool, reason:?string} */
    public function validateSignerToken(array $signer): array
    {
        if ((string) ($signer['status'] ?? '') === 'assinado') {
            return ['valid' => false, 'reason' => 'Este documento já foi assinado.'];
        }
        if ((string) ($signer['request_status'] ?? '') === 'cancelado') {
            return ['valid' => false, 'reason' => 'Este processo foi cancelado.'];
        }
        if (!empty($signer['public_token_revoked_at'])) {
            return ['valid' => false, 'reason' => 'Este link foi revogado.'];
        }
        $expires = (string) ($signer['public_token_expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            return ['valid' => false, 'reason' => 'Este link expirou.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function markSignerViewed(int $signerId): void
    {
        $this->query(
            "UPDATE `signature_signers` SET `status` = 'visualizado', `updated_at` = NOW()
              WHERE `id` = :id AND `status` = 'pendente'",
            ['id' => $signerId]
        );
    }

    public function sign(
        int $signerId,
        string $confirmedName,
        string $acceptanceText,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        $signer = $this->query('SELECT * FROM `signature_signers` WHERE `id` = :id', ['id' => $signerId])->fetch();
        if ($signer === false) {
            throw new RuntimeException('Signatário não encontrado.');
        }
        $requestId = (int) ($signer['signature_request_id'] ?? 0);
        $request = $this->findById($requestId);
        if ($request === null) {
            throw new RuntimeException('Processo não encontrado.');
        }

        $hashInput = (string) ($request['content_hash'] ?? '') . '|' . $confirmedName . '|' . date('c');
        $sigHash = hash('sha256', $hashInput);

        $signedIp = $ip ?? (function_exists('client_ip') ? client_ip() : ($_SERVER['REMOTE_ADDR'] ?? ''));
        $signedUa = $userAgent ?? substr((function_exists('user_agent') ? user_agent() : (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) ?: 'unknown', 0, 255);

        $this->query(
            'UPDATE `signature_signers`
                SET `status` = :st, `signed_at` = NOW(), `signed_ip` = :ip, `signed_user_agent` = :ua,
                    `signature_hash` = :sh, `acceptance_text` = :txt, `updated_at` = NOW()
              WHERE `id` = :id',
            [
                'st'  => 'assinado',
                'ip'  => $signedIp,
                'ua'  => $signedUa,
                'sh'  => $sigHash,
                'txt' => $acceptanceText,
                'id'  => $signerId,
            ]
        );

        $pending = (int) ($this->query(
            "SELECT COUNT(*) FROM `signature_signers`
              WHERE `signature_request_id` = :rid AND `status` NOT IN ('assinado','cancelado')",
            ['rid' => $requestId]
        )->fetchColumn() ?: 0);

        $newStatus = $pending === 0 ? 'assinado' : 'parcialmente_assinado';
        $this->query(
            'UPDATE `signature_requests`
                SET `status` = :st, `signed_at` = CASE WHEN :st2 = \'assinado\' THEN NOW() ELSE `signed_at` END, `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => $newStatus, 'st2' => $newStatus, 'id' => $requestId]
        );

        if ($newStatus === 'assinado' && (string) ($request['source_type'] ?? '') === 'collector_application') {
            $this->syncCollectorApplicationSignatureStage((int) $request['source_id']);
        }

        if ($newStatus === 'assinado') {
            $this->generateSignedPdf($requestId);
        }
    }

    public function generateSignedPdf(int $requestId, bool $force = false): ?string
    {
        $request = $this->findById($requestId);
        if ($request === null || (string) ($request['status'] ?? '') !== 'assinado') {
            return null;
        }

        $existing = (string) ($request['signed_pdf_path'] ?? '');
        if (!$force && $existing !== '' && is_file($existing)) {
            return $existing;
        }

        if ($force && $existing !== '' && is_file($existing)) {
            @unlink($existing);
        }

        $signedSigners = $this->signedSignersForRequest($requestId);
        if ($signedSigners === []) {
            return null;
        }

        $stored = (new SignaturePdfGenerator())->generateAndStore($request, $signedSigners);
        $this->query(
            'UPDATE `signature_requests`
                SET `signed_pdf_path` = :path, `signed_pdf_original_name` = :name, `updated_at` = NOW()
              WHERE `id` = :id',
            [
                'path' => $stored['path'],
                'name' => $stored['original_name'],
                'id'   => $requestId,
            ]
        );

        return $stored['path'];
    }

    /** @return array{path:string,name:string}|null */
    public function resolveSignedPdfDownload(int $requestId): ?array
    {
        $request = $this->findById($requestId);
        if ($request === null) {
            return null;
        }

        $path = (string) ($request['signed_pdf_path'] ?? '');
        if ($path === '' || !is_file($path) || $this->signedPdfNeedsRegeneration($path)) {
            $path = (string) ($this->generateSignedPdf($requestId, true) ?? '');
        }
        if ($path === '' || !is_file($path)) {
            return null;
        }

        return [
            'path' => $path,
            'name' => (string) ($request['signed_pdf_original_name'] ?? 'contrato-assinado.pdf'),
        ];
    }

    private function signedPdfNeedsRegeneration(string $path): bool
    {
        if (!is_file($path)) {
            return true;
        }

        $head = file_get_contents($path, false, null, 0, 8192);

        return $head === false
            || !str_starts_with($head, '%PDF')
            || !str_contains($head, 'Comprovante de assinatura');
    }

    /** @return array{path:string,name:string}|null */
    public function resolveSignedPdfBySignerToken(string $token): ?array
    {
        $signer = $this->findSignerByToken($token);
        if ($signer === null || (string) ($signer['status'] ?? '') !== 'assinado') {
            return null;
        }

        return $this->resolveSignedPdfDownload((int) ($signer['request_id'] ?? 0));
    }

    public function cancel(int $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `signature_requests`
                SET `status` = :st, `cancelled_at` = NOW(), `cancelled_by` = :uid, `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => 'cancelado', 'uid' => $userId, 'id' => $id]
        );
        $this->query(
            "UPDATE `signature_signers` SET `status` = 'cancelado', `updated_at` = NOW()
              WHERE `signature_request_id` = :id AND `status` NOT IN ('assinado')",
            ['id' => $id]
        );
    }

    public function send(int $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `signature_requests` SET `status` = :st, `sent_at` = NOW(), `sent_by` = :uid, `updated_at` = NOW() WHERE `id` = :id',
            ['st' => 'aguardando_assinatura', 'uid' => $userId, 'id' => $id]
        );
    }

    public function archive(int $id): void
    {
        $this->query(
            'UPDATE `signature_requests` SET `archived_at` = NOW(), `status` = :st, `updated_at` = NOW() WHERE `id` = :id',
            ['st' => 'arquivado', 'id' => $id]
        );
    }

    public function restore(int $id): void
    {
        $this->query(
            'UPDATE `signature_requests` SET `archived_at` = NULL, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->buildWhere($filters);

        return $this->query(
            "SELECT * FROM `signature_requests` WHERE {$where} ORDER BY `id` DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();
    }

    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);

        return (int) ($this->query("SELECT COUNT(*) FROM `signature_requests` WHERE {$where}", $params)->fetchColumn() ?: 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function signersForRequest(int $requestId): array
    {
        return $this->query(
            'SELECT * FROM `signature_signers` WHERE `signature_request_id` = :id ORDER BY `id` ASC',
            ['id' => $requestId]
        )->fetchAll();
    }

    /** @param array<string, mixed> $filters @return array{0:string,1:array<string,mixed>} */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];
        if (empty($filters['show_archived'])) {
            $conditions[] = '`archived_at` IS NULL';
        }
        foreach (['status', 'source_type'] as $key) {
            $val = trim((string) ($filters[$key] ?? ''));
            if ($val !== '') {
                $conditions[] = '`' . $key . '` = :' . $key;
                $params[$key] = $val;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }
}
