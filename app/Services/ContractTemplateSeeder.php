<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Data\CaptadorConfidencialidadeTemplate;
use App\Data\CaptadorCondutaTemplate;
use App\Data\CaptadorExternoContractTemplate;
use App\Data\CaptadorLgpdTemplate;
use App\Data\CaptadorPortalUsoTemplate;
use App\Data\CaptadorProjetoComissaoTemplate;
use PDO;

/** Insere ou atualiza modelos de contrato padrão no banco. */
final class ContractTemplateSeeder
{
    public static function upsertCaptadorExternoDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorExternoContractTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteúdo HTML do contrato captador externo ausente ou inválido.');
        }

        $key = CaptadorExternoContractTemplate::TEMPLATE_KEY;
        $stmt = $pdo->prepare('SELECT id FROM contract_templates WHERE template_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $existingId = $stmt->fetchColumn();

        $payload = [
            'title'               => CaptadorExternoContractTemplate::TITLE,
            'description'         => CaptadorExternoContractTemplate::DESCRIPTION,
            'template_type'       => CaptadorExternoContractTemplate::TEMPLATE_TYPE,
            'status'              => 'ativo',
            'content_html'        => $html,
            'default_signer_role' => 'captador',
            'is_default'          => 1,
        ];

        if ($existingId) {
            $pdo->prepare(
                'UPDATE contract_templates SET title = ?, description = ?, template_type = ?, status = ?,
                    content_html = ?, default_signer_role = ?, is_default = ?, updated_at = NOW() WHERE id = ?'
            )->execute([
                $payload['title'],
                $payload['description'],
                $payload['template_type'],
                $payload['status'],
                $payload['content_html'],
                $payload['default_signer_role'],
                $payload['is_default'],
                (int) $existingId,
            ]);

            return (int) $existingId;
        }

        $pdo->prepare(
            'INSERT INTO contract_templates
                (template_key, title, description, template_type, status, content_html, default_signer_role, is_default, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $key,
            $payload['title'],
            $payload['description'],
            $payload['template_type'],
            $payload['status'],
            $payload['content_html'],
            $payload['default_signer_role'],
            $payload['is_default'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function upsertCaptadorConfidencialidadeDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorConfidencialidadeTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteudo HTML do termo de confidencialidade ausente ou invalido.');
        }

        $key = CaptadorConfidencialidadeTemplate::TEMPLATE_KEY;
        $stmt = $pdo->prepare('SELECT id FROM contract_templates WHERE template_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $existingId = $stmt->fetchColumn();

        $payload = [
            'title'               => CaptadorConfidencialidadeTemplate::TITLE,
            'description'         => CaptadorConfidencialidadeTemplate::DESCRIPTION,
            'template_type'       => CaptadorConfidencialidadeTemplate::TEMPLATE_TYPE,
            'status'              => 'ativo',
            'content_html'        => $html,
            'content_text'        => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))),
            'available_placeholders_json' => json_encode([
                'collector.name',
                'collector.legal_type',
                'collector.document_number',
                'collector.city_state',
                'collector.email',
                'collector.phone_whatsapp',
                'collector.company_or_activity',
                'application.application_number',
                'organization.name',
                'organization.document',
                'organization.address',
                'contract.forum',
                'signature.request_number',
                'date.today',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'default_signer_role' => 'captador',
            'is_default'          => 1,
            'collector_signature_stage_enabled' => 1,
            'collector_signature_required' => 1,
            'collector_signature_order' => 20,
        ];

        if ($existingId) {
            $pdo->prepare(
                'UPDATE contract_templates SET title = ?, description = ?, template_type = ?, status = ?,
                    content_html = ?, content_text = ?, available_placeholders_json = ?,
                    default_signer_role = ?, is_default = ?,
                    collector_signature_stage_enabled = ?, collector_signature_required = ?, collector_signature_order = ?,
                    updated_at = NOW() WHERE id = ?'
            )->execute([
                $payload['title'],
                $payload['description'],
                $payload['template_type'],
                $payload['status'],
                $payload['content_html'],
                $payload['content_text'],
                $payload['available_placeholders_json'],
                $payload['default_signer_role'],
                $payload['is_default'],
                $payload['collector_signature_stage_enabled'],
                $payload['collector_signature_required'],
                $payload['collector_signature_order'],
                (int) $existingId,
            ]);

            return (int) $existingId;
        }

        $pdo->prepare(
            'INSERT INTO contract_templates
                (template_key, title, description, template_type, status, content_html, content_text,
                 available_placeholders_json, default_signer_role, is_default,
                 collector_signature_stage_enabled, collector_signature_required, collector_signature_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $key,
            $payload['title'],
            $payload['description'],
            $payload['template_type'],
            $payload['status'],
            $payload['content_html'],
            $payload['content_text'],
            $payload['available_placeholders_json'],
            $payload['default_signer_role'],
            $payload['is_default'],
            $payload['collector_signature_stage_enabled'],
            $payload['collector_signature_required'],
            $payload['collector_signature_order'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function upsertCaptadorLgpdDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorLgpdTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteudo HTML do termo LGPD ausente ou invalido.');
        }

        return self::upsertCollectorSignatureTemplate($pdo, [
            'template_key' => CaptadorLgpdTemplate::TEMPLATE_KEY,
            'title' => CaptadorLgpdTemplate::TITLE,
            'description' => CaptadorLgpdTemplate::DESCRIPTION,
            'template_type' => CaptadorLgpdTemplate::TEMPLATE_TYPE,
            'content_html' => $html,
            'collector_signature_order' => 30,
        ]);
    }

    public static function upsertCaptadorCondutaDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorCondutaTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteudo HTML do codigo de conduta ausente ou invalido.');
        }

        return self::upsertCollectorSignatureTemplate($pdo, [
            'template_key' => CaptadorCondutaTemplate::TEMPLATE_KEY,
            'title' => CaptadorCondutaTemplate::TITLE,
            'description' => CaptadorCondutaTemplate::DESCRIPTION,
            'template_type' => CaptadorCondutaTemplate::TEMPLATE_TYPE,
            'content_html' => $html,
            'collector_signature_order' => 40,
        ]);
    }

    public static function upsertCaptadorPortalUsoDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorPortalUsoTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteudo HTML do termo de uso do portal ausente ou invalido.');
        }

        return self::upsertCollectorSignatureTemplate($pdo, [
            'template_key' => CaptadorPortalUsoTemplate::TEMPLATE_KEY,
            'title' => CaptadorPortalUsoTemplate::TITLE,
            'description' => CaptadorPortalUsoTemplate::DESCRIPTION,
            'template_type' => CaptadorPortalUsoTemplate::TEMPLATE_TYPE,
            'content_html' => $html,
            'collector_signature_order' => 50,
        ]);
    }

    public static function upsertCaptadorProjetoComissaoDefault(?PDO $pdo = null): int
    {
        $pdo ??= Database::connection();
        $html = CaptadorProjetoComissaoTemplate::contentHtml();
        if (trim(strip_tags($html)) === '') {
            throw new \RuntimeException('Conteudo HTML do termo de projeto/comissao ausente ou invalido.');
        }

        return self::upsertCollectorSignatureTemplate($pdo, [
            'template_key' => CaptadorProjetoComissaoTemplate::TEMPLATE_KEY,
            'title' => CaptadorProjetoComissaoTemplate::TITLE,
            'description' => CaptadorProjetoComissaoTemplate::DESCRIPTION,
            'template_type' => CaptadorProjetoComissaoTemplate::TEMPLATE_TYPE,
            'content_html' => $html,
            'collector_signature_order' => 60,
        ]);
    }

    /** @param array<string, mixed> $data */
    private static function upsertCollectorSignatureTemplate(PDO $pdo, array $data): int
    {
        $html = (string) $data['content_html'];
        $stmt = $pdo->prepare('SELECT id FROM contract_templates WHERE template_key = ? LIMIT 1');
        $stmt->execute([(string) $data['template_key']]);
        $existingId = $stmt->fetchColumn();

        $payload = [
            'title'               => (string) $data['title'],
            'description'         => (string) $data['description'],
            'template_type'       => (string) $data['template_type'],
            'status'              => 'ativo',
            'content_html'        => $html,
            'content_text'        => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))),
            'available_placeholders_json' => json_encode([
                'collector.name',
                'collector.legal_type',
                'collector.document_number',
                'collector.city_state',
                'collector.email',
                'collector.phone_whatsapp',
                'collector.company_or_activity',
                'application.application_number',
                'organization.name',
                'organization.document',
                'organization.address',
                'contract.forum',
                'signature.request_number',
                'date.today',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'default_signer_role' => 'captador',
            'is_default'          => 1,
            'collector_signature_stage_enabled' => 1,
            'collector_signature_required' => 1,
            'collector_signature_order' => (int) $data['collector_signature_order'],
        ];

        if ($existingId) {
            $pdo->prepare(
                'UPDATE contract_templates SET title = ?, description = ?, template_type = ?, status = ?,
                    content_html = ?, content_text = ?, available_placeholders_json = ?,
                    default_signer_role = ?, is_default = ?,
                    collector_signature_stage_enabled = ?, collector_signature_required = ?, collector_signature_order = ?,
                    updated_at = NOW() WHERE id = ?'
            )->execute([
                $payload['title'],
                $payload['description'],
                $payload['template_type'],
                $payload['status'],
                $payload['content_html'],
                $payload['content_text'],
                $payload['available_placeholders_json'],
                $payload['default_signer_role'],
                $payload['is_default'],
                $payload['collector_signature_stage_enabled'],
                $payload['collector_signature_required'],
                $payload['collector_signature_order'],
                (int) $existingId,
            ]);

            return (int) $existingId;
        }

        $pdo->prepare(
            'INSERT INTO contract_templates
                (template_key, title, description, template_type, status, content_html, content_text,
                 available_placeholders_json, default_signer_role, is_default,
                 collector_signature_stage_enabled, collector_signature_required, collector_signature_order, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            (string) $data['template_key'],
            $payload['title'],
            $payload['description'],
            $payload['template_type'],
            $payload['status'],
            $payload['content_html'],
            $payload['content_text'],
            $payload['available_placeholders_json'],
            $payload['default_signer_role'],
            $payload['is_default'],
            $payload['collector_signature_stage_enabled'],
            $payload['collector_signature_required'],
            $payload['collector_signature_order'],
        ]);

        return (int) $pdo->lastInsertId();
    }
}
