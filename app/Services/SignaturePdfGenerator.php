<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ContractDocumentHelper;

/** Gera PDF do contrato assinado com template institucional premium. */
final class SignaturePdfGenerator
{
    /**
     * @param array<string, mixed> $request
     * @param list<array<string, mixed>> $signedSigners
     * @return array{path:string,original_name:string}
     */
    public function generateAndStore(array $request, array $signedSigners): array
    {
        $requestId = (int) ($request['id'] ?? 0);
        if ($requestId <= 0) {
            throw new \RuntimeException('Processo de assinatura inválido.');
        }
        if ($signedSigners === []) {
            throw new \RuntimeException('Nenhuma assinatura registrada para gerar o PDF.');
        }

        $title = ContractDocumentHelper::normalizeDocumentText(trim((string) ($request['title'] ?? 'Contrato')));
        $safeTitle = preg_replace('/[^aA-Z0-9._\-]+/', '_', $title) ?: 'contrato';
        $originalName = $safeTitle . '-assinado.pdf';

        $dir = dirname(__DIR__, 2) . '/storage/uploads/signatures/' . date('Y/m');
        $absolutePath = $dir . '/sig-' . $requestId . '-' . bin2hex(random_bytes(8)) . '.pdf';

        $config = require dirname(__DIR__, 2) . '/config/app.php';
        $org = (array) ($config['organization'] ?? []);
        $legal = (array) ($org['legal_entity'] ?? []);
        $brandingCfg = (array) ($org['branding'] ?? []);
        $publicRoot = dirname(__DIR__, 2) . '/public/';

        $producerName = trim((string) ($legal['trade_name'] ?? $legal['name'] ?? 'JA Produções'));
        $producerRole = trim((string) ($legal['role'] ?? 'Responsável jurídica pela contratação'));
        $producerLegalLine = $producerName . ($producerRole !== '' ? ' — ' . $producerRole : '');

        $lastSignedAt = '';
        foreach ($signedSigners as $signer) {
            $at = (string) ($signer['signed_at'] ?? '');
            if ($at !== '' && ($lastSignedAt === '' || strcmp($at, $lastSignedAt) > 0)) {
                $lastSignedAt = $at;
            }
        }

        $pdf = new ContractPdfDocument();
        $pdf->setBranding([
            'producer_legal_line' => $producerLegalLine,
            'footer_line'         => (string) ($config['name'] ?? 'Dança Carajás Captação'),
            'festival_logo_path'  => $publicRoot . ltrim((string) ($brandingCfg['festival_logo'] ?? ''), '/'),
            'producer_logo_path'  => $publicRoot . ltrim((string) ($brandingCfg['producer_logo'] ?? ''), '/'),
        ]);
        $pdf->setMeta([
            'title'     => $title,
            'reference' => $this->buildReference($request),
            'issued_at' => $lastSignedAt !== '' ? format_datetime_br($lastSignedAt) : (new DateTimeImmutable('now', app_timezone()))->format('d/m/Y H:i:s'),
        ]);

        $pdf->addSpacer(2.0);
        foreach ($this->htmlToBlocks(ContractDocumentHelper::normalizeDocumentText((string) ($request['rendered_html'] ?? ''))) as $block) {
            if ($block['type'] === 'heading') {
                $pdf->addHeading($block['text']);
            } else {
                $pdf->addParagraph($block['text']);
            }
        }

        foreach ($signedSigners as $signer) {
            $signerToken = (string) ($signer['public_token'] ?? '');
            $auditUrl = $signerToken !== ''
                ? app_url('/assinatura/' . rawurlencode($signerToken) . '/auditoria')
                : '';

            $signatureHash = (string) ($signer['signature_hash'] ?? '');
            $role = (string) ($signer['signer_role'] ?? '');
            $roleLabel = match ($role) {
                'contratante' => 'Assinatura da contratante',
                'captador'    => 'Assinatura do captador externo',
                default       => 'Comprovante de assinatura eletronica',
            };

            $pdf->addSignatureStamp([
                'role_label'        => $roleLabel,
                'signer_name'       => (string) ($signer['signer_name'] ?? ''),
                'signer_document'   => ContractDocumentHelper::maskDocument((string) ($signer['signer_document'] ?? '')),
                'signed_at'         => format_datetime_br((string) ($signer['signed_at'] ?? '')),
                'verification_code' => ContractDocumentHelper::formatVerificationCode($signatureHash),
                'audit_url'         => $auditUrl,
                'content_hash'      => (string) ($request['content_hash'] ?? ''),
                'signature_hash'    => $signatureHash,
                'signed_ip'         => (string) ($signer['signed_ip'] ?? '—'),
                'acceptance_text'   => (string) ($signer['acceptance_text'] ?? ''),
            ]);
        }

        $pdf->saveToFile($absolutePath);

        return ['path' => $absolutePath, 'original_name' => $originalName];
    }

    /** @param array<string, mixed> $request */
    private function buildReference(array $request): string
    {
        $parts = [];
        $sourceType = (string) ($request['source_type'] ?? '');
        $sourceId = (int) ($request['source_id'] ?? 0);
        if ($sourceType === 'collector_application' && $sourceId > 0) {
            $parts[] = 'Candidatura nº ' . $sourceId;
        } elseif ($sourceId > 0) {
            $parts[] = 'Processo nº ' . $sourceId;
        }
        $requestId = (int) ($request['id'] ?? 0);
        if ($requestId > 0) {
            $parts[] = 'Assinatura nº ' . $requestId;
        }

        return implode(' · ', $parts);
    }

    /** @return list<array{type:string,text:string}> */
    private function htmlToBlocks(string $html): array
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $html) ?? $html;
        $html = preg_replace('/<(br|BR)\s*\/?>/', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(p|div|li|h1|h2|h3|h4|tr)>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<(h1|h2|h3|h4)[^>]*>/i', "\n## ", $html) ?? $html;

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace("/\r\n|\r/", "\n", $plain) ?? $plain;
        $plain = preg_replace("/\n{3,}/", "\n\n", $plain) ?? $plain;

        $blocks = [];
        foreach (preg_split("/\n/", trim($plain)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $blocks[] = str_starts_with($line, '## ')
                ? ['type' => 'heading', 'text' => trim(substr($line, 3))]
                : ['type' => 'paragraph', 'text' => $line];
        }

        return $blocks;
    }
}
