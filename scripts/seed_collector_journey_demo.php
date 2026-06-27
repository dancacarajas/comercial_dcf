<?php

declare(strict_types=1);

/**
 * Cria candidaturas demo — uma por etapa da esteira — e imprime os links.
 * Uso: docker exec dcc_app php /var/www/html/scripts/seed_collector_journey_demo.php
 */

$root = dirname(__DIR__);

require $root . '/app/Core/App.php';
(new App\Core\App($root))->boot();

$pdo = App\Core\Database::connection();
$model = new App\Models\CollectorApplication();
$docModel = new App\Models\CollectorApplicationDocument();

$baseUrl = 'http://localhost:8080';
$demoPrefix = 'demo.esteira.';

/** @var list<array{slug:string,step:string,label:string,name:string,email:string,status:string,document_status?:string,review_status?:string,access_status?:string,docs?:list<array{status:string}>,with_token:bool,with_slots:bool}> */
$scenarios = [
    [
        'slug'   => 'manifestacao',
        'step'   => 'manifestacao',
        'label'  => '1. Manifestação',
        'name'   => 'Demo Esteira — Manifestação',
        'email'  => $demoPrefix . 'manifestacao@example.com',
        'status' => 'em_triagem',
        'document_status' => 'nao_solicitado',
        'review_status'   => 'pendente',
        'with_token' => true,
        'with_slots' => false,
    ],
    [
        'slug'   => 'documentos',
        'step'   => 'documentos',
        'label'  => '2. Envio documental',
        'name'   => 'Demo Esteira — Documentos',
        'email'  => $demoPrefix . 'documentos@example.com',
        'status' => 'documentos_solicitados',
        'document_status' => 'solicitado',
        'review_status'   => 'pendente',
        'with_token' => true,
        'with_slots' => true,
        'docs' => [
            ['status' => 'pendente'],
            ['status' => 'pendente'],
        ],
    ],
    [
        'slug'   => 'analise',
        'step'   => 'analise',
        'label'  => '3. Análise documental',
        'name'   => 'Demo Esteira — Análise',
        'email'  => $demoPrefix . 'analise@example.com',
        'status' => 'em_analise_documental',
        'document_status' => 'enviado',
        'review_status'   => 'em_analise',
        'with_token' => true,
        'with_slots' => true,
        'docs' => [
            ['status' => 'em_analise'],
            ['status' => 'enviado'],
        ],
    ],
    [
        'slug'   => 'aprovacao',
        'step'   => 'aprovacao',
        'label'  => '4. Aprovação',
        'name'   => 'Demo Esteira — Aprovação',
        'email'  => $demoPrefix . 'aprovacao@example.com',
        'status' => 'aprovado',
        'document_status' => 'aprovado',
        'review_status'   => 'aprovado',
        'with_token' => true,
        'with_slots' => true,
        'docs' => [
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
        ],
    ],
    [
        'slug'   => 'assinatura',
        'step'   => 'assinatura',
        'label'  => '5. Assinatura contratual',
        'name'   => 'Demo Esteira — Assinatura',
        'email'  => $demoPrefix . 'assinatura@example.com',
        'status' => 'aguardando_assinatura_contratual',
        'document_status' => 'aprovado',
        'review_status'   => 'aprovado',
        'access_status'   => 'nao_liberado',
        'with_token' => true,
        'with_slots' => true,
        'with_signature' => true,
        'docs' => [
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
        ],
    ],
    [
        'slug'   => 'contrato_assinado',
        'step'   => 'acesso',
        'label'  => '6a. Contrato assinado (aguardando liberação)',
        'name'   => 'Demo Esteira — Contrato assinado',
        'email'  => $demoPrefix . 'contrato@example.com',
        'status' => 'contrato_assinado',
        'document_status' => 'aprovado',
        'review_status'   => 'aprovado',
        'access_status'   => 'pendente_criacao',
        'with_token' => true,
        'with_slots' => true,
        'with_signature' => 'signed',
        'docs' => [
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
        ],
    ],
    [
        'slug'   => 'acesso',
        'step'   => 'acesso',
        'label'  => '6. Liberação de acesso',
        'name'   => 'Demo Esteira — Acesso liberado',
        'email'  => $demoPrefix . 'acesso@example.com',
        'status' => 'acesso_liberado',
        'document_status' => 'aprovado',
        'review_status'   => 'aprovado',
        'access_status'   => 'acesso_liberado',
        'with_token' => true,
        'with_slots' => true,
        'docs' => [
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
        ],
    ],
    [
        'slug'   => 'reprovado',
        'step'   => 'aprovacao',
        'label'  => 'Extra — Reprovado',
        'name'   => 'Demo Esteira — Reprovado',
        'email'  => $demoPrefix . 'reprovado@example.com',
        'status' => 'reprovado',
        'document_status' => 'reprovado',
        'review_status'   => 'reprovado',
        'with_token' => true,
        'with_slots' => true,
        'docs' => [
            ['status' => 'reprovado'],
            ['status' => 'aprovado'],
        ],
    ],
    [
        'slug'   => 'arquivado',
        'step'   => 'acesso',
        'label'  => 'Extra — Arquivado',
        'name'   => 'Demo Esteira — Arquivado',
        'email'  => $demoPrefix . 'arquivado@example.com',
        'status' => 'arquivado',
        'document_status' => 'aprovado',
        'review_status'   => 'aprovado',
        'with_token' => true,
        'with_slots' => true,
        'archived' => true,
        'docs' => [
            ['status' => 'aprovado'],
            ['status' => 'aprovado'],
        ],
    ],
];

$docTypesPf = ['identidade', 'comprovante_endereco', 'comprovante_bancario'];
$docTypesCnpj = ['cartao_cnpj', 'contrato_social_ou_mei', 'comprovante_bancario', 'documento_representante', 'identidade', 'comprovante_endereco'];

foreach ($scenarios as $scenario) {
    $email = $scenario['email'];
    $st = $pdo->prepare('SELECT id FROM collector_applications WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $existingId = $st->fetchColumn();

    if ($existingId) {
        $appId = (int) $existingId;
        $pdo->prepare('DELETE FROM collector_application_documents WHERE collector_application_id = ?')->execute([$appId]);
        $model->update($appId, [
            'name' => $scenario['name'],
            'status' => $scenario['status'],
            'document_status' => $scenario['document_status'] ?? 'nao_solicitado',
            'review_status' => $scenario['review_status'] ?? 'pendente',
            'access_status' => $scenario['access_status'] ?? 'nao_liberado',
            'public_token_revoked_at' => null,
            'documents_requested_at' => !empty($scenario['with_slots']) ? date('Y-m-d H:i:s', strtotime('-3 days')) : null,
            'documents_submitted_at' => in_array($scenario['step'], ['analise', 'aprovacao', 'assinatura', 'acesso'], true)
                ? date('Y-m-d H:i:s', strtotime('-2 days')) : null,
            'approved_at' => in_array($scenario['step'], ['aprovacao', 'assinatura', 'acesso'], true)
                ? date('Y-m-d H:i:s', strtotime('-1 day')) : null,
            'access_released_at' => ($scenario['status'] ?? '') === 'acesso_liberado'
                ? date('Y-m-d H:i:s') : null,
        ]);
        if (!empty($scenario['archived'])) {
            $model->archive($appId);
        } else {
            $pdo->prepare('UPDATE collector_applications SET archived_at = NULL WHERE id = ?')->execute([$appId]);
        }
    } else {
        $appId = (int) $model->create([
            'source' => 'manual',
            'name' => $scenario['name'],
            'email' => $email,
            'phone_whatsapp' => '94999990000',
            'document_number' => '52998224725',
            'city_state' => 'Belém/PA',
            'rouanet_experience' => 'intermediaria',
            'segments' => 'Cultura',
            'status' => $scenario['status'],
            'document_status' => $scenario['document_status'] ?? 'nao_solicitado',
            'review_status' => $scenario['review_status'] ?? 'pendente',
            'access_status' => $scenario['access_status'] ?? 'nao_liberado',
            'consent_contact' => 1,
            'consent_lgpd_at' => date('Y-m-d H:i:s'),
        ]);
        if (!empty($scenario['archived'])) {
            $model->archive($appId);
        }
    }

    if (!empty($scenario['with_slots'])) {
        $docTypes = !empty($scenario['is_cnpj']) ? $docTypesCnpj : $docTypesPf;
        $docModel->createSlots($appId, $docTypes);
        $slots = $docModel->findByApplication($appId);
        foreach ($slots as $i => $slot) {
            $docStatus = $scenario['docs'][$i]['status'] ?? 'pendente';
            $slotId = (int) $slot['id'];
            $updates = ['status' => $docStatus];
            if (in_array($docStatus, ['enviado', 'em_analise', 'aprovado', 'reprovado'], true)) {
                $updates['uploaded_at'] = date('Y-m-d H:i:s', strtotime('-2 days'));
                $updates['uploaded_original_name'] = 'demo-' . $scenario['slug'] . '.pdf';
                $updates['uploaded_stored_name'] = 'demo-' . $scenario['slug'] . '-' . $slotId . '.pdf';
                $updates['file_extension'] = 'pdf';
                $updates['file_mime'] = 'application/pdf';
                $updates['file_size'] = 1024;
                $updates['checksum'] = hash('sha256', 'demo-' . $scenario['slug'] . '-' . $slotId);
            }
            $sets = [];
            $params = ['id' => $slotId];
            foreach ($updates as $col => $val) {
                $sets[] = "`{$col}` = :{$col}";
                $params[$col] = $val;
            }
            $pdo->prepare('UPDATE collector_application_documents SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        }
    }

    if (!empty($scenario['with_token'])) {
        $current = $model->findById($appId);
        $needsToken = empty($current['public_token'])
            || !empty($current['public_token_revoked_at'])
            || (
                !empty($current['public_token_expires_at'])
                && strtotime((string) $current['public_token_expires_at']) < time()
            );
        if ($needsToken) {
            $model->generatePublicToken($appId, 90);
        }
    }

    // Demo de acesso liberado: sempre sem usuário vinculado (formulário público disponível)
    if (($scenario['slug'] ?? '') === 'acesso') {
        $pdo->prepare('UPDATE collector_applications SET user_created_id = NULL WHERE id = ?')->execute([$appId]);
    }

    if (!empty($scenario['with_signature'])) {
        $pdo->prepare('DELETE FROM signature_signers WHERE signature_request_id IN (SELECT id FROM signature_requests WHERE source_type = ? AND source_id = ?)')->execute(['collector_application', $appId]);
        $pdo->prepare('DELETE FROM signature_requests WHERE source_type = ? AND source_id = ?')->execute(['collector_application', $appId]);
        $appRow = $model->findById($appId);
        $tpl = (new App\Models\ContractTemplate())->findDefaultForType('contrato_captador')
            ?? (new App\Models\ContractTemplate())->findDefaultForType('autorizacao_captador');
        if (is_array($appRow) && is_array($tpl)) {
            $reqId = (new App\Models\SignatureRequest())->createForCollectorApplication($appRow, $tpl, null);
            if ($scenario['with_signature'] === 'signed') {
                $signers = (new App\Models\SignatureRequest())->signersForRequest($reqId);
                if ($signers !== []) {
                    foreach ($signers as $signerRow) {
                        if (($signerRow['signer_role'] ?? '') === 'captador' && (string) ($signerRow['status'] ?? '') !== 'assinado') {
                            $name = (string) ($signerRow['signer_name'] ?? $appRow['name']);
                            (new App\Models\SignatureRequest())->sign((int) $signerRow['id'], $name, 'Aceite demo seed.');
                        }
                    }
                    (new App\Models\SignatureRequest())->generateSignedPdf($reqId);
                }
            }
        }
    }
}

echo "=== Demo esteira — links para análise ===\n";
echo "Login interno: admin@dancacarajas.com / Mudar@123\n\n";

$statuses = $model->getStatuses();

foreach ($scenarios as $scenario) {
    $st = $pdo->prepare('SELECT * FROM collector_applications WHERE email = ? LIMIT 1');
    $st->execute([$scenario['email']]);
    $app = $st->fetch(PDO::FETCH_ASSOC);
    if (!$app) {
        continue;
    }

    $id = (int) $app['id'];
    $token = (string) ($app['public_token'] ?? '');
    $step = $model->journeyStepKey($app);
    $statusLabel = $statuses[(string) $app['status']] ?? (string) $app['status'];
    $publicUrl = $token !== ''
        ? $baseUrl . '/captadores/credenciamento/' . rawurlencode($token)
        : '(sem token)';
    $internalUrl = $baseUrl . '/collector-applications/' . $id;
    $sigLink = '';
    $activeSig = (new App\Models\SignatureRequest())->activeForCollectorApplication($id);
    if ($activeSig) {
        $signers = (new App\Models\SignatureRequest())->signersForRequest((int) $activeSig['id']);
        if ($signers !== [] && !empty($signers[0]['public_token'])) {
            $sigLink = $baseUrl . '/assinatura/' . rawurlencode((string) $signers[0]['public_token']);
        }
    }

    echo "{$scenario['label']}\n";
    echo "  Etapa visual: {$step} | Status: {$statusLabel}\n";
    echo "  Público:  {$publicUrl}\n";
    if ($sigLink !== '') {
        echo "  Assinatura: {$sigLink}\n";
    }
    echo "  Interno:  {$internalUrl}\n\n";
}

echo "Para listar novamente: docker exec dcc_app php /var/www/html/scripts/list_collector_journey_links.php\n";
