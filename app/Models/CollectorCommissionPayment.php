<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Pagamentos registrados para comissoes de captadores (Etapa 20B-2).
 */
final class CollectorCommissionPayment extends Model
{
    protected string $table = 'collector_commission_payments';

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'confirmado' => 'Confirmado',
            'cancelado' => 'Cancelado',
            'estornado' => 'Estornado',
        ];
    }

    /** @return array<string, string> */
    public function getMethods(): array
    {
        return [
            'pix' => 'PIX',
            'transferencia' => 'Transferencia bancaria',
            'boleto' => 'Boleto',
            'dinheiro' => 'Dinheiro',
            'outro' => 'Outro',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT p.*, d.`title` AS proof_document_title, u.`name` AS created_by_name,
                    cu.`name` AS cancelled_by_name
               FROM `collector_commission_payments` p
               LEFT JOIN `documents` d ON d.`id` = p.`proof_document_id`
               LEFT JOIN `users` u ON u.`id` = p.`created_by`
               LEFT JOIN `users` cu ON cu.`id` = p.`cancelled_by`
              WHERE p.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function findByCommission(int|string $commissionId): array
    {
        return $this->query(
            'SELECT p.*, d.`title` AS proof_document_title, u.`name` AS created_by_name,
                    cu.`name` AS cancelled_by_name
               FROM `collector_commission_payments` p
               LEFT JOIN `documents` d ON d.`id` = p.`proof_document_id`
               LEFT JOIN `users` u ON u.`id` = p.`created_by`
               LEFT JOIN `users` cu ON cu.`id` = p.`cancelled_by`
              WHERE p.`collector_commission_id` = :cid
              ORDER BY p.`payment_date` DESC, p.`id` DESC',
            ['cid' => $commissionId]
        )->fetchAll();
    }
}
