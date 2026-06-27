<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Cadastro mestre do captador credenciado (Etapa 18C).
 *
 * Diferente de CollectorApplication (processo de credenciamento), esta entidade
 * é o cadastro oficial/permanente do captador aprovado, com os dados que os
 * contratos e a operação comercial realmente consomem.
 */
final class Collector extends Model
{
    protected string $table = 'collectors';

    /** @var list<string> */
    private const FILLABLE = [
        'collector_application_id', 'user_id', 'collector_code', 'type', 'status', 'registration_status',
        'name', 'legal_name', 'trade_name', 'document_number', 'state_registration', 'municipal_registration',
        'birth_date', 'nationality', 'marital_status', 'profession',
        'email', 'phone_whatsapp', 'secondary_phone',
        'address_zipcode', 'address_street', 'address_number', 'address_complement',
        'address_district', 'address_city', 'address_state',
        'bank_name', 'bank_code', 'agency', 'account', 'account_digit', 'account_type',
        'pix_key', 'pix_key_type', 'bank_holder_name', 'bank_holder_document',
        'representative_name', 'representative_document', 'representative_email',
        'representative_phone', 'representative_role',
        'rouanet_experience', 'segments', 'sponsor_network_description', 'territory_scope',
        'portfolio_summary', 'has_rouanet_experience',
        'commission_percentage', 'commission_payment_rule', 'commission_limit_rule',
        'contract_start_date', 'contract_end_date', 'exclusivity_type', 'exclusivity_scope',
        'confidentiality_required',
        'internal_notes',
        'validated_by', 'validated_at', 'created_by', 'updated_by',
    ];

    /** @return array<string, string> */
    public function getTypes(): array
    {
        return [
            'pessoa_fisica'   => 'Pessoa física (CPF)',
            'pessoa_juridica' => 'Pessoa jurídica (CNPJ)',
        ];
    }

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'ativo'    => 'Ativo',
            'suspenso' => 'Suspenso',
            'inativo'  => 'Inativo',
        ];
    }

    /** @return array<string, string> */
    public function getRegistrationStatuses(): array
    {
        return [
            'incompleto' => 'Cadastro incompleto',
            'completo'   => 'Cadastro completo',
            'validado'   => 'Cadastro validado',
        ];
    }

    /** @return array<string, string> */
    public function getAccountTypes(): array
    {
        return [
            'corrente'  => 'Conta corrente',
            'poupanca'  => 'Conta poupança',
            'pagamento' => 'Conta de pagamento',
        ];
    }

    /** @return array<string, string> */
    public function getPixKeyTypes(): array
    {
        return [
            'cpf'        => 'CPF',
            'cnpj'       => 'CNPJ',
            'email'      => 'E-mail',
            'telefone'   => 'Telefone',
            'aleatoria'  => 'Chave aleatória',
        ];
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT c.*, ca.`application_number`, vu.`name` AS validated_by_name, u.`email` AS user_email
               FROM `collectors` c
               LEFT JOIN `collector_applications` ca ON ca.`id` = c.`collector_application_id`
               LEFT JOIN `users` vu ON vu.`id` = c.`validated_by`
               LEFT JOIN `users` u ON u.`id` = c.`user_id`
              WHERE c.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findByApplication(int|string $applicationId): ?array
    {
        $row = $this->query(
            'SELECT * FROM `collectors` WHERE `collector_application_id` = :aid AND `archived_at` IS NULL LIMIT 1',
            ['aid' => $applicationId]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $params['limit'] = $perPage;
        $params['offset'] = $offset;

        return $this->query(
            'SELECT c.`id`, c.`collector_code`, c.`name`, c.`type`, c.`document_number`,
                    c.`status`, c.`registration_status`, c.`commission_percentage`,
                    c.`created_at`, c.`archived_at`, ca.`application_number`
               FROM `collectors` c
               LEFT JOIN `collector_applications` ca ON ca.`id` = c.`collector_application_id`
              WHERE ' . $where . '
              ORDER BY c.`created_at` DESC, c.`id` DESC
              LIMIT :limit OFFSET :offset',
            $params
        )->fetchAll();
    }

    /** @param array<string, mixed> $filters */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->query('SELECT COUNT(*) AS c FROM `collectors` c WHERE ' . $where, $params)->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'c.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(c.`name` LIKE :q OR c.`document_number` LIKE :q OR c.`collector_code` LIKE :q OR c.`email` LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        foreach (['status', 'registration_status', 'type'] as $key) {
            $val = trim((string) ($filters[$key] ?? ''));
            if ($val !== '') {
                $conditions[] = 'c.`' . $key . '` = :' . $key;
                $params[$key] = $val;
            }
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * Pré-preenche um cadastro a partir da candidatura aprovada.
     *
     * @param array<string, mixed> $application
     * @return array<string, mixed>
     */
    public function defaultsFromApplication(array $application): array
    {
        $doc = preg_replace('/\D+/', '', (string) ($application['document_number'] ?? '')) ?? '';
        $type = strlen($doc) === 14 ? 'pessoa_juridica' : 'pessoa_fisica';

        [$city, $state] = $this->splitCityState((string) ($application['city_state'] ?? ''));

        return [
            'collector_application_id'    => (int) ($application['id'] ?? 0),
            'type'                        => $type,
            'status'                      => 'ativo',
            'registration_status'         => 'incompleto',
            'name'                        => (string) ($application['name'] ?? ''),
            'legal_name'                  => $type === 'pessoa_juridica' ? (string) ($application['company_or_activity'] ?? '') : '',
            'document_number'             => (string) ($application['document_number'] ?? ''),
            'email'                       => (string) ($application['email'] ?? ''),
            'phone_whatsapp'              => (string) ($application['phone_whatsapp'] ?? ''),
            'address_city'                => $city,
            'address_state'               => $state,
            'rouanet_experience'          => (string) ($application['rouanet_experience'] ?? ''),
            'segments'                    => (string) ($application['segments'] ?? ''),
            'sponsor_network_description' => (string) ($application['sponsor_network_description'] ?? ''),
            'has_rouanet_experience'      => in_array((string) ($application['rouanet_experience'] ?? ''), ['basica', 'intermediaria', 'avancada', 'especialista'], true) ? 1 : 0,
        ];
    }

    /** @return array{0:string,1:string} */
    private function splitCityState(string $cityState): array
    {
        $cityState = trim($cityState);
        if ($cityState === '') {
            return ['', ''];
        }
        if (preg_match('/^(.*?)[\/\-]\s*([A-Za-z]{2})$/', $cityState, $m)) {
            return [trim($m[1]), strtoupper($m[2])];
        }

        return [$cityState, ''];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->filterFillable($data);
        if (empty($payload['name'])) {
            $payload['name'] = 'Captador';
        }
        if (empty($payload['collector_code'])) {
            $payload['collector_code'] = $this->generateCode();
        }

        $cols = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `collectors` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')',
            $payload
        );

        return (string) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): void
    {
        $payload = $this->filterFillable($data);
        $payload['updated_at'] = date('Y-m-d H:i:s');
        if ($payload === []) {
            return;
        }

        $sets = [];
        foreach (array_keys($payload) as $col) {
            $sets[] = '`' . $col . '` = :' . $col;
        }
        $payload['id'] = $id;
        $this->query(
            'UPDATE `collectors` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    /**
     * Recalcula o status de cadastro (incompleto/completo) preservando o estado validado.
     *
     * @param array<string, mixed> $collector
     */
    public function refreshRegistrationStatus(int|string $id, ?array $collector = null): string
    {
        $collector ??= $this->findById($id) ?? [];
        $missing = $this->missingRequirements($collector);
        $current = (string) ($collector['registration_status'] ?? 'incompleto');

        if ($missing !== []) {
            $next = 'incompleto';
        } elseif ($current === 'validado') {
            $next = 'validado';
        } else {
            $next = 'completo';
        }

        if ($next !== $current) {
            $this->query(
                'UPDATE `collectors` SET `registration_status` = :st, `updated_at` = NOW() WHERE `id` = :id',
                ['st' => $next, 'id' => $id]
            );
        }

        return $next;
    }

    public function markValidated(int|string $id, int|string|null $userId): void
    {
        $this->query(
            'UPDATE `collectors`
                SET `registration_status` = :st, `validated_by` = :uid, `validated_at` = NOW(), `updated_at` = NOW()
              WHERE `id` = :id',
            ['st' => 'validado', 'uid' => $userId, 'id' => $id]
        );
    }

    public function isLegalEntity(array $collector): bool
    {
        $type = (string) ($collector['type'] ?? '');
        if ($type !== '') {
            return $type === 'pessoa_juridica';
        }
        $doc = preg_replace('/\D+/', '', (string) ($collector['document_number'] ?? '')) ?? '';

        return strlen($doc) === 14;
    }

    /**
     * Lista de blocos obrigatórios ainda pendentes para considerar o cadastro completo.
     * A geração de documentos só é liberada quando esta lista está vazia E o cadastro foi validado.
     *
     * @param array<string, mixed> $c
     * @return array<string, string>
     */
    public function missingRequirements(array $c): array
    {
        $missing = [];

        // Identificação
        $type = (string) ($c['type'] ?? '');
        $doc = preg_replace('/\D+/', '', (string) ($c['document_number'] ?? '')) ?? '';
        $docOk = in_array(strlen($doc), [11, 14], true);
        if ($type === '' || trim((string) ($c['name'] ?? '')) === '' || !$docOk) {
            $missing['identification'] = 'Identificação (tipo PF/PJ, nome e CPF/CNPJ válido)';
        }

        // Endereço
        if (!$this->blockFilled($c, ['address_zipcode', 'address_street', 'address_number', 'address_district', 'address_city', 'address_state'])) {
            $missing['address'] = 'Endereço completo (CEP, logradouro, número, bairro, cidade e UF)';
        }

        // Dados bancários OU PIX
        $bankOk = $this->blockFilled($c, ['bank_name', 'agency', 'account', 'account_type', 'bank_holder_name']);
        $pixOk = $this->blockFilled($c, ['pix_key', 'pix_key_type']);
        if (!$bankOk && !$pixOk) {
            $missing['bank'] = 'Dados bancários (banco, agência, conta, tipo e titular) ou chave PIX';
        }

        // Representante legal (PJ)
        if ($this->isLegalEntity($c) && !$this->blockFilled($c, ['representative_name', 'representative_document'])) {
            $missing['representative'] = 'Representante legal (nome e documento) para pessoa jurídica';
        }

        // Regras comerciais
        $pct = $c['commission_percentage'] ?? null;
        $commercialOk = $pct !== null && $pct !== '' && (float) $pct > 0
            && trim((string) ($c['contract_start_date'] ?? '')) !== ''
            && trim((string) ($c['contract_end_date'] ?? '')) !== '';
        if (!$commercialOk) {
            $missing['commercial'] = 'Regras comerciais (percentual de comissão e vigência do contrato)';
        }

        return $missing;
    }

    /**
     * Cadastro está apto para gerar documentos/assinatura (completo + validado)?
     *
     * @param array<string, mixed> $collector
     */
    public function isReadyForDocuments(array $collector): bool
    {
        return $this->missingRequirements($collector) === []
            && (string) ($collector['registration_status'] ?? '') === 'validado';
    }

    /**
     * @param array<string, mixed> $c
     * @param list<string> $fields
     */
    private function blockFilled(array $c, array $fields): bool
    {
        foreach ($fields as $field) {
            if (trim((string) ($c[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'Informe o nome do captador.';
        }
        if (!array_key_exists((string) ($data['type'] ?? ''), $this->getTypes())) {
            $errors['type'] = 'Selecione o tipo (PF/PJ).';
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }
        $pct = $data['commission_percentage'] ?? '';
        if ($pct !== '' && $pct !== null && (!is_numeric($pct) || (float) $pct < 0 || (float) $pct > 100)) {
            $errors['commission_percentage'] = 'Percentual de comissão deve estar entre 0 e 100.';
        }

        return $errors;
    }

    private function generateCode(): string
    {
        return 'DCF-CAP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function filterFillable(array $data): array
    {
        $out = [];
        foreach (self::FILLABLE as $col) {
            if (array_key_exists($col, $data)) {
                $out[$col] = $data[$col] === '' ? null : $data[$col];
            }
        }
        // Campos NOT NULL não podem ser null.
        foreach (['type', 'status', 'registration_status', 'name', 'has_rouanet_experience', 'confidentiality_required'] as $req) {
            if (array_key_exists($req, $out) && $out[$req] === null) {
                unset($out[$req]);
            }
        }

        return $out;
    }
}
