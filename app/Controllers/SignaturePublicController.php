<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\ContractDocumentHelper;
use App\Models\ActivityLog;
use App\Models\CollectorApplication;
use App\Models\SignatureRequest;
use App\Services\EmailEventService;

final class SignaturePublicController extends Controller
{
    public function show(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $signer = $model->findSignerByToken($token);

        if ($signer === null) {
            $this->renderError('Link inválido', 'Não encontramos este documento para assinatura.');
            return;
        }

        $check = $model->validateSignerToken($signer);
        if (!$check['valid']) {
            if ((string) ($signer['status'] ?? '') === 'assinado') {
                $request = $model->findById((int) ($signer['request_id'] ?? 0));
                $fullySigned = is_array($request) && (string) ($request['status'] ?? '') === 'assinado';
                $this->view('signatures/public/signed', [
                    'title'       => $fullySigned ? 'Assinatura concluída' : 'Assinatura registrada',
                    'signer'      => $signer,
                    'fullySigned' => $fullySigned,
                    'documentUrl' => $fullySigned ? $this->documentUrl($token) : '',
                    'auditUrl'    => $fullySigned ? $this->auditUrl($token) : '',
                    'publicJourneyUrl' => $this->publicJourneyUrlForSigner($signer),
                ], 'layouts/print');
                return;
            }
            $this->renderError('Link indisponível', (string) $check['reason']);
            return;
        }

        $model->markSignerViewed((int) $signer['id']);
        (new ActivityLog())->record('signature_request_viewed', null, 'signature_request', (int) ($signer['request_id'] ?? 0));

        $this->view('signatures/public/sign', [
            'title'          => $signer['request_title'] ?? 'Assinatura eletrônica',
            'signer'         => $signer,
            'renderedHtml'   => (string) ($signer['rendered_html'] ?? ''),
            'maskedDocument' => $this->maskDocument((string) ($signer['signer_document'] ?? '')),
        ], 'layouts/print');
    }

    public function sign(array $params): void
    {
        csrf_verify();
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $signer = $model->findSignerByToken($token);

        if ($signer === null) {
            $this->renderError('Link inválido', 'Não encontramos este documento para assinatura.');
            return;
        }

        $check = $model->validateSignerToken($signer);
        if (!$check['valid']) {
            $this->renderError('Link indisponível', (string) $check['reason']);
            return;
        }

        $accept = input('accept_terms') === '1';
        $confirmedName = trim((string) input('confirmed_name', ''));
        $expectedName = trim((string) ($signer['signer_name'] ?? ''));

        $errors = [];
        if (!$accept) {
            $errors['accept_terms'] = 'É necessário aceitar os termos.';
        }
        if ($confirmedName === '') {
            $errors['confirmed_name'] = 'Digite seu nome completo para confirmar.';
        } elseif (mb_strtolower($confirmedName) !== mb_strtolower($expectedName)) {
            $errors['confirmed_name'] = 'O nome informado não confere com o cadastro.';
        }

        if ($errors !== []) {
            http_response_code(422);
            $this->view('signatures/public/sign', [
                'title'          => $signer['request_title'] ?? 'Assinatura eletrônica',
                'signer'         => $signer,
                'renderedHtml'   => (string) ($signer['rendered_html'] ?? ''),
                'maskedDocument' => $this->maskDocument((string) ($signer['signer_document'] ?? '')),
                'errors'         => $errors,
                'old'            => ['confirmed_name' => $confirmedName, 'accept_terms' => $accept],
            ], 'layouts/print');
            return;
        }

        $acceptanceText = 'Li e concordo com os termos deste documento. Assinado eletronicamente por ' . $confirmedName . '.';
        $model->sign((int) $signer['id'], $confirmedName, $acceptanceText);

        (new ActivityLog())->record('signature_request_signed', null, 'signature_request', (int) ($signer['request_id'] ?? 0));
        if ((string) ($signer['signer_role'] ?? '') === 'captador') {
            $signedRequest = (new SignatureRequest())->findById((int) ($signer['request_id'] ?? 0));
            $applicationId = (int) ($signedRequest['source_id'] ?? 0);
            (new ActivityLog())->record('collector_contract_signed', null, 'collector_application', $applicationId);
            $application = $applicationId > 0 ? (new CollectorApplication())->findById($applicationId) : null;
            if ($application !== null) {
                (new EmailEventService())->sendToCollector('collector_contract_signed', $application);
                (new EmailEventService())->sendToTeam('collector_contract_signed_internal', $application);
            }
        }

        $request = $model->findById((int) ($signer['request_id'] ?? 0));
        $fullySigned = is_array($request) && (string) ($request['status'] ?? '') === 'assinado';
        if ($fullySigned && (string) ($request['source_type'] ?? '') === 'collector_application') {
            $application = (new CollectorApplication())->findById((int) ($request['source_id'] ?? 0));
            if ($application !== null) {
                (new EmailEventService())->sendToCollector('collector_contract_fully_signed', $application, [
                    'signature_url' => $this->documentUrl($token),
                ]);
            }
        }

        header('Location: ' . app_url('/assinatura/' . rawurlencode($token)), true, 303);
        exit;
    }

    public function documento(array $params): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $signer = $model->findSignerByToken($token);

        if ($signer === null || (string) ($signer['status'] ?? '') !== 'assinado') {
            $this->renderError('Documento indisponível', 'Este contrato ainda não foi assinado ou o link é inválido.');
            return;
        }

        $request = $model->findById((int) ($signer['request_id'] ?? 0));
        if ($request === null || (string) ($request['status'] ?? '') !== 'assinado') {
            $this->renderError('Documento indisponível', 'Este contrato ainda não foi assinado por todas as partes ou o link é inválido.');
            return;
        }

        $signedSigners = $model->signedSignersForRequest((int) $request['id']);
        $allSigners = $model->signersForRequest((int) $request['id']);
        $pendingCount = count(array_filter(
            $allSigners,
            static fn (array $s): bool => !in_array((string) ($s['status'] ?? ''), ['assinado', 'cancelado'], true)
        ));
        if ($pendingCount > 0) {
            $this->renderError('Documento indisponível', 'Aguardando assinatura de todas as partes (captador e contratante).');
            return;
        }
        if ($signedSigners === []) {
            $this->renderError('Documento indisponível', 'Nenhuma assinatura registrada.');
            return;
        }

        $docTitle = ContractDocumentHelper::normalizeDocumentText((string) ($request['title'] ?? 'Documento assinado'));
        $renderedHtml = ContractDocumentHelper::normalizeDocumentText((string) ($request['rendered_html'] ?? ''));

        $this->view('signatures/public/document', [
            'title'         => $docTitle,
            'request'       => array_merge($request, [
                'title'     => $docTitle,
                'reference' => $this->buildReference($request),
            ]),
            'signer'        => $signer,
            'signedSigners' => $signedSigners,
            'renderedHtml'  => $renderedHtml,
            'auditUrl'     => $this->auditUrl($token),
            'documentUrl'  => $this->documentUrl($token),
        ], 'layouts/print');
    }

    public function auditoria(array $params): void
    {
        header('Content-Type: text/html; charset=UTF-8');
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $signer = $model->findSignerByToken($token);

        if ($signer === null) {
            $this->renderError('Auditoria indisponível', 'Registro de assinatura não encontrado.');
            return;
        }

        $request = $model->findById((int) ($signer['request_id'] ?? 0));
        $verified = (string) ($signer['status'] ?? '') === 'assinado'
            && !empty($signer['signature_hash'])
            && !empty($request['content_hash']);

        $this->view('signatures/public/audit', [
            'title'       => 'Auditoria de assinatura',
            'signer'      => $signer,
            'request'     => $request ?? [],
            'verified'    => $verified,
            'documentUrl' => $this->documentUrl($token),
        ], 'layouts/print');
    }

    public function pdf(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $file  = $model->resolveSignedPdfBySignerToken($token);

        if ($file === null) {
            $this->renderError('PDF indisponível', 'O contrato assinado ainda não está disponível.');
            return;
        }

        $this->streamPdf($file['path'], $file['name'], inline: true);
    }

    public function pdfDownload(array $params): void
    {
        $token = trim((string) ($params['token'] ?? ''));
        $model = new SignatureRequest();
        $file  = $model->resolveSignedPdfBySignerToken($token);

        if ($file === null) {
            $this->renderError('PDF indisponível', 'O contrato assinado ainda não está disponível.');
            return;
        }

        $this->streamPdf($file['path'], $file['name'], inline: false);
    }

    private function streamPdf(string $path, string $downloadName, bool $inline): void
    {
        if (!is_file($path)) {
            $this->renderError('PDF indisponível', 'Arquivo não encontrado.');
            return;
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._\-]+/', '_', $downloadName) ?: 'contrato-assinado.pdf';
        if (!str_ends_with(strtolower($safeName), '.pdf')) {
            $safeName .= '.pdf';
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($safeName));
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($path);
        exit;
    }

    private function documentUrl(string $token): string
    {
        return app_url('/assinatura/' . rawurlencode($token) . '/documento');
    }

    private function auditUrl(string $token): string
    {
        return app_url('/assinatura/' . rawurlencode($token) . '/auditoria');
    }

    private function publicJourneyUrlForSigner(array $signer): string
    {
        $requestId = (int) ($signer['request_id'] ?? 0);
        if ($requestId <= 0) {
            return '';
        }

        $request = (new SignatureRequest())->findById($requestId);
        if ($request === null || (string) ($request['source_type'] ?? '') !== 'collector_application') {
            return '';
        }

        $applicationId = (int) ($request['source_id'] ?? 0);
        if ($applicationId <= 0) {
            return '';
        }

        $appModel = new CollectorApplication();
        $application = $appModel->findById($applicationId);
        if ($application === null) {
            return '';
        }

        $token = trim((string) ($application['public_token'] ?? ''));
        if ($token === '' || !$appModel->validatePublicToken($application)['valid']) {
            $token = $appModel->generatePublicToken($applicationId, 30);
        }

        return app_url('/captadores/credenciamento/' . rawurlencode($token));
    }

    /** @param array<string, mixed> $request */
    private function buildReference(array $request): string
    {
        $parts = [];
        if ((string) ($request['source_type'] ?? '') === 'collector_application' && (int) ($request['source_id'] ?? 0) > 0) {
            $parts[] = 'Candidatura nº ' . (int) $request['source_id'];
        }
        if ((int) ($request['id'] ?? 0) > 0) {
            $parts[] = 'Assinatura nº ' . (int) $request['id'];
        }

        return implode(' · ', $parts);
    }

    private function maskDocument(string $doc): string
    {
        $digits = preg_replace('/\D+/', '', $doc) ?? '';
        if (strlen($digits) === 11) {
            return '***.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-**';
        }
        if (strlen($digits) === 14) {
            return '**.' . substr($digits, 2, 3) . '.' . substr($digits, 5, 3) . '/****-**';
        }

        return $doc !== '' ? '***' : '—';
    }

    private function renderError(string $title, string $message): void
    {
        $this->view('signatures/public/error', [
            'title'   => $title,
            'heading' => $title,
            'message' => $message,
        ], 'layouts/print');
    }
}
