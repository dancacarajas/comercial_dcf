<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CollectorCommission;
use App\Models\CollectorCommissionPayment;
use App\Models\Document;
use PDO;

/**
 * Regras de pagamento de comissao (Etapa 20B-2).
 */
final class CollectorCommissionPaymentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /** @param array<string, mixed> $data */
    public function register(int $commissionId, array $data, int|string|null $userId): int
    {
        $commission = (new CollectorCommission())->findById($commissionId);
        if ($commission === null) {
            throw new \RuntimeException('Comissao nao encontrada.');
        }
        $this->assertPayable($commission);

        $amount = $this->amount($data['amount'] ?? null);
        $balance = round((float) ($commission['payment_balance_amount'] ?? 0), 2);
        if ($amount > $balance) {
            throw new \RuntimeException('Pagamento nao pode ultrapassar o saldo da comissao.');
        }

        $paymentDate = $this->date((string) ($data['payment_date'] ?? ''));
        $method = trim((string) ($data['payment_method'] ?? ''));
        $methods = (new CollectorCommissionPayment())->getMethods();
        if ($method === '' || !array_key_exists($method, $methods)) {
            throw new \RuntimeException('Informe uma forma de pagamento valida.');
        }

        $proofDocumentId = (int) ($data['proof_document_id'] ?? 0);
        if ($proofDocumentId <= 0 || (new Document())->findById($proofDocumentId) === null) {
            throw new \RuntimeException('Informe um comprovante de pagamento valido.');
        }

        $notes = trim((string) ($data['notes'] ?? ''));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO `collector_commission_payments`
                    (`collector_commission_id`, `incentive_project_id`, `collector_id`, `amount`,
                     `payment_date`, `payment_method`, `proof_document_id`, `status`, `notes`, `created_by`, `created_at`)
                 VALUES
                    (:commission_id, :project_id, :collector_id, :amount,
                     :payment_date, :payment_method, :proof_document_id, :status, :notes, :created_by, NOW())'
            );
            $stmt->execute([
                'commission_id' => $commissionId,
                'project_id' => (int) $commission['incentive_project_id'],
                'collector_id' => (int) $commission['collector_id'],
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'proof_document_id' => $proofDocumentId,
                'status' => 'confirmado',
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => $userId !== null ? (int) $userId : null,
            ]);
            $paymentId = (int) $this->db->lastInsertId();
            $this->refreshCommission($commissionId);
            $this->db->commit();

            return $paymentId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancel(int $paymentId, int|string|null $userId, string $reason, string $status = 'cancelado'): int
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Informe o motivo do cancelamento/estorno.');
        }
        if (!in_array($status, ['cancelado', 'estornado'], true)) {
            throw new \RuntimeException('Status de cancelamento invalido.');
        }

        $payment = (new CollectorCommissionPayment())->findById($paymentId);
        if ($payment === null) {
            throw new \RuntimeException('Pagamento nao encontrado.');
        }
        if ((string) ($payment['status'] ?? '') !== 'confirmado') {
            throw new \RuntimeException('Somente pagamento confirmado pode ser cancelado ou estornado.');
        }

        $commissionId = (int) $payment['collector_commission_id'];
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE `collector_commission_payments`
                    SET `status` = :status,
                        `cancel_reason` = :reason,
                        `cancelled_by` = :uid,
                        `cancelled_at` = NOW(),
                        `updated_at` = NOW()
                  WHERE `id` = :id"
            );
            $stmt->execute([
                'status' => $status,
                'reason' => $reason,
                'uid' => $userId !== null ? (int) $userId : null,
                'id' => $paymentId,
            ]);
            $this->refreshCommission($commissionId);
            $this->db->commit();

            return $commissionId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $commission */
    private function assertPayable(array $commission): void
    {
        if ((string) ($commission['approval_status'] ?? '') !== 'aprovada') {
            throw new \RuntimeException('Somente comissao aprovada pode receber pagamento.');
        }
        if (!in_array((string) ($commission['payment_status'] ?? ''), ['a_pagar', 'parcialmente_pago'], true)) {
            throw new \RuntimeException('Comissao nao esta em status pagavel.');
        }
        if ((float) ($commission['payment_balance_amount'] ?? 0) <= 0) {
            throw new \RuntimeException('Comissao sem saldo para pagamento.');
        }
    }

    private function refreshCommission(int $commissionId): void
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(`amount`), 0)
               FROM `collector_commission_payments`
              WHERE `collector_commission_id` = :cid
                AND `status` = 'confirmado'"
        );
        $stmt->execute(['cid' => $commissionId]);
        $total = round((float) $stmt->fetchColumn(), 2);

        $commission = (new CollectorCommission())->findById($commissionId);
        if ($commission === null) {
            throw new \RuntimeException('Comissao nao encontrada ao atualizar saldo.');
        }
        $capped = round((float) ($commission['capped_commission_amount'] ?? 0), 2);
        $balance = max(0, round($capped - $total, 2));
        $status = 'a_pagar';
        if ($total > 0 && $balance > 0) {
            $status = 'parcialmente_pago';
        } elseif ($total > 0 && $balance <= 0) {
            $status = 'pago';
        }

        $stmt = $this->db->prepare(
            "UPDATE `collector_commissions`
                SET `payment_total_amount` = :total,
                    `payment_balance_amount` = :balance,
                    `payment_status` = :status,
                    `payment_started_at` = CASE
                        WHEN :total_started > 0 AND `payment_started_at` IS NULL THEN NOW()
                        WHEN :total_started2 <= 0 THEN NULL
                        ELSE `payment_started_at`
                    END,
                    `paid_at` = CASE
                        WHEN :balance_paid <= 0 AND :total_paid > 0 THEN COALESCE(`paid_at`, NOW())
                        ELSE NULL
                    END,
                    `updated_at` = NOW()
              WHERE `id` = :id"
        );
        $stmt->execute([
            'total' => $total,
            'balance' => $balance,
            'status' => $status,
            'total_started' => $total,
            'total_started2' => $total,
            'balance_paid' => $balance,
            'total_paid' => $total,
            'id' => $commissionId,
        ]);
    }

    private function amount(mixed $value): float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            throw new \RuntimeException('Informe o valor do pagamento.');
        }
        $normalized = str_replace(['R$', ' ', '.'], '', $raw);
        $normalized = str_replace(',', '.', $normalized);
        $amount = round((float) $normalized, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Valor do pagamento deve ser maior que zero.');
        }

        return $amount;
    }

    private function date(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new \RuntimeException('Informe a data do pagamento.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }
        $dt = \DateTimeImmutable::createFromFormat('d/m/Y', $value);
        if ($dt === false) {
            throw new \RuntimeException('Data de pagamento invalida.');
        }

        return $dt->format('Y-m-d');
    }
}
