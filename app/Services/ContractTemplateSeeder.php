<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Data\CaptadorExternoContractTemplate;
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
}
