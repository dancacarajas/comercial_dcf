<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use Throwable;

/**
 * Model de logs de atividade (auditoria).
 */
final class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Registra uma acao na tabela activity_logs.
     *
     * Nunca interrompe o fluxo: falhas de log sao silenciadas (com error_log).
     */
    public function record(
        string $action,
        int|string|null $userId = null,
        ?string $entityType = null,
        int|string|null $entityId = null
    ): void {
        try {
            $this->query(
                'INSERT INTO `activity_logs`
                    (`user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`)
                 VALUES
                    (:user_id, :action, :entity_type, :entity_id, :ip, :ua)',
                [
                    'user_id'     => $userId,
                    'action'      => $action,
                    'entity_type' => $entityType,
                    'entity_id'   => $entityId,
                    'ip'          => client_ip(),
                    'ua'          => user_agent(),
                ]
            );
        } catch (Throwable $e) {
            error_log('[ActivityLog] Falha ao registrar acao "' . $action . '": ' . $e->getMessage());
        }
    }
}
