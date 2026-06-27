<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Credenciamento de Captadores de Recursos.
 */
final class CollectorApplication extends Model
{
    protected string $table = 'collector_applications';

    private const FILLABLE = [
        'application_number', 'source', 'source_page', 'source_url',
        'name', 'company_or_activity', 'document_number', 'email', 'phone_whatsapp',
        'city_state', 'rouanet_experience', 'segments', 'sponsor_network_description', 'message',
        'status', 'document_status', 'review_status', 'access_status',
        'public_token', 'public_token_expires_at', 'public_token_revoked_at',
        'review_notes', 'rejection_reason', 'approval_notes', 'internal_notes',
        'consent_contact', 'consent_lgpd_at',
        'ip_address', 'user_agent',
        'assigned_user_id', 'reviewed_by', 'approved_by', 'rejected_by', 'user_created_id',
        'reviewed_at', 'approved_at', 'rejected_at',
        'documents_requested_at', 'documents_submitted_at', 'access_released_at',
        'created_by', 'updated_by',
    ];

    private const LIST_COLUMNS =
        'ca.`id`, ca.`application_number`, ca.`source`, ca.`name`, ca.`email`, ca.`document_number`,
         ca.`city_state`, ca.`rouanet_experience`, ca.`status`, ca.`document_status`, ca.`review_status`,
         ca.`access_status`, ca.`created_at`, ca.`archived_at`,
         au.`name` AS assigned_name';

    /** @var array<string, list<string>> */
    private const FIELD_ALIASES = [
        'name'                        => ['name', 'nome', 'your-name', 'full_name'],
        'company_or_activity'         => ['company_or_activity', 'empresa', 'empresa_atuacao', 'company', 'atividade'],
        'document_number'             => ['document_number', 'cpf_cnpj', 'cpf', 'cnpj', 'documento'],
        'email'                       => ['email', 'your-email', 'e-mail'],
        'phone_whatsapp'              => ['phone_whatsapp', 'whatsapp', 'telefone', 'phone', 'celular'],
        'city_state'                  => ['city_state', 'cidade_uf', 'cidade', 'city'],
        'rouanet_experience'          => ['rouanet_experience', 'experiencia_rouanet', 'experiencia_lei_rouanet', 'rouanet'],
        'segments'                    => ['segments', 'segmentos', 'segmentos_atuacao'],
        'sponsor_network_description' => ['sponsor_network_description', 'carteira_patrocinadores', 'rede_patrocinadores', 'carteira'],
        'message'                     => ['message', 'mensagem', 'observacoes'],
        'consent_contact'             => ['consent_contact', 'consent', 'consentimento', 'lgpd', 'aceite', 'autorizacao_contato'],
        'source_page'                 => ['source_page', 'origem'],
        'source_url'                  => ['source_url'],
    ];

    /** @return array<string, string> */
    public function getStatuses(): array
    {
        return [
            'manifestacao_recebida'   => 'Manifestação recebida',
            'em_triagem'              => 'Em triagem',
            'documentos_solicitados'  => 'Documentos solicitados',
            'documentos_enviados'     => 'Documentos enviados',
            'em_analise_documental'   => 'Em análise documental',
            'ajustes_solicitados'     => 'Ajustes solicitados',
            'aprovado'                => 'Aprovado',
            'aguardando_assinatura_contratual' => 'Aguardando assinatura contratual',
            'contrato_assinado'       => 'Contrato assinado',
            'reprovado'               => 'Reprovado',
            'acesso_preparado'        => 'Acesso preparado',
            'acesso_liberado'         => 'Acesso liberado',
            'suspenso'                => 'Suspenso',
            'arquivado'               => 'Arquivado',
        ];
    }

    /** @return array<string, string> */
    public function getDocumentStatuses(): array
    {
        return [
            'nao_solicitado'     => 'Não solicitado',
            'solicitado'         => 'Solicitado',
            'parcial'            => 'Parcial',
            'enviado'            => 'Enviado',
            'em_analise'         => 'Em análise',
            'aprovado'           => 'Aprovado',
            'pendente_correcao'  => 'Pendente correção',
            'reprovado'          => 'Reprovado',
        ];
    }

    /** @return array<string, string> */
    public function getReviewStatuses(): array
    {
        return [
            'pendente'             => 'Pendente',
            'em_analise'           => 'Em análise',
            'aprovado'             => 'Aprovado',
            'reprovado'            => 'Reprovado',
            'ajustes_solicitados'  => 'Ajustes solicitados',
        ];
    }

    /** @return array<string, string> */
    public function getAccessStatuses(): array
    {
        return [
            'nao_liberado'      => 'Não liberado',
            'pendente_criacao'  => 'Pendente criação',
            'usuario_criado'    => 'Usuário criado',
            'acesso_liberado'   => 'Acesso liberado',
            'acesso_suspenso'   => 'Acesso suspenso',
        ];
    }

    /** @return array<string, string> */
    public function getRouanetExperienceOptions(): array
    {
        return [
            'nenhuma'     => 'Nenhuma',
            'basica'      => 'Básica',
            'intermediaria'=> 'Intermediária',
            'avancada'    => 'Avançada',
            'especialista'=> 'Especialista',
        ];
    }

    /** @return array<string, string> */
    public function getJourneySteps(): array
    {
        return [
            'manifestacao' => 'Manifestação',
            'documentos'   => 'Envio documental',
            'analise'      => 'Análise documental',
            'aprovacao'    => 'Aprovação',
            'assinatura'   => 'Assinatura contratual',
            'acesso'       => 'Liberação de acesso',
        ];
    }

    /**
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function mapIncoming(array $raw): array
    {
        $flat = [];
        foreach ($raw as $k => $v) {
            if (is_string($k)) {
                $flat[strtolower(trim($k))] = $v;
            }
        }

        $mapped = ['source' => 'site'];
        foreach (self::FIELD_ALIASES as $target => $aliases) {
            if ($target === 'consent_contact') {
                $mapped[$target] = $this->normalizeConsentFromFlat($flat, $aliases);
                continue;
            }
            foreach ($aliases as $alias) {
                $key = strtolower($alias);
                if (array_key_exists($key, $flat) && $flat[$key] !== '' && $flat[$key] !== null) {
                    $mapped[$target] = $this->sanitizeText((string) $flat[$key]);
                    break;
                }
            }
        }

        if (!empty($mapped['email'])) {
            $mapped['email'] = strtolower(trim((string) $mapped['email']));
        }
        if (!empty($mapped['phone_whatsapp'])) {
            $mapped['phone_whatsapp'] = preg_replace('/\D+/', '', (string) $mapped['phone_whatsapp']) ?: (string) $mapped['phone_whatsapp'];
        }
        if (!empty($mapped['document_number'])) {
            $mapped['document_number'] = preg_replace('/\D+/', '', (string) $mapped['document_number']) ?: trim((string) $mapped['document_number']);
        }

        $mapped['consent_contact'] = (int) ($mapped['consent_contact'] ?? 0);
        if ($mapped['consent_contact'] === 1) {
            $mapped['consent_lgpd_at'] = date('Y-m-d H:i:s');
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data, string $mode = 'create'): array
    {
        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'O nome deve ter ao menos 2 caracteres.';
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Informe o e-mail.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'E-mail inválido.';
        }

        $whatsapp = trim((string) ($data['phone_whatsapp'] ?? ''));
        if ($whatsapp === '') {
            $errors['phone_whatsapp'] = 'Informe o WhatsApp.';
        }

        $doc = trim((string) ($data['document_number'] ?? ''));
        if ($doc === '') {
            $errors['document_number'] = 'Informe CPF ou CNPJ.';
        }

        $city = trim((string) ($data['city_state'] ?? ''));
        if ($city === '') {
            $errors['city_state'] = 'Informe cidade/UF.';
        }

        $rouanet = trim((string) ($data['rouanet_experience'] ?? ''));
        if ($rouanet === '') {
            $errors['rouanet_experience'] = 'Informe a experiência com Lei Rouanet.';
        }

        if ($mode === 'api' && empty($data['consent_contact'])) {
            $errors['consent_contact'] = 'É necessário autorizar o contato.';
        }

        if ($mode !== 'api') {
            $status = (string) ($data['status'] ?? 'manifestacao_recebida');
            if (!array_key_exists($status, $this->getStatuses())) {
                $errors['status'] = 'Status inválido.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->buildWhere($filters);
        $row = $this->query("SELECT COUNT(*) AS c FROM `collector_applications` ca WHERE {$where}", $params)->fetch();

        return (int) ($row['c'] ?? 0);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function paginate(array $filters, int $page, int $perPage): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $offset = max(0, ($page - 1) * $perPage);
        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        return $this->query(
            'SELECT ' . self::LIST_COLUMNS . '
               FROM `collector_applications` ca
               LEFT JOIN `users` au ON au.`id` = ca.`assigned_user_id`
              WHERE ' . $where . '
              ORDER BY ca.`created_at` DESC, ca.`id` DESC
              LIMIT :limit OFFSET :offset',
            $params
        )->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function findById(int|string $id): ?array
    {
        $row = $this->query(
            'SELECT ca.*, au.`name` AS assigned_name,
                    ru.`name` AS reviewed_by_name, apu.`name` AS approved_by_name,
                    rju.`name` AS rejected_by_name, cu.`name` AS user_created_name
               FROM `collector_applications` ca
               LEFT JOIN `users` au ON au.`id` = ca.`assigned_user_id`
               LEFT JOIN `users` ru ON ru.`id` = ca.`reviewed_by`
               LEFT JOIN `users` apu ON apu.`id` = ca.`approved_by`
               LEFT JOIN `users` rju ON rju.`id` = ca.`rejected_by`
               LEFT JOIN `users` cu ON cu.`id` = ca.`user_created_id`
              WHERE ca.`id` = :id LIMIT 1',
            ['id' => $id]
        )->fetch();

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
            'SELECT * FROM `collector_applications` WHERE `public_token` = :token LIMIT 1',
            ['token' => $token]
        )->fetch();

        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): string
    {
        $payload = $this->filterFillable($data);
        if (empty($payload['application_number'])) {
            $payload['application_number'] = $this->generateApplicationNumber();
        }
        if (empty($payload['status'])) {
            $payload['status'] = 'manifestacao_recebida';
        }

        $cols = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $this->query(
            'INSERT INTO `collector_applications` (`' . implode('`, `', $cols) . '`) VALUES (' . implode(', ', $placeholders) . ')',
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
            'UPDATE `collector_applications` SET ' . implode(', ', $sets) . ' WHERE `id` = :id',
            $payload
        );
    }

    public function archive(int|string $id, int|string|null $userId = null): void
    {
        $this->query(
            'UPDATE `collector_applications` SET `archived_at` = NOW(), `status` = :st, `updated_by` = :uid, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id, 'st' => 'arquivado', 'uid' => $userId]
        );
    }

    public function restore(int|string $id, int|string|null $userId = null): void
    {
        $this->query(
            'UPDATE `collector_applications` SET `archived_at` = NULL, `status` = :st, `updated_by` = :uid, `updated_at` = NOW() WHERE `id` = :id',
            ['id' => $id, 'st' => 'manifestacao_recebida', 'uid' => $userId]
        );
    }

    public function generatePublicToken(int|string $id, int $daysValid = 30): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable())->modify('+' . $daysValid . ' days')->format('Y-m-d H:i:s');
        $this->update($id, [
            'public_token'            => $token,
            'public_token_expires_at' => $expires,
            'public_token_revoked_at' => null,
        ]);

        return $token;
    }

    public function revokePublicToken(int|string $id): void
    {
        $this->update($id, ['public_token_revoked_at' => date('Y-m-d H:i:s')]);
    }

    /** @return array{valid:bool, reason:?string} */
    public function validatePublicToken(array $application): array
    {
        if (empty($application['public_token'])) {
            return ['valid' => false, 'reason' => 'Link não disponível. Entre em contato com a equipe Dança Carajás.'];
        }
        if (!empty($application['public_token_revoked_at'])) {
            return ['valid' => false, 'reason' => 'Este link foi revogado. Solicite um novo link à equipe.'];
        }
        $expires = (string) ($application['public_token_expires_at'] ?? '');
        if ($expires !== '' && strtotime($expires) < time()) {
            return ['valid' => false, 'reason' => 'Este link expirou. Solicite um novo link à equipe.'];
        }

        return ['valid' => true, 'reason' => null];
    }

    public function isLegalEntity(array $application): bool
    {
        $digits = preg_replace('/\D+/', '', (string) ($application['document_number'] ?? '')) ?? '';

        return strlen($digits) > 11;
    }

    /** @return array<string, string> */
    public function defaultDocumentTypesFor(array $application): array
    {
        $docModel = new CollectorApplicationDocument();
        $keys = $this->isLegalEntity($application)
            ? $docModel->getDefaultTypeKeysLegalEntity()
            : $docModel->getDefaultTypeKeysIndividual();

        return $docModel->labelsForTypeKeys($keys);
    }

    /** @return array<string, string> */
    public function optionalDocumentTypesFor(array $application): array
    {
        $docModel = new CollectorApplicationDocument();
        $available = $this->isLegalEntity($application)
            ? array_merge($docModel->getTypesLegalEntity(), $docModel->getTypesIndividual())
            : $docModel->getTypesIndividual();

        $defaults = array_keys($this->defaultDocumentTypesFor($application));
        $optional = [];
        foreach ($available as $key => $label) {
            if (!in_array($key, $defaults, true)) {
                $optional[$key] = $label;
            }
        }

        return $optional;
    }

    public function entityTypeLabel(array $application): string
    {
        return $this->isLegalEntity($application) ? 'Pessoa jurídica (CNPJ)' : 'Pessoa física (CPF)';
    }

    public function hasCompletedOnboarding(array $application): bool
    {
        return (int) ($application['user_created_id'] ?? 0) > 0;
    }

    public function canSelfRegister(array $application): bool
    {
        if (!empty($application['archived_at']) || (string) ($application['status'] ?? '') === 'arquivado') {
            return false;
        }

        if ($this->hasCompletedOnboarding($application)) {
            return false;
        }

        return (string) ($application['status'] ?? '') === 'acesso_liberado';
    }

    public function hasSignedContract(array $application): bool
    {
        return $this->hasCompletedRequiredCollectorSignatures($application);
    }

    public function hasCompletedRequiredCollectorSignatures(array $application): bool
    {
        $appId = (int) ($application['id'] ?? 0);
        if ($appId <= 0) {
            return false;
        }

        $templateModel = new ContractTemplate();
        $requiredTemplates = $templateModel->findRequiredForCollectorSignatureStage();
        $sigModel = new SignatureRequest();

        if ($requiredTemplates === []) {
            $active = $sigModel->activeForCollectorApplication($appId);

            return $active !== null && $sigModel->isFullySigned($active);
        }

        foreach ($requiredTemplates as $template) {
            $request = $sigModel->activeForCollectorApplicationByTemplate($appId, (int) ($template['id'] ?? 0));
            if ($request === null || !$sigModel->isFullySigned($request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   signed_required: int,
     *   total_required: int,
     *   signed_total: int,
     *   total_enabled: int,
     *   all_required_signed: bool,
     *   pending_required_titles: list<string>
     * }
     */
    public function signatureStageProgress(int $applicationId): array
    {
        $templateModel = new ContractTemplate();
        $sigModel = new SignatureRequest();
        $enabledTemplates = $templateModel->findForCollectorSignatureStage();
        $items = [];
        $signedRequired = 0;
        $signedTotal = 0;
        $totalRequired = 0;
        $pendingTitles = [];

        foreach ($enabledTemplates as $template) {
            $templateId = (int) ($template['id'] ?? 0);
            $isRequired = (int) ($template['collector_signature_required'] ?? 1) === 1;
            if ($isRequired) {
                ++$totalRequired;
            }

            $request = $sigModel->activeForCollectorApplicationByTemplate($applicationId, $templateId);
            $signers = $request ? $sigModel->signersForRequest((int) ($request['id'] ?? 0)) : [];
            $captadorLink = null;
            $pdfUrl = null;
            $captadorSigned = false;
            $contratanteSigned = false;

            foreach ($signers as $signer) {
                $role = (string) ($signer['signer_role'] ?? '');
                if ($role === 'captador') {
                    if (!empty($signer['public_token'])) {
                        $captadorLink = app_url('/assinatura/' . rawurlencode((string) $signer['public_token']));
                    }
                    $captadorSigned = (string) ($signer['status'] ?? '') === 'assinado';
                    if ($captadorSigned && !empty($signer['public_token'])) {
                        $pdfUrl = app_url('/assinatura/' . rawurlencode((string) $signer['public_token']) . '/pdf');
                    }
                }
                if ($role === 'contratante') {
                    $contratanteSigned = (string) ($signer['status'] ?? '') === 'assinado';
                }
            }

            $isSigned = $request !== null && $sigModel->isFullySigned($request);
            if ($isSigned) {
                ++$signedTotal;
                if ($isRequired) {
                    ++$signedRequired;
                }
            } elseif ($isRequired) {
                $pendingTitles[] = (string) ($template['title'] ?? 'Documento contratual');
            }

            $items[] = [
                'template_id'         => $templateId,
                'title'               => (string) ($request['title'] ?? $template['title'] ?? 'Documento'),
                'description'         => (string) ($template['description'] ?? ''),
                'is_required'         => $isRequired,
                'is_signed'           => $isSigned,
                'request_id'          => (int) ($request['id'] ?? 0),
                'request_status'      => (string) ($request['status'] ?? ''),
                'sent_at'             => (string) ($request['sent_at'] ?? ''),
                'signed_at'           => (string) ($request['signed_at'] ?? ''),
                'captador_link'       => $captadorLink,
                'pdf_url'             => $pdfUrl,
                'captador_signed'     => $captadorSigned,
                'contratante_signed'  => $contratanteSigned,
                'signers'             => $signers,
                'template'            => $template,
                'request'             => $request,
            ];
        }

        if ($items === []) {
            foreach ($sigModel->activeForCollectorApplicationList($applicationId) as $request) {
                $signers = $sigModel->signersForRequest((int) ($request['id'] ?? 0));
                $captadorLink = null;
                $pdfUrl = null;
                $captadorSigned = false;
                $contratanteSigned = false;
                foreach ($signers as $signer) {
                    $role = (string) ($signer['signer_role'] ?? '');
                    if ($role === 'captador') {
                        if (!empty($signer['public_token'])) {
                            $captadorLink = app_url('/assinatura/' . rawurlencode((string) $signer['public_token']));
                        }
                        $captadorSigned = (string) ($signer['status'] ?? '') === 'assinado';
                        if ($captadorSigned && !empty($signer['public_token'])) {
                            $pdfUrl = app_url('/assinatura/' . rawurlencode((string) $signer['public_token']) . '/pdf');
                        }
                    }
                    if ($role === 'contratante') {
                        $contratanteSigned = (string) ($signer['status'] ?? '') === 'assinado';
                    }
                }
                $isSigned = $sigModel->isFullySigned($request);
                if ($isSigned) {
                    ++$signedTotal;
                    ++$signedRequired;
                } else {
                    $pendingTitles[] = (string) ($request['title'] ?? 'Documento contratual');
                }
                ++$totalRequired;
                $items[] = [
                    'template_id'        => (int) ($request['contract_template_id'] ?? 0),
                    'title'              => (string) ($request['title'] ?? 'Documento'),
                    'description'        => '',
                    'is_required'        => true,
                    'is_signed'          => $isSigned,
                    'request_id'         => (int) ($request['id'] ?? 0),
                    'request_status'     => (string) ($request['status'] ?? ''),
                    'sent_at'            => (string) ($request['sent_at'] ?? ''),
                    'signed_at'          => (string) ($request['signed_at'] ?? ''),
                    'captador_link'      => $captadorLink,
                    'pdf_url'            => $pdfUrl,
                    'captador_signed'    => $captadorSigned,
                    'contratante_signed' => $contratanteSigned,
                    'signers'            => $signers,
                    'template'           => null,
                    'request'            => $request,
                ];
            }
        }

        return [
            'items'                   => $items,
            'signed_required'         => $signedRequired,
            'total_required'          => $totalRequired,
            'signed_total'            => $signedTotal,
            'total_enabled'           => count($items),
            'all_required_signed'     => $totalRequired > 0 && $signedRequired === $totalRequired,
            'pending_required_titles' => $pendingTitles,
        ];
    }

    /** @return array<string, mixed>|null */
    public function activeSignatureRequest(int $applicationId): ?array
    {
        return (new SignatureRequest())->activeForCollectorApplication($applicationId);
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSignatureRequests(int $applicationId): array
    {
        return (new SignatureRequest())->activeForCollectorApplicationList($applicationId);
    }

    /**
     * @return array{total:int, submitted:int, pending:int, approved:int}
     */
    public function documentProgress(int|string $applicationId): array
    {
        $docs = (new CollectorApplicationDocument())->findByApplication((int) $applicationId);
        $total = count($docs);
        $submitted = 0;
        $pending = 0;
        $approved = 0;

        foreach ($docs as $doc) {
            $st = (string) ($doc['status'] ?? '');
            if (in_array($st, ['enviado', 'em_analise', 'aprovado'], true)) {
                ++$submitted;
            }
            if (in_array($st, ['pendente', 'substituir', 'reprovado'], true)) {
                ++$pending;
            }
            if ($st === 'aprovado') {
                ++$approved;
            }
        }

        return [
            'total'     => $total,
            'submitted' => $submitted,
            'pending'   => $pending,
            'approved'  => $approved,
        ];
    }

    public function allDocumentsSubmitted(int|string $applicationId): bool
    {
        $progress = $this->documentProgress($applicationId);

        return $progress['total'] > 0
            && $progress['submitted'] === $progress['total']
            && $progress['pending'] === 0;
    }

    public function syncDocumentStatus(int|string $applicationId): void
    {
        $docs = (new CollectorApplicationDocument())->findByApplication((int) $applicationId, true);
        if ($docs === []) {
            return;
        }

        $progress = $this->documentProgress((int) $applicationId);
        $total = $progress['total'];
        $submitted = $progress['submitted'];
        $pending = $progress['pending'];
        $approved = $progress['approved'];

        $hasCorrection = false;
        foreach ($docs as $doc) {
            $st = (string) ($doc['status'] ?? '');
            if (in_array($st, ['substituir', 'reprovado'], true)) {
                $hasCorrection = true;
                break;
            }
        }

        if ($pending === $total) {
            $documentStatus = 'solicitado';
        } elseif ($submitted > 0 && $pending > 0) {
            $documentStatus = $hasCorrection ? 'pendente_correcao' : 'parcial';
        } elseif ($submitted === $total && $approved === $total) {
            $documentStatus = 'aprovado';
        } elseif ($submitted === $total) {
            $documentStatus = 'enviado';
        } else {
            $documentStatus = 'parcial';
        }

        $app = $this->findById($applicationId);
        $status = (string) ($app['status'] ?? '');
        $updates = ['document_status' => $documentStatus];

        if (!in_array($status, ['aprovado', 'reprovado', 'acesso_preparado', 'acesso_liberado'], true)) {
            if ($submitted === $total && $pending === 0) {
                $updates['status'] = 'documentos_enviados';
                $updates['documents_submitted_at'] = date('Y-m-d H:i:s');
            } elseif ($hasCorrection) {
                $updates['status'] = 'ajustes_solicitados';
                $updates['documents_submitted_at'] = null;
            } else {
                $updates['status'] = 'documentos_solicitados';
                if ($submitted < $total) {
                    $updates['documents_submitted_at'] = null;
                }
            }
        }

        $this->update($applicationId, $updates);
    }

    public function countByStatus(string $status): int
    {
        return $this->count(['status' => $status]);
    }

    public function countPendingReview(): int
    {
        $row = $this->query(
            "SELECT COUNT(*) AS c FROM `collector_applications`
              WHERE `archived_at` IS NULL
                AND `status` IN ('manifestacao_recebida','em_triagem','documentos_enviados','em_analise_documental','ajustes_solicitados')"
        )->fetch();

        return (int) ($row['c'] ?? 0);
    }

    public function countAwaitingDocuments(): int
    {
        return $this->count(['status' => 'documentos_solicitados']);
    }

    public function countApproved(): int
    {
        return $this->count(['status' => 'aprovado']);
    }

    public function countAccessReleased(): int
    {
        return $this->count(['access_status' => 'acesso_liberado']);
    }

    public function journeyStepKey(array $application): string
    {
        if (!empty($application['archived_at']) || (string) ($application['status'] ?? '') === 'arquivado') {
            return 'acesso';
        }

        $status = (string) ($application['status'] ?? '');
        return match (true) {
            in_array($status, ['acesso_preparado', 'acesso_liberado'], true) => 'acesso',
            $status === 'contrato_assinado' => 'acesso',
            $status === 'aguardando_assinatura_contratual' => 'assinatura',
            $status === 'aprovado' => 'aprovacao',
            in_array($status, ['em_analise_documental', 'documentos_enviados'], true) => 'analise',
            in_array($status, ['documentos_solicitados', 'ajustes_solicitados'], true) => 'documentos',
            $status === 'reprovado' => 'aprovacao',
            default => 'manifestacao',
        };
    }

    private function generateApplicationNumber(): string
    {
        return 'CAP-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
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
                $out[$col] = $data[$col];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $conditions = ['1=1'];
        $params     = [];

        if (empty($filters['show_archived'])) {
            $conditions[] = 'ca.`archived_at` IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $conditions[] = '(ca.`name` LIKE :q_name OR ca.`email` LIKE :q_email OR ca.`document_number` LIKE :q_doc OR ca.`city_state` LIKE :q_city OR ca.`segments` LIKE :q_seg OR ca.`application_number` LIKE :q_num)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_email'] = $like;
            $params['q_doc'] = $like;
            $params['q_city'] = $like;
            $params['q_seg'] = $like;
            $params['q_num'] = $like;
        }

        foreach (['status', 'document_status', 'review_status', 'access_status', 'source'] as $key) {
            $val = trim((string) ($filters[$key] ?? ''));
            if ($val !== '') {
                $conditions[] = 'ca.`' . $key . '` = :' . $key;
                $params[$key] = $val;
            }
        }

        if (!empty($filters['assigned_user_id'])) {
            $conditions[] = 'ca.`assigned_user_id` = :assigned_user_id';
            $params['assigned_user_id'] = (int) $filters['assigned_user_id'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    private function sanitizeText(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param array<string, mixed> $flat
     * @param array<int, string> $aliases
     */
    private function normalizeConsentFromFlat(array $flat, array $aliases): int
    {
        foreach ($aliases as $alias) {
            $key = strtolower($alias);
            if (!array_key_exists($key, $flat)) {
                continue;
            }
            if ($this->normalizeConsent($flat[$key]) === 1) {
                return 1;
            }
        }

        return 0;
    }

    private function normalizeConsent(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, ['1', 'true', 'sim', 'yes', 'on'], true) ? 1 : 0;
    }
}
